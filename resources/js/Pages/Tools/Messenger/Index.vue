<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { Head, Link } from '@inertiajs/vue3';
import { RefreshCw, AlertCircle } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';

const loading = ref(false);
const refreshing = ref(false);
const detailsLoading = ref(false);
const error = ref('');
const detailsError = ref('');
const connectError = ref('');
const connectSuccess = ref('');
const connectSubmitting = ref(false);
const schemaLoading = ref(false);
const connectorSchema = ref({
    providers: {},
    provider_order: [],
});
const connectFieldErrors = ref({});
const actionBusyByConnector = ref({});

const health = ref({
    status: 'unknown',
    connectors: [],
    queue: { backlog_size: 0 },
    recent_error_rate: 0,
    dead_letter_count: 0,
});

const metrics = ref({
    inbound_messages: {},
    actions: {},
    latency: {},
    webhook_failures: {},
    connectors: {},
});

const connectors = ref([]);
const sessions = ref([]);
const selectedSessionId = ref(null);
const sessionMessages = ref([]);
const sessionActions = ref([]);
const connectForm = ref({
    provider: '',
    name: '',
    connection_mode: 'webhook',
    credentials: {},
    confirmation_required: true,
    default_verbosity: 'summary',
});

const selectedSession = computed(() => sessions.value.find((item) => item.id === selectedSessionId.value) ?? null);
const providerOrder = computed(() => connectorSchema.value?.provider_order ?? []);
const availableProviders = computed(() => {
    const providers = connectorSchema.value?.providers ?? {};

    return providerOrder.value
        .map((key) => providers[key])
        .filter((provider) => provider && provider.enabled === true);
});
const selectedProviderSchema = computed(() => {
    const provider = String(connectForm.value.provider ?? '');
    const providers = connectorSchema.value?.providers ?? {};

    return providers[provider] ?? null;
});
const selectedProviderFields = computed(() => selectedProviderSchema.value?.credential_fields ?? []);
const selectedProviderModes = computed(() => selectedProviderSchema.value?.supported_connection_modes ?? ['webhook']);
const providerDescriptions = computed(() => {
    return availableProviders.value.map((provider) => ({
        key: provider.key,
        label: provider.label,
        description: provider.description,
    }));
});

const healthBadgeVariant = computed(() => {
    const status = String(health.value?.status ?? '').toLowerCase();
    if (status === 'healthy') return 'default';
    if (status === 'degraded') return 'secondary';
    return 'outline';
});

const connectorCount = computed(() => connectors.value.length);
const connectedCount = computed(() => {
    return connectors.value.filter((item) => {
        const effectiveState = String(item?.runtime_state ?? item?.status ?? '').trim().toLowerCase();
        return effectiveState === 'connected';
    }).length;
});
const queueBacklog = computed(() => Number(health.value?.queue?.backlog_size ?? 0));
const recentErrorRate = computed(() => Number(health.value?.recent_error_rate ?? 0));
const deadLetterCount = computed(() => Number(health.value?.dead_letter_count ?? 0));

const inboundRows = computed(() => Object.entries(metrics.value?.inbound_messages ?? {}));
const webhookFailureRows = computed(() => Object.entries(metrics.value?.webhook_failures ?? {}));
const actionRows = computed(() => {
    return Object.entries(metrics.value?.actions ?? {})
        .map(([actionType, payload]) => ({
            actionType,
            success: Number(payload?.success ?? 0),
            failure: Number(payload?.failure ?? 0),
        }))
        .sort((a, b) => (b.success + b.failure) - (a.success + a.failure));
});

const totalActionSuccess = computed(() => actionRows.value.reduce((sum, row) => sum + row.success, 0));
const totalActionFailure = computed(() => actionRows.value.reduce((sum, row) => sum + row.failure, 0));

const createEmptyCredentialState = (providerKey) => {
    const providers = connectorSchema.value?.providers ?? {};
    const provider = providers[providerKey] ?? null;

    if (!provider || !Array.isArray(provider.credential_fields)) {
        return {};
    }

    return provider.credential_fields.reduce((carry, field) => {
        if (field?.key) {
            carry[field.key] = '';
        }

        return carry;
    }, {});
};

const initializeConnectForm = () => {
    if (!availableProviders.value.length) {
        connectForm.value.provider = '';
        connectForm.value.name = '';
        connectForm.value.credentials = {};
        return;
    }

    const firstProvider = availableProviders.value[0];
    connectForm.value.provider = firstProvider.key;
    connectForm.value.name = `${firstProvider.label} Connector`;
    connectForm.value.connection_mode = firstProvider.default_connection_mode ?? 'webhook';
    connectForm.value.credentials = createEmptyCredentialState(firstProvider.key);
    connectForm.value.confirmation_required = true;
    connectForm.value.default_verbosity = 'summary';
};

const getFieldError = (fieldKey) => {
    return connectFieldErrors.value[`credentials.${fieldKey}`] ?? null;
};

const extractApiErrorMessage = (requestError, fallback) => {
    const payload = requestError?.response?.data;
    const topLevelMessage = typeof payload?.message === 'string' ? payload.message.trim() : '';
    const nestedMessage = typeof payload?.error?.message === 'string' ? payload.error.message.trim() : '';

    if (nestedMessage !== '') {
        return nestedMessage;
    }

    if (topLevelMessage !== '') {
        return topLevelMessage;
    }

    const runtimeMessage = typeof requestError?.message === 'string' ? requestError.message.trim() : '';
    if (runtimeMessage !== '') {
        return runtimeMessage;
    }

    return fallback;
};

const isLikelyMarkdown = (content) => {
    const text = String(content ?? '').trim();
    if (text === '') {
        return false;
    }

    // Markdown headings, emphasis, lists, blockquotes, links, code fences/inline code.
    return /(^|\n)\s{0,3}(#{1,6}\s|[-*+]\s|\d+\.\s|>\s)|\*\*[^*\n]+\*\*|`{1,3}[^`\n]+`{1,3}|\[[^\]]+\]\([^)]+\)/m.test(text);
};

const loadConnectorSchema = async () => {
    schemaLoading.value = true;

    try {
        const schemaResponse = await axios.get('/agent/api/v1/messenger/connectors/schema');
        connectorSchema.value = schemaResponse?.data?.data ?? { providers: {}, provider_order: [] };
        initializeConnectForm();
    } catch (requestError) {
        error.value = extractApiErrorMessage(requestError, 'Failed to load connector schema.');
    } finally {
        schemaLoading.value = false;
    }
};

const loadOverview = async () => {
    loading.value = true;
    error.value = '';

    const failures = [];

    try {
        const [healthResult, metricsResult, connectorsResult, sessionsResult] = await Promise.allSettled([
            axios.get('/agent/api/v1/health/messenger'),
            axios.get('/agent/api/v1/messenger/metrics'),
            axios.get('/agent/api/v1/messenger/connectors', {
                params: { with_session_count: 1, per_page: 50, sort: 'updated_at', dir: 'desc' },
            }),
            axios.get('/agent/api/v1/chat/sessions', {
                params: {
                    with_connector: 1,
                    with_message_count: 1,
                    per_page: 20,
                    sort: 'updated_at',
                    dir: 'desc',
                },
            }),
        ]);

        if (healthResult.status === 'fulfilled') {
            health.value = healthResult.value?.data ?? health.value;
        } else {
            failures.push(`Health: ${extractApiErrorMessage(healthResult.reason, 'request failed')}`);
        }

        if (metricsResult.status === 'fulfilled') {
            metrics.value = metricsResult.value?.data?.data ?? metrics.value;
        } else {
            failures.push(`Metrics: ${extractApiErrorMessage(metricsResult.reason, 'request failed')}`);
        }

        if (connectorsResult.status === 'fulfilled') {
            connectors.value = Array.isArray(connectorsResult.value?.data?.data)
                ? connectorsResult.value.data.data
                : [];
        } else {
            failures.push(`Connectors: ${extractApiErrorMessage(connectorsResult.reason, 'request failed')}`);
        }

        if (sessionsResult.status === 'fulfilled') {
            sessions.value = Array.isArray(sessionsResult.value?.data?.data)
                ? sessionsResult.value.data.data
                : [];
        } else {
            failures.push(`Sessions: ${extractApiErrorMessage(sessionsResult.reason, 'request failed')}`);
        }

        if (!selectedSessionId.value && sessions.value.length > 0) {
            selectedSessionId.value = sessions.value[0].id;
        }

        if (selectedSessionId.value) {
            await loadSessionDetails(selectedSessionId.value);
        } else {
            sessionMessages.value = [];
            sessionActions.value = [];
        }

        if (failures.length > 0) {
            error.value = failures.join(' | ');
        }
    } catch (requestError) {
        error.value = extractApiErrorMessage(requestError, 'Failed to load messenger control-plane data.');
    } finally {
        loading.value = false;
    }
};

const loadSessionDetails = async (sessionId) => {
    if (!sessionId) {
        return;
    }

    detailsLoading.value = true;
    detailsError.value = '';

    try {
        const [messagesResponse, actionsResponse] = await Promise.all([
            axios.get(`/agent/api/v1/chat/sessions/${sessionId}/messages`, {
                params: { with_actions: 1, with_attachments: 1, per_page: 30, sort: 'created_at', dir: 'desc' },
            }),
            axios.get(`/agent/api/v1/chat/sessions/${sessionId}/actions`, {
                params: { per_page: 30, sort: 'created_at', dir: 'desc' },
            }),
        ]);

        sessionMessages.value = Array.isArray(messagesResponse?.data?.data) ? messagesResponse.data.data : [];
        sessionActions.value = Array.isArray(actionsResponse?.data?.data) ? actionsResponse.data.data : [];
    } catch (requestError) {
        detailsError.value = extractApiErrorMessage(requestError, 'Failed to load session details.');
    } finally {
        detailsLoading.value = false;
    }
};

const refreshAll = async () => {
    refreshing.value = true;
    await loadOverview();
    refreshing.value = false;
};

const setConnectorActionBusy = (connectorId, isBusy) => {
    actionBusyByConnector.value = {
        ...actionBusyByConnector.value,
        [connectorId]: isBusy,
    };
};

const isConnectorActionBusy = (connectorId) => Boolean(actionBusyByConnector.value?.[connectorId]);

const submitConnector = async () => {
    if (!connectForm.value.provider) {
        connectError.value = 'Select a provider to continue.';
        return;
    }

    connectSubmitting.value = true;
    connectError.value = '';
    connectSuccess.value = '';
    connectFieldErrors.value = {};

    try {
        const payloadCredentials = Object.entries(connectForm.value.credentials ?? {}).reduce((carry, [key, value]) => {
            const normalizedValue = String(value ?? '').trim();

            if (normalizedValue !== '') {
                carry[key] = normalizedValue;
            }

            return carry;
        }, {});

        const response = await axios.post('/agent/api/v1/messenger/connectors', {
            provider: connectForm.value.provider,
            name: String(connectForm.value.name ?? '').trim(),
            connection_mode: connectForm.value.connection_mode,
            credentials: payloadCredentials,
            config: {
                confirmation_required: Boolean(connectForm.value.confirmation_required),
                default_verbosity: String(connectForm.value.default_verbosity ?? 'summary'),
            },
        });

        const connector = response?.data?.data ?? null;
        const connectorId = connector?.id ?? null;

        if (connectorId) {
            try {
                await axios.post(`/agent/api/v1/messenger/connectors/${connectorId}/test`);
            } catch (requestError) {
                connectError.value = extractApiErrorMessage(requestError, 'Connector saved, but connectivity check failed.');
            }
        }

        const providerLabel = selectedProviderSchema.value?.label ?? connectForm.value.provider;
        connectSuccess.value = `${providerLabel} connector saved.`;
        await loadOverview();
    } catch (requestError) {
        const details = requestError?.response?.data?.error?.details ?? {};
        connectFieldErrors.value = typeof details === 'object' && details !== null ? details : {};
        connectError.value = extractApiErrorMessage(requestError, 'Failed to save connector.');
    } finally {
        connectSubmitting.value = false;
    }
};

const retestConnector = async (connector) => {
    setConnectorActionBusy(connector.id, true);
    connectError.value = '';
    connectSuccess.value = '';

    try {
        const response = await axios.post(`/agent/api/v1/messenger/connectors/${connector.id}/test`);
        const message = response?.data?.data?.message ?? 'Connectivity test completed.';
        connectSuccess.value = `${connector.name}: ${message}`;
        await loadOverview();
    } catch (requestError) {
        connectError.value = extractApiErrorMessage(requestError, `Failed to retest ${connector.name}.`);
    } finally {
        setConnectorActionBusy(connector.id, false);
    }
};

const disconnectConnector = async (connector) => {
    const approved = window.confirm(`Disconnect ${connector.name}? This will remove stored credentials.`);
    if (!approved) {
        return;
    }

    setConnectorActionBusy(connector.id, true);
    connectError.value = '';
    connectSuccess.value = '';

    try {
        await axios.delete(`/agent/api/v1/messenger/connectors/${connector.id}`);
        connectSuccess.value = `${connector.name} disconnected.`;
        await loadOverview();
    } catch (requestError) {
        connectError.value = extractApiErrorMessage(requestError, `Failed to disconnect ${connector.name}.`);
    } finally {
        setConnectorActionBusy(connector.id, false);
    }
};

const selectSession = async (sessionId) => {
    if (!sessionId || selectedSessionId.value === sessionId) {
        return;
    }

    selectedSessionId.value = sessionId;
    await loadSessionDetails(sessionId);
};

const getConnectorStatusVariant = (status) => {
    if (status === 'connected') return 'default';
    if (status === 'error') return 'destructive';
    return 'outline';
};

const getConnectorRuntimeStateClass = (runtimeState) => {
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

watch(
    () => connectForm.value.provider,
    (providerKey) => {
        if (!providerKey) {
            return;
        }

        const provider = (connectorSchema.value?.providers ?? {})[providerKey];
        if (!provider) {
            return;
        }

        connectForm.value.connection_mode = provider.default_connection_mode ?? 'webhook';
        connectForm.value.credentials = createEmptyCredentialState(providerKey);
        if (String(connectForm.value.name ?? '').trim() === '') {
            connectForm.value.name = `${provider.label} Connector`;
        }
    }
);

onMounted(async () => {
    await loadConnectorSchema();
    await loadOverview();
});
</script>

<template>
    <AppLayout title="Messenger Control Plane">
        <Head title="Messenger Control Plane" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-foreground">Messenger Control Plane</h2>
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
                    <Link :href="route('messenger.health.dashboard')">
                        <Button variant="outline" size="sm">
                            Health
                        </Button>
                    </Link>
                    <Button variant="outline" size="sm" :disabled="loading || refreshing" @click="refreshAll">
                        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': refreshing }" />
                        {{ refreshing ? 'Refreshing' : 'Refresh' }}
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px] space-y-4">
                <Skeleton v-if="loading" class="h-8 w-64" />
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>
                <div v-if="connectError" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ connectError }}</div>
                <div v-if="connectSuccess" class="rounded-md border border-success/50 bg-success/10 px-3 py-2 text-sm text-success">{{ connectSuccess }}</div>

                <Card>
                    <CardHeader>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <CardTitle>Connect Messenger Service</CardTitle>
                            <CardDescription>Credentials are encrypted at rest and never returned in API responses.</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Skeleton v-if="schemaLoading" class="h-6 w-48" />
                        <div v-if="!schemaLoading && providerDescriptions.length > 0" class="mb-4 flex flex-wrap gap-2">
                            <Badge v-for="provider in providerDescriptions" :key="provider.key" variant="secondary">
                                {{ provider.label }}: {{ provider.description }}
                            </Badge>
                        </div>

                        <form class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="submitConnector">
                            <label class="text-sm text-muted-foreground">
                                Provider
                                <select
                                    v-model="connectForm.provider"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="schemaLoading || connectSubmitting"
                                >
                                    <option value="" disabled>Select provider</option>
                                    <option v-for="provider in availableProviders" :key="provider.key" :value="provider.key">
                                        {{ provider.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="text-sm text-muted-foreground">
                                Connector Name
                                <Input
                                    v-model="connectForm.name"
                                    type="text"
                                    class="mt-1"
                                    :disabled="connectSubmitting"
                                    placeholder="My Workspace"
                                />
                            </label>

                            <label class="text-sm text-muted-foreground">
                                Connection Mode
                                <select
                                    v-model="connectForm.connection_mode"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="connectSubmitting"
                                >
                                    <option v-for="mode in selectedProviderModes" :key="mode" :value="mode">{{ mode }}</option>
                                </select>
                            </label>

                            <label
                                v-for="field in selectedProviderFields"
                                :key="field.key"
                                class="text-sm text-muted-foreground"
                            >
                                {{ field.label }}<span v-if="field.required" class="text-destructive"> *</span>
                                <Input
                                    v-model="connectForm.credentials[field.key]"
                                    :type="field.type === 'password' ? 'password' : 'text'"
                                    class="mt-1"
                                    :disabled="connectSubmitting"
                                    :placeholder="field.placeholder || ''"
                                    :error="!!getFieldError(field.key)"
                                />
                                <p v-if="getFieldError(field.key)" class="mt-1 text-xs text-destructive">
                                    {{ Array.isArray(getFieldError(field.key)) ? getFieldError(field.key)[0] : getFieldError(field.key) }}
                                </p>
                            </label>

                            <div class="flex items-center gap-2">
                                <label class="inline-flex items-center gap-2 text-sm text-muted-foreground">
                                    <input v-model="connectForm.confirmation_required" type="checkbox" class="rounded border-input" :disabled="connectSubmitting" />
                                    Require confirmation
                                </label>
                            </div>

                            <label class="text-sm text-muted-foreground">
                                Verbosity
                                <select
                                    v-model="connectForm.default_verbosity"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="connectSubmitting"
                                >
                                    <option value="summary">summary</option>
                                    <option value="verbose">verbose</option>
                                </select>
                            </label>

                            <div class="md:col-span-2 xl:col-span-3">
                                <Button type="submit" :disabled="connectSubmitting || schemaLoading || !connectForm.provider">
                                    {{ connectSubmitting ? 'Saving...' : 'Save & Connect' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardDescription>Health</CardDescription>
                            <Badge :variant="healthBadgeVariant" class="mt-2 w-fit uppercase">{{ health.status }}</Badge>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Connectors</CardDescription>
                            <p class="mt-2 text-lg font-semibold text-foreground">{{ connectedCount }} / {{ connectorCount }} connected</p>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Queue Backlog</CardDescription>
                            <p class="mt-2 text-lg font-semibold text-foreground">{{ queueBacklog }}</p>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Recent Error Rate</CardDescription>
                            <p class="mt-2 text-lg font-semibold text-foreground">{{ recentErrorRate.toFixed(2) }}%</p>
                        </CardHeader>
                    </Card>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Connectors</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Mode</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Sessions</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="connector in connectors" :key="connector.id">
                                        <TableCell>
                                            <p class="font-medium">{{ connector.name }}</p>
                                            <p v-if="connector.setup?.webhook_url" class="mt-0.5 break-all text-xs text-muted-foreground">
                                                {{ connector.setup.webhook_url }}
                                            </p>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground">{{ connector.provider }}</TableCell>
                                        <TableCell class="text-muted-foreground">{{ connector.connection_mode }}</TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="status-dot"
                                                    :class="getConnectorRuntimeStateClass(connector.runtime_state)"
                                                    :title="connector.runtime_error_message"
                                                ></span>
                                                <span class="capitalize">{{ connector.runtime_state || connector.status }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground">{{ connector.sessions_count ?? 0 }}</TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    :disabled="isConnectorActionBusy(connector.id)"
                                                    @click="retestConnector(connector)"
                                                >
                                                    Retest
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    :disabled="isConnectorActionBusy(connector.id)"
                                                    @click="disconnectConnector(connector)"
                                                >
                                                    Disconnect
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="connectors.length === 0">
                                        <TableCell colspan="6" class="text-center text-muted-foreground">No connectors found.</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Metric Totals (cached)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div class="rounded-md border border-border bg-muted/50 px-3 py-2 text-sm">
                                    <p class="font-medium">Inbound messages</p>
                                    <p class="mt-1 text-foreground">{{ inboundRows.reduce((sum, row) => sum + Number(row[1] || 0), 0) }}</p>
                                </div>
                                <div class="rounded-md border border-border bg-muted/50 px-3 py-2 text-sm">
                                    <p class="font-medium">Webhook verification failures</p>
                                    <p class="mt-1 text-foreground">{{ webhookFailureRows.reduce((sum, row) => sum + Number(row[1] || 0), 0) }}</p>
                                </div>
                                <div class="rounded-md border border-border bg-muted/50 px-3 py-2 text-sm">
                                    <p class="font-medium">Action success</p>
                                    <p class="mt-1 text-foreground">{{ totalActionSuccess }}</p>
                                </div>
                                <div class="rounded-md border border-border bg-muted/50 px-3 py-2 text-sm">
                                    <p class="font-medium">Action failure</p>
                                    <p class="mt-1 text-foreground">{{ totalActionFailure }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Sessions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <button
                                    v-for="session in sessions"
                                    :key="session.id"
                                    type="button"
                                    class="w-full rounded-md border px-3 py-2 text-left text-sm transition"
                                    :class="selectedSessionId === session.id
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border bg-card text-foreground hover:bg-muted'"
                                    @click="selectSession(session.id)"
                                >
                                    <p class="font-medium">{{ session.provider }} · {{ session.channel_id }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">Status: {{ session.status }} · Messages: {{ session.messages_count ?? 0 }}</p>
                                </button>
                                <p v-if="sessions.length === 0" class="text-sm text-muted-foreground">No chat sessions found.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="lg:col-span-2">
                        <CardHeader>
                            <div class="flex items-center justify-between gap-3">
                                <CardTitle>Session Detail</CardTitle>
                                <CardDescription v-if="selectedSession">
                                    {{ selectedSession.provider }} · {{ selectedSession.channel_id }} · {{ selectedSession.thread_id || 'no-thread' }}
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="detailsLoading" class="h-32 w-full" />
                            <div v-if="detailsError" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ detailsError }}</div>

                            <div v-if="!detailsLoading && selectedSession" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <p class="mb-2 text-sm font-medium text-muted-foreground">Messages</p>
                                    <div class="max-h-64 space-y-2 overflow-auto rounded-md border border-border bg-muted/30 p-2">
                                        <div v-for="message in sessionMessages" :key="message.id" class="rounded-md border border-border bg-card px-2 py-1.5 text-sm">
                                            <p class="font-medium">{{ message.direction }} · {{ message.created_at }}</p>
                                            <MarkdownRenderer
                                                v-if="isLikelyMarkdown(message.content)"
                                                :markdown="String(message.content ?? '')"
                                                :normalize="false"
                                                class="messenger-message-markdown prose prose-sm mt-1 max-w-none break-words text-muted-foreground dark:prose-invert prose-headings:mb-2 prose-headings:mt-3 prose-p:my-1.5 prose-li:my-0.5 prose-code:rounded prose-code:bg-accent prose-code:px-1 prose-code:py-0.5"
                                            />
                                            <p v-else class="mt-1 whitespace-pre-wrap break-words text-muted-foreground">{{ message.content }}</p>
                                        </div>
                                        <p v-if="sessionMessages.length === 0" class="text-sm text-muted-foreground">No messages found for this session.</p>
                                    </div>
                                </div>
                                <div>
                                    <p class="mb-2 text-sm font-medium text-muted-foreground">Actions</p>
                                    <div class="max-h-64 space-y-2 overflow-auto rounded-md border border-border bg-muted/30 p-2">
                                        <div v-for="action in sessionActions" :key="action.id" class="rounded-md border border-border bg-card px-2 py-1.5 text-sm">
                                            <p class="font-medium">{{ action.action_type }}</p>
                                            <p class="mt-0.5 text-muted-foreground">Status: {{ action.status }} · {{ action.created_at }}</p>
                                            <p v-if="action.error" class="mt-1 text-destructive">{{ action.error }}</p>
                                        </div>
                                        <p v-if="sessionActions.length === 0" class="text-sm text-muted-foreground">No actions found for this session.</p>
                                    </div>
                                </div>
                            </div>

                            <p v-if="!detailsLoading && !selectedSession" class="text-sm text-muted-foreground">Select a session to inspect messages and actions.</p>
                        </CardContent>
                    </Card>
                </div>
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
