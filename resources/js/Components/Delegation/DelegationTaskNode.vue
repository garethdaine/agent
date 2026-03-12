<script setup>
import { Handle } from '@vue-flow/core';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Info, X } from 'lucide-vue-next';
import { confirmDialog } from '@/Support/confirmDialog';

const props = defineProps({
    id: { type: String, default: '' },
    data: { type: Object, default: () => ({}) },
});

const label = computed(() => props.data?.label ?? props.data?.name ?? 'Task');
const status = computed(() => props.data?.status ?? 'pending');
const capability = computed(() => props.data?.capability ?? props.data?.contract_json?.required_capability ?? '');
const delegateeProfileName = computed(() => props.data?.delegateeProfileName ?? null);

const statusClass = computed(() => {
    const s = status.value;
    if (s === 'succeeded') return 'bg-emerald-100 dark:bg-emerald-900/30 border-emerald-300 dark:border-emerald-700';
    if (s === 'failed' || s === 'cancelled') return 'bg-red-100 dark:bg-red-900/30 border-red-300 dark:border-red-700';
    if (s === 'running' || s === 'verifying') return 'bg-blue-100 dark:bg-blue-900/30 border-blue-300 dark:border-blue-700';
    return 'bg-gray-100 dark:bg-gray-800 border-gray-300 dark:border-gray-600';
});

const showInfo = ref(false);
const popoverRef = ref(null);
const triggerRef = ref(null);

function toggleInfo(event) {
    event.stopPropagation();
    showInfo.value = !showInfo.value;
}

async function handleDelete(event) {
    event.stopPropagation();
    const taskName = props.data?.name ?? props.data?.label ?? 'this task';
    const approved = await confirmDialog(`Are you sure you want to delete "${taskName}"?`, {
        title: 'Delete Task',
        confirmText: 'Delete',
        confirmVariant: 'destructive',
    });
    if (approved && typeof props.data?.onDeleteNode === 'function') {
        props.data.onDeleteNode(props.id);
    }
}

function onClickOutside(event) {
    if (
        showInfo.value &&
        popoverRef.value &&
        !popoverRef.value.contains(event.target) &&
        triggerRef.value &&
        !triggerRef.value.contains(event.target)
    ) {
        showInfo.value = false;
    }
}

onMounted(() => document.addEventListener('pointerdown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onClickOutside));
</script>

<template>
    <div
        :class="[
            'group relative rounded-lg border px-3 py-2 w-[200px] shadow-sm',
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

        <!-- Delete cross — top-right, visible on hover -->
        <button
            type="button"
            class="absolute -top-2 -right-2 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-destructive text-destructive-foreground opacity-0 shadow-sm transition-opacity group-hover:opacity-100"
            aria-label="Delete task"
            @click="handleDelete"
            @pointerdown.stop
        >
            <X class="h-3 w-3" />
        </button>

        <div class="flex items-start justify-between gap-1">
            <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-foreground truncate max-w-[160px]">
                    {{ label }}
                </div>
                <div v-if="capability" class="text-xs text-muted-foreground truncate max-w-[160px] mt-0.5">
                    {{ capability }}
                </div>
            </div>
            <button
                ref="triggerRef"
                type="button"
                class="mt-0.5 shrink-0 rounded p-0.5 text-muted-foreground hover:text-foreground hover:bg-muted/50 transition-colors"
                aria-label="Task details"
                @click="toggleInfo"
                @pointerdown.stop
            >
                <Info class="h-3.5 w-3.5" />
            </button>
        </div>

        <!-- Info popover -->
        <div
            v-if="showInfo"
            ref="popoverRef"
            class="absolute left-1/2 top-full z-50 mt-2 w-56 -translate-x-1/2 rounded-lg border border-border bg-popover p-3 shadow-lg"
            @pointerdown.stop
        >
            <div class="space-y-2 text-xs">
                <div>
                    <div class="font-medium text-foreground">Capability</div>
                    <div class="text-muted-foreground">{{ capability || 'None' }}</div>
                </div>
                <div>
                    <div class="font-medium text-foreground">Delegatee profile</div>
                    <div class="text-muted-foreground">{{ delegateeProfileName ?? 'Unassigned' }}</div>
                </div>
                <div>
                    <div class="font-medium text-foreground">Status</div>
                    <div class="text-muted-foreground">{{ status }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
