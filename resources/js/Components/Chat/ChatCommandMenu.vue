<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Terminal } from 'lucide-vue-next';

const props = defineProps({
    commands: { type: Array, default: () => [] },
    visible: { type: Boolean, default: false },
    filter: { type: String, default: '' },
});

const emit = defineEmits(['select']);

const selectedIndex = ref(0);

const filtered = computed(() => {
    const q = props.filter.toLowerCase();
    if (!q) return props.commands;
    return props.commands.filter(
        (c) => c.name.includes(q) || c.description.toLowerCase().includes(q),
    );
});

watch(
    () => props.filter,
    () => {
        selectedIndex.value = 0;
    },
);

watch(
    () => props.visible,
    (v) => {
        if (v) selectedIndex.value = 0;
    },
);

const selectCurrent = () => {
    const cmd = filtered.value[selectedIndex.value];
    if (cmd) emit('select', cmd);
};

const moveUp = () => {
    if (selectedIndex.value > 0) selectedIndex.value--;
};

const moveDown = () => {
    if (selectedIndex.value < filtered.value.length - 1) selectedIndex.value++;
};

defineExpose({ selectCurrent, moveUp, moveDown, hasResults: () => filtered.value.length > 0 });
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-100 ease-out"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-75 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-1"
    >
        <div
            v-if="visible && filtered.length > 0"
            class="absolute bottom-full left-0 right-0 z-10 mb-1 mx-4 max-h-[280px] overflow-y-auto rounded-xl border border-border bg-popover shadow-lg"
        >
            <div class="py-1.5">
                <div class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <Terminal class="h-3 w-3" />
                    Commands
                </div>
                <button
                    v-for="(cmd, i) in filtered"
                    :key="cmd.name"
                    type="button"
                    class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors"
                    :class="i === selectedIndex ? 'bg-accent text-accent-foreground' : 'text-foreground hover:bg-muted'"
                    @mouseenter="selectedIndex = i"
                    @click="emit('select', cmd)"
                >
                    <span class="font-mono text-xs font-semibold text-primary">/{{ cmd.name }}</span>
                    <span class="text-xs text-muted-foreground truncate">{{ cmd.description }}</span>
                </button>
            </div>
        </div>
    </Transition>
</template>
