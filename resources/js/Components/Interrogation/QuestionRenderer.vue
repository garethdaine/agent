<script setup>
import { computed, ref } from 'vue';
import { choiceOptionsFromQuestion, displayQuestionMarkdown, formatReasoning, renderMarkdownToHtml } from '@/Components/Interrogation/questionPresentation';

const props = defineProps({
    question: {
        type: Object,
        default: null,
    },
});

const showReasoning = ref(false);
const showFullReasoning = ref(false);

const options = computed(() => choiceOptionsFromQuestion(props.question));

const promptMarkdown = computed(() => displayQuestionMarkdown(props.question?.question_text ?? '', options.value));
const promptHtml = computed(() => renderMarkdownToHtml(promptMarkdown.value));

const reasoning = computed(() => formatReasoning(props.question?.reasoning ?? ''));
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div v-if="!question" class="flex items-center gap-2 text-sm text-gray-500">
            <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-indigo-500 border-t-transparent" />
            <span>Waiting for runner output...</span>
        </div>

        <template v-else>
            <div class="flex items-center justify-between gap-3">
                <span class="rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    {{ question.category || 'general' }}
                </span>
                <span class="text-xs text-gray-500">Progress {{ question.progress_estimate ?? 0 }}%</span>
            </div>

            <div
                class="prose prose-sm mt-3 max-w-none text-gray-900 dark:prose-invert dark:text-gray-100 prose-p:my-0 prose-p:text-xl prose-p:leading-8 prose-ul:my-2 prose-ol:my-2 prose-li:my-1 prose-code:rounded prose-code:bg-gray-100 prose-code:px-1 prose-code:py-0.5 prose-code:text-[0.9em] dark:prose-code:bg-gray-700"
                v-html="promptHtml"
            />

            <div class="mt-3 h-2 w-full rounded bg-gray-200 dark:bg-gray-700">
                <div class="h-2 rounded bg-indigo-500" :style="{ width: `${Math.min(100, Math.max(0, question.progress_estimate ?? 0))}%` }" />
            </div>

            <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                <button
                    type="button"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                    @click="showReasoning = !showReasoning"
                >
                    {{ showReasoning ? 'Hide reasoning' : 'Show reasoning' }}
                </button>

                <div v-if="showReasoning" class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                    <p class="text-sm leading-6 text-gray-700 dark:text-gray-200">{{ reasoning.summary || 'No reasoning provided.' }}</p>

                    <ul v-if="reasoning.bullets.length > 0" class="mt-3 space-y-2">
                        <li
                            v-for="(item, index) in reasoning.bullets"
                            :key="`${index}-${item}`"
                            class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm leading-6 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        >
                            {{ item }}
                        </li>
                    </ul>

                    <div v-if="reasoning.paragraphs.length > 1" class="mt-3">
                        <button
                            type="button"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500"
                            @click="showFullReasoning = !showFullReasoning"
                        >
                            {{ showFullReasoning ? 'Hide full reasoning' : 'View full reasoning' }}
                        </button>

                        <div v-if="showFullReasoning" class="mt-2 space-y-2 border-l-2 border-gray-200 pl-3 dark:border-gray-700">
                            <p
                                v-for="(paragraph, index) in reasoning.paragraphs"
                                :key="`${index}-${paragraph}`"
                                class="text-sm leading-6 text-gray-600 dark:text-gray-300"
                            >
                                {{ paragraph }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
