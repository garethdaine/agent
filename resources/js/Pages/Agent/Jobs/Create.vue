<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import JobForm from './Partials/JobForm.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref } from 'vue';
import { Briefcase } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    config: {
        type: Object,
        default: () => ({}),
    },
    verticalTemplates: {
        type: Array,
        default: () => [],
    },
});

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

const applyTemplate = (template) => {
    if (!template) return;
    model.name = template.name ?? model.name;
    model.cron_expression = template.cron_expression ?? model.cron_expression;
    model.runner_type = template.runner_type ?? model.runner_type;
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
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Briefcase class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Create Agent Job</h2>
                    <HelpHint
                        ui-key="jobs.create"
                        short-text="Configure runner, schedule, and task for a new agent job."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="">
                <Card>
                    <CardContent class="pt-6">
                        <div v-if="verticalTemplates.length > 0" class="mb-4 flex flex-wrap items-center gap-2">
                            <label class="text-sm font-medium text-muted-foreground">Start from template:</label>
                            <select
                                class="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                                @change="(e) => applyTemplate(verticalTemplates.find((t) => t.key === (e.target.value)) || null)"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="t in verticalTemplates"
                                    :key="t.key"
                                    :value="t.key"
                                >
                                    {{ t.name }}
                                </option>
                            </select>
                        </div>
                        <JobForm
                            v-model="model"
                            :errors="errors"
                            :is-submitting="isSubmitting"
                            :config="props.config"
                            submit-label="Create Job"
                            @submit="onSubmit"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
