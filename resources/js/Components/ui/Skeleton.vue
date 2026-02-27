<script setup>
defineProps({ class: { type: String, default: '' } });
</script>

<template>
  <div :class="['animate-pulse rounded-md bg-accent', $props.class]" />
</template>
