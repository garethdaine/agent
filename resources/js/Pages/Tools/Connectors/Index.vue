<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Plug, RefreshCw, Library, CheckCircle, XCircle } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const activeTab = ref('connected');
const loading = ref(true);
const error = ref('');
const connections = ref([]);
let refreshTimer = null;

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/agent/api/v1/connectors', {
            params: { status: 'connected' },
        });
        connections.value = data.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load connected services.';
    } finally {
        loading.value = false;
    }
};

const healthColor = (score) => {
    if (score >= 0.8) return 'bg-green-500';
    if (score >= 0.5) return 'bg-yellow-500';
    return 'bg-red-500';
};

const healthLabel = (score) => {
    if (score >= 0.8) return 'Healthy';
    if (score >= 0.5) return 'Degraded';
    return 'Unhealthy';
};

const statusVariant = (status) => {
    if (status === 'connected') return 'default';
    if (status === 'degraded') return 'secondary';
    if (status === 'pending') return 'outline';
    return 'destructive';
};

const testingId = ref(null);
const testResult = ref(null);

const testConnection = async (connector) => {
    testingId.value = connector.id;
    testResult.value = null;
    try {
        const { data } = await axios.post(`/agent/api/v1/connectors/${connector.id}/test`);
        testResult.value = { id: connector.id, success: true, message: 'Connection healthy' };
    } catch (e) {
        testResult.value = { id: connector.id, success: false, message: e?.response?.data?.error?.message ?? 'Test failed' };
    } finally {
        testingId.value = null;
    }
};

const formatTime = (timestamp) => {
    if (!timestamp) return 'Never';
    const d = new Date(timestamp);
    return d.toLocaleString();
};

onMounted(() => {
    load();
    refreshTimer = setInterval(load, 30000);
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});
</script>

<template>
    <AppLayout title="Connectors">
        <Head title="Connectors" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Plug class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Connectors</h2>
                        <HelpHint
                            ui-key="connectors.overview"
                            short-text="Connect external services and manage integrations."
                            learn-more-href="/docs/connectors"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('tools.connectors.library')"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-2 text-sm hover:bg-muted"
                    >
                        <Library class="h-4 w-4" />
                        Browse Library
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <div v-if="testResult" class="rounded-md border px-3 py-2 text-sm transition-all" :class="testResult.success ? 'border-green-500/50 bg-green-500/10 text-green-700' : 'border-destructive/50 bg-destructive/10 text-destructive'">
                    <div class="flex items-center gap-2">
                        <CheckCircle v-if="testResult.success" class="h-4 w-4 shrink-0" />
                        <XCircle v-else class="h-4 w-4 shrink-0" />
                        {{ testResult.message }}
                    </div>
                </div>

                <div v-if="loading" class="text-center py-12 text-muted-foreground">Loading connected services...</div>

                <div v-else-if="connections.length === 0" class="text-center py-12">
                    <Plug class="mx-auto h-12 w-12 text-muted-foreground/50" />
                    <h3 class="mt-4 text-sm font-semibold text-foreground">No services connected</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Browse the library to connect your first service.</p>
                    <Link
                        :href="route('tools.connectors.library')"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground hover:bg-primary/90"
                    >
                        <Library class="h-4 w-4" />
                        Browse Library
                    </Link>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Card v-for="connector in connections" :key="connector.id">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium">{{ connector.display_name || connector.name }}</CardTitle>
                                <Badge :variant="statusVariant(connector.status)">{{ connector.status }}</Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-2 rounded-full" :class="healthColor(connector.health_score ?? 1)"></div>
                                    <span class="text-sm text-muted-foreground">{{ healthLabel(connector.health_score ?? 1) }}</span>
                                    <span class="text-xs text-muted-foreground ml-auto">{{ ((connector.health_score ?? 1) * 100).toFixed(0) }}%</span>
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                                    <div>
                                        <span class="block text-foreground font-medium">{{ connector.action_count_24h ?? 0 }}</span>
                                        Actions (24h)
                                    </div>
                                    <div>
                                        <span class="block text-foreground font-medium">{{ connector.error_count_24h ?? 0 }}</span>
                                        Errors (24h)
                                    </div>
                                </div>

                                <p class="text-xs text-muted-foreground">
                                    Last action: {{ formatTime(connector.last_action_at) }}
                                </p>

                                <div class="flex items-center gap-2 pt-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="testingId === connector.id"
                                        @click="testConnection(connector)"
                                    >
                                        <RefreshCw class="h-3 w-3 mr-1" :class="{ 'animate-spin': testingId === connector.id }" />
                                        Test
                                    </Button>
                                    <Link
                                        :href="route('tools.connectors.show', connector.id)"
                                        class="inline-flex items-center rounded-md border border-border px-2.5 py-1.5 text-xs hover:bg-muted"
                                    >
                                        Details
                                    </Link>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
