<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import { Head, Link } from '@inertiajs/vue3';
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

const props = defineProps({
    jobMetrics: {
        type: Object,
        required: true,
    },
    system: {
        type: Object,
        required: true,
    },
    runtimePolicy: {
        type: Object,
        default: () => ({}),
    },
    navigation: {
        type: Object,
        required: true,
    },
});

// Windowed performance metrics (loaded via API)
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
const apiScheduler = computed(() => payload.value?.scheduler ?? { status: 'unknown', age_seconds: null });

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
        errorMessage.value = error?.response?.data?.error?.message ?? 'Unable to load performance metrics.';
    } finally {
        loading.value = false;
    }
};

const windowOptions = [
    { value: '24h', label: 'Last 24h' },
    { value: '7d', label: 'Last 7 days' },
];

const schedulerVariant = computed(() => {
    const status = props.system.scheduler.status;
    if (status === 'healthy') return 'default';
    if (status === 'degraded') return 'secondary';
    if (status === 'down') return 'destructive';
    return 'outline';
});

const activeBuildAgeLabel = computed(() => {
    if (props.system.freshness.active_build_age_seconds == null) {
        return 'n/a';
    }

    return `${props.system.freshness.active_build_age_seconds}s`;
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
                        <h2 class="text-base font-semibold text-foreground truncate">Dashboard</h2>
                        <HelpHint
                            ui-key="dashboard.overview"
                            short-text="Overview of jobs, performance metrics, and system health."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.deployments">Deployments</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.escalations">Escalations</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.replayBuilds">Replay Builds</Link>
                    <Link class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" :href="navigation.budgets">Budgets</Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="space-y-8 px-4 sm:px-6 lg:px-8">

                <!-- Jobs section -->
                <section>
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Jobs</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Total Jobs</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-2xl font-semibold text-foreground">{{ jobMetrics.total_jobs }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">Scheduled automations</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Runs Today</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-2xl font-semibold text-foreground">{{ jobMetrics.runs_today }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">Executions so far</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Successful Today</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-2xl font-semibold text-foreground">{{ jobMetrics.successful_runs_today }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">Completed without failure</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Success Rate (Today)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-2xl font-semibold text-foreground">{{ jobMetrics.success_rate_percent }}%</p>
                                <p class="mt-1 text-xs text-muted-foreground">Of today's runs</p>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <!-- Performance section (windowed) -->
                <section>
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Performance</h3>
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

                    <p v-if="errorMessage" class="mb-4 rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {{ errorMessage }}
                    </p>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
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
                    </div>
                </section>

                <!-- System section -->
                <section>
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">System</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border border-border bg-card p-4">
                            <h4 class="text-sm font-semibold">Scheduler</h4>
                            <div class="mt-2 flex items-center gap-2">
                                <Badge :variant="schedulerVariant">{{ system.scheduler.status }}</Badge>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">Age: <span class="text-foreground">{{ system.scheduler.age_seconds ?? 'n/a' }}s</span></p>
                            <p class="text-sm text-muted-foreground">Last seen: <span class="text-foreground">{{ system.scheduler.last_seen_at ?? 'n/a' }}</span></p>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4">
                            <h4 class="text-sm font-semibold">Projection Build</h4>
                            <p class="mt-2 text-sm text-muted-foreground">Active build: <span class="font-mono text-foreground">{{ system.activeProjectionBuildId ?? 'none' }}</span></p>
                            <p class="text-sm text-muted-foreground">Rebuilding build: <span class="font-mono text-foreground">{{ system.rebuildingBuildId ?? 'none' }}</span></p>
                            <p class="text-sm text-muted-foreground">active_build_age_seconds:
                                <span :class="system.freshness.active_build_is_stale ? 'text-amber-600' : 'text-foreground'">{{ activeBuildAgeLabel }}</span>
                            </p>
                            <p class="text-xs text-muted-foreground">reason_state: {{ system.reasonState }}</p>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4">
                            <h4 class="text-sm font-semibold">Runtime Policy</h4>
                            <div class="mt-2 flex items-center gap-2">
                                <Badge variant="secondary">{{ runtimePolicy.default_mode ?? 'safe' }}</Badge>
                                <span class="text-xs text-muted-foreground">default mode</span>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Deny list: <span class="font-mono text-foreground">{{ runtimePolicy.tool_deny?.length ?? 0 }}</span> tools
                            </p>
                            <p v-if="runtimePolicy.tool_allowlist_active" class="text-sm text-muted-foreground">
                                Allow list: <span class="font-mono text-foreground">{{ runtimePolicy.tool_allow?.length ?? 0 }}</span> tools (active)
                            </p>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4">
                            <h4 class="text-sm font-semibold">Delayed telemetry (reason codes)</h4>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="signal in system.signals.delayed" :key="`delayed-${signal.id}`" class="rounded border border-border px-2 py-1">
                                    <span class="font-mono text-xs">{{ signal.workflow_key }}</span>
                                    <span class="ml-2">{{ signal.reason_code }}</span>
                                </li>
                                <li v-if="system.signals.delayed.length === 0" class="text-muted-foreground">No delayed incidents in active build scope.</li>
                            </ul>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4">
                            <h4 class="text-sm font-semibold">Unobservable telemetry (reason codes)</h4>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="signal in system.signals.unobservable" :key="`unobservable-${signal.id}`" class="rounded border border-border px-2 py-1">
                                    <span class="font-mono text-xs">{{ signal.workflow_key }}</span>
                                    <span class="ml-2">{{ signal.reason_code }}</span>
                                </li>
                                <li v-if="system.signals.unobservable.length === 0" class="text-muted-foreground">No unobservable incidents in active build scope.</li>
                            </ul>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
