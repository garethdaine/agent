<script setup>
import { ref, onMounted } from 'vue';
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
import { ArrowLeft, ClipboardCheck, GitBranch } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import { formatDateTime } from '@/Utils/formatDate';

const props = defineProps({
    graphId: {
        type: Number,
        required: true,
    },
    taskId: {
        type: Number,
        required: true,
    },
});

const task = ref(null);
const loading = ref(true);
const error = ref('');

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}/tasks/${props.taskId}`);
        task.value = data.data;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load task.';
    } finally {
        loading.value = false;
    }
};

const statusBadgeVariant = (status) => {
    const variants = {
        pending: 'outline',
        blocked: 'secondary',
        ready: 'default',
        assigned: 'default',
        running: 'secondary',
        verifying: 'default',
        succeeded: 'default',
        failed: 'destructive',
        cancelled: 'outline',
        passed: 'default',
        skipped: 'outline',
    };
    return variants[status] || 'secondary';
};

const hasPendingApproval = () => {
    if (!task.value?.verification_results) return false;
    return task.value.verification_results.some(r => r.step_type === 'human_approval' && r.verdict === 'pending');
};

onMounted(load);
</script>

<template>
    <AppLayout title="Task Detail">
        <Head :title="task?.name ?? 'Task Detail'" />

        <template #header>
            <div class="flex items-center justify-between gap-4 min-w-0">
                <div class="flex items-center gap-3 min-w-0">
                    <Link :href="route('agent.delegation.show', graphId)" class="shrink-0">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <GitBranch class="h-4 w-4 text-primary" />
                    </div>
                    <div class="flex items-center gap-2 min-w-0">
                        <h2 class="text-base font-semibold text-foreground truncate">{{ task?.name ?? 'Loading...' }}</h2>
                        <HelpHint
                            ui-key="delegation.task"
                            short-text="Inspect task assignment details and verification status."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link
                    v-if="hasPendingApproval()"
                    :href="route('agent.delegation.task.approve', { graphId: graphId, taskId: taskId })"
                >
                    <Button>
                        <ClipboardCheck class="mr-2 h-4 w-4" />
                        Review & Approve
                    </Button>
                </Link>
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

                <template v-else-if="task">
                    <Card>
                        <CardContent class="pt-6">
                            <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Status</span>
                                    <div class="mt-2">
                                        <Badge :variant="statusBadgeVariant(task.status)">{{ task.status }}</Badge>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Assigned Profile</span>
                                    <div class="mt-2 text-sm text-foreground">{{ task.assigned_profile?.name ?? '-' }}</div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Started</span>
                                    <div class="mt-2 text-sm text-foreground">{{ formatDateTime(task.started_at) }}</div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium uppercase text-muted-foreground">Finished</span>
                                    <div class="mt-2 text-sm text-foreground">{{ formatDateTime(task.finished_at) }}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Contract</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <pre class="overflow-x-auto rounded-lg bg-muted p-4 text-xs text-foreground font-mono">{{ JSON.stringify(task.contract_json, null, 2) }}</pre>
                        </CardContent>
                    </Card>

                    <Card class="overflow-hidden">
                        <CardHeader>
                            <CardTitle>Attempts</CardTitle>
                        </CardHeader>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>#</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Profile</TableHead>
                                        <TableHead>Duration</TableHead>
                                        <TableHead>Started</TableHead>
                                        <TableHead>Finished</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="attempt in task.attempts" :key="attempt.id">
                                        <TableCell class="font-medium text-foreground">{{ attempt.attempt_number }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="statusBadgeVariant(attempt.status)">{{ attempt.status }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground">{{ attempt.profile?.name ?? '-' }}</TableCell>
                                        <TableCell class="text-muted-foreground">{{ attempt.duration_ms ? `${attempt.duration_ms}ms` : '-' }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ formatDateTime(attempt.started_at) }}</TableCell>
                                        <TableCell class="text-xs text-muted-foreground">{{ formatDateTime(attempt.finished_at) }}</TableCell>
                                    </TableRow>
                                    <TableRow v-if="!task.attempts || task.attempts.length === 0">
                                        <TableCell colspan="6" class="py-8 text-center text-muted-foreground">
                                            No attempts yet.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Verification History</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-for="result in task.verification_results" :key="result.id" class="rounded-lg border border-border p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-foreground">{{ result.step_type }}</span>
                                        <span class="text-xs text-muted-foreground">(Step {{ result.step_order }})</span>
                                    </div>
                                    <Badge :variant="statusBadgeVariant(result.verdict)">{{ result.verdict }}</Badge>
                                </div>
                                <div v-if="result.evidence_json" class="mt-3">
                                    <span class="text-xs font-medium text-muted-foreground">Evidence:</span>
                                    <pre class="mt-2 overflow-x-auto rounded-lg bg-muted p-3 text-xs text-muted-foreground font-mono">{{ JSON.stringify(result.evidence_json, null, 2) }}</pre>
                                </div>
                            </div>
                            <div v-if="!task.verification_results || task.verification_results.length === 0" class="text-sm text-muted-foreground">
                                No verification results yet.
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
