<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, AlertCircle, CheckCircle, XCircle, Activity, HeartPulse } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    health: Object,
    gatewayRunning: Boolean,
    queueHealth: Object,
    deadLetterCount: Number,
});

const refreshing = ref(false);

const refreshPage = () => {
    refreshing.value = true;
    router.reload({
        onFinish: () => {
            refreshing.value = false;
        },
    });
};

const statusBadgeVariant = computed(() => {
    const status = props.health?.status ?? 'unknown';
    if (status === 'healthy') return 'default';
    if (status === 'degraded') return 'destructive';
    if (status === 'partial') return 'secondary';
    return 'outline';
});

const getConnectorStatusClass = (runtimeState) => {
    switch (runtimeState) {
        case 'connected':
            return 'status-connected';
        case 'reconnecting':
            return 'status-reconnecting';
        case 'disconnected':
            return 'status-disconnected';
        case 'error':
            return 'status-error';
        default:
            return 'status-unknown';
    }
};

const getConnectorStatusLabel = (runtimeState) => {
    switch (runtimeState) {
        case 'connected':
            return 'Connected';
        case 'reconnecting':
            return 'Reconnecting';
        case 'disconnected':
            return 'Disconnected';
        case 'error':
            return 'Error';
        default:
            return 'Unknown';
    }
};

const formatDateTime = (isoString) => {
    if (!isoString) return 'Never';
    const date = new Date(isoString);
    return date.toLocaleString();
};
</script>

<template>
    <AppLayout title="Messenger Health Dashboard">
        <Head title="Messenger Health Dashboard" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <HeartPulse class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">Messenger Health Dashboard</h2>
                        <HelpHint
                            ui-key="messenger.health"
                            short-text="Monitor connector health and uptime status."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('messenger.dead-letters.index')">
                        <Button variant="outline" size="sm">
                            <AlertCircle class="h-4 w-4 mr-1" />
                            Failed Messages
                            <Badge v-if="deadLetterCount > 0" variant="destructive" class="ml-2">
                                {{ deadLetterCount }}
                            </Badge>
                        </Button>
                    </Link>
                    <Link :href="route('tools.messenger.index')">
                        <Button variant="outline" size="sm">
                            Control Plane
                        </Button>
                    </Link>
                    <Button variant="outline" size="sm" :disabled="refreshing" @click="refreshPage">
                        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': refreshing }" />
                        {{ refreshing ? 'Refreshing' : 'Refresh' }}
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <!-- Health Summary Cards -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardDescription>Overall Status</CardDescription>
                            <Badge :variant="statusBadgeVariant" class="mt-2 w-fit uppercase">
                                {{ health.status }}
                            </Badge>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>Connectors</CardDescription>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="text-lg font-semibold text-foreground">
                                    {{ health.summary.connected }} / {{ health.summary.total_connectors }}
                                </span>
                                <span class="text-sm text-muted-foreground">connected</span>
                            </div>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>Gateway Process</CardDescription>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="status-dot" :class="gatewayRunning ? 'status-connected' : 'status-disconnected'"></span>
                                <span class="text-lg font-semibold text-foreground">
                                    {{ gatewayRunning ? 'Running' : 'Not Running' }}
                                </span>
                            </div>
                            <p v-if="!gatewayRunning" class="mt-1 text-xs text-muted-foreground">
                                Start with: <code class="rounded bg-muted px-1">php artisan agent:messenger-gateway</code>
                            </p>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>Failed Messages</CardDescription>
                            <div class="mt-2">
                                <span class="text-lg font-semibold" :class="deadLetterCount > 0 ? 'text-destructive' : 'text-foreground'">
                                    {{ deadLetterCount }}
                                </span>
                            </div>
                        </CardHeader>
                    </Card>
                </div>

                <!-- Status Breakdown -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Connected</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2">
                                <span class="status-dot status-connected"></span>
                                <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ health.summary.connected }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Reconnecting</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2">
                                <span class="status-dot status-reconnecting"></span>
                                <span class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                                    {{ health.summary.reconnecting }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Disconnected</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2">
                                <span class="status-dot status-disconnected"></span>
                                <span class="text-2xl font-bold text-muted-foreground">
                                    {{ health.summary.disconnected }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription>Errors</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-2">
                                <span class="status-dot status-error"></span>
                                <span class="text-2xl font-bold text-red-600 dark:text-red-400">
                                    {{ health.summary.error }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Connectors Table -->
                <Card>
                    <CardHeader>
                        <CardTitle>Connectors</CardTitle>
                        <CardDescription>All configured messenger connectors with their current status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Provider</TableHead>
                                    <TableHead>Mode</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Last Health Check</TableHead>
                                    <TableHead>Error</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="connector in health.connectors" :key="connector.id">
                                    <TableCell class="font-medium">{{ connector.name }}</TableCell>
                                    <TableCell class="capitalize text-muted-foreground">{{ connector.provider }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="connector.mode === 'local' ? 'default' : 'secondary'">
                                            {{ connector.mode === 'local' ? 'Local' : 'Webhook' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <span class="status-dot" :class="getConnectorStatusClass(connector.runtime_state)"></span>
                                            <span>{{ getConnectorStatusLabel(connector.runtime_state) }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ formatDateTime(connector.last_health_check_at) }}
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="connector.error_message" class="text-destructive text-sm" :title="connector.error_message">
                                            {{ connector.error_message.length > 50 ? connector.error_message.substring(0, 50) + '...' : connector.error_message }}
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="health.connectors.length === 0">
                                    <TableCell colspan="6" class="text-center text-muted-foreground py-8">
                                        No connectors configured.
                                        <Link :href="route('tools.messenger.index')" class="text-primary underline">
                                            Add a connector
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <!-- Queue Health -->
                <Card>
                    <CardHeader>
                        <CardTitle>Queue Health</CardTitle>
                        <CardDescription>Message processing queue status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="rounded-md border border-border bg-muted/50 px-4 py-3">
                                <p class="text-sm text-muted-foreground">Status</p>
                                <p class="mt-1 text-lg font-semibold text-foreground capitalize">{{ queueHealth.status }}</p>
                            </div>
                            <div class="rounded-md border border-border bg-muted/50 px-4 py-3">
                                <p class="text-sm text-muted-foreground">Pending Jobs</p>
                                <p class="mt-1 text-lg font-semibold text-foreground">{{ queueHealth.pending_jobs }}</p>
                            </div>
                            <div class="rounded-md border border-border bg-muted/50 px-4 py-3">
                                <p class="text-sm text-muted-foreground">Failed Jobs</p>
                                <p class="mt-1 text-lg font-semibold" :class="queueHealth.failed_jobs > 0 ? 'text-destructive' : 'text-foreground'">
                                    {{ queueHealth.failed_jobs }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.status-connected {
    background: #22c55e;
}

.status-reconnecting {
    background: #eab308;
    animation: pulse 1s infinite;
}

.status-disconnected {
    background: #9ca3af;
}

.status-error {
    background: #ef4444;
}

.status-unknown {
    background: #6b7280;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}
</style>
