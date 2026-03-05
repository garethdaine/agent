<script setup>
import { Handle } from '@vue-flow/core';
import { computed } from 'vue';

const props = defineProps({
    id: { type: String, default: '' },
    data: { type: Object, default: () => ({}) },
});

const label = computed(() => props.data?.label ?? props.data?.name ?? 'Task');
const status = computed(() => props.data?.status ?? 'pending');
const capability = computed(() => props.data?.capability ?? props.data?.contract_json?.required_capability ?? '');

const statusClass = computed(() => {
    const s = status.value;
    if (s === 'succeeded') return 'bg-emerald-100 dark:bg-emerald-900/30 border-emerald-300 dark:border-emerald-700';
    if (s === 'failed' || s === 'cancelled') return 'bg-red-100 dark:bg-red-900/30 border-red-300 dark:border-red-700';
    if (s === 'running' || s === 'verifying') return 'bg-blue-100 dark:bg-blue-900/30 border-blue-300 dark:border-blue-700';
    return 'bg-gray-100 dark:bg-gray-800 border-gray-300 dark:border-gray-600';
});
</script>

<template>
    <div
        :class="[
            'rounded-lg border px-3 py-2 min-w-[140px] shadow-sm',
            statusClass,
        ]"
    >
        <Handle id="top-source" type="source" position="top" class="!w-2 !h-2 !bg-primary" />
        <Handle id="top-target" type="target" position="top" class="!w-2 !h-2 !bg-primary" />
        <Handle id="bottom-source" type="source" position="bottom" class="!w-2 !h-2 !bg-primary" />
        <Handle id="bottom-target" type="target" position="bottom" class="!w-2 !h-2 !bg-primary" />
        <Handle id="left-source" type="source" position="left" class="!w-2 !h-2 !bg-primary" />
        <Handle id="left-target" type="target" position="left" class="!w-2 !h-2 !bg-primary" />
        <Handle id="right-source" type="source" position="right" class="!w-2 !h-2 !bg-primary" />
        <Handle id="right-target" type="target" position="right" class="!w-2 !h-2 !bg-primary" />
        <div class="text-sm font-medium text-foreground truncate max-w-[180px]">
            {{ label }}
        </div>
        <div v-if="capability" class="text-xs text-muted-foreground truncate max-w-[180px] mt-0.5">
            {{ capability }}
        </div>
    </div>
</template>
