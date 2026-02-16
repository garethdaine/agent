<script setup>
import { computed, reactive } from 'vue';
import { normalizeMarkdownContent, renderMarkdownToHtml } from '@/Components/Interrogation/questionPresentation';

const props = defineProps({
    plan: {
        type: Object,
        default: () => ({}),
    },
    busy: {
        type: Boolean,
        default: false,
    },
    generating: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['revise', 'export']);

const revision = reactive({
    action: 'expand',
    section: '',
    notes: '',
});

const list = (value) => (Array.isArray(value) ? value : []);
const planMarkdown = computed(() => normalizeMarkdownContent(String(props.plan?.plan_markdown ?? '')));
const planHtml = computed(() => renderMarkdownToHtml(planMarkdown.value));
const hasMeaningfulPlanMarkdown = computed(() => {
    if (planMarkdown.value === '') {
        return false;
    }

    return !/^plan not generated yet\.?$/i.test(planMarkdown.value);
});

const sectionEntries = computed(() => {
    const primary = [
        { key: 'sections', title: 'Sections', items: list(props.plan?.sections) },
        { key: 'risks', title: 'Risks', items: list(props.plan?.risks) },
        { key: 'assumptions', title: 'Assumptions', items: list(props.plan?.assumptions) },
    ];

    const knownKeys = new Set(primary.map((entry) => entry.key));
    const extras = Object.entries(props.plan ?? {})
        .filter(([key, value]) => !knownKeys.has(key) && Array.isArray(value))
        .map(([key, value]) => ({
            key,
            title: key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
            items: list(value),
        }));

    return [...primary, ...extras];
});

const hasPlan = computed(() => {
    if (hasMeaningfulPlanMarkdown.value) {
        return true;
    }

    return sectionEntries.value.some((entry) => entry.items.length > 0);
});

const requestRevision = () => {
    emit('revise', { ...revision });
};

const renderItemHtml = (item) => {
    if (typeof item === 'string') {
        return renderMarkdownToHtml(normalizeMarkdownContent(item));
    }

    return renderMarkdownToHtml(normalizeMarkdownContent(JSON.stringify(item, null, 2)));
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Plan</h3>
            <button
                v-if="hasPlan"
                type="button"
                class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200"
                :disabled="busy || generating"
                @click="emit('export')"
            >
                Export Plan
            </button>
        </div>

        <div
            v-if="generating && !hasPlan"
            class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200"
        >
            <div class="flex items-center gap-2">
                <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-indigo-500 border-t-transparent" />
                <span class="font-medium">Generating plan...</span>
            </div>
            <p class="mt-1 text-xs text-indigo-700/90 dark:text-indigo-200/90">Plan generation is running. This panel will update automatically when ready.</p>
        </div>

        <div
            v-if="hasMeaningfulPlanMarkdown"
            class="summary-markdown prose prose-sm mt-3 max-w-none rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-800 dark:prose-invert dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100 prose-headings:mb-2 prose-headings:mt-4 prose-p:my-2 prose-li:my-1 prose-code:rounded prose-code:bg-gray-100 prose-code:px-1 prose-code:py-0.5 dark:prose-code:bg-gray-700"
            v-html="planHtml"
        />
        <p v-else class="mt-3 rounded bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-200">Plan not generated yet.</p>

        <div v-if="hasPlan" class="mt-4 space-y-3">
            <details
                v-for="section in sectionEntries"
                :key="section.key"
                class="group overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/30"
                :open="section.items.length > 0"
            >
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-800/40">
                    <span>{{ section.title }}</span>
                    <span class="text-xs font-medium text-gray-500">{{ section.items.length }}</span>
                </summary>
                <div class="border-t border-gray-200 px-3 py-3 dark:border-gray-700">
                    <ul v-if="section.items.length > 0" class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                        <li
                            v-for="(item, index) in section.items"
                            :key="`${section.key}-${index}`"
                            class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/60"
                        >
                            <div class="summary-markdown prose prose-sm max-w-none dark:prose-invert" v-html="renderItemHtml(item)" />
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">No items provided.</p>
                </div>
            </details>
        </div>

        <div v-if="hasPlan" class="mt-5 rounded-md border border-gray-200 p-3 dark:border-gray-700">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Request Revision</p>
            <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">
                <select v-model="revision.action" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <option value="expand">Expand</option>
                    <option value="simplify">Simplify</option>
                    <option value="add_examples">Add Examples</option>
                    <option value="rewrite">Rewrite</option>
                    <option value="split_into_steps">Split Into Steps</option>
                    <option value="add_acceptance_criteria">Add Acceptance Criteria</option>
                </select>
                <input v-model="revision.section" type="text" placeholder="Section (optional)" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                <input v-model="revision.notes" type="text" placeholder="Notes (optional)" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
            </div>
            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="busy || generating"
                    @click="requestRevision"
                >
                    {{ busy ? 'Submitting...' : 'Request Revision' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.summary-markdown .md-table) {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    overflow: hidden;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

:deep(.summary-markdown .md-table-th) {
    background: #f3f4f6;
    border-bottom: 1px solid #d1d5db;
    border-right: 1px solid #d1d5db;
    padding: 0.625rem 0.75rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.875rem;
    color: #111827;
}

:deep(.summary-markdown .md-table-th:last-child) {
    border-right: none;
}

:deep(.summary-markdown .md-table-td) {
    border-top: 1px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
    padding: 0.625rem 0.75rem;
    vertical-align: top;
}

:deep(.summary-markdown .md-table-td:last-child) {
    border-right: none;
}

:deep(.summary-markdown .md-table-tr:nth-child(even) .md-table-td) {
    background: #f9fafb;
}

:deep(.dark .summary-markdown .md-table) {
    border-color: #4b5563;
}

:deep(.dark .summary-markdown .md-table-th) {
    background: #1f2937;
    border-color: #4b5563;
    color: #f3f4f6;
}

:deep(.dark .summary-markdown .md-table-td) {
    border-color: #374151;
}

:deep(.dark .summary-markdown .md-table-tr:nth-child(even) .md-table-td) {
    background: #111827;
}

:deep(.summary-markdown .md-code-block) {
    margin-top: 0.5rem;
    margin-bottom: 0.75rem;
    overflow-x: hidden;
    border-radius: 0.5rem;
    border: 1px solid #0f172a;
    background: #020617;
    padding: 0.75rem;
}

:deep(.summary-markdown .md-code) {
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

:deep(.summary-markdown .md-json-key) {
    color: #93c5fd;
}

:deep(.summary-markdown .md-json-string) {
    color: #86efac;
}

:deep(.summary-markdown .md-json-number) {
    color: #fca5a5;
}

:deep(.summary-markdown .md-json-literal) {
    color: #fcd34d;
}

:deep(.dark .summary-markdown .md-code-block) {
    border-color: #0f172a;
    background: #020617;
}

:deep(.dark .summary-markdown .md-code) {
    color: #e5e7eb;
}
</style>
