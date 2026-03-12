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
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import InputLabel from '@/Components/InputLabel.vue';
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
    Globe,
    Server,
    Terminal,
    RefreshCw,
    Search,
    X,
    Clock,
    AlertTriangle,
    Pencil,
    KeyRound,
    Lock,
} from 'lucide-vue-next';
import axios from 'axios';
import { ref, computed, onMounted } from 'vue';

// ─── Shared state ───
const activeTab = ref('vault');
const error = ref('');
const success = ref('');

const clearMessages = () => {
    error.value = '';
    success.value = '';
};

// ─── Legacy provider tab ───
const providers = ref([]);
const legacyLoading = ref(false);
const adding = ref(null);
const newValue = ref('');
const showValue = ref(false);

const loadProviders = async () => {
    legacyLoading.value = true;
    clearMessages();
    try {
        const response = await axios.get('/agent/api/v1/credentials');
        providers.value = response.data.data.providers;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load credentials.';
    } finally {
        legacyLoading.value = false;
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

const storeLegacyCredential = async () => {
    if (!adding.value || !newValue.value) return;
    clearMessages();
    try {
        await axios.post('/agent/api/v1/credentials', {
            provider: adding.value.provider,
            key: adding.value.key,
            value: newValue.value,
        });
        success.value = `Stored ${adding.value.provider}/${adding.value.key} successfully.`;
        adding.value = null;
        newValue.value = '';
        await loadProviders();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to store credential.';
    }
};

const deleteLegacyCredential = async (provider, key) => {
    if (!confirm(`Delete ${provider}/${key}? This cannot be undone.`)) return;
    clearMessages();
    try {
        await axios.delete('/agent/api/v1/credentials', { data: { provider, key } });
        success.value = `Deleted ${provider}/${key}.`;
        await loadProviders();
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to delete credential.';
    }
};

// ─── Vault tab ───
const vaultGroups = ref([]);
const vaultLoading = ref(false);
const vaultSearch = ref('');
const vaultTypeFilter = ref('');
const showAddForm = ref(false);
const editingCredential = ref(null);
const editForm = ref({
    name: '',
    provider: '',
    url: '',
    approval_mode: 'supervised',
    notes: '',
    expires_at: '',
    secrets: {},
});
const showRotateForm = ref(null);

const typeOptions = [
    { value: '', label: 'All types' },
    { value: 'login', label: 'Website Login' },
    { value: 'api_key', label: 'API Key' },
    { value: 'ssh_key', label: 'SSH Key' },
    { value: 'oauth_token', label: 'OAuth Token' },
];

const approvalOptions = [
    { value: 'autonomous', label: 'Autonomous' },
    { value: 'supervised', label: 'Supervised' },
    { value: 'restricted', label: 'Restricted' },
];

const typeIcons = {
    login: Globe,
    api_key: Key,
    ssh_key: Terminal,
    oauth_token: RefreshCw,
};

const typeLabels = {
    login: 'Website Login',
    api_key: 'API Key',
    ssh_key: 'SSH Key',
    oauth_token: 'OAuth Token',
};

const approvalBadgeVariant = (mode) => {
    if (mode === 'autonomous') return 'default';
    if (mode === 'supervised') return 'secondary';
    return 'destructive';
};

// Add form state
const newCredential = ref({
    credential_type: 'login',
    name: '',
    provider: '',
    url: '',
    approval_mode: 'supervised',
    notes: '',
    expires_at: '',
    secrets: {},
});

// Rotate form state
const rotateSecrets = ref({});

const secretFieldsForType = (type) => {
    const map = {
        login: ['username', 'password'],
        api_key: ['api_key', 'api_secret'],
        ssh_key: ['private_key', 'passphrase'],
        oauth_token: ['access_token', 'refresh_token'],
    };
    return map[type] || [];
};

const secretFieldLabels = {
    username: 'Username',
    password: 'Password',
    api_key: 'API Key',
    api_secret: 'API Secret',
    private_key: 'Private Key',
    passphrase: 'Passphrase',
    access_token: 'Access Token',
    refresh_token: 'Refresh Token',
};

const resetAddForm = () => {
    newCredential.value = {
        credential_type: 'login',
        name: '',
        provider: '',
        url: '',
        approval_mode: 'supervised',
        notes: '',
        expires_at: '',
        secrets: {},
    };
    showAddForm.value = false;
};

const allVaultCredentials = computed(() => {
    const all = [];
    for (const group of vaultGroups.value) {
        for (const cred of group.credentials) {
            all.push({ ...cred, type_label: group.label });
        }
    }
    return all;
});

const loadVault = async () => {
    vaultLoading.value = true;
    clearMessages();
    try {
        const params = {};
        if (vaultTypeFilter.value) params.type = vaultTypeFilter.value;
        if (vaultSearch.value) params.search = vaultSearch.value;

        const response = await axios.get('/agent/api/v1/credentials/vault', { params });
        vaultGroups.value = response.data.data.vault;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load vault.';
    } finally {
        vaultLoading.value = false;
    }
};

const storeVaultCredential = async () => {
    clearMessages();
    const data = { ...newCredential.value };
    if (!data.url) delete data.url;
    if (!data.notes) delete data.notes;
    if (!data.expires_at) delete data.expires_at;

    try {
        await axios.post('/agent/api/v1/credentials/vault', data);
        success.value = `Stored "${data.name}" successfully.`;
        resetAddForm();
        await loadVault();
    } catch (e) {
        const details = e?.response?.data?.errors;
        if (details) {
            error.value = Object.values(details).flat().join(' ');
        } else {
            error.value = e?.response?.data?.message ?? 'Failed to store credential.';
        }
    }
};

const deleteVaultCredential = async (cred) => {
    if (!confirm(`Delete "${cred.name}"? This cannot be undone.`)) return;
    clearMessages();
    try {
        await axios.delete(`/agent/api/v1/credentials/vault/${cred.id}`);
        success.value = `Deleted "${cred.name}".`;
        await loadVault();
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to delete credential.';
    }
};

const startRotate = (cred) => {
    editingCredential.value = null;
    showRotateForm.value = cred.id;
    const type = allVaultCredentials.value.find(c => c.id === cred.id)?.credential_type ?? 'api_key';
    const fields = secretFieldsForType(type);
    rotateSecrets.value = {};
    fields.forEach(f => rotateSecrets.value[f] = '');
};

const cancelRotate = () => {
    showRotateForm.value = null;
    rotateSecrets.value = {};
};

const rotateVaultCredential = async (credId) => {
    clearMessages();
    // Filter out empty fields
    const secrets = {};
    for (const [k, v] of Object.entries(rotateSecrets.value)) {
        if (v) secrets[k] = v;
    }
    if (Object.keys(secrets).length === 0) {
        error.value = 'Provide at least one secret to rotate.';
        return;
    }
    try {
        await axios.post(`/agent/api/v1/credentials/vault/${credId}/rotate`, { secrets });
        success.value = 'Credential rotated successfully.';
        cancelRotate();
        await loadVault();
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to rotate credential.';
    }
};

const startEdit = (cred) => {
    editingCredential.value = cred.id;
    showRotateForm.value = null;
    editForm.value = {
        name: cred.name || '',
        provider: cred.provider || '',
        url: cred.url || '',
        approval_mode: cred.approval_mode || 'supervised',
        notes: '',
        expires_at: cred.expires_at ? cred.expires_at.slice(0, 16) : '',
        secrets: {},
    };
};

const cancelEdit = () => {
    editingCredential.value = null;
    editForm.value = { name: '', provider: '', url: '', approval_mode: 'supervised', notes: '', expires_at: '', secrets: {} };
};

const updateVaultCredential = async (credId) => {
    clearMessages();
    const data = {};
    if (editForm.value.name) data.name = editForm.value.name;
    if (editForm.value.provider) data.provider = editForm.value.provider;
    data.url = editForm.value.url || null;
    if (editForm.value.approval_mode) data.approval_mode = editForm.value.approval_mode;
    if (editForm.value.notes) data.notes = editForm.value.notes;
    data.expires_at = editForm.value.expires_at || null;

    const secrets = {};
    for (const [k, v] of Object.entries(editForm.value.secrets)) {
        if (v) secrets[k] = v;
    }
    if (Object.keys(secrets).length > 0) data.secrets = secrets;

    try {
        await axios.put(`/agent/api/v1/credentials/vault/${credId}`, data);
        success.value = 'Credential updated successfully.';
        cancelEdit();
        await loadVault();
    } catch (e) {
        const details = e?.response?.data?.errors;
        if (details) {
            error.value = Object.values(details).flat().join(' ');
        } else {
            error.value = e?.response?.data?.message ?? 'Failed to update credential.';
        }
    }
};

const formatDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const showSecretFields = ref({});
const toggleSecretVisibility = (fieldKey) => {
    showSecretFields.value[fieldKey] = !showSecretFields.value[fieldKey];
};

onMounted(() => {
    loadProviders();
    loadVault();
});
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
                        short-text="Manage encrypted credentials. Store website logins, API keys, SSH keys, and OAuth tokens for AI runtime use."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <!-- Error / Success -->
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>
                <div v-if="success" class="rounded-md border border-green-500/50 bg-green-500/10 px-3 py-2 text-sm text-green-600">
                    {{ success }}
                </div>

                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <ShieldCheck class="h-4 w-4" />
                    Credentials are encrypted at rest using AES-256-CBC. Secrets are never returned by the API.
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-border">
                    <button
                        class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
                        :class="activeTab === 'vault'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'vault'"
                    >
                        <div class="flex items-center gap-2">
                            <Lock class="h-4 w-4" />
                            Credential Vault
                        </div>
                    </button>
                    <button
                        class="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
                        :class="activeTab === 'providers'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'"
                        @click="activeTab = 'providers'"
                    >
                        <div class="flex items-center gap-2">
                            <Server class="h-4 w-4" />
                            Provider Keys
                        </div>
                    </button>
                </div>

                <!-- ═══ VAULT TAB ═══ -->
                <div v-if="activeTab === 'vault'" class="space-y-4">
                    <!-- Toolbar -->
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-1 items-center gap-2">
                            <div class="relative flex-1 max-w-xs">
                                <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <input
                                    v-model="vaultSearch"
                                    type="text"
                                    placeholder="Search credentials..."
                                    class="w-full rounded-md border border-input bg-input-background pl-9 pr-3 py-1.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    @keydown.enter="loadVault"
                                />
                                <button
                                    v-if="vaultSearch"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="vaultSearch = ''; loadVault()"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <Select
                                v-model="vaultTypeFilter"
                                :options="typeOptions"
                                size="sm"
                                class="w-40"
                                @update:model-value="loadVault"
                            />
                        </div>
                        <Button size="sm" @click="showAddForm = !showAddForm">
                            <Plus class="h-4 w-4" />
                            Add Credential
                        </Button>
                    </div>

                    <!-- Add Credential Form -->
                    <Card v-if="showAddForm">
                        <CardHeader>
                            <CardTitle class="text-base">New Credential</CardTitle>
                            <CardDescription>All secret fields are encrypted before storage.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form class="space-y-4" @submit.prevent="storeVaultCredential">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <InputLabel value="Type" />
                                        <Select
                                            v-model="newCredential.credential_type"
                                            :options="[
                                                { value: 'login', label: 'Website Login' },
                                                { value: 'api_key', label: 'API Key' },
                                                { value: 'ssh_key', label: 'SSH Key' },
                                                { value: 'oauth_token', label: 'OAuth Token' },
                                            ]"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <InputLabel value="Approval Mode" />
                                        <Select
                                            v-model="newCredential.approval_mode"
                                            :options="approvalOptions"
                                        />
                                        <p class="text-xs text-muted-foreground">
                                            <template v-if="newCredential.approval_mode === 'autonomous'">AI can use without asking.</template>
                                            <template v-else-if="newCredential.approval_mode === 'supervised'">AI asks when trust is low.</template>
                                            <template v-else>Always requires your confirmation.</template>
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <InputLabel value="Name" />
                                        <Input v-model="newCredential.name" placeholder="e.g. My GitHub Account" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <InputLabel value="Provider" />
                                        <Input v-model="newCredential.provider" placeholder="e.g. github, stripe, aws" />
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <InputLabel value="URL (optional)" />
                                    <Input v-model="newCredential.url" placeholder="e.g. https://github.com" />
                                </div>

                                <!-- Secret fields (dynamic by type) -->
                                <div class="space-y-3 rounded-md border border-primary/20 bg-primary/5 p-4">
                                    <p class="text-sm font-medium flex items-center gap-2">
                                        <KeyRound class="h-4 w-4 text-primary" />
                                        Secret Fields
                                    </p>
                                    <div
                                        v-for="field in secretFieldsForType(newCredential.credential_type)"
                                        :key="field"
                                        class="space-y-1.5"
                                    >
                                        <InputLabel :value="secretFieldLabels[field] || field" />
                                        <div class="relative">
                                            <input
                                                v-model="newCredential.secrets[field]"
                                                :type="showSecretFields[`new-${field}`] ? 'text' : 'password'"
                                                :placeholder="`Enter ${secretFieldLabels[field] || field}...`"
                                                class="w-full rounded-md border border-input bg-input-background px-3 py-2 pr-10 text-sm font-mono shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            />
                                            <button
                                                type="button"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                                @click="toggleSecretVisibility(`new-${field}`)"
                                            >
                                                <EyeOff v-if="showSecretFields[`new-${field}`]" class="h-4 w-4" />
                                                <Eye v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <InputLabel value="Notes (optional, encrypted)" />
                                        <textarea
                                            v-model="newCredential.notes"
                                            rows="2"
                                            placeholder="Personal account, MFA enabled..."
                                            class="w-full rounded-md border border-input bg-input-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <InputLabel value="Expires At (optional)" />
                                        <Input v-model="newCredential.expires_at" type="datetime-local" />
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <Button type="submit" size="sm" :disabled="!newCredential.name || !newCredential.provider">
                                        Store Credential
                                    </Button>
                                    <Button variant="outline" size="sm" @click="resetAddForm">
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <!-- Vault Loading -->
                    <div v-if="vaultLoading && allVaultCredentials.length === 0" class="text-center py-12 text-muted-foreground">
                        Loading vault...
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="!vaultLoading && allVaultCredentials.length === 0" class="text-center py-12">
                        <Lock class="mx-auto h-10 w-10 text-muted-foreground/50" />
                        <p class="mt-3 text-sm text-muted-foreground">
                            No credentials stored yet. Add your first credential to get started.
                        </p>
                    </div>

                    <!-- Credential List -->
                    <div v-else class="space-y-3">
                        <div
                            v-for="cred in allVaultCredentials"
                            :key="cred.id"
                            class="rounded-lg border border-border bg-card transition-colors"
                        >
                            <div class="flex items-start justify-between p-4">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 mt-0.5">
                                        <component :is="typeIcons[cred.credential_type] || Key" class="h-4 w-4 text-primary" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-sm font-medium text-foreground truncate">{{ cred.name }}</p>
                                            <Badge :variant="approvalBadgeVariant(cred.approval_mode)" class="text-[10px]">
                                                {{ cred.approval_mode }}
                                            </Badge>
                                            <Badge v-if="cred.is_expired" variant="destructive" class="gap-1 text-[10px]">
                                                <AlertTriangle class="h-3 w-3" />
                                                Expired
                                            </Badge>
                                        </div>
                                        <div class="flex items-center gap-3 mt-1 text-xs text-muted-foreground flex-wrap">
                                            <span class="font-mono">{{ cred.provider }}</span>
                                            <span v-if="cred.url" class="truncate max-w-[200px]">{{ cred.url }}</span>
                                            <span>{{ typeLabels[cred.credential_type] || cred.credential_type }}</span>
                                        </div>
                                        <div class="flex items-center gap-4 mt-1.5 text-xs text-muted-foreground">
                                            <span v-if="cred.last_used_at" class="flex items-center gap-1">
                                                <Clock class="h-3 w-3" />
                                                Used {{ formatDate(cred.last_used_at) }}
                                            </span>
                                            <span v-if="cred.last_rotated_at" class="flex items-center gap-1">
                                                <RefreshCw class="h-3 w-3" />
                                                Rotated {{ formatDate(cred.last_rotated_at) }}
                                            </span>
                                            <span v-if="cred.expires_at" class="flex items-center gap-1">
                                                <AlertTriangle class="h-3 w-3" />
                                                Expires {{ formatDate(cred.expires_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0 ml-3">
                                    <Button variant="outline" size="sm" @click="startEdit(cred)">
                                        <Pencil class="h-3 w-3" />
                                        Edit
                                    </Button>
                                    <Button variant="outline" size="sm" @click="startRotate(cred)">
                                        <RefreshCw class="h-3 w-3" />
                                        Rotate
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="deleteVaultCredential(cred)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>

                            <!-- Inline Edit Form -->
                            <div
                                v-if="editingCredential === cred.id"
                                class="border-t border-border p-4 bg-primary/5 space-y-4"
                            >
                                <p class="text-sm font-medium flex items-center gap-2">
                                    <Pencil class="h-4 w-4 text-primary" />
                                    Edit "{{ cred.name }}"
                                </p>
                                <form class="space-y-4" @submit.prevent="updateVaultCredential(cred.id)">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div class="space-y-1.5">
                                            <InputLabel value="Name" />
                                            <Input v-model="editForm.name" placeholder="e.g. My GitHub Account" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <InputLabel value="Provider" />
                                            <Input v-model="editForm.provider" placeholder="e.g. github, stripe, aws" />
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <InputLabel value="URL" />
                                        <Input v-model="editForm.url" placeholder="e.g. https://github.com" />
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div class="space-y-1.5">
                                            <InputLabel value="Approval Mode" />
                                            <Select
                                                v-model="editForm.approval_mode"
                                                :options="approvalOptions"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                <template v-if="editForm.approval_mode === 'autonomous'">AI can use without asking.</template>
                                                <template v-else-if="editForm.approval_mode === 'supervised'">AI asks when trust is low.</template>
                                                <template v-else>Always requires your confirmation.</template>
                                            </p>
                                        </div>
                                        <div class="space-y-1.5">
                                            <InputLabel value="Expires At" />
                                            <Input v-model="editForm.expires_at" type="datetime-local" />
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <InputLabel value="Notes (encrypted, leave empty to keep current)" />
                                        <textarea
                                            v-model="editForm.notes"
                                            rows="2"
                                            placeholder="Leave empty to keep existing notes..."
                                            class="w-full rounded-md border border-input bg-input-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                    </div>

                                    <!-- Optional secret fields -->
                                    <div class="space-y-3 rounded-md border border-primary/20 bg-primary/5 p-4">
                                        <p class="text-sm font-medium flex items-center gap-2">
                                            <KeyRound class="h-4 w-4 text-primary" />
                                            Update Secrets (optional)
                                        </p>
                                        <p class="text-xs text-muted-foreground">Leave empty to keep current values.</p>
                                        <div
                                            v-for="field in secretFieldsForType(cred.credential_type)"
                                            :key="field"
                                            class="space-y-1"
                                        >
                                            <InputLabel :value="secretFieldLabels[field] || field" />
                                            <div class="relative">
                                                <input
                                                    v-model="editForm.secrets[field]"
                                                    :type="showSecretFields[`edit-${cred.id}-${field}`] ? 'text' : 'password'"
                                                    :placeholder="`New ${secretFieldLabels[field] || field}...`"
                                                    class="w-full rounded-md border border-input bg-input-background px-3 py-2 pr-10 text-sm font-mono shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                />
                                                <button
                                                    type="button"
                                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                                    @click="toggleSecretVisibility(`edit-${cred.id}-${field}`)"
                                                >
                                                    <EyeOff v-if="showSecretFields[`edit-${cred.id}-${field}`]" class="h-4 w-4" />
                                                    <Eye v-else class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <Button type="submit" size="sm" :disabled="!editForm.name || !editForm.provider">
                                            Save Changes
                                        </Button>
                                        <Button variant="outline" size="sm" @click="cancelEdit">
                                            Cancel
                                        </Button>
                                    </div>
                                </form>
                            </div>

                            <!-- Inline Rotate Form -->
                            <div
                                v-if="showRotateForm === cred.id"
                                class="border-t border-border p-4 bg-primary/5 space-y-3"
                            >
                                <p class="text-sm font-medium flex items-center gap-2">
                                    <RefreshCw class="h-4 w-4 text-primary" />
                                    Rotate Secrets for "{{ cred.name }}"
                                </p>
                                <p class="text-xs text-muted-foreground">Enter new values for the secrets you want to rotate. Leave empty to keep the current value.</p>
                                <div
                                    v-for="field in secretFieldsForType(cred.credential_type)"
                                    :key="field"
                                    class="space-y-1"
                                >
                                    <InputLabel :value="secretFieldLabels[field] || field" />
                                    <div class="relative">
                                        <input
                                            v-model="rotateSecrets[field]"
                                            :type="showSecretFields[`rotate-${cred.id}-${field}`] ? 'text' : 'password'"
                                            :placeholder="`New ${secretFieldLabels[field] || field}...`"
                                            class="w-full rounded-md border border-input bg-input-background px-3 py-2 pr-10 text-sm font-mono shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                        <button
                                            type="button"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                            @click="toggleSecretVisibility(`rotate-${cred.id}-${field}`)"
                                        >
                                            <EyeOff v-if="showSecretFields[`rotate-${cred.id}-${field}`]" class="h-4 w-4" />
                                            <Eye v-else class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Button size="sm" @click="rotateVaultCredential(cred.id)">
                                        Rotate
                                    </Button>
                                    <Button variant="outline" size="sm" @click="cancelRotate">
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ PROVIDERS TAB ═══ -->
                <div v-if="activeTab === 'providers'" class="space-y-4">
                    <div v-if="legacyLoading && providers.length === 0" class="text-center py-12 text-muted-foreground">
                        Loading...
                    </div>

                    <div v-for="provider in providers" :key="provider.key">
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
                                                @click="deleteLegacyCredential(provider.key, k.key)"
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
                                            <Button size="sm" :disabled="!newValue" @click="storeLegacyCredential">
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
        </div>
    </AppLayout>
</template>
