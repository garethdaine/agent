<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    graphId: {
        type: Number,
        required: true,
    },
});

const graph = ref(null);
const tasks = ref([]);
const events = ref([]);
const loading = ref(true);
const error = ref('');
const showEvents = ref(false);

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const [graphRes, tasksRes] = await Promise.all([
            axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}`),
            axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}/tasks`),
        ]);
        graph.value = graphRes.data.data;
        tasks.value = tasksRes.data.data;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load graph.';
    } finally {
        loading.value = false;
    }
};

const loadEvents = async () => {
    try {
        const { data } = await axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}/events`);
        events.value = data.data;
    } catch (e) {
        console.error('Failed to load events', e);
    }
};

const startGraph = async () => {
    try {
        await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/start`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to start graph.';
    }
};

const cancelGraph = async () => {
    try {
        await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/cancel`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to cancel graph.';
    }
};

const cloneGraph = async () => {
    try {
        const { data } = await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/clone`);
        router.visit(route('agent.delegation.show', data.data.id));
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to clone graph.';
    }
};

const statusBadgeClass = (status) => {
    const classes = {
        draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        validating: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
        ready: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
        running: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-200',
        succeeded: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200',
        failed: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200',
        partial: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200',
        cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        blocked: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200',
        assigned: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
        verifying: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-200',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};

const canStart = computed(() => graph.value?.status === 'ready');
const canCancel = computed(() => graph.value?.status === 'running');

onMounted(() => {
    load();
});
</script>

<template>
    <AppLayout title="Delegation Graph">
        <Head :title="graph?.name ?? 'Delegation Graph'" />

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ graph?.name ?? 'Loading...' }}</h2>
                    <p v-if="graph?.description" class="text-sm text-gray-500 dark:text-gray-400">{{ graph.description }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="canStart"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500"
                        @click="startGraph"
                    >
                        Start
                    </button>
                    <button
                        v-if="canCancel"
                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500"
                        @click="cancelGraph"
                    >
                        Cancel
                    </button>
                    <button
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        @click="cloneGraph"
                    >
                        Clone
                    </button>
                </div>
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

                <template v-else-if="graph">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div>
                                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</span>
                                <div class="mt-1">
                                    <span :class="statusBadgeClass(graph.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ graph.status }}</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Tasks</span>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ graph.tasks_completed ?? 0 }}/{{ graph.tasks_total ?? 0 }}</div>
                            </div>
                            <div>
                                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Started</span>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ graph.started_at ?? '-' }}</div>
                            </div>
                            <div>
                                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Finished</span>
                                <div class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ graph.finished_at ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Tasks</h3>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Assigned Profile</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Verification</th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="task in tasks" :key="task.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer" @click="$inertia.visit(route('agent.delegation.task', { graphId: graphId, taskId: task.id }))">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ task.name }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span :class="statusBadgeClass(task.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ task.status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ task.assigned_profile?.name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                        <span v-if="task.verification_status" :class="statusBadgeClass(task.verification_status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ task.verification_status }}</span>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs">
                                        <Link :href="route('agent.delegation.task', { graphId: graphId, taskId: task.id })" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50" @click.stop>View</Link>
                                    </td>
                                </tr>
                                <tr v-if="tasks.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No tasks in this graph.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <button class="flex w-full items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700" @click="showEvents = !showEvents; if (showEvents && events.length === 0) loadEvents()">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Events</h3>
                            <span class="text-sm text-gray-500">{{ showEvents ? 'Hide' : 'Show' }}</span>
                        </button>
                        <div v-if="showEvents" class="max-h-96 overflow-y-auto p-4">
                            <div v-if="events.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No events yet.</div>
                            <div v-else class="space-y-2">
                                <div v-for="event in events" :key="event.id" class="rounded border border-gray-200 p-3 text-sm dark:border-gray-700">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ event.event_type }}</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ event.event_ts }}</span>
                                    </div>
                                    <pre v-if="event.payload_json" class="mt-2 overflow-x-auto text-xs text-gray-600 dark:text-gray-300">{{ JSON.stringify(event.payload_json, null, 2) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
