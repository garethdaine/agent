<script setup>
import { computed } from 'vue';
import { cn } from './utils';

const props = defineProps({
  modelValue: { type: String, default: '' },
  rows: { type: Number, default: 3 },
  disabled: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const classes = computed(() => cn(
  'flex min-h-[60px] w-full rounded-md border border-input bg-input-background px-3 py-2 text-sm shadow-sm',
  'placeholder:text-muted-foreground',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
  'disabled:cursor-not-allowed disabled:opacity-50',
  props.error && 'border-destructive focus-visible:ring-destructive'
));
</script>

<template>
  <textarea :class="classes" :rows="rows" :value="modelValue" :disabled="disabled" @input="emit('update:modelValue', $event.target.value)" />
</template>
