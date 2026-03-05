<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { LayoutDashboard, RefreshCw } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const loading = ref(true);
const errorMessage = ref('');
const windowKey = ref('24h');
const payload = ref(null);

const metrics = computed(() => payload.value?.metrics ?? {
    runs_today: 0,
    success_rate_percent: 0,
    average_duration_ms: 0,
    backlog_count: 0,
    oldest_queued_age_seconds: 0,
});
const scheduler = computed(() => payload.value?.scheduler ?? { status: 'unknown', age_seconds: null });

const formatDuration = (ms) => {
    const seconds = Math.max(0, Math.floor(ms / 1000));
    const minutes = Math.floor(seconds / 60);
    const remSeconds = seconds % 60;

    if (minutes > 0) {
        return `${minutes}m ${remSeconds}s`;
    }

    return `${remSeconds}s`;
};

const loadMetrics = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/dashboard/metrics', {
            params: { window: windowKey.value },
        });
        payload.value = data.data ?? null;
    } catch (error) {
        errorMessage.value = error?.response?.data?.error?.message ?? 'Unable to load dashboard metrics.';
    } finally {
        loading.value = false;
    }
};

const windowOptions = [
    { value: '1h', label: 'Last 1h' },
    { value: '6h', label: 'Last 6h' },
    { value: '24h', label: 'Last 24h' },
    { value: '7d', label: 'Last 7 days' },
];

const schedulerVariant = computed(() => {
    const status = scheduler.value.status;
    if (status === 'healthy') return 'default';
    if (status === 'degraded') return 'secondary';
    if (status === 'down') return 'destructive';
    return 'outline';
});

onMounted(loadMetrics);
</script>

<template>
    <AppLayout title="Dashboard">
        <Head title="Dashboard" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <LayoutDashboard class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold text-xl text-foreground leading-tight">Dashboard</h2>
                        <HelpHint
                            ui-key="dashboard.overview"
                            short-text="Understand dashboard metrics, scheduler health, and safe interpretation."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <select
                        v-model="windowKey"
                        class="h-9 rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        @change="loadMetrics"
                    >
                        <option v-for="opt in windowOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <Button variant="outline" size="sm" @click="loadMetrics">
                        <RefreshCw class="h-4 w-4 mr-1" />
                        Refresh
                    </Button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="px-4 sm:px-6 lg:px-8">
                <p v-if="errorMessage" class="mb-4 rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ errorMessage }}
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Runs Today</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-24" />
                            <p v-else class="text-2xl font-semibold text-foreground">{{ metrics.runs_today }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Success Rate ({{ windowKey }})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-24" />
                            <template v-else>
                                <p class="text-2xl font-semibold text-foreground">{{ metrics.success_rate_percent }}%</p>
                                <p class="mt-1 text-xs text-muted-foreground">Skipped runs excluded.</p>
                            </template>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Avg Duration ({{ windowKey }})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-24" />
                            <p v-else class="text-2xl font-semibold text-foreground">{{ formatDuration(metrics.average_duration_ms) }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Backlog Count</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-24" />
                            <p v-else class="text-2xl font-semibold text-foreground">{{ metrics.backlog_count }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Oldest Queued Age</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-24" />
                            <template v-else>
                                <p class="text-2xl font-semibold text-foreground">{{ formatDuration(metrics.oldest_queued_age_seconds * 1000) }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">Computed globally across queued runs.</p>
                            </template>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Scheduler Health</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton v-if="loading" class="h-8 w-24" />
                            <template v-else>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="schedulerVariant">{{ scheduler.status }}</Badge>
                                </div>
                                <p class="mt-2 text-xs text-muted-foreground">Age: {{ scheduler.age_seconds ?? 'n/a' }}s</p>
                            </template>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
