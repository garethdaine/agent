<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, reactive, ref } from 'vue';

const settings = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const validation = ref({});

const firstValidationError = (field) => {
    const messages = validation.value?.[field];

    return Array.isArray(messages) && messages.length > 0 ? messages[0] : '';
};

const form = reactive({
    system_prompt: '',
    default_runner: 'claude',
    max_active_sessions: 3,
});

const load = async () => {
    loading.value = true;
    error.value = '';
    validation.value = {};

    try {
        const { data } = await axios.get('/agent/api/v1/interrogation/settings');
        settings.value = data.data || [];

        const prompt = settings.value.find((item) => item.key === 'interrogation.system_prompt');
        const runner = settings.value.find((item) => item.key === 'interrogation.default_runner');
        const maxActive = settings.value.find((item) => item.key === 'interrogation.max_active_sessions');

        form.system_prompt = typeof prompt?.value === 'string' ? prompt.value : (prompt?.value?.text || '');
        form.default_runner = typeof runner?.value === 'string' ? runner.value : 'claude';
        const parsedMaxActive = Number.parseInt(String(maxActive?.value ?? ''), 10);
        form.max_active_sessions = Number.isInteger(parsedMaxActive) && parsedMaxActive > 0 ? parsedMaxActive : 3;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load settings.';
    } finally {
        loading.value = false;
    }
};

const save = async () => {
    saving.value = true;
    error.value = '';
    validation.value = {};

    try {
        await axios.put('/agent/api/v1/interrogation/settings/interrogation.system_prompt', {
            value: form.system_prompt,
        });

        await axios.put('/agent/api/v1/interrogation/settings/interrogation.default_runner', {
            value: form.default_runner,
        });

        await axios.put('/agent/api/v1/interrogation/settings/interrogation.max_active_sessions', {
            value: Number(form.max_active_sessions),
        });

        await load();
    } catch (e) {
        const payload = e?.response?.data ?? {};
        const envelope = payload?.error ?? null;

        if (envelope) {
            validation.value = envelope?.details ?? {};
            error.value = envelope?.message ?? 'Failed to save settings.';
        } else if (payload?.errors && typeof payload.errors === 'object') {
            validation.value = payload.errors;
            error.value = payload?.message ?? 'The given data was invalid.';
        } else {
            validation.value = {};
            error.value = payload?.message ?? 'Failed to save settings.';
        }
    } finally {
        saving.value = false;
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Discovery Settings">
        <Head title="Discovery Settings" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Discovery Settings</h2>
                <Link :href="route('tools.discovery.index')" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Back</Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Default Runner</label>
                    <select v-model="form.default_runner" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="claude">claude</option>
                        <option value="codex">codex</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">System Prompt Override</label>
                    <textarea v-model="form.system_prompt" rows="14" class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm dark:border-gray-700 dark:bg-gray-900" />
                    <p class="mt-1 text-xs text-gray-500">Leave empty to use the built-in runtime-safe prompt.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Max Active Sessions</label>
                    <input
                        v-model.number="form.max_active_sessions"
                        type="number"
                        min="1"
                        max="50"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    />
                    <p class="mt-1 text-xs text-gray-500">Maximum concurrently active discovery sessions per user.</p>
                    <p v-if="firstValidationError('value')" class="mt-1 text-sm text-red-600">{{ firstValidationError('value') }}</p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="saving || loading"
                        @click="save"
                    >
                        {{ saving ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
