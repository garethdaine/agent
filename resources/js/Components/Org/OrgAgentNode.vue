<script setup>
import { Handle, useNode } from '@vue-flow/core';
import { computed } from 'vue';

const { data } = useNode();

const label = computed(() => data?.name ?? data?.label ?? 'Agent');
const role = computed(() => data?.role_slug ?? data?.role_description ?? '');
const isNew = computed(() => data?.isNew === true);
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
        <Handle type="target" :position="'left'" class="!w-2 !h-2 !bg-primary" />
        <div class="text-sm font-medium text-foreground truncate max-w-[180px]">
            {{ label }}
        </div>
        <div v-if="role" class="text-xs text-muted-foreground truncate max-w-[180px] mt-0.5">
            {{ role }}
        </div>
        <div v-if="isNew" class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
            New (unsaved)
        </div>
        <Handle type="source" :position="'right'" class="!w-2 !h-2 !bg-primary" />
    </div>
</template>
