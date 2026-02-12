<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import JobForm from './Partials/JobForm.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref } from 'vue';

const errors = reactive({});
const isSubmitting = ref(false);

const model = reactive({
    name: '',
    description: '',
    cron_expression: '0 9 * * *',
    timezone: 'UTC',
    is_enabled: true,
    max_runtime_seconds: 300,
    cooldown_seconds: 0,
    runner_type: 'codex',
    command_template: '',
    task_markdown_path: '/Users/garethdaine/Code/agent/tasks/',
    task_markdown_content: '',
    working_directory: '/Users/garethdaine/Code/agent',
    env_json: {},
});

const clearErrors = () => {
    Object.keys(errors).forEach((key) => delete errors[key]);
};

const applyApiErrors = (e) => {
    const apiError = e?.response?.data?.error ?? null;
    const details = apiError?.details;

    if (details && typeof details === 'object' && !Array.isArray(details) && Object.keys(details).length > 0) {
        Object.assign(errors, details);
        return;
    }

    if (apiError?.code === 'UNAUTHENTICATED') {
        errors._form = ['Your session is not authenticated. Refresh the page, sign in, and try again.'];
        return;
    }

    errors._form = [apiError?.message ?? 'Unable to create this job right now.'];
};

const onSubmit = async ({ payload, invalidEnvJson, invalidTaskMarkdown }) => {
    clearErrors();

    if (invalidEnvJson) {
        errors.env_json = ['env_json must be valid JSON.'];
        return;
    }

    if (invalidTaskMarkdown === 'path_empty') {
        errors.task_markdown_path = ['Task markdown path is required when using file-path mode.'];
        return;
    }

    if (invalidTaskMarkdown === 'inline_empty') {
        errors.task_markdown_content = ['Inline markdown content is required when using editor mode.'];
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post('/agent/api/v1/jobs', payload);
        router.visit(route('agent.jobs.index'));
    } catch (e) {
        applyApiErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <AppLayout title="Create Job">
        <Head title="Create Job" />

        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Create Agent Job</h2>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <JobForm
                    v-model="model"
                    :errors="errors"
                    :is-submitting="isSubmitting"
                    submit-label="Create Job"
                    @submit="onSubmit"
                />
            </div>
        </div>
    </AppLayout>
</template>
