<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
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

const healthBadgeClass = computed(() => {
    const status = String(health.value?.status ?? '').toLowerCase();

    if (status === 'healthy') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800';
    }

    if (status === 'degraded') {
        return 'border-amber-300 bg-amber-50 text-amber-800';
    }

    return 'border-gray-300 bg-gray-50 text-gray-700';
});

const connectorCount = computed(() => connectors.value.length);
const connectedCount = computed(() => connectors.value.filter((item) => item.status === 'connected').length);
const queueBacklog = computed(() => Number(health.value?.queue?.backlog_size ?? 0));
const recentErrorRate = computed(() => Number(health.value?.recent_error_rate ?? 0));

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
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Messenger Control Plane</h2>
                <button
                    type="button"
                    class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700/50"
                    :disabled="loading || refreshing"
                    @click="refreshAll"
                >
                    {{ refreshing ? 'Refreshing…' : 'Refresh' }}
                </button>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <p v-if="loading" class="text-sm text-gray-500 dark:text-gray-400">Loading messenger control-plane data…</p>
                <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800/70 dark:bg-red-950/30 dark:text-red-200">{{ error }}</p>
                <p v-if="connectError" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800/70 dark:bg-red-950/30 dark:text-red-200">{{ connectError }}</p>
                <p v-if="connectSuccess" class="rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-800/70 dark:bg-emerald-950/30 dark:text-emerald-200">{{ connectSuccess }}</p>

                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Connect Messenger Service</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Credentials are encrypted at rest and never returned in API responses.</p>
                    </div>

                    <p v-if="schemaLoading" class="mt-3 text-xs text-gray-500 dark:text-gray-400">Loading provider requirements…</p>
                    <div v-if="!schemaLoading && providerDescriptions.length > 0" class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="provider in providerDescriptions"
                            :key="provider.key"
                            class="rounded border border-gray-300 bg-gray-50 px-2 py-1 text-[11px] text-gray-700 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-300"
                        >
                            {{ provider.label }}: {{ provider.description }}
                        </span>
                    </div>

                    <form class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="submitConnector">
                        <label class="text-xs text-gray-600 dark:text-gray-300">
                            Provider
                            <select
                                v-model="connectForm.provider"
                                class="mt-1 w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                :disabled="schemaLoading || connectSubmitting"
                            >
                                <option value="" disabled>Select provider</option>
                                <option v-for="provider in availableProviders" :key="provider.key" :value="provider.key">
                                    {{ provider.label }}
                                </option>
                            </select>
                        </label>

                        <label class="text-xs text-gray-600 dark:text-gray-300">
                            Connector Name
                            <input
                                v-model="connectForm.name"
                                type="text"
                                class="mt-1 w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                :disabled="connectSubmitting"
                                placeholder="My Workspace"
                            />
                        </label>

                        <label class="text-xs text-gray-600 dark:text-gray-300">
                            Connection Mode
                            <select
                                v-model="connectForm.connection_mode"
                                class="mt-1 w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                :disabled="connectSubmitting"
                            >
                                <option v-for="mode in selectedProviderModes" :key="mode" :value="mode">{{ mode }}</option>
                            </select>
                        </label>

                        <label
                            v-for="field in selectedProviderFields"
                            :key="field.key"
                            class="text-xs text-gray-600 dark:text-gray-300"
                        >
                            {{ field.label }}<span v-if="field.required" class="text-red-600 dark:text-red-400"> *</span>
                            <input
                                v-model="connectForm.credentials[field.key]"
                                :type="field.type === 'password' ? 'password' : 'text'"
                                class="mt-1 w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                :disabled="connectSubmitting"
                                :placeholder="field.placeholder || ''"
                            />
                            <p v-if="getFieldError(field.key)" class="mt-1 text-[11px] text-red-600 dark:text-red-300">
                                {{ Array.isArray(getFieldError(field.key)) ? getFieldError(field.key)[0] : getFieldError(field.key) }}
                            </p>
                        </label>

                        <div class="flex items-center gap-2">
                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                <input v-model="connectForm.confirmation_required" type="checkbox" class="rounded border-gray-300 dark:border-gray-600" :disabled="connectSubmitting" />
                                Require confirmation
                            </label>
                        </div>

                        <label class="text-xs text-gray-600 dark:text-gray-300">
                            Verbosity
                            <select
                                v-model="connectForm.default_verbosity"
                                class="mt-1 w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                :disabled="connectSubmitting"
                            >
                                <option value="summary">summary</option>
                                <option value="verbose">verbose</option>
                            </select>
                        </label>

                        <div class="md:col-span-2 xl:col-span-3">
                            <button
                                type="submit"
                                class="rounded border border-indigo-500 bg-indigo-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="connectSubmitting || schemaLoading || !connectForm.provider"
                            >
                                {{ connectSubmitting ? 'Saving…' : 'Save & Connect' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Health</p>
                        <p class="mt-2 inline-flex rounded border px-2 py-1 text-xs font-semibold uppercase" :class="healthBadgeClass">{{ health.status }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Connectors</p>
                        <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ connectedCount }} / {{ connectorCount }} connected</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Queue Backlog</p>
                        <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ queueBacklog }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Recent Error Rate</p>
                        <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ recentErrorRate.toFixed(2) }}%</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Connectors</h3>
                        <div class="mt-3 overflow-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                                        <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Provider</th>
                                        <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mode</th>
                                        <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                                        <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sessions</th>
                                        <th class="px-2 py-2 text-left font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <tr v-for="connector in connectors" :key="connector.id">
                                        <td class="px-2 py-2 text-gray-900 dark:text-gray-100">
                                            <p>{{ connector.name }}</p>
                                            <p v-if="connector.setup?.webhook_url" class="mt-0.5 break-all text-[11px] text-gray-500 dark:text-gray-400">
                                                {{ connector.setup.webhook_url }}
                                            </p>
                                        </td>
                                        <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ connector.provider }}</td>
                                        <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ connector.connection_mode }}</td>
                                        <td class="px-2 py-2">
                                            <span class="rounded border px-2 py-0.5 text-[11px] font-semibold uppercase" :class="connector.status === 'connected'
                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                                                : connector.status === 'error'
                                                    ? 'border-red-300 bg-red-50 text-red-800'
                                                    : 'border-gray-300 bg-gray-50 text-gray-700'">
                                                {{ connector.status }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-2 text-gray-600 dark:text-gray-300">{{ connector.sessions_count ?? 0 }}</td>
                                        <td class="px-2 py-2">
                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded border border-gray-300 px-2 py-0.5 text-[11px] text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700/50"
                                                    :disabled="isConnectorActionBusy(connector.id)"
                                                    @click="retestConnector(connector)"
                                                >
                                                    Retest
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded border border-red-300 px-2 py-0.5 text-[11px] text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/40"
                                                    :disabled="isConnectorActionBusy(connector.id)"
                                                    @click="disconnectConnector(connector)"
                                                >
                                                    Disconnect
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="connectors.length === 0">
                                        <td colspan="6" class="px-2 py-3 text-center text-gray-500 dark:text-gray-400">No connectors found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Metric Totals (cached)</h3>
                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-900/50">
                                <p class="font-semibold text-gray-700 dark:text-gray-200">Inbound messages</p>
                                <p class="mt-1 text-gray-900 dark:text-gray-100">{{ inboundRows.reduce((sum, row) => sum + Number(row[1] || 0), 0) }}</p>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-900/50">
                                <p class="font-semibold text-gray-700 dark:text-gray-200">Webhook verification failures</p>
                                <p class="mt-1 text-gray-900 dark:text-gray-100">{{ webhookFailureRows.reduce((sum, row) => sum + Number(row[1] || 0), 0) }}</p>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-900/50">
                                <p class="font-semibold text-gray-700 dark:text-gray-200">Action success</p>
                                <p class="mt-1 text-gray-900 dark:text-gray-100">{{ totalActionSuccess }}</p>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-900/50">
                                <p class="font-semibold text-gray-700 dark:text-gray-200">Action failure</p>
                                <p class="mt-1 text-gray-900 dark:text-gray-100">{{ totalActionFailure }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Recent Sessions</h3>
                        <div class="mt-3 space-y-2">
                            <button
                                v-for="session in sessions"
                                :key="session.id"
                                type="button"
                                class="w-full rounded border px-3 py-2 text-left text-xs transition"
                                :class="selectedSessionId === session.id
                                    ? 'border-indigo-400 bg-indigo-50 text-indigo-900 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200'
                                    : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900/30 dark:text-gray-200 dark:hover:bg-gray-900/60'"
                                @click="selectSession(session.id)"
                            >
                                <p class="font-semibold">{{ session.provider }} · {{ session.channel_id }}</p>
                                <p class="mt-0.5 text-[11px] opacity-80">Status: {{ session.status }} · Messages: {{ session.messages_count ?? 0 }}</p>
                            </button>
                            <p v-if="sessions.length === 0" class="text-xs text-gray-500 dark:text-gray-400">No chat sessions found.</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 lg:col-span-2 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Session Detail</h3>
                            <p v-if="selectedSession" class="text-xs text-gray-500 dark:text-gray-400">
                                {{ selectedSession.provider }} · {{ selectedSession.channel_id }} · {{ selectedSession.thread_id || 'no-thread' }}
                            </p>
                        </div>

                        <p v-if="detailsLoading" class="mt-3 text-xs text-gray-500 dark:text-gray-400">Loading session details…</p>
                        <p v-if="detailsError" class="mt-3 rounded border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-800/70 dark:bg-red-950/30 dark:text-red-200">{{ detailsError }}</p>

                        <div v-if="!detailsLoading && selectedSession" class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Messages</p>
                                <div class="mt-2 max-h-64 space-y-2 overflow-auto rounded border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900/40">
                                    <div v-for="message in sessionMessages" :key="message.id" class="rounded border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900">
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ message.direction }} · {{ message.created_at }}</p>
                                        <p class="mt-1 whitespace-pre-wrap break-words text-gray-700 dark:text-gray-300">{{ message.content }}</p>
                                    </div>
                                    <p v-if="sessionMessages.length === 0" class="text-xs text-gray-500 dark:text-gray-400">No messages found for this session.</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</p>
                                <div class="mt-2 max-h-64 space-y-2 overflow-auto rounded border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900/40">
                                    <div v-for="action in sessionActions" :key="action.id" class="rounded border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900">
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ action.action_type }}</p>
                                        <p class="mt-0.5 text-gray-600 dark:text-gray-300">Status: {{ action.status }} · {{ action.created_at }}</p>
                                        <p v-if="action.error" class="mt-1 text-red-700 dark:text-red-300">{{ action.error }}</p>
                                    </div>
                                    <p v-if="sessionActions.length === 0" class="text-xs text-gray-500 dark:text-gray-400">No actions found for this session.</p>
                                </div>
                            </div>
                        </div>

                        <p v-if="!detailsLoading && !selectedSession" class="mt-3 text-xs text-gray-500 dark:text-gray-400">Select a session to inspect messages and actions.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
