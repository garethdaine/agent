<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { MessageSquare, PanelRight, Menu, X } from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';

import ChatSessionList from '@/Components/Chat/ChatSessionList.vue';
import ChatMessageFeed from '@/Components/Chat/ChatMessageFeed.vue';
import ChatComposer from '@/Components/Chat/ChatComposer.vue';
import ChatContextPanel from '@/Components/Chat/ChatContextPanel.vue';
import ChatEmptyState from '@/Components/Chat/ChatEmptyState.vue';
import ChatNewSessionModal from '@/Components/Chat/ChatNewSessionModal.vue';

import { useChatSessions } from '@/Composables/Chat/useChatSessions';
import { useChatMessages } from '@/Composables/Chat/useChatMessages';
import { useChatActions } from '@/Composables/Chat/useChatActions';

const {
    sessions,
    meta,
    loading: sessionsLoading,
    error: sessionsError,
    statusFilter,
    searchQuery,
    activeSessionId,
    connectorAccounts,
    connectorsLoading,
    loadSessions,
    loadConnectors,
    createSession,
    archiveSession,
    selectSession,
    activeSession,
    filteredSessions,
    relativeTime,
} = useChatSessions();

const {
    messages,
    messagesLoading,
    sendLoading,
    messagesError,
    loadMessages,
    sendMessage,
    stopMessagePolling,
} = useChatMessages();

const {
    actions,
    actionsLoading,
    loadActions,
    confirmAction,
    cancelAction,
    stopAllPolling,
} = useChatActions();

const showContextPanel = ref(true);
const mobileSessionList = ref(false);
const showNewSessionModal = ref(false);

const handleOpenNewSession = async () => {
    showNewSessionModal.value = true;
    await loadConnectors();
};

const handleCreateSession = async (connectorAccountId, channelId) => {
    const newSession = await createSession(connectorAccountId, channelId);
    showNewSessionModal.value = false;
    if (newSession) {
        selectSession(newSession.id);
    }
};

// When active session changes, load its messages and actions
watch(activeSessionId, async (newId) => {
    stopAllPolling();
    stopMessagePolling();
    if (newId) {
        mobileSessionList.value = false;
        await Promise.all([loadMessages(newId), loadActions(newId)]);
    } else {
        messages.value = [];
        actions.value = [];
    }
});

const handleSelectSession = (sessionId) => {
    selectSession(sessionId);
};

const handleSend = async (content) => {
    if (!activeSessionId.value) return;
    await sendMessage(activeSessionId.value, content);
    await loadActions(activeSessionId.value);
};

const handleArchive = async (sessionId) => {
    await archiveSession(sessionId);
};

const handleConfirmAction = async (actionId) => {
    await confirmAction(actionId);
};

const handleCancelAction = async (actionId) => {
    await cancelAction(actionId);
};

// Echo real-time
let echoUserId = null;

onMounted(() => {
    loadSessions();

    const userId = usePage().props.auth?.user?.id;
    if (window.Echo && userId) {
        echoUserId = userId;
        window.Echo.private(`user.${userId}`)
            .listen('.chat.message_received', (e) => {
                if (activeSessionId.value === e.session_id) {
                    loadMessages(e.session_id);
                    loadActions(e.session_id);
                }
                loadSessions(meta.value.current_page ?? 1);
            });
    }
});

onBeforeUnmount(() => {
    stopAllPolling();
    if (window.Echo && echoUserId) {
        window.Echo.leave(`user.${echoUserId}`);
    }
});
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
                    <h2 class="text-base font-semibold text-foreground truncate">
                        Chat Sessions
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <!-- Mobile: toggle session list -->
                    <Button
                        variant="outline"
                        size="icon"
                        class="h-9 w-9 lg:hidden"
                        @click="mobileSessionList = !mobileSessionList"
                    >
                        <Menu v-if="!mobileSessionList" class="h-4 w-4" />
                        <X v-else class="h-4 w-4" />
                    </Button>
                    <!-- Desktop: toggle context panel -->
                    <Button
                        v-if="activeSessionId"
                        variant="outline"
                        size="icon"
                        class="h-9 w-9 hidden lg:flex"
                        title="Toggle details panel"
                        @click="showContextPanel = !showContextPanel"
                    >
                        <PanelRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </template>

        <!-- Three-panel layout: break out of AppLayout padding -->
        <div class="-mx-6 -my-6 flex" style="height: calc(100vh - 3.5rem)">
            <!-- Session list (desktop) -->
            <div class="hidden lg:flex">
                <ChatSessionList
                    :sessions="filteredSessions()"
                    :meta="meta"
                    :loading="sessionsLoading"
                    :active-session-id="activeSessionId"
                    :status-filter="statusFilter"
                    :search-query="searchQuery"
                    :relative-time="relativeTime"
                    @select="handleSelectSession"
                    @archive="handleArchive"
                    @create="handleOpenNewSession"
                    @refresh="loadSessions(meta.current_page ?? 1)"
                    @update:status-filter="statusFilter = $event"
                    @update:search-query="searchQuery = $event"
                    @load-page="loadSessions($event)"
                />
            </div>

            <!-- Mobile session list overlay -->
            <Teleport to="body">
                <Transition
                    enter-active-class="transition-opacity duration-200 ease-out"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-150 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="mobileSessionList"
                        class="fixed inset-0 z-50 lg:hidden"
                    >
                        <div class="absolute inset-0 bg-black/50" @click="mobileSessionList = false" />
                        <div class="absolute inset-y-0 left-0 w-[320px] max-w-[85vw] shadow-xl">
                            <ChatSessionList
                                :sessions="filteredSessions()"
                                :meta="meta"
                                :loading="sessionsLoading"
                                :active-session-id="activeSessionId"
                                :status-filter="statusFilter"
                                :search-query="searchQuery"
                                :relative-time="relativeTime"
                                @select="handleSelectSession"
                                @archive="handleArchive"
                                @create="handleOpenNewSession"
                                @refresh="loadSessions(meta.current_page ?? 1)"
                                @update:status-filter="statusFilter = $event"
                                @update:search-query="searchQuery = $event"
                                @load-page="loadSessions($event)"
                            />
                        </div>
                    </div>
                </Transition>
            </Teleport>

            <!-- Chat area -->
            <div class="flex flex-1 flex-col min-w-0 border-l border-border bg-background">
                <template v-if="activeSessionId && activeSession()">
                    <!-- Message feed -->
                    <ChatMessageFeed
                        :messages="messages"
                        :actions="actions"
                        :loading="messagesLoading"
                        :relative-time="relativeTime"
                        @confirm-action="handleConfirmAction"
                        @cancel-action="handleCancelAction"
                    />

                    <!-- Error bar -->
                    <div
                        v-if="messagesError"
                        class="shrink-0 border-t border-destructive/30 bg-destructive/10 px-4 py-2 text-xs text-destructive"
                    >
                        {{ messagesError }}
                    </div>

                    <!-- Composer -->
                    <ChatComposer
                        :disabled="false"
                        :send-loading="sendLoading"
                        :session-status="activeSession()?.status"
                        @send="handleSend"
                    />
                </template>

                <template v-else>
                    <ChatEmptyState />
                </template>
            </div>

            <!-- Context panel (desktop, collapsible) -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="w-0 opacity-0"
                enter-to-class="w-[320px] opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="w-[320px] opacity-100"
                leave-to-class="w-0 opacity-0"
            >
                <div
                    v-if="showContextPanel && activeSessionId && activeSession()"
                    class="hidden lg:block overflow-hidden"
                >
                    <ChatContextPanel
                        :session="activeSession()"
                        :actions="actions"
                        :actions-loading="actionsLoading"
                        :messages="messages"
                        :relative-time="relativeTime"
                        @close="showContextPanel = false"
                        @archive="handleArchive"
                    />
                </div>
            </Transition>
        </div>
        <!-- New session modal -->
        <ChatNewSessionModal
            :open="showNewSessionModal"
            :connectors="connectorAccounts"
            :loading="connectorsLoading"
            @close="showNewSessionModal = false"
            @create="handleCreateSession"
        />
    </AppLayout>
</template>
