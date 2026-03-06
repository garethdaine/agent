<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { Globe, Play, Square, Trash2, Plus, X, Copy, Check, AlertTriangle, Loader2 } from 'lucide-vue-next';
import { ref, computed, reactive } from 'vue';

const props = defineProps({
    tunnelSettings: { type: Object, required: true },
    status: { type: String, required: true },
    validationChecks: { type: Object, default: () => ({}) },
    versionInfo: { type: Object, default: () => ({}) },
    appUrl: { type: String, default: 'http://localhost:8000' },
    useSyncStart: { type: Boolean, default: false },
});

const saving = ref(false);
const starting = ref(false);
const stopping = ref(false);
const error = ref('');
const success = ref('');
const showDeleteConfirm = ref(false);
const generatingToken = ref(false);
const routingDns = ref(false);
const generatedSecret = ref(null);
const copiedField = ref(null);

const form = reactive({
    hostname: props.tunnelSettings.hostname ?? '',
    protocol: props.tunnelSettings.protocol ?? 'http2',
    ip_allowlist: [...(props.tunnelSettings.ip_allowlist ?? [])],
    cloudflare_access_enabled: props.tunnelSettings.cloudflare_access_enabled ?? false,
});

const newCidr = ref('');

const statusVariant = computed(() => {
    const map = { active: 'default', stopped: 'secondary', error: 'destructive', unconfigured: 'outline' };
    return map[props.status] ?? 'outline';
});

const statusColor = computed(() => {
    const map = { active: 'bg-green-500', stopped: 'bg-yellow-500', error: 'bg-red-500', unconfigured: 'bg-gray-400' };
    return map[props.status] ?? 'bg-gray-400';
});

const hasTunnelConfig = computed(() => !!(props.tunnelSettings?.tunnel_uuid && props.tunnelSettings?.hostname));
const isUnconfigured = computed(() => !hasTunnelConfig.value);
const isActive = computed(() => props.status === 'active');

const addCidr = () => {
    const value = newCidr.value.trim();
    if (value && !form.ip_allowlist.includes(value)) {
        form.ip_allowlist.push(value);
    }
    newCidr.value = '';
};

const removeCidr = (index) => {
    form.ip_allowlist.splice(index, 1);
};

const saveSettings = () => {
    saving.value = true;
    error.value = '';
    success.value = '';

    router.post(route('settings.tunnel.update'), {
        hostname: form.hostname,
        protocol: form.protocol,
        ip_allowlist: form.ip_allowlist,
        cloudflare_access_enabled: form.cloudflare_access_enabled,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            success.value = 'Tunnel settings updated.';
            saving.value = false;
        },
        onError: (errors) => {
            error.value = Object.values(errors).flat().join(' ');
            saving.value = false;
        },
    });
};

const startTunnel = () => {
    starting.value = true;
    error.value = '';
    success.value = '';
    router.post(route('settings.tunnel.start'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            starting.value = false;
            success.value = 'Tunnel started.';
        },
        onError: (errors) => {
            starting.value = false;
            error.value = Object.values(errors).flat().join(' ');
        },
        onFinish: () => {
            starting.value = false;
        },
    });
};

const stopTunnel = () => {
    stopping.value = true;
    error.value = '';
    success.value = '';
    router.post(route('settings.tunnel.stop'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            stopping.value = false;
            success.value = 'Tunnel stopped.';
        },
        onError: (errors) => {
            stopping.value = false;
            error.value = Object.values(errors).flat().join(' ');
        },
        onFinish: () => {
            stopping.value = false;
        },
    });
};

const deleteTunnel = () => {
    router.post(route('settings.tunnel.delete'), {}, {
        preserveScroll: true,
        onSuccess: () => { showDeleteConfirm.value = false; },
        onError: (errors) => {
            error.value = Object.values(errors).flat().join(' ');
            showDeleteConfirm.value = false;
        },
    });
};

const routeDns = async () => {
    routingDns.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(route('settings.tunnel.route-dns'));
        if (data.success) {
            success.value = 'DNS routed successfully.';
        } else {
            error.value = data.error ?? data.manual_instructions ?? 'Failed to route DNS.';
        }
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to route DNS.';
    } finally {
        routingDns.value = false;
    }
};

const generateAccessToken = () => {
    generatingToken.value = true;

    router.post(route('settings.tunnel.access-token'), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            generatingToken.value = false;
            if (page.props?.flash?.access_secret) {
                generatedSecret.value = page.props.flash.access_secret;
            }
        },
        onError: (errors) => {
            error.value = Object.values(errors).flat().join(' ');
            generatingToken.value = false;
        },
    });
};

const copyToClipboard = (text, field) => {
    navigator.clipboard.writeText(text);
    copiedField.value = field;
    setTimeout(() => { copiedField.value = null; }, 2000);
};
</script>

<template>
    <AppLayout title="Tunnel">
        <Head title="Tunnel Settings" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Globe class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Tunnel</h2>
                        <HelpHint
                            ui-key="settings.tunnel"
                            short-text="Manage your Cloudflare Tunnel for secure remote access."
                            learn-more-href="/docs/tunnel"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2.5 w-2.5">
                            <span v-if="isActive" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="statusColor"></span>
                        </span>
                        <Badge :variant="statusVariant">{{ status }}</Badge>
                    </div>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <!-- Error/success messages -->
                <div v-if="tunnelSettings?.last_error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    <p class="font-medium">Tunnel failed to start</p>
                    <p class="mt-1">{{ tunnelSettings.last_error }}</p>
                </div>
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>
                <div v-if="success" class="rounded-md border border-green-500/50 bg-green-500/10 px-3 py-2 text-sm text-green-600">
                    {{ success }}
                </div>

                <!-- Security warning -->
                <div v-if="status !== 'unconfigured'" class="flex items-start gap-3 rounded-md border border-amber-500/50 bg-amber-500/10 px-4 py-3">
                    <AlertTriangle class="h-5 w-5 shrink-0 text-amber-600 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-amber-800">Security Notice</p>
                        <p class="text-sm text-amber-700">
                            Exposing this application to the internet requires authentication to be properly configured.
                            Ensure IP allowlist and/or Cloudflare Access are enabled before activating.
                        </p>
                    </div>
                </div>

                <!-- Version warning -->
                <div v-if="versionInfo.warning" class="rounded-md border border-yellow-500/50 bg-yellow-500/10 px-3 py-2 text-sm text-yellow-700">
                    {{ versionInfo.warning }}
                </div>

                <!-- Setup wizard CTA -->
                <Card v-if="isUnconfigured">
                    <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                        <Globe class="h-12 w-12 text-muted-foreground/50 mb-4" />
                        <h3 class="text-lg font-semibold text-foreground">No Tunnel Configured</h3>
                        <p class="mt-1 text-sm text-muted-foreground max-w-md">
                            Set up a Cloudflare Tunnel to securely expose this application to the internet with a custom hostname.
                        </p>
                        <Link
                            :href="route('settings.tunnel.wizard')"
                            class="mt-6 inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Set up Tunnel
                        </Link>
                    </CardContent>
                </Card>

                <!-- Configuration form (when configured) -->
                <template v-if="!isUnconfigured">
                    <!-- Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm">Tunnel Actions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap gap-3">
                                <Button
                                    v-if="!isActive"
                                    variant="default"
                                    size="sm"
                                    :disabled="starting"
                                    @click="startTunnel"
                                >
                                    <Loader2 v-if="starting" class="h-4 w-4 mr-1 animate-spin" />
                                    <Play v-else class="h-4 w-4 mr-1" />
                                    {{ starting ? 'Starting...' : 'Start Tunnel' }}
                                </Button>
                                <Button
                                    v-if="isActive"
                                    variant="secondary"
                                    size="sm"
                                    :disabled="stopping"
                                    @click="stopTunnel"
                                >
                                    <Loader2 v-if="stopping" class="h-4 w-4 mr-1 animate-spin" />
                                    <Square v-else class="h-4 w-4 mr-1" />
                                    {{ stopping ? 'Stopping...' : 'Stop Tunnel' }}
                                </Button>
                                <Button variant="destructive" size="sm" @click="showDeleteConfirm = true">
                                    <Trash2 class="h-4 w-4 mr-1" />
                                    Delete Tunnel
                                </Button>
                            </div>
                            <p v-if="!useSyncStart" class="mt-3 text-xs text-muted-foreground">
                                In-app Start requires Horizon. If the tunnel doesn't connect, run
                                <code class="rounded bg-muted px-1 py-0.5 font-mono text-[11px]">php artisan tunnel:run --sync</code>
                                in a terminal, or set <code class="rounded bg-muted px-1 py-0.5 font-mono text-[11px]">TUNNEL_USE_SYNC_START=true</code> in .env.
                            </p>
                            <div v-if="form.hostname" class="mt-3 rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs text-amber-800">
                                <p class="font-medium">No access at https://{{ form.hostname }}?</p>
                                <p class="mt-1 text-amber-700">
                                    Ensure DNS is routed: add a CNAME for <code class="font-mono">{{ form.hostname }}</code> → <code class="font-mono">{{ tunnelSettings.tunnel_uuid }}.cfargotunnel.com</code> in Cloudflare, or click Route DNS below.
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="mt-2"
                                    :disabled="routingDns"
                                    @click="routeDns"
                                >
                                    <Loader2 v-if="routingDns" class="h-3 w-3 mr-1 animate-spin" />
                                    Route DNS
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Configuration -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm">Configuration</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-1">
                                <label for="hostname" class="text-sm font-medium">Hostname</label>
                                <input
                                    id="hostname"
                                    v-model="form.hostname"
                                    type="text"
                                    placeholder="agent.mydomain.com"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium">Origin URL</label>
                                <p class="text-sm text-muted-foreground font-mono">{{ appUrl }}</p>
                            </div>

                            <div class="space-y-1">
                                <label for="protocol" class="text-sm font-medium">Protocol</label>
                                <select
                                    id="protocol"
                                    v-model="form.protocol"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option value="http2">HTTP/2</option>
                                    <option value="quic">QUIC</option>
                                </select>
                            </div>

                            <div class="flex justify-end">
                                <Button :disabled="saving" @click="saveSettings">
                                    {{ saving ? 'Saving...' : 'Save Settings' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- IP Allowlist -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm">IP Allowlist</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <p class="text-xs text-muted-foreground">
                                Restrict tunnel access to specific IP ranges. Leave empty to allow all IPs.
                            </p>
                            <div class="flex gap-2">
                                <input
                                    v-model="newCidr"
                                    type="text"
                                    placeholder="192.168.1.0/24"
                                    class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                    @keydown.enter.prevent="addCidr"
                                />
                                <Button variant="outline" size="sm" @click="addCidr">
                                    <Plus class="h-4 w-4" />
                                </Button>
                            </div>
                            <div v-if="form.ip_allowlist.length" class="flex flex-wrap gap-2">
                                <span
                                    v-for="(cidr, index) in form.ip_allowlist"
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
                            <p v-else class="text-xs text-muted-foreground">No IP restrictions configured.</p>
                        </CardContent>
                    </Card>

                    <!-- Cloudflare Access -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm">Cloudflare Access</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <Toggle
                                    :model-value="form.cloudflare_access_enabled"
                                    @update:model-value="form.cloudflare_access_enabled = $event"
                                />
                                <span class="text-sm">Enable Cloudflare Access service token authentication</span>
                            </div>

                            <template v-if="form.cloudflare_access_enabled">
                                <div v-if="tunnelSettings.access_client_id" class="space-y-2">
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Client ID</label>
                                        <div class="flex items-center gap-2">
                                            <code class="flex-1 rounded-md border border-input bg-muted px-3 py-2 text-xs font-mono">
                                                {{ tunnelSettings.access_client_id }}
                                            </code>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="copyToClipboard(tunnelSettings.access_client_id, 'client_id')"
                                            >
                                                <Check v-if="copiedField === 'client_id'" class="h-4 w-4 text-green-500" />
                                                <Copy v-else class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div v-if="generatedSecret" class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Client Secret (shown once)</label>
                                        <div class="flex items-center gap-2">
                                            <code class="flex-1 rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-xs font-mono">
                                                {{ generatedSecret }}
                                            </code>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="copyToClipboard(generatedSecret, 'secret')"
                                            >
                                                <Check v-if="copiedField === 'secret'" class="h-4 w-4 text-green-500" />
                                                <Copy v-else class="h-4 w-4" />
                                            </Button>
                                        </div>
                                        <p class="text-xs text-amber-600">Save this secret now. It will not be shown again.</p>
                                    </div>
                                </div>
                                <div v-else>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="generatingToken"
                                        @click="generateAccessToken"
                                    >
                                        {{ generatingToken ? 'Generating...' : 'Generate Service Token' }}
                                    </Button>
                                </div>
                            </template>
                        </CardContent>
                    </Card>
                </template>

                <!-- Delete confirmation dialog -->
                <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div class="w-full max-w-md rounded-lg border border-border bg-card p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-foreground">Delete Tunnel</h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            This will permanently delete the tunnel and all its configuration. This action cannot be undone.
                        </p>
                        <div class="mt-6 flex justify-end gap-3">
                            <Button variant="outline" @click="showDeleteConfirm = false">Cancel</Button>
                            <Button variant="destructive" @click="deleteTunnel">Delete Tunnel</Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
