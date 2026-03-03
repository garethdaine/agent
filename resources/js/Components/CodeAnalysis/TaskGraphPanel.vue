<script setup>
import { computed } from 'vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    canRetryFailedTask: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['retry-task']);

const normalizeStatus = (status) => String(status ?? '').trim().toLowerCase();

const normalizeAnalyzerLabel = (value) => String(value ?? '').replace(/^ai_repo_overview$/, 'ai_overview');
const normalizeTaskKeyLabel = (value) => String(value ?? '').replace(/^ai_repo_overview:/, 'ai_overview:');

const dependencySummary = (task) => {
    const dependencies = Array.isArray(task?.depends_on_json)
        ? task.depends_on_json
            .filter(Boolean)
            .map((dependency) => normalizeAnalyzerLabel(dependency))
        : [];
    if (dependencies.length === 0) {
        return 'none';
    }

    return dependencies.slice(0, 3).join(', ') + (dependencies.length > 3 ? ` +${dependencies.length - 3} more` : '');
};

const taskProgress = computed(() => {
    const summary = {
        total: props.tasks.length,
        pending: 0,
        running: 0,
        completed: 0,
        failed: 0,
        skipped: 0,
        finished: 0,
        percent: 0,
    };

    for (const task of props.tasks) {
        const status = normalizeStatus(task?.status);
        if (status === 'completed') {
            summary.completed += 1;
            summary.finished += 1;
            continue;
        }

        if (status === 'failed') {
            summary.failed += 1;
            summary.finished += 1;
            continue;
        }

        if (status === 'skipped') {
            summary.skipped += 1;
            summary.finished += 1;
            continue;
        }

        if (['running', 'starting', 'in_progress', 'retrying'].includes(status)) {
            summary.running += 1;
            continue;
        }

        summary.pending += 1;
    }

    if (summary.total > 0) {
        summary.percent = Math.round((summary.finished / summary.total) * 100);
    }

    return summary;
});

const isActiveStatus = (status) => ['running', 'starting', 'in_progress', 'retrying'].includes(normalizeStatus(status));

const statusLabel = (status) => {
    const normalized = normalizeStatus(status);
    if (normalized === '') {
        return 'unknown';
    }

    return normalized.replace(/_/g, ' ');
};

const statusBadgeClass = (status) => {
    const normalized = normalizeStatus(status);

    if (normalized === 'completed') {
        return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (normalized === 'failed') {
        return 'border-destructive/50 bg-destructive/10 text-destructive';
    }

    if (normalized === 'skipped') {
        return 'border-border bg-muted/30 text-muted-foreground';
    }

    if (isActiveStatus(normalized)) {
        return 'border-primary/40 bg-primary/10 text-primary';
    }

    return 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300';
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Task Graph</CardTitle>
        </CardHeader>
        <CardContent>
            <p class="mb-3 text-xs text-muted-foreground">
                Deterministic analyzer DAG planned in phase 2. Each row is one analyzer task with dependency ordering.
            </p>
            <div v-if="tasks.length > 0" class="mb-4 space-y-2">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <Badge variant="outline">{{ taskProgress.total }} total</Badge>
                    <Badge variant="outline" class="border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">{{ taskProgress.completed }} completed</Badge>
                    <Badge variant="outline" class="border-primary/40 bg-primary/10 text-primary">{{ taskProgress.running }} running</Badge>
                    <Badge variant="outline" class="border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300">{{ taskProgress.pending }} pending</Badge>
                    <Badge
                        v-if="taskProgress.failed > 0"
                        variant="outline"
                        class="border-destructive/50 bg-destructive/10 text-destructive"
                    >
                        {{ taskProgress.failed }} failed
                    </Badge>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-muted/60">
                    <div
                        class="h-full rounded-full bg-primary transition-all duration-300"
                        :style="{ width: `${taskProgress.percent}%` }"
                    />
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ taskProgress.finished }} of {{ taskProgress.total }} tasks finished ({{ taskProgress.percent }}%).
                </p>
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Task</TableHead>
                        <TableHead>Analyzer</TableHead>
                        <TableHead>Depends On</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Attempts</TableHead>
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="loading && tasks.length === 0">
                        <TableCell colspan="6" class="text-center text-muted-foreground">
                            <span class="inline-flex items-center gap-2">
                                <Spinner size="sm" />
                                Loading tasks…
                            </span>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="task in tasks" :key="task.id ?? task.task_key">
                        <TableCell class="font-medium">{{ normalizeTaskKeyLabel(task.task_key) }}</TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ normalizeAnalyzerLabel(task.analyzer_name) }}</TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ dependencySummary(task) }}</TableCell>
                        <TableCell class="text-xs">
                            <div class="space-y-1">
                                <Badge variant="outline" :class="statusBadgeClass(task.status)">
                                    <Spinner v-if="isActiveStatus(task.status)" size="sm" class="mr-1" />
                                    {{ statusLabel(task.status) }}
                                </Badge>
                                <p
                                    v-if="task.error_summary"
                                    class="max-w-xs truncate text-[11px] text-destructive"
                                    :title="task.error_summary"
                                >
                                    {{ task.error_summary }}
                                </p>
                            </div>
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ task.attempt_count ?? 0 }}</TableCell>
                        <TableCell class="text-right">
                            <Button
                                v-if="canRetryFailedTask && task.status === 'failed'"
                                variant="outline"
                                size="sm"
                                @click="emit('retry-task', task.id)"
                            >
                                Retry Task
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="!loading && tasks.length === 0">
                        <TableCell colspan="6" class="text-center text-muted-foreground">No planned tasks yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
