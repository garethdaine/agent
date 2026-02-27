<script setup>
import { computed } from 'vue';

const props = defineProps({
  size: { type: String, default: 'default' },
});

const sizeClasses = {
  sm: 'h-4 w-4 border-2',
  default: 'h-6 w-6 border-2',
  lg: 'h-8 w-8 border-3',
};
</script>

<template>
  <div :class="['animate-spin rounded-full border-primary border-t-transparent', sizeClasses[size]]" />
</template>
