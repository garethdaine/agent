<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    graphId: {
        type: Number,
        required: true,
    },
    taskId: {
        type: Number,
        required: true,
    },
});

const task = ref(null);
const loading = ref(true);
const error = ref('');

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}/tasks/${props.taskId}`);
        task.value = data.data;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load task.';
    } finally {
        loading.value = false;
    }
};

const statusBadgeClass = (status) => {
    const classes = {
        pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        blocked: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200',
        ready: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
        assigned: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
        running: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-200',
        verifying: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-200',
        succeeded: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
        failed: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200',
        cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        passed: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
        skipped: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};

const hasPendingApproval = () => {
    if (!task.value?.verification_results) return false;
    return task.value.verification_results.some(r => r.step_type === 'human_approval' && r.verdict === 'pending');
};

onMounted(load);
</script>

<template>
    <AppLayout title="Task Detail">
        <Head :title="task?.name ?? 'Task Detail'" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <Link :href="route('agent.delegation.show', graphId)" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                            Back to Graph
                        </Link>
                        <span class="text-gray-400">/</span>
                    </div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ task?.name ?? 'Loading...' }}</h2>
                </div>
                <Link
                    v-if="hasPendingApproval()"
                    :href="route('agent.delegation.task.approve', { graphId: graphId, taskId: taskId })"
                    class="rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-500"
                >
                    Review & Approve
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <p v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </p>

                <div v-if="loading" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Loading...
                </div>

                <template v-else-if="task">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div>
                                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</span>
                                <div class="mt-1">
                                    <span :class="statusBadgeClass(task.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ task.status }}</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Assigned Profile</span>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ task.assigned_profile?.name ?? '-' }}</div>
                            </div>
                            <div>
                                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Started</span>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ task.started_at ?? '-' }}</div>
                            </div>
                            <div>
                                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Finished</span>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ task.finished_at ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Contract</h3>
                        </div>
                        <div class="p-6">
                            <pre class="overflow-x-auto rounded bg-gray-50 p-4 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ JSON.stringify(task.contract_json, null, 2) }}</pre>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Attempts</h3>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Profile</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Duration</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Started</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Finished</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="attempt in task.attempts" :key="attempt.id">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ attempt.attempt_number }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span :class="statusBadgeClass(attempt.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ attempt.status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ attempt.profile?.name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ attempt.duration_ms ? `${attempt.duration_ms}ms` : '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ attempt.started_at ?? '-' }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ attempt.finished_at ?? '-' }}</td>
                                </tr>
                                <tr v-if="!task.attempts || task.attempts.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No attempts yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Verification History</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div v-for="result in task.verification_results" :key="result.id" class="rounded border border-gray-200 p-4 dark:border-gray-700">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ result.step_type }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">(Step {{ result.step_order }})</span>
                                    </div>
                                    <span :class="statusBadgeClass(result.verdict)" class="rounded-full px-2 py-1 text-xs font-medium">{{ result.verdict }}</span>
                                </div>
                                <div v-if="result.evidence_json" class="mt-2">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Evidence:</span>
                                    <pre class="mt-1 overflow-x-auto rounded bg-gray-50 p-2 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ JSON.stringify(result.evidence_json, null, 2) }}</pre>
                                </div>
                            </div>
                            <div v-if="!task.verification_results || task.verification_results.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                                No verification results yet.
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
