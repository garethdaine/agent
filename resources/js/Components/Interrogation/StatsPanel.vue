<script setup>
import { computed } from 'vue';

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

const questionCount = computed(() => props.events.filter((event) => event.event_type === 'question').length);
const answerCount = computed(() => props.events.filter((event) => event.event_type === 'answer').length);

const categories = computed(() => {
    const values = new Set();

    props.events.forEach((event) => {
        if (event.event_type === 'question' && typeof event.payload?.category === 'string' && event.payload.category !== '') {
            values.add(event.payload.category);
        }
    });

    return Array.from(values);
});

const latestProgress = computed(() => {
    const questions = props.events.filter((event) => event.event_type === 'question');

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
</script>

<template>
    <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-200">Stats</h3>

        <dl class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
            <div class="flex justify-between"><dt>Questions</dt><dd>{{ questionCount }}</dd></div>
            <div class="flex justify-between"><dt>Answers</dt><dd>{{ answerCount }}</dd></div>
            <div class="flex justify-between"><dt>Elapsed</dt><dd>{{ elapsedLabel }}</dd></div>
            <div class="flex justify-between"><dt>Status</dt><dd>{{ session.status }}</dd></div>
        </dl>

        <div>
            <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                <span>Progress</span>
                <span>{{ latestProgress }}%</span>
            </div>
            <div class="h-2 rounded bg-gray-200 dark:bg-gray-700">
                <div class="h-2 rounded bg-indigo-500" :style="{ width: `${Math.max(0, Math.min(100, latestProgress))}%` }" />
            </div>
        </div>

        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Categories</p>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ categories.length > 0 ? categories.join(', ') : 'No categories yet' }}</p>
        </div>
    </div>
</template>
