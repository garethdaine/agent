<script setup>
import { computed } from 'vue';
import { cn } from './utils';

const props = defineProps({
  variant: { type: String, default: 'default' },
});

const variantClasses = {
  default: 'border-transparent bg-primary text-primary-foreground',
  secondary: 'border-transparent bg-secondary text-secondary-foreground',
  destructive: 'border-transparent bg-destructive text-destructive-foreground',
  outline: 'text-foreground border-border',
};

const classes = computed(() => cn(
  'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium transition-colors',
  'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
  '[&>svg]:size-3 [&>svg]:mr-1',
  variantClasses[props.variant]
));
</script>

<template>
  <span :class="classes"><slot /></span>
</template>
