<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'execution',
    },
    build: {
        type: Object,
        default: () => ({}),
    },
    actions: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['generate-tasks', 'start', 'pause', 'resume', 'retry', 'clarify']);

const clarification = ref('');

const isTasksMode = computed(() => props.mode === 'tasks');
const isExecutionMode = computed(() => props.mode === 'execution');

const tasks = computed(() => (Array.isArray(props.build?.tasks) ? props.build.tasks : []));
const status = computed(() => String(props.build?.status ?? 'idle'));
const activeTask = computed(() => props.build?.active_task ?? null);
const activeRun = computed(() => props.build?.active_run ?? null);
const flags = computed(() => (typeof props.build?.flags === 'object' && props.build?.flags !== null ? props.build.flags : {}));

const canGenerate = computed(() => !props.disabled && !props.actions.generateBuildTasks && status.value !== 'generating_tasks');
const canStart = computed(() => !props.disabled && !props.actions.startBuild && tasks.value.length > 0 && ['ready', 'failed', 'completed', 'idle'].includes(status.value));
const canPause = computed(() => !props.disabled && !props.actions.pauseBuild && status.value === 'running');
const canResume = computed(() => !props.disabled && !props.actions.resumeBuild && status.value === 'paused');
const canRetry = computed(() => !props.disabled && !props.actions.startBuild && tasks.value.length > 0 && ['failed', 'completed'].includes(status.value));

const statusLabel = computed(() => status.value.replace(/_/g, ' '));

const activeRunLogLines = computed(() => {
    const tail = Array.isArray(activeRun.value?.log_tail) ? activeRun.value.log_tail : [];

    return tail
        .map((entry) => {
            const payload = entry?.payload;
            if (typeof payload === 'string') {
                return payload;
            }

            if (payload && typeof payload === 'object') {
                try {
                    return JSON.stringify(payload);
                } catch {
                    return '[unprintable payload]';
                }
            }

            return String(payload ?? '');
        })
        .filter((line) => line.trim() !== '');
});

const submitClarification = () => {
    const message = clarification.value.trim();
    if (message === '' || props.actions.clarifyBuild || props.disabled) {
        return;
    }

    emit('clarify', { message, task_id: activeTask.value?.id ?? null });
    clarification.value = '';
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ isTasksMode ? 'Build Tasks' : 'Build Execution' }}</h3>
            <span class="rounded-full border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 capitalize dark:border-gray-600 dark:text-gray-200">
                {{ statusLabel }}
            </span>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <button
                v-if="isTasksMode"
                type="button"
                class="rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canGenerate"
                @click="emit('generate-tasks')"
            >
                {{ actions.generateBuildTasks ? 'Generating...' : 'Generate Build Tasks' }}
            </button>
            <button
                v-if="isTasksMode"
                type="button"
                class="rounded border border-green-400 px-3 py-2 text-xs font-semibold text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canStart"
                @click="emit('start')"
            >
                {{ actions.startBuild ? 'Starting...' : 'Start Build' }}
            </button>

            <button
                v-if="isExecutionMode"
                type="button"
                class="rounded border border-amber-400 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canPause"
                @click="emit('pause')"
            >
                {{ actions.pauseBuild ? 'Pausing...' : 'Pause Build' }}
            </button>
            <button
                v-if="isExecutionMode"
                type="button"
                class="rounded border border-sky-400 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canResume"
                @click="emit('resume')"
            >
                {{ actions.resumeBuild ? 'Resuming...' : 'Resume Build' }}
            </button>
            <button
                v-if="isExecutionMode"
                type="button"
                class="rounded border border-rose-400 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canRetry"
                @click="emit('retry')"
            >
                {{ actions.startBuild ? 'Retrying...' : 'Retry Failed' }}
            </button>
        </div>

        <div v-if="isTasksMode && status === 'generating_tasks'" class="mt-4 rounded border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-800">
            Generating build tasks from the approved plan...
        </div>

        <div v-if="isExecutionMode && flags.approval_required" class="mt-4 rounded border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
            <p class="font-semibold">Approval likely required in active run output.</p>
            <p v-if="flags.approval_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.approval_excerpt }}</p>
        </div>

        <div v-if="isExecutionMode && flags.rate_limit_detected" class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-xs text-red-900">
            <p class="font-semibold">Rate limit detected.</p>
            <p v-if="flags.rate_limit_reset_at" class="mt-1">Reset at: {{ flags.rate_limit_reset_at }}</p>
            <p v-if="flags.rate_limit_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.rate_limit_excerpt }}</p>
        </div>

        <div v-if="isExecutionMode && activeTask" class="mt-4 rounded border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current Task</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">#{{ activeTask.sequence }} · {{ activeTask.title }}</p>
            <p v-if="activeTask.description" class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ activeTask.description }}</p>
            <p v-if="activeRun" class="mt-2 text-xs text-gray-600 dark:text-gray-300">Run #{{ activeRun.id }} · {{ activeRun.status }}</p>
        </div>

        <div v-if="isExecutionMode && activeRunLogLines.length > 0" class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Active Run Log Tail</p>
            <pre class="mt-2 max-h-56 overflow-auto rounded border border-gray-200 bg-gray-950 p-3 text-xs text-gray-100 dark:border-gray-700">{{ activeRunLogLines.join('\n') }}</pre>
        </div>

        <div v-if="isExecutionMode" class="mt-4 rounded border border-gray-200 p-3 dark:border-gray-700">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Clarification</p>
            <textarea
                v-model="clarification"
                rows="3"
                class="mt-2 w-full rounded border border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                placeholder="Add clarification for the active task..."
            />
            <div class="mt-2 flex justify-end">
                <button
                    type="button"
                    class="rounded bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200"
                    :disabled="disabled || actions.clarifyBuild || clarification.trim() === ''"
                    @click="submitClarification"
                >
                    {{ actions.clarifyBuild ? 'Submitting...' : 'Submit Clarification' }}
                </button>
            </div>
        </div>

        <div class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tasks</p>
            <div v-if="tasks.length === 0" class="mt-2 rounded border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No build tasks generated yet.
            </div>
            <div v-else class="mt-2 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Task</th>
                            <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Attempts</th>
                            <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500">Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="task in tasks" :key="task.id">
                            <td class="px-2 py-2 text-gray-800 dark:text-gray-100">#{{ task.sequence }} {{ task.title }}</td>
                            <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ task.status }}</td>
                            <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ task.attempt_count }}</td>
                            <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ task.last_error || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="isExecutionMode && build.completion_summary" class="mt-4 rounded border border-green-300 bg-green-50 p-3 text-xs text-green-800">
            {{ build.completion_summary }}
        </div>
    </div>
</template>
