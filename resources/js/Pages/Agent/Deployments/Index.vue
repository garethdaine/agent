<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    activeProjectionBuildId: {
        type: String,
        default: null,
    },
    freshness: {
        type: Object,
        required: true,
    },
    navigation: {
        type: Object,
        required: true,
    },
    workflows: {
        type: Array,
        default: () => [],
    },
});

const freshnessLabel = computed(() => {
    if (props.freshness.active_build_age_seconds == null) {
        return 'n/a';
    }

    return `${props.freshness.active_build_age_seconds}s`;
});
</script>

<template>
    <Head title="Agent Deployments" />

    <AppLayout title="Agent Deployments">
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Agent Deployments</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Deployment reliability/cost/escalation visibility scoped to the active projection build.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.systemOverview">System Overview</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.escalations">Escalations</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.budgets">Budgets</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.replayBuilds">Replay Builds</Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border border-border bg-card p-4">
                    <p class="text-sm text-muted-foreground">Active projection build: <span class="font-mono text-foreground">{{ activeProjectionBuildId ?? 'none' }}</span></p>
                    <p class="text-sm text-muted-foreground">active_build_age_seconds: <span :class="freshness.active_build_is_stale ? 'text-amber-600' : 'text-foreground'">{{ freshnessLabel }}</span></p>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Workflow</th>
                                <th class="px-4 py-3 text-left font-medium">ReliabilityScore</th>
                                <th class="px-4 py-3 text-left font-medium">DegradedRate</th>
                                <th class="px-4 py-3 text-left font-medium">HardFailRate</th>
                                <th class="px-4 py-3 text-left font-medium">BudgetUtilization</th>
                                <th class="px-4 py-3 text-left font-medium">EscalationEvents</th>
                                <th class="px-4 py-3 text-left font-medium">Countability</th>
                                <th class="px-4 py-3 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="workflow in workflows" :key="workflow.workflow_key">
                                <td class="px-4 py-3 font-mono text-xs">{{ workflow.workflow_key }}</td>
                                <td class="px-4 py-3">{{ workflow.reliability_score ?? 'n/a' }}</td>
                                <td class="px-4 py-3">{{ workflow.degraded_rate ?? 'n/a' }}</td>
                                <td class="px-4 py-3">{{ workflow.hard_fail_rate ?? 'n/a' }}</td>
                                <td class="px-4 py-3">{{ workflow.budget_utilization_percent != null ? `${workflow.budget_utilization_percent}%` : 'n/a' }}</td>
                                <td class="px-4 py-3">{{ workflow.escalation_events ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded border border-border px-2 py-1 text-xs"
                                        :class="workflow.countability_reason === 'incident_open' ? 'border-amber-400 text-amber-700' : ''"
                                    >
                                        {{ workflow.countability_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <Link class="text-primary underline-offset-2 hover:underline" :href="workflow.links.detail">View details</Link>
                                </td>
                            </tr>
                            <tr v-if="workflows.length === 0">
                                <td class="px-4 py-6 text-muted-foreground" colspan="8">No active-build workflow rows available yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
