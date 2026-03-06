<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Globe, Check, X, Copy, ExternalLink, AlertTriangle, Loader2,
} from 'lucide-vue-next';
import { ref, reactive, onUnmounted, onMounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    appUrl: { type: String, default: 'http://localhost:8000' },
    tunnelSettings: { type: Object, default: () => ({}) },
    status: { type: String, default: 'unconfigured' },
});

const currentStep = ref(1);
const totalSteps = 5;

const stepData = reactive({
    // Step 1 — Install
    installChecking: false,
    installInstalling: false,
    installChecks: null,
    installCommand: '',
    platform: '',
    versionWarning: null,
    installError: null,
    // Step 2 — Auth
    authStarting: false,
    authUrl: null,
    authPolling: false,
    authComplete: false,
    authTimeout: false,
    alreadyAuthenticated: false,
    // Step 3 — Create
    tunnelName: '',
    hostname: '',
    creating: false,
    tunnelCreated: false,
    tunnelUuid: null,
    routingDns: false,
    dnsSuccess: false,
    dnsFailed: false,
    cnameTarget: '',
    manualCnameConfirmed: false,
    createError: null,
    useExistingLoading: false,
    // Step 4 — Configure
    protocol: 'http2',
    ipAllowlist: [],
    newCidr: '',
    configureSaving: false,
    // Step 5 — Review
    activating: false,
    activateError: null,
});

let authPollInterval = null;
let authTimeoutTimer = null;

const isCreateErrorAlreadyExists = computed(() =>
    stepData.createError && String(stepData.createError).toLowerCase().includes('already exists'),
);

onMounted(() => {
    const s = props.tunnelSettings;
    if (s?.tunnel_uuid && s?.tunnel_name && s?.hostname) {
        stepData.tunnelName = s.tunnel_name ?? '';
        stepData.hostname = s.hostname ?? '';
        stepData.tunnelCreated = true;
        stepData.tunnelUuid = s.tunnel_uuid ?? null;
        stepData.dnsSuccess = true;
        if (s.protocol) stepData.protocol = s.protocol;
        if (Array.isArray(s.ip_allowlist)) stepData.ipAllowlist = [...s.ip_allowlist];
        const hasProtocol = s.protocol != null;
        currentStep.value = hasProtocol ? 5 : 4;
    } else if (s?.tunnel_name || s?.hostname) {
        stepData.tunnelName = s.tunnel_name ?? '';
        stepData.hostname = s.hostname ?? '';
        currentStep.value = 3;
    }
});

onUnmounted(() => {
    clearAuthPolling();
});

const clearAuthPolling = () => {
    if (authPollInterval) {
        clearInterval(authPollInterval);
        authPollInterval = null;
    }
    if (authTimeoutTimer) {
        clearTimeout(authTimeoutTimer);
        authTimeoutTimer = null;
    }
};

const steps = [
    { number: 1, label: 'Install' },
    { number: 2, label: 'Authenticate' },
    { number: 3, label: 'Create' },
    { number: 4, label: 'Configure' },
    { number: 5, label: 'Activate' },
];

const stepState = (stepNumber) => {
    if (stepNumber < currentStep.value) return 'complete';
    if (stepNumber === currentStep.value) return 'active';
    return 'pending';
};

const canProceedStep1 = computed(() => stepData.installChecks?.binary === true);
const canProceedStep2 = computed(() => stepData.authComplete || stepData.alreadyAuthenticated);
const canProceedStep3 = computed(() => {
    if (!stepData.tunnelCreated) return false;
    return stepData.dnsSuccess || stepData.manualCnameConfirmed;
});

// Step 1 — Install check
const checkInstallation = async () => {
    stepData.installChecking = true;
    stepData.installError = null;
    try {
        const { data } = await axios.post(route('settings.tunnel.install-check'));
        stepData.installChecks = data;
        stepData.installCommand = data.install_command ?? '';
        stepData.platform = data.platform ?? '';
        stepData.versionWarning = data.version_warning ?? null;

        if (data.credentials) {
            stepData.alreadyAuthenticated = true;
        }
    } catch {
        stepData.installChecks = { binary: false, credentials: false, functional: false };
    } finally {
        stepData.installChecking = false;
    }
};

const runInstall = async () => {
    stepData.installInstalling = true;
    stepData.installError = null;
    try {
        const { data } = await axios.post(route('settings.tunnel.install'));
        if (data.success) {
            await checkInstallation();
        } else {
            stepData.installError = data.message ?? 'Installation failed.';
            if (data.output) {
                stepData.installError += `\n\n${data.output}`;
            }
            if (data.install_command) {
                stepData.installCommand = data.install_command;
            }
        }
    } catch (err) {
        stepData.installError = err.response?.data?.message ?? 'Installation failed. Run the command manually in your terminal.';
    } finally {
        stepData.installInstalling = false;
    }
};

// Step 2 — Auth
const startAuth = async () => {
    stepData.authStarting = true;
    stepData.authUrl = null;
    stepData.authTimeout = false;

    try {
        const { data } = await axios.post(route('settings.tunnel.auth-start'));
        stepData.authUrl = data.auth_url ?? null;
        stepData.authStarting = false;
        startAuthPolling();
    } catch {
        stepData.authStarting = false;
    }
};

const startAuthPolling = () => {
    stepData.authPolling = true;

    authPollInterval = setInterval(async () => {
        try {
            const { data } = await axios.post(route('settings.tunnel.auth-poll'));
            if (data.authenticated) {
                stepData.authComplete = true;
                stepData.authPolling = false;
                clearAuthPolling();
                currentStep.value = 3;
            }
        } catch {
            // continue polling
        }
    }, 2000);

    authTimeoutTimer = setTimeout(() => {
        if (!stepData.authComplete) {
            stepData.authTimeout = true;
            stepData.authPolling = false;
            clearInterval(authPollInterval);
            authPollInterval = null;
        }
    }, 5 * 60 * 1000);
};

const skipAuth = () => {
    stepData.authComplete = true;
    currentStep.value = 3;
};

// Step 3 — Create tunnel
const createTunnel = async () => {
    stepData.creating = true;
    stepData.dnsFailed = false;
    stepData.createError = null;

    try {
        const { data } = await axios.post(route('settings.tunnel.create'), {
            tunnel_name: stepData.tunnelName,
            hostname: stepData.hostname,
        });
        stepData.tunnelCreated = true;
        stepData.tunnelUuid = data.tunnel_uuid ?? null;

        // Auto-route DNS
        await routeDns();
    } catch (err) {
        stepData.createError = err.response?.data?.message ?? 'Failed to create tunnel. Please try again.';
    } finally {
        stepData.creating = false;
    }
};

const useExistingTunnel = async () => {
    stepData.useExistingLoading = true;
    stepData.createError = null;
    try {
        const { data } = await axios.post(route('settings.tunnel.use-existing'), {
            tunnel_name: stepData.tunnelName,
            hostname: stepData.hostname,
        });
        stepData.tunnelCreated = true;
        stepData.tunnelUuid = data.tunnel_uuid ?? null;
        await routeDns();
    } catch (err) {
        stepData.createError = err.response?.data?.message ?? 'Failed to use existing tunnel.';
    } finally {
        stepData.useExistingLoading = false;
    }
};

const routeDns = async () => {
    stepData.routingDns = true;

    try {
        const { data } = await axios.post(route('settings.tunnel.route-dns'));
        if (data.success) {
            stepData.dnsSuccess = true;
        } else {
            stepData.dnsFailed = true;
            stepData.cnameTarget = data.cname_target ?? '';
        }
    } catch {
        stepData.dnsFailed = true;
        stepData.cnameTarget = stepData.tunnelUuid
            ? `${stepData.tunnelUuid}.cfargotunnel.com`
            : '';
    } finally {
        stepData.routingDns = false;
        stepData.creating = false;
    }
};

// Step 4 — Save config
const addCidr = () => {
    const value = stepData.newCidr.trim();
    if (value && !stepData.ipAllowlist.includes(value)) {
        stepData.ipAllowlist.push(value);
    }
    stepData.newCidr = '';
};

const removeCidr = (index) => {
    stepData.ipAllowlist.splice(index, 1);
};

const saveConfiguration = async () => {
    stepData.configureSaving = true;
    try {
        await axios.post(route('settings.tunnel.update'), {
            protocol: stepData.protocol,
            ip_allowlist: stepData.ipAllowlist,
        });
        currentStep.value = 5;
    } catch {
        // stay on step 4
    } finally {
        stepData.configureSaving = false;
    }
};

// Step 5 — Activate
const activateTunnel = () => {
    stepData.activating = true;
    stepData.activateError = null;
    router.post(route('settings.tunnel.start'), {}, {
        onSuccess: () => {
            router.visit(route('settings.tunnel.index'));
        },
        onError: (errors) => {
            stepData.activating = false;
            stepData.activateError = Object.values(errors || {}).flat().join(' ') || 'Failed to activate tunnel.';
        },
    });
};

const copiedValue = ref(null);
const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    copiedValue.value = text;
    setTimeout(() => { copiedValue.value = null; }, 2000);
};
</script>

<template>
    <AppLayout title="Tunnel Setup">
        <Head title="Tunnel Setup Wizard" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Globe class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground truncate">Tunnel Setup</h2>
                    <HelpHint
                        ui-key="settings.tunnel.wizard"
                        short-text="Step-by-step wizard to set up a Cloudflare Tunnel."
                        learn-more-href="/docs/tunnel"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl space-y-6">

                <!-- Step indicator -->
                <nav class="flex items-center justify-center gap-1">
                    <template v-for="step in steps" :key="step.number">
                        <div class="flex items-center gap-1.5">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold transition-colors"
                                :class="{
                                    'bg-primary text-primary-foreground': stepState(step.number) === 'active',
                                    'bg-green-500 text-white': stepState(step.number) === 'complete',
                                    'bg-muted text-muted-foreground': stepState(step.number) === 'pending',
                                }"
                            >
                                <Check v-if="stepState(step.number) === 'complete'" class="h-4 w-4" />
                                <span v-else>{{ step.number }}</span>
                            </div>
                            <span
                                class="hidden text-xs font-medium sm:inline"
                                :class="{
                                    'text-foreground': stepState(step.number) === 'active',
                                    'text-green-600': stepState(step.number) === 'complete',
                                    'text-muted-foreground': stepState(step.number) === 'pending',
                                }"
                            >
                                {{ step.label }}
                            </span>
                        </div>
                        <div
                            v-if="step.number < totalSteps"
                            class="mx-1 h-px w-6 sm:w-10"
                            :class="step.number < currentStep ? 'bg-green-500' : 'bg-border'"
                        ></div>
                    </template>
                </nav>

                <!-- Step 1: Install cloudflared -->
                <Card v-if="currentStep === 1">
                    <CardHeader>
                        <CardTitle>Step 1: Install cloudflared</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            The cloudflared daemon must be installed on this server to create and manage tunnels.
                        </p>

                        <Button
                            variant="outline"
                            :disabled="stepData.installChecking"
                            @click="checkInstallation"
                        >
                            <Loader2 v-if="stepData.installChecking" class="h-4 w-4 mr-1 animate-spin" />
                            {{ stepData.installChecking ? 'Checking...' : 'Check Installation' }}
                        </Button>

                        <div v-if="stepData.installChecks" class="space-y-2">
                            <div class="flex items-center gap-2 text-sm">
                                <Check v-if="stepData.installChecks.binary" class="h-4 w-4 text-green-500" />
                                <X v-else class="h-4 w-4 text-red-500" />
                                <span>cloudflared binary installed</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <Check v-if="stepData.installChecks.credentials" class="h-4 w-4 text-green-500" />
                                <X v-else class="h-4 w-4 text-red-500" />
                                <span>cert.pem exists</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <Check v-if="stepData.installChecks.functional" class="h-4 w-4 text-green-500" />
                                <X v-else class="h-4 w-4 text-red-500" />
                                <span>Functional authentication</span>
                            </div>

                            <div v-if="stepData.installChecks.version" class="text-xs text-muted-foreground">
                                Installed version: {{ stepData.installChecks.version }}
                            </div>
                        </div>

                        <div v-if="stepData.versionWarning" class="rounded-md border border-yellow-500/50 bg-yellow-500/10 px-3 py-2 text-sm text-yellow-700">
                            {{ stepData.versionWarning }}
                        </div>

                        <div v-if="stepData.installCommand && !stepData.installChecks?.binary" class="space-y-3">
                            <p class="text-xs font-medium text-muted-foreground">
                                Install command ({{ stepData.platform }}):
                            </p>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 rounded-md border border-input bg-muted px-3 py-2 text-xs font-mono">
                                    {{ stepData.installCommand }}
                                </code>
                                <Button variant="outline" size="sm" @click="copyToClipboard(stepData.installCommand)">
                                    <Check v-if="copiedValue === stepData.installCommand" class="h-4 w-4 text-green-500" />
                                    <Copy v-else class="h-4 w-4" />
                                </Button>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="default"
                                    :disabled="stepData.installInstalling"
                                    @click="runInstall"
                                >
                                    <Loader2 v-if="stepData.installInstalling" class="h-4 w-4 mr-1 animate-spin" />
                                    {{ stepData.installInstalling ? 'Installing...' : 'Install cloudflared' }}
                                </Button>
                                <span class="text-xs text-muted-foreground">
                                    Runs the command on this server. May require TUNNEL_ALLOW_INSTALL=true.
                                </span>
                            </div>
                            <div v-if="stepData.installError" class="rounded-md border border-red-500/50 bg-red-500/10 px-3 py-2 text-sm text-red-700 whitespace-pre-wrap">
                                {{ stepData.installError }}
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button :disabled="!canProceedStep1" @click="currentStep = 2">Next</Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step 2: Authenticate -->
                <Card v-if="currentStep === 2">
                    <CardHeader>
                        <CardTitle>Step 2: Authenticate with Cloudflare</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            Authorize this server to manage tunnels for your Cloudflare account.
                        </p>

                        <div v-if="stepData.authComplete || stepData.alreadyAuthenticated" class="flex items-center gap-2 text-sm text-green-600">
                            <Check class="h-4 w-4" />
                            <span>Authentication complete</span>
                        </div>

                        <template v-else>
                            <Button
                                variant="outline"
                                :disabled="stepData.authStarting || stepData.authPolling"
                                @click="startAuth"
                            >
                                <Loader2 v-if="stepData.authStarting" class="h-4 w-4 mr-1 animate-spin" />
                                {{ stepData.authStarting ? 'Starting...' : 'Authenticate with Cloudflare' }}
                            </Button>

                            <div v-if="stepData.authUrl" class="space-y-2">
                                <p class="text-sm">
                                    Open this link to authenticate:
                                </p>
                                <a
                                    :href="stepData.authUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                                >
                                    {{ stepData.authUrl }}
                                    <ExternalLink class="h-3 w-3" />
                                </a>
                            </div>

                            <div v-if="stepData.authPolling" class="flex items-center gap-2 text-sm text-muted-foreground">
                                <Loader2 class="h-4 w-4 animate-spin" />
                                <span>Waiting for authentication...</span>
                            </div>

                            <div v-if="stepData.authTimeout" class="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm text-amber-700">
                                Authentication timed out after 5 minutes. Please try again.
                            </div>
                        </template>

                        <div v-if="stepData.alreadyAuthenticated && !stepData.authComplete" class="pt-2">
                            <Button variant="ghost" size="sm" @click="skipAuth">
                                Skip (already authenticated)
                            </Button>
                        </div>

                        <div class="flex justify-between">
                            <Button variant="outline" @click="currentStep = 1">Back</Button>
                            <Button :disabled="!canProceedStep2" @click="currentStep = 3">Next</Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step 3: Create Tunnel + Hostname -->
                <Card v-if="currentStep === 3">
                    <CardHeader>
                        <CardTitle>Step 3: Create Tunnel</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1">
                            <label for="tunnel_name" class="text-sm font-medium">Tunnel Name</label>
                            <input
                                id="tunnel_name"
                                v-model="stepData.tunnelName"
                                type="text"
                                placeholder="my-agent"
                                :disabled="stepData.tunnelCreated"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                            />
                        </div>

                        <div class="space-y-1">
                            <label for="wizard_hostname" class="text-sm font-medium">Hostname</label>
                            <input
                                id="wizard_hostname"
                                v-model="stepData.hostname"
                                type="text"
                                placeholder="agent.mydomain.com"
                                :disabled="stepData.tunnelCreated"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50"
                            />
                        </div>

                        <Button
                            v-if="!stepData.tunnelCreated"
                            :disabled="stepData.creating || !stepData.tunnelName || !stepData.hostname"
                            @click="createTunnel"
                        >
                            <Loader2 v-if="stepData.creating" class="h-4 w-4 mr-1 animate-spin" />
                            {{ stepData.creating ? 'Creating...' : 'Create Tunnel' }}
                        </Button>

                        <div v-if="stepData.createError" class="space-y-2 rounded-md border border-red-500/50 bg-red-500/10 px-3 py-2 text-sm text-red-700">
                            <p>{{ stepData.createError }}</p>
                            <Button
                                v-if="isCreateErrorAlreadyExists && stepData.tunnelName && stepData.hostname"
                                variant="outline"
                                size="sm"
                                :disabled="stepData.useExistingLoading"
                                @click="useExistingTunnel"
                            >
                                <Loader2 v-if="stepData.useExistingLoading" class="h-4 w-4 mr-1 animate-spin" />
                                Use existing tunnel
                            </Button>
                        </div>

                        <div v-if="stepData.tunnelCreated" class="flex items-center gap-2 text-sm text-green-600">
                            <Check class="h-4 w-4" />
                            <span>Tunnel created ({{ stepData.tunnelUuid }})</span>
                        </div>

                        <div v-if="stepData.routingDns" class="flex items-center gap-2 text-sm text-muted-foreground">
                            <Loader2 class="h-4 w-4 animate-spin" />
                            <span>Configuring DNS...</span>
                        </div>

                        <div v-if="stepData.dnsSuccess" class="flex items-center gap-2 text-sm text-green-600">
                            <Check class="h-4 w-4" />
                            <span>DNS configured automatically</span>
                        </div>

                        <div v-if="stepData.dnsFailed" class="space-y-3 rounded-md border border-amber-500/50 bg-amber-500/10 p-4">
                            <p class="text-sm font-medium text-amber-800">Automatic DNS configuration failed</p>
                            <p class="text-sm text-amber-700">
                                Add a CNAME record in your Cloudflare DNS:
                            </p>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-xs font-mono">
                                    {{ stepData.hostname }} CNAME {{ stepData.cnameTarget }}
                                </code>
                                <Button variant="outline" size="sm" @click="copyToClipboard(stepData.cnameTarget)">
                                    <Check v-if="copiedValue === stepData.cnameTarget" class="h-4 w-4 text-green-500" />
                                    <Copy v-else class="h-4 w-4" />
                                </Button>
                            </div>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input
                                    v-model="stepData.manualCnameConfirmed"
                                    type="checkbox"
                                    class="rounded border-input"
                                />
                                I've added the CNAME record
                            </label>
                        </div>

                        <div class="flex justify-between">
                            <Button variant="outline" @click="currentStep = 2">Back</Button>
                            <Button :disabled="!canProceedStep3" @click="currentStep = 4">Next</Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step 4: Configure -->
                <Card v-if="currentStep === 4">
                    <CardHeader>
                        <CardTitle>Step 4: Configure</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1">
                            <label for="wizard_protocol" class="text-sm font-medium">Protocol</label>
                            <select
                                id="wizard_protocol"
                                v-model="stepData.protocol"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="http2">HTTP/2</option>
                                <option value="quic">QUIC</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">IP Allowlist (optional)</label>
                            <div class="flex gap-2">
                                <input
                                    v-model="stepData.newCidr"
                                    type="text"
                                    placeholder="192.168.1.0/24"
                                    class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                    @keydown.enter.prevent="addCidr"
                                />
                                <Button variant="outline" size="sm" @click="addCidr">Add</Button>
                            </div>
                            <div v-if="stepData.ipAllowlist.length" class="flex flex-wrap gap-2">
                                <span
                                    v-for="(cidr, index) in stepData.ipAllowlist"
                                    :key="cidr"
                                    class="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-1 text-xs font-mono"
                                >
                                    {{ cidr }}
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="removeCidr(index)"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-between">
                            <Button variant="outline" @click="currentStep = 3">Back</Button>
                            <Button :disabled="stepData.configureSaving" @click="saveConfiguration">
                                <Loader2 v-if="stepData.configureSaving" class="h-4 w-4 mr-1 animate-spin" />
                                {{ stepData.configureSaving ? 'Saving...' : 'Next' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step 5: Review + Activate -->
                <Card v-if="currentStep === 5">
                    <CardHeader>
                        <CardTitle>Step 5: Review & Activate</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="rounded-md border border-border bg-muted/50 p-4 space-y-2">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <span class="text-muted-foreground">Tunnel Name</span>
                                <span class="font-medium">{{ stepData.tunnelName }}</span>

                                <span class="text-muted-foreground">Hostname</span>
                                <span class="font-medium">{{ stepData.hostname }}</span>

                                <span class="text-muted-foreground">Protocol</span>
                                <span class="font-medium">{{ stepData.protocol === 'http2' ? 'HTTP/2' : 'QUIC' }}</span>

                                <span class="text-muted-foreground">Origin URL</span>
                                <span class="font-medium font-mono text-xs">{{ appUrl }}</span>

                                <span class="text-muted-foreground">IP Allowlist</span>
                                <span class="font-medium">
                                    <template v-if="stepData.ipAllowlist.length">
                                        <Badge v-for="cidr in stepData.ipAllowlist" :key="cidr" variant="outline" class="mr-1 font-mono text-[10px]">{{ cidr }}</Badge>
                                    </template>
                                    <span v-else class="text-muted-foreground">None (all IPs allowed)</span>
                                </span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 rounded-md border border-amber-500/50 bg-amber-500/10 px-4 py-3">
                            <AlertTriangle class="h-5 w-5 shrink-0 text-amber-600 mt-0.5" />
                            <div>
                                <p class="text-sm font-medium text-amber-800">Security Warning</p>
                                <p class="text-sm text-amber-700">
                                    You are about to expose this application to the internet.
                                    Ensure authentication is properly configured before proceeding.
                                </p>
                            </div>
                        </div>

                        <div v-if="stepData.activateError" class="rounded-md border border-red-500/50 bg-red-500/10 px-3 py-2 text-sm text-red-700">
                            {{ stepData.activateError }}
                        </div>

                        <div class="flex justify-between">
                            <Button variant="outline" @click="currentStep = 4">Back</Button>
                            <Button :disabled="stepData.activating" @click="activateTunnel">
                                <Loader2 v-if="stepData.activating" class="h-4 w-4 mr-1 animate-spin" />
                                {{ stepData.activating ? 'Activating...' : 'Activate Tunnel' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AppLayout>
</template>
