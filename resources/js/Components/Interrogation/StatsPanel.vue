<script setup>
import { computed } from 'vue';
import { isAnswerableQuestionEvent, normalizeQuestionCategory } from '@/Components/Interrogation/questionPresentation';

const props = defineProps({
    session: {
        type: Object,
        required: true,
    },
    events: {
        type: Array,
        default: () => [],
    },
});

const questionCount = computed(() => props.events.filter((event) => isAnswerableQuestionEvent(event)).length);
const answerCount = computed(() => props.events.filter((event) => event.event_type === 'answer').length);

const categories = computed(() => {
    const values = new Set();

    props.events.forEach((event) => {
        if (isAnswerableQuestionEvent(event) && typeof event.payload?.category === 'string' && event.payload.category !== '') {
            const normalized = normalizeQuestionCategory(event.payload.category);
            if (normalized !== '') {
                values.add(normalized);
            }
        }
    });

    return Array.from(values);
});

const latestProgress = computed(() => {
    const questions = props.events.filter((event) => isAnswerableQuestionEvent(event));

    if (questions.length === 0) {
        return 0;
    }

    return Number(questions[questions.length - 1]?.payload?.progress_estimate ?? 0);
});

const elapsedLabel = computed(() => {
    const startedAt = props.session?.started_at;

    if (!startedAt) {
        return 'Not started';
    }

    const ms = Date.now() - new Date(startedAt).getTime();
    if (!Number.isFinite(ms) || ms < 0) {
        return 'Unknown';
    }

    const minutes = Math.floor(ms / 60000);
    const hours = Math.floor(minutes / 60);

    if (hours > 0) {
        return `${hours}h ${minutes % 60}m`;
    }

    return `${minutes}m`;
});

const statusClass = computed(() => {
    const status = String(props.session?.status ?? '').toLowerCase();
    const map = {
        setup: 'bg-muted text-muted-foreground',
        discovering: 'bg-primary/10 text-primary',
        interrogating: 'bg-primary/10 text-primary',
        summarizing: 'bg-warning/10 text-warning',
        planning: 'bg-warning/10 text-warning',
        build_rules: 'bg-warning/10 text-warning',
        build_tasks: 'bg-primary/10 text-primary',
        build_executing: 'bg-primary/10 text-primary',
        paused: 'bg-muted text-muted-foreground',
        completed: 'bg-success/10 text-success',
        failed: 'bg-destructive/10 text-destructive',
    };

    return map[status] ?? 'bg-muted text-muted-foreground';
});

const categoryClass = (index) => {
    const classes = [
        'bg-primary/10 text-primary',
        'bg-warning/10 text-warning',
        'bg-success/10 text-success',
        'bg-destructive/10 text-destructive',
    ];

    return classes[index % classes.length];
};
</script>

<template>
    <div class="space-y-3 rounded-lg border border-border bg-card p-4">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Stats</h3>

        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-muted-foreground">Questions</dt><dd class="font-medium text-foreground">{{ questionCount }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted-foreground">Answers</dt><dd class="font-medium text-foreground">{{ answerCount }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted-foreground">Elapsed</dt><dd class="font-medium text-foreground">{{ elapsedLabel }}</dd></div>
            <div class="flex justify-between items-center">
                <dt class="text-muted-foreground">Status</dt>
                <dd class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium capitalize" :class="statusClass">{{ session.status }}</dd>
            </div>
        </dl>

        <div>
            <div class="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                <span>Progress</span>
                <span>{{ latestProgress }}%</span>
            </div>
            <div class="h-1.5 rounded bg-muted/80">
                <div class="h-1.5 rounded bg-primary" :style="{ width: `${Math.max(0, Math.min(100, latestProgress))}%` }" />
            </div>
        </div>

        <div class="border-t border-border pt-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Categories</p>
            <div v-if="categories.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                <span
                    v-for="(category, index) in categories"
                    :key="category"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                    :class="categoryClass(index)"
                >
                    {{ category }}
                </span>
            </div>
            <p v-else class="mt-1 text-xs text-muted-foreground">No categories yet</p>
        </div>
    </div>
</template>
