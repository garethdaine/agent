<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
const submitting = ref(false);
const error = ref('');

const verdict = ref('passed');
const notes = ref('');

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

const submit = async () => {
    submitting.value = true;
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/tasks/${props.taskId}/verification/resolve`, {
            verdict: verdict.value,
            evidence_json: {
                notes: notes.value,
                reviewed_at: new Date().toISOString(),
            },
        });
        router.visit(route('agent.delegation.task', { graphId: props.graphId, taskId: props.taskId }));
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to submit verification.';
    } finally {
        submitting.value = false;
    }
};

const latestAttempt = () => {
    if (!task.value?.attempts || task.value.attempts.length === 0) return null;
    return task.value.attempts[task.value.attempts.length - 1];
};

onMounted(load);
</script>

<template>
    <AppLayout title="Verification Approval">
        <Head title="Verification Approval" />

        <template #header>
            <div>
                <div class="flex items-center gap-2">
                    <Link :href="route('agent.delegation.task', { graphId: graphId, taskId: taskId })" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Back to Task
                    </Link>
                    <span class="text-gray-400">/</span>
                </div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Human Verification Approval</h2>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-6">
                <p v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </p>

                <div v-if="loading" class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Loading...
                </div>

                <template v-else-if="task">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Task Context</h3>
                        <div class="space-y-4">
                            <div>
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Task Name</span>
                                <p class="text-gray-900 dark:text-gray-100">{{ task.name }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Contract</span>
                                <pre class="mt-1 overflow-x-auto rounded bg-gray-50 p-4 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ JSON.stringify(task.contract_json, null, 2) }}</pre>
                            </div>
                        </div>
                    </div>

                    <div v-if="latestAttempt()" class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Latest Attempt Output</h3>
                        <div class="space-y-2">
                            <div class="flex items-center gap-4 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">Attempt #{{ latestAttempt().attempt_number }}</span>
                                <span class="text-gray-500 dark:text-gray-400">Status: {{ latestAttempt().status }}</span>
                                <span v-if="latestAttempt().duration_ms" class="text-gray-500 dark:text-gray-400">Duration: {{ latestAttempt().duration_ms }}ms</span>
                            </div>
                            <div v-if="latestAttempt().metadata_json" class="mt-2">
                                <pre class="overflow-x-auto rounded bg-gray-50 p-4 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ JSON.stringify(latestAttempt().metadata_json, null, 2) }}</pre>
                            </div>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">No output metadata available.</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Your Decision</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Verdict</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="verdict" type="radio" value="passed" class="text-green-600" />
                                        <span class="text-sm text-gray-900 dark:text-gray-100">Approve</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input v-model="verdict" type="radio" value="failed" class="text-red-600" />
                                        <span class="text-sm text-gray-900 dark:text-gray-100">Reject</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes (optional)</label>
                                <textarea v-model="notes" rows="4" class="block w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="Add any notes about your decision..." />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Link :href="route('agent.delegation.task', { graphId: graphId, taskId: taskId })" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            Cancel
                        </Link>
                        <button
                            class="rounded-md px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            :class="verdict === 'passed' ? 'bg-green-600 hover:bg-green-500' : 'bg-red-600 hover:bg-red-500'"
                            :disabled="submitting"
                            @click="submit"
                        >
                            {{ submitting ? 'Submitting...' : (verdict === 'passed' ? 'Approve' : 'Reject') }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
