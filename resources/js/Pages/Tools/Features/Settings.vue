<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const loading = ref(false);
const saving = ref(false);
const success = ref('');
const error = ref('');
const flags = ref([]);

const hasFlags = computed(() => flags.value.length > 0);

const load = async () => {
    loading.value = true;
    error.value = '';
    success.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/features/settings');
        flags.value = (data.data || []).map((flag) => ({
            ...flag,
            is_enabled: Boolean(flag.is_enabled),
        }));
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load feature settings.';
    } finally {
        loading.value = false;
    }
};

const save = async () => {
    saving.value = true;
    error.value = '';
    success.value = '';

    const payload = {};
    for (const flag of flags.value) {
        payload[flag.key] = Boolean(flag.is_enabled);
    }

    try {
        const { data } = await axios.put('/agent/api/v1/features/settings', {
            flags: payload,
        });

        flags.value = (data.data || []).map((flag) => ({
            ...flag,
            is_enabled: Boolean(flag.is_enabled),
        }));
        success.value = 'Feature settings saved.';
    } catch (e) {
        const response = e?.response?.data ?? {};
        error.value = response?.error?.message ?? response?.message ?? 'Failed to save feature settings.';
    } finally {
        saving.value = false;
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Feature Settings">
        <Head title="Feature Settings" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Feature Settings</h2>
                    <HelpHint
                        ui-key="features.settings"
                        short-text="Review feature-flag impact before enabling flags in production workflows."
                        learn-more-href="/docs/overview"
                    />
                </div>
                <Link :href="route('tools.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-4">
                <Skeleton v-if="loading" class="h-8 w-48" />
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>
                <div v-if="success" class="rounded-md border border-success/50 bg-success/10 px-3 py-2 text-sm text-success">{{ success }}</div>

                <Card>
                    <CardHeader>
                        <CardTitle>Feature Flags</CardTitle>
                        <CardDescription>Enable or disable runtime feature flags without changing environment variables.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Card v-for="flag in flags" :key="flag.key">
                            <CardContent class="pt-6">
                                <label class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold">{{ flag.label }}</p>
                                        <p class="mt-1 text-sm text-muted-foreground">{{ flag.description }}</p>
                                        <p class="mt-2 text-xs text-muted-foreground">
                                            Key: <code class="rounded bg-muted px-1 py-0.5">{{ flag.key }}</code>
                                        </p>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                            <span>Default: <strong>{{ flag.default_enabled ? 'Enabled' : 'Disabled' }}</strong></span>
                                            <Badge v-if="flag.is_overridden" variant="secondary">Overridden</Badge>
                                        </div>
                                    </div>
                                    <input v-model="flag.is_enabled" type="checkbox" class="mt-1 rounded border-input" />
                                </label>
                            </CardContent>
                        </Card>

                        <p v-if="!loading && !hasFlags" class="text-sm text-muted-foreground">No managed feature flags available.</p>

                        <div class="flex justify-end">
                            <Button :disabled="saving || loading || !hasFlags" @click="save">
                                {{ saving ? 'Saving...' : 'Save Settings' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
