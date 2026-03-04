<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    build: {
        type: Object,
        required: true,
    },
    activeProjectionBuildId: {
        type: String,
        default: null,
    },
    rebuildingBuildId: {
        type: String,
        default: null,
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
    if (props.build.active_build_age_seconds == null) {
        return 'n/a';
    }

    return `${props.build.active_build_age_seconds}s`;
});
</script>

<template>
    <Head :title="`Replay Build ${build.id}`" />

    <AppLayout :title="`Replay Build ${build.id}`">
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Replay Build Detail</h2>
                    <p class="mt-1 font-mono text-xs text-muted-foreground">{{ build.id }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.replayBuilds">Back to replay builds</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.systemOverview">System Overview</Link>
                    <button
                        v-if="governance.canManageReplay && !build.isActive"
                        type="button"
                        class="rounded-md border border-border px-3 py-2 text-sm"
                    >Activate replay build</button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border border-border bg-card p-4 text-sm">
                    <p class="text-muted-foreground">Status: <span class="text-foreground">{{ build.status }}</span></p>
                    <p class="text-muted-foreground">Serving state:
                        <span v-if="build.isServing" class="text-emerald-700">active serving build</span>
                        <span v-else class="text-muted-foreground">non-serving build</span>
                    </p>
                    <p class="text-muted-foreground">Active projection build pointer: <span class="font-mono text-foreground">{{ activeProjectionBuildId ?? 'none' }}</span></p>
                    <p class="text-muted-foreground">Rebuilding pointer: <span class="font-mono text-foreground">{{ rebuildingBuildId ?? 'none' }}</span></p>
                    <p class="text-muted-foreground">active_build_age_seconds:
                        <span :class="build.active_build_is_stale ? 'text-amber-600' : 'text-foreground'">{{ activeBuildAgeLabel }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground">stale_after_seconds: {{ build.stale_after_seconds ?? 'n/a' }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
