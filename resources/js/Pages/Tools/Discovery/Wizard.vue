<script setup>
import AnswerInput from '@/Components/Interrogation/AnswerInput.vue';
import PhaseStepper from '@/Components/Interrogation/PhaseStepper.vue';
import PlanViewer from '@/Components/Interrogation/PlanViewer.vue';
import QaHistoryPanel from '@/Components/Interrogation/QaHistoryPanel.vue';
import QuestionRenderer from '@/Components/Interrogation/QuestionRenderer.vue';
import SessionStatusBadge from '@/Components/Interrogation/SessionStatusBadge.vue';
import StatsPanel from '@/Components/Interrogation/StatsPanel.vue';
import StatusCard from '@/Components/Interrogation/StatusCard.vue';
import SummaryViewer from '@/Components/Interrogation/SummaryViewer.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    sessionId: {
        type: Number,
        required: true,
    },
});

const session = ref(null);
const events = ref([]);
const loading = ref(true);
const error = ref('');
const busy = ref(false);
const pollingTimer = ref(null);
let echoChannel = null;

const latestQuestion = computed(() => {
    const questionEvents = events.value.filter((event) => event.event_type === 'question');

    return questionEvents.length > 0 ? questionEvents[questionEvents.length - 1].payload : null;
});

const latestDiscoveryEvent = computed(() => {
    const discovery = events.value.filter((event) => event.event_type === 'discovery_activity');

    return discovery.length > 0 ? discovery[discovery.length - 1] : null;
});

const loadSession = async (includeEvents = true) => {
    try {
        const { data } = await axios.get(`/agent/api/v1/interrogation/sessions/${props.sessionId}`, {
            params: {
                include_events: includeEvents ? 1 : 0,
            },
        });

        session.value = data.data;

        if (includeEvents) {
            events.value = data.data?.events || [];
        }

        error.value = '';
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load session.';
    } finally {
        loading.value = false;
    }
};

const loadNewEvents = async () => {
    if (!session.value) {
        return;
    }

    const after = events.value.length > 0 ? events.value[events.value.length - 1].sequence : 0;

    try {
        const { data } = await axios.get(`/agent/api/v1/interrogation/sessions/${props.sessionId}/events`, {
            params: {
                after_sequence: after,
                limit: 200,
            },
        });

        const incoming = data.data || [];
        if (incoming.length === 0) {
            return;
        }

        const merged = new Map();
        [...events.value, ...incoming].forEach((event) => {
            merged.set(event.id, event);
        });

        events.value = Array.from(merged.values()).sort((a, b) => a.sequence - b.sequence).slice(-1200);
    } catch {
        // ignore transient poll errors in wizard
    }
};

const schedulePoll = () => {
    clearTimeout(pollingTimer.value);
    pollingTimer.value = setTimeout(async () => {
        await loadNewEvents();
        await loadSession(false);
        schedulePoll();
    }, 3000);
};

const subscribeEcho = () => {
    if (!window.Echo) {
        return;
    }

    echoChannel = window.Echo.private(`interrogation.${props.sessionId}`)
        .listen('.session.updated', (event) => {
            if (!event) {
                return;
            }

            const candidate = {
                id: `ws-${event.sequence}`,
                session_id: event.session_id,
                sequence: event.sequence,
                event_type: event.event_type,
                payload: event.payload,
                created_at: new Date().toISOString(),
                event_ts: new Date().toISOString(),
            };

            const existing = events.value.find((item) => item.sequence === candidate.sequence);
            if (!existing) {
                events.value = [...events.value, candidate].sort((a, b) => a.sequence - b.sequence).slice(-1200);
            }

            loadSession(false);
        })
        .listen('.phase.changed', () => {
            loadSession(false);
        });
};

const unsubscribeEcho = () => {
    if (window.Echo && echoChannel) {
        window.Echo.leave(`private-interrogation.${props.sessionId}`);
        window.Echo.leave(`interrogation.${props.sessionId}`);
    }

    echoChannel = null;
};

const submitAnswer = async (payload) => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/answer`, payload);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to submit answer.';
    } finally {
        busy.value = false;
    }
};

const editFromQuestion = async (questionId) => {
    if (!questionId) {
        return;
    }

    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/answer/edit`, {
            question_id: questionId,
            answer_type: 'freetext',
            answer_text: 'Please re-evaluate from this question. I will provide an updated answer next.',
        });
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to request edit.';
    } finally {
        busy.value = false;
    }
};

const confirmSummary = async () => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/confirm-summary`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to confirm summary.';
    } finally {
        busy.value = false;
    }
};

const generatePlan = async () => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/generate-plan`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to queue plan generation.';
    } finally {
        busy.value = false;
    }
};

const requestRevision = async (payload) => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/revise-plan`, payload);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to request plan revision.';
    } finally {
        busy.value = false;
    }
};

const exportPlan = async () => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/export-plan`);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to export plan.';
    } finally {
        busy.value = false;
    }
};

const pause = async () => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/pause`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to pause session.';
    } finally {
        busy.value = false;
    }
};

const resume = async () => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/resume`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to resume session.';
    } finally {
        busy.value = false;
    }
};

onMounted(async () => {
    await loadSession(true);
    subscribeEcho();
    schedulePoll();
});

onBeforeUnmount(() => {
    clearTimeout(pollingTimer.value);
    unsubscribeEcho();
});
</script>

<template>
    <AppLayout title="Discovery Wizard">
        <Head title="Discovery Wizard" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ session?.name || `Session #${sessionId}` }}</h2>
                    <p class="mt-1 text-xs text-gray-500">{{ session?.project_directory || 'Loading...' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <SessionStatusBadge v-if="session" :status="session.status" />
                    <button v-if="session && session.status !== 'paused'" type="button" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" :disabled="busy" @click="pause">Pause</button>
                    <button v-if="session && session.status === 'paused'" type="button" class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50" :disabled="busy" @click="resume">Resume</button>
                    <Link :href="route('tools.discovery.index')" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Back</Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1500px] space-y-4">
                <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

                <div v-if="loading" class="rounded-lg border border-gray-200 bg-white p-8 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800">Loading session...</div>

                <template v-else-if="session">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <PhaseStepper :phase="session.phase" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                        <div class="xl:col-span-3">
                            <QaHistoryPanel :events="events" :selected-question-id="latestQuestion?.question_id || ''" @edit-question="editFromQuestion" />
                        </div>

                        <div class="space-y-4 xl:col-span-6">
                            <StatusCard v-if="session.phase <= 1" :session="session" :latest-discovery-event="latestDiscoveryEvent" />

                            <template v-if="session.phase === 2">
                                <QuestionRenderer :question="latestQuestion" />
                                <AnswerInput :question="latestQuestion" :busy="busy" @submit="submitAnswer" />
                            </template>

                            <template v-if="session.phase === 3">
                                <SummaryViewer :summary="session.summary_json || {}" :busy="busy" @confirm="confirmSummary" />
                            </template>

                            <template v-if="session.phase >= 4">
                                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <div class="mb-3 flex justify-end">
                                        <button type="button" class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50" :disabled="busy" @click="generatePlan">
                                            {{ busy ? 'Processing...' : 'Generate Plan' }}
                                        </button>
                                    </div>
                                    <PlanViewer :plan="session.plan_json || {}" :busy="busy" @revise="requestRevision" @export="exportPlan" />
                                </div>
                            </template>
                        </div>

                        <div class="xl:col-span-3">
                            <StatsPanel :session="session" :events="events" />
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
