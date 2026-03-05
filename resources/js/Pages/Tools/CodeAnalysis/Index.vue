<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import { Head, Link } from '@inertiajs/vue3';
import { FileCode, Plus } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import axios from 'axios';
import { onMounted, onUnmounted, ref } from 'vue';
import { confirmDialog } from '@/Support/confirmDialog';

const props = defineProps({
    sessions: {
        type: Array,
        default: () => [],
    },
});

const sessions = ref(props.sessions);
const loading = ref(false);
const error = ref('');
const websocketActive = ref(false);

const runningStatuses = ['snapshotting', 'planning', 'executing', 'validating', 'reporting'];
const subscribedSessionIds = new Set();

const upsertSessionLinks = (session) => ({
    ...session,
    wizard_url: route('tools.code-analysis.wizard', session.id),
    settings_url: route('tools.code-analysis.settings', session.id),
});

const applyRealtimeEvent = (sessionId, event) => {
    const index = sessions.value.findIndex((session) => Number(session.id) === Number(sessionId));
    if (index < 0) {
        return;
    }

    const current = sessions.value[index];
    const nextStatus = String(event?.status ?? '').trim();
    const nextPhase = Number(event?.phase ?? Number.NaN);
    const nextUpdatedAt = String(event?.event_ts ?? '').trim();

    sessions.value[index] = {
        ...current,
        status: nextStatus !== '' ? nextStatus : current.status,
        phase: Number.isNaN(nextPhase) ? current.phase : nextPhase,
        updated_at: nextUpdatedAt !== '' ? nextUpdatedAt : current.updated_at,
    };
};

const leaveRealtime = () => {
    if (!window.Echo) {
        subscribedSessionIds.clear();
        websocketActive.value = false;

        return;
    }

    for (const sessionId of subscribedSessionIds) {
        window.Echo.leave(`private-code-analysis.${sessionId}`);
        window.Echo.leave(`code-analysis.${sessionId}`);
    }

    subscribedSessionIds.clear();
    websocketActive.value = false;
};

const subscribeRealtime = () => {
    leaveRealtime();

    if (!window.Echo) {
        websocketActive.value = false;
        return;
    }

    try {
        for (const session of sessions.value) {
            const sessionId = Number(session?.id);
            if (!Number.isFinite(sessionId)) {
                continue;
            }

            window.Echo.private(`code-analysis.${sessionId}`)
                .listen('.session.updated', (event) => applyRealtimeEvent(sessionId, event));
            subscribedSessionIds.add(sessionId);
        }

        websocketActive.value = true;
    } catch {
        websocketActive.value = false;
    }
};

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/code-analysis/sessions');
        sessions.value = (data?.data ?? []).map((session) => upsertSessionLinks(session));
        subscribeRealtime();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load code analysis sessions.';
    } finally {
        loading.value = false;
    }
};

const pauseSession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/code-analysis/sessions/${sessionId}/pause`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to pause session.';
    }
};

const resumeSession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/code-analysis/sessions/${sessionId}/resume`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to resume session.';
    }
};

const retrySession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/code-analysis/sessions/${sessionId}/retry`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to retry session.';
    }
};

const restartSession = async (sessionId) => {
    const approved = await confirmDialog('Restart this code analysis session from the beginning?', {
        title: 'Restart Session',
        confirmText: 'Restart',
        confirmVariant: 'destructive',
    });

    if (!approved) {
        return;
    }

    try {
        await axios.post(`/agent/api/v1/code-analysis/sessions/${sessionId}/restart-from-beginning`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restart session.';
    }
};

const purgeSession = async (sessionId) => {
    const approved = await confirmDialog('Permanently delete this session and all related tasks, events, reports, and exported files? This cannot be undone.', {
        title: 'Permanently Delete Session',
        confirmText: 'Delete Permanently',
        confirmVariant: 'destructive',
    });

    if (!approved) {
        return;
    }

    try {
        await axios.post(`/agent/api/v1/code-analysis/sessions/${sessionId}/purge`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to permanently delete session.';
    }
};

onMounted(load);
onUnmounted(leaveRealtime);
</script>

<template>
    <AppLayout title="Code Analysis">
        <Head title="Code Analysis" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <FileCode class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Code Analysis</h2>
                        <HelpHint
                            ui-key="code-analysis.overview"
                            short-text="Manage repository analysis sessions."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.code-analysis.create')">
                    <Button size="sm">
                        <Plus class="h-4 w-4" />
                        New Session
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <div
                    v-if="!websocketActive"
                    class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-300"
                >
                    Realtime updates unavailable. Start Reverb/Echo to live-refresh session statuses.
                </div>
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <Card>
                    <CardContent class="pt-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Session</TableHead>
                                    <TableHead>Runner</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Phase</TableHead>
                                    <TableHead>Updated</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="loading">
                                    <TableCell colspan="6" class="text-center text-muted-foreground">Loading sessions…</TableCell>
                                </TableRow>
                                <TableRow v-for="session in sessions" :key="session.id">
                                    <TableCell>
                                        <p class="font-medium">{{ session.name || `Session #${session.id}` }}</p>
                                        <p class="text-xs text-muted-foreground">{{ session.project_directory }}</p>
                                    </TableCell>
                                    <TableCell class="text-xs text-muted-foreground">{{ session.runner_type || 'claude' }}</TableCell>
                                    <TableCell class="text-xs">{{ session.status }}</TableCell>
                                    <TableCell class="text-xs text-muted-foreground">{{ session.phase }}</TableCell>
                                    <TableCell class="text-xs text-muted-foreground">{{ session.updated_at || '—' }}</TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button
                                                v-if="runningStatuses.includes(session.status)"
                                                variant="outline"
                                                size="sm"
                                                @click="pauseSession(session.id)"
                                            >
                                                Pause
                                            </Button>
                                            <Button
                                                v-if="session.status === 'paused'"
                                                variant="outline"
                                                size="sm"
                                                @click="resumeSession(session.id)"
                                            >
                                                Resume
                                            </Button>
                                            <Button
                                                v-if="session.status === 'failed'"
                                                variant="outline"
                                                size="sm"
                                                @click="retrySession(session.id)"
                                            >
                                                Retry
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="restartSession(session.id)"
                                            >
                                                Restart
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="border-destructive/40 text-destructive hover:bg-destructive/10"
                                                @click="purgeSession(session.id)"
                                            >
                                                Delete
                                            </Button>
                                            <Link :href="route('tools.code-analysis.settings', session.id)">
                                                <Button variant="outline" size="sm">Settings</Button>
                                            </Link>
                                            <Link :href="route('tools.code-analysis.wizard', session.id)">
                                                <Button size="sm">Open</Button>
                                            </Link>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!loading && sessions.length === 0">
                                    <TableCell colspan="6" class="text-center text-muted-foreground">No sessions found.</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
