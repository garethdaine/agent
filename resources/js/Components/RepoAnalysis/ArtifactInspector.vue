<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';

defineProps({
    artifacts: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Artifacts</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2">
            <div v-for="artifact in artifacts" :key="artifact.id" class="rounded border border-border p-3">
                <p class="text-sm font-medium">{{ artifact.artifact_key }}</p>
                <p class="mt-1 text-xs text-muted-foreground">type {{ artifact.artifact_type }}</p>
                <p class="mt-1 text-xs text-muted-foreground">hash {{ artifact.content_hash }}</p>
            </div>
            <p v-if="artifacts.length === 0" class="text-sm text-muted-foreground">No artifacts recorded yet.</p>
        </CardContent>
    </Card>
</template>
