<script setup>
import { reactive } from 'vue';

const props = defineProps({
    plan: {
        type: Object,
        default: () => ({}),
    },
    busy: {
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

const requestRevision = () => {
    emit('revise', { ...revision });
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Plan</h3>
            <button
                type="button"
                class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200"
                :disabled="busy"
                @click="emit('export')"
            >
                Export Plan
            </button>
        </div>

        <pre class="mt-3 whitespace-pre-wrap rounded bg-gray-50 p-3 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ plan.plan_markdown || 'Plan not generated yet.' }}</pre>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Sections</p>
                <ul class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                    <li v-for="section in list(plan.sections)" :key="section">- {{ section }}</li>
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Risks</p>
                <ul class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                    <li v-for="risk in list(plan.risks)" :key="risk">- {{ risk }}</li>
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assumptions</p>
                <ul class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                    <li v-for="item in list(plan.assumptions)" :key="item">- {{ item }}</li>
                </ul>
            </div>
        </div>

        <div class="mt-5 rounded-md border border-gray-200 p-3 dark:border-gray-700">
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
                    :disabled="busy"
                    @click="requestRevision"
                >
                    {{ busy ? 'Submitting...' : 'Request Revision' }}
                </button>
            </div>
        </div>
    </div>
</template>
