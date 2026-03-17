<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, BrainCircuit, CheckCircle2, LoaderCircle, Play, Waypoints } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { formatDateTime } from '@/Utils/formatDate';

const props = defineProps({
    sessionId: {
        type: Number,
        required: true,
    },
});

const loading = ref(false);
const error = ref('');
const session = ref(null);
const stepIndex = ref(0);
const starting = ref(false);
const submitting = ref(false);
const generatingPlan = ref(false);
const answerDrafts = reactive({});
let echoChannel = null;

const applySession = (nextSession) => {
    if (!nextSession) {
        return;
    }

    const existingEvents = Array.isArray(session.value?.events) ? session.value.events : [];
    const existingSession = session.value ?? {};
    session.value = {
        ...existingSession,
        ...nextSession,
        company_description: nextSession.company_description ?? existingSession.company_description ?? null,
        workflow_brief: nextSession.workflow_brief ?? existingSession.workflow_brief ?? null,
        events: Array.isArray(nextSession.events) ? nextSession.events : existingEvents,
    };
};

const mergeEvent = (event) => {
    if (!event) {
        return;
    }

    const existingEvents = Array.isArray(session.value?.events) ? session.value.events : [];
    if (existingEvents.some((item) => item.sequence === event.sequence)) {
        return;
    }

    const nextEvents = [...existingEvents, {
        id: `ws-${event.sequence}`,
        sequence: event.sequence,
        event_type: event.event_type,
        payload: event.payload,
        created_at: event.event_ts ?? new Date().toISOString(),
        event_ts: event.event_ts ?? new Date().toISOString(),
    }]
        .sort((a, b) => a.sequence - b.sequence)
        .slice(-150);

    session.value = {
        ...(session.value ?? {}),
        events: nextEvents,
    };
};

const load = async (silent = false) => {
    if (!silent) {
        loading.value = true;
    }
    error.value = '';

    try {
        const { data } = await axios.get(`/agent/api/v1/workflow-interrogator/sessions/${props.sessionId}`);
        applySession(data?.data ?? null);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load workflow interrogation session.';
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
};

const activeBatch = computed(() => session.value?.active_batch ?? null);
const questions = computed(() => Array.isArray(activeBatch.value?.questions) ? activeBatch.value.questions : []);
const currentQuestion = computed(() => questions.value[stepIndex.value] ?? null);
const hasSummary = computed(() => !!session.value?.summary_json && Object.keys(session.value.summary_json || {}).length > 0);
const hasActionPlan = computed(() => !!session.value?.action_plan_json && Object.keys(session.value.action_plan_json || {}).length > 0);
const attachments = computed(() => Array.isArray(session.value?.attachments) ? session.value.attachments : []);
const ambiguityReport = computed(() => session.value?.ambiguity_report ?? {});
const processing = computed(() => session.value?.processing ?? null);
const isProcessing = computed(() => ['queued', 'running'].includes(String(processing.value?.state ?? '')));
const hasFailedState = computed(() => String(session.value?.status ?? '') === 'failed');
const latestErrorEvent = computed(() => {
    const events = Array.isArray(session.value?.events) ? session.value.events : [];

    return [...events].reverse().find((event) => String(event?.event_type ?? '') === 'error') ?? null;
});
const processingLabel = computed(() => {
    const kind = String(processing.value?.kind ?? '');
    if (kind === 'plan') {
        return 'Generating action plan';
    }

    return session.value?.current_round > 0 ? 'Generating next batch' : 'Generating first batch';
});

const formatAttachmentSize = (value) => {
    const size = Number(value ?? 0);
    if (!Number.isFinite(size) || size <= 0) {
        return '0 B';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const ensureDraftsForBatch = () => {
    for (const question of questions.value) {
        const questionId = String(question?.question_id ?? '');
        if (questionId === '' || answerDrafts[questionId]) {
            continue;
        }

        answerDrafts[questionId] = {
            question_id: questionId,
            answer_type: String(question?.answer_type ?? 'freetext'),
            answer_text: '',
            selected_option: '',
            selected_options: [],
        };
    }

    if (stepIndex.value >= questions.value.length) {
        stepIndex.value = 0;
    }
};

watch(questions, ensureDraftsForBatch, { deep: true });

const subscribeRealtime = () => {
    if (!window.Echo) {
        return;
    }

    echoChannel = window.Echo.private(`workflow-interrogation.${props.sessionId}`)
        .listen('.session.updated', async (event) => {
            if (!event) {
                return;
            }

            mergeEvent(event);

            if (event.session) {
                applySession(event.session);
            }

            if (
                event._requires_refresh
                || event._truncated
                || event?.session?.summary_json?._truncated
                || event?.session?.action_plan_json?._truncated
            ) {
                await load(true);
            }
        });
};

const unsubscribeRealtime = () => {
    if (window.Echo && echoChannel) {
        window.Echo.leave(`private-workflow-interrogation.${props.sessionId}`);
        window.Echo.leave(`workflow-interrogation.${props.sessionId}`);
    }

    echoChannel = null;
};

const startInterrogation = async () => {
    starting.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(`/agent/api/v1/workflow-interrogator/sessions/${props.sessionId}/start`);
        applySession(data?.data ?? null);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to start interrogation.';
    } finally {
        starting.value = false;
    }
};

const currentAnswerValid = computed(() => {
    const question = currentQuestion.value;
    if (!question) {
        return true;
    }

    const draft = answerDrafts[String(question.question_id)] ?? null;
    if (!draft) {
        return false;
    }

    if (draft.answer_type === 'choice') {
        return String(draft.selected_option ?? '').trim() !== '';
    }

    if (draft.answer_type === 'multi_choice') {
        return Array.isArray(draft.selected_options) && draft.selected_options.length > 0;
    }

    return String(draft.answer_text ?? '').trim() !== '';
});

const nextStep = () => {
    if (stepIndex.value < questions.value.length - 1) {
        stepIndex.value += 1;
    }
};

const previousStep = () => {
    if (stepIndex.value > 0) {
        stepIndex.value -= 1;
    }
};

const toggleMultiChoice = (questionId, option) => {
    const draft = answerDrafts[String(questionId)];
    if (!draft) {
        return;
    }

    const set = new Set(Array.isArray(draft.selected_options) ? draft.selected_options : []);
    if (set.has(option)) {
        set.delete(option);
    } else {
        set.add(option);
    }

    draft.selected_options = Array.from(set);
};

const submitBatch = async () => {
    submitting.value = true;
    error.value = '';

    try {
        const answers = questions.value.map((question) => {
            const questionId = String(question?.question_id ?? '');
            const draft = answerDrafts[questionId] ?? {};

            return {
                question_id: questionId,
                answer_type: draft.answer_type ?? question.answer_type ?? 'freetext',
                answer_text: draft.answer_text ?? '',
                selected_option: draft.selected_option ?? '',
                selected_options: Array.isArray(draft.selected_options) ? draft.selected_options : [],
            };
        });

        const { data } = await axios.post(`/agent/api/v1/workflow-interrogator/sessions/${props.sessionId}/submit-batch`, { answers });
        Object.keys(answerDrafts).forEach((key) => delete answerDrafts[key]);
        stepIndex.value = 0;
        applySession(data?.data ?? null);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to submit question batch.';
    } finally {
        submitting.value = false;
    }
};

const generatePlan = async () => {
    generatingPlan.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(`/agent/api/v1/workflow-interrogator/sessions/${props.sessionId}/generate-plan`);
        applySession(data?.data ?? null);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to generate action plan.';
    } finally {
        generatingPlan.value = false;
    }
};

const summaryMarkdown = computed(() => String(session.value?.summary_json?.summary_markdown ?? '').trim());
const actionPlanMarkdown = computed(() => String(session.value?.action_plan_json?.action_plan_markdown ?? '').trim());

onMounted(async () => {
    await load();
    subscribeRealtime();
});
onUnmounted(() => {
    unsubscribeRealtime();
});
</script>

<template>
    <AppLayout title="Workflow Interrogator Session">
        <Head title="Workflow Interrogator Session" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Waypoints class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Workflow Interrogator Session</h2>
                        <HelpHint
                            ui-key="workflow-interrogator.wizard"
                            short-text="Batch-driven interrogation flow that keeps asking until ambiguity is materially exhausted."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.workflow-interrogator.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <div v-if="loading" class="grid grid-cols-1 gap-4 xl:grid-cols-[280px_minmax(0,1fr)_320px]">
                    <Card v-for="index in 3" :key="index">
                        <CardContent class="space-y-3 pt-6">
                            <Skeleton class="h-5 w-1/2" />
                            <Skeleton class="h-4 w-full" />
                            <Skeleton class="h-4 w-4/5" />
                            <Skeleton class="h-20 w-full" />
                        </CardContent>
                    </Card>
                </div>

                <div v-else-if="session" class="grid grid-cols-1 gap-4 xl:grid-cols-[280px_minmax(0,1fr)_320px]">
                    <Card class="h-fit">
                        <CardHeader>
                            <CardTitle>{{ session.workflow_title }}</CardTitle>
                            <CardDescription>{{ session.company_name }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status</p>
                                <p class="mt-1 font-medium text-foreground">{{ session.status }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Round</p>
                                <p class="mt-1 text-foreground">{{ session.current_round }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Mode</p>
                                <p class="mt-1 text-foreground">{{ session.interrogation_mode }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Project directory</p>
                                <p class="mt-1 break-all text-muted-foreground">{{ session.project_directory }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Updated</p>
                                <p class="mt-1 text-muted-foreground">{{ formatDateTime(session.updated_at) }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Session context files</p>
                                <div class="mt-2 space-y-2">
                                    <div
                                        v-for="attachment in attachments"
                                        :key="attachment.id"
                                        class="rounded-lg border border-border bg-muted/20 px-3 py-2"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-foreground">{{ attachment.filename }}</p>
                                                <p class="text-xs text-muted-foreground">{{ attachment.mime_type }} · {{ formatAttachmentSize(attachment.size_bytes) }}</p>
                                            </div>
                                            <a
                                                v-if="attachment.download_url"
                                                :href="attachment.download_url"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="shrink-0 text-xs font-medium text-primary hover:underline"
                                            >
                                                Open
                                            </a>
                                        </div>
                                    </div>
                                    <p v-if="attachments.length === 0" class="text-muted-foreground">No uploaded files or images.</p>
                                </div>
                            </div>
                            <div v-if="questions.length > 0" class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Current batch</p>
                                <div
                                    v-for="(question, index) in questions"
                                    :key="question.question_id"
                                    class="rounded-lg border px-3 py-2 text-sm"
                                    :class="index === stepIndex ? 'border-primary bg-primary/5' : 'border-border bg-background'"
                                >
                                    <p class="font-medium text-foreground">{{ index + 1 }}. {{ question.category || 'Question' }}</p>
                                    <p class="mt-1 line-clamp-2 text-muted-foreground">{{ question.prompt }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="space-y-4">
                        <Card v-if="session.status === 'setup'">
                            <CardHeader>
                                <CardTitle>Ready to start interrogation</CardTitle>
                                <CardDescription>
                                    The system will generate the first finite batch, then continue in new rounds until ambiguity is materially closed.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="rounded-lg border border-border bg-muted/20 p-4">
                                    <p class="text-sm font-medium text-foreground">Workflow brief</p>
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">{{ session.workflow_brief }}</p>
                                </div>
                                <Button :disabled="starting" @click="startInterrogation">
                                    <Play class="h-4 w-4" />
                                    {{ starting ? 'Generating first batch...' : 'Start interrogation' }}
                                </Button>
                            </CardContent>
                        </Card>

                        <Card v-else-if="isProcessing">
                            <CardHeader>
                                <CardTitle>{{ processingLabel }}</CardTitle>
                                <CardDescription>
                                    This work is queued onto the interrogation worker, so the page does not block while the runner executes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center gap-3 rounded-lg border border-border bg-muted/20 px-4 py-4 text-sm text-muted-foreground">
                                    <LoaderCircle class="h-4 w-4 animate-spin" />
                                    <span>
                                        {{ processing?.state === 'queued' ? 'Waiting for an interrogation queue worker.' : 'Runner execution in progress.' }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <Card v-else-if="hasFailedState">
                            <CardHeader>
                                <CardTitle>Interrogation failed</CardTitle>
                                <CardDescription>
                                    The session stopped before it could produce a usable batch or summary. The latest recorded failure is shown below.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm">
                                    <p class="font-medium text-destructive">
                                        {{ session.error_summary || 'Workflow Interrogator failed without a recorded summary.' }}
                                    </p>
                                    <p v-if="session.error_code" class="mt-2 text-xs text-destructive/90">
                                        Code: {{ session.error_code }}
                                    </p>
                                    <p v-if="latestErrorEvent?.payload?.message" class="mt-3 whitespace-pre-wrap text-xs text-destructive/90">
                                        {{ latestErrorEvent.payload.message }}
                                    </p>
                                </div>

                                <div class="rounded-lg border border-border bg-muted/20 p-4 text-sm text-muted-foreground">
                                    Retry will queue a fresh round generation for this session using the same runner and current context.
                                </div>

                                <div class="flex justify-end">
                                    <Button :disabled="starting" @click="startInterrogation">
                                        <LoaderCircle v-if="starting" class="h-4 w-4 animate-spin" />
                                        <Play v-else class="h-4 w-4" />
                                        {{ starting ? 'Retrying interrogation...' : 'Retry interrogation' }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card v-else-if="questions.length > 0">
                            <CardHeader>
                                <CardTitle>Round {{ activeBatch?.round || session.current_round }}</CardTitle>
                                <CardDescription>
                                    This round was generated as a finite batch. The overall interrogation remains open until ambiguity is exhausted.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-5">
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/20 px-4 py-3 text-sm">
                                    <div>
                                        <p class="font-medium text-foreground">Question {{ stepIndex + 1 }} of {{ questions.length }}</p>
                                        <p class="text-muted-foreground">{{ currentQuestion?.category || 'General clarification' }}</p>
                                    </div>
                                    <div class="inline-flex items-center gap-2 text-muted-foreground">
                                        <BrainCircuit class="h-4 w-4" />
                                        {{ currentQuestion?.answer_type || 'freetext' }}
                                    </div>
                                </div>

                                <div v-if="currentQuestion" class="space-y-4">
                                    <div class="rounded-lg border border-border bg-background p-4">
                                        <MarkdownRenderer
                                            :markdown="String(currentQuestion.prompt ?? '')"
                                            class="prose prose-sm max-w-none text-foreground dark:prose-invert prose-headings:text-foreground prose-strong:text-foreground"
                                        />
                                        <p v-if="currentQuestion.rationale" class="mt-3 text-xs text-muted-foreground">
                                            Why this is being asked: {{ currentQuestion.rationale }}
                                        </p>
                                    </div>

                                    <div v-if="currentQuestion.answer_type === 'choice'" class="space-y-2">
                                        <label
                                            v-for="option in currentQuestion.options || []"
                                            :key="option"
                                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-border px-3 py-3 text-sm transition-colors hover:border-primary/40 hover:bg-primary/5"
                                        >
                                            <input
                                                v-model="answerDrafts[currentQuestion.question_id].selected_option"
                                                type="radio"
                                                class="h-4 w-4"
                                                :name="`question-${currentQuestion.question_id}`"
                                                :value="option"
                                            >
                                            <span class="text-foreground">{{ option }}</span>
                                        </label>
                                    </div>

                                    <div v-else-if="currentQuestion.answer_type === 'multi_choice'" class="space-y-2">
                                        <label
                                            v-for="option in currentQuestion.options || []"
                                            :key="option"
                                            class="flex cursor-pointer items-center gap-3 rounded-lg border border-border px-3 py-3 text-sm transition-colors hover:border-primary/40 hover:bg-primary/5"
                                        >
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4"
                                                :checked="answerDrafts[currentQuestion.question_id].selected_options.includes(option)"
                                                @change="toggleMultiChoice(currentQuestion.question_id, option)"
                                            >
                                            <span class="text-foreground">{{ option }}</span>
                                        </label>
                                    </div>

                                    <div v-else>
                                        <label class="block text-sm font-medium text-foreground">Answer</label>
                                        <Textarea
                                            v-model="answerDrafts[currentQuestion.question_id].answer_text"
                                            class="mt-2 min-h-40"
                                            placeholder="Answer directly and operationally. If something is unknown, say that explicitly."
                                        />
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-3">
                                    <Button variant="outline" :disabled="stepIndex === 0" @click="previousStep">
                                        Previous
                                    </Button>
                                    <div class="flex items-center gap-3">
                                        <Button
                                            v-if="stepIndex < questions.length - 1"
                                            :disabled="!currentAnswerValid"
                                            @click="nextStep"
                                        >
                                            Next
                                            <ArrowRight class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            v-else
                                            :disabled="!currentAnswerValid || submitting"
                                            @click="submitBatch"
                                        >
                                            <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
                                            <CheckCircle2 v-else class="h-4 w-4" />
                                            {{ submitting ? 'Submitting batch...' : 'Submit batch' }}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card v-else-if="hasSummary">
                            <CardHeader>
                                <CardTitle>Structured findings summary</CardTitle>
                                <CardDescription>
                                    The interrogation loop has reached summary readiness. Generate the action plan once the findings look complete.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <MarkdownRenderer
                                    v-if="summaryMarkdown !== ''"
                                    :markdown="summaryMarkdown"
                                    class="summary-markdown prose prose-sm max-w-none rounded-lg border border-border bg-muted/20 p-4 text-foreground dark:prose-invert prose-headings:text-foreground prose-strong:text-foreground prose-th:text-foreground prose-td:text-foreground"
                                />
                                <div v-else class="rounded-lg border border-border bg-muted/20 p-4 text-sm text-muted-foreground">
                                    Structured summary payload exists, but no markdown body was returned.
                                </div>

                                <div class="flex justify-end" v-if="!hasActionPlan">
                                    <Button :disabled="generatingPlan" @click="generatePlan">
                                        <LoaderCircle v-if="generatingPlan" class="h-4 w-4 animate-spin" />
                                        <BrainCircuit v-else class="h-4 w-4" />
                                        {{ generatingPlan ? 'Generating action plan...' : 'Generate action plan' }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card v-if="hasActionPlan">
                            <CardHeader>
                                <CardTitle>Action plan</CardTitle>
                                <CardDescription>
                                    Recommended implementation approach, pilot wedge, and tooling direction.
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <MarkdownRenderer
                                    v-if="actionPlanMarkdown !== ''"
                                    :markdown="actionPlanMarkdown"
                                    class="summary-markdown prose prose-sm max-w-none rounded-lg border border-border bg-muted/20 p-4 text-foreground dark:prose-invert prose-headings:text-foreground prose-strong:text-foreground prose-th:text-foreground prose-td:text-foreground"
                                />
                                <div v-else class="rounded-lg border border-border bg-muted/20 p-4 text-sm text-muted-foreground">
                                    Action plan payload exists, but no markdown body was returned.
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card class="h-fit">
                        <CardHeader>
                            <CardTitle>Ambiguity state</CardTitle>
                            <CardDescription>
                                Why the system continued, or why it believes the current interrogation can close.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4 text-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Closure reason</p>
                                <p class="mt-1 text-muted-foreground">{{ ambiguityReport.closure_reason || 'No closure reason recorded yet.' }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Open ambiguities</p>
                                <ul class="mt-2 space-y-1 text-muted-foreground">
                                    <li v-for="item in ambiguityReport.open_ambiguities || []" :key="item">• {{ item }}</li>
                                    <li v-if="!(ambiguityReport.open_ambiguities || []).length">None recorded.</li>
                                </ul>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Coverage gaps</p>
                                <ul class="mt-2 space-y-1 text-muted-foreground">
                                    <li v-for="item in ambiguityReport.coverage_gaps || []" :key="item">• {{ item }}</li>
                                    <li v-if="!(ambiguityReport.coverage_gaps || []).length">None recorded.</li>
                                </ul>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Contradictions</p>
                                <ul class="mt-2 space-y-1 text-muted-foreground">
                                    <li v-for="item in ambiguityReport.contradictions || []" :key="item">• {{ item }}</li>
                                    <li v-if="!(ambiguityReport.contradictions || []).length">None recorded.</li>
                                </ul>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Recent events</p>
                                <div class="mt-2 space-y-2">
                                    <div
                                        v-for="event in session.events || []"
                                        :key="event.id"
                                        class="rounded-lg border border-border bg-muted/20 px-3 py-2"
                                    >
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="font-medium text-foreground">{{ event.event_type }}</span>
                                            <span class="text-xs text-muted-foreground">{{ formatDateTime(event.created_at) }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-muted-foreground">Sequence {{ event.sequence }}</p>
                                    </div>
                                    <p v-if="!(session.events || []).length" class="text-muted-foreground">No events recorded yet.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.summary-markdown :deep(table) {
    width: 100%;
}
</style>
