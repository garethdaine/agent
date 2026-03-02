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
    session: {
        type: Object,
        default: null,
    },
});

const latestCoverageEvent = () => {
    const coverageEvents = props.events.filter((event) => ['coverage_validated', 'coverage_gate'].includes(String(event?.event_type ?? '')));
    return coverageEvents.length > 0 ? coverageEvents[coverageEvents.length - 1] : null;
};

const coveragePayload = () => {
    const event = latestCoverageEvent();
    if (event?.payload && typeof event.payload === 'object') {
        return event.payload;
    }

    const sessionCoverage = props.session?.report_summary?.coverage;
    if (sessionCoverage && typeof sessionCoverage === 'object') {
        return sessionCoverage;
    }

    return null;
};

const coverageCodes = (items) => {
    if (!Array.isArray(items)) {
        return [];
    }

    return items
        .map((item) => item?.code)
        .filter((code) => typeof code === 'string' && code.length > 0);
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Coverage Gate</CardTitle>
        </CardHeader>
        <CardContent>
            <p class="mb-3 text-xs text-muted-foreground">
                Phase 4 completion gate. Session cannot finish unless required artifact classes exist and critical task failures are resolved.
            </p>

            <template v-if="coveragePayload()">
                <p class="text-sm font-medium">
                    Gate status:
                    <span :class="coveragePayload().passed ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'">
                        {{ coveragePayload().passed ? 'passed' : 'blocked' }}
                    </span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Tasks completed {{ coveragePayload().completed_task_count ?? 0 }} / {{ coveragePayload().task_count ?? 0 }}
                </p>
                <p v-if="coverageCodes(coveragePayload().blocking_failures).length > 0" class="mt-2 text-xs text-destructive">
                    Blocking: {{ coverageCodes(coveragePayload().blocking_failures).join(', ') }}
                </p>
                <p v-if="coverageCodes(coveragePayload().warnings).length > 0" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                    Warnings: {{ coverageCodes(coveragePayload().warnings).join(', ') }}
                </p>
                <pre class="mt-2 max-h-48 overflow-auto rounded bg-muted p-3 text-xs">{{ JSON.stringify(coveragePayload(), null, 2) }}</pre>
            </template>
            <p v-else class="text-sm text-muted-foreground">Coverage events will appear after validation begins.</p>
        </CardContent>
    </Card>
</template>
