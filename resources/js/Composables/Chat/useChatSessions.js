import { ref } from 'vue';
import axios from 'axios';

export function useChatSessions() {
    const sessions = ref([]);
    const meta = ref({});
    const loading = ref(false);
    const error = ref('');
    const statusFilter = ref('');
    const searchQuery = ref('');
    const activeSessionId = ref(null);
    const connectorAccounts = ref([]);
    const connectorsLoading = ref(false);

    const loadSessions = async (page = 1) => {
        loading.value = true;
        error.value = '';

        try {
            const params = { page, per_page: 50 };
            if (statusFilter.value) params.status = statusFilter.value;

            const response = await axios.get('/agent/api/v1/chat/sessions', { params });
            sessions.value = response.data.data;
            meta.value = response.data.meta ?? {};
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Failed to load sessions.';
        } finally {
            loading.value = false;
        }
    };

    const loadConnectors = async () => {
        connectorsLoading.value = true;
        try {
            const response = await axios.get('/agent/api/v1/chat/connectors');
            connectorAccounts.value = response.data.data ?? [];
        } catch {
            connectorAccounts.value = [];
        } finally {
            connectorsLoading.value = false;
        }
    };

    const createSession = async (connectorAccountId, channelId = null) => {
        error.value = '';
        try {
            const payload = { connector_account_id: connectorAccountId };
            if (channelId) payload.channel_id = channelId;

            const response = await axios.post('/agent/api/v1/chat/sessions', payload);
            const newSession = response.data.data;

            await loadSessions(1);
            activeSessionId.value = newSession.id;

            return newSession;
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Failed to create session.';
            return null;
        }
    };

    const archiveSession = async (sessionId) => {
        try {
            await axios.post(`/agent/api/v1/chat/sessions/${sessionId}/archive`);
            await loadSessions(meta.value.current_page ?? 1);

            if (activeSessionId.value === sessionId) {
                activeSessionId.value = null;
            }
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Failed to archive session.';
        }
    };

    const selectSession = (sessionId) => {
        activeSessionId.value = sessionId;
    };

    const activeSession = () => {
        return sessions.value.find((s) => s.id === activeSessionId.value) ?? null;
    };

    const filteredSessions = () => {
        if (!searchQuery.value.trim()) return sessions.value;
        const q = searchQuery.value.toLowerCase();
        return sessions.value.filter((s) => {
            const key = (s.session_key ?? s.channel_id ?? '').toLowerCase();
            const provider = (s.connector_account?.provider ?? '').toLowerCase();
            return key.includes(q) || provider.includes(q);
        });
    };

    const relativeTime = (ts) => {
        if (!ts) return '—';
        const diff = Date.now() - new Date(ts).getTime();
        if (diff < 60000) return `${Math.round(diff / 1000)}s ago`;
        if (diff < 3600000) return `${Math.round(diff / 60000)}m ago`;
        if (diff < 86400000) return `${Math.round(diff / 3600000)}h ago`;
        return `${Math.round(diff / 86400000)}d ago`;
    };

    return {
        sessions,
        meta,
        loading,
        error,
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
    };
}
