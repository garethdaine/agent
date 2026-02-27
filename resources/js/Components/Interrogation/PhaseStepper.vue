<script setup>
import { computed } from 'vue';
import { Check } from 'lucide-vue-next';

const props = defineProps({
    phase: {
        type: Number,
        required: true,
    },
});

const steps = [
    { label: 'Setup' },
    { label: 'Tech Stack' },
    { label: 'Discovery' },
    { label: 'Interrogation' },
    { label: 'Summary' },
    { label: 'Planning' },
    { label: 'Rules' },
    { label: 'Tasks' },
    { label: 'Build' },
];

const normalizedPhase = computed(() => {
    const phase = Number(props.phase ?? 0);
    if (phase <= 1) {
        return 0;
    }

    return phase - 1;
});

const stateFor = (stepIndex) => {
    if (stepIndex < normalizedPhase.value) {
        return 'done';
    }

    if (stepIndex === normalizedPhase.value) {
        return 'active';
    }

    return 'future';
};

const circleClass = (stepIndex) => {
    const state = stateFor(stepIndex);

    if (state === 'done') {
        return 'bg-success text-success-foreground border-success';
    }

    if (state === 'active') {
        return 'bg-primary text-primary-foreground border-primary ring-4 ring-primary/20';
    }

    return 'border-2 border-muted-foreground/30 bg-card text-muted-foreground';
};

const labelClass = (stepIndex) => {
    const state = stateFor(stepIndex);

    if (state === 'done') {
        return 'text-success';
    }

    if (state === 'active') {
        return 'text-primary';
    }

    return 'text-muted-foreground';
};

const connectorClass = (stepIndex) => {
    return stepIndex < normalizedPhase.value ? 'bg-success' : 'bg-muted-foreground/25';
};
</script>

<template>
    <div class="overflow-x-auto px-1">
        <div class="flex min-w-[700px] items-start justify-between py-1">
            <template v-for="(step, index) in steps" :key="step.label">
                <div class="flex flex-1 items-start last:flex-none">
                    <div class="flex flex-col items-center gap-1.5">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full border text-[12px] font-semibold transition-all" :class="circleClass(index)">
                            <Check v-if="stateFor(index) === 'done'" class="h-3.5 w-3.5" />
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <span class="whitespace-nowrap text-[11px] font-medium" :class="labelClass(index)">
                            {{ step.label }}
                        </span>
                    </div>

                    <div v-if="index < steps.length - 1" class="mx-2 mt-[13px] h-0.5 flex-1 rounded-full" :class="connectorClass(index)" />
                </div>
            </template>
        </div>
    </div>
</template>
