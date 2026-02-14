<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref } from 'vue';

const form = reactive({
    name: '',
    runner_type: 'claude',
    project_directory: '/Users/garethdaine/Code/agent',
    interrogation_type: 'feature',
    feature_brief: '',
});

const submitting = ref(false);
const error = ref('');
const validation = ref({});

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validation.value = {};

    try {
        const { data } = await axios.post('/agent/api/v1/interrogation/sessions', form);
        const id = data?.data?.id;

        if (id) {
            router.visit(route('tools.discovery.wizard', id));
            return;
        }

        router.visit(route('tools.discovery.index'));
    } catch (e) {
        validation.value = e?.response?.data?.error?.details ?? {};
        error.value = e?.response?.data?.error?.message ?? 'Failed to create session.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AppLayout title="New Discovery Session">
        <Head title="New Discovery Session" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">New Discovery Session</h2>
                <Link :href="route('tools.discovery.index')" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Back</Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Name (optional)</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                        <p v-if="validation.name" class="mt-1 text-sm text-red-600">{{ validation.name[0] }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Runner</label>
                        <select v-model="form.runner_type" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="claude">claude</option>
                            <option value="codex">codex</option>
                        </select>
                        <p v-if="validation.runner_type" class="mt-1 text-sm text-red-600">{{ validation.runner_type[0] }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Project Directory</label>
                    <input v-model="form.project_directory" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                    <p class="mt-1 text-xs text-gray-500">Absolute path where discovery commands run.</p>
                    <p v-if="validation.project_directory" class="mt-1 text-sm text-red-600">{{ validation.project_directory[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Interrogation Type</label>
                    <select v-model="form.interrogation_type" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="feature">feature</option>
                        <option value="general">general</option>
                    </select>
                    <p v-if="validation.interrogation_type" class="mt-1 text-sm text-red-600">{{ validation.interrogation_type[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Feature Brief</label>
                    <textarea
                        v-model="form.feature_brief"
                        rows="10"
                        class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-900"
                        placeholder="Describe the feature scope, users, and constraints."
                    />
                    <p class="mt-1 text-xs text-gray-500">Required for feature sessions.</p>
                    <p v-if="validation.feature_brief" class="mt-1 text-sm text-red-600">{{ validation.feature_brief[0] }}</p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="submitting"
                        @click="submit"
                    >
                        {{ submitting ? 'Creating...' : 'Create Session' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
