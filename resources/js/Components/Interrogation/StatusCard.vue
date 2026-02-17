<script setup>
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { computed } from 'vue';

const props = defineProps({
    session: {
        type: Object,
        required: true,
    },
    latestDiscoveryEvent: {
        type: Object,
        default: null,
    },
});

const displayMessage = computed(() => {
    const raw = String(props.latestDiscoveryEvent?.payload?.message ?? '').trim();
    if (raw === '') {
        return 'Analyzing repository and gathering context...';
    }

    if (raw.length <= 1200) {
        return raw;
    }

    return `${raw.slice(0, 1200).trimEnd()}\n\n… [truncated]`;
});

</script>

<template>
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-950/30">
        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Discovery Status</p>
        <h3 class="mt-1 text-base font-semibold text-blue-900 dark:text-blue-200">{{ session.status }}</h3>
        <MarkdownRenderer
            :markdown="displayMessage"
            class="prose prose-sm mt-2 max-w-none text-blue-900 dark:prose-invert dark:text-blue-200 prose-p:my-2 prose-headings:my-2 prose-ul:my-2 prose-ol:my-2 prose-li:my-1 prose-strong:text-blue-900 dark:prose-strong:text-blue-100 prose-code:rounded prose-code:bg-blue-100 prose-code:px-1 prose-code:py-0.5 dark:prose-code:bg-blue-900/40"
        />
    </div>
</template>
