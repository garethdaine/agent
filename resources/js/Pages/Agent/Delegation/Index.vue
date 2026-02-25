<script setup>
import { ref, reactive, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

const graphs = ref([]);
const loading = ref(true);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

const filters = reactive({
    status: '',
    page: 1,
});

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/delegation/graphs', { params: filters });
        graphs.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load delegation graphs.';
    } finally {
        loading.value = false;
    }
};

const setPage = async (page) => {
    filters.page = page;
    await load();
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
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};

onMounted(load);
</script>

<template>
    <AppLayout title="Delegation Graphs">
        <Head title="Delegation Graphs" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Delegation Graphs</h2>
                <Link :href="route('agent.delegation.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Create Graph
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <div class="flex items-center gap-2">
                    <button
                        class="rounded border px-3 py-1 text-xs font-semibold"
                        :class="filters.status === '' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700/50'"
                        @click="filters.status = ''; filters.page = 1; load()"
                    >
                        All
                    </button>
                    <button
                        class="rounded border px-3 py-1 text-xs font-semibold"
                        :class="filters.status === 'running' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700/50'"
                        @click="filters.status = 'running'; filters.page = 1; load()"
                    >
                        Active
                    </button>
                    <button
                        class="rounded border px-3 py-1 text-xs font-semibold"
                        :class="filters.status === 'succeeded' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700/50'"
                        @click="filters.status = 'succeeded'; filters.page = 1; load()"
                    >
                        Completed
                    </button>
                    <button
                        class="rounded border px-3 py-1 text-xs font-semibold"
                        :class="filters.status === 'failed' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/20 dark:text-indigo-200' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700/50'"
                        @click="filters.status = 'failed'; filters.page = 1; load()"
                    >
                        Failed
                    </button>
                </div>

                <p v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </p>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tasks</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Created</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="loading">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Loading...</td>
                            </tr>
                            <tr v-for="graph in graphs" :key="graph.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer" @click="$inertia.visit(route('agent.delegation.show', graph.id))">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ graph.name }}</div>
                                    <div v-if="graph.description" class="text-xs text-gray-500 dark:text-gray-400">{{ graph.description }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span :class="statusBadgeClass(graph.status)" class="rounded-full px-2 py-1 text-xs font-medium">{{ graph.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                    {{ graph.tasks_completed ?? 0 }}/{{ graph.tasks_total ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ graph.created_at }}</td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <Link :href="route('agent.delegation.show', graph.id)" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50" @click.stop>View</Link>
                                </td>
                            </tr>
                            <tr v-if="!loading && graphs.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No delegation graphs found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                    <p>Showing page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</p>
                    <div class="flex gap-2">
                        <button class="rounded border border-gray-300 px-3 py-1 text-gray-700 disabled:opacity-50 dark:border-gray-600 dark:text-gray-100" :disabled="meta.current_page <= 1" @click="setPage(meta.current_page - 1)">Prev</button>
                        <button class="rounded border border-gray-300 px-3 py-1 text-gray-700 disabled:opacity-50 dark:border-gray-600 dark:text-gray-100" :disabled="meta.current_page >= meta.last_page" @click="setPage(meta.current_page + 1)">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
