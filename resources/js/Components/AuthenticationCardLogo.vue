<script setup>
import { Link } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
</script>

<template>
  <Link href="/" class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
      <Clock class="w-5 h-5 text-primary-foreground" />
    </div>
    <span class="text-foreground font-semibold text-xl">Agent Scheduler</span>
  </Link>
</template>
