<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

defineProps({
    activeProjectionBuildId: {
        type: String,
        default: null,
    },
    incidents: {
        type: Array,
        default: () => [],
    },
    delayedSignals: {
        type: Array,
        default: () => [],
    },
    unobservableSignals: {
        type: Array,
        default: () => [],
    },
    governance: {
        type: Object,
        required: true,
    },
    navigation: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Escalations" />

    <AppLayout title="Escalations">
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <AlertTriangle class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Escalations</h2>
                        <HelpHint
                            ui-key="escalations.overview"
                            short-text="Track incident lifecycle and reason-code visibility."
                            learn-more-href="/docs/overview"
                        />
                    </div>
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
                    Active projection build: <span class="font-mono text-foreground">{{ activeProjectionBuildId ?? 'none' }}</span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h3 class="text-sm font-semibold">Delayed</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ delayedSignals.length }} incident(s) with `telemetry_delayed`.</p>
                    </div>
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h3 class="text-sm font-semibold">Unobservable</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ unobservableSignals.length }} incident(s) with `telemetry_unobservable`.</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Workflow</th>
                                <th class="px-4 py-3 text-left font-medium">Trigger</th>
                                <th class="px-4 py-3 text-left font-medium">Status</th>
                                <th class="px-4 py-3 text-left font-medium">Reason code</th>
                                <th class="px-4 py-3 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="incident in incidents" :key="incident.id">
                                <td class="px-4 py-3 font-mono text-xs">{{ incident.workflow_key }}</td>
                                <td class="px-4 py-3">{{ incident.trigger_type }}</td>
                                <td class="px-4 py-3">{{ incident.status }}</td>
                                <td class="px-4 py-3">{{ incident.reason_code ?? 'n/a' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Link class="text-primary underline-offset-2 hover:underline" :href="incident.links.deployment">Deployment</Link>
                                        <a class="text-primary underline-offset-2 hover:underline" :href="incident.links.history">History</a>
                                        <button
                                            v-if="governance.canManageEscalations"
                                            type="button"
                                            class="rounded-md border border-border px-2 py-1 text-xs"
                                        >Resolve incident</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="incidents.length === 0">
                                <td class="px-4 py-6 text-muted-foreground" colspan="5">No escalation incidents in active-build scope.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
