<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import HelpHint from '@/Components/HelpHint.vue';
import { ArrowLeft, Bot, RefreshCw, Code, FileText, GitBranch } from 'lucide-vue-next';

const props = defineProps({
    ritualId: {
        type: String,
        required: true,
    },
    runId: {
        type: String,
        required: true,
    },
});

const run = ref(null);
const ritual = ref(null);
const loading = ref(true);
const error = ref('');
const retrying = ref(false);

// Track view mode per phase: 'rendered' or 'json'
const viewModes = ref({});

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const [runRes, ritualRes] = await Promise.all([
            axios.get(`/agent/api/v1/org/ritual-runs/${props.runId}`),
            axios.get(`/agent/api/v1/org/rituals/${props.ritualId}`),
        ]);
        run.value = runRes.data.data;
        ritual.value = ritualRes.data.data;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load run details.';
    } finally {
        loading.value = false;
    }
};

const phases = computed(() => {
    return ritual.value?.phase_graph ?? [];
});

const phaseOutputs = computed(() => {
    return run.value?.phase_outputs ?? {};
});

const canRetry = computed(() => {
    return ['failed', 'partial'].includes(run.value?.state);
});

const retryRun = async () => {
    retrying.value = true;
    try {
        const { data } = await axios.post(`/agent/api/v1/org/ritual-runs/${props.runId}/retry`);
        router.visit(route('org.rituals.runs.show', {
            ritualId: props.ritualId,
            runId: data.data.id,
        }));
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to retry run.';
        retrying.value = false;
    }
};

const toggleViewMode = (phaseId) => {
    const current = viewModes.value[phaseId] ?? 'rendered';
    viewModes.value[phaseId] = current === 'rendered' ? 'json' : 'rendered';
};

const getViewMode = (phaseId) => {
    return viewModes.value[phaseId] ?? 'rendered';
};

const stateBadgeVariant = (state) => {
    const variants = {
        succeeded: 'default',
        failed: 'destructive',
        partial: 'secondary',
        running: 'secondary',
        queued: 'outline',
        cancelled: 'outline',
    };
    return variants[state] || 'secondary';
};

const formatDuration = (ms) => {
    if (!ms) return '-';
    if (ms < 1000) return `${ms}ms`;
    const seconds = Math.round(ms / 1000);
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const remainingSecs = seconds % 60;
    return `${minutes}m ${remainingSecs}s`;
};

onMounted(load);
</script>

<template>
    <AppLayout title="Ritual Run">
        <Head :title="`Ritual Run — ${run?.state ?? 'Loading...'}`" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('org.rituals.show', { id: ritualId })">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Bot class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Ritual Run</h2>
                        <HelpHint
                            ui-key="org.rituals.detail"
                            short-text="Ritual run phases, delegation graphs, and phase outputs."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="canRetry"
                        variant="outline"
                        :disabled="retrying"
                        @click="retryRun"
                    >
                        <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': retrying }" />
                        Retry Failed Phases
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <div v-if="loading" class="flex flex-col items-center justify-center py-12 gap-4">
                    <Skeleton class="h-4 w-48" />
                    <Skeleton class="h-4 w-32" />
                    <Skeleton class="h-32 w-full max-w-2xl" />
                </div>

                <template v-else-if="run">
                    <!-- Run Summary -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="grid grid-cols-2 gap-6 md:grid-cols-5">
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">State</span>
                                    <div class="mt-2">
                                        <Badge :variant="stateBadgeVariant(run.state)">{{ run.state }}</Badge>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Correlation</span>
                                    <div class="mt-2 text-sm text-foreground font-mono truncate" :title="run.correlation_id">
                                        {{ run.correlation_id?.substring(0, 8) ?? '-' }}
                                    </div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Started</span>
                                    <div class="mt-2 text-sm text-foreground">
                                        {{ run.started_at ? new Date(run.started_at).toLocaleString() : '-' }}
                                    </div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Completed</span>
                                    <div class="mt-2 text-sm text-foreground">
                                        {{ run.completed_at ? new Date(run.completed_at).toLocaleString() : '-' }}
                                    </div>
                                </div>
                                <div v-if="run.delegation_graph_id">
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Graph</span>
                                    <div class="mt-2">
                                        <Link :href="route('agent.delegation.show', { graphId: run.delegation_graph_id })">
                                            <Button variant="outline" size="sm">
                                                <GitBranch class="mr-1 h-3 w-3" />
                                                View
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Phase Outputs -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-foreground">Phase Outputs</h3>

                        <Card v-for="phase in phases" :key="phase.id">
                            <CardHeader class="pb-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <CardTitle class="text-base">{{ phase.name ?? phase.id }}</CardTitle>
                                        <Badge
                                            v-if="phaseOutputs[phase.id]"
                                            :variant="stateBadgeVariant(phaseOutputs[phase.id]?.status)"
                                        >
                                            {{ phaseOutputs[phase.id]?.status ?? 'unknown' }}
                                        </Badge>
                                        <Badge v-else variant="outline">pending</Badge>
                                    </div>
                                    <Button
                                        v-if="phaseOutputs[phase.id]"
                                        variant="ghost"
                                        size="sm"
                                        @click="toggleViewMode(phase.id)"
                                    >
                                        <Code v-if="getViewMode(phase.id) === 'rendered'" class="mr-1 h-3 w-3" />
                                        <FileText v-else class="mr-1 h-3 w-3" />
                                        {{ getViewMode(phase.id) === 'rendered' ? 'JSON' : 'Rendered' }}
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <template v-if="phaseOutputs[phase.id]">
                                    <!-- Metadata row -->
                                    <div class="mb-4 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                        <span v-if="phaseOutputs[phase.id].duration_ms">
                                            Duration: {{ formatDuration(phaseOutputs[phase.id].duration_ms) }}
                                        </span>
                                        <span v-if="phaseOutputs[phase.id].attempt_number">
                                            Attempt #{{ phaseOutputs[phase.id].attempt_number }}
                                        </span>
                                        <span v-if="phaseOutputs[phase.id].exit_code != null">
                                            Exit: {{ phaseOutputs[phase.id].exit_code }}
                                        </span>
                                        <span v-if="phaseOutputs[phase.id].completed_at">
                                            {{ new Date(phaseOutputs[phase.id].completed_at).toLocaleString() }}
                                        </span>
                                    </div>

                                    <!-- Rendered view -->
                                    <div v-if="getViewMode(phase.id) === 'rendered'">
                                        <div
                                            v-if="phaseOutputs[phase.id].agent_output"
                                            class="prose prose-sm dark:prose-invert max-w-none rounded-lg border border-border bg-muted/30 p-4"
                                        >
                                            <MarkdownRenderer :markdown="phaseOutputs[phase.id].agent_output" />
                                        </div>
                                        <p v-else class="text-sm text-muted-foreground italic">
                                            No agent output captured for this phase.
                                        </p>
                                    </div>

                                    <!-- JSON view -->
                                    <div v-else>
                                        <pre class="overflow-x-auto rounded-lg border border-border bg-muted p-4 text-xs text-muted-foreground font-mono max-h-96 overflow-y-auto">{{ JSON.stringify(phaseOutputs[phase.id], null, 2) }}</pre>
                                    </div>
                                </template>

                                <p v-else class="py-4 text-center text-sm text-muted-foreground">
                                    No output yet for this phase.
                                </p>
                            </CardContent>
                        </Card>

                        <Card v-if="phases.length === 0">
                            <CardContent class="py-8 text-center text-muted-foreground">
                                No phase information available.
                            </CardContent>
                        </Card>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
