<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head } from '@inertiajs/vue3';
import {
    MessageSquare,
    RefreshCw,
    Archive,
    Send,
    ChevronDown,
    ChevronUp,
    Clock,
} from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted } from 'vue';

const sessions = ref([]);
const meta = ref({});
const loading = ref(false);
const error = ref('');
const statusFilter = ref('');
const expandedSession = ref(null);
const history = ref([]);
const historyLoading = ref(false);
const sendContent = ref('');
const sendLoading = ref(false);

const load = async (page = 1) => {
    loading.value = true;
    error.value = '';

    try {
        const params = { page, per_page: 25 };
        if (statusFilter.value) params.status = statusFilter.value;

        const response = await axios.get('/agent/api/v1/chat/sessions', { params });
        sessions.value = response.data.data;
        meta.value = response.data.meta;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load sessions.';
    } finally {
        loading.value = false;
    }
};

const toggleSession = async (session) => {
    if (expandedSession.value === session.id) {
        expandedSession.value = null;
        history.value = [];
        return;
    }

    expandedSession.value = session.id;
    historyLoading.value = true;
    history.value = [];

    try {
        const response = await axios.get(`/agent/api/v1/chat/sessions/${session.id}/messages`);
        history.value = response.data.data;
    } catch {
        history.value = [];
    } finally {
        historyLoading.value = false;
    }
};

const sendMessage = async (sessionId) => {
    if (!sendContent.value.trim()) return;

    sendLoading.value = true;
    try {
        await axios.post(`/agent/api/v1/chat/sessions/${sessionId}/send`, {
            content: sendContent.value,
        });
        sendContent.value = '';
        const response = await axios.get(`/agent/api/v1/chat/sessions/${sessionId}/messages`);
        history.value = response.data.data;
    } catch (e) {
        error.value = e?.response?.data?.error ?? 'Failed to send message.';
    } finally {
        sendLoading.value = false;
    }
};

const archiveSession = async (sessionId) => {
    if (!confirm('Archive this session?')) return;

    try {
        await axios.post(`/agent/api/v1/chat/sessions/${sessionId}/archive`);
        await load(meta.value.current_page);
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to archive session.';
    }
};

const relativeTime = (ts) => {
    if (!ts) return '—';
    const diff = Date.now() - new Date(ts).getTime();
    if (diff < 60000) return `${Math.round(diff / 1000)}s ago`;
    if (diff < 3600000) return `${Math.round(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.round(diff / 3600000)}h ago`;
    return `${Math.round(diff / 86400000)}d ago`;
};

onMounted(load);
</script>

<template>
    <AppLayout title="Chat Sessions">
        <Head title="Chat Sessions" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <MessageSquare class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            Chat Sessions
                        </h2>
                        <HelpHint
                            ui-key="chat-sessions"
                            short-text="View messenger chat sessions, browse history, and send messages."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <select
                        v-model="statusFilter"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        @change="load(1)"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                    </select>
                    <Button variant="outline" size="sm" :disabled="loading" @click="load(meta.current_page)">
                        <RefreshCw class="h-4 w-4" />
                        Refresh
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-4">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <div v-if="loading && sessions.length === 0" class="text-center py-12 text-muted-foreground">
                    Loading sessions...
                </div>

                <div v-else-if="sessions.length === 0" class="text-center py-12 text-muted-foreground">
                    No chat sessions found.
                </div>

                <div v-else class="space-y-3">
                    <Card
                        v-for="session in sessions"
                        :key="session.id"
                    >
                        <CardContent class="pt-4 pb-2 space-y-0">
                            <!-- Session row -->
                            <div
                                class="flex items-center justify-between cursor-pointer"
                                @click="toggleSession(session)"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <Badge :variant="session.status === 'active' ? 'default' : 'outline'">
                                        {{ session.status }}
                                    </Badge>
                                    <span class="text-sm font-mono truncate">{{ session.session_key }}</span>
                                    <Badge variant="outline" class="text-xs">
                                        {{ session.connector_account?.provider ?? '?' }}
                                    </Badge>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-xs text-muted-foreground flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" />
                                        {{ session.messages_count ?? 0 }}
                                    </span>
                                    <span class="text-xs text-muted-foreground flex items-center gap-1">
                                        <Clock class="h-3 w-3" />
                                        {{ relativeTime(session.updated_at) }}
                                    </span>
                                    <Button
                                        v-if="session.status === 'active'"
                                        variant="outline"
                                        size="sm"
                                        @click.stop="archiveSession(session.id)"
                                    >
                                        <Archive class="h-3 w-3" />
                                    </Button>
                                    <ChevronUp v-if="expandedSession === session.id" class="h-4 w-4 text-muted-foreground" />
                                    <ChevronDown v-else class="h-4 w-4 text-muted-foreground" />
                                </div>
                            </div>

                            <!-- Expanded history -->
                            <div v-if="expandedSession === session.id" class="mt-3 pt-3 border-t space-y-3">
                                <div v-if="historyLoading" class="text-sm text-muted-foreground py-2">
                                    Loading history...
                                </div>
                                <div v-else-if="history.length === 0" class="text-sm text-muted-foreground py-2">
                                    No messages in this session.
                                </div>
                                <div v-else class="max-h-[400px] overflow-y-auto space-y-2 pr-2">
                                    <div
                                        v-for="msg in history"
                                        :key="msg.id"
                                        class="flex gap-2"
                                        :class="msg.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                                    >
                                        <div
                                            class="max-w-[80%] rounded-lg px-3 py-2 text-sm"
                                            :class="msg.direction === 'outbound'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted'"
                                        >
                                            <p class="text-xs font-medium opacity-70 mb-0.5">
                                                {{ msg.direction === 'outbound' ? 'Bot' : 'User' }}
                                            </p>
                                            <p class="whitespace-pre-wrap break-words">{{ msg.content }}</p>
                                            <p class="text-[10px] opacity-50 mt-1">{{ relativeTime(msg.created_at) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Send message -->
                                <div v-if="session.status === 'active'" class="flex gap-2">
                                    <input
                                        v-model="sendContent"
                                        type="text"
                                        placeholder="Send a message..."
                                        class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                        @keydown.enter="sendMessage(session.id)"
                                    />
                                    <Button
                                        size="sm"
                                        :disabled="sendLoading || !sendContent.trim()"
                                        @click="sendMessage(session.id)"
                                    >
                                        <Send class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Pagination -->
                <div v-if="meta.last_page > 1" class="flex justify-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="meta.current_page <= 1"
                        @click="load(meta.current_page - 1)"
                    >
                        Previous
                    </Button>
                    <span class="text-sm text-muted-foreground self-center">
                        Page {{ meta.current_page }} of {{ meta.last_page }}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="meta.current_page >= meta.last_page"
                        @click="load(meta.current_page + 1)"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
