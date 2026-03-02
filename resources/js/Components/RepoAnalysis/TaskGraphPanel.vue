<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';

defineProps({
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
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Task Graph</CardTitle>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Task</TableHead>
                        <TableHead>Analyzer</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Attempts</TableHead>
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="loading">
                        <TableCell colspan="5" class="text-center text-muted-foreground">Loading tasks…</TableCell>
                    </TableRow>
                    <TableRow v-for="task in tasks" :key="task.id">
                        <TableCell class="font-medium">{{ task.task_key }}</TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ task.analyzer_name }}</TableCell>
                        <TableCell class="text-xs">{{ task.status }}</TableCell>
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
                        <TableCell colspan="5" class="text-center text-muted-foreground">No planned tasks yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
