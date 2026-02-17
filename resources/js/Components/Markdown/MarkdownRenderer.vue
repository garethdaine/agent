<script setup>
import { computed } from 'vue';
import { VueMarkdown } from '@crazydos/vue-markdown';
import remarkGfm from 'remark-gfm';
import { normalizeMarkdownContent } from '@/Components/Interrogation/questionPresentation';

const props = defineProps({
    markdown: {
        type: String,
        default: '',
    },
    normalize: {
        type: Boolean,
        default: true,
    },
});

const remarkPlugins = [remarkGfm];

const customAttrs = {
    a: (node) => {
        const href = String(node?.properties?.href ?? '');

        if (/^https?:\/\//i.test(href)) {
            return {
                target: '_blank',
                rel: 'noopener noreferrer',
            };
        }

        return {};
    },
};

const normalizedMarkdown = computed(() => {
    if (!props.normalize) {
        return String(props.markdown ?? '');
    }

    return normalizeMarkdownContent(String(props.markdown ?? ''));
});
</script>

<template>
    <VueMarkdown
        :markdown="normalizedMarkdown"
        :remark-plugins="remarkPlugins"
        :custom-attrs="customAttrs"
        sanitize
    />
</template>

