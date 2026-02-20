<script setup>
import { computed } from 'vue';

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

const stepClass = (stepIndex) => {
    const state = stateFor(stepIndex);

    if (state === 'done') {
        return 'bg-green-600 text-white border-green-600';
    }

    if (state === 'active') {
        return 'bg-indigo-600 text-white border-indigo-600';
    }

    return 'bg-white text-gray-600 border-gray-300 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700';
};

const connectorClass = (stepIndex) => computed(() => (stepIndex < normalizedPhase.value ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700'));
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <template v-for="(step, index) in steps" :key="step.label">
            <div class="flex items-center gap-2">
                <div class="flex h-8 min-w-8 items-center justify-center rounded-full border px-2 text-xs font-semibold" :class="stepClass(index)">
                    {{ index + 1 }}
                </div>
                <span class="text-xs font-medium text-gray-700 dark:text-gray-200">{{ step.label }}</span>
            </div>
            <div
                v-if="index < steps.length - 1"
                class="h-1 w-8 rounded"
                :class="connectorClass(index).value"
            />
        </template>
    </div>
</template>
