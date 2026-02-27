<script setup>
import { computed } from 'vue';
import { cn } from './utils';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },
  size: { type: String, default: 'default' },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const sizeClasses = { default: 'h-9', sm: 'h-8 text-xs', lg: 'h-10' };

const classes = computed(() => cn(
  'flex w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
  'disabled:cursor-not-allowed disabled:opacity-50',
  sizeClasses[props.size]
));
</script>

<template>
  <select :class="classes" :value="modelValue" :disabled="disabled" @change="emit('update:modelValue', $event.target.value)">
    <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
    <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
  </select>
</template>
