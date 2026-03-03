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
import { Plus } from 'lucide-vue-next';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const props = defineProps({
    sessions: {
        type: Array,
        default: () => [],
    },
});

const sessions = ref(props.sessions);
const loading = ref(false);
const error = ref('');

const runningStatuses = ['snapshotting', 'planning', 'executing', 'validating', 'reporting'];

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/repo-analysis/sessions');
        sessions.value = (data?.data ?? []).map((session) => ({
            ...session,
            wizard_url: route('tools.repo-analysis.wizard', session.id),
            settings_url: route('tools.repo-analysis.settings', session.id),
        }));
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load repo analysis sessions.';
    } finally {
        loading.value = false;
    }
};

const pauseSession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/repo-analysis/sessions/${sessionId}/pause`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to pause session.';
    }
};

const resumeSession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/repo-analysis/sessions/${sessionId}/resume`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to resume session.';
    }
};

const retrySession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/repo-analysis/sessions/${sessionId}/retry`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to retry session.';
    }
};

const restartSession = async (sessionId) => {
    if (!window.confirm('Restart this repo analysis session from the beginning?')) {
        return;
    }

    try {
        await axios.post(`/agent/api/v1/repo-analysis/sessions/${sessionId}/restart-from-beginning`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restart session.';
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Repo Analysis">
        <Head title="Repo Analysis" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-foreground">Repo Analysis</h2>
                <Link :href="route('tools.repo-analysis.create')">
                    <Button size="sm">
                        <Plus class="h-4 w-4" />
                        New Session
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px] space-y-4">
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
                                            <Link :href="route('tools.repo-analysis.settings', session.id)">
                                                <Button variant="outline" size="sm">Settings</Button>
                                            </Link>
                                            <Link :href="route('tools.repo-analysis.wizard', session.id)">
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
