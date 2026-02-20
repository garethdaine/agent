<script setup>
import { computed, reactive, watch } from 'vue';
import MarkdownEditor from '@/Components/Markdown/MarkdownEditor.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { normalizeMarkdownContent } from '@/Components/Interrogation/questionPresentation';

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
    revising: {
        type: Boolean,
        default: false,
    },
    revisionSubmitting: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['revise', 'export']);

const revision = reactive({
    action: 'expand',
    sections: [],
    section: '',
    notes: '',
});

const list = (value) => (Array.isArray(value) ? value : []);
const planMarkdown = computed(() => normalizeMarkdownContent(String(props.plan?.plan_markdown ?? '')));
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

const availablePlanSections = computed(() => {
    const seen = new Set();
    const values = [];

    for (const item of list(props.plan?.sections)) {
        if (typeof item !== 'string') {
            continue;
        }

        const normalized = item.trim();
        if (normalized === '' || seen.has(normalized)) {
            continue;
        }

        seen.add(normalized);
        values.push(normalized);
    }

    return values;
});

const selectedSectionsLabel = computed(() => {
    if (revision.sections.length === 0) {
        return 'All plan sections (default)';
    }

    if (revision.sections.length === 1) {
        return revision.sections[0];
    }

    return `${revision.sections.length} sections selected`;
});

watch(
    availablePlanSections,
    (sections) => {
        revision.sections = revision.sections.filter((item) => sections.includes(item));
    },
    { immediate: true }
);

const toggleSection = (section, selected) => {
    const current = new Set(revision.sections);
    if (selected) {
        current.add(section);
    } else {
        current.delete(section);
    }

    revision.sections = Array.from(current);
};

const selectAllSections = () => {
    revision.sections = [...availablePlanSections.value];
};

const clearSections = () => {
    revision.sections = [];
};

const requestRevision = () => {
    const selectedSections = revision.sections.map((item) => item.trim()).filter((item) => item !== '');

    emit('revise', {
        action: revision.action,
        section: selectedSections.length > 0 ? selectedSections.join(', ') : revision.section,
        sections: selectedSections,
        notes: revision.notes,
    });
};

const renderItemMarkdown = (item) => {
    if (typeof item === 'string') {
        return normalizeMarkdownContent(item);
    }

    return `\`\`\`json\n${JSON.stringify(item, null, 2)}\n\`\`\``;
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
                :disabled="busy || generating || revising"
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
            v-if="revising"
            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <div class="flex items-center gap-2">
                <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-amber-500 border-t-transparent" />
                <span class="font-medium">Revising plan...</span>
            </div>
            <p class="mt-1 text-xs text-amber-800/90 dark:text-amber-200/90">Revision has been submitted. The current plan remains visible until the new revision is ready.</p>
        </div>

        <MarkdownRenderer
            v-if="hasMeaningfulPlanMarkdown"
            :markdown="planMarkdown"
            class="summary-markdown prose prose-sm mt-3 max-w-none rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-800 dark:prose-invert dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100 prose-headings:mb-2 prose-headings:mt-4 prose-p:my-2 prose-li:my-1 prose-code:rounded prose-code:bg-gray-100 prose-code:px-1 prose-code:py-0.5 dark:prose-code:bg-gray-700"
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
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ section.items.length }}</span>
                </summary>
                <div class="border-t border-gray-200 px-3 py-3 dark:border-gray-700">
                    <ul v-if="section.items.length > 0" class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                        <li
                            v-for="(item, index) in section.items"
                            :key="`${section.key}-${index}`"
                            class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800/60"
                        >
                            <MarkdownRenderer
                                :markdown="renderItemMarkdown(item)"
                                class="summary-markdown prose prose-sm max-w-none dark:prose-invert"
                            />
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No items provided.</p>
                </div>
            </details>
        </div>

        <div v-if="hasPlan" class="mt-5 rounded-md border border-gray-200 p-3 dark:border-gray-700">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Request Revision</p>
            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-2">
                <select v-model="revision.action" class="rounded-md border-gray-300 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    <option value="expand">Expand</option>
                    <option value="simplify">Simplify</option>
                    <option value="add_examples">Add Examples</option>
                    <option value="rewrite">Rewrite</option>
                    <option value="split_into_steps">Split Into Steps</option>
                    <option value="add_acceptance_criteria">Add Acceptance Criteria</option>
                </select>

                <details class="relative rounded-md border border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <summary class="cursor-pointer select-none px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-200">
                        {{ selectedSectionsLabel }}
                    </summary>
                    <div class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        <div class="mb-2 flex items-center justify-between text-xs">
                            <button type="button" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" @click="selectAllSections">Select all</button>
                            <button type="button" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" @click="clearSections">Clear</button>
                        </div>

                        <div v-if="availablePlanSections.length > 0" class="max-h-56 space-y-2 overflow-y-auto pr-1">
                            <label
                                v-for="sectionName in availablePlanSections"
                                :key="sectionName"
                                class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600"
                                    :checked="revision.sections.includes(sectionName)"
                                    @change="toggleSection(sectionName, $event.target.checked)"
                                >
                                <span>{{ sectionName }}</span>
                            </label>
                        </div>
                        <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                            No generated sections are available yet.
                        </p>
                    </div>
                </details>
            </div>

            <div class="mt-3">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Revision Notes (Markdown)</label>
                <MarkdownEditor
                    v-model="revision.notes"
                    placeholder="Describe what should change. Markdown supported."
                />
            </div>
            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="busy || generating || revising || revisionSubmitting"
                    @click="requestRevision"
                >
                    {{ revisionSubmitting ? 'Submitting...' : (revising ? 'Revision queued...' : 'Request Revision') }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.summary-markdown) {
    max-width: 100%;
    overflow-x: hidden;
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
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
}

:deep(.summary-markdown th) {
    background: #f3f4f6;
    border-bottom: 1px solid #d1d5db;
    border-right: 1px solid #d1d5db;
    padding: 0.625rem 0.75rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.875rem;
    color: #111827;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}

:deep(.summary-markdown th:last-child) {
    border-right: none;
}

:deep(.summary-markdown td) {
    border-top: 1px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
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
    background: #f9fafb;
}

:deep(.dark .summary-markdown table) {
    border-color: #4b5563;
}

:deep(.dark .summary-markdown th) {
    background: #1f2937;
    border-color: #4b5563;
    color: #f3f4f6;
}

:deep(.dark .summary-markdown td) {
    border-color: #374151;
}

:deep(.dark .summary-markdown tbody tr:nth-child(even) td) {
    background: #111827;
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

:deep(.dark .summary-markdown pre) {
    border-color: #0f172a;
    background: #020617;
}

:deep(.dark .summary-markdown pre code) {
    color: #e5e7eb;
}
</style>
