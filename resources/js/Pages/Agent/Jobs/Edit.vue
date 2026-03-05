<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import JobForm from './Partials/JobForm.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref, onMounted } from 'vue';
import { Briefcase } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    jobId: {
        type: Number,
        required: true,
    },
    config: {
        type: Object,
        default: () => ({}),
    },
});

const errors = reactive({});
const isSubmitting = ref(false);
const loading = ref(true);

const model = reactive({
    id: null,
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
    active_hours_config: null,
    star_preamble_enabled: null,
    targeted_retry_enabled: null,
    max_retries: null,
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

    errors._form = [apiError?.message ?? 'Unable to update this job right now.'];
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
        id: job.id,
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
        active_hours_config: job.active_hours_config ?? null,
        star_preamble_enabled: job.star_preamble_enabled ?? null,
        targeted_retry_enabled: job.targeted_retry_enabled ?? null,
        max_retries: job.max_retries ?? null,
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
        applyApiErrors(e);
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
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Briefcase class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground truncate">Edit Agent Job</h2>
                    <HelpHint
                        ui-key="jobs.edit"
                        short-text="Modify runner, schedule, and task configuration."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="">
                <Card>
                    <CardContent class="pt-6">
                        <div v-if="loading" class="space-y-4">
                            <Skeleton class="h-10 w-full" />
                            <Skeleton class="h-10 w-full" />
                            <Skeleton class="h-10 w-3/4" />
                            <Skeleton class="h-32 w-full" />
                        </div>
                        <JobForm
                            v-else
                            v-model="model"
                            :errors="errors"
                            :is-submitting="isSubmitting"
                            :config="props.config"
                            submit-label="Update Job"
                            @submit="onSubmit"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
