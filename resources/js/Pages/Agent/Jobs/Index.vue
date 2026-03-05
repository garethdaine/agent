<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Input from '@/Components/ui/Input.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Briefcase, Plus, Play, Pencil, Trash2, RotateCcw, ToggleLeft, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import axios from 'axios';
import { onMounted, reactive, ref } from 'vue';

const loading = ref(false);
const error = ref('');
const jobs = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

const filters = reactive({
    q: '',
    is_enabled: '',
    runner_type: '',
    source: 'user',
    deleted: '',
    page: 1,
});

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'true', label: 'Enabled' },
    { value: 'false', label: 'Disabled' },
];

const runnerOptions = [
    { value: '', label: 'All runners' },
    { value: 'claude', label: 'claude' },
    { value: 'codex', label: 'codex' },
    { value: 'custom', label: 'custom' },
];

const sourceOptions = [
    { value: '', label: 'All job sources' },
    { value: 'user', label: 'User jobs' },
    { value: 'build', label: 'Build jobs' },
];

const deletedOptions = [
    { value: '', label: 'Active only' },
    { value: 'all', label: 'All' },
    { value: 'true', label: 'Deleted only' },
];

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/jobs', { params: filters });
        jobs.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load jobs.';
    } finally {
        loading.value = false;
    }
};

const toggle = async (id) => {
    await axios.post(`/agent/api/v1/jobs/${id}/toggle`);
    await load();
};

const runNow = async (id) => {
    try {
        await axios.post(`/agent/api/v1/jobs/${id}/run-now`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to enqueue run.';
    }
};

const removeJob = async (id) => {
    await axios.delete(`/agent/api/v1/jobs/${id}`);
    await load();
};

const restoreJob = async (id) => {
    await axios.post(`/agent/api/v1/jobs/${id}/restore`);
    await load();
};

const setPage = async (page) => {
    filters.page = page;
    await load();
};

const setSource = (source) => {
    filters.source = source;
    filters.page = 1;
    load();
};

onMounted(load);
</script>

<template>
    <AppLayout title="Agent Jobs">
        <Head title="Agent Jobs" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Briefcase class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">Agent Jobs</h2>
                        <HelpHint
                            ui-key="jobs.overview"
                            short-text="Review scheduling, runner selection, and safe execution controls."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('agent.jobs.create')">
                    <Button>
                        <Plus class="h-4 w-4 mr-2" />
                        New Job
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <Card class="p-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                        <Input v-model="filters.q" placeholder="Search name / description" @change="load" />
                        <select
                            v-model="filters.is_enabled"
                            class="h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="load"
                        >
                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <select
                            v-model="filters.runner_type"
                            class="h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="load"
                        >
                            <option v-for="opt in runnerOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <select
                            v-model="filters.source"
                            class="h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="load"
                        >
                            <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <select
                            v-model="filters.deleted"
                            class="h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="load"
                        >
                            <option v-for="opt in deletedOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                </Card>

                <div class="flex items-center gap-2">
                    <Button
                        :variant="filters.source === '' ? 'default' : 'outline'"
                        size="sm"
                        @click="setSource('')"
                    >
                        All Jobs
                    </Button>
                    <Button
                        :variant="filters.source === 'user' ? 'default' : 'outline'"
                        size="sm"
                        @click="setSource('user')"
                    >
                        User Jobs
                    </Button>
                    <Button
                        :variant="filters.source === 'build' ? 'default' : 'outline'"
                        size="sm"
                        @click="setSource('build')"
                    >
                        Build Jobs
                    </Button>
                </div>

                <p v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </p>

                <Card>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Enabled</TableHead>
                                <TableHead>Runner</TableHead>
                                <TableHead>Cron / TZ</TableHead>
                                <TableHead>Next Run (UTC)</TableHead>
                                <TableHead>Last Run</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="loading">
                                <TableCell colspan="7" class="text-center py-8">
                                    <div class="flex flex-col items-center gap-2">
                                        <Skeleton class="h-4 w-48" />
                                        <Skeleton class="h-4 w-32" />
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="job in jobs" :key="job.id">
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-foreground">{{ job.name }}</span>
                                        <Badge v-if="job.is_build_job" variant="secondary">Build</Badge>
                                    </div>
                                    <div class="text-xs text-muted-foreground">{{ job.description }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="job.is_enabled ? 'default' : 'secondary'">
                                        {{ job.is_enabled ? 'Enabled' : 'Disabled' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground">{{ job.runner_type }}</TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {{ job.cron_expression }}<br>{{ job.timezone }}
                                </TableCell>
                                <TableCell class="text-xs text-muted-foreground">{{ job.next_run_utc ?? '—' }}</TableCell>
                                <TableCell class="text-xs text-muted-foreground">
                                    {{ job.last_run_status ?? 'none' }}<br>
                                    {{ job.last_run_finished_at ?? '—' }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button variant="ghost" size="icon" @click="runNow(job.id)" title="Run now">
                                            <Play class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" @click="toggle(job.id)" title="Toggle enabled">
                                            <ToggleLeft class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            v-if="!job.deleted_at"
                                            variant="ghost"
                                            size="icon"
                                            @click="removeJob(job.id)"
                                            title="Delete"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            v-else
                                            variant="ghost"
                                            size="icon"
                                            @click="restoreJob(job.id)"
                                            title="Restore"
                                        >
                                            <RotateCcw class="h-4 w-4" />
                                        </Button>
                                        <Link :href="route('agent.jobs.edit', job.id)">
                                            <Button variant="ghost" size="icon" title="Edit">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!loading && jobs.length === 0">
                                <TableCell colspan="7" class="text-center py-8 text-muted-foreground">
                                    No jobs found.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </Card>

                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <p>Showing page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="meta.current_page <= 1"
                            @click="setPage(meta.current_page - 1)"
                        >
                            <ChevronLeft class="h-4 w-4 mr-1" />
                            Prev
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="meta.current_page >= meta.last_page"
                            @click="setPage(meta.current_page + 1)"
                        >
                            Next
                            <ChevronRight class="h-4 w-4 ml-1" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
