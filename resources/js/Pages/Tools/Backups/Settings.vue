<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Head } from '@inertiajs/vue3';
import { Play, Database } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';
import { formatDateTime } from '@/Utils/formatDate';

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
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Database class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground truncate">Database Backup Settings</h2>
                    <HelpHint
                        ui-key="backups.settings"
                        short-text="Confirm schedule, retention, and manual run behavior before enabling backups."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <Skeleton v-if="loading" class="h-8 w-48" />
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>
                <div v-if="success" class="rounded-md border border-success/50 bg-success/10 px-3 py-2 text-sm text-success">{{ success }}</div>

                <Card>
                    <CardHeader>
                        <CardTitle>Backup Configuration</CardTitle>
                        <CardDescription>Configure daily database backups and retention policy.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <label class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium">Enable Daily Database Backup</span>
                            <input v-model="form.is_enabled" type="checkbox" class="rounded border-input" />
                        </label>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Timezone</label>
                                <Input v-model="form.timezone" type="text" placeholder="UTC" />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium">Run Hour (0-23)</label>
                                <Input v-model.number="form.run_hour" type="number" min="0" max="23" />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium">Run Minute (0-59)</label>
                                <Input v-model.number="form.run_minute" type="number" min="0" max="59" />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium">Retention Days</label>
                            <Input v-model.number="form.retention_days" type="number" min="1" max="365" />
                        </div>

                        <div class="rounded-md border border-border bg-muted/50 px-3 py-2 text-sm">
                            <p><span class="font-medium">Configured Run Time:</span> {{ runTimeLabel }} ({{ form.timezone }})</p>
                            <p><span class="font-medium">Last Run At:</span> {{ form.last_run_at ? formatDateTime(form.last_run_at) : 'Never' }}</p>
                            <p><span class="font-medium">Last Status:</span> {{ form.last_status || 'N/A' }}</p>
                            <p><span class="font-medium">Last Error:</span> {{ form.last_error || 'None' }}</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button :disabled="saving" @click="save">
                                {{ saving ? 'Saving...' : 'Save Settings' }}
                            </Button>
                            <Button variant="outline" :disabled="running" @click="runNow">
                                <Play class="h-4 w-4" />
                                {{ running ? 'Running Backup...' : 'Run Backup Now' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
