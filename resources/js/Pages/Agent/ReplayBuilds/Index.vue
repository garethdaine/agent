<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { RotateCcw } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

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
    builds: {
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

const activeBuildAgeLabel = computed(() => {
    if (props.freshness.active_build_age_seconds == null) {
        return 'n/a';
    }

    return `${props.freshness.active_build_age_seconds}s`;
});
</script>

<template>
    <Head title="Replay Builds" />

    <AppLayout title="Replay Builds">
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <RotateCcw class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">Replay Builds</h2>
                        <HelpHint
                            ui-key="replay-builds.overview"
                            short-text="Track projection build history and serving-build status."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.deployments">Deployments</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.systemOverview">System Overview</Link>
                    <button
                        v-if="governance.canManageReplay"
                        type="button"
                        class="rounded-md border border-border px-3 py-2 text-sm"
                    >Start replay build</button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border border-border bg-card p-4 text-sm">
                    <p class="text-muted-foreground">Active build: <span class="font-mono text-foreground">{{ activeProjectionBuildId ?? 'none' }}</span></p>
                    <p class="text-muted-foreground">Rebuilding build: <span class="font-mono text-foreground">{{ rebuildingBuildId ?? 'none' }}</span></p>
                    <p class="text-muted-foreground">active_build_age_seconds:
                        <span :class="freshness.active_build_is_stale ? 'text-amber-600' : 'text-foreground'">{{ activeBuildAgeLabel }}</span>
                    </p>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card">
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Build ID</th>
                                <th class="px-4 py-3 text-left font-medium">Status</th>
                                <th class="px-4 py-3 text-left font-medium">Serving</th>
                                <th class="px-4 py-3 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="build in builds" :key="build.id">
                                <td class="px-4 py-3 font-mono text-xs">{{ build.id }}</td>
                                <td class="px-4 py-3">{{ build.status }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="build.is_serving" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700">active serving build</span>
                                    <span v-else class="rounded border border-border px-2 py-1 text-xs text-muted-foreground">non-serving build</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Link class="text-primary underline-offset-2 hover:underline" :href="build.links.detail">View build</Link>
                                </td>
                            </tr>
                            <tr v-if="builds.length === 0">
                                <td class="px-4 py-6 text-muted-foreground" colspan="4">No projection builds recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
