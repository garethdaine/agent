<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    timezone: {
        type: String,
        default: 'UTC',
    },
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'parsed', 'error', 'queued']);

const input = ref(props.modelValue);
const isLoading = ref(false);
const errorMessage = ref(null);

const maxLength = 200;
const charCount = computed(() => input.value.length);
const isOverLimit = computed(() => charCount.value > maxLength);

const parse = async () => {
    if (isOverLimit.value || !input.value.trim()) {
        return;
    }

    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.post('/internal/api/schedule/parse', {
            input: input.value,
            timezone: props.timezone,
        });

        if (response.data.status === 'completed') {
            emit('parsed', response.data);
        } else if (response.data.status === 'queued') {
            emit('queued', response.data.parse_attempt_id);
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to parse schedule';
        emit('error', errorMessage.value);
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="space-y-3">
        <div>
            <label class="block text-sm font-medium text-foreground">Describe your schedule</label>
            <textarea
                v-model="input"
                rows="2"
                :maxlength="maxLength"
                class="mt-1 w-full rounded-md border border-input bg-input-background focus-visible:ring-2 focus-visible:ring-ring"
                placeholder="e.g., every weekday at 9am, every 2 hours from 9am to 5pm"
            />
            <div class="mt-1 flex justify-between text-xs">
                <span class="text-muted-foreground">Describe your schedule in plain English</span>
                <span :class="isOverLimit ? 'text-destructive' : 'text-muted-foreground'">
                    {{ charCount }}/{{ maxLength }}
                </span>
            </div>
        </div>

        <div v-if="errorMessage" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {{ errorMessage }}
        </div>

        <button
            type="button"
            :disabled="isLoading || isOverLimit || !input.trim()"
            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
            @click="parse"
        >
            <span v-if="isLoading">Parsing...</span>
            <span v-else>Parse Schedule</span>
        </button>
    </div>
</template>
