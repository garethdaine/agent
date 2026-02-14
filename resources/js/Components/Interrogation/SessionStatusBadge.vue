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
        setup: 'bg-gray-100 text-gray-700 border-gray-200',
        discovering: 'bg-blue-100 text-blue-700 border-blue-200',
        interrogating: 'bg-indigo-100 text-indigo-700 border-indigo-200',
        summarizing: 'bg-purple-100 text-purple-700 border-purple-200',
        planning: 'bg-amber-100 text-amber-700 border-amber-200',
        paused: 'bg-slate-100 text-slate-700 border-slate-200',
        completed: 'bg-green-100 text-green-700 border-green-200',
        failed: 'bg-red-100 text-red-700 border-red-200',
    };

    return map[props.status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
});
</script>

<template>
    <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold" :class="colorClass">
        {{ status }}
    </span>
</template>
