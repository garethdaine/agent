<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import SessionStatusBadge from '@/Components/Interrogation/SessionStatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, reactive, ref } from 'vue';

const sessions = ref([]);
const loading = ref(false);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

const filters = reactive({
    q: '',
    status: '',
    type: '',
    runner: '',
    deleted: '',
    page: 1,
});

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/interrogation/sessions', { params: filters });
        sessions.value = data.data || [];
        meta.value = data.meta || meta.value;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load sessions.';
    } finally {
        loading.value = false;
    }
};

const setPage = async (page) => {
    filters.page = page;
    await load();
};

const retrySession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${sessionId}/retry`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to retry session.';
    }
};

const deleteSession = async (sessionId) => {
    if (!window.confirm('Delete this session? You can restore it later from the Deleted filter.')) {
        return;
    }

    try {
        await axios.delete(`/agent/api/v1/interrogation/sessions/${sessionId}`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete session.';
    }
};

const restoreSession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${sessionId}/restore`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restore session.';
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Discovery Sessions">
        <Head title="Discovery Sessions" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Requirements Discovery</h2>
                <div class="flex items-center gap-2">
                    <Link :href="route('tools.discovery.settings')" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Settings</Link>
                    <Link :href="route('tools.discovery.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">New Session</Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <div class="grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-5 dark:border-gray-700 dark:bg-gray-800">
                    <input v-model="filters.q" placeholder="Search sessions" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load" />
                    <select v-model="filters.status" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load">
                        <option value="">All statuses</option>
                        <option value="setup">setup</option>
                        <option value="discovering">discovering</option>
                        <option value="interrogating">interrogating</option>
                        <option value="summarizing">summarizing</option>
                        <option value="planning">planning</option>
                        <option value="paused">paused</option>
                        <option value="completed">completed</option>
                        <option value="failed">failed</option>
                    </select>
                    <select v-model="filters.type" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load">
                        <option value="">All types</option>
                        <option value="feature">feature</option>
                        <option value="general">general</option>
                    </select>
                    <select v-model="filters.runner" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load">
                        <option value="">All runners</option>
                        <option value="claude">claude</option>
                        <option value="codex">codex</option>
                    </select>
                    <select v-model="filters.deleted" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" @change="load">
                        <option value="">Active only</option>
                        <option value="all">All</option>
                        <option value="true">Deleted only</option>
                    </select>
                </div>

                <p v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Session</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Runner</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Updated</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="loading">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Loading...</td>
                            </tr>
                            <tr v-for="session in sessions" :key="session.id">
                                <td class="px-4 py-3 text-sm">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ session.name || `Session #${session.id}` }}</p>
                                    <p class="text-xs text-gray-500">{{ session.project_directory }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ session.runner_type }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ session.interrogation_type }}</td>
                                <td class="px-4 py-3 text-sm"><SessionStatusBadge :status="session.status" /></td>
                                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200">{{ session.updated_at || '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="!session.deleted_at && ['failed', 'paused', 'setup'].includes(session.status)"
                                            type="button"
                                            class="rounded border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50"
                                            @click="retrySession(session.id)"
                                        >
                                            Retry
                                        </button>
                                        <button
                                            v-if="!session.deleted_at"
                                            type="button"
                                            class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                                            @click="deleteSession(session.id)"
                                        >
                                            Delete
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="rounded border border-green-300 px-2 py-1 text-xs text-green-700 hover:bg-green-50"
                                            @click="restoreSession(session.id)"
                                        >
                                            Restore
                                        </button>
                                        <Link :href="route('tools.discovery.wizard', session.id)" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50 dark:border-gray-700">
                                            Open
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && sessions.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No sessions found.</td>
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
