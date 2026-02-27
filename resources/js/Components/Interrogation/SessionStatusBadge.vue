<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const colorClass = computed(() => {
    const map = {
        setup: 'bg-muted text-muted-foreground border-border',
        discovering: 'bg-primary/10 text-primary border-primary/30',
        interrogating: 'bg-primary/10 text-primary border-primary/30',
        summarizing: 'bg-warning/10 text-warning border-warning/30',
        planning: 'bg-warning/10 text-warning border-warning/30',
        build_rules: 'bg-warning/10 text-warning border-warning/30',
        build_tasks: 'bg-primary/10 text-primary border-primary/30',
        build_executing: 'bg-primary/10 text-primary border-primary/30',
        paused: 'bg-muted text-muted-foreground border-border',
        completed: 'bg-success/10 text-success border-success/30',
        failed: 'bg-destructive/10 text-destructive border-destructive/30',
    };

    return map[props.status] ?? 'bg-muted text-muted-foreground border-border';
});
</script>

<template>
    <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold capitalize" :class="colorClass">
        {{ status }}
    </span>
</template>
