<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/Button.vue';
import HelpHint from '@/Components/HelpHint.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, Rocket } from 'lucide-vue-next';

const props = defineProps({
    workflowKey: {
        type: String,
        required: true,
    },
    activeProjectionBuildId: {
        type: String,
        default: null,
    },
    freshness: {
        type: Object,
        required: true,
    },
    reliability: {
        type: Object,
        default: null,
    },
    governanceState: {
        type: Object,
        required: true,
    },
    deepLinks: {
        type: Object,
        required: true,
    },
    navigation: {
        type: Object,
        required: true,
    },
    governance: {
        type: Object,
        required: true,
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
    <Head :title="`Deployment ${workflowKey}`" />

    <AppLayout :title="`Deployment ${workflowKey}`">
        <template #header>
            <div class="flex items-center justify-between gap-4 min-w-0">
                <div class="flex items-center gap-3 min-w-0">
                    <Link :href="navigation.deployments" class="shrink-0">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Rocket class="h-4 w-4 text-primary" />
                    </div>
                    <div class="flex items-center gap-2 min-w-0">
                        <h2 class="text-base font-semibold text-foreground truncate">Deployment Detail</h2>
                        <HelpHint
                            ui-key="deployments.detail"
                            short-text="Inspect individual deployment details and status."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.dashboard">Dashboard</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.replayBuilds">Replay Builds</Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border border-border bg-card p-4 text-sm">
                    <p class="text-muted-foreground">Active projection build: <span class="font-mono text-foreground">{{ activeProjectionBuildId ?? 'none' }}</span></p>
                    <p class="text-muted-foreground">active_build_age_seconds: <span :class="freshness.active_build_is_stale ? 'text-amber-600' : 'text-foreground'">{{ freshnessLabel }}</span></p>
                    <p class="mt-2">
                        <span class="rounded border border-border px-2 py-1 text-xs">{{ governanceState.countabilityLabel }}</span>
                    </p>
                </div>

                <div class="rounded-lg border border-border bg-card p-4">
                    <h3 class="text-sm font-semibold">Deep Links</h3>
                    <div class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                        <a class="text-primary underline-offset-2 hover:underline" :href="deepLinks.reliability">Reliability</a>
                        <a class="text-primary underline-offset-2 hover:underline" :href="deepLinks.cost">Cost</a>
                        <a class="text-primary underline-offset-2 hover:underline" :href="deepLinks.attemptLineage">Attempt lineage</a>
                        <a class="text-primary underline-offset-2 hover:underline" :href="deepLinks.gateTransitions">Gate transitions</a>
                        <a class="text-primary underline-offset-2 hover:underline" :href="deepLinks.escalationHistory">Escalation history</a>
                        <Link class="text-primary underline-offset-2 hover:underline" :href="deepLinks.replayBuilds">Replay builds</Link>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-4">
                    <h3 class="text-sm font-semibold">Role-Gated Governance Controls</h3>
                    <div class="mt-3 flex flex-wrap gap-2 text-sm">
                        <button
                            v-if="governance.canPauseResume"
                            type="button"
                            class="rounded-md border border-border px-3 py-2"
                        >Pause workflow</button>
                        <button
                            v-if="governance.canPauseResume"
                            type="button"
                            class="rounded-md border border-border px-3 py-2"
                        >Resume workflow</button>
                        <button
                            v-if="governance.canManageEscalations"
                            type="button"
                            class="rounded-md border border-border px-3 py-2"
                        >Escalation lifecycle</button>
                        <button
                            v-if="governance.canManageReplay"
                            type="button"
                            class="rounded-md border border-border px-3 py-2"
                        >Start replay build</button>
                        <button
                            v-if="governance.canManageReplay"
                            type="button"
                            class="rounded-md border border-border px-3 py-2"
                        >Activate replay build</button>
                        <p
                            v-if="!governance.canPauseResume && !governance.canManageEscalations && !governance.canManageReplay"
                            class="text-muted-foreground"
                        >No governance actions available for this account.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
