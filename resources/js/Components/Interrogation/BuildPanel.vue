<script setup>
import MarkdownEditor from '@/Components/Markdown/MarkdownEditor.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { formatInterrogationError } from '@/Components/Interrogation/errorFormatting';
import { buildAgentRunEventPresentation } from '@/Support/agentRunEventFormatting';
import { confirmDialog } from '@/Support/confirmDialog';
import { GripVertical } from 'lucide-vue-next';
import draggable from 'vuedraggable';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
    'reorder-tasks',
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
const regenerateAdditionalContext = ref('');
const generationFeedback = ref('');

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
const activeRunEvents = ref([]);
const activeRunEventsRunId = ref(null);
const activeRunEventsAfterSequence = ref(0);
const activeRunEventsLoading = ref(false);
const activeRunEventsError = ref('');
const activeRunEventsBootstrapComplete = ref(false);
const activeRunEventsPollTimer = ref(null);
const activeRunLogContainer = ref(null);
const activeRunExpandedEntries = ref({});
const activeRunExpandedCommandOutputs = ref({});
const showHeartbeatEntries = ref(false);

const TERMINAL_RUN_STATUSES = new Set(['succeeded', 'failed', 'killed', 'timed_out', 'skipped']);
const ACTIVE_RUN_POLL_MS = 2000;
const ECHO_ACTIVE_RUN_POLL_MS = 10000;
const echoRunChannelId = ref(null);
const LOG_PREVIEW_CHAR_LIMIT = 800;

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
const canReorderTasks = computed(() => canManageTaskList.value && !props.actions.reorderBuildTasks && tasks.value.length > 1);
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
const selectedExecutionTaskId = ref(null);
const selectedTaskDetailsId = ref(null);
const orderedTasks = ref([]);

const normalizeTaskStatus = (value) => String(value ?? 'pending').trim().toLowerCase();
const COMPLETED_TASK_STATUSES = new Set(['completed']);
const FAILED_TASK_STATUSES = new Set(['failed', 'blocked', 'cancelled', 'killed', 'timed_out']);
const RUNNING_TASK_STATUSES = new Set(['in_progress', 'running']);

const executionCompletedCount = computed(() => {
    return tasks.value.filter((task) => COMPLETED_TASK_STATUSES.has(normalizeTaskStatus(task?.status))).length;
});

const executionProgressPercent = computed(() => {
    if (tasks.value.length === 0) {
        return 0;
    }

    return Math.round((executionCompletedCount.value / tasks.value.length) * 100);
});

const executionSummaryLabel = computed(() => {
    if (tasks.value.length === 0) {
        return 'No tasks generated yet';
    }

    return `${executionCompletedCount.value} of ${tasks.value.length} tasks complete`;
});

const tasksPendingCount = computed(() => {
    return tasks.value.filter((task) => normalizeTaskStatus(task?.status) === 'pending').length;
});

const tasksCompletedCount = computed(() => {
    return tasks.value.filter((task) => COMPLETED_TASK_STATUSES.has(normalizeTaskStatus(task?.status))).length;
});

const tasksFailedCount = computed(() => {
    return tasks.value.filter((task) => FAILED_TASK_STATUSES.has(normalizeTaskStatus(task?.status))).length;
});

const taskStatusLabel = (value) => {
    const normalized = normalizeTaskStatus(value);
    if (normalized === 'in_progress') {
        return 'running';
    }

    return normalized === '' ? 'pending' : normalized;
};

const humanizeStatus = (value) => String(value ?? '')
    .trim()
    .replace(/_/g, ' ')
    .replace(/\s+/g, ' ')
    .toLowerCase();

const activeRunDisplayStatus = computed(() => {
    const effectiveStatus = humanizeStatus(activeRun.value?.effective_status);
    if (effectiveStatus !== '') {
        if (effectiveStatus.startsWith('failed')) {
            return 'failed';
        }

        if (effectiveStatus.startsWith('blocked')) {
            return 'blocked';
        }

        return effectiveStatus;
    }

    const runStatus = humanizeStatus(activeRun.value?.status);
    if (runStatus !== '') {
        return runStatus;
    }

    return 'unknown';
});

const taskStatusBadgeClass = (value) => {
    const normalized = normalizeTaskStatus(value);

    if (COMPLETED_TASK_STATUSES.has(normalized)) {
        return 'border-success/30 bg-success/10 text-success';
    }

    if (RUNNING_TASK_STATUSES.has(normalized)) {
        return 'border-primary/30 bg-primary/10 text-primary';
    }

    if (FAILED_TASK_STATUSES.has(normalized)) {
        return 'border-destructive/30 bg-destructive/10 text-destructive';
    }

    return 'border-input bg-muted/30 text-muted-foreground';
};

const taskAttemptLabel = (task) => {
    const attempts = Number(task?.attempt_count ?? 0);
    if (!Number.isFinite(attempts) || attempts <= 0) {
        return 'Not started';
    }

    return `${attempts} attempt${attempts === 1 ? '' : 's'}`;
};

const selectDefaultExecutionTask = () => {
    const taskList = tasks.value;
    if (!Array.isArray(taskList) || taskList.length === 0) {
        selectedExecutionTaskId.value = null;
        return;
    }

    if (activeTask.value?.id) {
        selectedExecutionTaskId.value = activeTask.value.id;
        return;
    }

    const preferredTask = taskList.find((task) => RUNNING_TASK_STATUSES.has(normalizeTaskStatus(task?.status)))
        ?? taskList.find((task) => FAILED_TASK_STATUSES.has(normalizeTaskStatus(task?.status)))
        ?? taskList.find((task) => COMPLETED_TASK_STATUSES.has(normalizeTaskStatus(task?.status)))
        ?? taskList[0];

    selectedExecutionTaskId.value = preferredTask?.id ?? null;
};

const toggleExecutionTask = (taskId) => {
    if (selectedExecutionTaskId.value === taskId) {
        selectedExecutionTaskId.value = null;
        return;
    }

    selectedExecutionTaskId.value = taskId;
};

const toggleTaskDetails = (taskId) => {
    if (selectedTaskDetailsId.value === taskId) {
        selectedTaskDetailsId.value = null;
        return;
    }

    selectedTaskDetailsId.value = taskId;
};

watch(
    [tasks, activeTask, isExecutionMode],
    () => {
        if (!isExecutionMode.value) {
            return;
        }

        const stillExists = tasks.value.some((task) => task?.id === selectedExecutionTaskId.value);
        if (!stillExists || (activeTask.value?.id && selectedExecutionTaskId.value !== activeTask.value.id)) {
            selectDefaultExecutionTask();
        }
    },
    { deep: true, immediate: true }
);

watch(
    [tasks, isTasksMode, editingTaskId, regeneratingTaskId],
    () => {
        if (!isTasksMode.value) {
            selectedTaskDetailsId.value = null;
            return;
        }

        const ids = tasks.value.map((task) => task?.id).filter((id) => id !== null && id !== undefined);
        const selectedExists = ids.includes(selectedTaskDetailsId.value);

        if (!selectedExists) {
            selectedTaskDetailsId.value = ids[0] ?? null;
        }

        if (editingTaskId.value !== null) {
            selectedTaskDetailsId.value = editingTaskId.value;
        }

        if (regeneratingTaskId.value !== null) {
            selectedTaskDetailsId.value = regeneratingTaskId.value;
        }
    },
    { deep: true, immediate: true }
);

watch(
    tasks,
    (nextTasks) => {
        orderedTasks.value = Array.isArray(nextTasks) ? [...nextTasks] : [];
    },
    { deep: true, immediate: true }
);

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

const activeRunId = computed(() => Number(activeRun.value?.id ?? 0));

const clearActiveRunEventsPollTimer = () => {
    if (activeRunEventsPollTimer.value !== null) {
        clearTimeout(activeRunEventsPollTimer.value);
        activeRunEventsPollTimer.value = null;
    }
};

const normalizeRunEventList = (entries) => {
    if (!Array.isArray(entries)) {
        return [];
    }

    return entries
        .map((entry, index) => {
            const sequence = Number(entry?.sequence ?? NaN);
            if (!Number.isFinite(sequence) || sequence <= 0) {
                return null;
            }

            return {
                id: entry?.id ?? `run-event-${sequence}-${index}`,
                sequence,
                event_type: String(entry?.event_type ?? '').trim(),
                payload: entry?.payload ?? '',
                event_ts: entry?.event_ts ?? entry?.created_at ?? null,
                reasoning_step: entry?.reasoning_step ?? null,
            };
        })
        .filter((entry) => entry !== null)
        .sort((a, b) => Number(a.sequence) - Number(b.sequence));
};

const mergeActiveRunEvents = (incomingEntries, { replace = false } = {}) => {
    const incoming = normalizeRunEventList(incomingEntries);
    if (incoming.length === 0 && !replace) {
        return;
    }

    const combined = replace ? incoming : [...activeRunEvents.value, ...incoming];
    const bySequence = new Map();

    combined.forEach((entry) => {
        bySequence.set(Number(entry.sequence), entry);
    });

    const merged = Array.from(bySequence.values())
        .sort((a, b) => Number(a.sequence) - Number(b.sequence))
        .slice(-2500);

    activeRunEvents.value = merged;
    activeRunEventsAfterSequence.value = Number(merged[merged.length - 1]?.sequence ?? 0);
};

const fetchRunEvents = async (runId, { bootstrap = false } = {}) => {
    if (!Number.isFinite(runId) || runId <= 0) {
        return;
    }

    if (activeRunEventsLoading.value) {
        return;
    }

    activeRunEventsLoading.value = true;
    activeRunEventsError.value = '';

    try {
        let afterSequence = bootstrap ? 0 : Number(activeRunEventsAfterSequence.value ?? 0);
        let hasMore = true;
        let pageCount = 0;
        const collected = [];

        while (hasMore && pageCount < 40) {
            const { data } = await axios.get(`/agent/api/v1/runs/${runId}/events`, {
                params: {
                    after_sequence: afterSequence,
                    limit: 500,
                },
            });

            if (activeRunEventsRunId.value !== runId) {
                return;
            }

            const page = normalizeRunEventList(data?.data);
            if (page.length > 0) {
                collected.push(...page);
            }

            const nextAfter = Number(data?.meta?.next_after_sequence ?? afterSequence);
            hasMore = Boolean(data?.meta?.has_more);

            if (Number.isFinite(nextAfter) && nextAfter > afterSequence) {
                afterSequence = nextAfter;
            } else if (page.length > 0) {
                afterSequence = Number(page[page.length - 1]?.sequence ?? afterSequence);
            } else {
                hasMore = false;
            }

            pageCount += 1;
        }

        if (activeRunEventsRunId.value !== runId) {
            return;
        }

        if (bootstrap) {
            mergeActiveRunEvents(collected, { replace: true });
            activeRunEventsBootstrapComplete.value = true;
        } else if (collected.length > 0) {
            mergeActiveRunEvents(collected, { replace: false });
        }
    } catch (error) {
        activeRunEventsError.value = error?.response?.data?.error?.message ?? 'Failed to load full run log.';
    } finally {
        activeRunEventsLoading.value = false;
    }
};

const scheduleActiveRunEventsPoll = () => {
    clearActiveRunEventsPollTimer();

    const runId = Number(activeRunId.value ?? 0);
    const runStatus = String(activeRun.value?.status ?? '').trim().toLowerCase();

    if (!Number.isFinite(runId) || runId <= 0 || TERMINAL_RUN_STATUSES.has(runStatus)) {
        return;
    }

    const pollMs = echoRunChannelId.value === runId ? ECHO_ACTIVE_RUN_POLL_MS : ACTIVE_RUN_POLL_MS;
    activeRunEventsPollTimer.value = setTimeout(async () => {
        await fetchRunEvents(runId, { bootstrap: false });
        scheduleActiveRunEventsPoll();
    }, pollMs);
};

watch(
    activeRunId,
    async (nextRunId) => {
        clearActiveRunEventsPollTimer();

        if (echoRunChannelId.value) {
            window.Echo?.leave(`run.${echoRunChannelId.value}`);
            echoRunChannelId.value = null;
        }

        activeRunEvents.value = [];
        activeRunEventsAfterSequence.value = 0;
        activeRunEventsError.value = '';
        activeRunEventsBootstrapComplete.value = false;
        activeRunExpandedEntries.value = {};
        activeRunExpandedCommandOutputs.value = {};
        showHeartbeatEntries.value = false;

        if (!Number.isFinite(nextRunId) || nextRunId <= 0) {
            activeRunEventsRunId.value = null;
            return;
        }

        activeRunEventsRunId.value = Number(nextRunId);
        mergeActiveRunEvents(activeRun.value?.log_tail ?? [], { replace: true });
        await fetchRunEvents(Number(nextRunId), { bootstrap: true });

        if (window.Echo && Number.isFinite(nextRunId) && nextRunId > 0) {
            echoRunChannelId.value = nextRunId;
            window.Echo.private(`run.${nextRunId}`)
                .listen('.events.available', () => {
                    fetchRunEvents(nextRunId, { bootstrap: false });
                });
        }

        scheduleActiveRunEventsPoll();
    },
    { immediate: true }
);

watch(
    () => activeRun.value?.log_tail,
    (nextTail) => {
        const runId = Number(activeRunId.value ?? 0);
        if (!Number.isFinite(runId) || runId <= 0 || activeRunEventsRunId.value !== runId) {
            return;
        }

        mergeActiveRunEvents(nextTail ?? [], { replace: false });
    },
    { deep: true }
);

const activeRunSourceEvents = computed(() => {
    const runId = Number(activeRunId.value ?? 0);
    if (Number.isFinite(runId) && runId > 0 && activeRunEventsRunId.value === runId && activeRunEvents.value.length > 0) {
        return activeRunEvents.value;
    }

    return normalizeRunEventList(activeRun.value?.log_tail ?? []);
});

const activeRunPresentation = computed(() => {
    return buildAgentRunEventPresentation(
        activeRunSourceEvents.value,
        {
            issues: Array.isArray(props.build?.issues) ? props.build.issues : [],
        }
    );
});
const activeRunTimelineEntries = computed(() => {
    return Array.isArray(activeRunPresentation.value?.timelineEntries)
        ? activeRunPresentation.value.timelineEntries
        : [];
});
const activeRunHeartbeatEntries = computed(() => {
    return Array.isArray(activeRunPresentation.value?.heartbeatEntries)
        ? activeRunPresentation.value.heartbeatEntries
        : [];
});
const activeRunHeartbeatSummary = computed(() => activeRunPresentation.value?.heartbeatSummary ?? null);
const activeRunIssues = computed(() => {
    return Array.isArray(activeRunPresentation.value?.issues)
        ? activeRunPresentation.value.issues
        : [];
});
const hasActiveRunPresentation = computed(() => {
    return activeRunTimelineEntries.value.length > 0
        || activeRunHeartbeatEntries.value.length > 0
        || activeRunIssues.value.length > 0;
});
const timelineEntryKindClass = (entry) => {
    const kind = String(entry?.kind ?? '').trim().toLowerCase();
    if (kind === 'stderr') {
        return 'border-destructive/30 bg-destructive/5';
    }

    if (kind === 'command') {
        return 'border-primary/30 bg-primary/5';
    }

    if (kind === 'lifecycle') {
        return 'border-warning/30 bg-warning/10';
    }

    if (kind === 'tool_call' || kind === 'tool_result') {
        return 'border-primary/30 bg-primary/5';
    }

    return 'border-border bg-muted/10';
};
const timelineEntryText = (entry) => {
    const raw = entry?.text ?? '';
    if (typeof raw === 'string') return raw.trim();
    if (raw && typeof raw === 'object') {
        try { return JSON.stringify(raw); } catch { return '[unprintable]'; }
    }
    return String(raw).trim();
};
const timelineEntryDisplayFormat = (entry) => String(entry?.displayFormat ?? 'text').trim().toLowerCase() || 'text';
const isTimelineEntryExpandable = (entry) => {
    if (timelineEntryDisplayFormat(entry) === 'markdown') {
        return false;
    }

    return timelineEntryText(entry).length > LOG_PREVIEW_CHAR_LIMIT;
};
const isTimelineEntryExpanded = (entry) => Boolean(activeRunExpandedEntries.value?.[entry?.key]);
const toggleTimelineEntryExpanded = (entry) => {
    const key = String(entry?.key ?? '').trim();
    if (key === '') {
        return;
    }

    activeRunExpandedEntries.value = {
        ...activeRunExpandedEntries.value,
        [key]: !Boolean(activeRunExpandedEntries.value?.[key]),
    };
};
const timelineEntryPreviewText = (entry) => {
    const text = timelineEntryText(entry);
    if (!isTimelineEntryExpandable(entry) || isTimelineEntryExpanded(entry)) {
        return text;
    }

    return `${text.slice(0, LOG_PREVIEW_CHAR_LIMIT).trimEnd()}…`;
};
const formatPossibleJsonText = (value) => {
    const text = String(value ?? '').trim();
    if (text === '') {
        return '';
    }

    const first = text[0];
    if (first !== '{' && first !== '[') {
        return text;
    }

    try {
        return JSON.stringify(JSON.parse(text), null, 2);
    } catch {
        return text;
    }
};
const commandOutputText = (entry) => formatPossibleJsonText(String(entry?.command?.output ?? '').trim());
const isCommandOutputExpandable = (entry) => commandOutputText(entry).length > LOG_PREVIEW_CHAR_LIMIT;
const isCommandOutputExpanded = (entry) => Boolean(activeRunExpandedCommandOutputs.value?.[entry?.key]);
const toggleCommandOutputExpanded = (entry) => {
    const key = String(entry?.key ?? '').trim();
    if (key === '') {
        return;
    }

    activeRunExpandedCommandOutputs.value = {
        ...activeRunExpandedCommandOutputs.value,
        [key]: !Boolean(activeRunExpandedCommandOutputs.value?.[key]),
    };
};
const commandOutputPreview = (entry) => {
    const text = commandOutputText(entry);
    if (!isCommandOutputExpandable(entry) || isCommandOutputExpanded(entry)) {
        return text;
    }

    return `${text.slice(0, LOG_PREVIEW_CHAR_LIMIT).trimEnd()}…`;
};
const commandStatusClass = (entry) => {
    const status = String(entry?.command?.status ?? '').trim().toLowerCase();
    if (status === 'completed') {
        const exitCode = Number(entry?.command?.exitCode ?? NaN);
        if (Number.isFinite(exitCode) && exitCode === 0) {
            return 'border-success/30 bg-success/10 text-success';
        }

        return 'border-destructive/30 bg-destructive/10 text-destructive';
    }

    return 'border-primary/30 bg-primary/10 text-primary';
};
const timelineEntryHeaderLabel = (entry) => {
    const kind = String(entry?.kind ?? '').trim().toLowerCase();
    if (kind === 'command') {
        return 'Command';
    }

    if (kind === 'stderr') {
        return 'stderr';
    }

    if (kind === 'lifecycle') {
        return 'Lifecycle';
    }

    if (kind === 'tool_call') {
        return 'Tool call';
    }

    if (kind === 'tool_result') {
        return 'Tool result';
    }

    if (kind === 'structured') {
        return 'Structured';
    }

    return 'stdout';
};

const scrollActiveRunLogToBottom = () => {
    const container = activeRunLogContainer.value;
    if (!container) {
        return;
    }

    container.scrollTop = container.scrollHeight;
};

watch(
    activeRunEventsAfterSequence,
    async (nextSequence, previousSequence) => {
        if (!hasActiveRunPresentation.value) {
            return;
        }

        if (!Number.isFinite(nextSequence) || nextSequence <= 0) {
            return;
        }

        if (nextSequence === previousSequence) {
            return;
        }

        await nextTick();
        scrollActiveRunLogToBottom();
    }
);

watch(
    activeRunId,
    async (nextRunId, previousRunId) => {
        if (nextRunId === previousRunId || !hasActiveRunPresentation.value) {
            return;
        }

        await nextTick();
        scrollActiveRunLogToBottom();
    }
);

onBeforeUnmount(() => {
    clearActiveRunEventsPollTimer();
    if (echoRunChannelId.value) {
        window.Echo?.leave(`run.${echoRunChannelId.value}`);
    }
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

    const notes = String(generationFeedback.value ?? '').trim();

    emit('generate-tasks', {
        project_rules: buildProjectRulesPayload(),
        project_rule_files: [...projectRuleFiles.value],
        notes: notes !== '' ? notes : null,
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

const confirmTaskDelete = async (task) => {
    if (!canManageTaskList.value || isTaskDeleting(task.id)) {
        return;
    }

    const approved = await confirmDialog(`Delete task #${task.sequence} "${task.title}"?`, {
        title: 'Delete Task',
        confirmText: 'Delete',
        confirmVariant: 'destructive',
    });

    if (!approved) {
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
    regenerateAdditionalContext.value = '';
};

const cancelTaskRegeneration = () => {
    regeneratingTaskId.value = null;
    regenerateAmendNotes.value = '';
    regenerateAdditionalContext.value = '';
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
        additional_context: String(regenerateAdditionalContext.value ?? '').trim() || null,
    });

    cancelTaskRegeneration();
};

const handleTaskDragEnd = (event) => {
    if (!canReorderTasks.value) {
        return;
    }

    const fromIndex = Number(event?.oldIndex ?? -1);
    const toIndex = Number(event?.newIndex ?? -1);
    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
        return;
    }

    const previousOrder = tasks.value
        .map((task) => Number(task?.id))
        .filter((id) => Number.isFinite(id));
    const nextOrder = orderedTasks.value
        .map((task) => Number(task?.id))
        .filter((id) => Number.isFinite(id));

    if (nextOrder.length === 0 || nextOrder.join(',') === previousOrder.join(',')) {
        return;
    }

    emit('reorder-tasks', {
        task_ids: nextOrder,
    });
};

defineExpose({ activeRunEvents });
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4  ">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-foreground ">
                {{ isRulesMode ? 'Rules' : (isTasksMode ? 'Tasks' : 'Build') }}
            </h3>
            <span class="rounded-full border border-input px-2 py-1 text-xs font-medium text-foreground capitalize  ">
                {{ statusLabel }}
            </span>
        </div>

        <div v-if="isRulesMode" class="mt-4 rounded border border-border bg-muted/30 p-3  ">
            <p class="text-sm font-semibold text-foreground ">Optional Step: Project Rules</p>
            <p class="mt-1 text-xs text-muted-foreground ">
                Add markdown rules that must be respected for build task generation and build execution context.
            </p>

            <div class="mt-3">
                <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Generation Feedback / Context (Optional)</label>
                <textarea
                    v-model="generationFeedback"
                    rows="3"
                    class="mt-1 w-full rounded border border-input bg-input-background px-3 py-2 text-xs text-foreground"
                    placeholder="Add additional context for task generation (scope, constraints, or priorities)."
                />
                <p class="mt-1 text-[11px] text-muted-foreground">Sent to the AI runner when generating build tasks.</p>
            </div>

            <div class="mt-3">
                <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Upload Rule Files</label>
                <input
                    type="file"
                    multiple
                    accept=".md,.markdown,.txt,text/markdown,text/plain"
                    class="mt-1 block w-full cursor-pointer rounded border border-input bg-card px-3 py-2 text-xs text-foreground file:mr-3 file:rounded file:border-0 file:bg-primary file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white hover:file:bg-primary/50   "
                    :disabled="disabled || actions.generateBuildTasks"
                    @change="handleProjectRuleFileSelection"
                />
                <p class="mt-1 text-[11px] text-muted-foreground ">Multiple files supported. Accepted: .md, .markdown, .txt</p>
                <div v-if="projectRuleFiles.length > 0" class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="(file, index) in projectRuleFiles"
                        :key="`${file.name}-${index}`"
                        class="inline-flex items-center gap-2 rounded border border-primary/30 bg-primary/5 px-2 py-1 text-[11px] text-primary"
                    >
                        <span>{{ file.name }}</span>
                        <button
                            type="button"
                            class="rounded px-1 text-[10px] font-semibold hover:bg-primary/10"
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Inline Rules</p>
                    <button
                        type="button"
                        class="rounded border border-primary/30 px-2 py-1 text-[11px] font-semibold text-primary hover:bg-primary/5"
                        :disabled="disabled || actions.generateBuildTasks"
                        @click="addProjectRule"
                    >
                        Add Markdown Rule
                    </button>
                </div>

                <div v-if="projectRulesDraft.length === 0" class="rounded border border-dashed border-input px-3 py-2 text-xs text-muted-foreground  ">
                    No inline project rules added.
                </div>

                <div
                    v-for="(rule, index) in projectRulesDraft"
                    :key="rule.id"
                    class="rounded border border-border bg-card p-3  "
                >
                    <div class="mb-2 flex items-center gap-2">
                        <input
                            :value="rule.title"
                            type="text"
                            class="w-full rounded border border-input bg-input-background px-2 py-1 text-xs text-foreground   "
                            :placeholder="`Rule ${index + 1} title`"
                            @input="updateProjectRuleTitle(index, $event.target.value)"
                        />
                        <button
                            type="button"
                            class="rounded border border-destructive/30 px-2 py-1 text-[11px] font-semibold text-destructive hover:bg-destructive/10"
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
                    <p class="mt-2 text-[11px] text-muted-foreground ">
                        Source: {{ rule.source }}<span v-if="rule.filename"> · {{ rule.filename }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div v-if="isRulesMode" class="mt-3 flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canGenerate"
                @click="submitTaskGeneration"
            >
                {{ actions.generateBuildTasks ? 'Generating...' : 'Generate Build Tasks' }}
            </button>
        </div>

        <div v-if="isTasksMode" class="mt-3 rounded-lg border border-border bg-muted/20 p-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-base font-semibold text-foreground">Build Tasks</p>
                    <p class="mt-1 text-xs text-muted-foreground">Review and approve generated tasks before starting execution.</p>
                </div>
                <span class="rounded-full border border-input px-2 py-1 text-xs font-medium capitalize text-foreground">{{ statusLabel }}</span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span class="rounded-full border border-input bg-muted/30 px-2 py-1">{{ tasks.length }} total</span>
                <span class="rounded-full border border-success/30 bg-success/10 px-2 py-1 text-success">{{ tasksCompletedCount }} completed</span>
                <span class="rounded-full border border-input bg-muted/30 px-2 py-1">{{ tasksPendingCount }} pending</span>
                <span
                    v-if="tasksFailedCount > 0"
                    class="rounded-full border border-destructive/30 bg-destructive/10 px-2 py-1 text-destructive"
                >
                    {{ tasksFailedCount }} failed
                </span>
            </div>

            <div class="mt-3">
                <label class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Regeneration Feedback / Context (Optional)</label>
                <textarea
                    v-model="generationFeedback"
                    rows="3"
                    class="mt-1 w-full rounded border border-input bg-input-background px-3 py-2 text-xs text-foreground"
                    placeholder="Add context before regenerating all tasks (priorities, constraints, acceptance criteria, sequencing guidance)."
                />
                <p class="mt-1 text-[11px] text-muted-foreground">Sent to the AI runner when regenerating all tasks.</p>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canGenerate"
                    @click="submitTaskGeneration"
                >
                    {{ actions.generateBuildTasks ? 'Generating...' : (tasks.length > 0 ? 'Regenerate Build Tasks' : 'Generate Build Tasks') }}
                </button>
                <button
                    type="button"
                    class="rounded border border-primary/30 px-3 py-2 text-xs font-semibold text-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canApproveTasks"
                    @click="emit('approve-tasks')"
                >
                    {{ actions.approveBuildTasks ? 'Approving...' : (tasksApprovedAt ? 'Re-Approve Build Tasks' : 'Approve Build Tasks') }}
                </button>
                <button
                    type="button"
                    class="rounded border border-success/30 px-3 py-2 text-xs font-semibold text-success hover:bg-success/10 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canStart"
                    @click="emit('start')"
                >
                    {{ actions.startBuild ? 'Starting...' : 'Start Build' }}
                </button>
            </div>
        </div>

        <div v-if="(isRulesMode || isTasksMode) && status === 'generating_tasks'" class="mt-4 rounded border border-primary/30 bg-primary/5 p-3 text-xs text-primary">
            Generating build tasks from the approved plan...
        </div>

        <div v-if="isTasksMode && tasksApprovedAt" class="mt-4 rounded border border-success/30 bg-success/10 p-3 text-xs text-success">
            <p class="font-semibold">Build tasks approved.</p>
            <p class="mt-1">Approved at {{ new Date(tasksApprovedAt).toLocaleString() }}.</p>
            <p v-if="taskProviderSyncStatus === 'queued' || taskProviderSyncStatus === 'syncing'" class="mt-1">Syncing approved tasks to task provider...</p>
            <p v-if="taskProviderSyncStatus === 'synced'" class="mt-1">Task provider sync completed.</p>
            <p v-if="taskProviderSyncStatus === 'failed' && taskProviderSyncError.summary" class="mt-1 text-destructive ">Task provider sync failed: {{ taskProviderSyncError.summary }}</p>
            <p v-if="taskProviderSync.project_url" class="mt-2">
                <a :href="taskProviderSync.project_url" target="_blank" rel="noreferrer" class="font-semibold underline">Open synced provider project</a>
            </p>
        </div>

        <div v-if="(isRulesMode || isTasksMode) && status === 'failed'" class="mt-4 rounded border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive   ">
            <p class="font-semibold">Build task generation failed.</p>
            <p v-if="buildError.summary" class="mt-1 whitespace-pre-wrap">{{ buildError.summary }}</p>
            <details v-if="buildError.details" class="mt-2">
                <summary class="cursor-pointer font-medium">Show technical details</summary>
                <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap rounded border border-destructive/30 bg-card p-2 text-[11px] text-destructive">{{ buildError.details }}</pre>
            </details>
            <p class="mt-1">You can retry by clicking Generate Build Tasks.</p>
        </div>

        <div v-if="isExecutionMode" class="mt-4 space-y-3">
            <div class="rounded-lg border border-border bg-muted/20 p-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-base font-semibold text-foreground">Build Execution</p>
                        <p class="mt-1 text-xs capitalize text-muted-foreground">{{ statusLabel }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded border border-warning/30 px-3 py-1.5 text-xs font-semibold text-warning hover:bg-warning/10 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canPause"
                            @click="emit('pause')"
                        >
                            {{ actions.pauseBuild ? 'Pausing...' : 'Pause Build' }}
                        </button>
                        <button
                            type="button"
                            class="rounded border border-primary/30 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canResume"
                            @click="emit('resume')"
                        >
                            {{ actions.resumeBuild ? 'Resuming...' : 'Resume Build' }}
                        </button>
                        <button
                            type="button"
                            class="rounded border border-destructive/30 px-3 py-1.5 text-xs font-semibold text-destructive hover:bg-destructive/10 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canRetry"
                            @click="emit('retry')"
                        >
                            {{ retryLabel }}
                        </button>
                        <button
                            type="button"
                            class="rounded border border-warning/30 px-3 py-1.5 text-xs font-semibold text-warning hover:bg-warning/10 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canRerunAll"
                            @click="emit('rerun-all')"
                        >
                            {{ actions.startBuild ? 'Re-running build...' : 'Re-run Build' }}
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ executionSummaryLabel }}</span>
                    <span class="font-semibold text-foreground">{{ executionProgressPercent }}%</span>
                </div>
                <div class="mt-1.5 h-1.5 rounded-full bg-muted">
                    <div
                        class="h-1.5 rounded-full bg-primary transition-all duration-300"
                        :style="{ width: `${executionProgressPercent}%` }"
                    />
                </div>
            </div>

            <div v-if="tasks.length > 0" class="rounded-lg border border-border bg-card/70 p-2">
                <div class="space-y-2">
                    <div
                        v-for="task in tasks"
                        :key="`execution-task-${task.id}`"
                        class="rounded border border-border bg-muted/20"
                    >
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left"
                            @click="toggleExecutionTask(task.id)"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                        :class="taskStatusBadgeClass(task.status)"
                                    >
                                        {{ taskStatusLabel(task.status) }}
                                    </span>
                                    <span class="truncate text-sm font-medium text-foreground">#{{ task.sequence }} {{ task.title }}</span>
                                </div>
                                <span class="text-[11px] text-muted-foreground">{{ taskAttemptLabel(task) }}</span>
                            </div>
                        </button>

                        <div
                            v-if="selectedExecutionTaskId === task.id"
                            class="border-t border-border px-3 py-2 text-xs"
                        >
                            <p v-if="task.description" class="text-muted-foreground">{{ task.description }}</p>
                            <p v-if="task.last_error" class="mt-1 text-destructive">{{ task.last_error }}</p>
                            <p v-if="activeTask?.id === task.id && activeRun" class="mt-1 text-muted-foreground">
                                Run #{{ activeRun.id }} · {{ activeRunDisplayStatus }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isExecutionMode && flags.approval_required" class="mt-4 rounded border border-warning/30 bg-warning/10 p-3 text-xs text-warning">
            <p class="font-semibold">Approval likely required in active run output.</p>
            <p v-if="flags.approval_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.approval_excerpt }}</p>
        </div>

        <div v-if="isExecutionMode && flags.permission_required" class="mt-4 rounded border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive">
            <p class="font-semibold">Write permission blocker detected.</p>
            <p v-if="flags.permission_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.permission_excerpt }}</p>
        </div>

        <div v-if="isExecutionMode && flags.clarification_required" class="mt-4 rounded border border-primary/30 bg-primary/5 p-3 text-xs text-primary">
            <p class="font-semibold">Clarification requested by the AI.</p>
            <p v-if="flags.clarification_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.clarification_excerpt }}</p>
            <p class="mt-1">Submit clarification, then click Resume Build.</p>
            <textarea
                v-model="clarification"
                rows="3"
                class="mt-2 w-full rounded border border-primary/40 bg-input-background text-sm text-foreground"
                placeholder="Reply to the clarification request..."
            />
            <div class="mt-2 flex justify-end">
                <button
                    type="button"
                    class="rounded bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="disabled || actions.clarifyBuild || clarification.trim() === ''"
                    @click="submitClarification"
                >
                    {{ actions.clarifyBuild ? 'Submitting...' : 'Submit Clarification' }}
                </button>
            </div>
        </div>

        <div v-if="isExecutionMode && flags.rate_limit_detected" class="mt-4 rounded border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive">
            <p class="font-semibold">Rate limit detected.</p>
            <p v-if="flags.rate_limit_reset_at" class="mt-1">Reset at: {{ flags.rate_limit_reset_at }}</p>
            <p v-if="flags.rate_limit_excerpt" class="mt-1 whitespace-pre-wrap">{{ flags.rate_limit_excerpt }}</p>
        </div>

        <div v-if="isExecutionMode && (hasActiveRunPresentation || activeRunEventsLoading || activeRunEventsError)" class="mt-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Active Run AI Log</p>
                <span
                    v-if="activeRunEventsLoading"
                    class="rounded border border-primary/30 bg-primary/5 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary   "
                >
                    Syncing full run output...
                </span>
                <span
                    v-else-if="activeRunEventsBootstrapComplete"
                    class="rounded border border-success/30 bg-success/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-success   "
                >
                    Unified
                </span>
            </div>
            <p v-if="activeRunEventsError" class="mt-2 rounded border border-destructive/30 bg-destructive/10 px-2 py-1 text-[11px] text-destructive">
                {{ activeRunEventsError }}
            </p>
            <div v-if="activeRunIssues.length > 0" class="mt-2 space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-destructive">Issues</p>
                <div
                    v-for="issue in activeRunIssues"
                    :key="`run-issue-${issue.key}`"
                    class="rounded border border-destructive/30 bg-destructive/10 p-2 text-xs text-destructive"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold">{{ issue.title }}</p>
                        <span class="rounded-full border border-destructive/40 bg-destructive/20 px-1.5 py-0.5 text-[10px] font-semibold">
                            x{{ issue.count }}
                        </span>
                    </div>
                    <p class="mt-1">{{ issue.detail }}</p>
                    <p v-if="issue.suggestedAction" class="mt-1 text-[11px]">{{ issue.suggestedAction }}</p>
                </div>
            </div>
            <div v-if="activeRunHeartbeatSummary" class="mt-2 rounded border border-border bg-muted/20 px-2 py-1.5 text-[11px] text-muted-foreground">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p>Heartbeat events: {{ activeRunHeartbeatSummary.count }}<span v-if="activeRunHeartbeatSummary.latestTimestampLabel"> · latest {{ activeRunHeartbeatSummary.latestTimestampLabel }}</span></p>
                    <button
                        type="button"
                        class="rounded border border-input px-2 py-0.5 text-[10px] font-semibold text-foreground hover:bg-muted"
                        @click="showHeartbeatEntries = !showHeartbeatEntries"
                    >
                        {{ showHeartbeatEntries ? 'Hide heartbeats' : 'Show heartbeats' }}
                    </button>
                </div>
            </div>
            <div
                v-if="hasActiveRunPresentation"
                ref="activeRunLogContainer"
                class="mt-2 max-h-72 overflow-auto rounded border border-border bg-gray-950 p-3 text-xs text-gray-100 "
            >
                <div class="space-y-2 font-mono">
                    <div
                        v-for="entry in activeRunTimelineEntries"
                        :key="entry.key"
                        class="rounded border p-2"
                        :class="timelineEntryKindClass(entry)"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2 text-[10px] uppercase tracking-wide text-gray-300">
                            <div class="flex items-center gap-2">
                                <span>Seq {{ entry.sequence }}</span>
                                <span>{{ timelineEntryHeaderLabel(entry) }}</span>
                                <span v-if="entry.repeatCount > 1" class="rounded border border-border px-1.5 py-0.5">x{{ entry.repeatCount }}</span>
                            </div>
                            <span v-if="entry.timestampLabel">{{ entry.timestampLabel }}</span>
                        </div>

                        <div v-if="entry.kind === 'command'" class="mt-2 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded border px-1.5 py-0.5 text-[10px] font-semibold" :class="commandStatusClass(entry)">
                                    {{ entry.command?.status || 'running' }}
                                </span>
                                <span v-if="entry.command?.exitCode !== null" class="text-[11px] text-gray-300">exit {{ entry.command.exitCode }}</span>
                                <span v-if="entry.command?.durationMs !== null" class="text-[11px] text-gray-300">{{ entry.command.durationMs }}ms</span>
                            </div>
                            <pre class="whitespace-pre-wrap break-words text-[11px] leading-relaxed text-gray-100">{{ entry.command?.command || 'Command unavailable' }}</pre>
                            <div v-if="entry.command?.output" class="rounded border border-border/60 bg-black/30 p-2">
                                <pre class="whitespace-pre-wrap break-words text-[11px] leading-relaxed text-gray-300">{{ commandOutputPreview(entry) }}</pre>
                                <button
                                    v-if="isCommandOutputExpandable(entry)"
                                    type="button"
                                    class="mt-1 rounded border border-input px-2 py-0.5 text-[10px] font-semibold text-gray-100 hover:bg-muted/40"
                                    @click="toggleCommandOutputExpanded(entry)"
                                >
                                    {{ isCommandOutputExpanded(entry) ? 'Collapse output' : 'Expand output' }}
                                </button>
                            </div>
                        </div>

                        <div v-else class="mt-2">
                            <div
                                v-if="timelineEntryDisplayFormat(entry) === 'markdown'"
                                class="prose prose-invert prose-pre:my-2 prose-pre:max-w-full prose-code:text-inherit max-w-none text-[11px] leading-relaxed"
                            >
                                <MarkdownRenderer :markdown="timelineEntryPreviewText(entry)" :normalize="false" />
                            </div>
                            <pre v-else class="whitespace-pre-wrap break-words text-[11px] leading-relaxed">{{ timelineEntryPreviewText(entry) }}</pre>
                            <button
                                v-if="isTimelineEntryExpandable(entry)"
                                type="button"
                                class="mt-1 rounded border border-input px-2 py-0.5 text-[10px] font-semibold text-gray-100 hover:bg-muted/40"
                                @click="toggleTimelineEntryExpanded(entry)"
                            >
                                {{ isTimelineEntryExpanded(entry) ? 'Collapse' : 'Expand' }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="showHeartbeatEntries"
                        v-for="heartbeatEntry in activeRunHeartbeatEntries"
                        :key="`${heartbeatEntry.key}-heartbeat`"
                        class="rounded border border-border/70 bg-black/20 p-2"
                    >
                        <div class="flex items-center justify-between gap-2 text-[10px] uppercase tracking-wide text-gray-400">
                            <span>Seq {{ heartbeatEntry.sequence }} · Heartbeat</span>
                            <span v-if="heartbeatEntry.timestampLabel">{{ heartbeatEntry.timestampLabel }}</span>
                        </div>
                        <pre class="mt-1 whitespace-pre-wrap break-words text-[11px] leading-relaxed text-gray-400">{{ heartbeatEntry.text }}</pre>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isExecutionMode && !flags.clarification_required" class="mt-4 rounded border border-border p-3 ">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Clarification</p>
            <textarea
                v-model="clarification"
                rows="3"
                class="mt-2 w-full rounded border border-input bg-input-background text-sm text-foreground"
                placeholder="Add clarification for the active task..."
            />
            <div class="mt-2 flex justify-end">
                <button
                    type="button"
                    class="rounded bg-secondary px-3 py-2 text-xs font-semibold text-secondary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="disabled || actions.clarifyBuild || clarification.trim() === ''"
                    @click="submitClarification"
                >
                    {{ actions.clarifyBuild ? 'Submitting...' : 'Submit Clarification' }}
                </button>
            </div>
        </div>

        <div v-if="isTasksMode" class="mt-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Tasks</p>
                <button
                    v-if="isTasksMode"
                    type="button"
                    class="rounded border border-primary/30 px-2 py-1 text-[11px] font-semibold text-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canManageTaskList || actions.createBuildTask"
                    @click="openCreateTaskForm"
                >
                    {{ actions.createBuildTask ? 'Adding...' : 'Add Task' }}
                </button>
            </div>
            <div
                v-if="isTasksMode && showCreateTaskForm"
                class="mt-2 rounded border border-border bg-muted/30 p-3  "
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground ">New Task</p>
                <input
                    v-model="createTaskDraft.title"
                    type="text"
                    class="mt-2 w-full rounded border border-input bg-input-background px-2 py-1 text-xs text-foreground   "
                    placeholder="Task title"
                />
                <textarea
                    v-model="createTaskDraft.description"
                    rows="2"
                    class="mt-2 w-full rounded border border-input bg-input-background px-2 py-1 text-xs text-foreground   "
                    placeholder="Task description (optional)"
                />
                <textarea
                    v-model="createTaskDraft.instructions_markdown"
                    rows="4"
                    class="mt-2 w-full rounded border border-input bg-input-background px-2 py-1 text-xs text-foreground   "
                    placeholder="Task instructions in markdown (optional)"
                />
                <div class="mt-2 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded border border-input px-2 py-1 text-[11px] font-semibold text-foreground hover:bg-muted"
                        :disabled="actions.createBuildTask"
                        @click="cancelCreateTask"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded bg-primary px-2 py-1 text-[11px] font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="actions.createBuildTask || createTaskDraft.title.trim() === ''"
                        @click="submitCreateTask"
                    >
                        {{ actions.createBuildTask ? 'Adding...' : 'Add Task' }}
                    </button>
                </div>
            </div>
            <div v-if="tasks.length === 0" class="mt-2 rounded border border-dashed border-input px-3 py-2 text-xs text-muted-foreground  ">
                No build tasks generated yet.
            </div>
            <div v-else class="mt-2 space-y-2">
                <div v-if="actions.reorderBuildTasks" class="rounded border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary">
                    Saving task order...
                </div>
                <p v-if="canReorderTasks" class="text-[11px] text-muted-foreground">
                    Drag tasks by the grip handle to reorder execution.
                </p>
                <draggable
                    v-model="orderedTasks"
                    item-key="id"
                    handle=".task-drag-handle"
                    :disabled="!canReorderTasks"
                    :animation="180"
                    class="space-y-2"
                    @end="handleTaskDragEnd"
                >
                    <template #item="{ element: task }">
                        <div class="rounded border border-border bg-card/70">
                            <button
                                type="button"
                                class="w-full px-3 py-2 text-left"
                                @click="toggleTaskDetails(task.id)"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span
                                            class="task-drag-handle inline-flex h-6 w-6 items-center justify-center rounded border border-input text-muted-foreground"
                                            :class="canReorderTasks ? 'cursor-grab' : 'cursor-not-allowed opacity-50'"
                                            title="Drag to reorder"
                                            aria-hidden="true"
                                            @mousedown.stop
                                            @click.stop
                                        >
                                            <GripVertical class="h-3.5 w-3.5" />
                                        </span>
                                        <span
                                            class="inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                            :class="taskStatusBadgeClass(task.status)"
                                        >
                                            {{ taskStatusLabel(task.status) }}
                                        </span>
                                        <span class="truncate text-sm font-medium text-foreground">#{{ task.sequence }} {{ task.title }}</span>
                                    </div>
                                    <span class="text-[11px] text-muted-foreground">{{ taskAttemptLabel(task) }}</span>
                                </div>
                            </button>

                            <div
                                v-if="selectedTaskDetailsId === task.id || editingTaskId === task.id || regeneratingTaskId === task.id"
                                class="border-t border-border px-3 py-2"
                            >
                                <p v-if="task.description" class="text-xs text-muted-foreground">{{ task.description }}</p>
                                <p v-if="task.last_error" class="mt-1 text-xs text-destructive">{{ task.last_error }}</p>
                                <p v-if="task.metadata_json?.regeneration?.status === 'queued'" class="mt-1 text-xs text-primary">Regeneration queued...</p>
                                <p v-if="task.metadata_json?.regeneration?.status === 'failed'" class="mt-1 text-xs text-destructive">
                                    Regeneration failed: {{ task.metadata_json?.regeneration?.error || 'Unknown error' }}
                                </p>

                                <div class="mt-2 flex flex-wrap gap-1">
                                    <button
                                        type="button"
                                        class="rounded border border-primary/30 px-2 py-1 text-[11px] font-semibold text-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!canManageTaskList || isTaskUpdating(task.id)"
                                        @click="startTaskEdit(task)"
                                    >
                                        {{ isTaskUpdating(task.id) ? 'Saving...' : 'Edit' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded border border-destructive/30 px-2 py-1 text-[11px] font-semibold text-destructive hover:bg-destructive/10 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!canManageTaskList || isTaskDeleting(task.id)"
                                        @click="confirmTaskDelete(task)"
                                    >
                                        {{ isTaskDeleting(task.id) ? 'Deleting...' : 'Delete' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded border border-primary/30 px-2 py-1 text-[11px] font-semibold text-primary hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="!canManageTaskList || isTaskRegenerating(task.id)"
                                        @click="startTaskRegeneration(task)"
                                    >
                                        {{ isTaskRegenerating(task.id) ? 'Queueing...' : 'Regenerate' }}
                                    </button>
                                </div>

                                <div v-if="editingTaskId === task.id" class="mt-3 rounded border border-border bg-muted/30 p-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground ">Edit Task #{{ task.sequence }}</p>
                                    <input
                                        v-model="editTaskDraft.title"
                                        type="text"
                                        class="mt-2 w-full rounded border border-input bg-input-background px-2 py-1 text-xs text-foreground   "
                                        placeholder="Task title"
                                    />
                                    <textarea
                                        v-model="editTaskDraft.description"
                                        rows="2"
                                        class="mt-2 w-full rounded border border-input bg-input-background px-2 py-1 text-xs text-foreground   "
                                        placeholder="Task description"
                                    />
                                    <textarea
                                        v-model="editTaskDraft.instructions_markdown"
                                        rows="4"
                                        class="mt-2 w-full rounded border border-input bg-input-background px-2 py-1 text-xs text-foreground   "
                                        placeholder="Task instructions in markdown"
                                    />
                                    <div class="mt-2 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded border border-input px-2 py-1 text-[11px] font-semibold text-foreground hover:bg-muted"
                                            :disabled="isTaskUpdating(task.id)"
                                            @click="cancelTaskEdit"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded bg-primary px-2 py-1 text-[11px] font-semibold text-white hover:bg-primary/50 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="isTaskUpdating(task.id) || editTaskDraft.title.trim() === ''"
                                            @click="submitTaskEdit(task)"
                                        >
                                            {{ isTaskUpdating(task.id) ? 'Saving...' : 'Save Changes' }}
                                        </button>
                                    </div>
                                </div>

                                <div v-if="regeneratingTaskId === task.id" class="mt-3 rounded border border-primary/30 bg-primary/5 p-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-primary ">Regenerate Task #{{ task.sequence }} With Amend Notes</p>
                                    <textarea
                                        v-model="regenerateAmendNotes"
                                        rows="3"
                                        class="mt-2 w-full rounded border border-primary/30 bg-input-background px-2 py-1 text-xs text-foreground   "
                                        placeholder="Describe exactly what should change for this task..."
                                    />
                                    <textarea
                                        v-model="regenerateAdditionalContext"
                                        rows="2"
                                        class="mt-2 w-full rounded border border-primary/30 bg-input-background px-2 py-1 text-xs text-foreground   "
                                        placeholder="Optional additional context for the AI runner (constraints, references, sequencing)."
                                    />
                                    <div class="mt-2 flex justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded border border-input px-2 py-1 text-[11px] font-semibold text-foreground hover:bg-muted"
                                            :disabled="isTaskRegenerating(task.id)"
                                            @click="cancelTaskRegeneration"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded bg-primary px-2 py-1 text-[11px] font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="isTaskRegenerating(task.id) || regenerateAmendNotes.trim() === ''"
                                            @click="submitTaskRegeneration(task)"
                                        >
                                            {{ isTaskRegenerating(task.id) ? 'Queueing...' : 'Regenerate Task' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </draggable>
            </div>
        </div>

        <div v-if="isExecutionMode && build.completion_summary" class="mt-4 rounded border border-success/30 bg-success/10 p-3 text-xs text-success   ">
            {{ build.completion_summary }}
        </div>

        <div v-if="Array.isArray(activity) && activity.length > 0" class="mt-4 rounded border border-border bg-muted/30 p-3  ">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground ">AI Activity</p>
            <div class="mt-2 space-y-1">
                <p v-for="item in activity" :key="`${item.sequence}-${item.message}`" class="text-xs text-foreground ">
                    <span class="font-medium text-muted-foreground ">{{ item.at_label || item.at || 'now' }}</span>
                    <span class="ml-1">{{ item.message }}</span>
                </p>
            </div>
        </div>
    </div>
</template>
