import { ref, onUnmounted } from 'vue';
import axios from 'axios';

const POLL_INTERVAL = 5000;

export function useOfficeRealtime() {
    const officeState = ref(null);
    const connected = ref(false);
    const lastUpdate = ref(null);

    let pollTimer = null;
    let echoChannel = null;

    async function fetchState() {
        try {
            const { data } = await axios.get('/agent/api/v1/office/state');
            officeState.value = data?.data ?? null;
            lastUpdate.value = Date.now();
            return officeState.value;
        } catch {
            return null;
        }
    }

    function subscribeEcho(userId) {
        if (!window.Echo) return;
        try {
            echoChannel = window.Echo.private(`office.${userId}`);
            echoChannel.listen('.activity.changed', (event) => {
                handleRealtimeEvent(event);
            });
            connected.value = true;
        } catch {
            connected.value = false;
        }
    }

    function handleRealtimeEvent(event) {
        if (!officeState.value) return;

        const { event_type, payload } = event;

        switch (event_type) {
            case 'run.status_changed': {
                const agent = officeState.value.agents?.find((a) => a.id === payload.agent_id);
                if (agent) {
                    agent.status = payload.status;
                    agent.current_activity = payload.activity ?? 'idle';
                    agent.current_run = payload.run ?? null;
                }
                break;
            }
            case 'session.updated': {
                const agent = officeState.value.agents?.find((a) => a.id === payload.agent_id);
                if (agent) {
                    agent.current_session = payload.session ?? null;
                }
                break;
            }
            case 'system.health': {
                Object.assign(officeState.value.system, payload);
                break;
            }
            case 'delegation.updated': {
                Object.assign(officeState.value.delegation, payload);
                break;
            }
            case 'messenger.updated': {
                if (payload.channels) officeState.value.messenger.channels = payload.channels;
                break;
            }
            case 'memory.formed': {
                if (officeState.value.memory) {
                    officeState.value.memory.total_entries = payload.total_entries ?? officeState.value.memory.total_entries;
                    officeState.value.memory.recent_formations = (officeState.value.memory.recent_formations ?? 0) + 1;
                }
                break;
            }
        }

        lastUpdate.value = Date.now();
    }

    function startPolling() {
        if (pollTimer) return;
        fetchState();
        pollTimer = setInterval(fetchState, POLL_INTERVAL);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function start(userId) {
        startPolling();
        if (userId) subscribeEcho(userId);
    }

    function stop() {
        stopPolling();
        if (echoChannel) {
            try { echoChannel.stopListening('.activity.changed'); } catch {}
            echoChannel = null;
        }
        connected.value = false;
    }

    onUnmounted(() => stop());

    return {
        officeState,
        connected,
        lastUpdate,
        fetchState,
        start,
        stop,
    };
}
