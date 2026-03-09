import { ref, onUnmounted } from 'vue';
import axios from 'axios';

const POLL_INTERVAL = 5000;

function inferActivityFromName(name) {
    if (!name) return 'working';
    const lower = name.toLowerCase();
    if (lower.includes('review')) return 'reviewing';
    if (lower.includes('analysis') || lower.includes('analy')) return 'analyzing';
    if (lower.includes('synthesis') || lower.includes('report')) return 'compiling_report';
    if (lower.includes('test')) return 'testing';
    if (lower.includes('refactor')) return 'refactoring';
    if (lower.includes('fix') || lower.includes('debug')) return 'debugging';
    if (lower.includes('plan') || lower.includes('design')) return 'planning';
    if (lower.includes('write') || lower.includes('implement') || lower.includes('build')) return 'writing_code';
    return 'working';
}

export function useOfficeRealtime() {
    const officeState = ref(null);
    const connected = ref(false);
    const lastUpdate = ref(null);
    const agentOutputQueue = ref([]);

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
            case 'delegation.graph_created': {
                if (officeState.value.delegation) {
                    officeState.value.delegation.active_graphs = (officeState.value.delegation.active_graphs ?? 0) + 1;
                }
                break;
            }
            case 'ritual.started': {
                officeState.value.agents?.forEach((agent) => {
                    if (agent.role !== 'coordinator') return;
                    agent.zone = 'conference';
                    agent.current_activity = 'reading';
                    agent.status = 'running';
                });
                break;
            }
            case 'ritual.phase_started': {
                const agent = officeState.value.agents?.find((a) => a.id === payload.agent_id);
                if (agent) {
                    agent.status = 'running';
                    agent.current_activity = inferActivityFromName(payload.task_name ?? payload.phase_name);
                    agent.zone = 'workstation';
                }
                break;
            }
            case 'ritual.phase_completed': {
                const agent = officeState.value.agents?.find((a) => a.id === payload.agent_id);
                if (agent) {
                    agent.status = 'idle';
                    agent.current_activity = 'idle';
                }
                break;
            }
            case 'council.deliberation_started': {
                officeState.value.agents?.forEach((agent) => {
                    if (payload.member_ids?.includes(agent.id)) {
                        agent.zone = 'conference';
                        agent.current_activity = 'chatting';
                        agent.status = 'running';
                    }
                });
                break;
            }
            case 'council.deliberation_ended': {
                officeState.value.agents?.forEach((agent) => {
                    if (payload.member_ids?.includes(agent.id)) {
                        agent.zone = 'workstation';
                        agent.current_activity = 'idle';
                        agent.status = 'idle';
                    }
                });
                break;
            }
            case 'ritual.failed': {
                officeState.value.agents?.forEach((agent) => {
                    if (agent.role === 'coordinator') {
                        agent.zone = 'escalation';
                        agent.current_activity = 'waiting';
                    }
                });
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
            case 'agent.output': {
                agentOutputQueue.value.push({
                    run_id: payload.run_id,
                    text: payload.text,
                    at: Date.now(),
                });
                if (agentOutputQueue.value.length > 20) {
                    agentOutputQueue.value = agentOutputQueue.value.slice(-20);
                }
                break;
            }
            case 'delegation.task_completed': {
                const agent = officeState.value.agents?.find(
                    (a) => a.current_delegation_task?.id === payload.task_id,
                );
                if (agent) {
                    agent.status = 'idle';
                    agent.current_activity = 'idle';
                    agent.current_delegation_task = null;
                }
                agentOutputQueue.value.push({
                    event_type: 'task_completed',
                    task_name: payload.task_name,
                    next_tasks: payload.next_tasks ?? [],
                    delegatee_profile_id: payload.delegatee_profile_id,
                    at: Date.now(),
                });
                break;
            }
            case 'agent.escalation': {
                agentOutputQueue.value.push({
                    event_type: 'escalation',
                    run_id: payload.run_id,
                    job_name: payload.job_name,
                    reason: payload.reason,
                    summary: payload.summary,
                    at: Date.now(),
                });
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
            try { echoChannel.stopListening('.activity.changed'); } catch { /* channel already closed */ }
            echoChannel = null;
        }
        connected.value = false;
    }

    onUnmounted(() => stop());

    return {
        officeState,
        connected,
        lastUpdate,
        agentOutputQueue,
        fetchState,
        start,
        stop,
    };
}
