<script setup>
import { computed } from 'vue';

const props = defineProps({
    phase: {
        type: Number,
        required: true,
    },
});

const steps = [
    { value: 0, label: 'Setup' },
    { value: 1, label: 'Discovery' },
    { value: 2, label: 'Interrogation' },
    { value: 3, label: 'Summary' },
    { value: 4, label: 'Planning' },
    { value: 5, label: 'Build Tasks' },
    { value: 6, label: 'Build Execution' },
];

const stateFor = (value) => {
    if (value < props.phase) {
        return 'done';
    }

    if (value === props.phase) {
        return 'active';
    }

    return 'future';
};

const stepClass = (value) => {
    const state = stateFor(value);

    if (state === 'done') {
        return 'bg-green-600 text-white border-green-600';
    }

    if (state === 'active') {
        return 'bg-indigo-600 text-white border-indigo-600';
    }

    return 'bg-white text-gray-600 border-gray-300';
};

const connectorClass = (value) => computed(() => (value < props.phase ? 'bg-green-500' : 'bg-gray-200'));
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <template v-for="(step, index) in steps" :key="step.value">
            <div class="flex items-center gap-2">
                <div class="flex h-8 min-w-8 items-center justify-center rounded-full border px-2 text-xs font-semibold" :class="stepClass(step.value)">
                    {{ step.value + 1 }}
                </div>
                <span class="text-xs font-medium text-gray-700 dark:text-gray-200">{{ step.label }}</span>
            </div>
            <div
                v-if="index < steps.length - 1"
                class="h-1 w-8 rounded"
                :class="connectorClass(step.value).value"
            />
        </template>
    </div>
</template>
