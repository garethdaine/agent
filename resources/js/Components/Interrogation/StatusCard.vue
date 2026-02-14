<script setup>
defineProps({
    session: {
        type: Object,
        required: true,
    },
    latestDiscoveryEvent: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/50 dark:bg-blue-950/30">
        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Discovery Status</p>
        <h3 class="mt-1 text-base font-semibold text-blue-900 dark:text-blue-200">{{ session.status }}</h3>
        <p class="mt-2 text-sm text-blue-800 dark:text-blue-300">
            {{ latestDiscoveryEvent?.payload?.message || 'Analyzing repository and gathering context...' }}
        </p>
    </div>
</template>
