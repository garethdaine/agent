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
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Stethoscope,
    CheckCircle,
    AlertCircle,
    XCircle,
    RefreshCw,
    Activity,
    Server,
    Cpu,
    Layers,
    Clock,
} from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted, computed, onUnmounted } from 'vue';

const loading = ref(false);
const error = ref('');
const data = ref(null);
const autoRefresh = ref(false);
let refreshTimer = null;

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.get('/agent/api/v1/debug');
        data.value = response.data.data;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load debug data.';
    } finally {
        loading.value = false;
    }
};

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value;
    if (autoRefresh.value) {
        refreshTimer = setInterval(load, 10000);
    } else {
        clearInterval(refreshTimer);
        refreshTimer = null;
    }
};

const healthOk = computed(() => data.value?.health?.summary?.error === 0);

const statusVariant = (status) => {
    return { ok: 'default', warn: 'secondary', error: 'destructive' }[status] ?? 'outline';
};

const outcomeVariant = (outcome) => {
    return { success: 'default', failure: 'destructive', denied: 'secondary' }[outcome] ?? 'outline';
};

const relativeTime = (ts) => {
    const diff = Date.now() - new Date(ts).getTime();
    if (diff < 60000) return `${Math.round(diff / 1000)}s ago`;
    if (diff < 3600000) return `${Math.round(diff / 60000)}m ago`;
    return `${Math.round(diff / 3600000)}h ago`;
};

onMounted(load);

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});
</script>

<template>
    <AppLayout title="Debug Panel">
        <Head title="Debug Panel" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Stethoscope class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            Debug Panel
                        </h2>
                        <HelpHint
                            ui-key="diagnostics"
                            short-text="System health, active sessions, queue depth, recent events, and environment info."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :class="autoRefresh ? 'border-primary text-primary' : ''"
                        @click="toggleAutoRefresh"
                    >
                        <RefreshCw class="h-4 w-4" :class="autoRefresh ? 'animate-spin' : ''" />
                        {{ autoRefresh ? 'Auto' : 'Auto-refresh' }}
                    </Button>
                    <Button variant="outline" size="sm" :disabled="loading" @click="load">
                        <RefreshCw class="h-4 w-4" />
                        Refresh
                    </Button>
                    <Link :href="route('tools.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <div v-if="loading && !data" class="text-center py-12 text-muted-foreground">
                    Loading debug data...
                </div>

                <template v-if="data">
                    <!-- Status tiles -->
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <Card>
                            <CardContent class="flex items-center gap-3 pt-6">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="healthOk ? 'bg-green-500/10' : 'bg-destructive/10'">
                                    <Activity class="h-5 w-5" :class="healthOk ? 'text-green-600' : 'text-destructive'" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">{{ healthOk ? 'Healthy' : 'Issues' }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ data.health.summary.ok }}/{{ data.health.summary.total }} checks OK
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="flex items-center gap-3 pt-6">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                                    <Cpu class="h-5 w-5 text-blue-600" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">{{ data.sessions.active }}</p>
                                    <p class="text-xs text-muted-foreground">Active sessions ({{ data.sessions.total }} total)</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="flex items-center gap-3 pt-6">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-500/10">
                                    <Layers class="h-5 w-5 text-purple-600" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">{{ data.jobs.runs_24h }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        Runs (24h)
                                        <span v-if="data.jobs.failed_24h > 0" class="text-destructive">· {{ data.jobs.failed_24h }} failed</span>
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent class="flex items-center gap-3 pt-6">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/10">
                                    <Server class="h-5 w-5 text-amber-600" />
                                </div>
                                <div>
                                    <p class="text-2xl font-bold">{{ data.connectors }}</p>
                                    <p class="text-xs text-muted-foreground">Connectors</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Health checks -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Activity class="h-4 w-4" />
                                    Health Checks
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="overflow-x-auto rounded-md border">
                                    <table class="w-full text-sm">
                                        <thead class="border-b bg-muted/50">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-medium">Check</th>
                                                <th class="px-3 py-2 text-left font-medium">Status</th>
                                                <th class="px-3 py-2 text-left font-medium">Message</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(c, i) in data.health.checks"
                                                :key="i"
                                                class="border-b last:border-0"
                                            >
                                                <td class="px-3 py-2 font-mono text-xs">{{ c.check_id }}</td>
                                                <td class="px-3 py-2">
                                                    <Badge :variant="statusVariant(c.status)">{{ c.status }}</Badge>
                                                </td>
                                                <td class="px-3 py-2 text-xs text-muted-foreground">{{ c.message }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Environment -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Server class="h-4 w-4" />
                                    Environment
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <template v-for="(value, key) in data.environment" :key="key">
                                        <dt class="text-muted-foreground">{{ key.replace(/_/g, ' ') }}</dt>
                                        <dd class="font-mono text-xs">
                                            <Badge v-if="typeof value === 'boolean'" :variant="value ? 'destructive' : 'default'">
                                                {{ value }}
                                            </Badge>
                                            <span v-else>{{ value }}</span>
                                        </dd>
                                    </template>
                                </dl>

                                <div v-if="data.queue?.sizes && Object.keys(data.queue.sizes).length > 0" class="mt-4 pt-4 border-t">
                                    <p class="text-sm font-medium mb-2">Queue depths ({{ data.queue.driver }})</p>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                        <template v-for="(size, queue) in data.queue.sizes" :key="queue">
                                            <dt class="text-muted-foreground font-mono text-xs">{{ queue }}</dt>
                                            <dd>
                                                <Badge :variant="size > 50 ? 'destructive' : size > 10 ? 'secondary' : 'outline'">
                                                    {{ size }}
                                                </Badge>
                                            </dd>
                                        </template>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Recent audit events -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                Recent Events
                            </CardTitle>
                            <CardDescription>Latest 20 audit log entries</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="data.recent_events?.length === 0" class="text-sm text-muted-foreground py-4 text-center">
                                No recent events.
                            </div>
                            <div v-else class="overflow-x-auto rounded-md border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium">Time</th>
                                            <th class="px-3 py-2 text-left font-medium">Action</th>
                                            <th class="px-3 py-2 text-left font-medium">Actor</th>
                                            <th class="px-3 py-2 text-left font-medium">Target</th>
                                            <th class="px-3 py-2 text-left font-medium">Outcome</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="e in data.recent_events"
                                            :key="e.id"
                                            class="border-b last:border-0"
                                        >
                                            <td class="px-3 py-2 text-xs text-muted-foreground whitespace-nowrap">
                                                {{ relativeTime(e.created_at) }}
                                            </td>
                                            <td class="px-3 py-2 font-mono text-xs">{{ e.action }}</td>
                                            <td class="px-3 py-2 text-xs text-muted-foreground">{{ e.actor_type }}</td>
                                            <td class="px-3 py-2 text-xs text-muted-foreground">
                                                {{ e.target_type }}{{ e.target_id ? ` #${e.target_id}` : '' }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <Badge :variant="outcomeVariant(e.outcome)">{{ e.outcome }}</Badge>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
