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
    <div class="rounded-lg border border-primary/30 bg-primary/5 p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-primary">Discovery Status</p>
        <h3 class="mt-1 text-base font-semibold text-foreground capitalize">{{ session.status }}</h3>
        <MarkdownRenderer
            :markdown="displayMessage"
            class="prose prose-sm mt-2 max-w-none text-foreground dark:prose-invert prose-p:my-2 prose-headings:my-2 prose-ul:my-2 prose-ol:my-2 prose-li:my-1 prose-strong:text-foreground prose-code:rounded prose-code:bg-muted prose-code:px-1 prose-code:py-0.5"
        />
    </div>
</template>
