<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue']);

const toggle = (current, disabled) => {
    if (!disabled) {
        emit('update:modelValue', !current);
    }
};

const onKeydown = (e, current, disabled) => {
    if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        toggle(current, disabled);
    }
};
</script>

<template>
    <button
        type="button"
        role="switch"
        :id="id"
        :aria-checked="modelValue"
        :disabled="disabled"
        :class="[
            'relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
            modelValue ? 'bg-primary' : 'bg-muted-foreground/25',
            disabled ? 'cursor-not-allowed opacity-50' : '',
        ]"
        @click="toggle(modelValue, disabled)"
        @keydown="onKeydown($event, modelValue, disabled)"
    >
        <span
            :class="[
                'pointer-events-none inline-block h-3.5 w-3.5 rounded-full bg-background shadow-sm ring-0 transition-transform duration-200 ease-in-out',
                modelValue ? 'translate-x-4' : 'translate-x-0.5',
            ]"
        />
    </button>
</template>
