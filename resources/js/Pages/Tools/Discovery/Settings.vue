<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Search } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
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
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Search class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">Discovery Settings</h2>
                        <HelpHint
                            ui-key="discovery.settings"
                            short-text="Configure discovery defaults and provider settings."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.discovery.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Global Discovery Settings</CardTitle>
                        <CardDescription>Configure default behavior for all discovery sessions.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>

                        <Skeleton v-if="loading" class="h-32 w-full" />

                        <template v-else>
                            <div>
                                <label class="block text-sm font-medium">Default Runner</label>
                                <select
                                    v-model="form.default_runner"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="claude">claude</option>
                                    <option value="codex">codex</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium">System Prompt Override</label>
                                <Textarea v-model="form.system_prompt" :rows="14" class="mt-1 font-mono" />
                                <p class="mt-1 text-xs text-muted-foreground">Leave empty to use the built-in runtime-safe prompt.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium">Max Active Sessions</label>
                                <Input
                                    v-model.number="form.max_active_sessions"
                                    type="number"
                                    min="1"
                                    max="50"
                                    class="mt-1"
                                    :error="!!firstValidationError('value')"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">Maximum concurrently active discovery sessions per user.</p>
                                <p v-if="firstValidationError('value')" class="mt-1 text-sm text-destructive">{{ firstValidationError('value') }}</p>
                            </div>

                            <div class="flex justify-end">
                                <Button :disabled="saving || loading" @click="save">
                                    {{ saving ? 'Saving...' : 'Save Settings' }}
                                </Button>
                            </div>
                        </template>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
