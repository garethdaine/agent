<script setup>
import AnswerInput from '@/Components/Interrogation/AnswerInput.vue';
import BuildPanel from '@/Components/Interrogation/BuildPanel.vue';
import PhaseStepper from '@/Components/Interrogation/PhaseStepper.vue';
import PlanViewer from '@/Components/Interrogation/PlanViewer.vue';
import QaHistoryPanel from '@/Components/Interrogation/QaHistoryPanel.vue';
import QuestionRenderer from '@/Components/Interrogation/QuestionRenderer.vue';
import SessionStatusBadge from '@/Components/Interrogation/SessionStatusBadge.vue';
import StatsPanel from '@/Components/Interrogation/StatsPanel.vue';
import StatusCard from '@/Components/Interrogation/StatusCard.vue';
import SummaryViewer from '@/Components/Interrogation/SummaryViewer.vue';
import { isAnswerableQuestionEvent } from '@/Components/Interrogation/questionPresentation';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
const notice = ref('');
const pollingTimer = ref(null);
const selectedQuestionId = ref('');
const awaitingNextQuestion = ref(false);
const submittedQuestionCount = ref(0);
const localPlanGenerationPending = ref(false);
const localPlanRevisionPending = ref(false);
const planRevisionQueuedAfterSequence = ref(0);
const actionState = ref({
    approvePlan: false,
    revisePlan: false,
    exportPlan: false,
    generateBuildTasks: false,
    startBuild: false,
    pauseBuild: false,
    resumeBuild: false,
    clarifyBuild: false,
});
let echoChannel = null;

const latestQuestion = computed(() => {
    const questionEvents = events.value.filter((event) => isAnswerableQuestionEvent(event));

    return questionEvents.length > 0 ? questionEvents[questionEvents.length - 1].payload : null;
});

const selectedQuestion = computed(() => {
    const targetId = selectedQuestionId.value.trim();
    if (targetId === '') {
        return null;
    }

    const found = [...events.value]
        .reverse()
        .find((event) => event.event_type === 'question' && String(event?.payload?.question_id ?? '') === targetId);

    return found?.payload ?? null;
});

const activeQuestion = computed(() => selectedQuestion.value ?? latestQuestion.value);
const isRevisingHistoryQuestion = computed(() => {
    const selectedId = String(selectedQuestion.value?.question_id ?? '').trim();
    const latestId = String(latestQuestion.value?.question_id ?? '').trim();

    if (selectedId === '') {
        return false;
    }

    if (latestId === '') {
        return true;
    }

    return selectedId !== latestId;
});

const latestDiscoveryEvent = computed(() => {
    const discovery = events.value.filter((event) => event.event_type === 'discovery_activity');

    return discovery.length > 0 ? discovery[discovery.length - 1] : null;
});

const questionEventCount = computed(() => events.value.filter((event) => isAnswerableQuestionEvent(event)).length);
const hasMeaningfulPlan = computed(() => {
    const plan = session.value?.plan_json ?? {};
    const markdown = String(plan?.plan_markdown ?? '').trim();
    const hasMarkdown = markdown !== '' && !/^plan not generated yet\.?$/i.test(markdown);

    return hasMarkdown
        || (Array.isArray(plan?.sections) && plan.sections.length > 0)
        || (Array.isArray(plan?.risks) && plan.risks.length > 0)
        || (Array.isArray(plan?.assumptions) && plan.assumptions.length > 0);
});
const planRevisionStatus = computed(() => String(session.value?.metadata_json?.plan?.revision_status ?? '').toLowerCase());
const isPlanRevising = computed(() => {
    if ((session.value?.phase ?? 0) !== 4) {
        return false;
    }

    if (session.value?.status === 'failed') {
        return false;
    }

    return localPlanRevisionPending.value || ['queued', 'running'].includes(planRevisionStatus.value);
});
const isPlanGenerating = computed(() => {
    if ((session.value?.phase ?? 0) !== 4) {
        return false;
    }

    if (hasMeaningfulPlan.value) {
        return false;
    }

    return localPlanGenerationPending.value || session.value?.status === 'planning';
});
const hasPlanApproved = computed(() => Boolean(session.value?.approved_at));
const canApprovePlan = computed(() => hasMeaningfulPlan.value && !hasPlanApproved.value && !actionState.value.approvePlan && !busy.value && !isPlanGenerating.value && !isPlanRevising.value);
const planPrimaryActionLabel = computed(() => {
    if (!hasMeaningfulPlan.value) {
        if (isPlanGenerating.value) {
            return 'Generating plan...';
        }

        return busy.value ? 'Processing...' : 'Generate Plan';
    }

    if (isPlanRevising.value) {
        return 'Revising plan...';
    }

    return actionState.value.approvePlan ? 'Approving...' : 'Approve Plan';
});
const planPrimaryActionDisabled = computed(() => {
    if (!hasMeaningfulPlan.value) {
        return busy.value || isPlanGenerating.value || isPlanRevising.value;
    }

    return !canApprovePlan.value;
});
const latestPlanReadySequence = computed(() => {
    for (let index = events.value.length - 1; index >= 0; index -= 1) {
        const event = events.value[index];
        if (event?.event_type !== 'system') {
            continue;
        }

        if (String(event?.payload?.notice ?? '') !== 'plan_ready') {
            continue;
        }

        return Number(event?.sequence ?? 0);
    }

    return 0;
});
const build = computed(() => (session.value?.build && typeof session.value.build === 'object' ? session.value.build : {}));

watch(activeQuestion, (question) => {
    if (!question) {
        selectedQuestionId.value = '';
    }
});

watch(
    [hasMeaningfulPlan, () => session.value?.status, () => session.value?.phase],
    ([hasPlan, status, phase]) => {
        if (phase < 4 || hasPlan || status === 'failed' || status === 'paused') {
            localPlanGenerationPending.value = false;
        }
    }
);

watch(
    [() => session.value?.phase, () => session.value?.status, planRevisionStatus],
    ([phase, status, revisionStatus]) => {
        if (phase !== 4 || status === 'failed' || status === 'paused' || revisionStatus === 'idle' || revisionStatus === 'failed') {
            localPlanRevisionPending.value = false;
        }

        if (revisionStatus === 'failed') {
            const revisionError = String(session.value?.metadata_json?.plan?.revision_error ?? '').trim();
            if (revisionError !== '') {
                error.value = revisionError;
            }
        }
    }
);

watch(latestPlanReadySequence, (sequence) => {
    if (!localPlanRevisionPending.value) {
        return;
    }

    if (Number(sequence) <= Number(planRevisionQueuedAfterSequence.value || 0)) {
        return;
    }

    localPlanRevisionPending.value = false;
    notice.value = 'Plan revision complete.';
});

watch(
    [questionEventCount, () => session.value?.phase, () => session.value?.status],
    ([count, phase, status]) => {
        if (!awaitingNextQuestion.value) {
            return;
        }

        if (phase !== 2 || status !== 'interrogating') {
            awaitingNextQuestion.value = false;
            return;
        }

        if (Number(count) > submittedQuestionCount.value) {
            awaitingNextQuestion.value = false;
        }
    }
);

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

        const revisionError = String(session.value?.metadata_json?.plan?.revision_error ?? '').trim();
        const sessionError = String(session.value?.error_summary ?? '').trim();
        if (revisionError !== '') {
            error.value = revisionError;
        } else if (sessionError !== '') {
            error.value = sessionError;
        }
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
        const endpoint = isRevisingHistoryQuestion.value ? 'answer/edit' : 'answer';
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/${endpoint}`, payload);
        selectedQuestionId.value = '';
        submittedQuestionCount.value = questionEventCount.value;
        awaitingNextQuestion.value = true;
        await loadSession(false);
    } catch (e) {
        awaitingNextQuestion.value = false;
        error.value = e?.response?.data?.error?.message ?? 'Failed to submit answer.';
    } finally {
        busy.value = false;
    }
};

const focusQuestion = async (questionId) => {
    const targetId = String(questionId || '').trim();
    selectedQuestionId.value = targetId;

    if (targetId === '') {
        return;
    }

    if ((session.value?.phase ?? 0) !== 3) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/continue-interrogation`, {
            revisit_question_id: targetId,
        });
        awaitingNextQuestion.value = false;
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to reopen interrogation for question revision.';
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

const reviseSummary = async (payload) => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/revise-summary`, payload ?? {});
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to request summary revision.';
    } finally {
        busy.value = false;
    }
};

const continueInterrogation = async (payload) => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/continue-interrogation`, payload ?? {});
        selectedQuestionId.value = '';
        submittedQuestionCount.value = questionEventCount.value;
        awaitingNextQuestion.value = true;
        await loadSession(false);
    } catch (e) {
        awaitingNextQuestion.value = false;
        error.value = e?.response?.data?.error?.message ?? 'Failed to continue interrogation.';
    } finally {
        busy.value = false;
    }
};

const generatePlan = async () => {
    busy.value = true;
    localPlanGenerationPending.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/generate-plan`);
        await loadSession(false);
    } catch (e) {
        localPlanGenerationPending.value = false;
        error.value = e?.response?.data?.error?.message ?? 'Failed to queue plan generation.';
    } finally {
        busy.value = false;
    }
};

const approvePlan = async () => {
    actionState.value.approvePlan = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/approve-plan`);
        notice.value = 'Plan approved.';
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to approve plan.';
    } finally {
        actionState.value.approvePlan = false;
    }
};

const requestRevision = async (payload) => {
    actionState.value.revisePlan = true;
    error.value = '';
    planRevisionQueuedAfterSequence.value = Number(events.value[events.value.length - 1]?.sequence ?? 0);
    localPlanRevisionPending.value = true;

    try {
        const { data } = await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/revise-plan`, payload);
        notice.value = data?.data?.message ?? 'Plan revision queued. Regenerating plan now.';
        await loadSession(false);
    } catch (e) {
        localPlanRevisionPending.value = false;
        error.value = e?.response?.data?.error?.message ?? 'Failed to request plan revision.';
    } finally {
        actionState.value.revisePlan = false;
    }
};

const exportPlan = async () => {
    actionState.value.exportPlan = true;

    try {
        const { data } = await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/export-plan`);
        const path = data?.data?.path;
        notice.value = path ? `Plan exported to ${path}` : 'Plan exported.';
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to export plan.';
    } finally {
        actionState.value.exportPlan = false;
    }
};

const generateBuildTasks = async () => {
    actionState.value.generateBuildTasks = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/generate-build-tasks`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to generate build tasks.';
    } finally {
        actionState.value.generateBuildTasks = false;
    }
};

const startBuild = async (restartFailed = false) => {
    actionState.value.startBuild = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/start-build`, {
            restart_failed: restartFailed,
        });
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to start build.';
    } finally {
        actionState.value.startBuild = false;
    }
};

const pauseBuild = async () => {
    actionState.value.pauseBuild = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/pause-build`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to pause build.';
    } finally {
        actionState.value.pauseBuild = false;
    }
};

const resumeBuild = async () => {
    actionState.value.resumeBuild = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/resume-build`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to resume build.';
    } finally {
        actionState.value.resumeBuild = false;
    }
};

const clarifyBuild = async (payload) => {
    actionState.value.clarifyBuild = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/build/clarify`, payload ?? {});
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to submit clarification.';
    } finally {
        actionState.value.clarifyBuild = false;
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

const retrySession = async () => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/retry`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to retry session.';
    } finally {
        busy.value = false;
    }
};

const restartFromBeginning = async () => {
    if (!window.confirm('Restart from the beginning? This will permanently clear all questions, answers, and generated artifacts for this session.')) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/restart-from-beginning`);
        selectedQuestionId.value = '';
        awaitingNextQuestion.value = false;
        submittedQuestionCount.value = 0;
        notice.value = 'Session restarted from setup. Discovery has been queued.';
        await loadSession(true);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restart session.';
    } finally {
        busy.value = false;
    }
};

const cleanupInvalidQuestions = async () => {
    busy.value = true;

    try {
        const { data } = await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/cleanup-invalid-questions`);
        await loadSession(true);

        const removedQuestionEvents = Number(data?.data?.removed_question_events ?? 0);
        const removedAnswerEvents = Number(data?.data?.removed_answer_events ?? 0);
        const removedPendingOpenQuestions = Number(data?.data?.removed_pending_open_questions ?? 0);
        const removedAskedOpenQuestions = Number(data?.data?.removed_asked_open_questions ?? 0);
        const removedActiveOpenQuestion = Boolean(data?.data?.removed_active_open_question);
        const parts = [
            `${removedQuestionEvents} question event(s)`,
            `${removedAnswerEvents} answer event(s)`,
            `${removedPendingOpenQuestions + removedAskedOpenQuestions + (removedActiveOpenQuestion ? 1 : 0)} open-question queue item(s)`,
        ];

        notice.value = `Cleanup complete. Removed ${parts.join(', ')}.`;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to clean up invalid questions.';
    } finally {
        busy.value = false;
    }
};

const deleteSession = async () => {
    if (!window.confirm('Delete this session? You can restore it from the sessions list.')) {
        return;
    }

    busy.value = true;

    try {
        await axios.delete(`/agent/api/v1/interrogation/sessions/${props.sessionId}`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete session.';
    } finally {
        busy.value = false;
    }
};

const restoreSession = async () => {
    busy.value = true;

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/restore`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restore session.';
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
                    <button
                        v-if="session && !session.deleted_at
                            && (['failed', 'paused', 'setup'].includes(session.status)
                                || (session.status === 'interrogating' && session.phase === 2))"
                        type="button"
                        class="rounded border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50"
                        :disabled="busy"
                        @click="retrySession"
                    >
                        Retry
                    </button>
                    <button
                        v-if="session && !session.deleted_at"
                        type="button"
                        class="rounded border border-orange-300 px-2 py-1 text-xs text-orange-700 hover:bg-orange-50"
                        :disabled="busy"
                        @click="restartFromBeginning"
                    >
                        Restart Fresh
                    </button>
                    <button
                        v-if="session && !session.deleted_at && session.phase === 2"
                        type="button"
                        class="rounded border border-orange-300 px-2 py-1 text-xs text-orange-700 hover:bg-orange-50"
                        :disabled="busy"
                        @click="cleanupInvalidQuestions"
                    >
                        Clean Questions
                    </button>
                    <button
                        v-if="session && session.phase < 5 && session.status !== 'paused'"
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50"
                        :disabled="busy"
                        @click="pause"
                    >
                        Pause
                    </button>
                    <button
                        v-if="session && session.phase < 5 && session.status === 'paused'"
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50"
                        :disabled="busy"
                        @click="resume"
                    >
                        Resume
                    </button>
                    <button
                        v-if="session && !session.deleted_at"
                        type="button"
                        class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50"
                        :disabled="busy"
                        @click="deleteSession"
                    >
                        Delete
                    </button>
                    <button
                        v-if="session && session.deleted_at"
                        type="button"
                        class="rounded border border-green-300 px-2 py-1 text-xs text-green-700 hover:bg-green-50"
                        :disabled="busy"
                        @click="restoreSession"
                    >
                        Restore
                    </button>
                    <Link :href="route('tools.discovery.index')" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Back</Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
                <p v-if="notice" class="rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700">{{ notice }}</p>

                <div v-if="loading" class="rounded-lg border border-gray-200 bg-white p-8 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800">Loading session...</div>

                <template v-else-if="session">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <PhaseStepper :phase="session.phase" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                        <div v-if="session.phase < 4" class="xl:col-span-3">
                            <QaHistoryPanel :events="events" :selected-question-id="activeQuestion?.question_id || ''" @select-question="focusQuestion" />
                        </div>

                        <div class="space-y-4" :class="session.phase >= 4 ? 'xl:col-span-9' : 'xl:col-span-6'">
                            <StatusCard v-if="session.phase <= 1" :session="session" :latest-discovery-event="latestDiscoveryEvent" />

                            <template v-if="session.phase === 2">
                                <div
                                    v-if="selectedQuestion && latestQuestion && selectedQuestion.question_id !== latestQuestion.question_id"
                                    class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                                >
                                    Revising an earlier question ({{ selectedQuestion.question_id }}).
                                    <button type="button" class="ml-2 font-medium underline" @click="selectedQuestionId = ''">Return to latest question</button>
                                </div>
                                <QuestionRenderer :question="awaitingNextQuestion ? null : activeQuestion" />
                                <div
                                    v-if="awaitingNextQuestion"
                                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-4 w-4 animate-spin rounded-full border-2 border-indigo-500 border-t-transparent" />
                                        <span>Answer submitted. Generating next question...</span>
                                    </div>
                                </div>
                                <AnswerInput
                                    :question="awaitingNextQuestion ? null : activeQuestion"
                                    :busy="busy"
                                    :waiting-for-next-question="awaitingNextQuestion"
                                    @submit="submitAnswer"
                                />
                            </template>

                            <template v-if="session.phase === 3">
                                <SummaryViewer
                                    :summary="session.summary_json || {}"
                                    :busy="busy"
                                    :status="session.status || ''"
                                    @confirm="confirmSummary"
                                    @revise="reviseSummary"
                                    @continue="continueInterrogation"
                                />
                            </template>

                            <template v-if="session.phase === 4">
                                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <div class="mb-3 flex justify-end">
                                        <button
                                            v-if="!hasMeaningfulPlan || !hasPlanApproved"
                                            type="button"
                                            class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                            :disabled="planPrimaryActionDisabled"
                                            @click="!hasMeaningfulPlan ? generatePlan() : approvePlan()"
                                        >
                                            {{ planPrimaryActionLabel }}
                                        </button>
                                        <span
                                            v-else
                                            class="rounded border border-green-400 bg-green-50 px-3 py-2 text-sm font-semibold text-green-700"
                                        >Plan Approved</span>
                                    </div>
                                    <PlanViewer
                                        :plan="session.plan_json || {}"
                                        :busy="busy || actionState.exportPlan || actionState.approvePlan"
                                        :generating="isPlanGenerating"
                                        :revising="isPlanRevising"
                                        :revision-submitting="actionState.revisePlan"
                                        @revise="requestRevision"
                                        @export="exportPlan"
                                    />
                                </div>
                            </template>

                            <template v-if="session.phase === 5">
                                <BuildPanel
                                    mode="tasks"
                                    :build="build"
                                    :actions="actionState"
                                    :disabled="busy"
                                    @generate-tasks="generateBuildTasks"
                                    @start="startBuild(false)"
                                />
                            </template>

                            <template v-if="session.phase >= 6">
                                <BuildPanel
                                    mode="execution"
                                    :build="build"
                                    :actions="actionState"
                                    :disabled="busy"
                                    @pause="pauseBuild"
                                    @resume="resumeBuild"
                                    @retry="startBuild(true)"
                                    @clarify="clarifyBuild"
                                />
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
