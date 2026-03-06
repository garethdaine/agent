import { ref, nextTick, onBeforeUnmount } from 'vue';
import axios from 'axios';

const POLL_INTERVAL_MS = 2000;
const POLL_MAX_DURATION_MS = 30000;

export function useChatMessages() {
    const messages = ref([]);
    const messagesLoading = ref(false);
    const sendLoading = ref(false);
    const messagesError = ref('');
    const isAtBottom = ref(true);
    const feedRef = ref(null);
    let messagePollTimer = null;

    const loadMessages = async (sessionId) => {
        if (!sessionId) {
            messages.value = [];
            return;
        }

        messagesLoading.value = true;
        messagesError.value = '';

        try {
            const response = await axios.get(`/agent/api/v1/chat/sessions/${sessionId}/messages`, {
                params: { per_page: 100 },
            });
            messages.value = response.data.data;

            if (isAtBottom.value) {
                await nextTick();
                scrollToBottom();
            }
        } catch (e) {
            messagesError.value = e?.response?.data?.message ?? 'Failed to load messages.';
        } finally {
            messagesLoading.value = false;
        }
    };

    const stopMessagePolling = () => {
        if (messagePollTimer) {
            clearInterval(messagePollTimer);
            messagePollTimer = null;
        }
    };

    const sendMessage = async (sessionId, content) => {
        if (!content?.trim() || !sessionId) return;

        sendLoading.value = true;
        messagesError.value = '';
        stopMessagePolling();

        try {
            await axios.post(`/agent/api/v1/chat/sessions/${sessionId}/send`, { content });
            await loadMessages(sessionId);

            const pollStart = Date.now();
            messagePollTimer = setInterval(async () => {
                if (Date.now() - pollStart > POLL_MAX_DURATION_MS) {
                    stopMessagePolling();
                    return;
                }
                await loadMessages(sessionId);
                const last = messages.value[messages.value.length - 1];
                if (last?.direction === 'outbound') {
                    stopMessagePolling();
                }
            }, POLL_INTERVAL_MS);
        } catch (e) {
            messagesError.value = e?.response?.data?.error ?? 'Failed to send message.';
        } finally {
            sendLoading.value = false;
        }
    };

    const scrollToBottom = () => {
        if (feedRef.value) {
            feedRef.value.scrollTop = feedRef.value.scrollHeight;
        }
    };

    const handleScroll = () => {
        if (!feedRef.value) return;
        const { scrollTop, scrollHeight, clientHeight } = feedRef.value;
        isAtBottom.value = scrollHeight - scrollTop - clientHeight < 60;
    };

    const groupMessagesByDate = () => {
        const groups = [];
        let currentDate = null;

        for (const msg of messages.value) {
            const date = new Date(msg.created_at).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
            });

            if (date !== currentDate) {
                currentDate = date;
                groups.push({ type: 'date', label: date });
            }

            groups.push({ type: 'message', data: msg });
        }

        return groups;
    };

    onBeforeUnmount(() => {
        stopMessagePolling();
    });

    return {
        messages,
        messagesLoading,
        sendLoading,
        messagesError,
        isAtBottom,
        feedRef,
        loadMessages,
        sendMessage,
        stopMessagePolling,
        scrollToBottom,
        handleScroll,
        groupMessagesByDate,
    };
}
