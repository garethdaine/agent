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
    const notes = revision.notes;

    emit('revise', {
        action: revision.action,
        section: selectedSections.length > 0 ? selectedSections.join(', ') : revision.section,
        sections: selectedSections,
        notes,
    });

    revision.notes = '';
};

const renderItemMarkdown = (item) => {
    if (typeof item === 'string') {
        return normalizeMarkdownContent(item);
    }

    return `\`\`\`json\n${JSON.stringify(item, null, 2)}\n\`\`\``;
};
</script>

<template>
    <div class="rounded-lg border border-border bg-card p-4  ">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-foreground ">Plan</h3>
            <button
                v-if="hasPlan"
                type="button"
                class="rounded border border-input px-2 py-1 text-xs text-foreground hover:bg-muted/30  "
                :disabled="busy || generating || revising"
                @click="emit('export')"
            >
                Export Plan
            </button>
        </div>

        <div class="mt-3 space-y-4">
            <div
                v-if="generating && !hasPlan"
                class="rounded-lg border border-primary/30 bg-primary/5 p-3 text-sm text-primary"
            >
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                    <span class="font-medium">Generating plan...</span>
                </div>
                <p class="mt-1 text-xs text-primary/90 ">Plan generation is running. This panel will update automatically when ready.</p>
            </div>

            <div
                v-if="revising"
                class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-warning"
            >
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-warning border-t-transparent" />
                    <span class="font-medium">Revising plan...</span>
                </div>
                <p class="mt-1 text-xs text-warning/90">Revision has been submitted. The current plan remains visible until the new revision is ready.</p>
            </div>

            <MarkdownRenderer
                v-if="hasMeaningfulPlanMarkdown"
                :markdown="planMarkdown"
                class="summary-markdown summary-markdown-scroll prose prose-sm max-w-none rounded-lg border border-border bg-muted/30 p-4 text-foreground    prose-headings:mb-2 prose-headings:mt-4 prose-p:my-2 prose-li:my-1 prose-code:rounded prose-code:bg-muted prose-code:px-1 prose-code:py-0.5"
            />
            <p v-else class="rounded bg-muted/30 p-3 text-sm text-foreground  ">Plan not generated yet.</p>

            <div v-if="hasPlan" class="space-y-3">
                <details
                    v-for="section in sectionEntries"
                    :key="section.key"
                    class="group overflow-hidden rounded-lg border border-border bg-card  "
                >
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted/30">
                        <span>{{ section.title }}</span>
                        <span class="text-xs font-medium text-muted-foreground ">{{ section.items.length }}</span>
                    </summary>
                    <div class="border-t border-border px-3 py-3 ">
                        <ul v-if="section.items.length > 0" class="space-y-2 text-sm text-foreground ">
                            <li
                                v-for="(item, index) in section.items"
                                :key="`${section.key}-${index}`"
                                class="rounded-md border border-border bg-muted/30 px-3 py-2"
                            >
                                <MarkdownRenderer
                                    :markdown="renderItemMarkdown(item)"
                                    class="summary-markdown prose prose-sm max-w-none"
                                />
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground ">No items provided.</p>
                    </div>
                </details>
            </div>
        </div>

        <div v-if="hasPlan" class="mt-4 rounded-md border border-border p-3 ">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Request Revision</p>
            <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-2">
                <select v-model="revision.action" class="rounded-md border-input text-sm text-foreground   ">
                    <option value="expand">Expand</option>
                    <option value="simplify">Simplify</option>
                    <option value="add_examples">Add Examples</option>
                    <option value="rewrite">Rewrite</option>
                    <option value="split_into_steps">Split Into Steps</option>
                    <option value="add_acceptance_criteria">Add Acceptance Criteria</option>
                </select>

                <details class="relative rounded-md border border-input bg-card  ">
                    <summary class="cursor-pointer select-none px-3 py-2 text-left text-sm text-foreground ">
                        {{ selectedSectionsLabel }}
                    </summary>
                    <div class="absolute z-20 mt-1 w-full rounded-md border border-border bg-card p-2 shadow-lg  ">
                        <div class="mb-2 flex items-center justify-between text-xs">
                            <button type="button" class="text-primary hover:opacity-80" @click="selectAllSections">Select all</button>
                            <button type="button" class="text-muted-foreground hover:text-foreground" @click="clearSections">Clear</button>
                        </div>

                        <div v-if="availablePlanSections.length > 0" class="max-h-56 space-y-2 overflow-y-auto pr-1">
                            <label
                                v-for="sectionName in availablePlanSections"
                                :key="sectionName"
                                class="flex items-center gap-2 text-sm text-foreground "
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-input text-primary focus:ring-primary "
                                    :checked="revision.sections.includes(sectionName)"
                                    @change="toggleSection(sectionName, $event.target.checked)"
                                >
                                <span>{{ sectionName }}</span>
                            </label>
                        </div>
                        <p v-else class="text-xs text-muted-foreground ">
                            No generated sections are available yet.
                        </p>
                    </div>
                </details>
            </div>

            <div class="mt-3">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted-foreground ">Revision Notes (Markdown)</label>
                <MarkdownEditor
                    v-model="revision.notes"
                    placeholder="Describe what should change. Markdown supported."
                />
            </div>
            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    class="rounded bg-primary px-3 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
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

:deep(.dark .summary-markdown pre) {
    border-color: #0f172a;
    background: #020617;
}

:deep(.dark .summary-markdown pre code) {
    color: #e5e7eb;
}
</style>
