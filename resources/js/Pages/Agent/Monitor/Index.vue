<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { formatAgentRunEventEntries } from '@/Support/agentRunEventFormatting';
import { deriveActiveBuildFreshnessView } from './freshness';
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Monitor, Heart, Gauge, Radio, RefreshCw, AlertTriangle, ShieldCheck, HelpCircle, Square } from 'lucide-vue-next';

const runs = ref([]);
const events = ref([]);
const selectedRunId = ref(null);
const scheduler = ref({
    status: 'unknown',
    age_seconds: null,
    last_seen_at: null,
    projection: {
        active_build_activated_at: null,
        active_build_age_seconds: null,
        active_build_is_stale: null,
        stale_after_seconds: null,
    },
});
const loading = ref(true);
const errorMessage = ref('');
const failureCount = ref(0);
const autoFollow = ref(true);
const pollTimer = ref(null);
const isLoadingMonitor = ref(false);
const approvalBusy = ref(false);
const approvalError = ref('');
const approvalModalRunId = ref(null);
const clarificationModalRunId = ref(null);
const rateLimitBusy = ref(false);
const rateLimitError = ref('');
const rateLimitModalRunId = ref(null);
const lessonConfirmBusy = ref(false);
const lessonDismissed = ref(false);
const retryModalRunId = ref(null);
const retryBusy = ref(false);
const retryError = ref('');

const echoConnected = ref(false);
const BASE_POLL_MS = 2000;
const ECHO_POLL_MS = 15000;
const INACTIVE_POLL_MS = 10000;
const HIDDEN_POLL_MS = 15000;
const BACKOFF = [2000, 4000, 8000, 15000];
const ACTIVE_RUN_STATUSES = ['queued', 'starting', 'running', 'stopping'];
const OUTPUT_EVENT_TYPES = ['stdout', 'stderr'];
const CODEX_DEFAULT_MODEL = 'gpt-5.3-codex';

const reasoningStepConfig = {
    situation: { label: 'SITUATION', color: 'bg-blue-100 text-blue-800' },
    task: { label: 'TASK', color: 'bg-green-100 text-green-800' },
    action: { label: 'ACTION', color: 'bg-amber-100 text-amber-800' },
    result: { label: 'RESULT', color: 'bg-purple-100 text-purple-800' },
};

const getReasoningStepBadge = (step) => {
    return reasoningStepConfig[step] || null;
};

const consecutiveFailureWarning = computed(() => failureCount.value >= 3);

const activeRuns = computed(() => runs.value.filter((run) => ACTIVE_RUN_STATUSES.includes(run.status)));
const selectedRun = computed(() => runs.value.find((run) => run.id === selectedRunId.value) ?? null);
const approvalModalRun = computed(() => runs.value.find((run) => run.id === approvalModalRunId.value) ?? null);
const clarificationModalRun = computed(() => runs.value.find((run) => run.id === clarificationModalRunId.value) ?? null);
const rateLimitModalRun = computed(() => runs.value.find((run) => run.id === rateLimitModalRunId.value) ?? null);

// Retry chain visualization
const retryChain = computed(() => {
    if (!selectedRun.value) return [];

    const chain = [];
    let current = selectedRun.value;

    // Build chain going backwards
    while (current.metadata_json?.retry_of_run_id) {
        const parentId = current.metadata_json.retry_of_run_id;
        const parent = runs.value.find(r => r.id === parentId);
        if (parent) {
            chain.unshift(parent);
            current = parent;
        } else {
            chain.unshift({ id: parentId, status: 'unknown' });
            break;
        }
    }

    chain.push(selectedRun.value);
    return chain;
});

const isRetryRun = computed(() => {
    return !!selectedRun.value?.metadata_json?.retry_of_run_id;
});

// Suggested lesson display
const showSuggestedLesson = computed(() => {
    const hint = selectedRun.value?.metadata_json?.failure_mode_hint;
    return hint?.suggested_lesson && !selectedRun.value?.metadata_json?.suggested_lesson_confirmed;
});

const suggestedLessonText = computed(() => {
    return selectedRun.value?.metadata_json?.failure_mode_hint?.suggested_lesson || '';
});

// Manual retry eligibility
const canRetryRun = computed(() => {
    return selectedRun.value?.status === 'failed' && selectedRun.value?.metadata_json?.reasoning_summary;
});

const selectedRunNoOutputSeconds = computed(() => {
    const run = selectedRun.value;

    if (!run || !ACTIVE_RUN_STATUSES.includes(run.status)) {
        return null;
    }

    const latestOutputEvent = [...events.value].reverse().find((event) => OUTPUT_EVENT_TYPES.includes(event.event_type));
    const baselineTimestamp = latestOutputEvent?.created_at ?? run.started_at ?? run.created_at;

    if (!baselineTimestamp) {
        return null;
    }

    const parsed = new Date(baselineTimestamp).getTime();
    if (!Number.isFinite(parsed)) {
        return null;
    }

    return Math.max(0, Math.floor((Date.now() - parsed) / 1000));
});
const selectedRunLikelySilent = computed(() => (selectedRunNoOutputSeconds.value ?? 0) >= 20);
const formattedEvents = computed(() => formatAgentRunEventEntries(events.value));

const approvalStateForRun = (run) => {
    if (!run) {
        return null;
    }

    const metadata = run.metadata_json ?? {};
    if (metadata.permission_blocker_detected === true) {
        return {
            kind: 'permission_blocker',
            excerpt: String(metadata.permission_blocker_excerpt ?? metadata.approval_excerpt ?? 'Run reported missing write permissions and could not proceed.'),
        };
    }

    if (ACTIVE_RUN_STATUSES.includes(run.status) && metadata.approval_required === true) {
        return {
            kind: 'approval_required',
            excerpt: String(metadata.approval_excerpt ?? 'Approval is required for this run.'),
        };
    }

    return null;
};

const runHasApproval = (run) => approvalStateForRun(run) !== null;
const approvalModalState = computed(() => approvalStateForRun(approvalModalRun.value));

const clarificationStateForRun = (run) => {
    if (!run) {
        return null;
    }

    const metadata = run.metadata_json ?? {};
    if (metadata.clarification_required !== true) {
        return null;
    }

    return {
        excerpt: String(metadata.clarification_excerpt ?? 'The run is asking a question and needs clarification.'),
    };
};

const runHasClarification = (run) => clarificationStateForRun(run) !== null;
const clarificationModalState = computed(() => clarificationStateForRun(clarificationModalRun.value));

const rateLimitStateForRun = (run) => {
    if (!run) {
        return null;
    }

    const metadata = run.metadata_json ?? {};
    if (metadata.rate_limit_detected !== true) {
        return null;
    }

    const runStatus = String(run.status ?? '').toLowerCase();

    const holdUntilRaw = metadata.rate_limit_hold_until ?? metadata.rate_limit_reset_at ?? null;
    let holdUntil = null;
    let holdActive = false;

    if (typeof holdUntilRaw === 'string' && holdUntilRaw.trim() !== '') {
        holdUntil = holdUntilRaw;
        const parsed = new Date(holdUntilRaw).getTime();
        holdActive = Number.isFinite(parsed) && parsed > Date.now();
    }

    // Historical metadata may include false positives on succeeded runs; only surface when
    // there's an active hold or the run did not succeed.
    if (runStatus === 'succeeded' && !holdActive) {
        return null;
    }

    return {
        excerpt: String(metadata.rate_limit_excerpt ?? 'Upstream usage/rate limit detected.'),
        holdUntil,
        holdActive,
    };
};

const runHasRateLimit = (run) => rateLimitStateForRun(run) !== null;
const rateLimitModalState = computed(() => rateLimitStateForRun(rateLimitModalRun.value));

const openApprovalModal = (run) => {
    approvalError.value = '';
    approvalModalRunId.value = run.id;

    if (selectedRunId.value !== run.id) {
        selectRun(run.id);
    }
};

const closeApprovalModal = () => {
    approvalModalRunId.value = null;
    approvalError.value = '';
};

const openClarificationModal = (run) => {
    clarificationModalRunId.value = run.id;

    if (selectedRunId.value !== run.id) {
        selectRun(run.id);
    }
};

const closeClarificationModal = () => {
    clarificationModalRunId.value = null;
};

const openRateLimitModal = (run) => {
    rateLimitError.value = '';
    rateLimitModalRunId.value = run.id;

    if (selectedRunId.value !== run.id) {
        selectRun(run.id);
    }
};

const closeRateLimitModal = () => {
    rateLimitModalRunId.value = null;
    rateLimitError.value = '';
};

const queueLag = computed(() => {
    const queued = runs.value.filter((run) => run.status === 'queued');
    if (queued.length === 0) {
        return { count: 0, oldestSeconds: 0, warning: false };
    }

    const oldest = queued
        .map((run) => new Date(run.created_at).getTime())
        .reduce((min, current) => Math.min(min, current), Number.MAX_SAFE_INTEGER);

    const oldestSeconds = Math.floor((Date.now() - oldest) / 1000);

    return {
        count: queued.length,
        oldestSeconds,
        warning: oldestSeconds > 60 || queued.length > 10,
    };
});

const projectionFreshness = computed(() => deriveActiveBuildFreshnessView({
    active_build_age_seconds: scheduler.value?.projection?.active_build_age_seconds ?? null,
    stale_after_seconds: scheduler.value?.projection?.stale_after_seconds ?? null,
    active_build_is_stale: scheduler.value?.projection?.active_build_is_stale ?? null,
}));

const pollInterval = () => {
    if (document.hidden) {
        return HIDDEN_POLL_MS;
    }

    if (!document.hasFocus()) {
        return INACTIVE_POLL_MS;
    }

    return echoConnected.value ? ECHO_POLL_MS : BASE_POLL_MS;
};

const scheduleNext = () => {
    clearTimeout(pollTimer.value);

    const base = pollInterval();
    const extra = failureCount.value > 0 ? BACKOFF[Math.min(failureCount.value - 1, BACKOFF.length - 1)] : 0;

    pollTimer.value = setTimeout(loadMonitor, Math.max(base, extra));
};

const loadEvents = async () => {
    if (!selectedRunId.value) {
        events.value = [];
        return;
    }

    const afterSequence = events.value.length > 0 ? events.value[events.value.length - 1].sequence : 0;

    const { data } = await axios.get(`/agent/api/v1/runs/${selectedRunId.value}/events`, {
        params: {
            after_sequence: afterSequence,
            limit: 200,
        },
    });

    const incoming = data.data || [];

    if (incoming.length === 0) {
        return;
    }

    const deduped = new Map();
    [...events.value, ...incoming].forEach((event) => {
        deduped.set(event.id, event);
    });

    events.value = Array.from(deduped.values())
        .sort((a, b) => a.sequence - b.sequence)
        .slice(-1000);

    if (autoFollow.value) {
        requestAnimationFrame(() => {
            const container = document.getElementById('event-tail');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    }
};

const loadMonitor = async (force = false) => {
    if (isLoadingMonitor.value && !force) {
        return;
    }

    isLoadingMonitor.value = true;

    try {
        const [runsResponse, schedulerResponse] = await Promise.all([
            axios.get('/agent/api/v1/runs', { params: { hours: 24, limit: 50 } }),
            axios.get('/agent/api/v1/health/scheduler'),
        ]);

        runs.value = runsResponse.data.data || [];
        scheduler.value = schedulerResponse.data.data || scheduler.value;

        if (!selectedRunId.value && runs.value.length > 0) {
            selectedRunId.value = runs.value[0].id;
        }

        if (selectedRunId.value && !runs.value.find((run) => run.id === selectedRunId.value)) {
            selectedRunId.value = runs.value[0]?.id ?? null;
            events.value = [];
        }

        if (approvalModalRunId.value) {
            const modalRun = runs.value.find((run) => run.id === approvalModalRunId.value) ?? null;
            if (!modalRun || !runHasApproval(modalRun)) {
                closeApprovalModal();
            }
        }

        if (clarificationModalRunId.value) {
            const modalRun = runs.value.find((run) => run.id === clarificationModalRunId.value) ?? null;
            if (!modalRun || !runHasClarification(modalRun)) {
                closeClarificationModal();
            }
        }

        if (rateLimitModalRunId.value) {
            const modalRun = runs.value.find((run) => run.id === rateLimitModalRunId.value) ?? null;
            if (!modalRun || !runHasRateLimit(modalRun)) {
                closeRateLimitModal();
            }
        }

        await loadEvents();

        failureCount.value = 0;
        errorMessage.value = '';
    } catch (error) {
        failureCount.value += 1;
        errorMessage.value = error?.response?.data?.error?.message ?? 'Monitor polling failed.';
    } finally {
        isLoadingMonitor.value = false;
        loading.value = false;
        scheduleNext();
    }
};

const stopRun = async (runId) => {
    await axios.post(`/agent/api/v1/runs/${runId}/stop`);
    await loadMonitor(true);
};

const pollUntilTerminal = async (runId, attempts = 12) => {
    for (let i = 0; i < attempts; i += 1) {
        const { data } = await axios.get(`/agent/api/v1/runs/${runId}`);
        const status = data?.data?.status ?? null;

        if (['succeeded', 'failed', 'killed', 'timed_out', 'skipped'].includes(status)) {
            return;
        }

        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
};

const buildNonInteractiveTemplate = (job) => {
    if (job.runner_type === 'codex') {
        return `/opt/homebrew/bin/codex -m ${CODEX_DEFAULT_MODEL} --dangerously-bypass-approvals-and-sandbox --search exec --json {{task_markdown_path}}`;
    }

    if (job.runner_type === 'claude') {
        return '/Users/garethdaine/.local/bin/claude --dangerously-skip-permissions --verbose -p --output-format stream-json --include-partial-messages {{task_markdown_path}}';
    }

    if (job.runner_type === 'custom') {
        const current = String(job.command_template ?? '');

        if (current.includes('/opt/homebrew/bin/codex') || /\bcodex\b/.test(current)) {
            return `/opt/homebrew/bin/codex -m ${CODEX_DEFAULT_MODEL} --dangerously-bypass-approvals-and-sandbox --search exec --json {{task_markdown_path}}`;
        }

        if (current.includes('/Users/garethdaine/.local/bin/claude') || /\bclaude\b/.test(current)) {
            return '/Users/garethdaine/.local/bin/claude --dangerously-skip-permissions --verbose -p --output-format stream-json --include-partial-messages {{task_markdown_path}}';
        }

        throw new Error('Custom runner approval cannot be auto-applied. Update this job command template to a non-interactive mode first.');
    }

    return job.command_template ?? '';
};

const approveAndRerun = async () => {
    const run = approvalModalRun.value ?? selectedRun.value;

    if (!run) {
        return;
    }

    approvalBusy.value = true;
    approvalError.value = '';

    try {
        const { data } = await axios.get(`/agent/api/v1/jobs/${run.agent_job_id}`, {
            params: { include_task_content: 1 },
        });
        const job = data?.data;

        if (!job) {
            throw new Error('Job details could not be loaded.');
        }

        const updatePayload = {
            name: job.name,
            description: job.description ?? '',
            cron_expression: job.cron_expression,
            timezone: job.timezone,
            is_enabled: job.is_enabled,
            max_runtime_seconds: job.max_runtime_seconds,
            cooldown_seconds: job.cooldown_seconds,
            runner_type: job.runner_type,
            command_template: buildNonInteractiveTemplate(job),
            working_directory: job.working_directory,
            env_json: job.env_json ?? {},
        };

        if ((job.task_markdown_content ?? '').trim() !== '') {
            updatePayload.task_markdown_content = job.task_markdown_content;
        } else {
            updatePayload.task_markdown_path = job.task_markdown_path;
        }

        await axios.put(`/agent/api/v1/jobs/${run.agent_job_id}`, updatePayload);

        if (['queued', 'starting', 'running', 'stopping'].includes(run.status)) {
            await axios.post(`/agent/api/v1/runs/${run.id}/stop`);
            await pollUntilTerminal(run.id);
        }

        await axios.post(`/agent/api/v1/jobs/${run.agent_job_id}/run-now`);
        await loadMonitor(true);
        closeApprovalModal();
    } catch (error) {
        approvalError.value = error?.response?.data?.error?.message ?? error?.message ?? 'Approval action failed.';
    } finally {
        approvalBusy.value = false;
    }
};

const denyApprovalRun = async () => {
    const run = approvalModalRun.value;
    if (!run) {
        return;
    }

    approvalBusy.value = true;
    approvalError.value = '';

    try {
        await stopRun(run.id);
        closeApprovalModal();
    } catch (error) {
        approvalError.value = error?.response?.data?.error?.message ?? error?.message ?? 'Could not stop run.';
    } finally {
        approvalBusy.value = false;
    }
};

const runAnywayAfterRateLimit = async () => {
    const run = rateLimitModalRun.value;
    if (!run) {
        return;
    }

    rateLimitBusy.value = true;
    rateLimitError.value = '';

    try {
        await axios.post(`/agent/api/v1/jobs/${run.agent_job_id}/run-now`, {}, {
            params: {
                ignore_rate_limit_hold: 1,
            },
        });

        await loadMonitor(true);
        closeRateLimitModal();
    } catch (error) {
        rateLimitError.value = error?.response?.data?.error?.message ?? error?.message ?? 'Could not dispatch run.';
    } finally {
        rateLimitBusy.value = false;
    }
};

const selectRun = async (runId) => {
    selectedRunId.value = runId;
    events.value = [];
    lessonDismissed.value = false;
    await loadEvents();
};

const confirmLesson = async () => {
    if (!selectedRun.value) return;

    lessonConfirmBusy.value = true;
    try {
        await axios.post(`/agent/api/v1/runs/${selectedRun.value.id}/confirm-lesson`);
        await loadMonitor(true);
    } catch (error) {
        console.error('Failed to confirm lesson:', error);
    } finally {
        lessonConfirmBusy.value = false;
    }
};

const dismissLesson = () => {
    lessonDismissed.value = true;
};

const openRetryModal = () => {
    if (!selectedRun.value) return;
    retryError.value = '';
    retryModalRunId.value = selectedRun.value.id;
};

const closeRetryModal = () => {
    retryModalRunId.value = null;
    retryError.value = '';
};

const submitRetry = async () => {
    if (!retryModalRunId.value) return;

    retryBusy.value = true;
    retryError.value = '';

    try {
        await axios.post(`/agent/api/v1/runs/${retryModalRunId.value}/retry`);
        await loadMonitor(true);
        closeRetryModal();
    } catch (error) {
        retryError.value = error?.response?.data?.error?.message ?? error?.message ?? 'Could not dispatch retry.';
    } finally {
        retryBusy.value = false;
    }
};

const handleVisibility = () => {
    scheduleNext();
};

// Echo subscriptions
const currentRunChannel = ref(null);

const subscribeToRunChannel = (runId) => {
    if (currentRunChannel.value) {
        window.Echo?.leave(`run.${currentRunChannel.value}`);
        currentRunChannel.value = null;
    }

    if (!runId || !window.Echo) return;

    currentRunChannel.value = runId;
    window.Echo.private(`run.${runId}`)
        .listen('.events.available', () => {
            loadEvents();
        });
};

watch(selectedRunId, (newId) => {
    subscribeToRunChannel(newId);
});

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('focus', handleVisibility);
    window.addEventListener('blur', handleVisibility);

    const userId = usePage().props.auth?.user?.id;
    if (window.Echo && userId) {
        echoConnected.value = true;

        window.Echo.private(`user.${userId}`)
            .listen('.run.status_changed', () => {
                loadMonitor(true);
            })
            .listen('.runtime.approval_requested', () => {
                loadMonitor(true);
            });
    }

    loadMonitor();
});

onBeforeUnmount(() => {
    clearTimeout(pollTimer.value);
    document.removeEventListener('visibilitychange', handleVisibility);
    window.removeEventListener('focus', handleVisibility);
    window.removeEventListener('blur', handleVisibility);

    const userId = usePage().props.auth?.user?.id;
    if (window.Echo && userId) {
        window.Echo.leave(`user.${userId}`);
    }
    if (currentRunChannel.value) {
        window.Echo?.leave(`run.${currentRunChannel.value}`);
    }
});
</script>

<template>
    <AppLayout title="Monitor">
        <Head title="Monitor" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Monitor class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Run Monitor</h2>
                        <HelpHint
                            ui-key="monitor.run-states"
                            short-text="Track run states, approvals, and recovery actions for active executions."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('agent.jobs.index')">
                        <Button variant="outline" size="sm">Jobs</Button>
                    </Link>
                    <Button variant="outline" size="sm" @click="autoFollow = !autoFollow">
                        {{ autoFollow ? 'Auto-follow on' : 'Auto-follow off' }}
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                <Card class="h-full">
                    <div class="flex h-full min-h-[136px] flex-col p-6">
                        <div class="flex items-center gap-2.5">
                            <Heart class="h-4 w-4 text-success" />
                            <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Scheduler Health</span>
                        </div>
                        <div :class="[
                            'mt-4 text-[22px] font-bold leading-tight',
                            scheduler.status === 'healthy' ? 'text-success' : '',
                            scheduler.status === 'degraded' ? 'text-warning' : '',
                            scheduler.status === 'down' ? 'text-destructive' : '',
                            scheduler.status === 'unknown' ? 'text-muted-foreground' : '',
                        ]">
                            {{ scheduler.status }}
                        </div>
                        <div class="mt-3 space-y-1.5 text-xs leading-5 text-muted-foreground">
                            <p>Last seen: {{ scheduler.last_seen_at ?? 'never' }}</p>
                            <p>Age: {{ scheduler.age_seconds ?? 'n/a' }}s</p>
                        </div>
                    </div>
                </Card>

                <Card class="h-full">
                    <div class="flex h-full min-h-[136px] flex-col p-6">
                        <div class="flex items-center gap-2.5">
                            <Gauge class="h-4 w-4 text-primary" />
                            <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Queue Lag</span>
                        </div>
                        <div :class="['mt-4 text-[22px] font-bold leading-tight', queueLag.warning ? 'text-warning' : 'text-foreground']">
                            {{ queueLag.count }} queued / oldest {{ queueLag.oldestSeconds }}s
                        </div>
                        <p class="mt-3 text-xs leading-5 text-muted-foreground">Warning threshold: oldest &gt; 60s or queued &gt; 10</p>
                    </div>
                </Card>

                <Card class="h-full">
                    <div class="flex h-full min-h-[136px] flex-col p-6">
                        <div class="flex items-center gap-2.5">
                            <Gauge class="h-4 w-4 text-primary" />
                            <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Active Build Freshness</span>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="text-[22px] font-bold leading-tight text-foreground">
                                {{ projectionFreshness.ageSeconds ?? 'n/a' }}<span v-if="projectionFreshness.ageSeconds !== null" class="ml-0.5 text-base">s</span>
                            </span>
                            <Badge :variant="projectionFreshness.badgeVariant" :title="projectionFreshness.tooltip">
                                {{ projectionFreshness.badgeLabel }}
                            </Badge>
                        </div>
                        <div class="mt-3 space-y-1.5 text-xs leading-5 text-muted-foreground">
                            <p>Activated: {{ scheduler.projection?.active_build_activated_at ?? 'n/a' }}</p>
                            <p :title="projectionFreshness.tooltip">Threshold: {{ scheduler.projection?.stale_after_seconds ?? 'n/a' }}s</p>
                        </div>
                    </div>
                </Card>

                <Card class="h-full">
                    <div class="flex h-full min-h-[136px] flex-col p-6">
                        <div class="flex items-center gap-2.5">
                            <Radio class="h-4 w-4 text-primary" />
                            <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Poll Status</span>
                        </div>
                        <div class="mt-4 text-[22px] font-semibold leading-tight text-foreground">{{ failureCount }} consecutive failures</div>
                        <p class="mt-3 text-xs leading-5 text-muted-foreground">Intervals: active 2s, inactive 10s, hidden 15s with backoff.</p>
                    </div>
                </Card>
            </div>

            <div v-if="consecutiveFailureWarning" class="mt-4 flex items-center gap-2 rounded-md border border-warning/40 bg-warning/10 px-3 py-2 text-sm text-warning">
                <AlertTriangle class="w-4 h-4 shrink-0" />
                <span>Monitor polling is failing repeatedly. Retries continue automatically. {{ errorMessage }}</span>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <div class="px-5 py-4 border-b border-border">
                        <h3 class="text-[13px] font-semibold uppercase tracking-wider text-muted-foreground">Latest Runs (24h, max 50)</h3>
                    </div>
                    <div class="max-h-[30rem] overflow-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="text-xs uppercase tracking-wide">Run</TableHead>
                                    <TableHead class="text-xs uppercase tracking-wide">Status</TableHead>
                                    <TableHead class="text-xs uppercase tracking-wide">Alerts</TableHead>
                                    <TableHead class="text-xs uppercase tracking-wide">Created</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="loading">
                                    <TableCell colspan="5" class="text-center py-6">
                                        <div class="flex items-center justify-center gap-2 text-muted-foreground">
                                            <Skeleton class="h-4 w-4 rounded-full" />
                                            <span class="text-sm">Loading...</span>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow
                                    v-for="run in runs"
                                    :key="run.id"
                                    class="cursor-pointer"
                                    :data-state="selectedRunId === run.id ? 'selected' : undefined"
                                    @click="selectRun(run.id)"
                                >
                                    <TableCell class="text-xs text-foreground">#{{ run.id }} (job {{ run.agent_job_id }})</TableCell>
                                    <TableCell class="text-xs">
                                        <Badge :variant="run.status === 'succeeded' ? 'default' : run.status === 'failed' ? 'destructive' : 'secondary'">
                                            {{ run.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-xs">
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-if="runHasApproval(run)"
                                                class="inline-flex items-center gap-1 rounded-md border border-warning/50 bg-warning/10 px-2 py-1 text-[11px] font-semibold text-warning hover:bg-warning/20 transition-colors"
                                                @click.stop="openApprovalModal(run)"
                                            >
                                                <ShieldCheck class="w-3 h-3" />
                                                {{ approvalStateForRun(run)?.kind === 'permission_blocker' ? 'Permission' : 'Approval' }}
                                            </button>
                                            <button
                                                v-if="runHasClarification(run)"
                                                class="inline-flex items-center gap-1 rounded-md border border-primary/50 bg-primary/10 px-2 py-1 text-[11px] font-semibold text-primary hover:bg-primary/20 transition-colors"
                                                @click.stop="openClarificationModal(run)"
                                            >
                                                <HelpCircle class="w-3 h-3" />
                                                Clarification
                                            </button>
                                            <button
                                                v-if="runHasRateLimit(run)"
                                                class="inline-flex items-center gap-1 rounded-md border border-destructive/50 bg-destructive/10 px-2 py-1 text-[11px] font-semibold text-destructive hover:bg-destructive/20 transition-colors"
                                                @click.stop="openRateLimitModal(run)"
                                            >
                                                <AlertTriangle class="w-3 h-3" />
                                                Rate limit
                                            </button>
                                            <span v-if="!runHasApproval(run) && !runHasClarification(run) && !runHasRateLimit(run)" class="text-muted-foreground">-</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-xs text-muted-foreground">{{ run.created_at }}</TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            v-if="activeRuns.find((item) => item.id === run.id)"
                                            variant="outline"
                                            size="sm"
                                            @click.stop="stopRun(run.id)"
                                        >
                                            <Square class="w-3 h-3" />
                                            Stop
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!loading && runs.length === 0">
                                    <TableCell colspan="5" class="text-center py-6 text-muted-foreground">No runs in range.</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </Card>

                <Card>
                    <div class="px-5 py-4 border-b border-border">
                        <div class="flex items-center justify-between">
                            <h3 class="text-[13px] font-semibold uppercase tracking-wider text-muted-foreground">Event Tail</h3>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="isRetryRun"
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                                >
                                    Retry of #{{ selectedRun.metadata_json.retry_of_run_id }}
                                </span>
                                <Button
                                    v-if="canRetryRun"
                                    variant="outline"
                                    size="sm"
                                    @click="openRetryModal"
                                >
                                    <RefreshCw class="w-3 h-3 mr-1" />
                                    Retry
                                </Button>
                            </div>
                        </div>

                        <!-- Retry Chain -->
                        <div v-if="retryChain.length > 1" class="mt-3 flex items-center gap-2 text-sm">
                            <span class="text-muted-foreground">Retry chain:</span>
                            <template v-for="(run, index) in retryChain" :key="run.id">
                                <button
                                    @click="selectRun(run.id)"
                                    :class="[
                                        'px-2 py-1 rounded transition-colors',
                                        run.id === selectedRun?.id
                                            ? 'bg-primary/20 text-primary font-medium'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                    ]"
                                >
                                    #{{ run.id }}
                                    <span v-if="run.status === 'failed'" class="text-destructive ml-0.5">✗</span>
                                    <span v-else-if="run.status === 'succeeded'" class="text-success ml-0.5">✓</span>
                                </button>
                                <span v-if="index < retryChain.length - 1" class="text-muted-foreground">→</span>
                            </template>
                        </div>
                    </div>
                    <div id="event-tail" class="h-[30rem] overflow-auto bg-[#0c1222] p-4 font-mono text-sm text-foreground">
                        <div v-for="entry in formattedEvents" :key="entry.key" class="mb-2 whitespace-pre-wrap break-words">
                            <div class="flex items-center gap-2 text-[11px] text-muted-foreground">
                                <span
                                    v-if="entry.reasoningStep && getReasoningStepBadge(entry.reasoningStep)"
                                    :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium shrink-0',
                                        getReasoningStepBadge(entry.reasoningStep)?.color
                                    ]"
                                >
                                    {{ getReasoningStepBadge(entry.reasoningStep)?.label }}
                                </span>
                                <span>{{ entry.prefix }}</span>
                            </div>
                            <MarkdownRenderer
                                v-if="entry.format === 'markdown'"
                                :markdown="entry.payload"
                                :normalize="false"
                                class="tail-markdown prose prose-sm mt-1 max-w-none rounded border border-success/20 bg-success/5 px-2 py-1 font-sans text-success dark:prose-invert prose-headings:mb-2 prose-headings:mt-3 prose-p:my-1.5 prose-li:my-0.5 prose-code:rounded prose-code:bg-accent prose-code:px-1 prose-code:py-0.5"
                            />
                            <pre v-else class="mt-0.5 whitespace-pre-wrap break-words" :class="{
                                'text-destructive': entry.tone === 'stderr',
                                'text-primary': entry.tone === 'lifecycle',
                                'text-success': entry.tone === 'structured',
                            }">{{ entry.payload }}</pre>
                        </div>
                        <div
                            v-if="selectedRunLikelySilent && selectedRunNoOutputSeconds !== null"
                            class="mb-2 flex items-center gap-2 rounded-md border border-warning/40 bg-warning/10 px-2 py-1 text-[11px] text-warning"
                        >
                            <AlertTriangle class="w-3 h-3 shrink-0" />
                            <span>Run is active but has not emitted stdout/stderr for {{ selectedRunNoOutputSeconds }}s.</span>
                        </div>
                        <p v-if="events.length === 0" class="text-muted-foreground">No events yet for selected run.</p>
                    </div>

                    <!-- Suggested Lesson Banner -->
                    <div
                        v-if="showSuggestedLesson && !lessonDismissed"
                        class="mx-4 mb-4 p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg"
                    >
                        <div class="flex items-start gap-3">
                            <div class="shrink-0">
                                <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-amber-800 dark:text-amber-300">Suggested Lesson</h3>
                                <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                                    {{ suggestedLessonText }}
                                </p>
                                <div class="mt-3 flex gap-3">
                                    <Button
                                        size="sm"
                                        @click="confirmLesson"
                                        :disabled="lessonConfirmBusy"
                                        class="bg-amber-600 hover:bg-amber-700 text-white"
                                    >
                                        {{ lessonConfirmBusy ? 'Adding...' : 'Add to Lessons' }}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="dismissLesson"
                                        class="text-amber-700 dark:text-amber-400 hover:text-amber-900"
                                    >
                                        Dismiss
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <ConfirmationModal :show="!!retryModalRunId" @close="closeRetryModal">
                <template #title>
                    Retry Run
                </template>

                <template #content>
                    <p class="text-xs text-muted-foreground">
                        Dispatch a targeted retry for run #{{ retryModalRunId }}.
                    </p>
                    <div class="mt-4 rounded-md border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                        <p>The system will generate a corrective reframe prompt based on the STAR reasoning analysis from the failed run.</p>
                    </div>
                    <p v-if="retryError" class="mt-2 text-xs text-destructive">{{ retryError }}</p>
                </template>

                <template #footer>
                    <Button
                        variant="outline"
                        :disabled="retryBusy"
                        @click="closeRetryModal"
                    >
                        Cancel
                    </Button>
                    <Button
                        class="ms-3"
                        :disabled="retryBusy"
                        @click="submitRetry"
                    >
                        <RefreshCw v-if="retryBusy" class="w-4 h-4 animate-spin mr-1" />
                        {{ retryBusy ? 'Dispatching...' : 'Dispatch Retry' }}
                    </Button>
                </template>
            </ConfirmationModal>

            <ConfirmationModal :show="!!approvalModalState && !!approvalModalRun" @close="closeApprovalModal">
                <template #title>
                    {{ approvalModalState?.kind === 'permission_blocker' ? 'Write Permission Blocker' : 'Approval Required' }}
                </template>

                <template #content>
                    <p class="text-xs text-muted-foreground">
                        Run #{{ approvalModalRun?.id }} (job {{ approvalModalRun?.agent_job_id }}) {{
                            approvalModalState?.kind === 'permission_blocker'
                                ? 'reported a write-permission blocker.'
                                : 'needs approval before it can continue.'
                        }}
                    </p>

                    <div class="mt-4 rounded-md border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                        <p class="whitespace-pre-wrap break-words">{{ approvalModalState?.excerpt }}</p>
                    </div>

                    <p class="mt-3 text-xs text-muted-foreground">
                        Apply updates this job to a non-interactive command template (Codex/Claude aware), stops the current run if needed, then re-runs it.
                    </p>
                    <p v-if="approvalError" class="mt-2 text-xs text-destructive">{{ approvalError }}</p>
                </template>

                <template #footer>
                    <Button
                        variant="outline"
                        :disabled="approvalBusy"
                        @click="denyApprovalRun"
                    >
                        Deny (Stop Run)
                    </Button>
                    <Button
                        class="ms-3"
                        :disabled="approvalBusy"
                        @click="approveAndRerun"
                    >
                        <RefreshCw v-if="approvalBusy" class="w-4 h-4 animate-spin" />
                        {{ approvalBusy ? 'Processing…' : 'Apply & Re-run' }}
                    </Button>
                </template>
            </ConfirmationModal>

            <ConfirmationModal :show="!!rateLimitModalState && !!rateLimitModalRun" @close="closeRateLimitModal">
                <template #title>
                    Rate Limit Detected
                </template>

                <template #content>
                    <p class="text-xs text-muted-foreground">
                        Run #{{ rateLimitModalRun?.id }} (job {{ rateLimitModalRun?.agent_job_id }}) hit an upstream usage/rate limit.
                    </p>

                    <div class="mt-4 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                        <p class="whitespace-pre-wrap break-words">{{ rateLimitModalState?.excerpt }}</p>
                    </div>

                    <p v-if="rateLimitModalState?.holdUntil" class="mt-3 text-xs text-muted-foreground">
                        Hold until: <span class="font-semibold text-foreground">{{ rateLimitModalState.holdUntil }}</span>
                        <Badge v-if="rateLimitModalState.holdActive" variant="destructive" class="ml-2">active</Badge>
                    </p>
                    <p v-else class="mt-3 text-xs text-muted-foreground">
                        No reset time was parsed from output. Default hold policy applies.
                    </p>
                    <p v-if="rateLimitError" class="mt-2 text-xs text-destructive">{{ rateLimitError }}</p>
                </template>

                <template #footer>
                    <Button
                        variant="outline"
                        :disabled="rateLimitBusy"
                        @click="closeRateLimitModal"
                    >
                        Wait Until Reset
                    </Button>
                    <Button
                        variant="destructive"
                        class="ms-3"
                        :disabled="rateLimitBusy"
                        @click="runAnywayAfterRateLimit"
                    >
                        <RefreshCw v-if="rateLimitBusy" class="w-4 h-4 animate-spin" />
                        {{ rateLimitBusy ? 'Dispatching…' : 'Run Anyway Now' }}
                    </Button>
                </template>
            </ConfirmationModal>

            <ConfirmationModal :show="!!clarificationModalState && !!clarificationModalRun" @close="closeClarificationModal">
                <template #title>
                    Clarification Requested
                </template>

                <template #content>
                    <p class="text-xs text-muted-foreground">
                        Run #{{ clarificationModalRun?.id }} (job {{ clarificationModalRun?.agent_job_id }}) asked a question and needs clarification.
                    </p>

                    <div class="mt-4 rounded-md border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                        <p class="whitespace-pre-wrap break-words">{{ clarificationModalState?.excerpt }}</p>
                    </div>
                </template>

                <template #footer>
                    <Button variant="outline" @click="closeClarificationModal">
                        Close
                    </Button>
                </template>
            </ConfirmationModal>
        </div>
    </AppLayout>
</template>
