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
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Power,
    RefreshCw,
    CheckCircle,
    XCircle,
    Loader2,
    Zap,
    Radio,
    Clock,
    MessageSquare,
} from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted, onUnmounted } from 'vue';
import { confirmDialog } from '@/Support/confirmDialog';

const loading = ref(false);
const services = ref([]);
const error = ref('');
const restartingService = ref(null);
const restartResult = ref(null);
let refreshTimer = null;

const serviceIcons = {
    'horizon': Zap,
    'reverb': Radio,
    'scheduler': Clock,
    'messenger-gateway': MessageSquare,
};

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.get('/agent/api/v1/services');
        services.value = response.data.data;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load service status.';
    } finally {
        loading.value = false;
    }
};

const restartService = async (service) => {
    const confirmed = await confirmDialog({
        title: `Restart ${service.label}?`,
        message: `This will briefly stop and restart the ${service.label} service. Any tasks currently being processed will be interrupted and retried automatically.\n\nAre you sure you want to continue?`,
        confirmText: 'Restart',
        confirmVariant: 'default',
    });

    if (!confirmed) return;

    restartingService.value = service.key;
    restartResult.value = null;

    try {
        const response = await axios.post('/agent/api/v1/services/restart', {
            service: service.key,
        });
        restartResult.value = response.data.data;
    } catch (e) {
        if (e?.response?.status === 403) {
            restartResult.value = {
                success: false,
                message: 'You do not have permission to restart services. Please contact an administrator.',
            };
        } else {
            restartResult.value = {
                success: false,
                message: e?.response?.data?.data?.message ?? e?.response?.data?.message ?? 'An unexpected error occurred.',
            };
        }
    } finally {
        restartingService.value = null;
        // Refresh status after restart attempt
        await load();

        // Auto-clear result after 8 seconds
        setTimeout(() => {
            restartResult.value = null;
        }, 8000);
    }
};

const startAutoRefresh = () => {
    refreshTimer = setInterval(load, 15000);
};

onMounted(() => {
    load();
    startAutoRefresh();
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});
</script>

<template>
    <AppLayout title="Services">
        <Head title="Services" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Power class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            Services
                        </h2>
                        <HelpHint
                            ui-key="services"
                            short-text="View and restart background services that keep the application running."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" :disabled="loading" @click="load">
                        <RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />
                        Refresh
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <!-- Error banner -->
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <!-- Restart result toast -->
                <div
                    v-if="restartResult"
                    class="rounded-md border px-3 py-2 text-sm transition-all"
                    :class="restartResult.success
                        ? 'border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-400'
                        : 'border-destructive/50 bg-destructive/10 text-destructive'"
                >
                    <div class="flex items-center gap-2">
                        <CheckCircle v-if="restartResult.success" class="h-4 w-4 shrink-0" />
                        <XCircle v-else class="h-4 w-4 shrink-0" />
                        {{ restartResult.message }}
                    </div>
                </div>

                <!-- Loading skeleton -->
                <div v-if="loading && services.length === 0" class="text-center py-12 text-muted-foreground">
                    Loading services...
                </div>

                <!-- Service cards -->
                <div v-if="services.length > 0" class="grid gap-4 sm:grid-cols-2">
                    <Card v-for="service in services" :key="service.key">
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                                        :class="service.status === 'running' ? 'bg-green-500/10' : 'bg-destructive/10'"
                                    >
                                        <component
                                            :is="serviceIcons[service.key] || Power"
                                            class="h-5 w-5"
                                            :class="service.status === 'running' ? 'text-green-600 dark:text-green-400' : 'text-destructive'"
                                        />
                                    </div>
                                    <div>
                                        <CardTitle class="text-sm">{{ service.label }}</CardTitle>
                                        <Badge
                                            :variant="service.status === 'running' ? 'default' : 'destructive'"
                                            class="mt-1"
                                        >
                                            {{ service.status === 'running' ? 'Running' : 'Stopped' }}
                                        </Badge>
                                    </div>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="restartingService === service.key"
                                    @click="restartService(service)"
                                >
                                    <Loader2 v-if="restartingService === service.key" class="h-4 w-4 animate-spin" />
                                    <RefreshCw v-else class="h-4 w-4" />
                                    {{ restartingService === service.key ? 'Restarting...' : 'Restart' }}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-xs text-muted-foreground">
                                {{ service.description }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Help text -->
                <Card v-if="services.length > 0">
                    <CardContent class="pt-6">
                        <p class="text-xs text-muted-foreground">
                            If a service shows as <span class="font-medium text-destructive">Stopped</span>,
                            click <span class="font-medium">Restart</span> to start it again. Running tasks
                            will be automatically retried after a restart. Service status refreshes automatically
                            every 15 seconds.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
