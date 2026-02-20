<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';

const loading = ref(false);
const saving = ref(false);
const running = ref(false);
const success = ref('');
const error = ref('');

const form = reactive({
    is_enabled: true,
    timezone: 'UTC',
    run_hour: 2,
    run_minute: 0,
    retention_days: 14,
    last_run_at: null,
    last_status: null,
    last_error: null,
    updated_at: null,
});

const runTimeLabel = computed(() => {
    const hour = String(form.run_hour).padStart(2, '0');
    const minute = String(form.run_minute).padStart(2, '0');

    return `${hour}:${minute}`;
});

const load = async () => {
    loading.value = true;
    error.value = '';
    success.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/backups/settings');
        Object.assign(form, data.data || {});
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load backup settings.';
    } finally {
        loading.value = false;
    }
};

const save = async () => {
    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        const { data } = await axios.put('/agent/api/v1/backups/settings', {
            is_enabled: form.is_enabled,
            timezone: form.timezone,
            run_hour: Number(form.run_hour),
            run_minute: Number(form.run_minute),
            retention_days: Number(form.retention_days),
        });

        Object.assign(form, data.data || {});
        success.value = 'Backup settings saved.';
    } catch (e) {
        const payload = e?.response?.data ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to save backup settings.';
    } finally {
        saving.value = false;
    }
};

const runNow = async () => {
    running.value = true;
    error.value = '';
    success.value = '';

    try {
        const { data } = await axios.post('/agent/api/v1/backups/run-now');
        Object.assign(form, data?.data?.settings || {});
        success.value = 'Backup run completed successfully.';
    } catch (e) {
        const payload = e?.response?.data ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Backup run failed.';
    } finally {
        running.value = false;
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Backup Settings">
        <Head title="Backup Settings" />

        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Database Backup Settings</h2>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-4">
                <p v-if="loading" class="text-sm text-gray-500">Loading backup settings...</p>
                <p v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
                <p v-if="success" class="rounded-md border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700">{{ success }}</p>

                <div class="space-y-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                    <label class="flex items-center justify-between gap-3 text-sm text-gray-700 dark:text-gray-200">
                        <span class="font-medium">Enable Daily Database Backup</span>
                        <input v-model="form.is_enabled" type="checkbox" class="rounded border-gray-300" />
                    </label>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <label class="text-sm text-gray-700 dark:text-gray-200">
                            <span class="mb-1 block font-medium">Timezone</span>
                            <input v-model="form.timezone" type="text" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" placeholder="UTC" />
                        </label>

                        <label class="text-sm text-gray-700 dark:text-gray-200">
                            <span class="mb-1 block font-medium">Run Hour (0-23)</span>
                            <input v-model.number="form.run_hour" type="number" min="0" max="23" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                        </label>

                        <label class="text-sm text-gray-700 dark:text-gray-200">
                            <span class="mb-1 block font-medium">Run Minute (0-59)</span>
                            <input v-model.number="form.run_minute" type="number" min="0" max="59" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                        </label>
                    </div>

                    <label class="block text-sm text-gray-700 dark:text-gray-200">
                        <span class="mb-1 block font-medium">Retention Days</span>
                        <input v-model.number="form.retention_days" type="number" min="1" max="365" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                    </label>

                    <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <p><span class="font-medium">Configured Run Time:</span> {{ runTimeLabel }} ({{ form.timezone }})</p>
                        <p><span class="font-medium">Last Run At:</span> {{ form.last_run_at || 'Never' }}</p>
                        <p><span class="font-medium">Last Status:</span> {{ form.last_status || 'N/A' }}</p>
                        <p><span class="font-medium">Last Error:</span> {{ form.last_error || 'None' }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="saving"
                            @click="save"
                        >
                            {{ saving ? 'Saving...' : 'Save Settings' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200"
                            :disabled="running"
                            @click="runNow"
                        >
                            {{ running ? 'Running Backup...' : 'Run Backup Now' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
