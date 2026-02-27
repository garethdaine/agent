<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
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
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Heart, Gauge, Radio, RefreshCw, AlertTriangle, ShieldCheck, HelpCircle, Square, X } from 'lucide-vue-next';

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
                <h2 class="text-xl font-semibold leading-tight text-foreground">Run Monitor</h2>
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
            <div class="mx-auto grid max-w-[1440px] grid-cols-1 gap-6 lg:grid-cols-3">
                <Card>
                    <CardContent class="p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <Heart class="w-4 h-4 text-success" />
                            <span class="text-muted-foreground uppercase tracking-wider text-[11px] font-semibold">Scheduler Health</span>
                        </div>
                        <div :class="[
                            'text-[22px] font-bold',
                            scheduler.status === 'healthy' ? 'text-success' : '',
                            scheduler.status === 'degraded' ? 'text-warning' : '',
                            scheduler.status === 'down' ? 'text-destructive' : '',
                            scheduler.status === 'unknown' ? 'text-muted-foreground' : '',
                        ]">
                            {{ scheduler.status }}
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">Last seen: {{ scheduler.last_seen_at ?? 'never' }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Age: {{ scheduler.age_seconds ?? 'n/a' }}s</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <Gauge class="w-4 h-4 text-primary" />
                            <span class="text-muted-foreground uppercase tracking-wider text-[11px] font-semibold">Queue Lag</span>
                        </div>
                        <div :class="['text-[22px] font-bold', queueLag.warning ? 'text-warning' : 'text-foreground']">
                            {{ queueLag.count }} queued / oldest {{ queueLag.oldestSeconds }}s
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">Warning threshold: oldest &gt; 60s or queued &gt; 10</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <Radio class="w-4 h-4 text-primary" />
                            <span class="text-muted-foreground uppercase tracking-wider text-[11px] font-semibold">Poll Status</span>
                        </div>
                        <div class="text-sm text-foreground">Consecutive failures: {{ failureCount }}</div>
                        <p class="mt-2 text-xs text-muted-foreground">Intervals: active 2s, inactive 10s, hidden 15s with backoff.</p>
                    </CardContent>
                </Card>
            </div>

            <div v-if="consecutiveFailureWarning" class="mx-auto mt-4 max-w-[1440px] flex items-center gap-2 rounded-md border border-warning/40 bg-warning/10 px-3 py-2 text-sm text-warning">
                <AlertTriangle class="w-4 h-4 shrink-0" />
                <span>Monitor polling is failing repeatedly. Retries continue automatically. {{ errorMessage }}</span>
            </div>

            <div class="mx-auto mt-6 grid max-w-[1440px] grid-cols-1 gap-6 lg:grid-cols-2">
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
                        <h3 class="text-[13px] font-semibold uppercase tracking-wider text-muted-foreground">Event Tail</h3>
                    </div>
                    <div id="event-tail" class="h-[30rem] overflow-auto bg-[#0c1222] p-4 font-mono text-sm text-foreground">
                        <div v-for="entry in formattedEvents" :key="entry.key" class="mb-2 whitespace-pre-wrap break-words">
                            <div class="text-[11px] text-muted-foreground">{{ entry.prefix }}</div>
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
                </Card>
            </div>

            <div v-if="approvalModalState && approvalModalRun" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4">
                <Card class="w-full max-w-2xl border-warning/40 shadow-xl">
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <ShieldCheck class="w-5 h-5 text-warning" />
                                    <h3 class="text-lg font-semibold text-warning">
                                        {{ approvalModalState?.kind === 'permission_blocker' ? 'Write Permission Blocker' : 'Approval Required' }}
                                    </h3>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Run #{{ approvalModalRun.id }} (job {{ approvalModalRun.agent_job_id }}) {{
                                        approvalModalState?.kind === 'permission_blocker'
                                            ? 'reported a write-permission blocker.'
                                            : 'needs approval before it can continue.'
                                    }}
                                </p>
                            </div>
                            <Button variant="ghost" size="sm" @click="closeApprovalModal">
                                <X class="w-4 h-4" />
                            </Button>
                        </div>

                        <div class="mt-4 rounded-md border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                            <p class="whitespace-pre-wrap break-words">{{ approvalModalState.excerpt }}</p>
                        </div>

                        <p class="mt-3 text-xs text-muted-foreground">
                            Apply updates this job to a non-interactive command template (Codex/Claude aware), stops the current run if needed, then re-runs it.
                        </p>
                        <p v-if="approvalError" class="mt-2 text-xs text-destructive">{{ approvalError }}</p>

                        <div class="mt-4 flex items-center gap-2">
                            <Button
                                :disabled="approvalBusy"
                                @click="approveAndRerun"
                            >
                                <RefreshCw v-if="approvalBusy" class="w-4 h-4 animate-spin" />
                                {{ approvalBusy ? 'Processing…' : 'Apply & Re-run' }}
                            </Button>
                            <Button
                                variant="outline"
                                :disabled="approvalBusy"
                                @click="denyApprovalRun"
                            >
                                Deny (Stop Run)
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="rateLimitModalState && rateLimitModalRun" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4">
                <Card class="w-full max-w-2xl border-destructive/40 shadow-xl">
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <AlertTriangle class="w-5 h-5 text-destructive" />
                                    <h3 class="text-lg font-semibold text-destructive">Rate Limit Detected</h3>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Run #{{ rateLimitModalRun.id }} (job {{ rateLimitModalRun.agent_job_id }}) hit an upstream usage/rate limit.
                                </p>
                            </div>
                            <Button variant="ghost" size="sm" @click="closeRateLimitModal">
                                <X class="w-4 h-4" />
                            </Button>
                        </div>

                        <div class="mt-4 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                            <p class="whitespace-pre-wrap break-words">{{ rateLimitModalState.excerpt }}</p>
                        </div>

                        <p v-if="rateLimitModalState.holdUntil" class="mt-3 text-xs text-muted-foreground">
                            Hold until: <span class="font-semibold text-foreground">{{ rateLimitModalState.holdUntil }}</span>
                            <Badge v-if="rateLimitModalState.holdActive" variant="destructive" class="ml-2">active</Badge>
                        </p>
                        <p v-else class="mt-3 text-xs text-muted-foreground">
                            No reset time was parsed from output. Default hold policy applies.
                        </p>
                        <p v-if="rateLimitError" class="mt-2 text-xs text-destructive">{{ rateLimitError }}</p>

                        <div class="mt-4 flex items-center gap-2">
                            <Button
                                variant="destructive"
                                :disabled="rateLimitBusy"
                                @click="runAnywayAfterRateLimit"
                            >
                                <RefreshCw v-if="rateLimitBusy" class="w-4 h-4 animate-spin" />
                                {{ rateLimitBusy ? 'Dispatching…' : 'Run Anyway Now' }}
                            </Button>
                            <Button
                                variant="outline"
                                :disabled="rateLimitBusy"
                                @click="closeRateLimitModal"
                            >
                                Wait Until Reset
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="clarificationModalState && clarificationModalRun" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4">
                <Card class="w-full max-w-2xl border-primary/40 shadow-xl">
                    <CardContent class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <HelpCircle class="w-5 h-5 text-primary" />
                                    <h3 class="text-lg font-semibold text-primary">Clarification Requested</h3>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Run #{{ clarificationModalRun.id }} (job {{ clarificationModalRun.agent_job_id }}) asked a question and needs clarification.
                                </p>
                            </div>
                            <Button variant="ghost" size="sm" @click="closeClarificationModal">
                                <X class="w-4 h-4" />
                            </Button>
                        </div>

                        <div class="mt-4 rounded-md border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                            <p class="whitespace-pre-wrap break-words">{{ clarificationModalState.excerpt }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
