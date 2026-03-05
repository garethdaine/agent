<script setup>
import { Handle } from '@vue-flow/core';
import { computed } from 'vue';

const props = defineProps({
    id: { type: String, default: '' },
    data: { type: Object, default: () => ({}) },
});

const label = computed(() => props.data?.name ?? props.data?.label ?? 'Agent');
const role = computed(() => props.data?.role_slug ?? props.data?.role_description ?? '');
const isNew = computed(() => props.data?.isNew === true);
</script>

<template>
    <div
        :class="[
            'rounded-lg border px-3 py-2 min-w-[140px] shadow-sm',
            isNew
                ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-300 dark:border-amber-700'
                : 'bg-background border-border',
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
        <div v-if="role" class="text-xs text-muted-foreground truncate max-w-[180px] mt-0.5">
            {{ role }}
        </div>
        <div v-if="isNew" class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
            New (unsaved)
        </div>
    </div>
</template>
