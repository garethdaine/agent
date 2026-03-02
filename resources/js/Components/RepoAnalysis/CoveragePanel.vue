<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const latestCoverageEvent = () => {
    const coverageEvents = props.events.filter((event) => event?.event_type === 'coverage_gate');
    return coverageEvents.length > 0 ? coverageEvents[coverageEvents.length - 1] : null;
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Coverage Gate</CardTitle>
        </CardHeader>
        <CardContent>
            <template v-if="latestCoverageEvent()">
                <p class="text-sm font-medium">Latest coverage event at sequence {{ latestCoverageEvent().sequence }}</p>
                <pre class="mt-2 max-h-48 overflow-auto rounded bg-muted p-3 text-xs">{{ JSON.stringify(latestCoverageEvent().payload ?? {}, null, 2) }}</pre>
            </template>
            <p v-else class="text-sm text-muted-foreground">Coverage events will appear after validation begins.</p>
        </CardContent>
    </Card>
</template>
