<script setup>
import { ref, reactive, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
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
import { GitBranch, Plus, ChevronLeft, ChevronRight, Pencil, Copy, Trash2, RotateCcw } from 'lucide-vue-next';

const graphs = ref([]);
const loading = ref(true);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });
const confirmingDeleteId = ref(null);

const filters = reactive({
    status: '',
    page: 1,
});

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/delegation/graphs', { params: filters });
        graphs.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load delegation graphs.';
    } finally {
        loading.value = false;
    }
};

const setPage = async (page) => {
    filters.page = page;
    await load();
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
    };
    return variants[status] || 'secondary';
};

const canEdit = (graph) => graph.status === 'draft';
const canDelete = (graph) => ['succeeded', 'failed', 'partial', 'cancelled'].includes(graph.status) && !graph.deleted_at;
const isDeleted = (graph) => !!graph.deleted_at;

const editGraph = (graph) => {
    router.visit(route('agent.delegation.graphs.builder.edit', { graphId: graph.id }));
};

const cloneGraph = async (graph) => {
    try {
        const { data } = await axios.post(`/agent/api/v1/delegation/graphs/${graph.id}/clone`);
        router.visit(route('agent.delegation.show', data.data.id));
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to clone graph.';
    }
};

const deleteGraph = async (graph) => {
    try {
        await axios.delete(`/agent/api/v1/delegation/graphs/${graph.id}`);
        confirmingDeleteId.value = null;
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete graph.';
        confirmingDeleteId.value = null;
    }
};

const restoreGraph = async (graph) => {
    try {
        await axios.post(`/agent/api/v1/delegation/graphs/${graph.id}/restore`);
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restore graph.';
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Delegation Graphs">
        <Head title="Delegation Graphs" />

        <template #header>
            <div class="flex items-center justify-between gap-4 min-w-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <GitBranch class="h-4 w-4 text-primary" />
                    </div>
                    <div class="flex items-center gap-2 min-w-0">
                        <h2 class="text-base font-semibold text-foreground truncate">Delegation</h2>
                        <HelpHint
                            ui-key="delegation.graphs"
                            short-text="Understand graph lifecycle, task states, and verification checkpoints."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Link :href="route('agent.delegation.graphs.builder')">
                        <Button variant="outline" size="sm">
                            Visual builder
                        </Button>
                    </Link>
                    <Link :href="route('agent.delegation.create')">
                        <Button size="sm">
                            <Plus class="mr-1.5 h-4 w-4" />
                            Create Graph
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <nav class="flex gap-1 border-b border-border pb-2 mb-4" aria-label="Delegation sections">
            <Link
                :href="route('agent.delegation.index')"
                class="border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                :class="route().current('agent.delegation.index') ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
            >
                Graphs
            </Link>
            <Link
                :href="route('agent.delegation.profiles.index')"
                class="border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                :class="route().current('agent.delegation.profiles.*') ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
            >
                Delegatee Profiles
            </Link>
        </nav>

        <div class="space-y-4">
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <Button
                        :variant="filters.status === '' ? 'default' : 'outline'"
                        size="sm"
                        @click="filters.status = ''; filters.page = 1; load()"
                    >
                        All
                    </Button>
                    <Button
                        :variant="filters.status === 'running' ? 'default' : 'outline'"
                        size="sm"
                        @click="filters.status = 'running'; filters.page = 1; load()"
                    >
                        Active
                    </Button>
                    <Button
                        :variant="filters.status === 'succeeded' ? 'default' : 'outline'"
                        size="sm"
                        @click="filters.status = 'succeeded'; filters.page = 1; load()"
                    >
                        Completed
                    </Button>
                    <Button
                        :variant="filters.status === 'failed' ? 'default' : 'outline'"
                        size="sm"
                        @click="filters.status = 'failed'; filters.page = 1; load()"
                    >
                        Failed
                    </Button>
                </div>

                <p v-if="error" class="rounded-md border border-destructive bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </p>

                <Card>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Tasks</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="loading">
                                    <TableCell colspan="5" class="py-8">
                                        <div class="flex flex-col items-center justify-center gap-4">
                                            <Skeleton class="h-4 w-48" />
                                            <Skeleton class="h-4 w-32" />
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow
                                    v-for="graph in graphs"
                                    :key="graph.id"
                                    class="cursor-pointer"
                                    @click="$inertia.visit(route('agent.delegation.show', graph.id))"
                                >
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <GitBranch class="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <div class="font-medium text-foreground">{{ graph.name }}</div>
                                                <div v-if="graph.description" class="text-xs text-muted-foreground">{{ graph.description }}</div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="statusBadgeVariant(graph.status)">{{ graph.status }}</Badge>
                                        <Badge v-if="isDeleted(graph)" variant="outline" class="ml-1">deleted</Badge>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ graph.task_counts?.completed ?? 0 }}/{{ graph.task_counts?.total ?? 0 }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground text-xs">{{ graph.created_at }}</TableCell>
                                    <TableCell class="text-right" @click.stop>
                                        <div class="flex items-center justify-end gap-1">
                                            <Button
                                                v-if="canEdit(graph)"
                                                variant="ghost"
                                                size="sm"
                                                title="Edit"
                                                @click="editGraph(graph)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                title="Clone"
                                                @click="cloneGraph(graph)"
                                            >
                                                <Copy class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button
                                                v-if="isDeleted(graph)"
                                                variant="ghost"
                                                size="sm"
                                                title="Restore"
                                                @click="restoreGraph(graph)"
                                            >
                                                <RotateCcw class="h-3.5 w-3.5" />
                                            </Button>
                                            <template v-if="canDelete(graph)">
                                                <Button
                                                    v-if="confirmingDeleteId !== graph.id"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-destructive hover:text-destructive"
                                                    title="Delete"
                                                    @click="confirmingDeleteId = graph.id"
                                                >
                                                    <Trash2 class="h-3.5 w-3.5" />
                                                </Button>
                                                <template v-else>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        @click="deleteGraph(graph)"
                                                    >
                                                        Confirm
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        @click="confirmingDeleteId = null"
                                                    >
                                                        Cancel
                                                    </Button>
                                                </template>
                                            </template>
                                            <Link :href="route('agent.delegation.show', graph.id)">
                                                <Button variant="outline" size="sm">View</Button>
                                            </Link>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!loading && graphs.length === 0">
                                    <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                                        No delegation graphs found.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
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
                            <ChevronLeft class="mr-1 h-4 w-4" />
                            Prev
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="meta.current_page >= meta.last_page"
                            @click="setPage(meta.current_page + 1)"
                        >
                            Next
                            <ChevronRight class="ml-1 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
