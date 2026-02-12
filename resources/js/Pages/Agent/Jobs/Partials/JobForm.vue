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

const timezoneSuggestions = [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Phoenix',
    'America/Anchorage',
    'Pacific/Honolulu',
    'Europe/London',
    'Europe/Berlin',
    'Asia/Tokyo',
    'Australia/Sydney',
];

const hours = Array.from({ length: 24 }, (_, i) => i);
const minutes = Array.from({ length: 60 }, (_, i) => i);
const monthDays = Array.from({ length: 31 }, (_, i) => i + 1);
const hourIntervals = Array.from({ length: 23 }, (_, i) => i + 1);
const minuteIntervals = Array.from({ length: 59 }, (_, i) => i + 1);

const weekdays = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];

const form = reactive({
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
    env_json_text: '{}',
});

const taskSource = reactive({
    mode: 'path',
});

const schedule = reactive({
    mode: 'basic',
    frequency: 'daily',
    minute: 0,
    hour: 9,
    everyMinutes: 5,
    everyHours: 1,
    weekday: 1,
    monthDay: 1,
});

const parseInteger = (value, fallback) => {
    const parsed = Number.parseInt(value, 10);

    return Number.isInteger(parsed) ? parsed : fallback;
};

const inRange = (value, min, max, fallback) => {
    const int = parseInteger(value, fallback);

    return Math.max(min, Math.min(max, int));
};

const buildCronFromBasicSchedule = () => {
    const minute = inRange(schedule.minute, 0, 59, 0);
    const hour = inRange(schedule.hour, 0, 23, 0);

    if (schedule.frequency === 'minute_interval') {
        const everyMinutes = inRange(schedule.everyMinutes, 1, 59, 5);

        return `*/${everyMinutes} * * * *`;
    }

    if (schedule.frequency === 'hourly') {
        const everyHours = inRange(schedule.everyHours, 1, 23, 1);

        return `${minute} */${everyHours} * * *`;
    }

    if (schedule.frequency === 'weekly') {
        const weekday = inRange(schedule.weekday, 0, 6, 1);

        return `${minute} ${hour} * * ${weekday}`;
    }

    if (schedule.frequency === 'monthly') {
        const monthDay = inRange(schedule.monthDay, 1, 31, 1);

        return `${minute} ${hour} ${monthDay} * *`;
    }

    return `${minute} ${hour} * * *`;
};

const parseCronIntoBasicSchedule = (cronExpression) => {
    const cron = (cronExpression ?? '').trim();

    let match = cron.match(/^\*\/(\d+)\s+\*\s+\*\s+\*\s+\*$/);
    if (match) {
        schedule.frequency = 'minute_interval';
        schedule.everyMinutes = inRange(match[1], 1, 59, 5);

        return true;
    }

    match = cron.match(/^(\d+)\s+\*\/(\d+)\s+\*\s+\*\s+\*$/);
    if (match) {
        schedule.frequency = 'hourly';
        schedule.minute = inRange(match[1], 0, 59, 0);
        schedule.everyHours = inRange(match[2], 1, 23, 1);

        return true;
    }

    match = cron.match(/^(\d+)\s+(\d+)\s+\*\s+\*\s+\*$/);
    if (match) {
        schedule.frequency = 'daily';
        schedule.minute = inRange(match[1], 0, 59, 0);
        schedule.hour = inRange(match[2], 0, 23, 9);

        return true;
    }

    match = cron.match(/^(\d+)\s+(\d+)\s+\*\s+\*\s+(\d+)$/);
    if (match) {
        schedule.frequency = 'weekly';
        schedule.minute = inRange(match[1], 0, 59, 0);
        schedule.hour = inRange(match[2], 0, 23, 9);
        schedule.weekday = inRange(match[3], 0, 6, 1);

        return true;
    }

    match = cron.match(/^(\d+)\s+(\d+)\s+(\d+)\s+\*\s+\*$/);
    if (match) {
        schedule.frequency = 'monthly';
        schedule.minute = inRange(match[1], 0, 59, 0);
        schedule.hour = inRange(match[2], 0, 23, 9);
        schedule.monthDay = inRange(match[3], 1, 31, 1);

        return true;
    }

    return false;
};

const hydrate = (value) => {
    form.name = value.name ?? '';
    form.description = value.description ?? '';
    form.cron_expression = value.cron_expression ?? '0 9 * * *';
    form.timezone = value.timezone ?? 'UTC';
    form.is_enabled = value.is_enabled ?? true;
    form.max_runtime_seconds = value.max_runtime_seconds ?? 300;
    form.cooldown_seconds = value.cooldown_seconds ?? 0;
    form.runner_type = value.runner_type ?? 'codex';
    form.command_template = value.command_template ?? '';
    form.task_markdown_path = value.task_markdown_path ?? '';
    form.task_markdown_content = value.task_markdown_content ?? '';
    form.working_directory = value.working_directory ?? '';
    form.env_json_text = JSON.stringify(value.env_json ?? {}, null, 2);
    taskSource.mode = form.task_markdown_content.trim() !== '' ? 'inline' : 'path';

    if (parseCronIntoBasicSchedule(form.cron_expression)) {
        schedule.mode = 'basic';
    } else {
        schedule.mode = 'advanced';
    }
};

watch(() => props.modelValue, (value) => {
    hydrate(value);
}, { immediate: true, deep: true });

watch(() => [
    schedule.mode,
    schedule.frequency,
    schedule.minute,
    schedule.hour,
    schedule.everyMinutes,
    schedule.everyHours,
    schedule.weekday,
    schedule.monthDay,
], () => {
    if (schedule.mode !== 'basic') {
        return;
    }

    form.cron_expression = buildCronFromBasicSchedule();
}, { deep: true });

watch(() => schedule.mode, (mode) => {
    if (mode === 'basic' && !parseCronIntoBasicSchedule(form.cron_expression)) {
        form.cron_expression = buildCronFromBasicSchedule();
    }
});

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
        working_directory: form.working_directory,
    };

    if (taskSource.mode === 'inline') {
        if (form.task_markdown_content.trim() === '') {
            emit('submit', { payload: null, invalidEnvJson: env === '__INVALID_JSON__', invalidTaskMarkdown: 'inline_empty' });
            return;
        }

        payload.task_markdown_content = form.task_markdown_content;
    } else {
        if (form.task_markdown_path.trim() === '') {
            emit('submit', { payload: null, invalidEnvJson: env === '__INVALID_JSON__', invalidTaskMarkdown: 'path_empty' });
            return;
        }

        payload.task_markdown_path = form.task_markdown_path.trim();
    }

    if (env !== '__INVALID_JSON__') {
        payload.env_json = env;
    }

    emit('submit', { payload, invalidEnvJson: env === '__INVALID_JSON__', invalidTaskMarkdown: null });
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div v-if="errors._form" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            {{ Array.isArray(errors._form) ? errors._form[0] : errors._form }}
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Name</label>
                <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p class="mt-1 text-xs text-gray-500">A human-friendly label for this job in the UI.</p>
                <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Runner Type</label>
                <select v-model="form.runner_type" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    <option value="claude">claude</option>
                    <option value="codex">codex</option>
                    <option value="custom">custom</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Chooses the executable policy and default command template.</p>
                <p v-if="errors.runner_type" class="mt-1 text-sm text-red-600">{{ errors.runner_type[0] }}</p>
            </div>

            <div class="lg:col-span-2 rounded-md border border-gray-200 p-4 dark:border-gray-700">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Schedule</p>
                        <p class="text-xs text-gray-500">Use Basic mode for guided scheduling or Advanced mode to type a cron expression.</p>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input v-model="schedule.mode" type="radio" value="basic" class="border-gray-300" />
                            Basic
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input v-model="schedule.mode" type="radio" value="advanced" class="border-gray-300" />
                            Advanced
                        </label>
                    </div>
                </div>

                <div v-if="schedule.mode === 'basic'" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Frequency</label>
                        <select v-model="schedule.frequency" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="minute_interval">Every N Minutes</option>
                            <option value="hourly">Every N Hours</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>

                    <div v-if="schedule.frequency === 'minute_interval'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Every</label>
                        <select v-model="schedule.everyMinutes" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option v-for="n in minuteIntervals" :key="`min-${n}`" :value="n">{{ n }} minute<span v-if="n !== 1">s</span></option>
                        </select>
                    </div>

                    <div v-if="schedule.frequency === 'hourly'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Every</label>
                        <select v-model="schedule.everyHours" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option v-for="n in hourIntervals" :key="n" :value="n">{{ n }} hour<span v-if="n !== 1">s</span></option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Hour (24h)</label>
                        <select
                            v-model="schedule.hour"
                            :disabled="schedule.frequency === 'hourly' || schedule.frequency === 'minute_interval'"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 disabled:opacity-60"
                        >
                            <option v-for="h in hours" :key="`h-${h}`" :value="h">{{ String(h).padStart(2, '0') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Minute</label>
                        <select
                            v-model="schedule.minute"
                            :disabled="schedule.frequency === 'minute_interval'"
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 disabled:opacity-60"
                        >
                            <option v-for="m in minutes" :key="`m-${m}`" :value="m">{{ String(m).padStart(2, '0') }}</option>
                        </select>
                    </div>

                    <div v-if="schedule.frequency === 'weekly'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Day of Week</label>
                        <select v-model="schedule.weekday" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option v-for="day in weekdays" :key="day.value" :value="day.value">{{ day.label }}</option>
                        </select>
                    </div>

                    <div v-if="schedule.frequency === 'monthly'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Day of Month</label>
                        <select v-model="schedule.monthDay" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option v-for="day in monthDays" :key="`dom-${day}`" :value="day">{{ day }}</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Generated Cron Expression</label>
                        <input :value="form.cron_expression" type="text" readonly class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 font-mono text-sm dark:border-gray-700 dark:bg-gray-900" />
                    </div>
                </div>

                <div v-else>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Cron Expression</label>
                    <input v-model="form.cron_expression" type="text" class="mt-1 w-full rounded-md border-gray-300 font-mono dark:border-gray-700 dark:bg-gray-900" />
                    <p class="mt-1 text-xs text-gray-500">5-part numeric cron. Supports numbers, <code>*</code>, ranges, lists, and step values.</p>
                </div>

                <p v-if="errors.cron_expression" class="mt-2 text-sm text-red-600">{{ errors.cron_expression[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Timezone</label>
                <input v-model="form.timezone" type="text" list="timezone-suggestions" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <datalist id="timezone-suggestions">
                    <option v-for="tz in timezoneSuggestions" :key="tz" :value="tz" />
                </datalist>
                <p class="mt-1 text-xs text-gray-500">IANA timezone used to evaluate the schedule (for example, <code>America/New_York</code>).</p>
                <p v-if="errors.timezone" class="mt-1 text-sm text-red-600">{{ errors.timezone[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Max Runtime Seconds</label>
                <input v-model="form.max_runtime_seconds" type="number" min="10" max="86400" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p class="mt-1 text-xs text-gray-500">Hard timeout for a single run. The runner will terminate the process after this limit.</p>
                <p v-if="errors.max_runtime_seconds" class="mt-1 text-sm text-red-600">{{ errors.max_runtime_seconds[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Cooldown Seconds</label>
                <input v-model="form.cooldown_seconds" type="number" min="0" max="86400" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p class="mt-1 text-xs text-gray-500">Minimum wait after the last finished non-skipped run before a scheduled run can start.</p>
                <p v-if="errors.cooldown_seconds" class="mt-1 text-sm text-red-600">{{ errors.cooldown_seconds[0] }}</p>
            </div>

            <div class="lg:col-span-2 rounded-md border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Task Prompt Source</p>
                        <p class="text-xs text-gray-500">Choose a markdown file path or write markdown directly.</p>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input v-model="taskSource.mode" type="radio" value="path" class="border-gray-300" />
                            File Path
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input v-model="taskSource.mode" type="radio" value="inline" class="border-gray-300" />
                            Inline Markdown
                        </label>
                    </div>
                </div>

                <div v-if="taskSource.mode === 'path'" class="mt-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Task Markdown Path</label>
                    <input v-model="form.task_markdown_path" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                    <p class="mt-1 text-xs text-gray-500">Absolute path to an existing markdown task/prompt file.</p>
                    <p v-if="errors.task_markdown_path" class="mt-1 text-sm text-red-600">{{ errors.task_markdown_path[0] }}</p>
                </div>

                <div v-else class="mt-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Task Markdown Editor</label>
                    <textarea
                        v-model="form.task_markdown_content"
                        rows="12"
                        class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-900"
                        placeholder="# Task title&#10;&#10;Describe exactly what the agent should do."
                    />
                    <p class="mt-1 text-xs text-gray-500">Markdown text is persisted into a managed file path automatically.</p>
                    <p v-if="errors.task_markdown_content" class="mt-1 text-sm text-red-600">{{ errors.task_markdown_content[0] }}</p>
                </div>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Working Directory</label>
                <input v-model="form.working_directory" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p class="mt-1 text-xs text-gray-500">Absolute directory where the command will execute.</p>
                <p v-if="errors.working_directory" class="mt-1 text-sm text-red-600">{{ errors.working_directory[0] }}</p>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Command Template (optional for claude/codex)</label>
                <input v-model="form.command_template" type="text" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p class="mt-1 text-xs text-gray-500">Optional command override. Leave empty to use runner defaults. Must pass command safety validation.</p>
                <p v-if="errors.command_template" class="mt-1 text-sm text-red-600">{{ errors.command_template[0] }}</p>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Description</label>
                <textarea v-model="form.description" rows="3" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                <p class="mt-1 text-xs text-gray-500">Optional free-text notes about what this job does.</p>
                <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description[0] }}</p>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">env_json</label>
                <textarea v-model="form.env_json_text" rows="8" class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-900" />
                <p class="mt-1 text-xs text-gray-500">Optional environment variable overrides as JSON object. Restricted by security policy.</p>
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
