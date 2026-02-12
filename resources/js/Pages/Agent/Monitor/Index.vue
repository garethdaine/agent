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
const approvalBusy = ref(false);
const approvalError = ref('');

const BASE_POLL_MS = 2000;
const INACTIVE_POLL_MS = 10000;
const HIDDEN_POLL_MS = 15000;
const BACKOFF = [2000, 4000, 8000, 15000];

const consecutiveFailureWarning = computed(() => failureCount.value >= 3);

const activeRuns = computed(() => runs.value.filter((run) => ['queued', 'starting', 'running', 'stopping'].includes(run.status)));
const selectedRun = computed(() => runs.value.find((run) => run.id === selectedRunId.value) ?? null);

const approvalHint = computed(() => {
    if (!selectedRun.value) {
        return null;
    }

    const match = [...events.value].reverse().find((event) => {
        if (event.event_type !== 'stdout' && event.event_type !== 'stderr') {
            return false;
        }

        return /need permission|requires permission|could you approve|approval/i.test(String(event.payload ?? ''));
    });

    if (!match) {
        return null;
    }

    return {
        sequence: match.sequence,
        excerpt: String(match.payload ?? '').slice(0, 500),
    };
});

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

    events.value = [...events.value, ...incoming].slice(-1000);

    if (autoFollow.value) {
        requestAnimationFrame(() => {
            const container = document.getElementById('event-tail');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    }
};

const loadMonitor = async () => {
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

        await loadEvents();

        failureCount.value = 0;
        errorMessage.value = '';
    } catch (error) {
        failureCount.value += 1;
        errorMessage.value = error?.response?.data?.error?.message ?? 'Monitor polling failed.';
    } finally {
        loading.value = false;
        scheduleNext();
    }
};

const stopRun = async (runId) => {
    await axios.post(`/agent/api/v1/runs/${runId}/stop`);
    await loadMonitor();
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

const buildNonInteractiveTemplate = (run, job) => {
    if (job.runner_type === 'codex') {
        return '/opt/homebrew/bin/codex --dangerously-bypass-approvals-and-sandbox --search exec {{task_markdown_path}}';
    }

    if (job.runner_type === 'claude') {
        return '/Users/garethdaine/.local/bin/claude -p {{task_markdown_path}}';
    }

    return job.command_template ?? '';
};

const approveAndRerun = async () => {
    if (!selectedRun.value) {
        return;
    }

    approvalBusy.value = true;
    approvalError.value = '';

    try {
        const run = selectedRun.value;
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
            command_template: buildNonInteractiveTemplate(run, job),
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
        await loadMonitor();
    } catch (error) {
        approvalError.value = error?.response?.data?.error?.message ?? error?.message ?? 'Approval action failed.';
    } finally {
        approvalBusy.value = false;
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
                    <Link :href="route('agent.jobs.index')" class="rounded border border-gray-300 px-3 py-1 text-sm">Jobs</Link>
                    <button class="rounded border border-gray-300 px-3 py-1 text-sm" @click="autoFollow = !autoFollow">
                        {{ autoFollow ? 'Auto-follow on' : 'Auto-follow off' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div
                v-if="approvalHint"
                class="mx-auto mb-4 max-w-7xl rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <p class="font-semibold">Approval Required</p>
                <p class="mt-1 whitespace-pre-wrap break-words text-xs opacity-90">{{ approvalHint.excerpt }}</p>
                <p class="mt-1 text-xs opacity-80">
                    Approve updates this job to a non-interactive command template, stops the current run, then re-runs it.
                </p>
                <p v-if="approvalError" class="mt-2 text-xs text-red-700 dark:text-red-300">{{ approvalError }}</p>
                <div class="mt-3 flex items-center gap-2">
                    <button
                        class="rounded bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="approvalBusy"
                        @click="approveAndRerun"
                    >
                        {{ approvalBusy ? 'Processing…' : 'Approve & Re-run' }}
                    </button>
                    <button
                        v-if="selectedRunId"
                        class="rounded border border-amber-600 px-3 py-1.5 text-xs font-semibold text-amber-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-amber-300"
                        :disabled="approvalBusy"
                        @click="stopRun(selectedRunId)"
                    >
                        Deny (Stop Run)
                    </button>
                </div>
            </div>

            <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Scheduler Health</h3>
                    <p class="mt-2 text-lg font-semibold" :class="{
                        'text-green-600': scheduler.status === 'healthy',
                        'text-yellow-600': scheduler.status === 'degraded',
                        'text-red-600': scheduler.status === 'down',
                        'text-gray-500': scheduler.status === 'unknown',
                    }">
                        {{ scheduler.status }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Last seen: {{ scheduler.last_seen_at ?? 'never' }}</p>
                    <p class="mt-1 text-xs text-gray-500">Age: {{ scheduler.age_seconds ?? 'n/a' }}s</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Queue Lag</h3>
                    <p class="mt-2 text-lg font-semibold" :class="queueLag.warning ? 'text-yellow-600' : 'text-gray-900 dark:text-gray-100'">
                        {{ queueLag.count }} queued / oldest {{ queueLag.oldestSeconds }}s
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Warning threshold: oldest &gt; 60s or queued &gt; 10</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Poll Status</h3>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">Consecutive failures: {{ failureCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">Intervals: active 2s, inactive 10s, hidden 15s with backoff.</p>
                </div>
            </div>

            <p v-if="consecutiveFailureWarning" class="mx-auto mt-4 max-w-7xl rounded-md border border-yellow-400 bg-yellow-50 px-3 py-2 text-sm text-yellow-700">
                Monitor polling is failing repeatedly. Retries continue automatically. {{ errorMessage }}
            </p>

            <div class="mx-auto mt-6 grid max-w-7xl grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Latest Runs (24h, max 50)</h3>
                    </div>
                    <div class="max-h-[30rem] overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Run</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created</th>
                                    <th class="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="loading">
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Loading...</td>
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
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ run.created_at }}</td>
                                    <td class="px-4 py-2 text-right text-xs">
                                        <button
                                            v-if="activeRuns.find((item) => item.id === run.id)"
                                            class="rounded border border-gray-300 px-2 py-1 hover:bg-gray-50"
                                            @click.stop="stopRun(run.id)"
                                        >
                                            Stop
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!loading && runs.length === 0">
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No runs in range.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Event Tail</h3>
                    </div>
                    <div id="event-tail" class="h-[30rem] overflow-auto bg-gray-950 p-3 font-mono text-xs text-gray-100">
                        <div v-for="event in events" :key="event.id" class="mb-1 whitespace-pre-wrap break-words">
                            <span class="text-gray-500">[{{ event.sequence }} {{ event.event_type }}]</span>
                            <span class="ml-1">{{ event.payload }}</span>
                        </div>
                        <p v-if="events.length === 0" class="text-gray-500">No events yet for selected run.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
