<script setup>
import { ref, reactive, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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
import { GitBranch, Plus, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const graphs = ref([]);
const loading = ref(true);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

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

onMounted(load);
</script>

<template>
    <AppLayout title="Delegation Graphs">
        <Head title="Delegation Graphs" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">Delegation Graphs</h2>
                <Link :href="route('agent.delegation.create')">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Create Graph
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px] space-y-4">
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
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ graph.tasks_completed ?? 0 }}/{{ graph.tasks_total ?? 0 }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground text-xs">{{ graph.created_at }}</TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('agent.delegation.show', graph.id)" @click.stop>
                                            <Button variant="outline" size="sm">View</Button>
                                        </Link>
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
