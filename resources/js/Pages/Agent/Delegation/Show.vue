<script setup>
import { ref, onMounted, computed } from 'vue';
import { formatDateTime } from '@/Utils/formatDate';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Play, XCircle, Copy, ChevronDown, ChevronUp, ArrowLeft, GitBranch, Pencil, Trash2, RotateCcw } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    graphId: {
        type: Number,
        required: true,
    },
});

const graph = ref(null);
const tasks = ref([]);
const events = ref([]);
const loading = ref(true);
const error = ref('');
const showEvents = ref(false);

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const [graphRes, tasksRes] = await Promise.all([
            axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}`),
            axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}/tasks`),
        ]);
        graph.value = graphRes.data.data;
        tasks.value = tasksRes.data.data;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load graph.';
    } finally {
        loading.value = false;
    }
};

const loadEvents = async () => {
    try {
        const { data } = await axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}/events`);
        events.value = data.data;
    } catch (e) {
        console.error('Failed to load events', e);
    }
};

const startGraph = async () => {
    try {
        await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/start`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to start graph.';
    }
};

const cancelGraph = async () => {
    try {
        await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/cancel`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to cancel graph.';
    }
};

const cloneGraph = async () => {
    try {
        const { data } = await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/clone`);
        router.visit(route('agent.delegation.graphs.builder.edit', { id: data.data.id }));
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to clone graph.';
    }
};

const statusBadgeVariant = (status) => {
    const variants = {
        draft: 'secondary',
        validating: 'default',
        ready: 'default',
        running: 'secondary',
        succeeded: 'default',
        failed: 'destructive',
        partial: 'secondary',
        cancelled: 'outline',
        pending: 'outline',
        blocked: 'secondary',
        assigned: 'default',
        verifying: 'default',
    };
    return variants[status] || 'secondary';
};

const canStart = computed(() => graph.value?.status === 'ready');
const canCancel = computed(() => graph.value?.status === 'running');
const canEdit = computed(() => graph.value?.status === 'draft');
const canDelete = computed(() => ['succeeded', 'failed', 'partial', 'cancelled'].includes(graph.value?.status));
const isDeleted = computed(() => !!graph.value?.deleted_at);

const confirmingDelete = ref(false);

const editGraph = () => {
    router.visit(route('agent.delegation.graphs.builder.edit', { id: props.graphId }));
};

const deleteGraph = async () => {
    try {
        await axios.delete(`/agent/api/v1/delegation/graphs/${props.graphId}`);
        await load();
        confirmingDelete.value = false;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete graph.';
        confirmingDelete.value = false;
    }
};

const restoreGraph = async () => {
    try {
        await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/restore`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restore graph.';
    }
};

onMounted(() => {
    load();
});
</script>

<template>
    <AppLayout title="Delegation Graph">
        <Head :title="graph?.name ?? 'Delegation Graph'" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('agent.delegation.index')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <GitBranch class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-foreground truncate">{{ graph?.name ?? 'Loading...' }}</h2>
                            <HelpHint
                                ui-key="delegation.detail"
                                short-text="Inspect delegation graph structure and task assignments."
                                learn-more-href="/docs/overview"
                            />
                        </div>
                        <p v-if="graph?.description" class="text-sm text-muted-foreground">{{ graph.description }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="canEdit"
                        variant="outline"
                        @click="editGraph"
                    >
                        <Pencil class="mr-2 h-4 w-4" />
                        Edit
                    </Button>
                    <Button
                        v-if="canStart"
                        @click="startGraph"
                    >
                        <Play class="mr-2 h-4 w-4" />
                        Start
                    </Button>
                    <Button
                        v-if="canCancel"
                        variant="destructive"
                        @click="cancelGraph"
                    >
                        <XCircle class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button
                        variant="outline"
                        @click="cloneGraph"
                    >
                        <Copy class="mr-2 h-4 w-4" />
                        Clone
                    </Button>
                    <Button
                        v-if="isDeleted"
                        variant="outline"
                        @click="restoreGraph"
                    >
                        <RotateCcw class="mr-2 h-4 w-4" />
                        Restore
                    </Button>
                    <Button
                        v-if="canDelete && !isDeleted && !confirmingDelete"
                        variant="ghost"
                        class="text-destructive hover:text-destructive"
                        @click="confirmingDelete = true"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                    <template v-if="confirmingDelete">
                        <span class="text-sm text-muted-foreground">Are you sure?</span>
                        <Button
                            variant="destructive"
                            size="sm"
                            @click="deleteGraph"
                        >
                            Confirm
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="confirmingDelete = false"
                        >
                            Cancel
                        </Button>
                    </template>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <div v-if="loading" class="flex flex-col items-center justify-center py-12 gap-4">
                    <Skeleton class="h-4 w-48" />
                    <Skeleton class="h-4 w-32" />
                </div>

                <template v-else-if="graph">
                    <Card>
                        <CardContent class="pt-6">
                            <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Status</span>
                                    <div class="mt-2">
                                        <Badge :variant="statusBadgeVariant(graph.status)">{{ graph.status }}</Badge>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Tasks</span>
                                    <div class="mt-2 text-sm text-foreground">{{ graph.tasks_completed ?? 0 }}/{{ graph.tasks_total ?? 0 }}</div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Started</span>
                                    <div class="mt-2 text-sm text-foreground">{{ formatDateTime(graph.started_at) }}</div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Finished</span>
                                    <div class="mt-2 text-sm text-foreground">{{ formatDateTime(graph.finished_at) }}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="overflow-hidden">
                        <CardHeader>
                            <CardTitle>Tasks</CardTitle>
                        </CardHeader>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Assigned Profile</TableHead>
                                        <TableHead>Verification</TableHead>
                                        <TableHead class="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="task in tasks"
                                        :key="task.id"
                                        class="cursor-pointer"
                                        @click="$inertia.visit(route('agent.delegation.task', { graphId: graphId, taskId: task.id }))"
                                    >
                                        <TableCell class="font-medium text-foreground">{{ task.name }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="statusBadgeVariant(task.status)">{{ task.status }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground">{{ task.assigned_profile?.name ?? '-' }}</TableCell>
                                        <TableCell>
                                            <Badge v-if="task.verification_status" :variant="statusBadgeVariant(task.verification_status)">{{ task.verification_status }}</Badge>
                                            <span v-else class="text-muted-foreground">-</span>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Link :href="route('agent.delegation.task', { graphId: graphId, taskId: task.id })" @click.stop>
                                                <Button variant="outline" size="sm">View</Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="tasks.length === 0">
                                        <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                                            No tasks in this graph.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                    </Card>

                    <Card>
                        <button
                            class="flex w-full items-center justify-between p-6"
                            @click="showEvents = !showEvents; if (showEvents && events.length === 0) loadEvents()"
                        >
                            <h3 class="text-lg font-semibold text-foreground">Events</h3>
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <span>{{ showEvents ? 'Hide' : 'Show' }}</span>
                                <ChevronDown v-if="!showEvents" class="h-4 w-4" />
                                <ChevronUp v-else class="h-4 w-4" />
                            </div>
                        </button>
                        <CardContent v-if="showEvents" class="max-h-96 overflow-y-auto pt-0">
                            <div v-if="events.length === 0" class="text-sm text-muted-foreground">No events yet.</div>
                            <div v-else class="space-y-3">
                                <div v-for="event in events" :key="event.id" class="rounded-lg border border-border p-4 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-foreground">{{ event.event_type }}</span>
                                        <span class="text-xs text-muted-foreground">{{ formatDateTime(event.event_ts, { second: '2-digit' }) }}</span>
                                    </div>
                                    <pre v-if="event.payload_json" class="mt-3 overflow-x-auto rounded bg-muted p-2 text-xs text-muted-foreground font-mono">{{ JSON.stringify(event.payload_json, null, 2) }}</pre>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
