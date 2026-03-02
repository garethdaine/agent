<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';

defineProps({
    reports: {
        type: Array,
        default: () => [],
    },
    canExport: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['export-latest']);
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between gap-3">
                <CardTitle>Reports</CardTitle>
                <Button v-if="canExport" variant="outline" size="sm" @click="emit('export-latest')">
                    Export
                </Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-3">
            <div v-for="report in reports" :key="report.id" class="rounded border border-border p-3">
                <p class="text-sm font-medium">{{ report.report_version }} · {{ report.status }}</p>
                <p class="mt-1 text-xs text-muted-foreground">hash {{ report.report_hash }}</p>
                <p class="mt-1 text-xs text-muted-foreground">generated {{ report.generated_at ?? '—' }}</p>
            </div>
            <p v-if="reports.length === 0" class="text-sm text-muted-foreground">No reports generated yet.</p>
        </CardContent>
    </Card>
</template>
