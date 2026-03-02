<script setup>
import { computed } from 'vue';
import { deriveInterrogationStatusKey, humanizeDisplayValue } from '@/Components/Interrogation/displayFormatting';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
    buildStatus: {
        type: String,
        default: '',
    },
});

const statusKey = computed(() => deriveInterrogationStatusKey({
    status: props.status,
    buildStatus: props.buildStatus,
}));

const colorClass = computed(() => {
    const map = {
        setup: 'bg-muted text-muted-foreground border-border',
        discovering: 'bg-primary/10 text-primary border-primary/30',
        interrogating: 'bg-primary/10 text-primary border-primary/30',
        summarizing: 'bg-warning/10 text-warning border-warning/30',
        planning: 'bg-warning/10 text-warning border-warning/30',
        build_rules: 'bg-warning/10 text-warning border-warning/30',
        generating_tasks: 'bg-primary/10 text-primary border-primary/30',
        build_tasks: 'bg-primary/10 text-primary border-primary/30',
        build_executing: 'bg-primary/10 text-primary border-primary/30',
        paused: 'bg-muted text-muted-foreground border-border',
        completed: 'bg-success/10 text-success border-success/30',
        failed: 'bg-destructive/10 text-destructive border-destructive/30',
    };

    return map[statusKey.value] ?? 'bg-muted text-muted-foreground border-border';
});

const statusLabel = computed(() => humanizeDisplayValue(statusKey.value, 'Unknown'));
</script>

<template>
    <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold" :class="colorClass">
        {{ statusLabel }}
    </span>
</template>
