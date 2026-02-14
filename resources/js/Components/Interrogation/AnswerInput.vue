<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    question: {
        type: Object,
        default: null,
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['submit']);

const form = reactive({
    answer_type: 'freetext',
    answer_text: '',
    selected_option: '',
    skip_reason: '',
});

watch(() => props.question, (question) => {
    form.answer_type = Array.isArray(question?.options) && question.options.length > 0 ? 'choice' : 'freetext';
    form.answer_text = '';
    form.selected_option = '';
    form.skip_reason = '';
}, { immediate: true });

const submit = () => {
    if (!props.question) {
        return;
    }

    emit('submit', {
        question_id: props.question.question_id,
        answer_type: form.answer_type,
        answer_text: form.answer_text,
        selected_option: form.selected_option,
        skip_reason: form.skip_reason,
    });
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Your answer</p>

        <div class="mt-3 flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2">
                <input v-model="form.answer_type" type="radio" value="freetext" class="border-gray-300" />
                Free text
            </label>
            <label class="inline-flex items-center gap-2" :class="{ 'opacity-50': !(question?.options?.length) }">
                <input v-model="form.answer_type" type="radio" value="choice" class="border-gray-300" :disabled="!(question?.options?.length)" />
                Choice
            </label>
            <label class="inline-flex items-center gap-2">
                <input v-model="form.answer_type" type="radio" value="skip" class="border-gray-300" />
                Skip
            </label>
        </div>

        <textarea
            v-if="form.answer_type === 'freetext'"
            v-model="form.answer_text"
            rows="4"
            class="mt-3 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
            placeholder="Enter your answer"
        />

        <select
            v-if="form.answer_type === 'choice'"
            v-model="form.selected_option"
            class="mt-3 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <option value="" disabled>Select an option</option>
            <option v-for="option in question?.options || []" :key="option" :value="option">{{ option }}</option>
        </select>

        <select
            v-if="form.answer_type === 'skip'"
            v-model="form.skip_reason"
            class="mt-3 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
        >
            <option value="" disabled>Reason for skipping</option>
            <option value="unknown">I do not know yet</option>
            <option value="needs-research">Need to research first</option>
            <option value="not-applicable">Not applicable</option>
        </select>

        <div class="mt-4 flex justify-end">
            <button
                type="button"
                class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="busy"
                @click="submit"
            >
                {{ busy ? 'Submitting...' : 'Submit Answer' }}
            </button>
        </div>
    </div>
</template>
