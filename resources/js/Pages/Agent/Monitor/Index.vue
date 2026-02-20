<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const runs = ref([]);
const events = ref([]);
const selectedRunId = ref(null);
const scheduler = ref({ status: 'unknown', age_seconds: null, last_seen_at: null });
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

const BASE_POLL_MS = 2000;
const INACTIVE_POLL_MS = 10000;
const HIDDEN_POLL_MS = 15000;
const BACKOFF = [2000, 4000, 8000, 15000];
const ACTIVE_RUN_STATUSES = ['queued', 'starting', 'running', 'stopping'];
const OUTPUT_EVENT_TYPES = ['stdout', 'stderr'];
const CODEX_DEFAULT_MODEL = 'gpt-5.3-codex';

const consecutiveFailureWarning = computed(() => failureCount.value >= 3);

const activeRuns = computed(() => runs.value.filter((run) => ACTIVE_RUN_STATUSES.includes(run.status)));
const selectedRun = computed(() => runs.value.find((run) => run.id === selectedRunId.value) ?? null);
const approvalModalRun = computed(() => runs.value.find((run) => run.id === approvalModalRunId.value) ?? null);
const clarificationModalRun = computed(() => runs.value.find((run) => run.id === clarificationModalRunId.value) ?? null);
const rateLimitModalRun = computed(() => runs.value.find((run) => run.id === rateLimitModalRunId.value) ?? null);
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

    const holdUntilRaw = metadata.rate_limit_hold_until ?? metadata.rate_limit_reset_at ?? null;
    let holdUntil = null;
    let holdActive = false;

    if (typeof holdUntilRaw === 'string' && holdUntilRaw.trim() !== '') {
        holdUntil = holdUntilRaw;
        const parsed = new Date(holdUntilRaw).getTime();
        holdActive = Number.isFinite(parsed) && parsed > Date.now();
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

const pollInterval = () => {
    if (document.hidden) {
        return HIDDEN_POLL_MS;
    }

    if (!document.hasFocus()) {
        return INACTIVE_POLL_MS;
    }

    return BASE_POLL_MS;
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
        return `/opt/homebrew/bin/codex -m ${CODEX_DEFAULT_MODEL} --dangerously-bypass-approvals-and-sandbox --search exec {{task_markdown_path}}`;
    }

    if (job.runner_type === 'claude') {
        return '/Users/garethdaine/.local/bin/claude --dangerously-skip-permissions -p {{task_markdown_path}}';
    }

    if (job.runner_type === 'custom') {
        const current = String(job.command_template ?? '');

        if (current.includes('/opt/homebrew/bin/codex') || /\bcodex\b/.test(current)) {
            return `/opt/homebrew/bin/codex -m ${CODEX_DEFAULT_MODEL} --dangerously-bypass-approvals-and-sandbox --search exec {{task_markdown_path}}`;
        }

        if (current.includes('/Users/garethdaine/.local/bin/claude') || /\bclaude\b/.test(current)) {
            return '/Users/garethdaine/.local/bin/claude --dangerously-skip-permissions -p {{task_markdown_path}}';
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
    await loadEvents();
};

const handleVisibility = () => {
    scheduleNext();
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('focus', handleVisibility);
    window.addEventListener('blur', handleVisibility);
    loadMonitor();
});

onBeforeUnmount(() => {
    clearTimeout(pollTimer.value);
    document.removeEventListener('visibilitychange', handleVisibility);
    window.removeEventListener('focus', handleVisibility);
    window.removeEventListener('blur', handleVisibility);
});
</script>

<template>
    <AppLayout title="Monitor">
        <Head title="Monitor" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Run Monitor</h2>
                <div class="flex items-center gap-2">
                    <Link :href="route('agent.jobs.index')" class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50">Jobs</Link>
                    <button class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50" @click="autoFollow = !autoFollow">
                        {{ autoFollow ? 'Auto-follow on' : 'Auto-follow off' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Scheduler Health</h3>
                    <p class="mt-2 text-lg font-semibold" :class="{
                        'text-green-600': scheduler.status === 'healthy',
                        'text-yellow-600': scheduler.status === 'degraded',
                        'text-red-600': scheduler.status === 'down',
                        'text-gray-500 dark:text-gray-400': scheduler.status === 'unknown',
                    }">
                        {{ scheduler.status }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Last seen: {{ scheduler.last_seen_at ?? 'never' }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Age: {{ scheduler.age_seconds ?? 'n/a' }}s</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Queue Lag</h3>
                    <p class="mt-2 text-lg font-semibold" :class="queueLag.warning ? 'text-yellow-600' : 'text-gray-900 dark:text-gray-100'">
                        {{ queueLag.count }} queued / oldest {{ queueLag.oldestSeconds }}s
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Warning threshold: oldest &gt; 60s or queued &gt; 10</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Poll Status</h3>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">Consecutive failures: {{ failureCount }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Intervals: active 2s, inactive 10s, hidden 15s with backoff.</p>
                </div>
            </div>

            <p v-if="consecutiveFailureWarning" class="mx-auto mt-4 max-w-7xl rounded-md border border-yellow-400 bg-yellow-50 px-3 py-2 text-sm text-yellow-700 dark:border-yellow-800/70 dark:bg-yellow-950/40 dark:text-yellow-300">
                Monitor polling is failing repeatedly. Retries continue automatically. {{ errorMessage }}
            </p>

            <div class="mx-auto mt-6 grid max-w-7xl grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Latest Runs (24h, max 50)</h3>
                    </div>
                    <div class="max-h-[30rem] overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Run</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Alerts</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Created</th>
                                    <th class="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="loading">
                                    <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Loading...</td>
                                </tr>
                                <tr
                                    v-for="run in runs"
                                    :key="run.id"
                                    class="cursor-pointer"
                                    :class="selectedRunId === run.id ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''"
                                    @click="selectRun(run.id)"
                                >
                                    <td class="px-4 py-2 text-xs text-gray-700 dark:text-gray-200">#{{ run.id }} (job {{ run.agent_job_id }})</td>
                                    <td class="px-4 py-2 text-xs text-gray-700 dark:text-gray-200">{{ run.status }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        <div class="flex items-center gap-1">
                                            <button
                                                v-if="runHasApproval(run)"
                                                class="rounded border border-amber-500 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-100"
                                                @click.stop="openApprovalModal(run)"
                                            >
                                                {{ approvalStateForRun(run)?.kind === 'permission_blocker' ? 'Permission' : 'Approval' }}
                                            </button>
                                            <button
                                                v-if="runHasClarification(run)"
                                                class="rounded border border-sky-500 bg-sky-50 px-2 py-1 text-[11px] font-semibold text-sky-700 hover:bg-sky-100"
                                                @click.stop="openClarificationModal(run)"
                                            >
                                                Clarification
                                            </button>
                                            <button
                                                v-if="runHasRateLimit(run)"
                                                class="rounded border border-red-500 bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700 hover:bg-red-100"
                                                @click.stop="openRateLimitModal(run)"
                                            >
                                                Rate limit
                                            </button>
                                            <span v-if="!runHasApproval(run) && !runHasClarification(run) && !runHasRateLimit(run)" class="text-gray-400">-</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{{ run.created_at }}</td>
                                    <td class="px-4 py-2 text-right text-xs">
                                        <button
                                            v-if="activeRuns.find((item) => item.id === run.id)"
                                            class="rounded border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50"
                                            @click.stop="stopRun(run.id)"
                                        >
                                            Stop
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!loading && runs.length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No runs in range.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Event Tail</h3>
                    </div>
                    <div id="event-tail" class="h-[30rem] overflow-auto bg-gray-950 p-3 font-mono text-xs text-gray-100">
                        <div v-for="event in events" :key="event.id" class="mb-1 whitespace-pre-wrap break-words">
                            <span class="text-gray-400">[{{ event.sequence }} {{ event.event_type }}]</span>
                            <span class="ml-1">{{ event.payload }}</span>
                        </div>
                        <p
                            v-if="selectedRunLikelySilent && selectedRunNoOutputSeconds !== null"
                            class="mb-2 rounded border border-amber-500/40 bg-amber-500/10 px-2 py-1 text-[11px] text-amber-200"
                        >
                            Run is active but has not emitted stdout/stderr for {{ selectedRunNoOutputSeconds }}s.
                        </p>
                        <p v-if="events.length === 0" class="text-gray-400">No events yet for selected run.</p>
                    </div>
                </div>
            </div>

            <div v-if="approvalModalState && approvalModalRun" class="fixed inset-0 z-40 flex items-center justify-center bg-gray-900/50 p-4">
                <div class="w-full max-w-2xl rounded-lg border border-amber-300 bg-white p-5 shadow-xl dark:border-amber-900/70 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-amber-800 dark:text-amber-300">
                                {{ approvalModalState?.kind === 'permission_blocker' ? 'Write Permission Blocker' : 'Approval Required' }}
                            </h3>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                Run #{{ approvalModalRun.id }} (job {{ approvalModalRun.agent_job_id }}) {{
                                    approvalModalState?.kind === 'permission_blocker'
                                        ? 'reported a write-permission blocker.'
                                        : 'needs approval before it can continue.'
                                }}
                            </p>
                        </div>
                        <button class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50" @click="closeApprovalModal">Close</button>
                    </div>

                    <div class="mt-4 rounded-md border border-gray-200 bg-amber-50/50 p-3 text-sm text-amber-900 dark:border-gray-700 dark:bg-amber-950/30 dark:text-amber-200">
                        <p class="whitespace-pre-wrap break-words">{{ approvalModalState.excerpt }}</p>
                    </div>

                    <p class="mt-3 text-xs text-gray-600 dark:text-gray-300">
                        Apply updates this job to a non-interactive command template (Codex/Claude aware), stops the current run if needed, then re-runs it.
                    </p>
                    <p v-if="approvalError" class="mt-2 text-xs text-red-700 dark:text-red-300">{{ approvalError }}</p>

                    <div class="mt-4 flex items-center gap-2">
                        <button
                            class="rounded bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="approvalBusy"
                            @click="approveAndRerun"
                        >
                            {{ approvalBusy ? 'Processing…' : 'Apply & Re-run' }}
                        </button>
                        <button
                            class="rounded border border-amber-600 px-3 py-1.5 text-xs font-semibold text-amber-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-amber-300"
                            :disabled="approvalBusy"
                            @click="denyApprovalRun"
                        >
                            Deny (Stop Run)
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="rateLimitModalState && rateLimitModalRun" class="fixed inset-0 z-40 flex items-center justify-center bg-gray-900/50 p-4">
                <div class="w-full max-w-2xl rounded-lg border border-red-300 bg-white p-5 shadow-xl dark:border-red-900/70 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-red-800 dark:text-red-300">Rate Limit Detected</h3>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                Run #{{ rateLimitModalRun.id }} (job {{ rateLimitModalRun.agent_job_id }}) hit an upstream usage/rate limit.
                            </p>
                        </div>
                        <button class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50" @click="closeRateLimitModal">Close</button>
                    </div>

                    <div class="mt-4 rounded-md border border-gray-200 bg-red-50/50 p-3 text-sm text-red-900 dark:border-gray-700 dark:bg-red-950/30 dark:text-red-200">
                        <p class="whitespace-pre-wrap break-words">{{ rateLimitModalState.excerpt }}</p>
                    </div>

                    <p v-if="rateLimitModalState.holdUntil" class="mt-3 text-xs text-gray-700 dark:text-gray-300">
                        Hold until: <span class="font-semibold">{{ rateLimitModalState.holdUntil }}</span>
                        <span v-if="rateLimitModalState.holdActive"> (active)</span>
                    </p>
                    <p v-else class="mt-3 text-xs text-gray-700 dark:text-gray-300">
                        No reset time was parsed from output. Default hold policy applies.
                    </p>
                    <p v-if="rateLimitError" class="mt-2 text-xs text-red-700 dark:text-red-300">{{ rateLimitError }}</p>

                    <div class="mt-4 flex items-center gap-2">
                        <button
                            class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="rateLimitBusy"
                            @click="runAnywayAfterRateLimit"
                        >
                            {{ rateLimitBusy ? 'Dispatching…' : 'Run Anyway Now' }}
                        </button>
                        <button
                            class="rounded border border-red-600 px-3 py-1.5 text-xs font-semibold text-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-300"
                            :disabled="rateLimitBusy"
                            @click="closeRateLimitModal"
                        >
                            Wait Until Reset
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="clarificationModalState && clarificationModalRun" class="fixed inset-0 z-40 flex items-center justify-center bg-gray-900/50 p-4">
                <div class="w-full max-w-2xl rounded-lg border border-sky-300 bg-white p-5 shadow-xl dark:border-sky-900/70 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-sky-800 dark:text-sky-300">Clarification Requested</h3>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                Run #{{ clarificationModalRun.id }} (job {{ clarificationModalRun.agent_job_id }}) asked a question and needs clarification.
                            </p>
                        </div>
                        <button class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50" @click="closeClarificationModal">Close</button>
                    </div>

                    <div class="mt-4 rounded-md border border-gray-200 bg-sky-50/50 p-3 text-sm text-sky-900 dark:border-gray-700 dark:bg-sky-950/30 dark:text-sky-200">
                        <p class="whitespace-pre-wrap break-words">{{ clarificationModalState.excerpt }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
