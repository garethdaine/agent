<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({}),
    },
    busy: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['confirm']);

const showPrivate = ref(false);

const list = (value) => (Array.isArray(value) ? value : []);

const hasSummary = computed(() => {
    return typeof props.summary?.summary_markdown === 'string' && props.summary.summary_markdown.trim() !== '';
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Summary</h3>

        <p v-if="!hasSummary" class="mt-2 text-sm text-gray-500">Summary not available yet.</p>

        <template v-else>
            <pre class="mt-3 whitespace-pre-wrap rounded bg-gray-50 p-3 text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ summary.summary_markdown }}</pre>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Goals</p>
                    <ul class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                        <li v-for="goal in list(summary.goals)" :key="goal">- {{ goal }}</li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Constraints</p>
                    <ul class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                        <li v-for="item in list(summary.constraints)" :key="item">- {{ item }}</li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Acceptance Criteria</p>
                    <ul class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                        <li v-for="item in list(summary.acceptance_criteria)" :key="item">- {{ item }}</li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Open Questions</p>
                    <ul class="mt-1 text-sm text-gray-700 dark:text-gray-200">
                        <li v-for="item in list(summary.open_questions)" :key="item">- {{ item }}</li>
                    </ul>
                </div>
            </div>

            <div class="mt-4">
                <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-500" @click="showPrivate = !showPrivate">
                    {{ showPrivate ? 'Hide' : 'Show' }} private notes
                </button>
                <p v-if="showPrivate" class="mt-2 whitespace-pre-wrap text-sm text-gray-600 dark:text-gray-300">{{ summary.private_notes || 'No private notes' }}</p>
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    type="button"
                    class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="busy"
                    @click="emit('confirm')"
                >
                    {{ busy ? 'Confirming...' : 'Confirm Summary' }}
                </button>
            </div>
        </template>
    </div>
</template>
