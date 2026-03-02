<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import SessionStatusBadge from '@/Components/Interrogation/SessionStatusBadge.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Settings, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import axios from 'axios';
import { onMounted, reactive, ref } from 'vue';

const sessions = ref([]);
const loading = ref(false);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

const filters = reactive({
    q: '',
    status: '',
    type: '',
    runner: '',
    deleted: '',
    page: 1,
});

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/interrogation/sessions', { params: filters });
        sessions.value = data.data || [];
        meta.value = data.meta || meta.value;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load sessions.';
    } finally {
        loading.value = false;
    }
};

const setPage = async (page) => {
    filters.page = page;
    await load();
};

const retrySession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${sessionId}/retry`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to retry session.';
    }
};

const restartSession = async (sessionId) => {
    if (!window.confirm('Restart from the beginning? This will clear all questions, answers, and generated artifacts for this session.')) {
        return;
    }

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${sessionId}/restart-from-beginning`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restart session.';
    }
};

const renameSession = async (sessionId, currentName) => {
    const nextName = window.prompt('Session name', currentName ?? '');
    if (nextName === null) {
        return;
    }

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${sessionId}`, {
            name: nextName,
        });
        await load();
    } catch (e) {
        const payload = e?.response?.data ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to rename session.';
    }
};

const deleteSession = async (sessionId) => {
    if (!window.confirm('Delete this session? You can restore it later from the Deleted filter.')) {
        return;
    }

    try {
        await axios.delete(`/agent/api/v1/interrogation/sessions/${sessionId}`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete session.';
    }
};

const restoreSession = async (sessionId) => {
    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${sessionId}/restore`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restore session.';
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Discovery Sessions">
        <Head title="Discovery Sessions" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Requirements Discovery</h2>
                    <HelpHint
                        ui-key="discovery.sessions"
                        short-text="Find guidance for setup, interview flow, planning, and build handoff."
                        learn-more-href="/docs/overview"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('tools.discovery.settings')">
                        <Button variant="outline" size="sm">
                            <Settings class="h-4 w-4" />
                            Settings
                        </Button>
                    </Link>
                    <Link :href="route('tools.discovery.create')">
                        <Button size="sm">
                            <Plus class="h-4 w-4" />
                            New Session
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px] space-y-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                            <Input v-model="filters.q" placeholder="Search sessions" @change="load" />
                            <select
                                v-model="filters.status"
                                class="flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                @change="load"
                            >
                                <option value="">All statuses</option>
                                <option value="setup">setup</option>
                                <option value="discovering">discovering</option>
                                <option value="interrogating">interrogating</option>
                                <option value="summarizing">summarizing</option>
                                <option value="planning">planning</option>
                                <option value="build_tasks">build_tasks</option>
                                <option value="build_executing">build_executing</option>
                                <option value="paused">paused</option>
                                <option value="completed">completed</option>
                                <option value="failed">failed</option>
                            </select>
                            <select
                                v-model="filters.type"
                                class="flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                @change="load"
                            >
                                <option value="">All types</option>
                                <option value="feature">feature</option>
                                <option value="general">general</option>
                            </select>
                            <select
                                v-model="filters.runner"
                                class="flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                @change="load"
                            >
                                <option value="">All runners</option>
                                <option value="claude">claude</option>
                                <option value="codex">codex</option>
                            </select>
                            <select
                                v-model="filters.deleted"
                                class="flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                @change="load"
                            >
                                <option value="">Active only</option>
                                <option value="all">All</option>
                                <option value="true">Deleted only</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>

                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Session</TableHead>
                                <TableHead>Runner</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="loading">
                                <TableCell colspan="6" class="text-center">
                                    <Skeleton class="mx-auto h-6 w-32" />
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="session in sessions" :key="session.id">
                                <TableCell>
                                    <p class="font-medium">{{ session.name || `Session #${session.id}` }}</p>
                                    <p class="text-xs text-muted-foreground">{{ session.project_directory }}</p>
                                </TableCell>
                                <TableCell class="text-muted-foreground">{{ session.runner_type }}</TableCell>
                                <TableCell class="text-muted-foreground">{{ session.interrogation_type }}</TableCell>
                                <TableCell>
                                    <SessionStatusBadge
                                        :status="session.status"
                                        :build-status="session?.build?.status || session?.metadata_json?.build?.status || ''"
                                    />
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">{{ session.updated_at || '—' }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button
                                            v-if="!session.deleted_at
                                                && (['failed', 'paused', 'setup'].includes(session.status)
                                                    || (session.status === 'interrogating' && session.phase === 4))"
                                            variant="outline"
                                            size="sm"
                                            @click="retrySession(session.id)"
                                        >
                                            Retry
                                        </Button>
                                        <Button
                                            v-if="!session.deleted_at"
                                            variant="outline"
                                            size="sm"
                                            @click="restartSession(session.id)"
                                        >
                                            Restart
                                        </Button>
                                        <Button
                                            v-if="!session.deleted_at"
                                            variant="outline"
                                            size="sm"
                                            @click="renameSession(session.id, session.name || '')"
                                        >
                                            Rename
                                        </Button>
                                        <Button
                                            v-if="!session.deleted_at"
                                            variant="destructive"
                                            size="sm"
                                            @click="deleteSession(session.id)"
                                        >
                                            Delete
                                        </Button>
                                        <Link
                                            v-if="!session.deleted_at"
                                            :href="route('tools.discovery.session.settings', session.id)"
                                        >
                                            <Button variant="outline" size="sm">Settings</Button>
                                        </Link>
                                        <Button
                                            v-else
                                            variant="outline"
                                            size="sm"
                                            @click="restoreSession(session.id)"
                                        >
                                            Restore
                                        </Button>
                                        <Link :href="route('tools.discovery.wizard', session.id)">
                                            <Button variant="secondary" size="sm">Open</Button>
                                        </Link>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!loading && sessions.length === 0">
                                <TableCell colspan="6" class="text-center text-muted-foreground">No sessions found.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </Card>

                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <p>Showing page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</p>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" :disabled="meta.current_page <= 1" @click="setPage(meta.current_page - 1)">
                            <ChevronLeft class="h-4 w-4" />
                            Prev
                        </Button>
                        <Button variant="outline" size="sm" :disabled="meta.current_page >= meta.last_page" @click="setPage(meta.current_page + 1)">
                            Next
                            <ChevronRight class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
