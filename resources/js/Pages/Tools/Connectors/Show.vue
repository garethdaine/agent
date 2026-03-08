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
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import { Plug, ArrowLeft, RefreshCw, CheckCircle, XCircle, Shield } from 'lucide-vue-next';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const props = defineProps({
    connectorId: {
        type: String,
        required: true,
    },
});

const activeTab = ref('overview');
const loading = ref(true);
const error = ref('');
const connector = ref(null);
const telemetry = ref([]);
const telemetryLoading = ref(false);

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`/agent/api/v1/connectors/${props.connectorId}`);
        connector.value = data.data ?? null;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load connector details.';
    } finally {
        loading.value = false;
    }
};

const loadTelemetry = async () => {
    if (!connector.value) return;
    telemetryLoading.value = true;
    try {
        const { data } = await axios.get(`/agent/api/v1/connectors/${props.connectorId}/telemetry`);
        telemetry.value = data.data ?? [];
    } catch {
        telemetry.value = [];
    } finally {
        telemetryLoading.value = false;
    }
};

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'actions', label: 'Actions' },
    { key: 'telemetry', label: 'Telemetry' },
    { key: 'settings', label: 'Settings' },
];

const switchTab = (tab) => {
    activeTab.value = tab;
    if (tab === 'telemetry' && telemetry.value.length === 0) {
        loadTelemetry();
    }
};

const healthColor = (score) => {
    if (score >= 0.8) return 'text-green-600';
    if (score >= 0.5) return 'text-amber-600';
    return 'text-red-600';
};

const healthLabel = (score) => {
    if (score >= 0.8) return 'Healthy';
    if (score >= 0.5) return 'Degraded';
    return 'Unhealthy';
};

const statusVariant = (status) => {
    if (status === 'connected') return 'default';
    if (status === 'available') return 'secondary';
    if (status === 'degraded') return 'secondary';
    return 'destructive';
};

const methodColor = (method) => {
    const colors = {
        GET: 'bg-green-100 text-green-800',
        POST: 'bg-blue-100 text-blue-800',
        PUT: 'bg-yellow-100 text-yellow-800',
        PATCH: 'bg-orange-100 text-orange-800',
        DELETE: 'bg-red-100 text-red-800',
    };
    return colors[method?.toUpperCase()] || 'bg-gray-100 text-gray-800';
};

const testingConnection = ref(false);
const testResult = ref(null);

const testConnection = async () => {
    testingConnection.value = true;
    testResult.value = null;
    try {
        await axios.post(`/agent/api/v1/connectors/${props.connectorId}/test`);
        testResult.value = { success: true, message: 'Connection healthy' };
    } catch (e) {
        testResult.value = { success: false, message: e?.response?.data?.error?.message ?? 'Test failed' };
    } finally {
        testingConnection.value = false;
    }
};

const disconnecting = ref(false);

const disconnect = async () => {
    if (!confirm('Are you sure you want to disconnect this service? This action cannot be undone.')) return;
    disconnecting.value = true;
    try {
        await axios.delete(`/agent/api/v1/connectors/${props.connectorId}/disconnect`);
        await load();
    } catch (e) {
        alert(e?.response?.data?.error?.message ?? 'Disconnect failed.');
    } finally {
        disconnecting.value = false;
    }
};

const formatTime = (timestamp) => {
    if (!timestamp) return 'Never';
    return new Date(timestamp).toLocaleString();
};

const outcomeVariant = (outcome) => {
    if (outcome === 'success') return 'default';
    if (outcome === 'error') return 'destructive';
    return 'secondary';
};

onMounted(load);
</script>

<template>
    <AppLayout title="Connector Details">
        <Head :title="connector?.display_name || connector?.name || 'Connector'" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Plug class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            {{ connector?.display_name || connector?.name || 'Connector' }}
                        </h2>
                        <HelpHint
                            ui-key="connectors.detail"
                            short-text="View connector details, actions, and telemetry."
                            learn-more-href="/docs/connectors"
                        />
                    </div>
                </div>
                <Link
                    :href="route('tools.connectors.index')"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-2 text-sm hover:bg-muted"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <div v-if="loading" class="text-center py-12 text-muted-foreground">Loading connector...</div>

                <template v-else-if="connector">
                    <!-- Tabs -->
                    <div class="border-b border-border">
                        <nav class="flex gap-4">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                class="border-b-2 px-1 pb-3 text-sm font-medium transition-colors"
                                :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                                @click="switchTab(tab.key)"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <!-- Overview Tab -->
                    <div v-if="activeTab === 'overview'" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Connection Status</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-2">
                                            <Badge :variant="statusVariant(connector.status)">{{ connector.status }}</Badge>
                                        </div>
                                        <p class="text-sm text-muted-foreground">Auth type: <span class="text-foreground">{{ connector.auth_type }}</span></p>
                                        <p class="text-sm text-muted-foreground">Category: <span class="text-foreground">{{ connector.category }}</span></p>
                                        <p class="text-sm text-muted-foreground">Version: <span class="text-foreground">{{ connector.version ?? 'n/a' }}</span></p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Health</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-2xl font-semibold" :class="healthColor(connector.health_score ?? 1)">
                                                {{ ((connector.health_score ?? 1) * 100).toFixed(0) }}%
                                            </span>
                                            <span class="text-sm text-muted-foreground">{{ healthLabel(connector.health_score ?? 1) }}</span>
                                        </div>
                                        <div class="h-2 w-full rounded-full bg-muted">
                                            <div
                                                class="h-2 rounded-full transition-all"
                                                :class="connector.health_score >= 0.8 ? 'bg-green-500' : connector.health_score >= 0.5 ? 'bg-yellow-500' : 'bg-red-500'"
                                                :style="{ width: ((connector.health_score ?? 1) * 100) + '%' }"
                                            ></div>
                                        </div>
                                        <p class="text-sm text-muted-foreground">Last health check: {{ formatTime(connector.last_health_check_at) }}</p>
                                        <p class="text-sm text-muted-foreground">Last action: {{ formatTime(connector.last_action_at) }}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Actions (24h)</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-2xl font-semibold text-foreground">{{ connector.action_count_24h ?? 0 }}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Errors (24h)</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-2xl font-semibold" :class="(connector.error_count_24h ?? 0) > 0 ? 'text-red-600' : 'text-foreground'">{{ connector.error_count_24h ?? 0 }}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Risk Level</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-2xl font-semibold text-foreground">{{ connector.risk_level ?? 'standard' }}</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- Actions Tab -->
                    <div v-if="activeTab === 'actions'">
                        <Card v-if="connector.actions && connector.actions.length > 0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Approval Required</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="action in connector.actions" :key="action.name">
                                        <TableCell class="font-medium text-sm">{{ action.name }}</TableCell>
                                        <TableCell>
                                            <span :class="['inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase', methodColor(action.method)]">
                                                {{ action.method }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="text-sm text-muted-foreground">{{ action.description || '-' }}</TableCell>
                                        <TableCell>
                                            <Shield v-if="action.requires_approval" class="h-4 w-4 text-amber-500" />
                                            <span v-else class="text-xs text-muted-foreground">No</span>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </Card>
                        <div v-else class="text-center py-12 text-sm text-muted-foreground">
                            No actions available for this connector.
                        </div>
                    </div>

                    <!-- Telemetry Tab -->
                    <div v-if="activeTab === 'telemetry'">
                        <div v-if="telemetryLoading" class="text-center py-12 text-muted-foreground">Loading telemetry...</div>
                        <Card v-else-if="telemetry.length > 0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Action</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Duration</TableHead>
                                        <TableHead>Outcome</TableHead>
                                        <TableHead>Timestamp</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="entry in telemetry" :key="entry.id">
                                        <TableCell class="font-medium text-sm">{{ entry.action_name }}</TableCell>
                                        <TableCell class="text-sm">{{ entry.http_status ?? '-' }}</TableCell>
                                        <TableCell class="text-sm text-muted-foreground">{{ entry.duration_ms ? entry.duration_ms + 'ms' : '-' }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="outcomeVariant(entry.outcome)">{{ entry.outcome }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-sm text-muted-foreground">{{ formatTime(entry.created_at) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </Card>
                        <div v-else class="text-center py-12 text-sm text-muted-foreground">
                            No telemetry data recorded yet.
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div v-if="activeTab === 'settings'" class="space-y-4">
                        <div v-if="testResult" class="rounded-md border px-3 py-2 text-sm" :class="testResult.success ? 'border-green-500/50 bg-green-500/10 text-green-700' : 'border-destructive/50 bg-destructive/10 text-destructive'">
                            <div class="flex items-center gap-2">
                                <CheckCircle v-if="testResult.success" class="h-4 w-4 shrink-0" />
                                <XCircle v-else class="h-4 w-4 shrink-0" />
                                {{ testResult.message }}
                            </div>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle class="text-sm font-medium">Connection Management</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="flex items-center gap-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="testingConnection"
                                        @click="testConnection"
                                    >
                                        <RefreshCw class="h-3 w-3 mr-1" :class="{ 'animate-spin': testingConnection }" />
                                        Test Connection
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        :disabled="disconnecting"
                                        @click="disconnect"
                                    >
                                        {{ disconnecting ? 'Disconnecting...' : 'Disconnect' }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
