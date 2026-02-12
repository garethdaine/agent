<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const loading = ref(true);
const errorMessage = ref('');
const windowKey = ref('24h');
const payload = ref(null);

const metrics = computed(() => payload.value?.metrics ?? {
    runs_today: 0,
    success_rate_percent: 0,
    average_duration_ms: 0,
    backlog_count: 0,
    oldest_queued_age_seconds: 0,
});
const scheduler = computed(() => payload.value?.scheduler ?? { status: 'unknown', age_seconds: null });

const formatDuration = (ms) => {
    const seconds = Math.max(0, Math.floor(ms / 1000));
    const minutes = Math.floor(seconds / 60);
    const remSeconds = seconds % 60;

    if (minutes > 0) {
        return `${minutes}m ${remSeconds}s`;
    }

    return `${remSeconds}s`;
};

const loadMetrics = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/dashboard/metrics', {
            params: { window: windowKey.value },
        });
        payload.value = data.data ?? null;
    } catch (error) {
        errorMessage.value = error?.response?.data?.error?.message ?? 'Unable to load dashboard metrics.';
    } finally {
        loading.value = false;
    }
};

onMounted(loadMetrics);
</script>

<template>
    <AppLayout title="Dashboard">
        <Head title="Dashboard" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dashboard</h2>
                <div class="flex items-center gap-2">
                    <select
                        v-model="windowKey"
                        class="rounded border-gray-300 px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-900"
                        @change="loadMetrics"
                    >
                        <option value="24h">Last 24h</option>
                        <option value="7d">Last 7 days</option>
                    </select>
                    <button class="rounded border border-gray-300 px-2 py-1 text-sm hover:bg-gray-50 dark:hover:bg-gray-800" @click="loadMetrics">
                        Refresh
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p v-if="errorMessage" class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ errorMessage }}
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Runs Today</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ loading ? '…' : metrics.runs_today }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Success Rate ({{ windowKey }})</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ loading ? '…' : `${metrics.success_rate_percent}%` }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">Skipped runs excluded.</p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Avg Duration ({{ windowKey }})</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ loading ? '…' : formatDuration(metrics.average_duration_ms) }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Backlog Count</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ loading ? '…' : metrics.backlog_count }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Oldest Queued Age</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ loading ? '…' : formatDuration(metrics.oldest_queued_age_seconds * 1000) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">Computed globally across queued runs.</p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Scheduler Health</p>
                        <p
                            class="mt-2 text-2xl font-semibold"
                            :class="{
                                'text-green-600': scheduler.status === 'healthy',
                                'text-yellow-600': scheduler.status === 'degraded',
                                'text-red-600': scheduler.status === 'down',
                                'text-gray-500': scheduler.status === 'unknown',
                            }"
                        >
                            {{ loading ? '…' : scheduler.status }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500">Age: {{ loading ? '…' : (scheduler.age_seconds ?? 'n/a') }}s</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
