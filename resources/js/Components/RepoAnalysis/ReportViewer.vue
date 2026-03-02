<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    reports: {
        type: Array,
        default: () => [],
    },
    canExport: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['export-latest']);
const selectedReportId = ref(null);

watch(
    () => props.reports,
    (nextReports) => {
        if (!Array.isArray(nextReports) || nextReports.length === 0) {
            selectedReportId.value = null;
            return;
        }

        const exists = nextReports.some((report) => report?.id === selectedReportId.value);
        if (!exists) {
            selectedReportId.value = nextReports[0]?.id ?? null;
        }
    },
    { immediate: true, deep: true }
);

const selectedReport = computed(() => {
    if (!Array.isArray(props.reports) || props.reports.length === 0) {
        return null;
    }

    return props.reports.find((report) => report?.id === selectedReportId.value) ?? props.reports[0];
});

const selectedPayload = computed(() => selectedReport.value?.payload_json ?? {});
const selectedProfile = computed(() => selectedPayload.value?.repository_profile ?? {});
const selectedCoverage = computed(() => selectedProfile.value?.coverage_gate ?? selectedPayload.value?.coverage ?? {});

const asList = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value.filter((item) => typeof item === 'string' && item.length > 0);
};

const codeList = (value) => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item) => {
            if (typeof item === 'string') {
                return item;
            }

            if (item && typeof item === 'object' && typeof item.code === 'string') {
                return item.code;
            }

            return '';
        })
        .filter((item) => item.length > 0);
};

const dependencySample = (pairs, limit = 16) => {
    if (!Array.isArray(pairs)) {
        return [];
    }

    return pairs
        .filter((pair) => typeof pair?.name === 'string' && pair.name.length > 0)
        .slice(0, limit)
        .map((pair) => `${pair.name}${pair.version ? ` (${pair.version})` : ''}`);
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between gap-3">
                <CardTitle>Reports</CardTitle>
                <Button v-if="canExport" variant="outline" size="sm" @click="emit('export-latest')">
                    Export
                </Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-3">
            <div v-if="reports.length > 0" class="space-y-3">
                <div class="grid gap-2 md:grid-cols-2">
                    <button
                        v-for="report in reports"
                        :key="report.id"
                        type="button"
                        class="rounded border p-3 text-left transition hover:bg-muted/60"
                        :class="selectedReport?.id === report.id ? 'border-primary bg-muted/40' : 'border-border'"
                        @click="selectedReportId = report.id"
                    >
                        <p class="text-sm font-medium">{{ report.report_version }} · {{ report.status }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">hash {{ report.report_hash }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">generated {{ report.generated_at ?? '—' }}</p>
                    </button>
                </div>

                <div v-if="selectedReport" class="rounded border border-border p-4">
                    <h4 class="text-sm font-semibold">Full Report</h4>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Session {{ selectedPayload.session_id ?? '—' }} · artifacts {{ selectedPayload.artifact_count ?? 0 }}
                    </p>

                    <div class="mt-3 space-y-3 text-xs">
                        <div v-if="selectedProfile?.overview">
                            <p class="font-semibold">Overview</p>
                            <p class="text-muted-foreground">
                                Stack: {{ asList(selectedProfile.overview.inferred_stack).join(', ') || 'Not inferred' }}
                            </p>
                            <p class="text-muted-foreground">
                                Files analyzed: {{ selectedProfile.overview.snapshot_file_count ?? 0 }}
                            </p>
                            <p class="text-muted-foreground truncate">
                                Project: {{ selectedProfile.overview.project_directory ?? '—' }}
                            </p>
                        </div>

                        <div v-if="selectedProfile?.dependencies">
                            <p class="font-semibold">Dependencies</p>
                            <p class="text-muted-foreground">
                                PHP runtime: {{ selectedProfile.dependencies.php?.runtime_count ?? 0 }}
                            </p>
                            <p class="text-muted-foreground">
                                Node runtime: {{ selectedProfile.dependencies.node?.runtime_count ?? 0 }}
                            </p>
                            <p v-if="dependencySample(selectedProfile.dependencies.php?.runtime).length > 0" class="text-muted-foreground">
                                PHP sample: {{ dependencySample(selectedProfile.dependencies.php?.runtime).join(', ') }}
                            </p>
                            <p v-if="dependencySample(selectedProfile.dependencies.node?.runtime).length > 0" class="text-muted-foreground">
                                Node sample: {{ dependencySample(selectedProfile.dependencies.node?.runtime).join(', ') }}
                            </p>
                        </div>

                        <div v-if="selectedProfile?.backend">
                            <p class="font-semibold">Backend Surface</p>
                            <p class="text-muted-foreground">
                                Routes {{ selectedProfile.backend.route_file_count ?? 0 }}, models {{ selectedProfile.backend.model_count ?? 0 }}, migrations {{ selectedProfile.backend.migration_count ?? 0 }}, jobs {{ selectedProfile.backend.job_count ?? 0 }}, events {{ selectedProfile.backend.event_count ?? 0 }}
                            </p>
                        </div>

                        <div v-if="selectedProfile?.frontend">
                            <p class="font-semibold">Frontend Surface</p>
                            <p class="text-muted-foreground">
                                Entrypoints {{ selectedProfile.frontend.entrypoint_count ?? 0 }} · package manifest {{ selectedProfile.frontend.has_package_manifest ? 'yes' : 'no' }}
                            </p>
                        </div>

                        <div v-if="selectedProfile?.testing">
                            <p class="font-semibold">Testing</p>
                            <p class="text-muted-foreground">
                                Test files: {{ selectedProfile.testing.test_file_count ?? 0 }}
                            </p>
                            <p v-if="asList(selectedProfile.testing.warnings).length > 0" class="text-amber-700 dark:text-amber-300">
                                Warnings: {{ asList(selectedProfile.testing.warnings).join(', ') }}
                            </p>
                        </div>

                        <div v-if="selectedProfile?.risk_hotspots">
                            <p class="font-semibold">Risk Hotspots</p>
                            <p class="text-muted-foreground">
                                Hotspots: {{ selectedProfile.risk_hotspots.hotspot_count ?? 0 }}
                            </p>
                            <p v-if="asList(selectedProfile.risk_hotspots.warnings).length > 0" class="text-amber-700 dark:text-amber-300">
                                Warnings: {{ asList(selectedProfile.risk_hotspots.warnings).join(', ') }}
                            </p>
                        </div>

                        <div v-if="selectedCoverage">
                            <p class="font-semibold">Coverage Gate</p>
                            <p :class="selectedCoverage.passed ? 'text-emerald-700 dark:text-emerald-300' : 'text-destructive'">
                                {{ selectedCoverage.passed ? 'Passed' : 'Blocked' }}
                                · tasks {{ selectedCoverage.completed_task_count ?? 0 }}/{{ selectedCoverage.task_count ?? 0 }}
                            </p>
                            <p v-if="codeList(selectedCoverage.blocking_failure_codes ?? selectedCoverage.blocking_failures).length > 0" class="text-destructive">
                                Blocking: {{ codeList(selectedCoverage.blocking_failure_codes ?? selectedCoverage.blocking_failures).join(', ') }}
                            </p>
                            <p v-if="codeList(selectedCoverage.warning_codes ?? selectedCoverage.warnings).length > 0" class="text-amber-700 dark:text-amber-300">
                                Warnings: {{ codeList(selectedCoverage.warning_codes ?? selectedCoverage.warnings).join(', ') }}
                            </p>
                        </div>

                        <div v-if="selectedProfile?.glossary">
                            <p class="font-semibold">Glossary</p>
                            <p class="text-muted-foreground">Task Graph: {{ selectedProfile.glossary.task_graph ?? '—' }}</p>
                            <p class="text-muted-foreground">Coverage Gate: {{ selectedProfile.glossary.coverage_gate ?? '—' }}</p>
                            <p class="text-muted-foreground">Artifacts: {{ selectedProfile.glossary.artifacts ?? '—' }}</p>
                        </div>

                        <div>
                            <p class="font-semibold">Raw Report Payload</p>
                            <pre class="mt-1 max-h-96 overflow-auto rounded bg-muted p-3 text-[11px]">{{ JSON.stringify(selectedPayload, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
            </div>

            <p v-else class="text-sm text-muted-foreground">No reports generated yet.</p>
        </CardContent>
    </Card>
</template>
