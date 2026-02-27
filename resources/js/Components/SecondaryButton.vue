<!--
@deprecated Use Button from '@/Components/ui/Button.vue' with variant="secondary"
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
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-secondary border border-border rounded-md font-semibold text-xs text-secondary-foreground uppercase tracking-widest shadow-sm hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
