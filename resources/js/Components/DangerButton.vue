<!--
@deprecated Use Button from '@/Components/ui/Button.vue' with variant="destructive"
This component will be removed in a future version.
-->
<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center px-4 py-2 bg-destructive border border-transparent rounded-md font-semibold text-xs text-destructive-foreground uppercase tracking-widest hover:bg-destructive/90 active:bg-destructive/80 focus:outline-none focus:ring-2 focus:ring-destructive focus:ring-offset-2 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
