<script setup>
import { ref, watch } from 'vue';
import { ChevronRight } from 'lucide-vue-next';

const STORAGE_KEY = 'agent-ops-sidebar-groups';

const props = defineProps({
    label: String,
    collapsed: Boolean,
    defaultOpen: { type: Boolean, default: true },
});

const readStored = () => {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        return props.label in stored ? stored[props.label] : props.defaultOpen;
    } catch {
        return props.defaultOpen;
    }
};

const persist = (value) => {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        stored[props.label] = value;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
    } catch { /* silent */ }
};

const open = ref(readStored());

watch(() => props.collapsed, (isCollapsed) => {
    if (isCollapsed) {
        open.value = true;
    }
});

const toggle = () => {
    if (!props.collapsed) {
        open.value = !open.value;
        persist(open.value);
    }
};
</script>

<template>
    <div>
        <button
            v-if="!collapsed"
            type="button"
            class="flex w-full items-center justify-between px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/60 hover:text-muted-foreground transition-colors duration-150"
            @click="toggle"
        >
            <span>{{ label }}</span>
            <ChevronRight
                class="h-3 w-3 transition-transform duration-200"
                :class="{ 'rotate-90': open }"
            />
        </button>

        <div v-if="collapsed" class="mx-auto my-1.5 w-6 border-t border-border/50" />

        <div
            class="grid transition-[grid-template-rows] duration-200 ease-in-out"
            :class="open || collapsed ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
        >
            <div class="overflow-hidden">
                <div class="space-y-0.5 py-1" :class="collapsed ? 'px-0' : 'px-1'">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
