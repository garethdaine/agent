<script setup>
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { computed } from 'vue';
import { humanizeDisplayValue } from '@/Components/Interrogation/displayFormatting';

const props = defineProps({
    session: {
        type: Object,
        required: true,
    },
    latestDiscoveryEvent: {
        type: Object,
        default: null,
    },
    activityEvents: {
        type: Array,
        default: () => [],
    },
    phaseLabel: {
        type: String,
        default: 'Discovery',
    },
});

const buildEventMessage = (event) => {
    const payload = event?.payload ?? {};
    const message = String(payload?.message ?? payload?.title ?? payload?.notice ?? '').trim();
    if (message !== '') {
        return message;
    }

    if (typeof payload?.question_text === 'string' && payload.question_text.trim() !== '') {
        return `Question generated: ${payload.question_text.trim()}`;
    }

    const type = String(event?.event_type ?? '').trim();
    if (type === 'error') {
        return 'Runner reported an error.';
    }

    if (type === 'system') {
        return 'Runner status updated.';
    }

    return '';
};

const normalizedActivity = computed(() => {
    const sourceEvents = Array.isArray(props.activityEvents) && props.activityEvents.length > 0
        ? props.activityEvents
        : (props.latestDiscoveryEvent ? [props.latestDiscoveryEvent] : []);

    const baseEntries = sourceEvents
        .map((event) => {
            const message = buildEventMessage(event);
            if (message === '') {
                return null;
            }

            const at = String(event?.payload?.at ?? event?.event_ts ?? event?.created_at ?? '').trim();
            const atLabel = at !== '' ? new Date(at).toLocaleTimeString() : '';

            return {
                sequence: Number(event?.sequence ?? 0),
                message,
                at,
                atLabel,
                eventType: String(event?.event_type ?? '').trim().toLowerCase(),
            };
        })
        .filter((entry) => entry !== null)
        .sort((a, b) => Number(a.sequence ?? 0) - Number(b.sequence ?? 0));

    const collapsed = [];
    baseEntries.forEach((entry) => {
        const previous = collapsed[collapsed.length - 1];
        if (previous && previous.message === entry.message && previous.eventType === entry.eventType) {
            previous.count += 1;
            previous.sequence = entry.sequence;
            previous.at = entry.at;
            previous.atLabel = entry.atLabel;
            return;
        }

        collapsed.push({
            ...entry,
            count: 1,
        });
    });

    return collapsed.slice(-8);
});

const displayMessage = computed(() => {
    const latest = normalizedActivity.value[normalizedActivity.value.length - 1];
    const fallback = props.phaseLabel.toLowerCase() === 'interrogation'
        ? 'Generating the next question...'
        : 'Analyzing repository and gathering context...';
    const raw = String(latest?.message ?? fallback).trim();

    if (raw.length <= 1200) {
        return raw;
    }

    return `${raw.slice(0, 1200).trimEnd()}\n\n… [truncated]`;
});

const statusLabel = computed(() => humanizeDisplayValue(props.session?.status ?? '', 'Unknown'));

const showRecentActivity = computed(() => {
    const phase = String(props.phaseLabel ?? '').toLowerCase();
    return !['discovery', 'interrogation'].includes(phase);
});
</script>

<template>
    <div class="rounded-lg border border-primary/30 bg-primary/5 p-4">
        <div class="flex items-start justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ phaseLabel }} Status</p>
            <span class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">
                Live
            </span>
        </div>
        <h3 class="mt-1 text-base font-semibold text-foreground">{{ statusLabel }}</h3>
        <MarkdownRenderer
            :markdown="displayMessage"
            class="prose prose-sm mt-2 max-w-none text-foreground dark:prose-invert prose-p:my-2 prose-headings:my-2 prose-ul:my-2 prose-ol:my-2 prose-li:my-1 prose-strong:text-foreground prose-code:rounded prose-code:bg-muted prose-code:px-1 prose-code:py-0.5"
        />

        <div v-if="showRecentActivity && normalizedActivity.length > 0" class="mt-3 border-t border-primary/20 pt-3">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-primary/80">Recent Activity</p>
            <ul class="mt-2 space-y-2">
                <li
                    v-for="activity in normalizedActivity"
                    :key="`${activity.sequence}-${activity.message}`"
                    class="rounded-md border border-primary/20 bg-card/70 px-2.5 py-1.5"
                >
                    <div class="flex items-start justify-between gap-2">
                        <MarkdownRenderer
                            :markdown="activity.message"
                            class="prose prose-sm max-w-none text-foreground dark:prose-invert prose-p:my-0 prose-headings:my-0 prose-ul:my-1 prose-ol:my-1 prose-li:my-0 prose-strong:text-foreground prose-code:rounded prose-code:bg-muted prose-code:px-1 prose-code:py-0.5 prose-pre:my-1 prose-pre:max-h-44 prose-pre:overflow-auto"
                        />
                        <span v-if="activity.count > 1" class="shrink-0 rounded-full border border-primary/30 bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary">
                            x{{ activity.count }}
                        </span>
                    </div>
                    <p v-if="activity.atLabel" class="mt-1 text-[10px] text-muted-foreground">{{ activity.atLabel }}</p>
                </li>
            </ul>
        </div>
    </div>
</template>
