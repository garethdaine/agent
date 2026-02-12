<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, reactive, ref } from 'vue';

const loading = ref(false);
const error = ref('');
const jobs = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

const filters = reactive({
    q: '',
    is_enabled: '',
    runner_type: '',
    deleted: '',
    page: 1,
});

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/jobs', { params: filters });
        jobs.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load jobs.';
    } finally {
        loading.value = false;
    }
};

const toggle = async (id) => {
    await axios.post(`/agent/api/v1/jobs/${id}/toggle`);
    await load();
};

const runNow = async (id) => {
    try {
        await axios.post(`/agent/api/v1/jobs/${id}/run-now`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to enqueue run.';
    }
};

const removeJob = async (id) => {
    await axios.delete(`/agent/api/v1/jobs/${id}`);
    await load();
};

const restoreJob = async (id) => {
    await axios.post(`/agent/api/v1/jobs/${id}/restore`);
    await load();
};

const setPage = async (page) => {
    filters.page = page;
    await load();
};

onMounted(load);
</script>

<template>
    <AppLayout title="Agent Jobs">
        <Head title="Agent Jobs" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Agent Jobs</h2>
                <Link :href="route('agent.jobs.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    New Job
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-4 dark:border-gray-700 dark:bg-gray-800">
                    <input v-model="filters.q" placeholder="Search name / description" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load" />
                    <select v-model="filters.is_enabled" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load">
                        <option value="">All statuses</option>
                        <option value="true">Enabled</option>
                        <option value="false">Disabled</option>
                    </select>
                    <select v-model="filters.runner_type" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load">
                        <option value="">All runners</option>
                        <option value="claude">claude</option>
                        <option value="codex">codex</option>
                        <option value="custom">custom</option>
                    </select>
                    <select v-model="filters.deleted" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load">
                        <option value="">Active only</option>
                        <option value="all">All</option>
                        <option value="true">Deleted only</option>
                    </select>
                </div>

                <p v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </p>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Enabled</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Runner</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cron / TZ</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Next Run (UTC)</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Last Run</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="loading">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Loading...</td>
                            </tr>
                            <tr v-for="job in jobs" :key="job.id">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ job.name }}</div>
                                    <div class="text-xs text-gray-500">{{ job.description }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span :class="job.is_enabled ? 'text-green-600' : 'text-gray-500'">{{ job.is_enabled ? 'Yes' : 'No' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ job.runner_type }}</td>
                                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ job.cron_expression }}<br>{{ job.timezone }}</td>
                                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ job.next_run_utc ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">
                                    {{ job.last_run_status ?? 'none' }}<br>
                                    {{ job.last_run_finished_at ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50" @click="runNow(job.id)">Run now</button>
                                        <button class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50" @click="toggle(job.id)">Toggle</button>
                                        <button
                                            v-if="!job.deleted_at"
                                            class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50"
                                            @click="removeJob(job.id)"
                                        >
                                            Delete
                                        </button>
                                        <button
                                            v-else
                                            class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50"
                                            @click="restoreJob(job.id)"
                                        >
                                            Restore
                                        </button>
                                        <Link :href="route('agent.jobs.edit', job.id)" class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50">Edit</Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && jobs.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No jobs found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                    <p>Showing page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</p>
                    <div class="flex gap-2">
                        <button class="rounded border border-gray-300 px-3 py-1 disabled:opacity-50" :disabled="meta.current_page <= 1" @click="setPage(meta.current_page - 1)">Prev</button>
                        <button class="rounded border border-gray-300 px-3 py-1 disabled:opacity-50" :disabled="meta.current_page >= meta.last_page" @click="setPage(meta.current_page + 1)">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
