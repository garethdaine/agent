<script setup>
import { computed } from 'vue';

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

const emit = defineEmits(['edit-question']);

const qaPairs = computed(() => {
    const questions = props.events.filter((event) => event.event_type === 'question');
    const answers = props.events.filter((event) => event.event_type === 'answer');

    return questions.map((question) => {
        const questionId = String(question?.payload?.question_id ?? '');
        const answer = [...answers].reverse().find((candidate) => {
            const candidateId = String(candidate?.payload?.question_id ?? '');

            return questionId !== '' && candidateId === questionId;
        });

        return {
            question,
            answer,
        };
    });
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-200">Q&A History</h3>

        <div class="max-h-[32rem] space-y-3 overflow-auto pr-1">
            <div v-for="pair in qaPairs" :key="pair.question.id" class="rounded border border-gray-200 p-3 dark:border-gray-700">
                <p class="text-xs uppercase tracking-wide text-gray-500">Question</p>
                <p class="mt-1 text-sm text-gray-800 dark:text-gray-100">{{ pair.question.payload?.question_text }}</p>

                <p class="mt-3 text-xs uppercase tracking-wide text-gray-500">Answer</p>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                    {{ pair.answer?.payload?.answer_text || pair.answer?.payload?.selected_option || pair.answer?.payload?.skip_reason || 'No answer yet' }}
                </p>

                <button
                    type="button"
                    class="mt-3 text-xs font-medium text-indigo-600 hover:text-indigo-500"
                    @click="emit('edit-question', pair.question.payload?.question_id || '')"
                >
                    Edit from this question
                </button>
            </div>

            <p v-if="qaPairs.length === 0" class="text-sm text-gray-500">No Q&A history yet.</p>
        </div>
    </div>
</template>
