<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import { Link, router } from '@inertiajs/vue3';
import { Globe, Play, Square, ExternalLink, Loader2 } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const health = ref(null);
const loading = ref(true);
const error = ref(null);
const hidden = ref(false);
const toggling = ref(false);

let pollInterval = null;

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const fetchStatus = async () => {
    try {
        const { data } = await axios.get(route('api.tunnel.status'));
        health.value = data;
        error.value = null;
        loading.value = false;
    } catch (err) {
        loading.value = false;
        if (err?.response?.status === 404) {
            stopPolling();
            hidden.value = true;
            return;
        }
        error.value = 'Status unavailable';
    }
};

onMounted(() => {
    fetchStatus();
    pollInterval = setInterval(fetchStatus, 5000);
});

onUnmounted(() => {
    stopPolling();
});

const status = computed(() => health.value?.status ?? 'unknown');
const isUnconfigured = computed(() => status.value === 'unconfigured');
const isActive = computed(() => status.value === 'active');

const statusVariant = computed(() => {
    const map = { active: 'default', stopped: 'secondary', error: 'destructive', unconfigured: 'outline' };
    return map[status.value] ?? 'outline';
});

const statusColor = computed(() => {
    const map = { active: 'bg-green-500', stopped: 'bg-yellow-500', error: 'bg-red-500', unconfigured: 'bg-gray-400' };
    return map[status.value] ?? 'bg-gray-400';
});

const formatUptime = (seconds) => {
    if (!seconds || seconds <= 0) return '0m';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
};

const toggleTunnel = () => {
    toggling.value = true;
    const routeName = isActive.value ? 'settings.tunnel.stop' : 'settings.tunnel.start';

    router.post(route(routeName), {}, {
        preserveScroll: true,
        onFinish: () => {
            toggling.value = false;
            fetchStatus();
        },
    });
};
</script>

<template>
    <Card v-if="!hidden">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-xs uppercase tracking-wide text-muted-foreground font-medium">
                <Globe class="h-4 w-4" />
                Tunnel
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 class="h-4 w-4 animate-spin" />
                Loading...
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="space-y-2">
                <p class="text-sm text-destructive">{{ error }}</p>
                <p class="text-xs text-muted-foreground">Retrying automatically...</p>
            </div>

            <!-- Unconfigured state -->
            <div v-else-if="isUnconfigured" class="space-y-3">
                <p class="text-sm text-muted-foreground">No tunnel configured.</p>
                <Link
                    :href="route('settings.tunnel.wizard')"
                    class="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                >
                    Set up Tunnel
                </Link>
            </div>

            <!-- Configured state -->
            <div v-else class="space-y-3">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span v-if="isActive" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="statusColor"></span>
                    </span>
                    <Badge :variant="statusVariant">{{ status }}</Badge>
                </div>

                <div v-if="health?.hostname" class="text-sm">
                    <a
                        v-if="isActive"
                        :href="`https://${health.hostname}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-primary hover:underline"
                    >
                        {{ health.hostname }}
                        <ExternalLink class="h-3 w-3" />
                    </a>
                    <span v-else class="text-muted-foreground">{{ health.hostname }}</span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                    <div v-if="health?.uptime_seconds != null">
                        <span>Uptime:</span>
                        <span class="ml-1 text-foreground">{{ formatUptime(health.uptime_seconds) }}</span>
                    </div>
                    <div v-if="health?.connections != null">
                        <span>Connections:</span>
                        <span class="ml-1 text-foreground">{{ health.connections }}</span>
                    </div>
                </div>

                <div v-if="health?.version_warning" class="mt-1">
                    <Badge variant="secondary" class="text-[10px]">{{ health.version_warning }}</Badge>
                </div>

                <div class="pt-1">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="toggling"
                        @click="toggleTunnel"
                    >
                        <Loader2 v-if="toggling" class="h-3 w-3 mr-1 animate-spin" />
                        <Play v-else-if="!isActive" class="h-3 w-3 mr-1" />
                        <Square v-else class="h-3 w-3 mr-1" />
                        {{ isActive ? 'Stop' : 'Start' }}
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
