<script setup>
import { computed, ref } from 'vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { choiceOptionsFromQuestion, displayQuestionMarkdown, formatReasoning, normalizeQuestionCategory } from '@/Components/Interrogation/questionPresentation';
import { humanizeDisplayValue } from '@/Components/Interrogation/displayFormatting';

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
const reasoning = computed(() => formatReasoning(props.question?.reasoning ?? ''));
const categoryKey = computed(() => normalizeQuestionCategory(props.question?.category ?? '') || 'general');
const categoryLabel = computed(() => humanizeDisplayValue(categoryKey.value, 'General'));
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4">
        <div v-if="!question" class="flex items-center gap-2 text-sm text-muted-foreground">
            <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            <span>Waiting for runner output...</span>
        </div>

        <template v-else>
            <div class="flex items-center justify-between gap-3">
                <span class="rounded-full border border-border bg-muted/30 px-2.5 py-1 text-xs font-medium text-muted-foreground">
                    {{ categoryLabel }}
                </span>
                <span class="text-xs text-muted-foreground">Progress {{ question.progress_estimate ?? 0 }}%</span>
            </div>

            <MarkdownRenderer
                :markdown="promptMarkdown"
                class="prose prose-sm mt-3 max-w-none text-foreground dark:prose-invert prose-p:my-0 prose-p:text-xl prose-p:leading-8 prose-ul:my-2 prose-ol:my-2 prose-li:my-1 prose-code:rounded prose-code:bg-muted prose-code:px-1 prose-code:py-0.5 prose-code:text-[0.9em]"
            />

            <div class="mt-3 h-2 w-full rounded bg-muted/80">
                <div class="h-2 rounded bg-primary" :style="{ width: `${Math.min(100, Math.max(0, question.progress_estimate ?? 0))}%` }" />
            </div>

            <div class="mt-4 border-t border-border pt-3">
                <button
                    type="button"
                    class="text-sm font-medium text-primary hover:opacity-80"
                    @click="showReasoning = !showReasoning"
                >
                    {{ showReasoning ? 'Hide reasoning' : 'Show reasoning' }}
                </button>

                <div v-if="showReasoning" class="mt-3 rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-sm leading-6 text-foreground">{{ reasoning.summary || 'No reasoning provided.' }}</p>

                    <ul v-if="reasoning.bullets.length > 0" class="mt-3 space-y-2">
                        <li
                            v-for="(item, index) in reasoning.bullets"
                            :key="`${index}-${item}`"
                            class="rounded-md border border-border bg-card px-3 py-2 text-sm leading-6 text-foreground"
                        >
                            {{ item }}
                        </li>
                    </ul>

                    <div v-if="reasoning.paragraphs.length > 1" class="mt-3">
                        <button
                            type="button"
                            class="text-xs font-medium text-primary hover:opacity-80"
                            @click="showFullReasoning = !showFullReasoning"
                        >
                            {{ showFullReasoning ? 'Hide full reasoning' : 'View full reasoning' }}
                        </button>

                        <div v-if="showFullReasoning" class="mt-2 space-y-2 border-l-2 border-border pl-3">
                            <p
                                v-for="(paragraph, index) in reasoning.paragraphs"
                                :key="`${index}-${paragraph}`"
                                class="text-sm leading-6 text-muted-foreground"
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
