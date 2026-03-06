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
import { Head } from '@inertiajs/vue3';
import {
    Key,
    Plus,
    Trash2,
    Eye,
    EyeOff,
    ShieldCheck,
    CheckCircle,
    XCircle,
} from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted } from 'vue';

const providers = ref([]);
const loading = ref(false);
const error = ref('');
const success = ref('');
const adding = ref(null);
const newKey = ref('');
const newValue = ref('');
const showValue = ref(false);

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.get('/agent/api/v1/credentials');
        providers.value = response.data.data.providers;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load credentials.';
    } finally {
        loading.value = false;
    }
};

const startAdding = (provider, keyName) => {
    adding.value = { provider, key: keyName };
    newValue.value = '';
    showValue.value = false;
};

const cancelAdding = () => {
    adding.value = null;
    newValue.value = '';
};

const storeCredential = async () => {
    if (!adding.value || !newValue.value) return;

    error.value = '';
    success.value = '';

    try {
        await axios.post('/agent/api/v1/credentials', {
            provider: adding.value.provider,
            key: adding.value.key,
            value: newValue.value,
        });
        success.value = `Stored ${adding.value.provider}/${adding.value.key} successfully.`;
        adding.value = null;
        newValue.value = '';
        await load();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to store credential.';
    }
};

const deleteCredential = async (provider, key) => {
    if (!confirm(`Delete ${provider}/${key}? This cannot be undone.`)) return;

    error.value = '';
    success.value = '';

    try {
        await axios.delete('/agent/api/v1/credentials', {
            data: { provider, key },
        });
        success.value = `Deleted ${provider}/${key}.`;
        await load();
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to delete credential.';
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Credentials">
        <Head title="Credentials" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Key class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground truncate">
                        Credentials
                    </h2>
                    <HelpHint
                        ui-key="credentials"
                        short-text="Manage encrypted API keys and tokens. Values are stored encrypted and never displayed."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>
                <div v-if="success" class="rounded-md border border-green-500/50 bg-green-500/10 px-3 py-2 text-sm text-green-600">
                    {{ success }}
                </div>

                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <ShieldCheck class="h-4 w-4" />
                    Credentials are encrypted at rest using AES-256-CBC. Values are never returned by the API.
                </div>

                <div v-if="loading && providers.length === 0" class="text-center py-12 text-muted-foreground">
                    Loading...
                </div>

                <div v-for="provider in providers" :key="provider.key" class="space-y-0">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">{{ provider.label }}</CardTitle>
                            <CardDescription>Provider: <code class="text-xs">{{ provider.key }}</code></CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="k in provider.keys"
                                    :key="k.key"
                                    class="flex items-center justify-between rounded-md border px-3 py-2"
                                >
                                    <div class="flex items-center gap-3">
                                        <code class="text-sm font-mono">{{ k.key }}</code>
                                        <Badge v-if="k.has_value" variant="default" class="gap-1">
                                            <CheckCircle class="h-3 w-3" />
                                            Set
                                        </Badge>
                                        <Badge v-else variant="outline" class="gap-1">
                                            <XCircle class="h-3 w-3" />
                                            Not set
                                        </Badge>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="startAdding(provider.key, k.key)"
                                        >
                                            <Plus class="h-3 w-3" />
                                            {{ k.has_value ? 'Replace' : 'Add' }}
                                        </Button>
                                        <Button
                                            v-if="k.has_value"
                                            variant="outline"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            @click="deleteCredential(provider.key, k.key)"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>

                                <!-- Inline add form -->
                                <div
                                    v-if="adding && adding.provider === provider.key"
                                    class="rounded-md border border-primary/30 bg-primary/5 p-3 space-y-3"
                                >
                                    <p class="text-sm font-medium">
                                        {{ adding.key }} for {{ provider.label }}
                                    </p>
                                    <div class="relative">
                                        <input
                                            v-model="newValue"
                                            :type="showValue ? 'text' : 'password'"
                                            placeholder="Paste credential value..."
                                            class="w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm font-mono ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                        />
                                        <button
                                            type="button"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                            @click="showValue = !showValue"
                                        >
                                            <EyeOff v-if="showValue" class="h-4 w-4" />
                                            <Eye v-else class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button size="sm" :disabled="!newValue" @click="storeCredential">
                                            Save
                                        </Button>
                                        <Button variant="outline" size="sm" @click="cancelAdding">
                                            Cancel
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
