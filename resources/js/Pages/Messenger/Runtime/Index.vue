<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Button from '@/Components/ui/Button.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, Play, Eye } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    sessions: Object,
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
};

const getStatusBadgeVariant = (status) => {
    const variants = {
        pending: 'secondary',
        active: 'default',
        completed: 'outline',
        failed: 'destructive',
        stopped: 'outline',
    };
    return variants[status] || 'outline';
};

const getModeBadgeVariant = (mode) => {
    const variants = {
        safe: 'secondary',
        standard: 'default',
        full: 'destructive',
    };
    return variants[mode] || 'outline';
};

const refresh = () => {
    router.reload({ only: ['sessions'] });
};
</script>

<template>
    <AppLayout title="Sessions">
        <Head title="Sessions" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Play class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">Sessions</h2>
                        <HelpHint
                            ui-key="sessions.overview"
                            short-text="View and manage active agent runtime sessions."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Button variant="outline" size="sm" @click="refresh">
                    <RefreshCw class="h-4 w-4 mr-1" />
                    Refresh
                </Button>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Sessions</CardTitle>
                        <CardDescription>
                            {{ sessions.total || 0 }} session(s)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Mode</TableHead>
                                    <TableHead>Turns</TableHead>
                                    <TableHead>Started</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="session in sessions.data" :key="session.id">
                                    <TableCell>
                                        <Link
                                            :href="`/messenger/runtime/${session.id}`"
                                            class="font-medium text-primary hover:underline"
                                        >
                                            {{ session.title || 'Untitled Session' }}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusBadgeVariant(session.status)">
                                            {{ session.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getModeBadgeVariant(session.mode)">
                                            {{ session.mode }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {{ session.turns?.length || 0 }}
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground whitespace-nowrap">
                                        {{ formatDate(session.started_at) }}
                                    </TableCell>
                                    <TableCell>
                                        <Link :href="`/messenger/runtime/${session.id}`">
                                            <Button variant="outline" size="sm">
                                                <Eye class="h-4 w-4 mr-1" />
                                                View
                                            </Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!sessions.data?.length">
                                    <TableCell colspan="6" class="text-center text-muted-foreground py-8">
                                        No runtime sessions yet.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- Pagination -->
                        <div v-if="sessions.links?.length > 3" class="mt-4 flex flex-wrap items-center justify-center gap-1">
                            <template v-for="(link, index) in sessions.links" :key="index">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-1 text-sm rounded-md border"
                                    :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input hover:bg-muted'"
                                    v-html="link.label"
                                />
                                <span
                                    v-else
                                    class="px-3 py-1 text-sm text-muted-foreground"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
