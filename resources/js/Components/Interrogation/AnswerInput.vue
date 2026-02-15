<script setup>
import { computed, reactive, watch } from 'vue';
import { choiceOptionsFromQuestion, shouldAllowMultipleChoices } from '@/Components/Interrogation/questionPresentation';

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
    mode: 'choice',
    answer_text: '',
    selected_option: '',
    selected_options: [],
    skip_reason: 'user_skipped',
});

const options = computed(() => choiceOptionsFromQuestion(props.question));
const hasChoiceOptions = computed(() => options.value.length > 0);
const allowsMultiple = computed(() => shouldAllowMultipleChoices(props.question));

const canSubmit = computed(() => {
    if (!props.question) {
        return false;
    }

    if (form.mode === 'freetext') {
        return form.answer_text.trim() !== '';
    }

    if (!hasChoiceOptions.value) {
        return false;
    }

    if (allowsMultiple.value) {
        return form.selected_options.length > 0;
    }

    return form.selected_option !== '';
});

const submitLabel = computed(() => {
    if (form.mode === 'freetext') {
        return 'Submit answer';
    }

    return allowsMultiple.value ? 'Confirm selections' : 'Confirm selection';
});

const resetForm = () => {
    form.mode = hasChoiceOptions.value ? 'choice' : 'freetext';
    form.answer_text = '';
    form.selected_option = '';
    form.selected_options = [];
    form.skip_reason = 'user_skipped';
};

watch(() => props.question, resetForm, { immediate: true });

watch(allowsMultiple, (multiple) => {
    if (multiple) {
        if (form.selected_option !== '') {
            form.selected_options = [form.selected_option];
            form.selected_option = '';
        }

        return;
    }

    if (form.selected_options.length > 0) {
        form.selected_option = form.selected_options[0] ?? '';
        form.selected_options = [];
    }
});

const toggleOption = (option) => {
    form.mode = 'choice';

    if (allowsMultiple.value) {
        if (form.selected_options.includes(option)) {
            form.selected_options = form.selected_options.filter((value) => value !== option);
            return;
        }

        form.selected_options = [...form.selected_options, option];
        return;
    }

    form.selected_option = option;
};

const optionSelected = (option) => {
    if (allowsMultiple.value) {
        return form.selected_options.includes(option);
    }

    return form.selected_option === option;
};

const submitSkip = () => {
    if (!props.question || props.busy) {
        return;
    }

    const reason = typeof form.skip_reason === 'string' ? form.skip_reason.trim() : '';

    if (reason === '') {
        return;
    }

    emit('submit', {
        question_id: props.question.question_id,
        answer_type: 'skip',
        answer_text: '',
        selected_option: '',
        selected_options: [],
        skip_reason: reason,
    });
};

const submit = () => {
    if (!props.question) {
        return;
    }

    if (form.mode === 'freetext') {
        emit('submit', {
            question_id: props.question.question_id,
            answer_type: 'freetext',
            answer_text: form.answer_text,
            selected_option: '',
            selected_options: [],
            skip_reason: '',
        });

        return;
    }

    emit('submit', {
        question_id: props.question.question_id,
        answer_type: 'choice',
        answer_text: '',
        selected_option: form.selected_option,
        selected_options: form.selected_options,
        skip_reason: '',
    });
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div v-if="!question" class="text-sm text-gray-500">Waiting for the next question...</div>

        <template v-else>
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Your Answer</p>

            <div v-if="form.mode === 'choice' && hasChoiceOptions" class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">
                <button
                    v-for="(option, index) in options"
                    :key="option"
                    type="button"
                    class="flex w-full items-center gap-3 border-t border-gray-200 px-4 py-3 text-left transition first:border-t-0 dark:border-gray-700"
                    :class="optionSelected(option)
                        ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100'
                        : 'text-gray-800 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800/70'"
                    @click="toggleOption(option)"
                >
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ index + 1 }}
                    </span>
                    <span class="flex-1 text-base leading-7">
                        {{ option }}
                    </span>
                    <span
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-sm font-semibold"
                        :class="optionSelected(option)
                            ? 'bg-indigo-600 text-white dark:bg-indigo-500'
                            : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                    >
                        {{ allowsMultiple ? (optionSelected(option) ? 'x' : '+') : '>' }}
                    </span>
                </button>

                <button
                    type="button"
                    class="flex w-full items-center gap-3 border-t border-gray-200 px-4 py-3 text-left text-gray-500 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800/70"
                    @click="form.mode = 'freetext'"
                >
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-base dark:bg-gray-800">✎</span>
                    <span class="text-base leading-7">
                        Something else
                    </span>
                </button>
            </div>

            <div v-if="form.mode === 'freetext' || !hasChoiceOptions" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                <textarea
                    v-model="form.answer_text"
                    rows="6"
                    class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                    placeholder="Type your answer..."
                />
                <button
                    v-if="hasChoiceOptions"
                    type="button"
                    class="mt-2 text-xs font-medium text-indigo-600 hover:text-indigo-500"
                    @click="form.mode = 'choice'"
                >
                    Back to options
                </button>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <label for="skip-reason" class="text-xs text-gray-500">Skip reason</label>
                    <select
                        id="skip-reason"
                        v-model="form.skip_reason"
                        class="rounded-md border-gray-300 py-1 text-xs dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="user_skipped">Skip for now</option>
                        <option value="unknown">I do not know yet</option>
                        <option value="needs_research">Need to research first</option>
                        <option value="not_applicable">Not applicable</option>
                    </select>
                </div>
                <p class="text-xs text-gray-500">
                    {{ allowsMultiple && form.mode === 'choice' ? 'You can select multiple options.' : 'Select an option or provide a custom answer.' }}
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-900"
                        :disabled="busy || !form.skip_reason"
                        @click="submitSkip"
                    >
                        Skip
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || !canSubmit"
                        @click="submit"
                    >
                        {{ busy ? 'Submitting...' : submitLabel }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
