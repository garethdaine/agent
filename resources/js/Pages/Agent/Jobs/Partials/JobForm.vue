<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    submitLabel: {
        type: String,
        default: 'Save Job',
    },
    isSubmitting: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submit', 'update:modelValue']);

const form = reactive({
    name: '',
    description: '',
    cron_expression: '0 0 1 1 1',
    timezone: 'UTC',
    is_enabled: true,
    max_runtime_seconds: 300,
    cooldown_seconds: 0,
    runner_type: 'codex',
    command_template: '',
    task_markdown_path: '',
    working_directory: '',
    env_json_text: '{}',
});

const hydrate = (value) => {
    form.name = value.name ?? '';
    form.description = value.description ?? '';
    form.cron_expression = value.cron_expression ?? '0 0 1 1 1';
    form.timezone = value.timezone ?? 'UTC';
    form.is_enabled = value.is_enabled ?? true;
    form.max_runtime_seconds = value.max_runtime_seconds ?? 300;
    form.cooldown_seconds = value.cooldown_seconds ?? 0;
    form.runner_type = value.runner_type ?? 'codex';
    form.command_template = value.command_template ?? '';
    form.task_markdown_path = value.task_markdown_path ?? '';
    form.working_directory = value.working_directory ?? '';
    form.env_json_text = JSON.stringify(value.env_json ?? {}, null, 2);
};

watch(() => props.modelValue, (value) => {
    hydrate(value);
}, { immediate: true, deep: true });

const parseEnv = () => {
    try {
        const parsed = JSON.parse(form.env_json_text || '{}');
        return parsed;
    } catch {
        return '__INVALID_JSON__';
    }
};

const submit = () => {
    const env = parseEnv();

    const payload = {
        name: form.name,
        description: form.description,
        cron_expression: form.cron_expression,
        timezone: form.timezone,
        is_enabled: form.is_enabled,
        max_runtime_seconds: Number(form.max_runtime_seconds),
        cooldown_seconds: Number(form.cooldown_seconds),
        runner_type: form.runner_type,
        command_template: form.command_template,
        task_markdown_path: form.task_markdown_path,
        working_directory: form.working_directory,
    };

    if (env !== '__INVALID_JSON__') {
        payload.env_json = env;
    }

    emit('submit', { payload, invalidEnvJson: env === '__INVALID_JSON__' });
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Name</label>
                <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Runner Type</label>
                <select v-model="form.runner_type" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    <option value="claude">claude</option>
                    <option value="codex">codex</option>
                    <option value="custom">custom</option>
                </select>
                <p v-if="errors.runner_type" class="mt-1 text-sm text-red-600">{{ errors.runner_type[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Cron Expression</label>
                <input v-model="form.cron_expression" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.cron_expression" class="mt-1 text-sm text-red-600">{{ errors.cron_expression[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Timezone</label>
                <input v-model="form.timezone" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.timezone" class="mt-1 text-sm text-red-600">{{ errors.timezone[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Max Runtime Seconds</label>
                <input v-model="form.max_runtime_seconds" type="number" min="10" max="86400" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.max_runtime_seconds" class="mt-1 text-sm text-red-600">{{ errors.max_runtime_seconds[0] }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Cooldown Seconds</label>
                <input v-model="form.cooldown_seconds" type="number" min="0" max="86400" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.cooldown_seconds" class="mt-1 text-sm text-red-600">{{ errors.cooldown_seconds[0] }}</p>
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Task Markdown Path</label>
                <input v-model="form.task_markdown_path" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.task_markdown_path" class="mt-1 text-sm text-red-600">{{ errors.task_markdown_path[0] }}</p>
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Working Directory</label>
                <input v-model="form.working_directory" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.working_directory" class="mt-1 text-sm text-red-600">{{ errors.working_directory[0] }}</p>
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Command Template (optional for claude/codex)</label>
                <input v-model="form.command_template" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.command_template" class="mt-1 text-sm text-red-600">{{ errors.command_template[0] }}</p>
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Description</label>
                <textarea v-model="form.description" rows="3" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description[0] }}</p>
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">env_json</label>
                <textarea v-model="form.env_json_text" rows="8" class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-900" />
                <p v-if="errors.env_json" class="mt-1 text-sm text-red-600">{{ errors.env_json[0] }}</p>
            </div>
            <div class="flex items-center gap-2">
                <input id="is_enabled" v-model="form.is_enabled" type="checkbox" class="rounded border-gray-300" />
                <label for="is_enabled" class="text-sm text-gray-700 dark:text-gray-200">Enabled</label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button
                type="submit"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="isSubmitting"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
