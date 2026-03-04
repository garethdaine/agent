<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const opening = ref(false);
const pickerError = ref('');

const disableControls = computed(() => props.disabled || opening.value);

const updateModelValue = (value) => {
    emit('update:modelValue', value);
    if (pickerError.value !== '') {
        pickerError.value = '';
    }
};

const openPicker = async () => {
    if (disableControls.value) {
        return;
    }

    opening.value = true;
    pickerError.value = '';

    const payload = {};
    if (typeof props.modelValue === 'string' && props.modelValue.trim() !== '') {
        payload.current_path = props.modelValue.trim();
    }

    try {
        const { data } = await axios.post('/agent/api/v1/system/directory-picker', payload);
        const selectedPath = data?.data?.path;

        if (typeof selectedPath === 'string' && selectedPath.trim() !== '') {
            updateModelValue(selectedPath);
        }
    } catch (error) {
        const payloadError = error?.response?.data ?? {};
        const code = payloadError?.error?.code ?? '';

        if (code === 'DIRECTORY_PICKER_CANCELLED') {
            return;
        }

        pickerError.value = payloadError?.error?.message ?? payloadError?.message ?? 'Unable to open directory picker.';
    } finally {
        opening.value = false;
    }
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <Input
                :model-value="modelValue"
                :disabled="disableControls"
                :error="error"
                type="text"
                class="font-mono sm:flex-1"
                :placeholder="placeholder"
                @update:model-value="updateModelValue"
            />
            <Button type="button" variant="outline" class="shrink-0 sm:w-auto" :disabled="disableControls" @click="openPicker">
                {{ opening ? 'Opening...' : 'Browse...' }}
            </Button>
        </div>
        <p v-if="pickerError" class="text-xs text-destructive">{{ pickerError }}</p>
    </div>
</template>
