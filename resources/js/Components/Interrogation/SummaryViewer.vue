<script setup>
import { computed, reactive, ref, watch } from 'vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { normalizeMarkdownContent, normalizePrivateNotesContent } from '@/Components/Interrogation/questionPresentation';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({}),
    },
    busy: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['confirm', 'revise', 'continue']);

const list = (value) => (Array.isArray(value) ? value : []);

const hasSummary = computed(() => {
    return normalizedSummaryMarkdown.value !== '';
});

const normalizedSummaryMarkdown = computed(() => normalizeMarkdownContent(String(props.summary?.summary_markdown ?? '')));

const sections = computed(() => [
    {
        key: 'goals',
        title: 'Goals',
        items: list(props.summary?.goals),
    },
    {
        key: 'constraints',
        title: 'Constraints',
        items: list(props.summary?.constraints),
    },
    {
        key: 'acceptance_criteria',
        title: 'Acceptance Criteria',
        items: list(props.summary?.acceptance_criteria),
    },
    {
        key: 'open_questions',
        title: 'Open Questions',
        items: list(props.summary?.open_questions),
    },
]);

const privateNotes = computed(() => normalizePrivateNotesContent(String(props.summary?.private_notes ?? '')));
const openQuestions = computed(() => list(props.summary?.open_questions));
const hasOpenQuestions = computed(() => openQuestions.value.length > 0);
const canConfirm = computed(() => hasSummary.value && !hasOpenQuestions.value && !props.busy);

const reviseForm = reactive({
    notes: '',
});
const continueForm = reactive({
    focus: '',
});

const showReviseForm = ref(false);
const showContinueForm = ref(false);
const revisionPending = ref(false);
const revisionBaselineSummary = ref('');

const submitRevision = () => {
    const notes = reviseForm.notes.trim();
    revisionBaselineSummary.value = normalizedSummaryMarkdown.value;
    revisionPending.value = true;
    showReviseForm.value = false;
    reviseForm.notes = '';

    emit('revise', { notes });
};

const submitContinue = () => {
    emit('continue', {
        focus: continueForm.focus.trim(),
    });
};

watch(normalizedSummaryMarkdown, (nextSummary) => {
    if (!revisionPending.value) {
        return;
    }

    if (nextSummary !== revisionBaselineSummary.value) {
        revisionPending.value = false;
        revisionBaselineSummary.value = '';
    }
});

watch(() => props.status, (nextStatus) => {
    if (nextStatus === 'failed') {
        revisionPending.value = false;
        revisionBaselineSummary.value = '';
    }
});
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4  ">
        <h3 class="text-base font-semibold text-foreground ">Summary</h3>

        <div v-if="!hasSummary" class="mt-2 text-sm text-muted-foreground">
            <div v-if="status === 'summarizing'" class="rounded-lg border border-primary/30 bg-primary/5 p-3 text-primary">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                    <span class="font-medium">Generating summary...</span>
                </div>
                <p class="mt-1 text-xs text-primary/90 ">Interrogation is complete. Building the consolidated summary now.</p>
            </div>
            <p v-else>Summary not available yet.</p>
        </div>

        <template v-else>
            <div class="mt-3 space-y-4">
                <div
                    v-if="revisionPending"
                    class="rounded-lg border border-primary/30 bg-primary/5 p-3 text-primary"
                >
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                        <span class="font-medium">Amendments submitted. Regenerating summary...</span>
                    </div>
                    <p class="mt-1 text-xs text-primary/90 ">Current summary remains visible until the refreshed version is ready.</p>
                </div>

                <MarkdownRenderer
                    :markdown="normalizedSummaryMarkdown"
                    class="summary-markdown summary-markdown-scroll prose prose-sm max-w-none rounded-lg border border-border bg-muted/30 p-4 text-foreground    prose-headings:mb-2 prose-headings:mt-4 prose-p:my-2 prose-li:my-1 prose-code:rounded prose-code:bg-muted prose-code:px-1 prose-code:py-0.5"
                />

                <div class="space-y-3">
                    <details
                        v-for="section in sections"
                        :key="section.key"
                        class="group overflow-hidden rounded-lg border border-border bg-card  "
                    >
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted/30">
                            <span>{{ section.title }}</span>
                            <span class="text-xs font-medium text-muted-foreground">{{ section.items.length }}</span>
                        </summary>
                        <div class="border-t border-border px-3 py-3 ">
                            <ul v-if="section.items.length > 0" class="space-y-2 text-sm text-foreground ">
                                <li
                                    v-for="item in section.items"
                                    :key="item"
                                    class="rounded-md border border-border bg-muted/30 px-3 py-2"
                                >
                                    {{ item }}
                                </li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">No items provided.</p>
                        </div>
                    </details>
                </div>

                <details class="group overflow-hidden rounded-lg border border-border bg-card  ">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted/30">
                        <span>Private Notes</span>
                        <span class="text-xs font-medium text-muted-foreground">{{ privateNotes === '' ? 0 : 1 }}</span>
                    </summary>
                    <div class="border-t border-border px-3 py-3 ">
                        <MarkdownRenderer
                            v-if="privateNotes !== ''"
                            :markdown="privateNotes"
                            class="summary-markdown notes-markdown prose prose-sm max-w-none text-foreground  prose-p:my-2 prose-li:my-1 prose-code:rounded prose-code:bg-muted prose-code:px-1 prose-code:py-0.5"
                        />
                        <p v-else class="text-sm text-muted-foreground">No private notes.</p>
                    </div>
                </details>

                <div
                    v-if="hasOpenQuestions"
                    class="rounded-lg border border-warning/30 bg-warning/10 px-3 py-2 text-sm text-warning"
                >
                    Open questions remain ({{ openQuestions.length }}). Resolve them before confirming this summary.
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-input px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/30 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy"
                        @click="showContinueForm = !showContinueForm"
                    >
                        Continue interrogation
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-input px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/30 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy"
                        @click="showReviseForm = !showReviseForm"
                    >
                        Request amendments
                    </button>
                </div>
                <button
                    type="button"
                    class="rounded bg-primary px-3 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canConfirm"
                    @click="emit('confirm')"
                >
                    {{ busy ? 'Confirming...' : 'Confirm Summary' }}
                </button>
            </div>

            <div v-if="showContinueForm" class="mt-3 rounded-lg border border-border bg-muted/30 p-3  ">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Interrogation Focus (optional)</p>
                <textarea
                    v-model="continueForm.focus"
                    rows="3"
                    class="mt-2 w-full rounded-md border-input text-sm text-foreground"
                    placeholder="Add specific ambiguities you want the next interrogation round to resolve."
                />
                <div class="mt-2 flex justify-end">
                    <button
                        type="button"
                        class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy"
                        @click="submitContinue"
                    >
                        Continue Interrogation
                    </button>
                </div>
            </div>

            <div v-if="showReviseForm" class="mt-3 rounded-lg border border-border bg-muted/30 p-3  ">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Amendment Notes</p>
                <textarea
                    v-model="reviseForm.notes"
                    rows="4"
                    class="mt-2 w-full rounded-md border-input text-sm text-foreground"
                    placeholder="Describe what needs changing in the summary."
                />
                <div class="mt-2 flex justify-end">
                    <button
                        type="button"
                        class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="busy"
                        @click="submitRevision"
                    >
                        Request Summary Revision
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
:deep(.summary-markdown) {
    max-width: 100%;
    overflow-x: hidden;
    --summary-table-border: #d1d5db;
    --summary-table-head-bg: #f3f4f6;
    --summary-table-head-border: #d1d5db;
    --summary-table-head-text: #111827;
    --summary-table-cell-bg: #ffffff;
    --summary-table-cell-alt-bg: #f9fafb;
    --summary-table-cell-border: #e5e7eb;
    --summary-table-cell-text: #1f2937;
}

:deep(.summary-markdown-scroll) {
    max-height: 46vh;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-gutter: stable both-edges;
    padding-right: 0.875rem;
}

:deep(.summary-markdown table) {
    display: block;
    width: max-content;
    min-width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid var(--summary-table-border);
    border-radius: 0.5rem;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

:deep(.summary-markdown th) {
    background: var(--summary-table-head-bg);
    border-bottom: 1px solid var(--summary-table-head-border);
    border-right: 1px solid var(--summary-table-head-border);
    padding: 0.625rem 0.75rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--summary-table-head-text);
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}

:deep(.summary-markdown th:last-child) {
    border-right: none;
}

:deep(.summary-markdown td) {
    background: var(--summary-table-cell-bg);
    color: var(--summary-table-cell-text);
    border-top: 1px solid var(--summary-table-cell-border);
    border-right: 1px solid var(--summary-table-cell-border);
    padding: 0.625rem 0.75rem;
    vertical-align: top;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}

:deep(.summary-markdown td:last-child) {
    border-right: none;
}

:deep(.summary-markdown tbody tr:nth-child(even) td) {
    background: var(--summary-table-cell-alt-bg);
}

:deep(.dark .summary-markdown) {
    --summary-table-border: #4b5563;
    --summary-table-head-bg: #1f2937;
    --summary-table-head-border: #4b5563;
    --summary-table-head-text: #f3f4f6;
    --summary-table-cell-bg: #111827;
    --summary-table-cell-alt-bg: #1f2937;
    --summary-table-cell-border: #374151;
    --summary-table-cell-text: #f3f4f6;
}

@media (prefers-color-scheme: dark) {
    :deep(.summary-markdown) {
        --summary-table-border: #4b5563;
        --summary-table-head-bg: #1f2937;
        --summary-table-head-border: #4b5563;
        --summary-table-head-text: #f3f4f6;
        --summary-table-cell-bg: #111827;
        --summary-table-cell-alt-bg: #1f2937;
        --summary-table-cell-border: #374151;
        --summary-table-cell-text: #f3f4f6;
    }
}

:deep(.summary-markdown pre) {
    margin-top: 0.5rem;
    margin-bottom: 0.75rem;
    overflow-x: hidden;
    border-radius: 0.5rem;
    border: 1px solid #0f172a;
    background: #020617;
    padding: 0.75rem;
}

:deep(.summary-markdown pre code) {
    display: block;
    white-space: pre-wrap;
    word-break: break-word;
    overflow-wrap: anywhere;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.8125rem;
    line-height: 1.35;
    color: #e2e8f0;
    background: transparent !important;
    padding: 0 !important;
    border-radius: 0 !important;
}

:deep(.summary-markdown .token.property) {
    color: #93c5fd;
}

:deep(.summary-markdown .token.string) {
    color: #86efac;
}

:deep(.summary-markdown .token.number) {
    color: #fca5a5;
}

:deep(.summary-markdown .token.boolean),
:deep(.summary-markdown .token.null) {
    color: #fcd34d;
}

:deep(.dark .summary-markdown pre) {
    border-color: #0f172a;
    background: #020617;
}

:deep(.dark .summary-markdown pre code) {
    color: #e5e7eb;
}

:deep(.notes-markdown ol) {
    list-style: decimal;
    padding-left: 1.25rem;
}

:deep(.notes-markdown li + li) {
    margin-top: 0.5rem;
}
</style>
