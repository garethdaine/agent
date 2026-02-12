<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import JobForm from './Partials/JobForm.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref, onMounted } from 'vue';

const props = defineProps({
    jobId: {
        type: Number,
        required: true,
    },
});

const errors = reactive({});
const isSubmitting = ref(false);
const loading = ref(true);

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
    task_markdown_path: '',
    task_markdown_content: '',
    working_directory: '',
    env_json: {},
});

const clearErrors = () => {
    Object.keys(errors).forEach((key) => delete errors[key]);
};

const load = async () => {
    loading.value = true;
    let job = null;

    try {
        const response = await axios.get(`/agent/api/v1/jobs/${props.jobId}`, { params: { include_task_content: 1 } });
        job = response.data?.data ?? null;
    } catch {
        router.visit(route('agent.jobs.index'));
        return;
    }

    Object.assign(model, {
        name: job.name,
        description: job.description ?? '',
        cron_expression: job.cron_expression,
        timezone: job.timezone,
        is_enabled: job.is_enabled,
        max_runtime_seconds: job.max_runtime_seconds,
        cooldown_seconds: job.cooldown_seconds,
        runner_type: job.runner_type,
        command_template: job.command_template ?? '',
        task_markdown_path: job.task_markdown_path,
        task_markdown_content: job.task_markdown_content ?? '',
        working_directory: job.working_directory,
        env_json: job.env_json ?? {},
    });

    loading.value = false;
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
        await axios.put(`/agent/api/v1/jobs/${props.jobId}`, payload);
        router.visit(route('agent.jobs.index'));
    } catch (e) {
        const details = e?.response?.data?.error?.details ?? {};
        Object.assign(errors, details);
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Edit Job">
        <Head title="Edit Job" />

        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Edit Agent Job</h2>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <p v-if="loading" class="text-sm text-gray-500">Loading job...</p>
                <JobForm
                    v-else
                    v-model="model"
                    :errors="errors"
                    :is-submitting="isSubmitting"
                    submit-label="Update Job"
                    @submit="onSubmit"
                />
            </div>
        </div>
    </AppLayout>
</template>
