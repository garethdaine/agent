<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Key } from 'lucide-vue-next';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref('');
const success = ref('');
const providers = ref([]);
const editing = ref(null);
const formValue = ref('');
const formSaving = ref(false);

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/credentials');
        providers.value = data?.data?.providers ?? [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load credentials.';
    } finally {
        loading.value = false;
    }
};

const startEdit = (providerKey, keyName) => {
    editing.value = `${providerKey}:${keyName}`;
    formValue.value = '';
    formSaving.value = false;
};

const cancelEdit = () => {
    editing.value = null;
    formValue.value = '';
};

const saveCredential = async (providerKey, keyName) => {
    if (!formValue.value.trim()) return;

    formSaving.value = true;
    error.value = '';
    success.value = '';

    try {
        await axios.post('/agent/api/v1/credentials', {
            provider: providerKey,
            key: keyName,
            value: formValue.value.trim(),
        });
        success.value = 'Credential saved.';
        editing.value = null;
        formValue.value = '';
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? e?.response?.data?.error?.details?.value?.[0] ?? 'Failed to save.';
    } finally {
        formSaving.value = false;
    }
};

const removeCredential = async (providerKey, keyName) => {
    if (!confirm(`Remove ${keyName} for ${providerKey}?`)) return;

    error.value = '';
    success.value = '';

    try {
        await axios.delete('/agent/api/v1/credentials', {
            params: { provider: providerKey, key: keyName },
        });
        success.value = 'Credential removed.';
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to remove.';
    }
};

const isEditing = (providerKey, keyName) => editing.value === `${providerKey}:${keyName}`;

onMounted(load);
</script>

<template>
    <AppLayout title="Credentials">
        <Head title="Credentials" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Key class="h-5 w-5 text-primary" />
                    </div>
                    <h2 class="text-base font-semibold text-foreground truncate">Credentials</h2>
                </div>
                <Link :href="route('tools.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <p v-if="success" class="rounded-md border border-green-500/50 bg-green-500/10 px-3 py-2 text-sm text-green-700 dark:text-green-400">
                    {{ success }}
                </p>
                <p v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </p>

                <p class="text-sm text-muted-foreground">
                    Store API keys for OpenAI, Anthropic, and GitHub. Values are encrypted and never shown after saving.
                </p>

                <div v-if="loading" class="space-y-3">
                    <div class="h-24 animate-pulse rounded-lg bg-muted" />
                    <div class="h-24 animate-pulse rounded-lg bg-muted" />
                </div>

                <div v-else class="space-y-4">
                    <Card v-for="provider in providers" :key="provider.key">
                        <CardHeader>
                            <CardTitle class="text-base">{{ provider.label }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="key in provider.keys"
                                :key="key.key"
                                class="flex flex-wrap items-center gap-2 rounded-md border border-border bg-muted/30 px-3 py-2"
                            >
                                <span class="font-mono text-sm text-muted-foreground">{{ key.key }}</span>
                                <Badge v-if="key.has_value && !isEditing(provider.key, key.key)" variant="secondary" class="text-xs">
                                    Configured
                                </Badge>
                                <template v-if="isEditing(provider.key, key.key)">
                                    <input
                                        v-model="formValue"
                                        type="password"
                                        placeholder="Enter value..."
                                        class="min-w-[200px] rounded border border-input bg-background px-2 py-1 text-sm"
                                        @keydown.enter="saveCredential(provider.key, key.key)"
                                    />
                                    <Button size="sm" :disabled="formSaving || !formValue.trim()" @click="saveCredential(provider.key, key.key)">
                                        Save
                                    </Button>
                                    <Button size="sm" variant="ghost" @click="cancelEdit">Cancel</Button>
                                </template>
                                <template v-else>
                                    <Button size="sm" variant="outline" @click="startEdit(provider.key, key.key)">
                                        {{ key.has_value ? 'Update' : 'Set' }}
                                    </Button>
                                    <Button
                                        v-if="key.has_value"
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive hover:text-destructive"
                                        @click="removeCredential(provider.key, key.key)"
                                    >
                                        Remove
                                    </Button>
                                </template>
                            </div>
                        </CardContent>
                    </Card>

                    <p v-if="providers.length === 0" class="text-sm text-muted-foreground">No credential providers configured.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
