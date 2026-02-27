<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'relative inline-flex items-center px-3 py-2 rounded-md text-primary bg-primary/10 text-[13px] font-medium transition duration-150 ease-in-out'
        : 'relative inline-flex items-center px-3 py-2 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted text-[13px] font-medium transition duration-150 ease-in-out';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
        <span v-if="active" class="absolute bottom-0 left-3 right-3 h-0.5 bg-primary rounded-full" />
    </Link>
</template>
