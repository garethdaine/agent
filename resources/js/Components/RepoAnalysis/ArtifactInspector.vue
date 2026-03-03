<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';

defineProps({
    artifacts: {
        type: Array,
        default: () => [],
    },
});

const artifactDescriptions = {
    snapshot_manifest: 'Deterministic repository file manifest generated during snapshot.',
    filesystem_manifest: 'Filesystem inventory used as the base analyzer input.',
    dependency_manifest: 'Detected package manifests, lockfiles, and ecosystems.',
    routing_surface: 'Detected routing/API surface files across frameworks.',
    data_model_surface: 'Detected data-model, migration, and schema surfaces.',
    async_workflows_surface: 'Detected background-job, worker, and event/message surfaces.',
    frontend_surface: 'Frontend entrypoint/module surface map.',
    laravel_routes: 'Legacy route surface artifact.',
    laravel_models_migrations: 'Legacy model/migration artifact.',
    queue_jobs_events: 'Legacy queue/events artifact.',
    frontend_module_graph: 'Legacy frontend surface artifact.',
    test_coverage_map: 'Discovered tests and empty-suite warnings.',
    risk_hotspot: 'Files considered high-impact hotspots for review.',
    coverage_validation: 'Phase 4 gate summary used to block/allow completion.',
    ai_analysis_section: 'AI-authored analysis section for narrative synthesis.',
};

const artifactDescription = (artifact) => artifactDescriptions[String(artifact?.artifact_type ?? '')] ?? 'Analyzer output artifact.';

const artifactSummary = (artifact) => {
    const rawPayload = artifact?.payload_json;
    const payload = (rawPayload && typeof rawPayload === 'object' && rawPayload.payload && typeof rawPayload.payload === 'object')
        ? rawPayload.payload
        : rawPayload;

    if (!payload || typeof payload !== 'object') {
        return 'No payload summary available.';
    }

    const candidates = [
        ['file_count', 'Files'],
        ['route_file_count', 'Route files'],
        ['model_count', 'Models'],
        ['migration_count', 'Migrations'],
        ['schema_count', 'Schemas'],
        ['job_count', 'Jobs'],
        ['event_count', 'Events'],
        ['entrypoint_count', 'Entrypoints'],
        ['test_file_count', 'Test files'],
        ['hotspot_count', 'Hotspots'],
    ];

    const parts = candidates
        .filter(([key]) => typeof payload[key] === 'number')
        .map(([key, label]) => `${label}: ${payload[key]}`);

    if (parts.length > 0) {
        return parts.join(' · ');
    }

    const warningCount = Array.isArray(payload.warnings) ? payload.warnings.length : 0;
    if (warningCount > 0) {
        return `Warnings: ${warningCount}`;
    }

    return 'Structured payload available.';
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Artifacts</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
            <div v-for="artifact in artifacts" :key="artifact.id" class="rounded border border-border p-3">
                <p class="text-sm font-medium">{{ artifact.artifact_key }}</p>
                <p class="mt-1 text-xs text-muted-foreground">type {{ artifact.artifact_type }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ artifactDescription(artifact) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ artifactSummary(artifact) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">hash {{ artifact.content_hash }}</p>
            </div>
            <p v-if="artifacts.length === 0" class="text-sm text-muted-foreground">No artifacts recorded yet.</p>
        </CardContent>
    </Card>
</template>
