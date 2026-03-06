import { ref, onBeforeUnmount } from 'vue';
import axios from 'axios';

export function useChatActions() {
    const actions = ref([]);
    const actionsLoading = ref(false);
    const actionsError = ref('');
    const pollingIntervals = new Map();

    const loadActions = async (sessionId) => {
        if (!sessionId) {
            actions.value = [];
            return;
        }

        actionsLoading.value = true;
        actionsError.value = '';

        try {
            const response = await axios.get(`/agent/api/v1/chat/sessions/${sessionId}/actions`);
            actions.value = response.data.data ?? [];
        } catch (e) {
            actionsError.value = e?.response?.data?.message ?? 'Failed to load actions.';
        } finally {
            actionsLoading.value = false;
        }
    };

    const confirmAction = async (actionId) => {
        try {
            await axios.post(`/agent/api/v1/chat/actions/${actionId}/confirm`);
            updateActionInList(actionId, { status: 'executing', is_pending: false, is_executing: true });
            startPolling(actionId);
        } catch (e) {
            actionsError.value = e?.response?.data?.error ?? 'Failed to confirm action.';
        }
    };

    const cancelAction = async (actionId) => {
        try {
            await axios.post(`/agent/api/v1/chat/actions/${actionId}/cancel`);
            updateActionInList(actionId, { status: 'cancelled', is_pending: false, is_terminal: true });
            stopPolling(actionId);
        } catch (e) {
            actionsError.value = e?.response?.data?.error ?? 'Failed to cancel action.';
        }
    };

    const pollActionStatus = async (actionId) => {
        try {
            const response = await axios.get(`/agent/api/v1/chat/actions/${actionId}/status`);
            const data = response.data.data ?? response.data;

            updateActionInList(actionId, data);

            if (data.is_terminal) {
                stopPolling(actionId);
            }
        } catch {
            stopPolling(actionId);
        }
    };

    const startPolling = (actionId) => {
        if (pollingIntervals.has(actionId)) return;

        const interval = setInterval(() => pollActionStatus(actionId), 3000);
        pollingIntervals.set(actionId, interval);
    };

    const stopPolling = (actionId) => {
        const interval = pollingIntervals.get(actionId);
        if (interval) {
            clearInterval(interval);
            pollingIntervals.delete(actionId);
        }
    };

    const stopAllPolling = () => {
        for (const [, interval] of pollingIntervals) {
            clearInterval(interval);
        }
        pollingIntervals.clear();
    };

    const updateActionInList = (actionId, updates) => {
        const index = actions.value.findIndex((a) => a.id === actionId);
        if (index !== -1) {
            actions.value[index] = { ...actions.value[index], ...updates };
        }
    };

    const actionsForMessage = (messageId) => {
        return actions.value.filter((a) => a.chat_message_id === messageId);
    };

    onBeforeUnmount(() => {
        stopAllPolling();
    });

    return {
        actions,
        actionsLoading,
        actionsError,
        loadActions,
        confirmAction,
        cancelAction,
        actionsForMessage,
        startPolling,
        stopAllPolling,
    };
}
