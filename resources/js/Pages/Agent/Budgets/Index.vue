<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    activeProjectionBuildId: {
        type: String,
        default: null,
    },
    monthUtc: {
        type: String,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    navigation: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Budgets" />

    <AppLayout title="Budgets">
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Budgets</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Monthly per-workflow budget utilization using canonical cost.</p>
                </div>
                <div class="flex gap-2">
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.deployments">Deployments</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.systemOverview">System Overview</Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground">
                    <p>Budget month (UTC): <span class="font-mono text-foreground">{{ monthUtc }}</span></p>
                    <p>Active projection build: <span class="font-mono text-foreground">{{ activeProjectionBuildId ?? 'none' }}</span></p>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Workflow</th>
                                <th class="px-4 py-3 text-left font-medium">Budget USD</th>
                                <th class="px-4 py-3 text-left font-medium">Canonical cost USD</th>
                                <th class="px-4 py-3 text-left font-medium">Utilization</th>
                                <th class="px-4 py-3 text-left font-medium">Warning / Enforcement</th>
                                <th class="px-4 py-3 text-left font-medium">Countability</th>
                                <th class="px-4 py-3 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in rows" :key="row.workflow_key">
                                <td class="px-4 py-3 font-mono text-xs">{{ row.workflow_key }}</td>
                                <td class="px-4 py-3">{{ row.monthly_budget_usd ?? 'n/a' }}</td>
                                <td class="px-4 py-3">{{ row.monthly_canonical_cost_usd ?? '0' }}</td>
                                <td class="px-4 py-3">{{ row.budget_utilization_percent != null ? `${row.budget_utilization_percent}%` : 'n/a' }}</td>
                                <td class="px-4 py-3">{{ row.warning_threshold_percent ?? 'n/a' }}% / {{ row.enforcement_threshold_percent ?? 'n/a' }}%</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded border border-border px-2 py-1 text-xs"
                                        :class="row.countability_reason === 'incident_open' ? 'border-amber-400 text-amber-700' : ''"
                                    >
                                        {{ row.countability_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <Link class="text-primary underline-offset-2 hover:underline" :href="row.links.deployment">Deployment</Link>
                                        <a class="text-primary underline-offset-2 hover:underline" :href="row.links.cost">Cost API</a>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td class="px-4 py-6 text-muted-foreground" colspan="7">No budget policy rows are configured yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
