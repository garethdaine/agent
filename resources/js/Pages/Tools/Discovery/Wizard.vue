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
import { formatInterrogationError } from '@/Components/Interrogation/errorFormatting';
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

const PHASE = Object.freeze({
    SETUP: 0,
    PROVIDER_SETUP: 1,
    TECH_STACK_SETUP: 2,
    DISCOVERY: 3,
    INTERROGATION: 4,
    SUMMARY: 5,
    PLANNING: 6,
    BUILD_RULES: 7,
    BUILD_TASKS: 8,
    BUILD_EXECUTION: 9,
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
    regeneratePlan: false,
    exportPlan: false,
    generateBuildTasks: false,
    approveBuildTasks: false,
    startBuild: false,
    pauseBuild: false,
    resumeBuild: false,
    clarifyBuild: false,
    createBuildTask: false,
    updateBuildTaskIds: {},
    deleteBuildTaskIds: {},
    regenerateBuildTaskIds: {},
});
const providerConnecting = ref(false);
const providerDisconnecting = ref(false);
const providerSettingsSaving = ref(false);
const providerContextLoading = ref(false);
const providerContextLoaded = ref(false);
const providerTeamId = ref('');
const linearTeams = ref([]);
const techStackSubmitting = ref(false);
const techStackDraft = ref({
    name: '',
    documentation_url: '',
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
    const serverValue = session.value?.has_meaningful_plan;
    if (typeof serverValue === 'boolean') {
        return serverValue;
    }

    const plan = session.value?.plan_json ?? {};
    const markdown = String(plan?.plan_markdown ?? '').trim();
    const hasMarkdown = markdown !== '' && !/^plan not generated yet\.?$/i.test(markdown);

    return hasMarkdown
        || (Array.isArray(plan?.sections) && plan.sections.length > 0)
        || (Array.isArray(plan?.risks) && plan.risks.length > 0)
        || (Array.isArray(plan?.assumptions) && plan.assumptions.length > 0);
});
const planGenerationStatus = computed(() => String(session.value?.metadata_json?.plan?.generation_status ?? '').toLowerCase());
const planRevisionStatus = computed(() => String(session.value?.metadata_json?.plan?.revision_status ?? '').toLowerCase());
const isPlanRevising = computed(() => {
    if ((session.value?.phase ?? 0) !== PHASE.PLANNING) {
        return false;
    }

    if (session.value?.status === 'failed') {
        return false;
    }

    return localPlanRevisionPending.value || ['queued', 'running'].includes(planRevisionStatus.value);
});
const isPlanGenerating = computed(() => {
    if ((session.value?.phase ?? 0) !== PHASE.PLANNING) {
        return false;
    }

    if (hasMeaningfulPlan.value || session.value?.status === 'failed' || session.value?.status === 'paused') {
        return false;
    }

    return localPlanGenerationPending.value || ['queued', 'running'].includes(planGenerationStatus.value);
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
const taskProviders = computed(() => (Array.isArray(session.value?.task_providers) ? session.value.task_providers : []));
const linearProvider = computed(() => taskProviders.value.find((provider) => String(provider?.driver ?? '').toLowerCase() === 'linear') ?? null);
const selectedLinearTeam = computed(() => {
    const targetId = String(providerTeamId.value ?? '').trim();
    if (targetId === '') {
        return null;
    }

    return linearTeams.value.find((team) => String(team?.id ?? '').trim() === targetId) ?? null;
});
const techStacks = computed(() => (Array.isArray(session.value?.tech_stacks) ? session.value.tech_stacks : []));
const displayError = computed(() => formatInterrogationError(error.value, { allowDetails: true, maxSummaryLength: 240 }));
const buildActivity = computed(() => {
    const relevant = events.value
        .filter((event) => {
            const type = String(event?.event_type ?? '');
            const payload = event?.payload ?? {};
            const notice = String(payload?.notice ?? '');
            const code = String(payload?.code ?? '');

            if (type === 'error' && code === 'BUILD_TASK_GENERATION_FAILED') {
                return true;
            }

            if (type === 'system' && notice.startsWith('build_task')) {
                return true;
            }

            return false;
        })
        .slice(-8);

    return relevant.map((event) => {
        const payload = event?.payload ?? {};
        const rawMessage = String(payload?.message ?? payload?.notice ?? '').trim();
        const at = String(payload?.at ?? event?.event_ts ?? event?.created_at ?? '').trim();
        const atLabel = at !== '' ? new Date(at).toLocaleTimeString() : '';
        const message = formatInterrogationError(rawMessage, { allowDetails: false, maxSummaryLength: 140 }).summary;

        return {
            sequence: Number(event?.sequence ?? 0),
            at,
            at_label: atLabel,
            message: message || 'Build task activity update.',
        };
    });
});

watch(activeQuestion, (question) => {
    if (!question) {
        selectedQuestionId.value = '';
    }
});

watch(
    [hasMeaningfulPlan, () => session.value?.status, () => session.value?.phase, planGenerationStatus],
    ([hasPlan, status, phase, generationStatus]) => {
        if (phase < PHASE.PLANNING || hasPlan || status === 'failed' || status === 'paused' || generationStatus === '' || generationStatus === 'idle' || generationStatus === 'failed') {
            localPlanGenerationPending.value = false;
        }

        if (generationStatus === 'failed') {
            const generationError = String(session.value?.metadata_json?.plan?.generation_error ?? '').trim();
            if (generationError !== '') {
                error.value = generationError;
            }
        }
    }
);

watch(
    [() => session.value?.phase, () => session.value?.status, planRevisionStatus],
    ([phase, status, revisionStatus]) => {
        if (phase !== PHASE.PLANNING || status === 'failed' || status === 'paused' || revisionStatus === 'idle' || revisionStatus === 'failed') {
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

        if (phase !== PHASE.INTERROGATION || status !== 'interrogating') {
            awaitingNextQuestion.value = false;
            return;
        }

        if (Number(count) > submittedQuestionCount.value) {
            awaitingNextQuestion.value = false;
        }
    }
);

const syncProviderTeamDraftFromSession = () => {
    providerTeamId.value = String(linearProvider.value?.team_id ?? '').trim();
};

watch(linearProvider, async (provider, previousProvider) => {
    if (!provider) {
        linearTeams.value = [];
        providerContextLoaded.value = false;
        providerTeamId.value = '';
        return;
    }

    const previousTeamId = String(previousProvider?.team_id ?? '').trim();
    const currentDraftTeamId = String(providerTeamId.value ?? '').trim();
    if (currentDraftTeamId === '' || currentDraftTeamId === previousTeamId) {
        syncProviderTeamDraftFromSession();
    }

    if (!providerContextLoaded.value) {
        await loadLinearProviderContext();
    }
});

const loadLinearProviderContext = async ({ force = false } = {}) => {
    if (!linearProvider.value) {
        linearTeams.value = [];
        providerContextLoaded.value = false;
        providerTeamId.value = '';
        return;
    }

    if (providerContextLoading.value || (providerContextLoaded.value && !force)) {
        return;
    }

    providerContextLoading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/linear/projects`);
        linearTeams.value = Array.isArray(data?.data?.teams) ? data.data.teams : [];
        providerContextLoaded.value = true;

        const selectedTeamId = String(data?.data?.selected_team_id ?? '').trim();
        providerTeamId.value = selectedTeamId !== ''
            ? selectedTeamId
            : String(linearProvider.value?.team_id ?? '').trim();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load Linear team options.';
    } finally {
        providerContextLoading.value = false;
    }
};

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
        const revisionStatus = String(session.value?.metadata_json?.plan?.revision_status ?? '').trim().toLowerCase();
        const sessionError = String(session.value?.error_summary ?? '').trim();
        const buildError = String(session.value?.build?.error ?? '').trim();
        const sessionStatus = String(session.value?.status ?? '').trim().toLowerCase();

        if (revisionError !== '' && revisionStatus === 'failed') {
            error.value = revisionError;
        } else if (sessionStatus === 'failed' && sessionError !== '') {
            error.value = sessionError;
        } else if (sessionStatus === 'failed' && buildError !== '') {
            error.value = buildError;
        } else {
            const staleServerErrors = [revisionError, sessionError, buildError].filter((message) => message !== '');
            if (staleServerErrors.includes(String(error.value ?? '').trim())) {
                error.value = '';
            }
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

const startProviderOAuth = async (driver = 'linear') => {
    providerConnecting.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/${driver}/oauth/start`);
        const url = String(data?.data?.authorization_url ?? '').trim();
        if (url === '') {
            throw new Error('OAuth redirect URL was not returned.');
        }

        window.location.assign(url);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? e?.message ?? 'Failed to start provider authentication.';
        providerConnecting.value = false;
    }
};

const disconnectProvider = async (driver = 'linear') => {
    providerDisconnecting.value = true;
    error.value = '';

    try {
        await axios.delete(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/${driver}`);
        notice.value = `${driver} disconnected.`;
        linearTeams.value = [];
        providerContextLoaded.value = false;
        providerTeamId.value = '';
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to disconnect provider.';
    } finally {
        providerDisconnecting.value = false;
    }
};

const saveLinearTeamSelection = async () => {
    if (!linearProvider.value) {
        return;
    }

    providerSettingsSaving.value = true;
    error.value = '';

    try {
        const currentTeamId = String(linearProvider.value?.team_id ?? '').trim();
        const nextTeamId = String(providerTeamId.value ?? '').trim();
        const currentProjectMode = String(linearProvider.value?.project_mode ?? 'create_new').trim().toLowerCase() === 'existing'
            ? 'existing'
            : 'create_new';
        const projectMode = currentProjectMode === 'existing' && nextTeamId !== '' && nextTeamId !== currentTeamId
            ? 'create_new'
            : currentProjectMode;

        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/linear/settings`, {
            team_id: nextTeamId || null,
            project_mode: projectMode,
            existing_project_id: projectMode === 'existing'
                ? String(linearProvider.value?.selected_project_id ?? '').trim()
                : null,
        });

        const teamLabel = selectedLinearTeam.value?.name || linearProvider.value?.team_name || 'selected team';
        notice.value = `Linear team set to ${teamLabel}.`;
        await loadSession(false);
        await loadLinearProviderContext({ force: true });
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to update Linear team.';
    } finally {
        providerSettingsSaving.value = false;
    }
};

const advancePreDiscovery = async () => {
    busy.value = true;
    error.value = '';

    try {
        const phase = Number(session.value?.phase ?? PHASE.SETUP);
        if (phase === PHASE.TECH_STACK_SETUP && techStacks.value.length === 0) {
            error.value = 'Add at least one tech stack entry before starting discovery.';
            return;
        }

        const endpoint = phase === PHASE.TECH_STACK_SETUP
            ? 'start-discovery'
            : 'advance-pre-discovery';

        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/${endpoint}`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to advance setup.';
    } finally {
        busy.value = false;
    }
};

const addTechStack = async () => {
    const name = String(techStackDraft.value.name ?? '').trim();
    const documentationUrl = String(techStackDraft.value.documentation_url ?? '').trim();

    if (name === '' || documentationUrl === '') {
        error.value = 'Tech stack name and documentation URL are required.';
        return;
    }

    techStackSubmitting.value = true;
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/tech-stacks`, {
            name,
            documentation_url: documentationUrl,
        });
        techStackDraft.value = { name: '', documentation_url: '' };
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to add tech stack entry.';
    } finally {
        techStackSubmitting.value = false;
    }
};

const removeTechStack = async (stackId) => {
    const normalized = Number(stackId);
    if (!Number.isFinite(normalized)) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await axios.delete(`/agent/api/v1/interrogation/sessions/${props.sessionId}/tech-stacks/${normalized}`);
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to remove tech stack entry.';
    } finally {
        busy.value = false;
    }
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

    if ((session.value?.phase ?? 0) !== PHASE.SUMMARY) {
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
        notice.value = data?.data?.message ?? 'Plan revision queued. Revising plan now.';
        await loadSession(false);
    } catch (e) {
        localPlanRevisionPending.value = false;
        error.value = e?.response?.data?.error?.message ?? 'Failed to request plan revision.';
    } finally {
        actionState.value.revisePlan = false;
    }
};

const regeneratePlan = async () => {
    if (!window.confirm('Regenerate the entire plan from locked summary context? This will replace the current plan only if a valid regenerated plan is produced.')) {
        return;
    }

    actionState.value.regeneratePlan = true;
    error.value = '';
    planRevisionQueuedAfterSequence.value = Number(events.value[events.value.length - 1]?.sequence ?? 0);
    localPlanRevisionPending.value = true;

    try {
        const { data } = await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/regenerate-plan`);
        notice.value = data?.data?.message ?? 'Plan regeneration queued.';
        await loadSession(false);
    } catch (e) {
        localPlanRevisionPending.value = false;
        error.value = e?.response?.data?.error?.message ?? 'Failed to regenerate plan.';
    } finally {
        actionState.value.regeneratePlan = false;
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

const generateBuildTasks = async (payload = {}) => {
    actionState.value.generateBuildTasks = true;
    error.value = '';

    try {
        const rules = Array.isArray(payload?.project_rules) ? payload.project_rules : [];
        const files = Array.isArray(payload?.project_rule_files) ? payload.project_rule_files : [];

        if (files.length > 0) {
            const form = new FormData();
            form.append('project_rules', JSON.stringify(rules));
            files.forEach((file) => {
                form.append('project_rule_files[]', file);
            });

            await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/generate-build-tasks`, form, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
        } else {
            await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/generate-build-tasks`, {
                project_rules: rules,
            });
        }

        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to generate build tasks.';
    } finally {
        actionState.value.generateBuildTasks = false;
    }
};

const setTaskActionBusy = (key, taskId, value) => {
    const normalizedTaskId = Number(taskId);
    if (!Number.isFinite(normalizedTaskId)) {
        return;
    }

    const source = typeof actionState.value[key] === 'object' && actionState.value[key] !== null
        ? actionState.value[key]
        : {};
    const next = { ...source };
    if (value) {
        next[normalizedTaskId] = true;
    } else {
        delete next[normalizedTaskId];
    }
    actionState.value[key] = next;
};

const createBuildTask = async (payload = {}) => {
    actionState.value.createBuildTask = true;
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/build-tasks`, payload ?? {});
        notice.value = 'Build task added.';
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to add build task.';
    } finally {
        actionState.value.createBuildTask = false;
    }
};

const updateBuildTask = async (payload = {}) => {
    const taskId = Number(payload?.task_id);
    if (!Number.isFinite(taskId)) {
        return;
    }

    setTaskActionBusy('updateBuildTaskIds', taskId, true);
    error.value = '';

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}/build-tasks/${taskId}`, {
            title: payload?.title,
            description: payload?.description,
            instructions_markdown: payload?.instructions_markdown,
        });
        notice.value = 'Build task updated.';
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to update build task.';
    } finally {
        setTaskActionBusy('updateBuildTaskIds', taskId, false);
    }
};

const deleteBuildTask = async (payload = {}) => {
    const taskId = Number(payload?.task_id);
    if (!Number.isFinite(taskId)) {
        return;
    }

    setTaskActionBusy('deleteBuildTaskIds', taskId, true);
    error.value = '';

    try {
        await axios.delete(`/agent/api/v1/interrogation/sessions/${props.sessionId}/build-tasks/${taskId}`);
        notice.value = 'Build task deleted.';
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete build task.';
    } finally {
        setTaskActionBusy('deleteBuildTaskIds', taskId, false);
    }
};

const regenerateBuildTask = async (payload = {}) => {
    const taskId = Number(payload?.task_id);
    if (!Number.isFinite(taskId)) {
        return;
    }

    setTaskActionBusy('regenerateBuildTaskIds', taskId, true);
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/build-tasks/${taskId}/regenerate`, {
            amend_notes: payload?.amend_notes,
        });
        notice.value = 'Task regeneration queued.';
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to queue task regeneration.';
    } finally {
        setTaskActionBusy('regenerateBuildTaskIds', taskId, false);
    }
};

const approveBuildTasks = async () => {
    actionState.value.approveBuildTasks = true;
    error.value = '';

    try {
        const { data } = await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/approve-build-tasks`);
        notice.value = data?.data?.task_provider_sync_queued
            ? 'Build tasks approved. Syncing tasks to task provider.'
            : 'Build tasks approved.';
        await loadSession(false);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to approve build tasks.';
    } finally {
        actionState.value.approveBuildTasks = false;
    }
};

const startBuild = async ({ restartFailed = false, restartAll = false } = {}) => {
    actionState.value.startBuild = true;
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/start-build`, {
            restart_failed: restartFailed,
            restart_all: restartAll,
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

const renameCurrentSession = async () => {
    const nextName = window.prompt('Session name', session.value?.name ?? '');
    if (nextName === null) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}`, {
            name: nextName,
        });
        await loadSession(false);
        notice.value = 'Session name updated.';
    } catch (e) {
        const payload = e?.response?.data ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to rename session.';
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
        notice.value = 'Session restarted. Optionally connect a task provider, then add your tech stack before discovery.';
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

    const params = new URLSearchParams(window.location.search);
    const providerConnected = String(params.get('provider_connected') ?? '').trim();
    const providerError = String(params.get('provider_error') ?? '').trim();

    if (providerConnected !== '') {
        notice.value = `${providerConnected} connected successfully.`;
    }

    if (providerError !== '') {
        error.value = providerError;
    }

    if (providerConnected !== '' || providerError !== '') {
        const cleanUrl = `${window.location.pathname}${window.location.hash || ''}`;
        window.history.replaceState({}, document.title, cleanUrl);
    }

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
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ session?.project_directory || 'Loading...' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <SessionStatusBadge v-if="session" :status="session.status" />
                    <button
                        v-if="session && !session.deleted_at
                            && (['failed', 'paused', 'setup'].includes(session.status)
                                || (session.status === 'interrogating' && session.phase === PHASE.INTERROGATION))"
                        type="button"
                        class="rounded border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50 dark:border-amber-700/70 dark:text-amber-300 dark:hover:bg-amber-950/30"
                        :disabled="busy"
                        @click="retrySession"
                    >
                        Retry
                    </button>
                    <button
                        v-if="session && !session.deleted_at"
                        type="button"
                        class="rounded border border-orange-300 px-2 py-1 text-xs text-orange-700 hover:bg-orange-50 dark:border-orange-700/70 dark:text-orange-300 dark:hover:bg-orange-950/30"
                        :disabled="busy"
                        @click="restartFromBeginning"
                    >
                        Restart Fresh
                    </button>
                    <button
                        v-if="session && !session.deleted_at"
                        type="button"
                        class="rounded border border-blue-300 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50 dark:border-blue-700/70 dark:text-blue-300 dark:hover:bg-blue-950/30"
                        :disabled="busy"
                        @click="renameCurrentSession"
                    >
                        Rename
                    </button>
                    <button
                        v-if="session && !session.deleted_at && session.phase === PHASE.INTERROGATION"
                        type="button"
                        class="rounded border border-orange-300 px-2 py-1 text-xs text-orange-700 hover:bg-orange-50 dark:border-orange-700/70 dark:text-orange-300 dark:hover:bg-orange-950/30"
                        :disabled="busy"
                        @click="cleanupInvalidQuestions"
                    >
                        Clean Questions
                    </button>
                    <button
                        v-if="session && session.phase < PHASE.BUILD_TASKS && session.status !== 'paused'"
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50"
                        :disabled="busy"
                        @click="pause"
                    >
                        Pause
                    </button>
                    <button
                        v-if="session && session.phase < PHASE.BUILD_TASKS && session.status === 'paused'"
                        type="button"
                        class="rounded border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50"
                        :disabled="busy"
                        @click="resume"
                    >
                        Resume
                    </button>
                    <button
                        v-if="session && !session.deleted_at"
                        type="button"
                        class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-800/70 dark:text-red-300 dark:hover:bg-red-950/30"
                        :disabled="busy"
                        @click="deleteSession"
                    >
                        Delete
                    </button>
                    <button
                        v-if="session && session.deleted_at"
                        type="button"
                        class="rounded border border-green-300 px-2 py-1 text-xs text-green-700 hover:bg-green-50 dark:border-green-800/70 dark:text-green-300 dark:hover:bg-green-950/30"
                        :disabled="busy"
                        @click="restoreSession"
                    >
                        Restore
                    </button>
                    <Link
                        v-if="session"
                        :href="route('tools.discovery.session.settings', session.id)"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200"
                    >
                        Session Settings
                    </Link>
                    <Link :href="route('tools.discovery.index')" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Back</Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <div v-if="displayError.summary" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                    <p>{{ displayError.summary }}</p>
                    <details v-if="displayError.details" class="mt-2">
                        <summary class="cursor-pointer text-xs font-medium">Show technical details</summary>
                        <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap rounded border border-red-200 bg-white p-2 text-[11px] text-red-700 dark:border-red-800/60 dark:bg-gray-950 dark:text-red-200">{{ displayError.details }}</pre>
                    </details>
                </div>
                <p v-if="notice" class="rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">{{ notice }}</p>

                <div v-if="loading" class="rounded-lg border border-gray-200 bg-white p-8 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Loading session...</div>

                <template v-else-if="session">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                        <PhaseStepper :phase="session.phase" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                        <div v-if="session.phase >= PHASE.INTERROGATION && session.phase < PHASE.PLANNING" class="xl:col-span-3">
                            <QaHistoryPanel :events="events" :selected-question-id="activeQuestion?.question_id || ''" @select-question="focusQuestion" />
                        </div>

                        <div class="space-y-4" :class="session.phase >= PHASE.PLANNING ? 'xl:col-span-9' : 'xl:col-span-6'">
                            <template v-if="session.phase <= PHASE.TECH_STACK_SETUP">
                                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pre-Discovery Setup</p>
                                    <h3 class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">Setup</h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        Task provider is optional. You can connect Linear now or skip it and continue to tech stack setup.
                                    </p>

                                    <div class="mt-3 rounded border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Task Provider (Optional)</p>
                                        <p v-if="linearProvider" class="text-sm text-gray-800 dark:text-gray-100">
                                            Connected to Linear
                                            <span v-if="linearProvider.provider_workspace_name">({{ linearProvider.provider_workspace_name }})</span>
                                            <span v-if="linearProvider.team_name">· Team: {{ linearProvider.team_name }}</span>
                                        </p>
                                        <p v-else class="text-sm text-gray-700 dark:text-gray-200">Linear is not connected for this session.</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <button
                                                v-if="!linearProvider"
                                                type="button"
                                                class="rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                                :disabled="providerConnecting"
                                                @click="startProviderOAuth('linear')"
                                            >
                                                {{ providerConnecting ? 'Redirecting...' : 'Connect Linear' }}
                                            </button>
                                            <button
                                                v-else
                                                type="button"
                                                class="rounded border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50"
                                                :disabled="providerDisconnecting"
                                                @click="disconnectProvider('linear')"
                                            >
                                                {{ providerDisconnecting ? 'Disconnecting...' : 'Disconnect Linear' }}
                                            </button>
                                        </div>
                                        <div v-if="linearProvider" class="mt-3 space-y-2">
                                            <div class="flex items-center gap-2">
                                                <select
                                                    v-model="providerTeamId"
                                                    class="w-full rounded border border-gray-300 px-2 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                                >
                                                    <option value="">Select a Linear team...</option>
                                                    <option v-for="team in linearTeams" :key="team.id" :value="team.id">
                                                        {{ team.name || team.id }}
                                                    </option>
                                                </select>
                                                <button
                                                    type="button"
                                                    class="rounded border border-gray-300 px-2 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50"
                                                    :disabled="providerContextLoading"
                                                    @click="loadLinearProviderContext({ force: true })"
                                                >
                                                    {{ providerContextLoading ? 'Loading...' : 'Refresh' }}
                                                </button>
                                            </div>
                                            <div class="flex justify-end">
                                                <button
                                                    type="button"
                                                    class="rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                                    :disabled="providerSettingsSaving || providerTeamId.trim() === ''"
                                                    @click="saveLinearTeamSelection"
                                                >
                                                    {{ providerSettingsSaving ? 'Saving...' : 'Save Linear Team' }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Tech Stack</h3>
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                            Add stack entries one-by-one with documentation URLs. These are used as context in discovery, planning, and build execution.
                                        </p>
                                        <div class="mt-3 space-y-2">
                                            <div v-for="stack in techStacks" :key="stack.id" class="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900/40">
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ stack.name }}</p>
                                                    <a :href="stack.documentation_url" target="_blank" rel="noreferrer" class="text-xs text-indigo-700 underline dark:text-indigo-300">{{ stack.documentation_url }}</a>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-800/70 dark:text-red-300 dark:hover:bg-red-950/30"
                                                    :disabled="busy"
                                                    @click="removeTechStack(stack.id)"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                            <div v-if="techStacks.length === 0" class="rounded border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                                No tech stack entries added yet.
                                            </div>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                                            <input
                                                v-model="techStackDraft.name"
                                                type="text"
                                                class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                                placeholder="Stack name (e.g. Laravel 12)"
                                            />
                                            <input
                                                v-model="techStackDraft.documentation_url"
                                                type="url"
                                                class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 md:col-span-2"
                                                placeholder="Documentation URL"
                                            />
                                        </div>
                                        <div class="mt-2 flex justify-end">
                                            <button
                                                type="button"
                                                class="rounded border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:opacity-50 dark:border-indigo-800/70 dark:text-indigo-300 dark:hover:bg-indigo-950/30"
                                                :disabled="techStackSubmitting"
                                                @click="addTechStack"
                                            >
                                                {{ techStackSubmitting ? 'Adding...' : 'Add Tech Stack' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex justify-end">
                                        <button
                                            type="button"
                                            class="rounded bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                            :disabled="busy || (session.phase === PHASE.TECH_STACK_SETUP && techStacks.length === 0)"
                                            @click="advancePreDiscovery"
                                        >
                                            {{
                                                session.phase <= PHASE.PROVIDER_SETUP
                                                    ? 'Continue to Tech Stack'
                                                    : 'Start Discovery'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <StatusCard v-if="session.phase === PHASE.DISCOVERY" :session="session" :latest-discovery-event="latestDiscoveryEvent" />

                            <template v-if="session.phase === PHASE.INTERROGATION">
                                <div
                                    v-if="selectedQuestion && latestQuestion && selectedQuestion.question_id !== latestQuestion.question_id"
                                    class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800/70 dark:bg-amber-950/30 dark:text-amber-200"
                                >
                                    Revising an earlier question ({{ selectedQuestion.question_id }}).
                                    <button type="button" class="ml-2 font-medium underline" @click="selectedQuestionId = ''">Return to latest question</button>
                                </div>
                                <QuestionRenderer v-if="!awaitingNextQuestion" :question="activeQuestion" />
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
                                    v-if="!awaitingNextQuestion"
                                    :question="activeQuestion"
                                    :busy="busy"
                                    :waiting-for-next-question="awaitingNextQuestion"
                                    @submit="submitAnswer"
                                />
                            </template>

                            <template v-if="session.phase === PHASE.SUMMARY">
                                <SummaryViewer
                                    :summary="session.summary_json || {}"
                                    :busy="busy"
                                    :status="session.status || ''"
                                    @confirm="confirmSummary"
                                    @revise="reviseSummary"
                                    @continue="continueInterrogation"
                                />
                            </template>

                            <template v-if="session.phase === PHASE.PLANNING">
                                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <div class="mb-3 flex justify-end">
                                        <button
                                            v-if="hasMeaningfulPlan && !hasPlanApproved"
                                            type="button"
                                            class="mr-2 rounded border border-indigo-300 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 disabled:opacity-50 dark:border-indigo-700/70 dark:text-indigo-300 dark:hover:bg-indigo-950/30"
                                            :disabled="busy || actionState.regeneratePlan || actionState.revisePlan || actionState.approvePlan || isPlanRevising"
                                            @click="regeneratePlan"
                                        >
                                            {{ actionState.regeneratePlan ? 'Regenerating...' : 'Regenerate Plan' }}
                                        </button>
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
                                            class="rounded border border-green-400 bg-green-50 px-3 py-2 text-sm font-semibold text-green-700 dark:border-green-800/70 dark:bg-green-950/30 dark:text-green-300"
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

                            <template v-if="session.phase === PHASE.BUILD_RULES">
                                <BuildPanel
                                    mode="rules"
                                    :build="build"
                                    :activity="buildActivity"
                                    :actions="actionState"
                                    :disabled="busy"
                                    @generate-tasks="generateBuildTasks"
                                />
                            </template>

                            <template v-if="session.phase === PHASE.BUILD_TASKS">
                                <BuildPanel
                                    mode="tasks"
                                    :build="build"
                                    :activity="buildActivity"
                                    :actions="actionState"
                                    :disabled="busy"
                                    @generate-tasks="generateBuildTasks"
                                    @create-task="createBuildTask"
                                    @update-task="updateBuildTask"
                                    @delete-task="deleteBuildTask"
                                    @regenerate-task="regenerateBuildTask"
                                    @approve-tasks="approveBuildTasks"
                                    @start="startBuild()"
                                />
                            </template>

                            <template v-if="session.phase >= PHASE.BUILD_EXECUTION">
                                <BuildPanel
                                    mode="execution"
                                    :build="build"
                                    :activity="buildActivity"
                                    :actions="actionState"
                                    :disabled="busy"
                                    @pause="pauseBuild"
                                    @resume="resumeBuild"
                                    @retry="startBuild({ restartFailed: true })"
                                    @rerun-all="startBuild({ restartFailed: true, restartAll: true })"
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
