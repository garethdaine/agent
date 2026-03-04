<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    activeProjectionBuildId: {
        type: String,
        default: null,
    },
    rebuildingBuildId: {
        type: String,
        default: null,
    },
    freshness: {
        type: Object,
        required: true,
    },
    scheduler: {
        type: Object,
        required: true,
    },
    signals: {
        type: Object,
        required: true,
    },
    reasonState: {
        type: String,
        required: true,
    },
    navigation: {
        type: Object,
        required: true,
    },
});

const activeBuildAgeLabel = computed(() => {
    if (props.freshness.active_build_age_seconds == null) {
        return 'n/a';
    }

    return `${props.freshness.active_build_age_seconds}s`;
});
</script>

<template>
    <Head title="System Overview" />

    <AppLayout title="System Overview">
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">System Overview</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Projection freshness, delayed/unobservable telemetry split, and scheduler health.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.deployments">Deployments</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.escalations">Escalations</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.replayBuilds">Replay Builds</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.budgets">Budgets</Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h3 class="text-sm font-semibold">Projection Build</h3>
                        <p class="mt-2 text-sm text-muted-foreground">Active build: <span class="font-mono text-foreground">{{ activeProjectionBuildId ?? 'none' }}</span></p>
                        <p class="text-sm text-muted-foreground">Rebuilding build: <span class="font-mono text-foreground">{{ rebuildingBuildId ?? 'none' }}</span></p>
                        <p class="text-sm text-muted-foreground">active_build_age_seconds:
                            <span :class="freshness.active_build_is_stale ? 'text-amber-600' : 'text-foreground'">{{ activeBuildAgeLabel }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">reason_state: {{ reasonState }}</p>
                    </div>

                    <div class="rounded-lg border border-border bg-card p-4">
                        <h3 class="text-sm font-semibold">Scheduler</h3>
                        <p class="mt-2 text-sm text-muted-foreground">Status: <span class="text-foreground">{{ scheduler.status }}</span></p>
                        <p class="text-sm text-muted-foreground">Age: <span class="text-foreground">{{ scheduler.age_seconds ?? 'n/a' }}s</span></p>
                        <p class="text-sm text-muted-foreground">Last seen: <span class="text-foreground">{{ scheduler.last_seen_at ?? 'n/a' }}</span></p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <h3 class="text-sm font-semibold">Delayed telemetry (reason codes)</h3>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="signal in signals.delayed" :key="`delayed-${signal.id}`" class="rounded border border-border px-2 py-1">
                                <span class="font-mono text-xs">{{ signal.workflow_key }}</span>
                                <span class="ml-2">{{ signal.reason_code }}</span>
                            </li>
                            <li v-if="signals.delayed.length === 0" class="text-muted-foreground">No delayed incidents in active build scope.</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-border bg-card p-4">
                        <h3 class="text-sm font-semibold">Unobservable telemetry (reason codes)</h3>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="signal in signals.unobservable" :key="`unobservable-${signal.id}`" class="rounded border border-border px-2 py-1">
                                <span class="font-mono text-xs">{{ signal.workflow_key }}</span>
                                <span class="ml-2">{{ signal.reason_code }}</span>
                            </li>
                            <li v-if="signals.unobservable.length === 0" class="text-muted-foreground">No unobservable incidents in active build scope.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
