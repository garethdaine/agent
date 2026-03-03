<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import TaskGraphPanel from '@/Components/RepoAnalysis/TaskGraphPanel.vue';
import CoveragePanel from '@/Components/RepoAnalysis/CoveragePanel.vue';
import ReportViewer from '@/Components/RepoAnalysis/ReportViewer.vue';
import ArtifactInspector from '@/Components/RepoAnalysis/ArtifactInspector.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import { Head, Link } from '@inertiajs/vue3';
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
    actionVisibility: {
        type: Object,
        default: () => ({
            pause: false,
            resume: false,
            retry: false,
            restart: false,
            export: false,
        }),
    },
});

const session = ref(props.initialSession);
const events = ref([]);
const tasks = ref([]);
const artifacts = ref([]);
const reports = ref([]);
const loading = ref(false);
const loadingCollections = ref(false);
const error = ref('');
const notice = ref('');
const websocketActive = ref(false);
const actionVisibility = ref({ ...props.actionVisibility });
const actionBusy = ref({
    pause: false,
    resume: false,
    retry: false,
    restart: false,
    runNext: false,
    export: false,
});
const optimisticExpectedStatus = ref(null);
const autoStartRequested = ref(false);
const autoStartAttempted = ref(false);
const sessionRefreshTimer = ref(null);
const collectionsRefreshTimer = ref(null);

const activeStatusByPhase = {
    1: 'snapshotting',
    2: 'planning',
    3: 'executing',
    4: 'validating',
    5: 'reporting',
    6: 'completed',
};

const runningStatuses = ['snapshotting', 'planning', 'executing', 'validating', 'reporting'];

const deriveActionVisibility = () => {
    const status = String(session.value?.status ?? '');
    const canMutate = Boolean(props.viewer?.can_mutate);
    const hasExport = reports.value.length > 0;

    actionVisibility.value = {
        pause: canMutate && runningStatuses.includes(status),
        resume: canMutate && status === 'paused',
        retry: canMutate && status === 'failed',
        restart: canMutate,
        export: hasExport,
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
};

const loadSession = async () => {
    try {
        const { data } = await axios.get(`/agent/api/v1/repo-analysis/sessions/${props.sessionId}`);
        applySession(data?.data ?? null);
        deriveActionVisibility();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load session.';
    }
};

const loadEvents = async () => {
    try {
        const sinceSequence = nextEventCursor(events.value);
        const { data } = await axios.get(`/agent/api/v1/repo-analysis/sessions/${props.sessionId}/events`, {
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
        const [tasksResponse, artifactsResponse, reportsResponse] = await Promise.all([
            axios.get(`/agent/api/v1/repo-analysis/sessions/${props.sessionId}/tasks`, { params: { limit: 200 } }),
            axios.get(`/agent/api/v1/repo-analysis/sessions/${props.sessionId}/artifacts`, { params: { limit: 200 } }),
            axios.get(`/agent/api/v1/repo-analysis/sessions/${props.sessionId}/reports`, { params: { limit: 50 } }),
        ]);

        tasks.value = tasksResponse?.data?.data ?? [];
        artifacts.value = artifactsResponse?.data?.data ?? [];
        reports.value = reportsResponse?.data?.data ?? [];
        deriveActionVisibility();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load related repo analysis data.';
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
        echoChannel = window.Echo.private(`repo-analysis.${props.sessionId}`)
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
        window.Echo.leave(`private-repo-analysis.${props.sessionId}`);
        window.Echo.leave(`repo-analysis.${props.sessionId}`);
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

        await axios.post(`/agent/api/v1/repo-analysis/sessions/${props.sessionId}/${endpoint}`, payload ?? {});

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

const runNextStep = async () => {
    const phase = Number(session.value?.phase ?? 0);
    const status = String(session.value?.status ?? '');

    if (status === 'setup' && phase === 0) {
        await postLifecycle('start-snapshot', {
            busyKey: 'runNext',
            expectedStatus: 'snapshotting',
            successNotice: 'Snapshot queued.',
        });
        return;
    }

    if (phase === 2 && ['planning', 'failed', 'paused'].includes(status)) {
        await postLifecycle('plan', {
            busyKey: 'runNext',
            expectedStatus: 'planning',
            successNotice: 'Planning queued.',
        });
        return;
    }

    if (phase === 3 && ['executing', 'failed', 'paused'].includes(status)) {
        await postLifecycle('execute', {
            busyKey: 'runNext',
            expectedStatus: 'executing',
            successNotice: 'Execution queued.',
        });
        return;
    }

    if (phase === 4 && ['validating', 'failed', 'paused'].includes(status)) {
        await postLifecycle('validate-coverage', {
            busyKey: 'runNext',
            expectedStatus: 'validating',
            successNotice: 'Coverage validation queued.',
        });
        return;
    }

    if (phase === 5 && ['reporting', 'failed', 'paused'].includes(status)) {
        await postLifecycle('generate-report', {
            busyKey: 'runNext',
            expectedStatus: 'reporting',
            successNotice: 'Report generation queued.',
        });
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
        busyKey: 'runNext',
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
    <AppLayout title="Repo Analysis Wizard">
        <Head :title="`Repo Analysis #${sessionId}`" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Repo Analysis Wizard</h2>
                    <p class="text-xs text-muted-foreground">
                        Session #{{ sessionId }}
                        <span v-if="viewer.is_admin_override"> · admin override</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('tools.repo-analysis.settings', sessionId)">
                        <Button variant="outline" size="sm">Settings</Button>
                    </Link>
                    <Link :href="route('tools.repo-analysis.index')">
                        <Button variant="outline" size="sm">All Sessions</Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px] space-y-4">
                <div v-if="!websocketActive" class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-300">
                    Realtime updates are unavailable. Connect Reverb/Echo to receive live task updates.
                </div>
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>
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
                                <p class="mt-1 text-sm font-medium truncate">{{ session?.project_directory ?? '—' }}</p>
                            </div>
                            <div class="rounded border border-border p-3">
                                <p class="text-xs text-muted-foreground">Runner</p>
                                <p class="mt-1 text-sm font-medium">{{ session?.runner_type ?? 'claude' }}</p>
                            </div>
                            <div class="rounded border border-border p-3">
                                <p class="text-xs text-muted-foreground">Updated</p>
                                <p class="mt-1 text-sm font-medium">{{ session?.updated_at ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button :disabled="loading || actionBusy.runNext" @click="runNextStep">
                                {{ actionBusy.runNext ? 'Queueing…' : 'Run Next Step' }}
                            </Button>

                            <Button
                                v-if="actionVisibility.pause"
                                variant="outline"
                                :disabled="actionBusy.pause"
                                @click="postLifecycle('pause', { busyKey: 'pause', expectedStatus: 'paused', successNotice: 'Session paused.' })"
                            >
                                {{ actionBusy.pause ? 'Pausing…' : 'Pause' }}
                            </Button>

                            <Button
                                v-if="actionVisibility.resume"
                                variant="outline"
                                :disabled="actionBusy.resume"
                                @click="postLifecycle('resume', { busyKey: 'resume', expectedStatus: activeStatusByPhase[String(session?.phase ?? '')] ?? null, successNotice: 'Session resumed.' })"
                            >
                                {{ actionBusy.resume ? 'Resuming…' : 'Resume' }}
                            </Button>

                            <Button
                                v-if="actionVisibility.retry"
                                variant="outline"
                                :disabled="actionBusy.retry"
                                @click="postLifecycle('retry', { busyKey: 'retry', expectedStatus: activeStatusByPhase[String(session?.phase ?? '')] ?? null, successNotice: 'Session retry queued.' })"
                            >
                                {{ actionBusy.retry ? 'Retrying…' : 'Retry' }}
                            </Button>

                            <Button
                                v-if="actionVisibility.restart"
                                variant="outline"
                                :disabled="actionBusy.restart"
                                @click="postLifecycle('restart-from-beginning', { busyKey: 'restart', expectedStatus: 'setup', successNotice: 'Session restart queued.' })"
                            >
                                {{ actionBusy.restart ? 'Restarting…' : 'Restart' }}
                            </Button>

                            <Button
                                v-if="actionVisibility.export"
                                variant="outline"
                                :disabled="actionBusy.export"
                                @click="exportLatestReport"
                            >
                                {{ actionBusy.export ? 'Preparing…' : 'Export' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <TaskGraphPanel
                    :tasks="tasks"
                    :loading="loadingCollections"
                    :can-retry-failed-task="actionVisibility.retry || actionVisibility.resume || actionVisibility.pause"
                    @retry-task="retryTask"
                />

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <CoveragePanel :events="events" :session="session" />
                    <ArtifactInspector :artifacts="artifacts" />
                </div>

                <ReportViewer :reports="reports" :can-export="actionVisibility.export" @export-latest="exportLatestReport" />

                <Card>
                    <CardHeader>
                        <CardTitle>Event Stream</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="max-h-80 space-y-2 overflow-auto">
                            <div v-for="event in events" :key="event.sequence" class="rounded border border-border p-2">
                                <p class="text-xs font-medium">#{{ event.sequence }} · {{ event.event_type }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">phase {{ event.phase ?? '—' }} · status {{ event.status ?? '—' }}</p>
                            </div>
                            <p v-if="events.length === 0" class="text-sm text-muted-foreground">No events yet.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
