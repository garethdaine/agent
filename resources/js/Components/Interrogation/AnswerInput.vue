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
    waitingForNextQuestion: {
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

    if (props.busy || props.waitingForNextQuestion) {
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
    if (!props.question || props.busy || props.waitingForNextQuestion) {
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
    <div class="rounded-lg border border-border bg-card p-4">
        <div v-if="!question" class="flex items-center gap-2 text-sm text-muted-foreground">
            <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            <span>Waiting for the next question...</span>
        </div>

        <template v-else>
            <div
                v-if="waitingForNextQuestion"
                class="mb-3 rounded-md border border-primary/30 bg-primary/5 px-3 py-2 text-xs text-primary"
            >
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-3.5 w-3.5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                    <span>Answer submitted. Generating next question...</span>
                </div>
            </div>

            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Your Answer</p>

            <div v-if="form.mode === 'choice' && hasChoiceOptions" class="overflow-hidden rounded-lg border border-border bg-muted/30">
                <button
                    v-for="(option, index) in options"
                    :key="option"
                    type="button"
                    class="flex w-full items-center gap-3 border-t border-border px-4 py-3 text-left transition first:border-t-0"
                    :class="optionSelected(option)
                        ? 'bg-primary/10 text-foreground'
                        : 'text-foreground hover:bg-muted'"
                    :disabled="busy || waitingForNextQuestion"
                    @click="toggleOption(option)"
                >
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-sm font-semibold text-foreground">
                        {{ index + 1 }}
                    </span>
                    <span class="flex-1 text-base leading-7">
                        {{ option }}
                    </span>
                    <span
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-sm font-semibold"
                        :class="optionSelected(option)
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground'"
                    >
                        {{ allowsMultiple ? (optionSelected(option) ? 'x' : '+') : '>' }}
                    </span>
                </button>

                <button
                    type="button"
                    class="flex w-full items-center gap-3 border-t border-border px-4 py-3 text-left text-muted-foreground transition hover:bg-muted"
                    :disabled="busy || waitingForNextQuestion"
                    @click="form.mode = 'freetext'"
                >
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted text-base">✎</span>
                    <span class="text-base leading-7">
                        Something else
                    </span>
                </button>
            </div>

            <div v-if="form.mode === 'freetext' || !hasChoiceOptions" class="rounded-lg border border-border bg-muted/30 p-4">
                <textarea
                    v-model="form.answer_text"
                    rows="6"
                    class="w-full rounded-md border border-input bg-input-background text-sm text-foreground"
                    placeholder="Type your answer..."
                />
                <button
                    v-if="hasChoiceOptions"
                    type="button"
                    class="mt-2 text-xs font-medium text-primary hover:opacity-80"
                    :disabled="busy || waitingForNextQuestion"
                    @click="form.mode = 'choice'"
                >
                    Back to options
                </button>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <label for="skip-reason" class="text-xs text-muted-foreground">Skip reason</label>
                    <select
                        id="skip-reason"
                        v-model="form.skip_reason"
                        class="rounded-md border border-input bg-input-background py-1 text-xs text-foreground"
                    >
                        <option value="user_skipped">Skip for now</option>
                        <option value="unknown">I do not know yet</option>
                        <option value="needs_research">Need to research first</option>
                        <option value="not_applicable">Not applicable</option>
                    </select>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ allowsMultiple && form.mode === 'choice' ? 'You can select multiple options.' : 'Select an option or provide a custom answer.' }}
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-input px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/30 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || waitingForNextQuestion || !form.skip_reason"
                        @click="submitSkip"
                    >
                        Skip
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy || waitingForNextQuestion || !canSubmit"
                        @click="submit"
                    >
                        <span v-if="busy" class="inline-flex items-center gap-2">
                            <span class="inline-flex h-3.5 w-3.5 animate-spin rounded-full border-2 border-primary-foreground border-t-transparent" />
                            <span>Submitting answer...</span>
                        </span>
                        <span v-else>{{ submitLabel }}</span>
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
