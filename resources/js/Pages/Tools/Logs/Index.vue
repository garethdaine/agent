<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FileText,
    RefreshCw,
    Download,
    Search,
    Pause,
    Play,
} from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { formatDateTime } from '@/Utils/formatDate';

const channel = ref('laravel');
const level = ref('');
const search = ref('');
const lines = ref([]);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const autoTail = ref(false);
const channels = ref(['laravel', 'messenger', 'runtime', 'docs']);
let tailTimer = null;
const logContainer = ref(null);

const levels = ['', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const params = { channel: channel.value, lines: 150 };
        if (level.value) params.level = level.value;
        if (search.value) params.search = search.value;

        const response = await axios.get('/agent/api/v1/logs', { params });
        lines.value = response.data.data.lines;
        total.value = response.data.data.total;
        if (response.data.data.channels) {
            channels.value = response.data.data.channels;
        }

        await nextTick();
        scrollToBottom();
    } catch (e) {
        error.value = e?.response?.data?.error ?? e?.response?.data?.message ?? 'Failed to load logs.';
    } finally {
        loading.value = false;
    }
};

const scrollToBottom = () => {
    if (logContainer.value) {
        logContainer.value.scrollTop = logContainer.value.scrollHeight;
    }
};

const toggleTail = () => {
    autoTail.value = !autoTail.value;
    if (autoTail.value) {
        tailTimer = setInterval(load, 5000);
    } else {
        clearInterval(tailTimer);
        tailTimer = null;
    }
};

const exportLog = async () => {
    try {
        const response = await axios.get('/agent/api/v1/logs/export', {
            params: { channel: channel.value },
            responseType: 'blob',
        });

        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^";\s]+)"?/);
        const filename = match?.[1] || `${channel.value}-export.log`;

        const url = URL.createObjectURL(response.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    } catch {
        error.value = 'Failed to export log file.';
    }
};

const levelColor = (lvl) => {
    const map = {
        emergency: 'text-red-400 bg-red-500/10',
        alert: 'text-red-400 bg-red-500/10',
        critical: 'text-red-400 bg-red-500/10',
        error: 'text-red-400 bg-red-500/10',
        warning: 'text-amber-400 bg-amber-500/10',
        notice: 'text-blue-400 bg-blue-500/10',
        info: 'text-green-400 bg-green-500/10',
        debug: 'text-zinc-400 bg-zinc-500/10',
    };
    return map[lvl?.toLowerCase()] ?? 'text-zinc-400';
};

const levelVariant = (lvl) => {
    const map = { error: 'destructive', warning: 'secondary', critical: 'destructive', emergency: 'destructive' };
    return map[lvl?.toLowerCase()] ?? 'outline';
};

watch(channel, () => {
    lines.value = [];
    load();
});

onMounted(load);

onUnmounted(() => {
    if (tailTimer) clearInterval(tailTimer);
});
</script>

<template>
    <AppLayout title="Logs">
        <Head title="Logs" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <FileText class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            Logs
                        </h2>
                        <HelpHint
                            ui-key="logs"
                            short-text="Live tail of application log channels with filtering and export."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <!-- Controls -->
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <select
                                v-model="channel"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option v-for="ch in channels" :key="ch" :value="ch">
                                    {{ ch }}
                                </option>
                            </select>

                            <select
                                v-model="level"
                                class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                @change="load"
                            >
                                <option value="">All levels</option>
                                <option v-for="l in levels.filter(Boolean)" :key="l" :value="l">
                                    {{ l }}
                                </option>
                            </select>

                            <div class="relative flex-1 min-w-[200px]">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search logs..."
                                    class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                    @keydown.enter="load"
                                />
                            </div>

                            <Button variant="outline" size="sm" :disabled="loading" @click="load">
                                <RefreshCw class="h-4 w-4" />
                                Refresh
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                :class="autoTail ? 'border-primary text-primary' : ''"
                                @click="toggleTail"
                            >
                                <Play v-if="!autoTail" class="h-4 w-4" />
                                <Pause v-else class="h-4 w-4" />
                                {{ autoTail ? 'Tailing...' : 'Live tail' }}
                            </Button>

                            <Button variant="outline" size="sm" @click="exportLog">
                                <Download class="h-4 w-4" />
                                Export
                            </Button>

                            <Badge variant="outline">{{ total }} lines</Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Log output -->
                <div
                    ref="logContainer"
                    class="max-h-[600px] overflow-y-auto overflow-x-auto rounded-xl bg-zinc-950 font-mono text-xs"
                >
                    <div v-if="loading && lines.length === 0" class="p-4 text-zinc-500">
                        Loading...
                    </div>
                    <div v-else-if="lines.length === 0" class="p-4 text-zinc-500">
                        No log entries found.
                    </div>
                    <table v-else class="w-full">
                        <tbody>
                            <tr
                                v-for="(entry, i) in lines"
                                :key="i"
                                class="hover:bg-zinc-900/50 border-b border-zinc-800/30 align-top"
                            >
                                <td class="px-3 py-1.5 text-zinc-500 whitespace-nowrap select-none w-[180px]">
                                    {{ formatDateTime(entry.timestamp, { second: '2-digit' }) }}
                                </td>
                                <td class="px-2 py-1.5 w-[80px]">
                                    <span
                                        class="inline-block rounded px-1.5 py-0.5 text-[10px] font-medium uppercase"
                                        :class="levelColor(entry.level)"
                                    >
                                        {{ entry.level }}
                                    </span>
                                </td>
                                <td class="px-3 py-1.5 text-zinc-300 whitespace-pre-wrap break-all">
                                    {{ entry.message }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
