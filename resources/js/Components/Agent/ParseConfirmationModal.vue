<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    result: Object,
    ambiguousOptions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['confirm', 'cancel', 'editAdvanced']);

const nextRuns = computed(() => props.result?.next_runs || []);
const hasActiveHours = computed(() => props.result?.active_hours !== null);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50" @click.self></div>

        <!-- Modal -->
        <div class="relative z-10 w-full max-w-lg rounded-lg bg-background p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-foreground">Confirm Schedule</h3>

            <div class="mt-4 space-y-4">
                <!-- Cron Expression (read-only) -->
                <div>
                    <label class="block text-sm font-medium text-muted-foreground">Generated Cron Expression</label>
                    <div class="mt-1 rounded-md border border-input bg-muted px-3 py-2 font-mono text-sm">
                        {{ result?.cron_expression }}
                    </div>
                </div>

                <!-- Explanation -->
                <div>
                    <label class="block text-sm font-medium text-muted-foreground">Schedule Description</label>
                    <p class="mt-1 text-sm text-foreground">{{ result?.explanation }}</p>
                </div>

                <!-- Timezone -->
                <div>
                    <label class="block text-sm font-medium text-muted-foreground">Timezone</label>
                    <p class="mt-1 text-sm text-foreground">{{ result?.timezone }}</p>
                </div>

                <!-- Active Hours Summary -->
                <div v-if="hasActiveHours">
                    <label class="block text-sm font-medium text-muted-foreground">Active Hours</label>
                    <p class="mt-1 text-sm text-foreground">
                        {{ result.active_hours.start }} - {{ result.active_hours.end }}
                        (Days: {{ result.active_hours.days.join(', ') }})
                    </p>
                </div>

                <!-- Next 5 Runs -->
                <div>
                    <label class="block text-sm font-medium text-muted-foreground">Next 5 Runs</label>
                    <ul class="mt-1 space-y-1 text-sm">
                        <li v-for="(run, index) in nextRuns" :key="index" class="flex justify-between">
                            <span>{{ run.local }}</span>
                            <span class="text-muted-foreground">(UTC: {{ run.utc }})</span>
                        </li>
                    </ul>
                </div>

                <!-- Ambiguous Options (AM/PM) -->
                <div v-if="ambiguousOptions.length > 0" class="rounded-md border border-warning/50 bg-warning/10 p-3">
                    <p class="text-sm font-medium text-warning">Ambiguous time detected</p>
                    <p class="mt-1 text-sm text-muted-foreground">Please confirm the intended time:</p>
                    <div class="mt-2 flex gap-2">
                        <button
                            v-for="option in ambiguousOptions"
                            :key="option.value"
                            type="button"
                            class="rounded border border-input px-3 py-1 text-sm hover:bg-muted"
                            @click="$emit('confirm', option)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-md border border-input px-4 py-2 text-sm hover:bg-muted"
                    @click="$emit('cancel')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="rounded-md border border-primary px-4 py-2 text-sm text-primary hover:bg-primary/10"
                    @click="$emit('editAdvanced')"
                >
                    Edit in Advanced Mode
                </button>
                <button
                    v-if="ambiguousOptions.length === 0"
                    type="button"
                    class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90"
                    @click="$emit('confirm', result)"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
</template>
