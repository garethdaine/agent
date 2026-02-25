<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';

const profiles = ref([]);
const loading = ref(true);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

const load = async (page = 1) => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/delegation/delegatee-profiles', { params: { page } });
        profiles.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load profiles.';
    } finally {
        loading.value = false;
    }
};

const toggleActive = async (id) => {
    try {
        const profile = profiles.value.find(p => p.id === id);
        await axios.put(`/agent/api/v1/delegation/delegatee-profiles/${id}`, {
            is_active: !profile.is_active,
        });
        await load(meta.value.current_page);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to update profile.';
    }
};

const deleteProfile = async (id) => {
    if (!confirm('Are you sure you want to delete this profile?')) return;

    try {
        await axios.delete(`/agent/api/v1/delegation/delegatee-profiles/${id}`);
        await load(meta.value.current_page);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete profile.';
    }
};

const setPage = async (page) => {
    await load(page);
};

onMounted(() => load());
</script>

<template>
    <AppLayout title="Delegatee Profiles">
        <Head title="Delegatee Profiles" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Delegatee Profiles</h2>
                <Link :href="route('agent.delegation.profiles.create')" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Create Profile
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <p v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </p>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Runner Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Capabilities</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Active</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="loading">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Loading...</td>
                            </tr>
                            <tr v-for="profile in profiles" :key="profile.id">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ profile.name }}</div>
                                    <div v-if="profile.description" class="text-xs text-gray-500 dark:text-gray-400">{{ profile.description }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ profile.runner_type }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="cap in profile.capabilities"
                                            :key="cap.id"
                                            class="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-700 dark:bg-blue-900 dark:text-blue-200"
                                        >
                                            {{ cap.slug }}
                                        </span>
                                        <span v-if="!profile.capabilities || profile.capabilities.length === 0" class="text-gray-500 dark:text-gray-400">-</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span :class="profile.is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                                        {{ profile.is_active ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <div class="flex items-center justify-end gap-2">
                                        <button class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50" @click="toggleActive(profile.id)">
                                            {{ profile.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <Link :href="route('agent.delegation.profiles.edit', profile.id)" class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50">Edit</Link>
                                        <button class="rounded border border-red-300 px-2 py-1 text-red-700 hover:bg-red-50 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/20" @click="deleteProfile(profile.id)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && profiles.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No profiles found.</td>
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
