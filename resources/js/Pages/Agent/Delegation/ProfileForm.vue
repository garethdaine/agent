<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    profileId: {
        type: Number,
        default: null,
    },
});

const isEdit = computed(() => props.profileId !== null);

const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const validationErrors = ref({});

const capabilities = ref([]);

const form = ref({
    name: '',
    runner_type: 'claude',
    command_template: '',
    working_directory: '',
    env_json: '{}',
    config_json: '{}',
    is_active: true,
    capability_ids: [],
});

const loadCapabilities = async () => {
    try {
        const { data } = await axios.get('/agent/api/v1/delegation/capabilities');
        capabilities.value = data.data;
    } catch (e) {
        console.error('Failed to load capabilities', e);
    }
};

const loadProfile = async () => {
    if (!isEdit.value) {
        loading.value = false;
        return;
    }

    try {
        const { data } = await axios.get(`/agent/api/v1/delegation/delegatee-profiles/${props.profileId}`);
        const profile = data.data;
        form.value = {
            name: profile.name || '',
            runner_type: profile.runner_type || 'claude',
            command_template: profile.command_template || '',
            working_directory: profile.working_directory || '',
            env_json: profile.env_json ? JSON.stringify(profile.env_json, null, 2) : '{}',
            config_json: profile.config_json ? JSON.stringify(profile.config_json, null, 2) : '{}',
            is_active: profile.is_active ?? true,
            capability_ids: profile.capabilities?.map(c => c.id) || [],
        };
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load profile.';
    } finally {
        loading.value = false;
    }
};

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validationErrors.value = {};

    try {
        const payload = {
            name: form.value.name,
            runner_type: form.value.runner_type,
            command_template: form.value.command_template,
            working_directory: form.value.working_directory,
            env_json: JSON.parse(form.value.env_json),
            config_json: JSON.parse(form.value.config_json),
            is_active: form.value.is_active,
            capability_ids: form.value.capability_ids,
        };

        if (isEdit.value) {
            await axios.put(`/agent/api/v1/delegation/delegatee-profiles/${props.profileId}`, payload);
        } else {
            await axios.post('/agent/api/v1/delegation/delegatee-profiles', payload);
        }

        router.visit(route('agent.delegation.profiles.index'));
    } catch (e) {
        if (e?.response?.data?.error?.details) {
            validationErrors.value = e.response.data.error.details;
        } else {
            error.value = e?.response?.data?.error?.message ?? 'Failed to save profile.';
        }
    } finally {
        submitting.value = false;
    }
};

const toggleCapability = (capId) => {
    const idx = form.value.capability_ids.indexOf(capId);
    if (idx === -1) {
        form.value.capability_ids.push(capId);
    } else {
        form.value.capability_ids.splice(idx, 1);
    }
};

onMounted(async () => {
    await loadCapabilities();
    await loadProfile();
});
</script>

<template>
    <AppLayout :title="isEdit ? 'Edit Profile' : 'Create Profile'">
        <Head :title="isEdit ? 'Edit Profile' : 'Create Profile'" />

        <template #header>
            <div>
                <div class="flex items-center gap-2">
                    <Link :href="route('agent.delegation.profiles.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        Back to Profiles
                    </Link>
                    <span class="text-gray-400">/</span>
                </div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ isEdit ? 'Edit Delegatee Profile' : 'Create Delegatee Profile' }}
                </h2>
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

                <template v-else>
                    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                <input v-model="form.name" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="My Delegatee Profile" />
                                <p v-if="validationErrors.name" class="mt-1 text-xs text-red-600">{{ validationErrors.name[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Runner Type</label>
                                <select v-model="form.runner_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="claude">claude</option>
                                    <option value="codex">codex</option>
                                    <option value="custom">custom</option>
                                </select>
                                <p v-if="validationErrors.runner_type" class="mt-1 text-xs text-red-600">{{ validationErrors.runner_type[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Command Template</label>
                                <input v-model="form.command_template" type="text" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="claude -p {{task_markdown_path}}" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Available placeholders: {{task_markdown_path}}, {{working_directory}}</p>
                                <p v-if="validationErrors.command_template" class="mt-1 text-xs text-red-600">{{ validationErrors.command_template[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Working Directory</label>
                                <input v-model="form.working_directory" type="text" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="/path/to/working/directory" />
                                <p v-if="validationErrors.working_directory" class="mt-1 text-xs text-red-600">{{ validationErrors.working_directory[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Environment Variables (JSON)</label>
                                <textarea v-model="form.env_json" rows="4" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                                <p v-if="validationErrors.env_json" class="mt-1 text-xs text-red-600">{{ validationErrors.env_json[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Config (JSON)</label>
                                <textarea v-model="form.config_json" rows="4" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                                <p v-if="validationErrors.config_json" class="mt-1 text-xs text-red-600">{{ validationErrors.config_json[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Capabilities</label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="cap in capabilities"
                                        :key="cap.id"
                                        type="button"
                                        :class="form.capability_ids.includes(cap.id) ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'"
                                        class="rounded-full px-3 py-1 text-sm font-medium transition-colors"
                                        @click="toggleCapability(cap.id)"
                                    >
                                        {{ cap.slug }}
                                    </button>
                                    <span v-if="capabilities.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No capabilities available.</span>
                                </div>
                            </div>

                            <div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.is_active" type="checkbox" class="rounded text-indigo-600" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Link :href="route('agent.delegation.profiles.index')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            Cancel
                        </Link>
                        <button
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="submitting || !form.name"
                            @click="submit"
                        >
                            {{ submitting ? 'Saving...' : 'Save Profile' }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
