<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
    collapsed: Boolean,
    as: String,
});

const classes = computed(() => {
    const base = 'group relative flex items-center rounded-lg text-[13px] font-medium transition-all duration-200 ease-in-out';
    const spacing = props.collapsed ? 'justify-center w-10 h-10 mx-auto' : 'gap-3 px-3 py-2';

    return props.active
        ? `${base} ${spacing} text-primary bg-primary/10`
        : `${base} ${spacing} text-muted-foreground hover:text-foreground hover:bg-muted`;
});
</script>

<template>
    <div class="relative">
        <button v-if="as === 'button'" :class="classes" class="w-full text-start">
            <slot name="icon" />
            <span v-if="!collapsed" class="truncate">
                <slot />
            </span>
        </button>

        <Link v-else :href="href" :class="classes">
            <slot name="icon" />
            <span v-if="!collapsed" class="truncate">
                <slot />
            </span>
        </Link>

        <div
            v-if="collapsed"
            class="pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-2 rounded-md bg-popover px-2 py-1 text-xs font-medium text-popover-foreground shadow-md border border-border opacity-0 group-hover:opacity-100 transition-opacity duration-150 whitespace-nowrap z-50"
        >
            <slot />
        </div>
    </div>
</template>
