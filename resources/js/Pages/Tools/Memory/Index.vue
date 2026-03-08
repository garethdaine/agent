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
import { ArrowLeft, Settings, Brain, Database, Layers, Activity, Trash2, RefreshCw } from 'lucide-vue-next';
import axios from 'axios';
import { onMounted, ref, computed } from 'vue';

const baseUrl = '/agent/api/v1/memory';

// State
const loading = ref(false);
const error = ref('');
const stats = ref(null);
const capabilities = ref(null);

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

// Load stats and capabilities
const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const [statsRes, capabilitiesRes] = await Promise.all([
            axios.get(`${baseUrl}/stats`),
            axios.get(`${baseUrl}/settings/capabilities`),
        ]);

        stats.value = statsRes.data?.data || null;
        capabilities.value = capabilitiesRes.data?.data || null;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load memory statistics.';
    } finally {
        loading.value = false;
    }
};

const formatNumber = (num) => {
    if (num === null || num === undefined) return '0';
    return num.toLocaleString();
};

const formatCost = (cost) => {
    if (cost === null || cost === undefined) return '$0.00';
    return `$${cost.toFixed(4)}`;
};

onMounted(load);
</script>

<template>
    <AppLayout title="Agent Memory">
        <Head title="Agent Memory" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Brain class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Agent Memory</h2>
                        <HelpHint
                            ui-key="memory.diagnostics"
                            short-text="Interpret mode, diagnostics, and provider usage before changing memory settings."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('tools.memory.settings')">
                        <Button variant="outline" size="sm">
                            <Settings class="h-4 w-4" />
                            Settings
                        </Button>
                    </Link>
                    <Link :href="route('tools.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="h-4 w-4" />
                            Tools
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <!-- Status Message -->
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </div>

                <Skeleton v-if="loading" class="h-96 w-full" />

                <template v-else>
                    <!-- Operating Mode Overview -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Memory System Status</CardTitle>
                                    <CardDescription>Current operating mode and capabilities.</CardDescription>
                                </div>
                                <Badge :variant="operatingModeVariant" class="text-sm">{{ operatingModeLabel }}</Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap gap-2">
                                <Badge v-if="capabilities?.core_memory" variant="secondary">Core Memory</Badge>
                                <Badge v-if="capabilities?.working_memory" variant="secondary">Working Memory</Badge>
                                <Badge v-if="capabilities?.keyword_search" variant="secondary">Keyword Search</Badge>
                                <Badge v-if="capabilities?.semantic_search" variant="secondary">Semantic Search</Badge>
                                <Badge v-if="capabilities?.entity_extraction" variant="secondary">Entity Extraction</Badge>
                                <Badge v-if="capabilities?.graph_search" variant="secondary">Graph Search</Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Storage Statistics -->
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardContent class="pt-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                                        <Layers class="h-5 w-5 text-blue-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-muted-foreground">Core Blocks</p>
                                        <p class="text-2xl font-semibold">{{ formatNumber(stats?.core_blocks_count) }}</p>
                                        <p v-if="!stats?.core_blocks_count" class="text-xs text-muted-foreground/70">Created via memory API</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="pt-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-500/10">
                                        <Database class="h-5 w-5 text-purple-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-muted-foreground">Embeddings</p>
                                        <p class="text-2xl font-semibold">{{ formatNumber(stats?.embeddings_count) }}</p>
                                        <p v-if="!stats?.embeddings_count && operatingMode !== 'api'" class="text-xs text-muted-foreground/70">Requires API providers</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="pt-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-500/10">
                                        <Activity class="h-5 w-5 text-green-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-muted-foreground">Conversation Logs</p>
                                        <p class="text-2xl font-semibold">{{ formatNumber(stats?.conversation_logs_count) }}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="pt-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-500/10">
                                        <Brain class="h-5 w-5 text-orange-500" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-muted-foreground">Graph Entities</p>
                                        <p class="text-2xl font-semibold">{{ formatNumber(stats?.graph_entities_count || 0) }}</p>
                                        <p v-if="!stats?.graph_entities_count && operatingMode !== 'api'" class="text-xs text-muted-foreground/70">Requires API providers + Neo4j</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- CLI Usage (Subscription) -->
                    <Card>
                        <CardHeader>
                            <CardTitle>CLI Usage (Subscription)</CardTitle>
                            <CardDescription>Token consumption from agent run executions, tracked per provider and model.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="stats?.cli_usage" class="space-y-4">
                                <div class="flex items-center justify-between rounded-lg border bg-muted/30 p-4">
                                    <div>
                                        <p class="font-medium">Totals</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium">{{ formatNumber(stats.cli_usage.total_tokens) }} tokens</p>
                                        <p class="text-sm text-muted-foreground">{{ formatCost(stats.cli_usage.total_cost) }} est. cost</p>
                                    </div>
                                </div>
                                <div
                                    v-for="(usage, provider) in stats.cli_usage.by_provider"
                                    :key="`cli-${provider}`"
                                    class="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div>
                                        <p class="font-medium capitalize">{{ provider }}</p>
                                        <p class="text-xs text-muted-foreground">Subscription</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium">{{ formatNumber(usage.tokens) }} tokens</p>
                                        <p class="text-sm text-muted-foreground">{{ formatCost(usage.cost) }} est. cost</p>
                                    </div>
                                </div>
                                <div v-if="!stats.cli_usage.by_provider || Object.keys(stats.cli_usage.by_provider).length === 0" class="text-sm text-muted-foreground">
                                    No CLI usage recorded yet. Token usage is captured from agent run output.
                                </div>
                            </div>
                            <div v-else class="text-sm text-muted-foreground">
                                No CLI usage recorded yet.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Memory API Provider Usage -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Memory API Usage</CardTitle>
                            <CardDescription>API usage and cost tracking for memory operations (extraction, embedding, etc.).</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="stats?.provider_usage" class="space-y-4">
                                <div class="flex items-center justify-between rounded-lg border bg-muted/30 p-4">
                                    <div>
                                        <p class="font-medium">Totals</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium">{{ formatNumber(stats.provider_usage.total_tokens) }} tokens</p>
                                        <p class="text-sm text-muted-foreground">{{ formatCost(stats.provider_usage.total_cost) }} est. cost</p>
                                    </div>
                                </div>
                                <div
                                    v-for="(usage, provider) in stats.provider_usage.by_provider"
                                    :key="`api-${provider}`"
                                    class="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div>
                                        <p class="font-medium capitalize">{{ provider }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium">{{ formatNumber(usage.tokens) }} tokens</p>
                                        <p class="text-sm text-muted-foreground">{{ formatCost(usage.cost) }} est. cost</p>
                                    </div>
                                </div>
                                <div v-if="!stats.provider_usage.by_provider || Object.keys(stats.provider_usage.by_provider).length === 0" class="text-sm text-muted-foreground">
                                    No memory API usage recorded yet. Requires API providers to be configured.
                                </div>
                            </div>
                            <div v-else class="text-sm text-muted-foreground">
                                No memory API usage recorded yet.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Quick Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Actions</CardTitle>
                            <CardDescription>Common memory management operations.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap gap-3">
                                <Button variant="outline" disabled>
                                    <RefreshCw class="h-4 w-4" />
                                    Run Consolidation
                                </Button>
                                <Button variant="outline" disabled>
                                    <Trash2 class="h-4 w-4" />
                                    Prune (Dry Run)
                                </Button>
                                <Link :href="route('tools.memory.settings')">
                                    <Button variant="outline">
                                        <Settings class="h-4 w-4" />
                                        Configure Settings
                                    </Button>
                                </Link>
                            </div>
                            <p class="mt-3 text-xs text-muted-foreground">
                                Consolidation and pruning actions are available via Artisan commands:
                                <code class="rounded bg-muted px-1">php artisan memory:consolidate</code> and
                                <code class="rounded bg-muted px-1">php artisan memory:prune --force</code>
                            </p>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
