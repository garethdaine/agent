<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Brain, CheckCircle, XCircle, AlertCircle, Loader2, Wifi, WifiOff } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import axios from 'axios';
import { onMounted, onUnmounted, reactive, ref, computed } from 'vue';

const page = usePage();
const baseUrl = '/agent/api/v1/memory';

// State
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const capabilities = ref(null);
const testingProvider = ref(null);
const diagnostics = ref({ formation: [], usage: [] });
const wsConnected = ref(false);
const wsChannel = ref(null);

// Form state
const form = reactive({
    // Provider keys
    provider_key_openai: '',
    provider_key_anthropic: '',
    // Model selections
    extraction_model: 'gpt-4o-mini',
    summarization_model: 'gpt-4.1-nano',
    embeddings_model: 'text-embedding-3-small',
    // Token budget
    context_budget_percent: 5,
    context_floor_tokens: 1000,
    context_ceiling_tokens: 8000,
    context_margin_percent: 10,
    // Forgetting thresholds
    embedding_decay_threshold: 0.1,
    graph_importance_threshold: 0.2,
    graph_retention_days: 90,
    // Rate limiting (per provider)
    openai_rpm: 500,
    openai_tpm: 200000,
    anthropic_rpm: 50,
    anthropic_tpm: 100000,
});

// Model options
const extractionModels = [
    { value: 'gpt-4o-mini', label: 'GPT-4o Mini (OpenAI)' },
    { value: 'claude-3-haiku-20240307', label: 'Claude 3 Haiku (Anthropic)' },
];

const summarizationModels = [
    { value: 'gpt-4.1-nano', label: 'GPT-4.1 Nano (OpenAI)' },
    { value: 'claude-3-haiku-20240307', label: 'Claude 3 Haiku (Anthropic)' },
];

const embeddingsModels = [
    { value: 'text-embedding-3-small', label: 'Text Embedding 3 Small (OpenAI)' },
];

// Computed
const operatingMode = computed(() => capabilities.value?.mode || 'unknown');
const operatingModeLabel = computed(() => {
    const labels = {
        'no-api': 'No-API Mode',
        'api': 'Full API Mode',
        'degraded': 'Degraded Mode',
    };
    return labels[operatingMode.value] || 'Unknown';
});

const operatingModeVariant = computed(() => {
    const variants = {
        'no-api': 'secondary',
        'api': 'default',
        'degraded': 'warning',
    };
    return variants[operatingMode.value] || 'secondary';
});

// Load settings and capabilities
const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const [settingsRes, capabilitiesRes] = await Promise.all([
            axios.get(`${baseUrl}/settings`),
            axios.get(`${baseUrl}/settings/capabilities`),
        ]);

        const settings = settingsRes.data?.data || [];
        capabilities.value = capabilitiesRes.data?.data || null;

        // Populate form from settings
        const numericKeys = [
            'context_budget_percent', 'context_floor_tokens', 'context_ceiling_tokens',
            'context_margin_percent', 'embedding_decay_threshold', 'graph_importance_threshold',
            'graph_retention_days', 'openai_rpm', 'openai_tpm', 'anthropic_rpm', 'anthropic_tpm',
            'working_memory_max_messages', 'working_memory_ttl_seconds',
        ];
        settings.forEach(({ key, value }) => {
            if (key in form) {
                // Coerce numeric settings back to numbers (API returns strings)
                form[key] = numericKeys.includes(key) && value !== null ? Number(value) : value;
            }
        });
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load settings.';
    } finally {
        loading.value = false;
    }
};

// Save settings
const save = async () => {
    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        // Build settings object, excluding masked keys unless changed
        const settings = {};
        Object.entries(form).forEach(([key, value]) => {
            // Skip masked API keys (contain ****)
            if (key.startsWith('provider_key_') && String(value).includes('****')) {
                return;
            }
            settings[key] = value;
        });

        await axios.put(`${baseUrl}/settings`, { settings });

        success.value = 'Settings saved successfully.';
        setTimeout(() => { success.value = ''; }, 3000);

        // Reload capabilities to reflect any changes
        const capabilitiesRes = await axios.get(`${baseUrl}/settings/capabilities`);
        capabilities.value = capabilitiesRes.data?.data || null;
    } catch (e) {
        const payload = e?.response?.data ?? {};
        const envelope = payload?.error ?? null;
        error.value = envelope?.message ?? payload?.message ?? 'Failed to save settings.';
    } finally {
        saving.value = false;
    }
};

// Test connection
const testConnection = async (provider) => {
    testingProvider.value = provider;
    error.value = '';

    try {
        const res = await axios.post(`${baseUrl}/settings/test-connection`, { provider });
        const data = res.data?.data || {};

        if (data.success) {
            success.value = `${provider} connection successful!`;
        } else {
            error.value = data.error || `${provider} connection failed.`;
        }
        setTimeout(() => { success.value = ''; }, 3000);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? `Failed to test ${provider} connection.`;
    } finally {
        testingProvider.value = null;
    }
};

// WebSocket setup for real-time diagnostics
const setupWebSocket = () => {
    if (typeof window.Echo === 'undefined') {
        return;
    }

    try {
        // Listen for memory diagnostics on user's private channel
        wsChannel.value = window.Echo.private(`memory.diagnostics.${page.props.auth.user.id}`)
            .listen('MemoryFormationProgress', (e) => {
                diagnostics.value.formation.unshift({
                    id: Date.now(),
                    type: 'formation',
                    run_id: e.run_id,
                    status: e.status,
                    progress: e.progress,
                    timestamp: new Date().toISOString(),
                });
                // Keep last 10
                if (diagnostics.value.formation.length > 10) {
                    diagnostics.value.formation.pop();
                }
            })
            .listen('MemoryConsolidationProgress', (e) => {
                diagnostics.value.formation.unshift({
                    id: Date.now(),
                    type: 'consolidation',
                    status: e.status,
                    progress: e.progress,
                    timestamp: new Date().toISOString(),
                });
                if (diagnostics.value.formation.length > 10) {
                    diagnostics.value.formation.pop();
                }
            })
            .listen('MemoryProviderUsageUpdated', (e) => {
                diagnostics.value.usage.unshift({
                    id: Date.now(),
                    provider: e.provider,
                    model: e.model,
                    tokens: e.tokens,
                    cost: e.cost,
                    timestamp: new Date().toISOString(),
                });
                if (diagnostics.value.usage.length > 10) {
                    diagnostics.value.usage.pop();
                }
            });

        wsConnected.value = true;
    } catch (e) {
        wsConnected.value = false;
    }
};

const cleanupWebSocket = () => {
    if (wsChannel.value && typeof window.Echo !== 'undefined') {
        try {
            window.Echo.leave(`memory.diagnostics.${page.props.auth.user.id}`);
        } catch (e) {
            // Ignore cleanup errors
        }
    }
};

onMounted(() => {
    load();
    setupWebSocket();
});

onUnmounted(() => {
    cleanupWebSocket();
});
</script>

<template>
    <AppLayout title="Memory Settings">
        <Head title="Memory Settings" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Brain class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Memory Settings</h2>
                        <HelpHint
                            ui-key="memory.settings"
                            short-text="Configure memory provider and retention settings."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.memory.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-6">
                <!-- Status Messages -->
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </div>
                <div v-if="success" class="rounded-md border border-green-500/50 bg-green-500/10 px-4 py-3 text-sm text-green-600 dark:text-green-400">
                    {{ success }}
                </div>

                <Skeleton v-if="loading" class="h-96 w-full" />

                <template v-else>
                    <!-- Section 1: Provider Configuration -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Provider Configuration</CardTitle>
                            <CardDescription>Configure API keys and models for memory operations.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- OpenAI -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium">OpenAI API Key</label>
                                <div class="flex gap-2">
                                    <Input
                                        v-model="form.provider_key_openai"
                                        type="password"
                                        placeholder="sk-..."
                                        class="flex-1"
                                    />
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="testingProvider === 'openai' || !form.provider_key_openai"
                                        @click="testConnection('openai')"
                                    >
                                        <Loader2 v-if="testingProvider === 'openai'" class="h-4 w-4 animate-spin" />
                                        <span v-else>Test</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Anthropic -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium">Anthropic API Key</label>
                                <div class="flex gap-2">
                                    <Input
                                        v-model="form.provider_key_anthropic"
                                        type="password"
                                        placeholder="sk-ant-..."
                                        class="flex-1"
                                    />
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="testingProvider === 'anthropic' || !form.provider_key_anthropic"
                                        @click="testConnection('anthropic')"
                                    >
                                        <Loader2 v-if="testingProvider === 'anthropic'" class="h-4 w-4 animate-spin" />
                                        <span v-else>Test</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Model Selections -->
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium">Extraction Model</label>
                                    <Select
                                        v-model="form.extraction_model"
                                        :options="extractionModels"
                                        class="mt-1"
                                    />
                                    <p class="mt-1 text-xs text-muted-foreground">Entity extraction & importance scoring</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Summarization Model</label>
                                    <Select
                                        v-model="form.summarization_model"
                                        :options="summarizationModels"
                                        class="mt-1"
                                    />
                                    <p class="mt-1 text-xs text-muted-foreground">Working memory eviction</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Embeddings Model</label>
                                    <Select
                                        v-model="form.embeddings_model"
                                        :options="embeddingsModels"
                                        class="mt-1"
                                    />
                                    <p class="mt-1 text-xs text-muted-foreground">Semantic vector embeddings</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Section 2: Operating Mode -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Operating Mode</CardTitle>
                            <CardDescription>Current memory system capabilities based on configuration.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="mb-4 flex items-center gap-2">
                                <Badge :variant="operatingModeVariant">{{ operatingModeLabel }}</Badge>
                                <component
                                    :is="wsConnected ? Wifi : WifiOff"
                                    :class="['h-4 w-4', wsConnected ? 'text-green-500' : 'text-muted-foreground']"
                                />
                                <span class="text-xs text-muted-foreground">
                                    {{ wsConnected ? 'Real-time updates active' : 'Real-time updates unavailable' }}
                                </span>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="capabilities?.core_memory ? CheckCircle : XCircle"
                                        :class="['h-4 w-4', capabilities?.core_memory ? 'text-green-500' : 'text-muted-foreground']"
                                    />
                                    <span class="text-sm">Core Memory</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="capabilities?.working_memory ? CheckCircle : XCircle"
                                        :class="['h-4 w-4', capabilities?.working_memory ? 'text-green-500' : 'text-muted-foreground']"
                                    />
                                    <span class="text-sm">Working Memory</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="capabilities?.keyword_search ? CheckCircle : XCircle"
                                        :class="['h-4 w-4', capabilities?.keyword_search ? 'text-green-500' : 'text-muted-foreground']"
                                    />
                                    <span class="text-sm">Keyword Search (BM25)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="capabilities?.semantic_search ? CheckCircle : XCircle"
                                        :class="['h-4 w-4', capabilities?.semantic_search ? 'text-green-500' : 'text-muted-foreground']"
                                    />
                                    <span class="text-sm">Semantic Search (pgvector)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="capabilities?.entity_extraction ? CheckCircle : XCircle"
                                        :class="['h-4 w-4', capabilities?.entity_extraction ? 'text-green-500' : 'text-muted-foreground']"
                                    />
                                    <span class="text-sm">Entity Extraction</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="capabilities?.graph_search ? CheckCircle : XCircle"
                                        :class="['h-4 w-4', capabilities?.graph_search ? 'text-green-500' : 'text-muted-foreground']"
                                    />
                                    <span class="text-sm">Graph Search (Neo4j)</span>
                                </div>
                            </div>

                            <div v-if="capabilities?.configured_providers?.length" class="mt-4">
                                <p class="text-xs text-muted-foreground">
                                    Configured providers: {{ capabilities.configured_providers.join(', ') }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Section 3: Rate Limiting -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Rate Limiting</CardTitle>
                            <CardDescription>Configure per-provider rate limits to avoid API throttling.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <!-- OpenAI -->
                                <div class="space-y-3">
                                    <h4 class="font-medium">OpenAI</h4>
                                    <div>
                                        <label class="block text-sm">Requests per minute</label>
                                        <Input
                                            v-model.number="form.openai_rpm"
                                            type="number"
                                            min="1"
                                            max="10000"
                                            class="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm">Tokens per minute</label>
                                        <Input
                                            v-model.number="form.openai_tpm"
                                            type="number"
                                            min="1000"
                                            max="10000000"
                                            class="mt-1"
                                        />
                                    </div>
                                </div>

                                <!-- Anthropic -->
                                <div class="space-y-3">
                                    <h4 class="font-medium">Anthropic</h4>
                                    <div>
                                        <label class="block text-sm">Requests per minute</label>
                                        <Input
                                            v-model.number="form.anthropic_rpm"
                                            type="number"
                                            min="1"
                                            max="10000"
                                            class="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm">Tokens per minute</label>
                                        <Input
                                            v-model.number="form.anthropic_tpm"
                                            type="number"
                                            min="1000"
                                            max="10000000"
                                            class="mt-1"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Section 4: Token Budget -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Token Budget</CardTitle>
                            <CardDescription>Configure context injection token budget for memory retrieval.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium">Budget Percentage</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input
                                            v-model.number="form.context_budget_percent"
                                            type="range"
                                            min="1"
                                            max="20"
                                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-muted"
                                        />
                                        <span class="w-12 text-right text-sm font-medium">{{ form.context_budget_percent }}%</span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">Percentage of context window for memory</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Safety Margin</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input
                                            v-model.number="form.context_margin_percent"
                                            type="range"
                                            min="0"
                                            max="30"
                                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-muted"
                                        />
                                        <span class="w-12 text-right text-sm font-medium">{{ form.context_margin_percent }}%</span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">Reduction margin for safety</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Floor Tokens</label>
                                    <Input
                                        v-model.number="form.context_floor_tokens"
                                        type="number"
                                        min="100"
                                        max="5000"
                                        class="mt-1"
                                    />
                                    <p class="mt-1 text-xs text-muted-foreground">Minimum tokens for memory context</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Ceiling Tokens</label>
                                    <Input
                                        v-model.number="form.context_ceiling_tokens"
                                        type="number"
                                        min="1000"
                                        max="50000"
                                        class="mt-1"
                                    />
                                    <p class="mt-1 text-xs text-muted-foreground">Maximum tokens for memory context</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Section 5: Forgetting Thresholds -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Forgetting Thresholds</CardTitle>
                            <CardDescription>Configure memory decay and retention parameters.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="block text-sm font-medium">Embedding Decay Threshold</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input
                                            v-model.number="form.embedding_decay_threshold"
                                            type="range"
                                            min="0"
                                            max="1"
                                            step="0.05"
                                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-muted"
                                        />
                                        <span class="w-12 text-right text-sm font-medium">{{ form.embedding_decay_threshold.toFixed(2) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">Prune embeddings below this score</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Graph Importance Threshold</label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <input
                                            v-model.number="form.graph_importance_threshold"
                                            type="range"
                                            min="0"
                                            max="1"
                                            step="0.05"
                                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-muted"
                                        />
                                        <span class="w-12 text-right text-sm font-medium">{{ form.graph_importance_threshold.toFixed(2) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">Soft-delete entities below this score</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium">Retention Days</label>
                                    <Input
                                        v-model.number="form.graph_retention_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="mt-1"
                                    />
                                    <p class="mt-1 text-xs text-muted-foreground">Days before applying forgetting</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Section 6: Real-time Diagnostics -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                Real-time Diagnostics
                                <component
                                    :is="wsConnected ? Wifi : WifiOff"
                                    :class="['h-4 w-4', wsConnected ? 'text-green-500' : 'text-muted-foreground']"
                                />
                            </CardTitle>
                            <CardDescription>Live updates for formation progress, consolidation, and provider usage.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="!wsConnected" class="rounded-md border border-yellow-500/50 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-600 dark:text-yellow-400">
                                <AlertCircle class="mr-2 inline h-4 w-4" />
                                WebSocket connection unavailable. Real-time updates will not be displayed.
                            </div>

                            <template v-else>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <!-- Formation Progress -->
                                    <div>
                                        <h4 class="mb-2 font-medium">Formation Progress</h4>
                                        <div v-if="diagnostics.formation.length === 0" class="text-sm text-muted-foreground">
                                            No recent formation activity.
                                        </div>
                                        <div v-else class="max-h-48 space-y-2 overflow-y-auto">
                                            <div
                                                v-for="item in diagnostics.formation"
                                                :key="item.id"
                                                class="rounded-md bg-muted/50 px-3 py-2 text-xs"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <Badge variant="secondary">{{ item.type }}</Badge>
                                                    <span class="text-muted-foreground">{{ item.status }}</span>
                                                </div>
                                                <div v-if="item.progress" class="mt-1">
                                                    <div class="h-1 w-full rounded-full bg-muted">
                                                        <div
                                                            class="h-1 rounded-full bg-primary"
                                                            :style="{ width: `${item.progress}%` }"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Provider Usage -->
                                    <div>
                                        <h4 class="mb-2 font-medium">Provider Usage</h4>
                                        <div v-if="diagnostics.usage.length === 0" class="text-sm text-muted-foreground">
                                            No recent provider usage.
                                        </div>
                                        <div v-else class="max-h-48 space-y-2 overflow-y-auto">
                                            <div
                                                v-for="item in diagnostics.usage"
                                                :key="item.id"
                                                class="rounded-md bg-muted/50 px-3 py-2 text-xs"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <span class="font-medium">{{ item.provider }}</span>
                                                    <span class="text-muted-foreground">{{ item.model }}</span>
                                                </div>
                                                <div class="mt-1 flex items-center justify-between text-muted-foreground">
                                                    <span>{{ item.tokens?.toLocaleString() }} tokens</span>
                                                    <span v-if="item.cost">${{ item.cost.toFixed(4) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Save Button -->
                    <div class="flex justify-end">
                        <Button :disabled="saving || loading" @click="save">
                            <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                            {{ saving ? 'Saving...' : 'Save Settings' }}
                        </Button>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
