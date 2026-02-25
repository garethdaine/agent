<script setup>
import MarkdownEditor from '@/Components/Markdown/MarkdownEditor.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { formatInterrogationError } from '@/Components/Interrogation/errorFormatting';
import { formatAgentRunEventEntries } from '@/Support/agentRunEventFormatting';
import { computed, ref, watch } from 'vue';

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
    activity: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'generate-tasks',
    'approve-tasks',
    'start',
    'pause',
    'resume',
    'retry',
    'rerun-all',
    'clarify',
    'create-task',
    'update-task',
    'delete-task',
    'regenerate-task',
]);

const clarification = ref('');
const projectRulesDraft = ref([]);
const projectRuleFiles = ref([]);
const projectRulesDirty = ref(false);
const showCreateTaskForm = ref(false);
const createTaskDraft = ref({
    title: '',
    description: '',
    instructions_markdown: '',
});
const editingTaskId = ref(null);
const editTaskDraft = ref({
    title: '',
    description: '',
    instructions_markdown: '',
});
const regeneratingTaskId = ref(null);
const regenerateAmendNotes = ref('');

const isRulesMode = computed(() => props.mode === 'rules');
const isTasksMode = computed(() => props.mode === 'tasks');
const isExecutionMode = computed(() => props.mode === 'execution');

const tasks = computed(() => (Array.isArray(props.build?.tasks) ? props.build.tasks : []));
const status = computed(() => String(props.build?.status ?? 'idle'));
const activeTask = computed(() => props.build?.active_task ?? null);
const activeRun = computed(() => props.build?.active_run ?? null);
const flags = computed(() => (typeof props.build?.flags === 'object' && props.build?.flags !== null ? props.build.flags : {}));
const buildError = computed(() => formatInterrogationError(props.build?.error, { allowDetails: true, maxSummaryLength: 220 }));
const tasksApprovedAt = computed(() => {
    const value = String(props.build?.tasks_approved_at ?? '').trim();

    return value !== '' ? value : '';
});
const taskProviderSync = computed(() => (typeof props.build?.task_provider_sync === 'object' && props.build?.task_provider_sync !== null ? props.build.task_provider_sync : {}));
const taskProviderSyncStatus = computed(() => String(taskProviderSync.value?.status ?? 'idle').trim() || 'idle');
const taskProviderSyncError = computed(() => formatInterrogationError(taskProviderSync.value?.error, { allowDetails: true, maxSummaryLength: 180 }));
const taskUpdateState = computed(() => (typeof props.actions?.updateBuildTaskIds === 'object' && props.actions?.updateBuildTaskIds !== null ? props.actions.updateBuildTaskIds : {}));
const taskDeleteState = computed(() => (typeof props.actions?.deleteBuildTaskIds === 'object' && props.actions?.deleteBuildTaskIds !== null ? props.actions.deleteBuildTaskIds : {}));
const taskRegenerateState = computed(() => (typeof props.actions?.regenerateBuildTaskIds === 'object' && props.actions?.regenerateBuildTaskIds !== null ? props.actions.regenerateBuildTaskIds : {}));

const canGenerate = computed(() => !props.disabled && !props.actions.generateBuildTasks && status.value !== 'generating_tasks');
const canApproveTasks = computed(() => !props.disabled && !props.actions.approveBuildTasks && tasks.value.length > 0 && status.value !== 'generating_tasks');
const canStart = computed(() => !props.disabled
    && !props.actions.startBuild
    && tasks.value.length > 0
    && tasksApprovedAt.value !== ''
    && !['queued', 'syncing'].includes(taskProviderSyncStatus.value)
    && ['ready', 'failed', 'completed', 'idle'].includes(status.value));
const canPause = computed(() => !props.disabled && !props.actions.pauseBuild && status.value === 'running');
const canResume = computed(() => !props.disabled && !props.actions.resumeBuild && status.value === 'paused');
const canRetry = computed(() => !props.disabled && !props.actions.startBuild && tasks.value.length > 0 && ['failed', 'completed'].includes(status.value));
const canRerunAll = computed(() => !props.disabled && !props.actions.startBuild && tasks.value.length > 0 && ['failed', 'completed'].includes(status.value));
const canManageTaskList = computed(() => isTasksMode.value && !props.disabled && status.value !== 'generating_tasks');
const retryLabel = computed(() => {
    if (props.actions.startBuild) {
        return status.value === 'completed' ? 'Re-running failed tasks...' : 'Retrying failed tasks...';
    }

    return 'Retry Failed Tasks';
});

const statusLabel = computed(() => status.value.replace(/_/g, ' '));
const isTaskUpdating = (taskId) => Boolean(taskUpdateState.value?.[taskId] || taskUpdateState.value?.[String(taskId)]);
const isTaskDeleting = (taskId) => Boolean(taskDeleteState.value?.[taskId] || taskDeleteState.value?.[String(taskId)]);
const isTaskRegenerating = (taskId) => Boolean(taskRegenerateState.value?.[taskId] || taskRegenerateState.value?.[String(taskId)]);

const normalizeProjectRuleEntries = (rules) => {
    if (!Array.isArray(rules)) {
        return [];
    }

    const normalized = [];
    rules.forEach((rule, index) => {
        if (!rule || typeof rule !== 'object') {
            return;
        }

        const title = String(rule.title ?? '').trim();
        const markdown = String(rule.markdown ?? '').trim();
        if (title === '' && markdown === '') {
            return;
        }

        const source = String(rule.source ?? 'manual').trim().toLowerCase();
        normalized.push({
            id: String(rule.id ?? `rule-${index + 1}`),
            title,
            markdown,
            source: ['manual', 'uploaded'].includes(source) ? source : 'manual',
            filename: String(rule.filename ?? '').trim(),
        });
    });

    return normalized;
};

watch(
    () => props.build?.project_rules,
    (nextRules) => {
        if (projectRulesDirty.value) {
            return;
        }

        projectRulesDraft.value = normalizeProjectRuleEntries(nextRules);
    },
    { immediate: true }
);

const activeRunLogEntries = computed(() => {
    const tail = Array.isArray(activeRun.value?.log_tail) ? activeRun.value.log_tail : [];

    return formatAgentRunEventEntries(tail);
});

const addProjectRule = () => {
    projectRulesDirty.value = true;
    projectRulesDraft.value.push({
        id: `rule-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        title: '',
        markdown: '',
        source: 'manual',
        filename: '',
    });
};

const removeProjectRule = (index) => {
    projectRulesDirty.value = true;
    projectRulesDraft.value.splice(index, 1);
};

const handleProjectRuleFileSelection = (event) => {
    const input = event?.target;
    const files = Array.from(input?.files ?? []);
    if (files.length === 0) {
        return;
    }

    projectRulesDirty.value = true;
    projectRuleFiles.value = [...projectRuleFiles.value, ...files];
    input.value = '';
};

const removeProjectRuleFile = (index) => {
    projectRulesDirty.value = true;
    projectRuleFiles.value.splice(index, 1);
};

const updateProjectRuleTitle = (index, value) => {
    if (!projectRulesDraft.value[index]) {
        return;
    }

    projectRulesDirty.value = true;
    projectRulesDraft.value[index].title = String(value ?? '');
};

const updateProjectRuleMarkdown = (index, value) => {
    if (!projectRulesDraft.value[index]) {
        return;
    }

    projectRulesDirty.value = true;
    projectRulesDraft.value[index].markdown = String(value ?? '');
};

const buildProjectRulesPayload = () => {
    return projectRulesDraft.value
        .map((rule, index) => {
            const title = String(rule?.title ?? '').trim();
            const markdown = String(rule?.markdown ?? '').trim();
            if (title === '' && markdown === '') {
                return null;
            }

            if (markdown === '') {
                return null;
            }

            const source = String(rule?.source ?? 'manual').trim().toLowerCase();
            const filename = String(rule?.filename ?? '').trim();

            return {
                id: String(rule?.id ?? `rule-${index + 1}`),
                title: title !== '' ? title : `Rule ${index + 1}`,
                markdown,
                source: ['manual', 'uploaded'].includes(source) ? source : 'manual',
                filename: filename !== '' ? filename : null,
            };
        })
        .filter((entry) => entry !== null);
};

const submitTaskGeneration = () => {
    if (!canGenerate.value) {
        return;
    }

    emit('generate-tasks', {
        project_rules: buildProjectRulesPayload(),
        project_rule_files: [...projectRuleFiles.value],
    });
};

const submitClarification = () => {
    const message = clarification.value.trim();
    if (message === '' || props.actions.clarifyBuild || props.disabled) {
        return;
    }

    emit('clarify', { message, task_id: activeTask.value?.id ?? null });
    clarification.value = '';
};

const resetCreateTaskDraft = () => {
    createTaskDraft.value = {
        title: '',
        description: '',
        instructions_markdown: '',
    };
};

const openCreateTaskForm = () => {
    if (!canManageTaskList.value) {
        return;
    }

    showCreateTaskForm.value = true;
};

const cancelCreateTask = () => {
    showCreateTaskForm.value = false;
    resetCreateTaskDraft();
};

const submitCreateTask = () => {
    if (!canManageTaskList.value || props.actions.createBuildTask) {
        return;
    }

    const title = String(createTaskDraft.value.title ?? '').trim();
    if (title === '') {
        return;
    }

    emit('create-task', {
        title,
        description: String(createTaskDraft.value.description ?? ''),
        instructions_markdown: String(createTaskDraft.value.instructions_markdown ?? ''),
    });

    cancelCreateTask();
};

const startTaskEdit = (task) => {
    if (!canManageTaskList.value) {
        return;
    }

    editingTaskId.value = task.id;
    editTaskDraft.value = {
        title: String(task.title ?? ''),
        description: String(task.description ?? ''),
        instructions_markdown: String(task.instructions_markdown ?? ''),
    };
};

const cancelTaskEdit = () => {
    editingTaskId.value = null;
    editTaskDraft.value = {
        title: '',
        description: '',
        instructions_markdown: '',
    };
};

const submitTaskEdit = (task) => {
    if (!canManageTaskList.value || isTaskUpdating(task.id)) {
        return;
    }

    const title = String(editTaskDraft.value.title ?? '').trim();
    if (title === '') {
        return;
    }

    emit('update-task', {
        task_id: task.id,
        title,
        description: String(editTaskDraft.value.description ?? ''),
        instructions_markdown: String(editTaskDraft.value.instructions_markdown ?? ''),
    });

    cancelTaskEdit();
};

const confirmTaskDelete = (task) => {
    if (!canManageTaskList.value || isTaskDeleting(task.id)) {
        return;
    }

    if (!window.confirm(`Delete task #${task.sequence} "${task.title}"?`)) {
        return;
    }

    emit('delete-task', { task_id: task.id });
};

const startTaskRegeneration = (task) => {
    if (!canManageTaskList.value) {
        return;
    }

    regeneratingTaskId.value = task.id;
    regenerateAmendNotes.value = '';
};

const cancelTaskRegeneration = () => {
    regeneratingTaskId.value = null;
    regenerateAmendNotes.value = '';
};

const submitTaskRegeneration = (task) => {
    if (!canManageTaskList.value || isTaskRegenerating(task.id)) {
        return;
    }

    const amendNotes = String(regenerateAmendNotes.value ?? '').trim();
    if (amendNotes === '') {
        return;
    }

    emit('regenerate-task', {
        task_id: task.id,
        amend_notes: amendNotes,
    });

    cancelTaskRegeneration();
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                {{ isRulesMode ? 'Rules' : (isTasksMode ? 'Tasks' : 'Build') }}
            </h3>
            <span class="rounded-full border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 capitalize dark:border-gray-600 dark:text-gray-200">
                {{ statusLabel }}
            </span>
        </div>

        <div v-if="isRulesMode" class="mt-4 rounded border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Optional Step: Project Rules</p>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                Add markdown rules that must be respected for build task generation and build execution context.
            </p>

            <div class="mt-3">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Upload Rule Files</label>
                <input
                    type="file"
                    multiple
                    accept=".md,.markdown,.txt,text/markdown,text/plain"
                    class="mt-1 block w-full cursor-pointer rounded border border-gray-300 bg-white px-3 py-2 text-xs text-gray-700 file:mr-3 file:rounded file:border-0 file:bg-indigo-600 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    :disabled="disabled || actions.generateBuildTasks"
                    @change="handleProjectRuleFileSelection"
                />
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Multiple files supported. Accepted: .md, .markdown, .txt</p>
                <div v-if="projectRuleFiles.length > 0" class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="(file, index) in projectRuleFiles"
                        :key="`${file.name}-${index}`"
                        class="inline-flex items-center gap-2 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-[11px] text-indigo-800 dark:border-indigo-800/70 dark:bg-indigo-950/30 dark:text-indigo-200"
                    >
                        <span>{{ file.name }}</span>
                        <button
                            type="button"
                            class="rounded px-1 text-[10px] font-semibold hover:bg-indigo-100 dark:hover:bg-indigo-900/40"
                            :disabled="disabled || actions.generateBuildTasks"
                            @click="removeProjectRuleFile(index)"
                        >
                            Remove
                        </button>
                    </span>
                </div>
            </div>

            <div class="mt-4 space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Inline Rules</p>
                    <button
                        type="button"
                        class="rounded border border-indigo-300 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800/70 dark:text-indigo-300 dark:hover:bg-indigo-950/30"
                        :disabled="disabled || actions.generateBuildTasks"
                        @click="addProjectRule"
                    >
                        Add Markdown Rule
                    </button>
                </div>

                <div v-if="projectRulesDraft.length === 0" class="rounded border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                    No inline project rules added.
                </div>

                <div
                    v-for="(rule, index) in projectRulesDraft"
                    :key="rule.id"
                    class="rounded border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"
                >
                    <div class="mb-2 flex items-center gap-2">
                        <input
                            :value="rule.title"
                            type="text"
                            class="w-full rounded border border-gray-300 px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            :placeholder="`Rule ${index + 1} title`"
                            @input="updateProjectRuleTitle(index, $event.target.value)"
                        />
                        <button
                            type="button"
                            class="rounded border border-red-300 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-50 dark:border-red-800/70 dark:text-red-300 dark:hover:bg-red-950/30"
                            :disabled="disabled || actions.generateBuildTasks"
                            @click="removeProjectRule(index)"
                        >
                            Remove
                        </button>
                    </div>
                    <MarkdownEditor
                        :model-value="rule.markdown"
                        placeholder="Write this project rule in markdown..."
                        @update:model-value="updateProjectRuleMarkdown(index, $event)"
                    />
                    <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                        Source: {{ rule.source }}<span v-if="rule.filename"> · {{ rule.filename }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <button
                v-if="isRulesMode || isTasksMode"
                type="button"
                class="rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canGenerate"
                @click="submitTaskGeneration"
            >
                {{ actions.generateBuildTasks ? 'Generating...' : 'Generate Build Tasks' }}
            </button>
            <button
                v-if="isTasksMode"
                type="button"
                class="rounded border border-indigo-400 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-800/70 dark:text-indigo-300 dark:hover:bg-indigo-950/30"
                :disabled="!canApproveTasks"
                @click="emit('approve-tasks')"
            >
                {{ actions.approveBuildTasks ? 'Approving...' : (tasksApprovedAt ? 'Re-Approve Build Tasks' : 'Approve Build Tasks') }}
            </button>
            <button
                v-if="isTasksMode"
                type="button"
                class="rounded border border-green-400 px-3 py-2 text-xs font-semibold text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-green-800/70 dark:text-green-300 dark:hover:bg-green-950/30"
                :disabled="!canStart"
                @click="emit('start')"
            >
                {{ actions.startBuild ? 'Starting...' : 'Start Build' }}
            </button>

            <button
                v-if="isExecutionMode"
                type="button"
                class="rounded border border-amber-400 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-800/70 dark:text-amber-300 dark:hover:bg-amber-950/30"
                :disabled="!canPause"
                @click="emit('pause')"
            >
                {{ actions.pauseBuild ? 'Pausing...' : 'Pause Build' }}
            </button>
            <button
                v-if="isExecutionMode"
                type="button"
                class="rounded border border-sky-400 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-sky-800/70 dark:text-sky-300 dark:hover:bg-sky-950/30"
                :disabled="!canResume"
                @click="emit('resume')"
            >
                {{ actions.resumeBuild ? 'Resuming...' : 'Resume Build' }}
            </button>
            <button
                v-if="isExecutionMode"
                type="button"
                class="rounded border border-rose-400 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-800/70 dark:text-rose-300 dark:hover:bg-rose-950/30"
                :disabled="!canRetry"
                @click="emit('retry')"
            >
                {{ retryLabel }}
            </button>
            <button
                v-if="isExecutionMode"
                type="button"
                class="rounded border border-fuchsia-400 px-3 py-2 text-xs font-semibold text-fuchsia-700 hover:bg-fuchsia-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-fuchsia-800/70 dark:text-fuchsia-300 dark:hover:bg-fuchsia-950/30"
                :disabled="!canRerunAll"
                @click="emit('rerun-all')"
            >
                {{ actions.startBuild ? 'Re-running build...' : 'Re-run Build' }}
            </button>
        </div>

        <div v-if="(isRulesMode || isTasksMode) && status === 'generating_tasks'" class="mt-4 rounded border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-800 dark:border-indigo-800/70 dark:bg-indigo-950/30 dark:text-indigo-200">
            Generating build tasks from the approved plan...
        </div>

        <div v-if="isTasksMode && tasksApprovedAt" class="mt-4 rounded border border-green-300 bg-green-50 p-3 text-xs text-green-800 dark:border-green-800/70 dark:bg-green-950/30 dark:text-green-200">
            <p class="font-semibold">Build tasks approved.</p>
            <p class="mt-1">Approved at {{ new Date(tasksApprovedAt).toLocaleString() }}.</p>
            <p v-if="taskProviderSyncStatus === 'queued' || taskProviderSyncStatus === 'syncing'" class="mt-1">Syncing approved tasks to task provider...</p>
            <p v-if="taskProviderSyncStatus === 'synced'" class="mt-1">Task provider sync completed.</p>
            <p v-if="taskProviderSyncStatus === 'failed' && taskProviderSyncError.summary" class="mt-1 text-red-700 dark:text-red-300">Task provider sync failed: {{ taskProviderSyncError.summary }}</p>
            <p v-if="taskProviderSync.project_url" class="mt-2">
                <a :href="taskProviderSync.project_url" target="_blank" rel="noreferrer" class="font-semibold underline">Open synced provider project</a>
            </p>
        </div>

        <div v-if="(isRulesMode || isTasksMode) && status === 'failed'" class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
            <p class="font-semibold">Build task generation failed.</p>
            <p v-if="buildError.summary" class="mt-1 whitespace-pre-wrap">{{ buildError.summary }}</p>
            <details v-if="buildError.details" class="mt-2">
                <summary class="cursor-pointer font-medium">Show technical details</summary>
                <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap rounded border border-red-200 bg-white p-2 text-[11px] text-red-700 dark:border-red-800/60 dark:bg-gray-950 dark:text-red-200">{{ buildError.details }}</pre>
            </details>
            <p class="mt-1">You can retry by clicking Generate Build Tasks.</p>
        </div>

        <div v-if="isExecutionMode && flags.approval_required" class="mt-4 rounded border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800/70 dark:bg-amber-950/30 dark:text-amber-200">
            <p class="font-semibold">Approval likely required in active run output.</p>
            <p v-if="flags.approval_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.approval_excerpt }}</p>
        </div>

        <div v-if="isExecutionMode && flags.permission_required" class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-xs text-red-900 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
            <p class="font-semibold">Write permission blocker detected.</p>
            <p v-if="flags.permission_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.permission_excerpt }}</p>
        </div>

        <div v-if="isExecutionMode && flags.clarification_required" class="mt-4 rounded border border-sky-300 bg-sky-50 p-3 text-xs text-sky-900 dark:border-sky-800/70 dark:bg-sky-950/30 dark:text-sky-200">
            <p class="font-semibold">Clarification requested by the AI.</p>
            <p v-if="flags.clarification_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.clarification_excerpt }}</p>
            <p class="mt-1">Submit clarification below, then click Resume Build.</p>
        </div>

        <div v-if="isExecutionMode && flags.rate_limit_detected" class="mt-4 rounded border border-red-300 bg-red-50 p-3 text-xs text-red-900 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
            <p class="font-semibold">Rate limit detected.</p>
            <p v-if="flags.rate_limit_reset_at" class="mt-1">Reset at: {{ flags.rate_limit_reset_at }}</p>
            <p v-if="flags.rate_limit_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.rate_limit_excerpt }}</p>
        </div>

        <div v-if="isExecutionMode && activeTask" class="mt-4 rounded border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Current Task</p>
            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">#{{ activeTask.sequence }} · {{ activeTask.title }}</p>
            <p v-if="activeTask.description" class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ activeTask.description }}</p>
            <p v-if="activeRun" class="mt-2 text-xs text-gray-600 dark:text-gray-300">Run #{{ activeRun.id }} · {{ activeRun.status }}</p>
        </div>

        <div v-if="isExecutionMode && activeRunLogEntries.length > 0" class="mt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Active Run Log Tail</p>
            <div class="mt-2 max-h-56 overflow-auto rounded border border-gray-200 bg-gray-950 p-3 text-xs text-gray-100 dark:border-gray-700">
                <div v-for="entry in activeRunLogEntries" :key="entry.key" class="mb-2 whitespace-pre-wrap break-words">
                    <div class="text-[11px] text-gray-400">{{ entry.prefix }}</div>
                    <MarkdownRenderer
                        v-if="entry.format === 'markdown'"
                        :markdown="entry.payload"
                        :normalize="false"
                        class="tail-markdown prose prose-sm mt-1 max-w-none rounded border border-emerald-500/20 bg-emerald-500/5 px-2 py-1 font-sans text-emerald-100 dark:prose-invert prose-headings:mb-2 prose-headings:mt-3 prose-p:my-1.5 prose-li:my-0.5 prose-code:rounded prose-code:bg-gray-800 prose-code:px-1 prose-code:py-0.5"
                    />
                    <pre v-else class="mt-0.5 whitespace-pre-wrap break-words font-mono" :class="{
                        'text-rose-300': entry.tone === 'stderr',
                        'text-sky-200': entry.tone === 'lifecycle',
                        'text-emerald-200': entry.tone === 'structured',
                    }">{{ entry.payload }}</pre>
                </div>
            </div>
        </div>

        <div v-if="isExecutionMode" class="mt-4 rounded border border-gray-200 p-3 dark:border-gray-700">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Clarification</p>
            <textarea
                v-model="clarification"
                rows="3"
                class="mt-2 w-full rounded border border-gray-300 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-400"
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

        <div v-if="!isRulesMode" class="mt-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tasks</p>
                <button
                    v-if="isTasksMode"
                    type="button"
                    class="rounded border border-indigo-300 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-800/70 dark:text-indigo-300 dark:hover:bg-indigo-950/30"
                    :disabled="!canManageTaskList || actions.createBuildTask"
                    @click="openCreateTaskForm"
                >
                    {{ actions.createBuildTask ? 'Adding...' : 'Add Task' }}
                </button>
            </div>
            <div
                v-if="isTasksMode && showCreateTaskForm"
                class="mt-2 rounded border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">New Task</p>
                <input
                    v-model="createTaskDraft.title"
                    type="text"
                    class="mt-2 w-full rounded border border-gray-300 px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    placeholder="Task title"
                />
                <textarea
                    v-model="createTaskDraft.description"
                    rows="2"
                    class="mt-2 w-full rounded border border-gray-300 px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    placeholder="Task description (optional)"
                />
                <textarea
                    v-model="createTaskDraft.instructions_markdown"
                    rows="4"
                    class="mt-2 w-full rounded border border-gray-300 px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    placeholder="Task instructions in markdown (optional)"
                />
                <div class="mt-2 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                        :disabled="actions.createBuildTask"
                        @click="cancelCreateTask"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="actions.createBuildTask || createTaskDraft.title.trim() === ''"
                        @click="submitCreateTask"
                    >
                        {{ actions.createBuildTask ? 'Adding...' : 'Add Task' }}
                    </button>
                </div>
            </div>
            <div v-if="tasks.length === 0" class="mt-2 rounded border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                No build tasks generated yet.
            </div>
            <div v-else class="mt-2 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Task</th>
                            <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Attempts</th>
                            <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Error</th>
                            <th v-if="isTasksMode" class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template v-for="task in tasks" :key="task.id">
                            <tr>
                                <td class="px-2 py-2">
                                    <p class="text-gray-800 dark:text-gray-100">#{{ task.sequence }} {{ task.title }}</p>
                                    <p v-if="task.description" class="mt-0.5 text-[11px] text-gray-600 dark:text-gray-300">{{ task.description }}</p>
                                    <p v-if="task.metadata_json?.regeneration?.status === 'queued'" class="mt-1 text-[11px] text-indigo-700 dark:text-indigo-300">Regeneration queued...</p>
                                    <p v-if="task.metadata_json?.regeneration?.status === 'failed'" class="mt-1 text-[11px] text-red-700 dark:text-red-300">Regeneration failed: {{ task.metadata_json?.regeneration?.error || 'Unknown error' }}</p>
                                </td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ task.status }}</td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ task.attempt_count }}</td>
                                <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ task.last_error || '-' }}</td>
                                <td v-if="isTasksMode" class="px-2 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        <button
                                            type="button"
                                            class="rounded border border-blue-300 px-2 py-1 text-[11px] font-semibold text-blue-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-800/70 dark:text-blue-300 dark:hover:bg-blue-950/30"
                                            :disabled="!canManageTaskList || isTaskUpdating(task.id)"
                                            @click="startTaskEdit(task)"
                                        >
                                            {{ isTaskUpdating(task.id) ? 'Saving...' : 'Edit' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded border border-rose-300 px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-800/70 dark:text-rose-300 dark:hover:bg-rose-950/30"
                                            :disabled="!canManageTaskList || isTaskDeleting(task.id)"
                                            @click="confirmTaskDelete(task)"
                                        >
                                            {{ isTaskDeleting(task.id) ? 'Deleting...' : 'Delete' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded border border-indigo-300 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-800/70 dark:text-indigo-300 dark:hover:bg-indigo-950/30"
                                            :disabled="!canManageTaskList || isTaskRegenerating(task.id)"
                                            @click="startTaskRegeneration(task)"
                                        >
                                            {{ isTaskRegenerating(task.id) ? 'Queueing...' : 'Regenerate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="isTasksMode && editingTaskId === task.id" :key="`edit-${task.id}`" class="bg-gray-50/80 dark:bg-gray-900/30">
                                <td :colspan="isTasksMode ? 5 : 4" class="px-2 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Edit Task #{{ task.sequence }}</p>
                                    <input
                                        v-model="editTaskDraft.title"
                                        type="text"
                                        class="mt-2 w-full rounded border border-gray-300 px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        placeholder="Task title"
                                    />
                                    <textarea
                                        v-model="editTaskDraft.description"
                                        rows="2"
                                        class="mt-2 w-full rounded border border-gray-300 px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        placeholder="Task description"
                                    />
                                    <textarea
                                        v-model="editTaskDraft.instructions_markdown"
                                        rows="4"
                                        class="mt-2 w-full rounded border border-gray-300 px-2 py-1 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        placeholder="Task instructions in markdown"
                                    />
                                    <div class="mt-2 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded border border-gray-300 px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                            :disabled="isTaskUpdating(task.id)"
                                            @click="cancelTaskEdit"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded bg-blue-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="isTaskUpdating(task.id) || editTaskDraft.title.trim() === ''"
                                            @click="submitTaskEdit(task)"
                                        >
                                            {{ isTaskUpdating(task.id) ? 'Saving...' : 'Save Changes' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="isTasksMode && regeneratingTaskId === task.id" :key="`regen-${task.id}`" class="bg-indigo-50/60 dark:bg-indigo-950/20">
                                <td :colspan="isTasksMode ? 5 : 4" class="px-2 py-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Regenerate Task #{{ task.sequence }} With Amend Notes</p>
                                    <textarea
                                        v-model="regenerateAmendNotes"
                                        rows="3"
                                        class="mt-2 w-full rounded border border-indigo-300 px-2 py-1 text-xs text-gray-900 dark:border-indigo-800/70 dark:bg-gray-900 dark:text-gray-100"
                                        placeholder="Describe exactly what should change for this task..."
                                    />
                                    <div class="mt-2 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded border border-gray-300 px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                            :disabled="isTaskRegenerating(task.id)"
                                            @click="cancelTaskRegeneration"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded bg-indigo-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="isTaskRegenerating(task.id) || regenerateAmendNotes.trim() === ''"
                                            @click="submitTaskRegeneration(task)"
                                        >
                                            {{ isTaskRegenerating(task.id) ? 'Queueing...' : 'Regenerate Task' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="isExecutionMode && build.completion_summary" class="mt-4 rounded border border-green-300 bg-green-50 p-3 text-xs text-green-800 dark:border-green-800/70 dark:bg-green-950/30 dark:text-green-300">
            {{ build.completion_summary }}
        </div>

        <div v-if="Array.isArray(activity) && activity.length > 0" class="mt-4 rounded border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">AI Activity</p>
            <div class="mt-2 space-y-1">
                <p v-for="item in activity" :key="`${item.sequence}-${item.message}`" class="text-xs text-gray-700 dark:text-gray-300">
                    <span class="font-medium text-gray-500 dark:text-gray-400">{{ item.at_label || item.at || 'now' }}</span>
                    <span class="ml-1">{{ item.message }}</span>
                </p>
            </div>
        </div>
    </div>
</template>
