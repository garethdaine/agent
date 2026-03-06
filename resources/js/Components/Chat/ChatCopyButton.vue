<script setup>
import { ref } from 'vue';
import { Copy, Check } from 'lucide-vue-next';

const props = defineProps({
    content: { type: String, default: '' },
});

const copied = ref(false);
let timeout = null;

const copy = async () => {
    if (!props.content) return;

    try {
        await navigator.clipboard.writeText(props.content);
        copied.value = true;

        clearTimeout(timeout);
        timeout = setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // Clipboard API may fail in insecure contexts
    }
};
</script>

<template>
    <button
        type="button"
        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :title="copied ? 'Copied!' : 'Copy message'"
        @click.stop="copy"
    >
        <Check v-if="copied" class="h-3.5 w-3.5 text-success" />
        <Copy v-else class="h-3.5 w-3.5" />
    </button>
</template>
