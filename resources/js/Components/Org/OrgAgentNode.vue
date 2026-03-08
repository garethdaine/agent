<script setup>
import { Handle } from '@vue-flow/core';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Info, X } from 'lucide-vue-next';
import { confirmDialog } from '@/Support/confirmDialog';

const props = defineProps({
    id: { type: String, default: '' },
    data: { type: Object, default: () => ({}) },
});

const label = computed(() => props.data?.name ?? props.data?.label ?? 'Agent');
const role = computed(() => props.data?.role_slug ?? props.data?.role_description ?? '');
const isNew = computed(() => props.data?.isNew === true);
const diffState = computed(() => props.data?.diffState ?? null);

const roleDescription = computed(() => props.data?.role_description ?? '');
const parentAgentName = computed(() => props.data?.parentAgentName ?? null);
const delegateeProfileName = computed(() => props.data?.delegateeProfileName ?? null);

const showInfo = ref(false);
const popoverRef = ref(null);
const triggerRef = ref(null);

function toggleInfo(event) {
    event.stopPropagation();
    showInfo.value = !showInfo.value;
}

async function handleDelete(event) {
    event.stopPropagation();
    const agentName = props.data?.name ?? 'this agent';
    const approved = await confirmDialog(`Are you sure you want to delete "${agentName}"?`, {
        title: 'Delete Agent',
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
            'group relative rounded-lg border px-3 py-2 min-w-[140px] shadow-sm',
            diffState === 'add'
                ? 'border-green-500 bg-green-50 border-dashed'
                : diffState === 'update'
                    ? 'border-amber-500 bg-amber-50'
                    : diffState === 'remove'
                        ? 'border-red-500 bg-red-50 opacity-60'
                        : isNew
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

        <!-- Delete cross — top-right, visible on hover -->
        <button
            type="button"
            class="absolute -top-2 -right-2 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-destructive text-destructive-foreground opacity-0 shadow-sm transition-opacity group-hover:opacity-100"
            aria-label="Delete agent"
            @click="handleDelete"
            @pointerdown.stop
        >
            <X class="h-3 w-3" />
        </button>

        <div class="flex items-start justify-between gap-1">
            <div class="min-w-0 flex-1">
                <div
                    :class="[
                        'text-sm font-medium text-foreground truncate max-w-[160px]',
                        diffState === 'remove' ? 'line-through' : '',
                    ]"
                >
                    {{ label }}
                </div>
                <div v-if="role" class="text-xs text-muted-foreground truncate max-w-[160px] mt-0.5">
                    {{ role }}
                </div>
                <div v-if="isNew" class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                    New (unsaved)
                </div>
            </div>
            <button
                ref="triggerRef"
                type="button"
                class="mt-0.5 shrink-0 rounded p-0.5 text-muted-foreground hover:text-foreground hover:bg-muted/50 transition-colors"
                aria-label="Agent details"
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
                <div v-if="roleDescription">
                    <div class="font-medium text-foreground">Role description</div>
                    <div class="text-muted-foreground">{{ roleDescription }}</div>
                </div>
                <div>
                    <div class="font-medium text-foreground">Reports to</div>
                    <div class="text-muted-foreground">{{ parentAgentName ?? 'None' }}</div>
                </div>
                <div>
                    <div class="font-medium text-foreground">Delegatee profile</div>
                    <div class="text-muted-foreground">{{ delegateeProfileName ?? 'Unassigned' }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
