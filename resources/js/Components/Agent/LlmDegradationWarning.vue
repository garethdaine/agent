<script setup>
defineProps({
    show: Boolean,
    ruleBasedResult: Object,
    errorMessage: String,
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div v-if="show" class="rounded-md border border-warning bg-warning/10 p-4">
        <div class="flex items-start gap-3">
            <svg class="h-5 w-5 text-warning" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <div class="flex-1">
                <h4 class="font-medium text-warning">Limited Parsing Available</h4>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ errorMessage || 'Advanced parsing is temporarily unavailable. A basic interpretation is shown below.' }}
                </p>

                <div v-if="ruleBasedResult" class="mt-3 rounded border border-border bg-background p-3">
                    <p class="text-sm"><strong>Interpreted as:</strong> {{ ruleBasedResult.explanation }}</p>
                    <p class="mt-1 font-mono text-sm">{{ ruleBasedResult.cron_expression }}</p>
                </div>

                <div class="mt-4 flex gap-3">
                    <button
                        type="button"
                        class="rounded-md bg-warning px-4 py-2 text-sm font-medium text-warning-foreground hover:bg-warning/90"
                        @click="$emit('confirm', ruleBasedResult)"
                    >
                        Use This Schedule
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-input px-4 py-2 text-sm hover:bg-muted"
                        @click="$emit('cancel')"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
