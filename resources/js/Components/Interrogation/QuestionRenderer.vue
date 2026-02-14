<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    question: {
        type: Object,
        default: null,
    },
});

const showReasoning = ref(false);

const options = computed(() => {
    const raw = props.question?.options;

    return Array.isArray(raw) ? raw : [];
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div v-if="!question" class="text-sm text-gray-500">No question yet. Waiting for runner output.</div>
        <template v-else>
            <div class="flex items-start justify-between gap-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ question.question_text }}</h3>
                <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ question.category || 'general' }}</span>
            </div>

            <div v-if="options.length > 0" class="mt-3 space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Options</p>
                <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                    <li v-for="(option, index) in options" :key="`${index}-${option}`">- {{ option }}</li>
                </ul>
            </div>

            <div class="mt-3">
                <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-500" @click="showReasoning = !showReasoning">
                    {{ showReasoning ? 'Hide' : 'Show' }} reasoning
                </button>
                <p v-if="showReasoning" class="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">{{ question.reasoning || 'No reasoning provided.' }}</p>
            </div>

            <div class="mt-4">
                <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                    <span>Progress estimate</span>
                    <span>{{ question.progress_estimate ?? 0 }}%</span>
                </div>
                <div class="h-2 w-full rounded bg-gray-200 dark:bg-gray-700">
                    <div class="h-2 rounded bg-indigo-500" :style="{ width: `${Math.min(100, Math.max(0, question.progress_estimate ?? 0))}%` }" />
                </div>
            </div>
        </template>
    </div>
</template>
