<script setup>
import { computed } from 'vue';
import { isAnswerableQuestionEvent, shortQuestionText } from '@/Components/Interrogation/questionPresentation';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    selectedQuestionId: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['select-question']);

const qaPairs = computed(() => {
    const questions = props.events
        .filter((event) => isAnswerableQuestionEvent(event))
        .sort((a, b) => Number(a.sequence ?? 0) - Number(b.sequence ?? 0));
    const answers = props.events
        .filter((event) => event.event_type === 'answer')
        .sort((a, b) => Number(a.sequence ?? 0) - Number(b.sequence ?? 0));

    const latestQuestionByKey = new Map();
    for (const question of questions) {
        const questionId = String(question?.payload?.question_id ?? '').trim();
        const key = questionId !== '' ? `qid:${questionId}` : `event:${question.id}`;
        latestQuestionByKey.set(key, question);
    }

    const latestAnswerByQuestionId = new Map();
    for (const answer of answers) {
        const questionId = String(answer?.payload?.question_id ?? '').trim();
        if (questionId !== '') {
            latestAnswerByQuestionId.set(questionId, answer);
        }
    }

    return Array.from(latestQuestionByKey.values()).map((question) => {
        const questionId = String(question?.payload?.question_id ?? '');
        const answer = questionId !== '' ? latestAnswerByQuestionId.get(questionId) ?? null : null;
        const unresolved = answer === null;

        return {
            question,
            answer,
            unresolved,
            questionPreview: shortQuestionText(String(question?.payload?.question_text ?? '')),
        };
    }).sort((a, b) => {
        if (a.unresolved !== b.unresolved) {
            return a.unresolved ? -1 : 1;
        }

        return Number(b.question?.sequence ?? 0) - Number(a.question?.sequence ?? 0);
    });
});

const unansweredCount = computed(() => qaPairs.value.filter((pair) => pair.unresolved).length);

const answerPreview = (answerPayload) => {
    if (!answerPayload || typeof answerPayload !== 'object') {
        return 'No answer yet';
    }

    if (Array.isArray(answerPayload.selected_options) && answerPayload.selected_options.length > 0) {
        return answerPayload.selected_options.join(', ');
    }

    return answerPayload.answer_text || answerPayload.selected_option || answerPayload.skip_reason || 'No answer yet';
};
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Q&A History</h3>
            <span class="text-xs text-muted-foreground">{{ unansweredCount }} unanswered</span>
        </div>

        <div class="max-h-[32rem] space-y-3 overflow-auto pr-1">
            <div
                v-for="pair in qaPairs"
                :key="pair.question.id"
                class="rounded border p-3 transition-colors"
                :class="String(pair.question.payload?.question_id ?? '') === selectedQuestionId
                    ? 'border-primary/40 bg-primary/10'
                    : 'border-border bg-card'"
            >
                <p class="text-xs uppercase tracking-wide text-muted-foreground">Question</p>
                <p class="mt-1 text-sm text-foreground">{{ pair.questionPreview }}</p>

                <p class="mt-3 text-xs uppercase tracking-wide text-muted-foreground">Answer</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ answerPreview(pair.answer?.payload) }}</p>

                <button
                    type="button"
                    class="mt-3 text-xs font-medium text-primary hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!(pair.question.payload?.question_id)"
                    @click="emit('select-question', pair.question.payload?.question_id || '')"
                >
                    Answer/revise this question
                </button>
            </div>

            <p v-if="qaPairs.length === 0" class="text-sm text-muted-foreground">No Q&A history yet.</p>
        </div>
    </div>
</template>
