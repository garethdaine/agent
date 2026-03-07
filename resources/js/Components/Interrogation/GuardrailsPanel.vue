<script setup>
import { computed } from 'vue';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    runEvents: {
        type: Array,
        default: () => [],
    },
    runMetadata: {
        type: Object,
        default: () => ({}),
    },
    activeTask: {
        type: Object,
        default: null,
    },
});

// ── Payload Scanner ────────────────────────────────────────────
// Scans all run event payloads for signals. This is the primary
// detection mechanism — we look at what the AI actually outputs.

const allPayloads = computed(() => {
    return props.runEvents
        .map((e) => String(e?.payload ?? '').trim())
        .filter((p) => p.length > 0);
});

// Concatenated output — joins all payloads into a single string so we
// can detect patterns that span streaming chunk boundaries (e.g. a
// "### SITUATION" header split across two events).
const concatenatedOutput = computed(() => allPayloads.value.join(''));

// ── 1. STAR Methodology ────────────────────────────────────────
// The STAR preamble is injected into every task prompt. The AI should
// output ### SITUATION / ### TASK / ### ACTION / ### RESULT sections.
//
// Detection uses THREE sources (in priority order):
//   1. reasoning_step field (set by ReasoningStepParser on backend)
//   2. Concatenated output scan for exact markdown headers
//   3. Softer signal patterns (the AI might paraphrase headers)
//
// Source 2 fixes the streaming-chunk-split problem: getIncrementalOutput()
// can split "### SITUATION" across two chunks, causing the backend parser
// to miss it entirely (reasoning_step stays null for all events).

const STAR_STEPS = ['situation', 'task', 'action', 'result'];

// Exact headers in concatenated output (handles chunk splits)
const STAR_HEADER_GLOBAL = /^#{1,4}\s+(SITUATION|TASK|ACTION|RESULT)\b/gim;

// Softer patterns — the AI may use these instead of exact headers
const STAR_SOFT_PATTERNS = {
    situation: /\b(?:current\s+(?:state|situation)|what\s+exists|what\s+(?:we\s+)?have\s+(?:now|currently)|existing\s+(?:state|code|implementation))\b/i,
    task: /\b(?:end[- ]state|must\s+be\s+true|goal\s+is\s+to|objective|what\s+(?:needs?|must)\s+(?:to\s+)?(?:be\s+)?(?:done|achieved|true|completed))\b/i,
    action: /\b(?:steps?\s+(?:to|I(?:'ll| will))|I(?:'ll| will)\s+(?:first|start|begin)|approach|implementation\s+steps?|here(?:'s| is)\s+(?:my|the)\s+plan)\b/i,
    result: /\b(?:verif(?:y|ied|ying|ication)\s+(?:by|that|completion)|evidence\s+(?:of|proves?)|(?:how\s+(?:to|I(?:'ll| will)))\s+confirm|success\s+criteria)\b/i,
};

const starCounts = computed(() => {
    const counts = { situation: 0, task: 0, action: 0, result: 0 };
    const detected = new Set();

    // Source 1: reasoning_step field from backend ReasoningStepParser
    for (const event of props.runEvents) {
        const step = String(event?.reasoning_step ?? '').trim().toLowerCase();
        if (step !== '' && step in counts) {
            counts[step]++;
            detected.add(step);
        }
    }

    // Source 2: Scan concatenated output for exact STAR headers
    // This catches headers that were split across streaming chunks.
    const fullText = concatenatedOutput.value;
    if (fullText.length > 0) {
        let match;
        STAR_HEADER_GLOBAL.lastIndex = 0;
        while ((match = STAR_HEADER_GLOBAL.exec(fullText)) !== null) {
            const key = match[1].toLowerCase();
            if (key in counts && !detected.has(key)) {
                counts[key]++;
                detected.add(key);
            }
        }
    }

    // Source 3: Soft pattern detection on concatenated output
    // Only fills gaps — if a step wasn't detected by sources 1 or 2.
    if (fullText.length > 0) {
        for (const step of STAR_STEPS) {
            if (!detected.has(step) && STAR_SOFT_PATTERNS[step].test(fullText)) {
                counts[step]++;
                detected.add(step);
            }
        }
    }

    return counts;
});

const starTotal = computed(() => Object.values(starCounts.value).reduce((sum, v) => sum + v, 0));

const starCompleteness = computed(() => {
    const present = STAR_STEPS.filter((s) => starCounts.value[s] > 0).length;
    return Math.round((present / STAR_STEPS.length) * 100);
});

const starStatus = computed(() => {
    if (starTotal.value === 0) return 'idle';
    return starCompleteness.value === 100 ? 'complete' : 'active';
});

// ── 2. TDD Tracking ────────────────────────────────────────────
// Scan payloads for test execution commands, test file references,
// and test result output.

const TEST_RUN_PATTERN = /\b(?:php\s+artisan\s+test|phpunit|pest|npm\s+(?:run\s+)?test|vitest|jest)\b/i;
const TEST_FILE_PATTERN = /(?:Test\.php|\.test\.[jt]sx?|\.spec\.[jt]sx?|_test\.go|_test\.py)\b/i;
const TEST_RESULT_PATTERN = /\b(?:Tests?:\s*\d+|PASS(?:ED)?|FAIL(?:ED)?|✓|✗|passed|failed|\d+\s+assertions?)\b/i;

const tddSignals = computed(() => {
    let testRuns = 0;
    let testFileRefs = 0;
    let testResults = 0;

    for (const payload of allPayloads.value) {
        if (TEST_RUN_PATTERN.test(payload)) testRuns++;
        if (TEST_FILE_PATTERN.test(payload)) testFileRefs++;
        if (TEST_RESULT_PATTERN.test(payload)) testResults++;
    }

    return { testRuns, testFileRefs, testResults };
});

const tddScore = computed(() => {
    const { testRuns, testFileRefs, testResults } = tddSignals.value;
    if (testRuns === 0 && testFileRefs === 0 && testResults === 0) return 0;
    const signals = (testRuns > 0 ? 1 : 0) + (testFileRefs > 0 ? 1 : 0) + (testResults > 0 ? 1 : 0);
    return Math.round((signals / 3) * 100);
});

const tddStatus = computed(() => {
    if (tddScore.value === 0) return 'idle';
    return tddScore.value >= 66 ? 'active' : 'partial';
});

// ── 3. Planning ────────────────────────────────────────────────
// Scan payloads for planning artifacts: plan headers, numbered
// step sequences, implementation approach language.

const PLAN_HEADER_PATTERN = /^#{1,4}\s+(?:Plan|Planning|Implementation Plan|Approach|Strategy)\b/im;
const PLAN_STEPS_PATTERN = /(?:^|\n)\s*(?:Step\s+\d+|Phase\s+\d+|\d+\.\s+\w)/im;
const PLAN_LANGUAGE_PATTERN = /\b(?:(?:I(?:'ll|'ll| will| need to)\s+)?(?:first|then|next|finally|start by|begin with|approach)|plan(?:ning)?\s+(?:to|the)|implementation\s+(?:plan|steps|approach)|before\s+(?:implementing|coding|writing)|let(?:'s| me)\s+(?:plan|outline|break (?:this|it) down))\b/i;

const planningSignals = computed(() => {
    let planHeaders = 0;
    let numberedSteps = 0;
    let planLanguage = 0;

    // Per-event scanning
    for (const payload of allPayloads.value) {
        if (PLAN_HEADER_PATTERN.test(payload)) planHeaders++;
        if (PLAN_STEPS_PATTERN.test(payload)) numberedSteps++;
        if (PLAN_LANGUAGE_PATTERN.test(payload)) planLanguage++;
    }

    // Concatenated fallback — catches headers split across chunks
    if (planHeaders === 0) {
        const full = concatenatedOutput.value;
        if (PLAN_HEADER_PATTERN.test(full)) planHeaders++;
        if (numberedSteps === 0 && PLAN_STEPS_PATTERN.test(full)) numberedSteps++;
        if (planLanguage === 0 && PLAN_LANGUAGE_PATTERN.test(full)) planLanguage++;
    }

    // Also check task metadata for policy-required planning
    let policyRequired = 0;
    for (const task of props.tasks) {
        if (task?.metadata_json?.plan_required) policyRequired++;
    }

    return { planHeaders, numberedSteps, planLanguage, policyRequired };
});

const planningScore = computed(() => {
    const { planHeaders, numberedSteps, planLanguage, policyRequired } = planningSignals.value;
    const hasAnySignal = planHeaders > 0 || numberedSteps > 0 || planLanguage > 0;
    if (!hasAnySignal && policyRequired === 0) return 0;

    // Score based on how many different planning signals detected
    const signals = (planHeaders > 0 ? 1 : 0) + (numberedSteps > 0 ? 1 : 0) + (planLanguage > 0 ? 1 : 0);
    return Math.round((Math.max(signals, hasAnySignal ? 1 : 0) / 3) * 100);
});

const planningStatus = computed(() => {
    if (planningScore.value === 0) return 'idle';
    return planningScore.value >= 66 ? 'active' : 'partial';
});

// ── 4. Safeguards (Complexity, Gates, Redactions, Noise, Assumptions) ──
// Combined metadata-based + output-based detection.

const ASSUMPTION_PATTERN = /\b(?:I(?:'ll| will)?\s*assume|assuming|assumption|I(?:'ll| will)?\s*presume)\b/i;
const COMPLEXITY_LANGUAGE = /\b(?:edge case|corner case|boundary|constraint|caveat|limitation|trade-?off|risk|careful(?:ly)?)\b/i;

const safeguardStats = computed(() => {
    const complexities = new Set();
    let policyEvaluated = 0;
    let gatesEvaluated = 0;
    let blockedCount = 0;
    let assumptionsDetected = 0;
    let complexityLanguage = 0;

    for (const task of props.tasks) {
        const meta = task?.metadata_json ?? {};
        if (meta.workflow_policy_version) policyEvaluated++;
        if (meta.complexity_classification) complexities.add(meta.complexity_classification);
        if (Array.isArray(meta.compliance_gates)) gatesEvaluated += meta.compliance_gates.length;
        if (meta.compliance_block_reason) blockedCount++;
    }

    // Scan payloads for assumption/complexity awareness
    for (const payload of allPayloads.value) {
        if (ASSUMPTION_PATTERN.test(payload)) assumptionsDetected++;
        if (COMPLEXITY_LANGUAGE.test(payload)) complexityLanguage++;
    }

    const redactions = Number(props.runMetadata?.redaction_count ?? 0);
    const noiseSuppressed = Number(props.runMetadata?.noise_suppressed_chunks ?? 0);

    return {
        complexities: Array.from(complexities),
        policyEvaluated,
        gatesEvaluated,
        blockedCount,
        redactions,
        noiseSuppressed,
        assumptionsDetected,
        complexityLanguage,
    };
});

const safeguardScore = computed(() => {
    const s = safeguardStats.value;
    const hasAny = s.policyEvaluated > 0 || s.redactions > 0 || s.noiseSuppressed > 0
        || s.assumptionsDetected > 0 || s.complexityLanguage > 0;
    if (!hasAny) return 0;

    // Score based on breadth of safeguard signals
    const signals = (s.policyEvaluated > 0 ? 1 : 0) + (s.noiseSuppressed > 0 ? 1 : 0)
        + (s.redactions > 0 ? 1 : 0) + (s.assumptionsDetected > 0 || s.complexityLanguage > 0 ? 1 : 0);
    return Math.round((signals / 4) * 100);
});

const safeguardStatus = computed(() => {
    if (safeguardScore.value === 0) return 'idle';
    if (safeguardStats.value.blockedCount > 0) return 'blocked';
    return safeguardScore.value >= 50 ? 'active' : 'partial';
});

// ── 5. Verification ────────────────────────────────────────────
// Scan payloads for verification activity: explicit verification
// language, confirmed-working signals, test pass confirmations.

const VERIFY_LANGUAGE = /\b(?:verif(?:y|ied|ying|ication)|confirm(?:ed|ing)?|validated?|let(?:'s| me)\s+(?:verify|confirm|check|test|validate)|works?\s+(?:as expected|correctly|properly)|confirmed?\s+working|successfully\s+(?:tested|verified|validated))\b/i;
const VERIFY_TEST_PASS = /\b(?:all\s+(?:\d+\s+)?tests?\s+pass(?:ed|ing)?|tests?\s+(?:are\s+)?passing|build\s+succeed(?:ed|s)?|no\s+(?:errors?|failures?)|0\s+failures?)\b/i;
const VERIFY_REVIEW = /\b(?:(?:code|manual)\s+review|reviewed?\s+the\s+(?:code|changes?|output)|looks?\s+(?:good|correct)|lgtm)\b/i;

const verificationSignals = computed(() => {
    let verifyLanguage = 0;
    let testPassConfirm = 0;
    let reviewSignals = 0;

    for (const payload of allPayloads.value) {
        if (VERIFY_LANGUAGE.test(payload)) verifyLanguage++;
        if (VERIFY_TEST_PASS.test(payload)) testPassConfirm++;
        if (VERIFY_REVIEW.test(payload)) reviewSignals++;
    }

    // Also check task metadata for policy-required verification
    let policyRequired = 0;
    let evidenceCount = 0;
    for (const task of props.tasks) {
        const meta = task?.metadata_json ?? {};
        if (meta.verification_required) policyRequired++;
        if (meta.verification_evidence) evidenceCount++;
    }

    return { verifyLanguage, testPassConfirm, reviewSignals, policyRequired, evidenceCount };
});

const verificationScore = computed(() => {
    const { verifyLanguage, testPassConfirm, reviewSignals } = verificationSignals.value;
    const hasAny = verifyLanguage > 0 || testPassConfirm > 0 || reviewSignals > 0;
    if (!hasAny) return 0;

    const signals = (verifyLanguage > 0 ? 1 : 0) + (testPassConfirm > 0 ? 1 : 0) + (reviewSignals > 0 ? 1 : 0);
    return Math.round((signals / 3) * 100);
});

const verificationStatus = computed(() => {
    if (verificationScore.value === 0) return 'idle';
    return verificationScore.value >= 66 ? 'active' : 'partial';
});

// ── Status Helpers ──────────────────────────────────────────────

const statusIcon = (status) => {
    const map = { idle: '○', active: '◉', partial: '◔', complete: '●', blocked: '⊘' };
    return map[status] ?? '○';
};

const statusClass = (status) => {
    const map = {
        idle: 'text-muted-foreground',
        active: 'text-primary',
        partial: 'text-warning',
        complete: 'text-success',
        blocked: 'text-destructive',
    };
    return map[status] ?? 'text-muted-foreground';
};

const statusBadgeClass = (status) => {
    const map = {
        idle: 'bg-muted text-muted-foreground',
        active: 'bg-primary/10 text-primary',
        partial: 'bg-warning/10 text-warning',
        complete: 'bg-success/10 text-success',
        blocked: 'bg-destructive/10 text-destructive',
    };
    return map[status] ?? 'bg-muted text-muted-foreground';
};

const statusLabel = (status) => {
    const map = { idle: 'Waiting', active: 'Active', partial: 'Partial', complete: 'Complete', blocked: 'Blocked' };
    return map[status] ?? 'Waiting';
};

// ── Overall Health ──────────────────────────────────────────────
// ALWAYS includes all 5 categories. Idle categories score 0,
// so health only hits 100% when ALL guardrails show real activity.

const CATEGORIES = [
    { key: 'star', weight: 3 },
    { key: 'tdd', weight: 2 },
    { key: 'planning', weight: 2 },
    { key: 'safeguards', weight: 2 },
    { key: 'verification', weight: 2 },
];

const categoryScores = computed(() => ({
    star: starCompleteness.value,
    tdd: tddScore.value,
    planning: planningScore.value,
    safeguards: safeguardScore.value,
    verification: verificationScore.value,
}));

const overallScore = computed(() => {
    const scores = categoryScores.value;
    const totalWeight = CATEGORIES.reduce((sum, c) => sum + c.weight, 0);
    const weightedSum = CATEGORIES.reduce((sum, c) => sum + scores[c.key] * c.weight, 0);
    return totalWeight > 0 ? Math.round(weightedSum / totalWeight) : 0;
});

const overallBarColor = computed(() => {
    if (overallScore.value === 0) return 'bg-muted';
    if (overallScore.value < 40) return 'bg-warning';
    if (overallScore.value < 70) return 'bg-primary';
    return 'bg-success';
});
</script>

<template>
    <div class="space-y-3 rounded-lg border border-border bg-card p-4">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Guardrails</h3>

        <!-- Overall Health Bar -->
        <div>
            <div class="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                <span>Health</span>
                <span>{{ overallScore }}%</span>
            </div>
            <div class="h-1.5 rounded bg-muted/80">
                <div
                    class="h-1.5 rounded transition-all duration-500"
                    :class="overallBarColor"
                    :style="{ width: `${Math.max(0, Math.min(100, overallScore))}%` }"
                />
            </div>
        </div>

        <!-- Guardrail Rows -->
        <dl class="space-y-2.5 text-sm">
            <!-- 1. STAR Methodology -->
            <div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-1.5 text-muted-foreground">
                        <span :class="statusClass(starStatus)">{{ statusIcon(starStatus) }}</span>
                        STAR
                    </dt>
                    <dd class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusBadgeClass(starStatus)">
                        {{ statusLabel(starStatus) }}
                    </dd>
                </div>
                <div v-if="starTotal > 0" class="mt-1 flex gap-1.5 pl-5">
                    <span
                        v-for="step in STAR_STEPS"
                        :key="step"
                        class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium uppercase"
                        :class="starCounts[step] > 0 ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                    >
                        {{ step[0] }}
                        <span v-if="starCounts[step] > 0" class="ml-0.5 tabular-nums">{{ starCounts[step] }}</span>
                    </span>
                </div>
            </div>

            <!-- 2. TDD -->
            <div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-1.5 text-muted-foreground">
                        <span :class="statusClass(tddStatus)">{{ statusIcon(tddStatus) }}</span>
                        TDD
                    </dt>
                    <dd class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusBadgeClass(tddStatus)">
                        {{ statusLabel(tddStatus) }}
                    </dd>
                </div>
                <div v-if="tddSignals.testRuns > 0 || tddSignals.testFileRefs > 0 || tddSignals.testResults > 0" class="mt-1 flex gap-2 pl-5 text-[10px] text-muted-foreground">
                    <span v-if="tddSignals.testRuns > 0">{{ tddSignals.testRuns }} run{{ tddSignals.testRuns !== 1 ? 's' : '' }}</span>
                    <span v-if="tddSignals.testFileRefs > 0">{{ tddSignals.testFileRefs }} file{{ tddSignals.testFileRefs !== 1 ? 's' : '' }}</span>
                    <span v-if="tddSignals.testResults > 0">{{ tddSignals.testResults }} result{{ tddSignals.testResults !== 1 ? 's' : '' }}</span>
                </div>
            </div>

            <!-- 3. Planning -->
            <div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-1.5 text-muted-foreground">
                        <span :class="statusClass(planningStatus)">{{ statusIcon(planningStatus) }}</span>
                        Planning
                    </dt>
                    <dd class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusBadgeClass(planningStatus)">
                        {{ statusLabel(planningStatus) }}
                    </dd>
                </div>
                <div v-if="planningSignals.planHeaders > 0 || planningSignals.numberedSteps > 0 || planningSignals.planLanguage > 0" class="mt-1 flex gap-2 pl-5 text-[10px] text-muted-foreground">
                    <span v-if="planningSignals.planHeaders > 0">{{ planningSignals.planHeaders }} plan{{ planningSignals.planHeaders !== 1 ? 's' : '' }}</span>
                    <span v-if="planningSignals.numberedSteps > 0">{{ planningSignals.numberedSteps }} step{{ planningSignals.numberedSteps !== 1 ? 's' : '' }}</span>
                    <span v-if="planningSignals.planLanguage > 0">{{ planningSignals.planLanguage }} signal{{ planningSignals.planLanguage !== 1 ? 's' : '' }}</span>
                </div>
            </div>

            <!-- 4. Safeguards -->
            <div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-1.5 text-muted-foreground">
                        <span :class="statusClass(safeguardStatus)">{{ statusIcon(safeguardStatus) }}</span>
                        Safeguards
                    </dt>
                    <dd class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusBadgeClass(safeguardStatus)">
                        {{ statusLabel(safeguardStatus) }}
                    </dd>
                </div>
                <div v-if="safeguardScore > 0" class="mt-1 space-y-0.5 pl-5 text-[10px] text-muted-foreground">
                    <div v-if="safeguardStats.policyEvaluated > 0" class="flex justify-between">
                        <span>Policy checks</span>
                        <span class="tabular-nums">{{ safeguardStats.policyEvaluated }}</span>
                    </div>
                    <div v-if="safeguardStats.gatesEvaluated > 0" class="flex justify-between">
                        <span>Gates evaluated</span>
                        <span class="tabular-nums">{{ safeguardStats.gatesEvaluated }}</span>
                    </div>
                    <div v-if="safeguardStats.blockedCount > 0" class="flex justify-between text-destructive">
                        <span>Blocked</span>
                        <span class="tabular-nums">{{ safeguardStats.blockedCount }}</span>
                    </div>
                    <div v-if="safeguardStats.redactions > 0" class="flex justify-between">
                        <span>Redactions</span>
                        <span class="tabular-nums">{{ safeguardStats.redactions }}</span>
                    </div>
                    <div v-if="safeguardStats.noiseSuppressed > 0" class="flex justify-between">
                        <span>Noise filtered</span>
                        <span class="tabular-nums">{{ safeguardStats.noiseSuppressed }}</span>
                    </div>
                    <div v-if="safeguardStats.assumptionsDetected > 0" class="flex justify-between">
                        <span>Assumptions noted</span>
                        <span class="tabular-nums">{{ safeguardStats.assumptionsDetected }}</span>
                    </div>
                    <div v-if="safeguardStats.complexityLanguage > 0" class="flex justify-between">
                        <span>Complexity awareness</span>
                        <span class="tabular-nums">{{ safeguardStats.complexityLanguage }}</span>
                    </div>
                    <div v-if="safeguardStats.complexities.length > 0" class="mt-0.5 flex flex-wrap gap-1">
                        <span
                            v-for="c in safeguardStats.complexities"
                            :key="c"
                            class="rounded bg-muted px-1 py-0.5 text-[9px] uppercase text-muted-foreground"
                        >{{ c }}</span>
                    </div>
                </div>
            </div>

            <!-- 5. Verification -->
            <div>
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-1.5 text-muted-foreground">
                        <span :class="statusClass(verificationStatus)">{{ statusIcon(verificationStatus) }}</span>
                        Verification
                    </dt>
                    <dd class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusBadgeClass(verificationStatus)">
                        {{ statusLabel(verificationStatus) }}
                    </dd>
                </div>
                <div v-if="verificationScore > 0" class="mt-1 flex gap-2 pl-5 text-[10px] text-muted-foreground">
                    <span v-if="verificationSignals.verifyLanguage > 0">{{ verificationSignals.verifyLanguage }} check{{ verificationSignals.verifyLanguage !== 1 ? 's' : '' }}</span>
                    <span v-if="verificationSignals.testPassConfirm > 0">{{ verificationSignals.testPassConfirm }} pass{{ verificationSignals.testPassConfirm !== 1 ? 'es' : '' }}</span>
                    <span v-if="verificationSignals.reviewSignals > 0">{{ verificationSignals.reviewSignals }} review{{ verificationSignals.reviewSignals !== 1 ? 's' : '' }}</span>
                </div>
            </div>
        </dl>
    </div>
</template>
