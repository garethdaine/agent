<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskGraphPanel from '@/Components/CodeAnalysis/TaskGraphPanel.vue';
import ReportViewer from '@/Components/CodeAnalysis/ReportViewer.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import { Head, Link } from '@inertiajs/vue3';
import { FileCode } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import axios from 'axios';
import { onMounted, onUnmounted, ref } from 'vue';
import { mergeEventsInSequence, nextEventCursor } from './eventStream';

const props = defineProps({
    sessionId: {
        type: Number,
        required: true,
    },
    initialSession: {
        type: Object,
        default: null,
    },
    viewer: {
        type: Object,
        default: () => ({
            can_mutate: false,
            is_admin_override: false,
        }),
    },
});

const session = ref(props.initialSession);
const events = ref([]);
const tasks = ref([]);
const reports = ref([]);
const loading = ref(false);
const loadingCollections = ref(false);
const error = ref('');
const notice = ref('');
const websocketActive = ref(false);
const actionVisibility = ref({
    pause: false,
    resume: false,
    retry: false,
    restart: false,
});
const actionBusy = ref({
    pause: false,
    resume: false,
    retry: false,
    restart: false,
    export: false,
});
const optimisticExpectedStatus = ref(null);
const autoStartRequested = ref(false);
const autoStartAttempted = ref(false);
const sessionRefreshTimer = ref(null);
const collectionsRefreshTimer = ref(null);

const activeStatusByPhase = {
    3: 'executing',
};

const runningStatuses = ['snapshotting', 'planning', 'executing', 'validating', 'reporting'];

const deriveActionVisibility = () => {
    const status = String(session.value?.status ?? '');
    const canMutate = Boolean(props.viewer?.can_mutate);

    actionVisibility.value = {
        pause: canMutate && runningStatuses.includes(status),
        resume: canMutate && status === 'paused',
        retry: canMutate && status === 'failed',
        restart: canMutate,
    };
};

const applySession = (nextSession) => {
    const expected = optimisticExpectedStatus.value;
    session.value = nextSession;

    if (expected && String(nextSession?.status ?? '') !== expected) {
        notice.value = `Server state superseded stale client state (${expected} -> ${nextSession?.status ?? 'unknown'}).`;
        optimisticExpectedStatus.value = null;
    } else if (expected && String(nextSession?.status ?? '') === expected) {
        optimisticExpectedStatus.value = null;
    }

    deriveActionVisibility();
};

const loadSession = async () => {
    try {
        const { data } = await axios.get(`/agent/api/v1/code-analysis/sessions/${props.sessionId}`);
        applySession(data?.data ?? null);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load session.';
    }
};

const loadEvents = async () => {
    try {
        const sinceSequence = nextEventCursor(events.value);
        const { data } = await axios.get(`/agent/api/v1/code-analysis/sessions/${props.sessionId}/events`, {
            params: {
                since_sequence: sinceSequence,
                limit: 200,
            },
        });

        events.value = mergeEventsInSequence(events.value, data?.data ?? []);
    } catch {
        // Initial event hydration failures should not block wizard rendering.
    }
};

const loadCollections = async (options = {}) => {
    const { silent = false } = options;

    if (!silent) {
        loadingCollections.value = true;
    }

    try {
        const [tasksResponse, reportsResponse] = await Promise.all([
            axios.get(`/agent/api/v1/code-analysis/sessions/${props.sessionId}/tasks`, { params: { limit: 200 } }),
            axios.get(`/agent/api/v1/code-analysis/sessions/${props.sessionId}/reports`, { params: { limit: 50 } }),
        ]);

        tasks.value = tasksResponse?.data?.data ?? [];
        reports.value = reportsResponse?.data?.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load related code analysis data.';
    } finally {
        if (!silent) {
            loadingCollections.value = false;
        }
    }
};

const refreshAll = async (options = {}) => {
    const { silentCollections = false } = options;

    loading.value = true;
    error.value = '';

    await Promise.all([
        loadSession(),
        loadEvents(),
        loadCollections({ silent: silentCollections }),
    ]);

    loading.value = false;
};

let echoChannel = null;
const sessionRefreshEvents = new Set([
    'snapshot_generated',
    'tasks_planned',
    'task_started',
    'task_retry_scheduled',
    'task_failed',
    'task_completed',
    'snapshot_drift_detected',
    'coverage_validated',
    'report_generated',
]);
const collectionRefreshEvents = new Set([
    'snapshot_generated',
    'tasks_planned',
    'task_started',
    'task_retry_scheduled',
    'task_failed',
    'task_completed',
    'coverage_validated',
    'report_generated',
]);

const queueSessionRefresh = () => {
    clearTimeout(sessionRefreshTimer.value);
    sessionRefreshTimer.value = setTimeout(() => {
        loadSession();
    }, 150);
};

const queueCollectionsRefresh = () => {
    clearTimeout(collectionsRefreshTimer.value);
    collectionsRefreshTimer.value = setTimeout(() => {
        loadCollections({ silent: true });
    }, 150);
};

const subscribeRealtime = () => {
    if (!window.Echo) {
        websocketActive.value = false;
        return;
    }

    try {
        echoChannel = window.Echo.private(`code-analysis.${props.sessionId}`)
            .listen('.session.updated', (event) => {
                if (!event) {
                    return;
                }

                events.value = mergeEventsInSequence(events.value, [event]);
                const nextPhase = Number(event?.phase ?? NaN);
                if (!Number.isNaN(nextPhase) && session.value) {
                    session.value = {
                        ...session.value,
                        phase: nextPhase,
                    };
                }

                const nextStatus = String(event?.status ?? '').trim();
                if (nextStatus !== '' && session.value) {
                    session.value = {
                        ...session.value,
                        status: nextStatus,
                    };
                    deriveActionVisibility();
                }

                const eventType = String(event?.event_type ?? '');

                if (sessionRefreshEvents.has(eventType)) {
                    queueSessionRefresh();
                }

                if (collectionRefreshEvents.has(eventType)) {
                    queueCollectionsRefresh();
                }
            });

        websocketActive.value = true;
    } catch {
        websocketActive.value = false;
    }
};

const unsubscribeRealtime = () => {
    if (window.Echo && echoChannel) {
        window.Echo.leave(`private-code-analysis.${props.sessionId}`);
        window.Echo.leave(`code-analysis.${props.sessionId}`);
    }

    echoChannel = null;
    websocketActive.value = false;
};

const postLifecycle = async (endpoint, options = {}) => {
    const { busyKey = null, expectedStatus = null, payload = null, successNotice = '' } = options;

    if (busyKey) {
        actionBusy.value[busyKey] = true;
    }

    error.value = '';

    try {
        if (expectedStatus) {
            optimisticExpectedStatus.value = expectedStatus;
        }

        await axios.post(`/agent/api/v1/code-analysis/sessions/${props.sessionId}/${endpoint}`, payload ?? {});

        if (successNotice !== '') {
            notice.value = successNotice;
        }

        await refreshAll({ silentCollections: true });
    } catch (e) {
        optimisticExpectedStatus.value = null;
        error.value = e?.response?.data?.error?.message ?? 'Failed to perform session action.';
    } finally {
        if (busyKey) {
            actionBusy.value[busyKey] = false;
        }
    }
};

const maybeAutoStart = async () => {
    if (autoStartAttempted.value) {
        return;
    }

    if (!props.viewer?.can_mutate) {
        autoStartAttempted.value = true;

        return;
    }

    const phase = Number(session.value?.phase ?? 0);
    const status = String(session.value?.status ?? '');
    const metadataAutoStart = Boolean(session.value?.metadata?.auto_start_on_open);
    const inferredAutoStart = phase === 0 && status === 'setup' && events.value.length === 0;
    const shouldAutoStart = autoStartRequested.value || metadataAutoStart || inferredAutoStart;

    if (!shouldAutoStart) {
        autoStartAttempted.value = true;

        return;
    }

    if (phase !== 0 || status !== 'setup') {
        autoStartAttempted.value = true;

        return;
    }

    autoStartAttempted.value = true;

    await postLifecycle('start-snapshot', {
        expectedStatus: 'snapshotting',
        successNotice: 'Snapshot auto-started.',
    });
};

const retryTask = async (taskId) => {
    await postLifecycle('retry-task', {
        payload: { task_id: taskId },
        expectedStatus: activeStatusByPhase[String(session.value?.phase ?? '')] ?? null,
        successNotice: `Task ${taskId} queued for retry.`,
    });
};

const pauseSession = async () => {
    await postLifecycle('pause', {
        busyKey: 'pause',
        expectedStatus: 'paused',
        successNotice: 'Session paused.',
    });
};

const resumeSession = async () => {
    await postLifecycle('resume', {
        busyKey: 'resume',
        expectedStatus: activeStatusByPhase[String(session.value?.phase ?? '')] ?? null,
        successNotice: 'Session resumed.',
    });
};

const retrySession = async () => {
    await postLifecycle('retry', {
        busyKey: 'retry',
        expectedStatus: activeStatusByPhase[String(session.value?.phase ?? '')] ?? null,
        successNotice: 'Session retry queued.',
    });
};

const restartSession = async () => {
    await postLifecycle('restart-from-beginning', {
        busyKey: 'restart',
        expectedStatus: 'setup',
        successNotice: 'Session restart queued.',
    });
};

const exportLatestReport = async () => {
    actionBusy.value.export = true;
    error.value = '';

    try {
        const latestExportable = reports.value.find((report) => report?.markdown_export_path || report?.json_export_path);
        if (!latestExportable) {
            notice.value = 'Report exists, but export file paths are not available yet.';
            return;
        }

        const paths = [latestExportable.markdown_export_path, latestExportable.json_export_path].filter(Boolean);
        notice.value = `Export available at: ${paths.join(' | ')}`;
    } catch (e) {
        error.value = e?.message ?? 'Failed to prepare export.';
    } finally {
        actionBusy.value.export = false;
    }
};

onMounted(async () => {
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        autoStartRequested.value = params.get('autostart') === '1';

        if (autoStartRequested.value) {
            params.delete('autostart');
            const query = params.toString();
            const cleanedUrl = `${window.location.pathname}${query ? `?${query}` : ''}`;
            window.history.replaceState({}, '', cleanedUrl);
        }
    }

    await refreshAll();
    await maybeAutoStart();
    subscribeRealtime();
});

onUnmounted(() => {
    clearTimeout(sessionRefreshTimer.value);
    clearTimeout(collectionsRefreshTimer.value);
    unsubscribeRealtime();
});
</script>

<template>
    <AppLayout title="Code Analysis Wizard">
        <Head :title="`Code Analysis #${sessionId}`" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <FileCode class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-foreground truncate">Code Analysis Wizard</h2>
                            <HelpHint
                                ui-key="code-analysis.wizard"
                                short-text="Step through code analysis with AI assistance."
                                learn-more-href="/docs/overview"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Session #{{ sessionId }}
                            <span v-if="viewer.is_admin_override"> · admin override</span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('tools.code-analysis.settings', sessionId)">
                        <Button variant="outline" size="sm">Settings</Button>
                    </Link>
                    <Link :href="route('tools.code-analysis.index')">
                        <Button variant="outline" size="sm">All Sessions</Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <div v-if="!websocketActive" class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-300">
                    Realtime updates are unavailable. Connect Reverb/Echo to receive live task updates.
                </div>
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>
                <div v-if="session?.error_summary" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    <p class="font-medium">Latest failure</p>
                    <p class="mt-1">{{ session.error_summary }}</p>
                    <p v-if="session?.error_code" class="mt-1 text-xs opacity-90">Code: {{ session.error_code }}</p>
                </div>
                <div v-if="notice" class="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-300">{{ notice }}</div>

                <Card>
                    <CardHeader>
                        <CardTitle>Session State</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid gap-3 md:grid-cols-5">
                            <div class="rounded border border-border p-3">
                                <p class="text-xs text-muted-foreground">Status</p>
                                <p class="mt-1 text-sm font-medium">{{ session?.status ?? '—' }}</p>
                            </div>
                            <div class="rounded border border-border p-3">
                                <p class="text-xs text-muted-foreground">Phase</p>
                                <p class="mt-1 text-sm font-medium">{{ session?.phase ?? '—' }}</p>
                            </div>
                            <div class="rounded border border-border p-3">
                                <p class="text-xs text-muted-foreground">Project</p>
                                <p class="mt-1 truncate text-sm font-medium">{{ session?.project_directory ?? '—' }}</p>
                            </div>
                            <div class="rounded border border-border p-3">
                                <p class="text-xs text-muted-foreground">Runner</p>
                                <p class="mt-1 text-sm font-medium">{{ session?.runner_type ?? 'codex' }}</p>
                            </div>
                            <div class="rounded border border-border p-3">
                                <p class="text-xs text-muted-foreground">Updated</p>
                                <p class="mt-1 text-sm font-medium">{{ session?.updated_at ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="actionVisibility.pause"
                                variant="outline"
                                :disabled="actionBusy.pause"
                                @click="pauseSession"
                            >
                                {{ actionBusy.pause ? 'Pausing…' : 'Pause' }}
                            </Button>

                            <Button
                                v-if="actionVisibility.resume"
                                variant="outline"
                                :disabled="actionBusy.resume"
                                @click="resumeSession"
                            >
                                {{ actionBusy.resume ? 'Resuming…' : 'Resume' }}
                            </Button>

                            <Button
                                v-if="actionVisibility.retry"
                                variant="outline"
                                :disabled="actionBusy.retry"
                                @click="retrySession"
                            >
                                {{ actionBusy.retry ? 'Retrying…' : 'Retry Session' }}
                            </Button>

                            <Button
                                v-if="actionVisibility.restart"
                                variant="outline"
                                :disabled="actionBusy.restart"
                                @click="restartSession"
                            >
                                {{ actionBusy.restart ? 'Restarting…' : 'Restart' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <TaskGraphPanel
                    :tasks="tasks"
                    :loading="loadingCollections"
                    :can-retry-failed-task="viewer.can_mutate"
                    @retry-task="retryTask"
                />
                <ReportViewer :reports="reports" :can-export="reports.length > 0" @export-latest="exportLatestReport" />
            </div>
        </div>
    </AppLayout>
</template>
