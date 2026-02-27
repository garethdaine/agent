<script setup>
import { computed, ref, onMounted } from 'vue';
import { cn } from './utils';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  size: { type: String, default: 'default' },
  disabled: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const input = ref(null);

const sizeClasses = { default: 'h-9', sm: 'h-8 text-xs', lg: 'h-10' };

const classes = computed(() => cn(
  'flex w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm transition-colors',
  'file:border-0 file:bg-transparent file:text-sm file:font-medium',
  'placeholder:text-muted-foreground',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
  'disabled:cursor-not-allowed disabled:opacity-50',
  sizeClasses[props.size],
  props.error && 'border-destructive focus-visible:ring-destructive'
));

onMounted(() => {
  if (input.value?.hasAttribute('autofocus')) {
    input.value.focus();
  }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
  <input
    ref="input"
    :type="type"
    :class="classes"
    :value="modelValue"
    :disabled="disabled"
    @input="emit('update:modelValue', $event.target.value)"
  />
</template>
