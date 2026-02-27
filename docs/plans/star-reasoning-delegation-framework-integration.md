# Implementation Plan

Derived from discovery session 10.

# STAR Reasoning & Delegation Framework Integration - Implementation Plan

## Executive Summary

This plan implements STAR (Situation, Task, Action, Result) structured reasoning into Agent's job dispatch pipeline across four interdependent work items. The implementation adds goal articulation preambles to job execution, captures reasoning steps from runner output, enables targeted retry on structured failures, and computes trust scores for delegation profiles based on STAR performance metrics.

---

## Work Item 1: STAR Preamble Generator

### 1.1 StarPreambleGenerator Service

**File**: `app/Support/Agent/StarPreambleGenerator.php`

Create a new service that generates STAR reasoning preambles for job execution. The preamble is a hardcoded template that runners must complete before beginning work.

**Implementation**:
- Constructor accepts `LessonsManager` dependency
- Method `generate(AgentJob $job, AgentJobRun $run): string` returns the full preamble text
- Method `isEnabled(AgentJob $job): bool` checks global config and per-job override
- Method `assignAbGroup(AgentJob $job): ?string` returns 'control' or 'treatment' when A/B testing enabled, null otherwise
- The preamble template is a private const string within the class
- When lessons enrichment is enabled, append filtered lessons to the ACTION section guardrails

**Preamble Template** (hardcoded):
```markdown
## Pre-Execution Goal Articulation (Required)

Before beginning any work, complete each section below. Do not skip or abbreviate.

### SITUATION
What is the current state? What exists, what doesn't? What constraints apply?

### TASK
What specifically must be true when this work is complete? State the end-state, not the process.

### ACTION
What steps will achieve that end state? List them in order.

### RESULT
How will completion be verified? What evidence proves success?

---

Now proceed with the work described below.
```

**Lessons Enrichment**:
- Query `LessonsManager` with runner_type filter (see 1.2)
- Append relevant lessons as guardrails below the ACTION section template
- Format: `**Learned Guardrails:**\n- {lesson_content}`

### 1.2 LessonsManager Extension

**File**: `app/Support/Compliance/LessonsManager.php`

Extend the existing `queryLessons()` method to accept and filter by runner_type.

**Changes**:
- Add optional `?string $runnerType = null` parameter to `queryLessons()` signature
- When appending lessons via `appendLesson()`, include `runner_type` in the context array
- Update `formatEntry()` to write runner_type to the lesson entry
- Update `parseEntries()` to extract runner_type from lesson content
- Filter results by runner_type when provided (in addition to existing TaskCategory filter)

### 1.3 ExecuteAgentRunJob Integration

**File**: `app/Jobs/ExecuteAgentRunJob.php`

Integrate STAR preamble injection after compliance pre-run gate, before process spawn.

**Integration Point** (after line 124, before line 130):
1. Resolve `StarPreambleGenerator` from container
2. Call `isEnabled($run->job)` to check if STAR should apply
3. If enabled, call `assignAbGroup($run->job)` and store result in run metadata
4. Call `generate($run->job, $run)` to get preamble text
5. For custom runners: create temporary file combining preamble + original task markdown content, use temp path as `task_markdown_path` token
6. For claude/codex runners: same approach (prepend to task markdown)
7. Store `star_preamble_applied: true` in run metadata

**A/B Group Assignment**:
- When `agent.star_preamble.ab_test_enabled` is true:
  - Generate random float 0-100
  - If random < `ab_test_treatment_percent`, assign 'treatment' (apply STAR)
  - Otherwise assign 'control' (skip STAR preamble)
  - Store assignment in `AgentJobRun.star_ab_group` column
- When A/B testing disabled but STAR enabled: always apply (no group assignment)

### 1.4 Database Migration: agent_jobs STAR columns

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_star_columns_to_agent_jobs.php`

```php
Schema::table('agent_jobs', function (Blueprint $table) {
    $table->boolean('star_preamble_enabled')->nullable()->after('active_hours_config');
    $table->boolean('targeted_retry_enabled')->nullable()->after('star_preamble_enabled');
    $table->unsignedTinyInteger('max_retries')->nullable()->after('targeted_retry_enabled');
});
```

### 1.5 Database Migration: agent_job_runs star_ab_group

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_star_ab_group_to_agent_job_runs.php`

```php
Schema::table('agent_job_runs', function (Blueprint $table) {
    $table->string('star_ab_group', 16)->nullable()->after('metadata_json');
});
```

### 1.6 Model Updates

**File**: `app/Models/AgentJob.php`

Add casts for new nullable boolean columns:
```php
'star_preamble_enabled' => 'boolean',
'targeted_retry_enabled' => 'boolean',
'max_retries' => 'integer',
```

### 1.7 Configuration

**File**: `config/agent.php`

Add star_preamble configuration block after the existing `compliance` block:

```php
'star_preamble' => [
    'enabled' => (bool) env('AGENT_STAR_PREAMBLE_ENABLED', true),
    'ab_test_enabled' => (bool) env('AGENT_STAR_AB_TEST_ENABLED', false),
    'ab_test_treatment_percent' => (int) env('AGENT_STAR_AB_TEST_PERCENT', 50),
],
```

### 1.8 API Request Validation Updates

**File**: `app/Http/Requests/StoreAgentJobRequest.php` (or inline in controller)
**File**: `app/Http/Requests/UpdateAgentJobRequest.php` (or inline in controller)

Add validation rules for new fields:
```php
'star_preamble_enabled' => ['nullable', 'boolean'],
'targeted_retry_enabled' => ['nullable', 'boolean'],
'max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
```

### 1.9 Job Form UI Integration

**File**: `resources/js/Pages/Agent/Jobs/Create.vue`
**File**: `resources/js/Pages/Agent/Jobs/Edit.vue`

Add STAR configuration section to job forms:
- Checkbox: "Enable STAR preamble" (maps to `star_preamble_enabled`)
- Checkbox: "Enable targeted retry" (maps to `targeted_retry_enabled`)
- Number input: "Max retries" (maps to `max_retries`, shown when targeted retry enabled)
- Help text explaining STAR structured reasoning and retry behavior

**Location in UI**: Below existing "Advanced Settings" or as new collapsible "Reasoning & Retry" section

**Discoverability**: Section visible by default with informative tooltips explaining each option

### 1.10 Unit Tests

**File**: `tests/Unit/Support/Agent/StarPreambleGeneratorTest.php`

Test cases:
- `test_generates_preamble_with_hardcoded_template()`
- `test_respects_global_enabled_config()`
- `test_per_job_override_takes_precedence_over_global()`
- `test_ab_test_assignment_returns_treatment_or_control()`
- `test_ab_test_disabled_returns_null_group()`
- `test_lessons_enrichment_appends_filtered_lessons()`
- `test_lessons_filtered_by_runner_type()`

**File**: `tests/Unit/Support/Compliance/LessonsManagerRunnerTypeTest.php`

Test cases:
- `test_query_lessons_filters_by_runner_type()`
- `test_append_lesson_stores_runner_type_in_context()`
- `test_query_lessons_runner_type_filter_combined_with_category()`

### 1.11 Feature Tests

**File**: `tests/Feature/Jobs/ExecuteAgentRunJobStarPreambleTest.php`

Test cases:
- `test_star_preamble_prepended_to_task_markdown_when_enabled()`
- `test_star_preamble_skipped_when_disabled()`
- `test_ab_group_stored_in_run_when_ab_testing_enabled()`
- `test_star_metadata_recorded_in_run()`

---

## Work Item 2: Structured Reasoning Capture

### 2.1 ReasoningStepParser Service

**File**: `app/Support/Agent/ReasoningStepParser.php`

Create a stateful parser that tracks which STAR section is currently being output based on markdown headers in stdout.

**Implementation**:
- Constructor initializes state: `currentStep = null`, `completedSteps = []`, `stepContents = []`
- Method `parse(string $chunk): ?string` returns the reasoning step identifier if chunk belongs to a STAR section, null otherwise
- Method `getSummary(): array` returns the extracted reasoning summary with per-step content
- Method `reset(): void` clears parser state

**Parsing Logic**:
- Detect STAR section headers: `### SITUATION`, `### TASK`, `### ACTION`, `### RESULT`
- When a header is detected, set `currentStep` to that step
- Content after header until next header or `---` delimiter belongs to current step
- Headers are case-insensitive for matching
- Track truncated content (first 2000 chars per step for summary)

**Step Identifiers** (string, not enum):
- `situation`
- `task`
- `action`
- `result`

### 2.2 FailureModeClassifier Service

**File**: `app/Support/Agent/FailureModeClassifier.php`

Create a heuristic classifier that categorizes failure modes based on reasoning summary content.

**Implementation**:
- Method `classify(array $reasoningSummary, AgentJob $job): ?FailureModeHint`
- Returns DTO with `type` (1, 2, or 3), `confidence` (low/medium/high), `description`

**Classification Heuristics**:
- **Type 1 (Distance Heuristic)**: TASK step missing expected subjects from job context
  - Extract key nouns from job name/description
  - Check if TASK step content references these subjects
  - If missing, likely Type 1
- **Type 2 (Environmental Rationalization)**: ACTION contradicts TASK framing
  - Simple keyword overlap check between TASK and ACTION
  - If ACTION introduces concepts not in TASK, potential Type 2
- **Type 3 (Ironic Self-Awareness)**: TASK correct but ACTION diverges
  - TASK references expected subjects
  - ACTION doesn't follow through on TASK goals
  - Pattern: "I should X" in TASK but ACTION does Y

**Output**: Advisory only, stored in metadata, never blocks execution

### 2.3 FailureModeHint DTO

**File**: `app/Support/Agent/DTOs/FailureModeHint.php`

```php
readonly class FailureModeHint
{
    public function __construct(
        public int $type,          // 1, 2, or 3
        public string $confidence, // 'low', 'medium', 'high'
        public string $description,
        public ?string $suggestedLesson = null,
    ) {}
}
```

### 2.4 RunEventWriter Extension

**File**: `app/Support/Agent/RunEventWriter.php`

Extend to accept optional reasoning step tag when writing events.

**Changes**:
- Update `createEvent()` to accept optional `?string $reasoningStep = null`
- Pass reasoning step to event creation
- Update `appendOutput()` to accept optional reasoning step
- Update `appendChunk()` to pass through reasoning step

### 2.5 Database Migration: agent_run_events reasoning_step

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_reasoning_step_to_agent_run_events.php`

```php
Schema::table('agent_run_events', function (Blueprint $table) {
    $table->string('reasoning_step', 16)->nullable()->after('payload');
    $table->index(['agent_job_run_id', 'reasoning_step'], 'agent_run_events_run_reasoning_idx');
});
```

### 2.6 AgentRunEvent Model Update

**File**: `app/Models/AgentRunEvent.php`

No cast needed for nullable string column. Add constant for valid values:

```php
public const REASONING_STEPS = ['situation', 'task', 'action', 'result'];
```

### 2.7 ExecuteAgentRunJob Monitoring Loop Integration

**File**: `app/Jobs/ExecuteAgentRunJob.php`

Integrate reasoning step parsing into the monitoring loop (lines 196-289).

**Changes**:
1. Instantiate `ReasoningStepParser` before the monitoring loop
2. In the stdout processing block (lines 197-199), pipe chunk through parser:
   ```php
   $reasoningStep = $parser->parse($stdout);
   $writer->appendOutput('stdout', $stdout, $reasoningStep);
   ```
3. On run finalization, extract reasoning summary from parser and store in metadata:
   ```php
   $metadata['reasoning_summary'] = $parser->getSummary();
   ```
4. If run failed and has reasoning_summary, invoke `FailureModeClassifier`:
   ```php
   $hint = $classifier->classify($metadata['reasoning_summary'], $run->job);
   if ($hint) {
       $metadata['failure_mode_hint'] = [
           'type' => $hint->type,
           'confidence' => $hint->confidence,
           'description' => $hint->description,
           'suggested_lesson' => $hint->suggestedLesson,
       ];
   }
   ```

### 2.8 API Response Updates

**File**: `app/Http/Controllers/Api/V1/AgentRunController.php`

Update `events()` method to include reasoning_step in response (line 222-229):

```php
'reasoning_step' => $event->reasoning_step,
```

### 2.9 Monitor UI: Reasoning Step Indicators

**File**: `resources/js/Pages/Agent/Monitor/Index.vue`

Add visual indicators for reasoning step on tagged events.

**Changes to Event Rendering** (around line 673):
- Check if event has `reasoning_step` field
- When present, render a badge/pill showing the step: `[SITUATION]`, `[TASK]`, `[ACTION]`, `[RESULT]`
- Use distinct colors per step for quick visual scanning
- Badge appears before event payload in the event tail

**Visual Design**:
- SITUATION: blue badge
- TASK: green badge
- ACTION: amber badge
- RESULT: purple badge

**Location**: Inline with event prefix, before payload content

### 2.10 Monitor UI: Suggested Lesson Display

**File**: `resources/js/Pages/Agent/Monitor/Index.vue`

Add suggested lesson UI for failed runs with failure_mode_hint.

**Implementation**:
- When selected run has `metadata_json.failure_mode_hint.suggested_lesson`:
  - Show notification banner below event tail
  - Banner contains: failure mode description, suggested lesson text
  - Two buttons: "Add to Lessons" and "Dismiss"
  - "Add to Lessons" calls new API endpoint (see 2.11)
- State: `suggestedLessonPending`, `suggestedLessonDismissed`

**Visual Design**:
- Yellow/amber warning banner
- Clear call-to-action button styling
- Dismissible (stored in session state, not persisted)

### 2.11 API Endpoint: Confirm Suggested Lesson

**File**: `app/Http/Controllers/Api/V1/AgentRunController.php`

Add new method `confirmSuggestedLesson()`:

```php
public function confirmSuggestedLesson(Request $request, int $id, LessonsManager $lessons): JsonResponse
```

**Behavior**:
- Validate run belongs to user
- Extract suggested_lesson from run metadata
- Call `$lessons->appendLesson()` with source='failure_mode_classification'
- Update run metadata: `suggested_lesson_confirmed: true`, `suggested_lesson_confirmed_at`
- Return success response

**Route**: `POST /runs/{id}/confirm-lesson`

**File**: `routes/api.php`

Add route after line 54:
```php
Route::post('/runs/{id}/confirm-lesson', [AgentRunController::class, 'confirmSuggestedLesson'])->middleware('throttle:agent-mutations');
```

### 2.12 Unit Tests

**File**: `tests/Unit/Support/Agent/ReasoningStepParserTest.php`

Test cases:
- `test_parses_situation_header()`
- `test_parses_all_four_steps_in_sequence()`
- `test_handles_missing_steps_gracefully()`
- `test_handles_malformed_headers()`
- `test_summary_contains_truncated_content()`
- `test_reset_clears_state()`

**File**: `tests/Unit/Support/Agent/FailureModeClassifierTest.php`

Test cases:
- `test_classifies_type_1_missing_subject()`
- `test_classifies_type_2_action_contradicts_task()`
- `test_classifies_type_3_task_correct_action_diverges()`
- `test_returns_null_for_unclassifiable_failure()`
- `test_suggested_lesson_generated_for_classified_failures()`

### 2.13 Feature Tests

**File**: `tests/Feature/Jobs/ExecuteAgentRunJobReasoningCaptureTest.php`

Test cases:
- `test_reasoning_step_events_tagged_correctly()`
- `test_reasoning_summary_stored_in_metadata()`
- `test_failure_mode_hint_stored_on_failed_run()`
- `test_non_star_output_has_null_reasoning_step()`

---

## Work Item 3: Targeted Retry

### 3.1 TargetedRetryService

**File**: `app/Support/Agent/TargetedRetryService.php`

Create service that builds and dispatches targeted retry prompts.

**Implementation**:
- Constructor accepts dependencies: `RunStateTransitionService`, `DispatchDueService`
- Method `shouldRetry(AgentJobRun $failedRun): bool`
  - Check run has STAR metadata (reasoning_summary exists)
  - Check job has targeted_retry_enabled (or global config)
  - Check retry count < max_retries
  - Check job not on rate-limit hold
- Method `buildRetryPrompt(AgentJobRun $failedRun): string`
  - Include original SITUATION verbatim
  - Reference failed TASK step with quoted content
  - Provide corrective reframe based on failure_mode_hint
  - Ask runner to reformulate from TASK step forward
- Method `dispatchRetry(AgentJobRun $failedRun): AgentJobRun`
  - Create new AgentJobRun with modified task markdown
  - Set metadata: `retry_of_run_id`, `retry_count`, `retry_reasoning_target`
  - Queue via existing dispatch mechanism
  - Return new run

**Retry Prompt Template**:
```markdown
## Retry: Correcting Previous Attempt

Your previous attempt (Run {{original_run_id}}) failed verification.

### What Went Wrong
Your TASK formulation was: "{{original_task_step}}"

This led to {{failure_mode_description}}.

### Corrective Reframe
Re-examine the SITUATION and reformulate your TASK step. Specifically:
- {{targeted_correction_guidance}}

### Complete STAR Framework (Revised)

#### SITUATION
{{original_situation_step}}

#### TASK
[Reformulate this step, addressing the issue above]

#### ACTION
[Derive new actions from the corrected TASK]

#### RESULT
[Update verification criteria accordingly]

---

Now proceed with the corrected approach.
```

### 3.2 ExecuteAgentRunJob Integration

**File**: `app/Jobs/ExecuteAgentRunJob.php`

Integrate targeted retry trigger in `finalizeTerminal()` method.

**Integration Point** (after line 491, end of finalizeTerminal):
1. Check if status is failed and targeted retry is enabled
2. Resolve `TargetedRetryService` from container
3. Call `shouldRetry($run)` to check eligibility
4. If eligible, call `dispatchRetry($run)`
5. Log retry dispatch to audit log

### 3.3 API Endpoint: Manual Retry

**File**: `app/Http/Controllers/Api/V1/AgentRunController.php`

Add new method `retry()`:

```php
public function retry(Request $request, int $id, TargetedRetryService $retryService): JsonResponse
```

**Behavior**:
- Validate run belongs to user
- Check run is in terminal failed state
- Accept optional `retry_prompt` body parameter
- If provided, use custom prompt; otherwise call `buildRetryPrompt()`
- Dispatch retry and return new run details

**Route**: `POST /runs/{id}/retry`

**File**: `routes/api.php`

Add route after the confirm-lesson route:
```php
Route::post('/runs/{id}/retry', [AgentRunController::class, 'retry'])->middleware('throttle:agent-mutations');
```

### 3.4 Configuration

**File**: `config/agent.php`

Add targeted_retry configuration block:

```php
'targeted_retry' => [
    'enabled' => (bool) env('AGENT_TARGETED_RETRY_ENABLED', false),
    'max_retries' => (int) env('AGENT_TARGETED_RETRY_MAX', 1),
],
```

### 3.5 Monitor UI: Retry Chain Visualization

**File**: `resources/js/Pages/Agent/Monitor/Index.vue`

Add retry chain display linking original to retry runs.

**Changes**:
- In the runs table, when a run has `metadata_json.retry_of_run_id`:
  - Add "Retry" badge/indicator next to run ID
  - Show link to original run: "Retry of #{{retry_of_run_id}}"
- When viewing a run that has been retried (i.e., another run references it):
  - Show "Retried" indicator
  - Link to retry run(s)
- Chain visualization: `Original #123 → Retry #124 → Retry #125`

**Implementation**:
- Compute retry relationships from loaded runs array
- Build parent→child mapping for visualization
- Display as inline breadcrumb-style chain in run detail area

**Location**: In run detail header area, above event tail

### 3.6 Monitor UI: Manual Retry Button

**File**: `resources/js/Pages/Agent/Monitor/Index.vue`

Add manual retry trigger for failed runs.

**Implementation**:
- When selected run is in failed state and has STAR metadata:
  - Show "Retry" button in run actions area
  - Button opens modal with:
    - Auto-generated retry prompt preview (read-only textarea)
    - Optional: Allow editing the prompt before submission
    - Confirm/Cancel buttons
  - On confirm, call `POST /runs/{id}/retry`
  - On success, reload monitor to show new run

**State Variables**:
- `retryModalRunId`
- `retryModalPrompt`
- `retryBusy`
- `retryError`

### 3.7 Unit Tests

**File**: `tests/Unit/Support/Agent/TargetedRetryServiceTest.php`

Test cases:
- `test_should_retry_returns_true_for_star_structured_failure()`
- `test_should_retry_returns_false_for_unstructured_failure()`
- `test_should_retry_respects_max_retry_count()`
- `test_should_retry_respects_rate_limit_hold()`
- `test_build_retry_prompt_includes_original_situation()`
- `test_build_retry_prompt_references_failed_task_step()`
- `test_dispatch_retry_creates_new_run_with_correct_metadata()`

### 3.8 Feature Tests

**File**: `tests/Feature/Jobs/ExecuteAgentRunJobTargetedRetryTest.php`

Test cases:
- `test_automatic_retry_fires_on_star_structured_failure()`
- `test_retry_respects_max_retries_config()`
- `test_retry_blocked_when_rate_limited()`
- `test_retry_run_linked_via_metadata()`

**File**: `tests/Feature/Http/Controllers/Api/V1/AgentRunControllerRetryTest.php`

Test cases:
- `test_manual_retry_endpoint_returns_new_run()`
- `test_manual_retry_accepts_custom_prompt()`
- `test_manual_retry_rejects_non_failed_run()`

---

## Work Item 4: Trust Calibration

### 4.1 StarMetrics DTO

**File**: `app/Support/Delegation/DTOs/StarMetrics.php`

```php
readonly class StarMetrics
{
    public function __construct(
        public float $starCompletionRate,      // 0.0-1.0
        public float $situationCorrectRate,    // 0.0-1.0
        public float $taskCorrectRate,         // 0.0-1.0
        public float $actionCorrectRate,       // 0.0-1.0
        public float $resultCorrectRate,       // 0.0-1.0
        public float $firstPassSuccessRate,    // 0.0-1.0
        public float $recoveryRate,            // 0.0-1.0
        public array $failureModeDistribution, // ['type_1' => float, 'type_2' => float, 'type_3' => float]
        public int $sampleSize,
    ) {}
}
```

### 4.2 TrustScore DTO

**File**: `app/Support/Delegation/DTOs/TrustScore.php`

```php
readonly class TrustScore
{
    public function __construct(
        public float $score,           // 0.0-1.0
        public string $confidence,     // 'low', 'medium', 'high'
        public array $components,      // Breakdown of contributing factors
        public int $sampleSize,
        public ?string $source,        // 'job', 'runner_type', 'default'
    ) {}

    public function isLowTrust(): bool
    {
        return $this->score < 0.4;
    }

    public function isMediumTrust(): bool
    {
        return $this->score >= 0.4 && $this->score < 0.8;
    }

    public function isHighTrust(): bool
    {
        return $this->score >= 0.8;
    }
}
```

### 4.3 TrustScoreCalculator Service

**File**: `app/Support/Delegation/TrustScoreCalculator.php`

Create service that computes trust scores from historical STAR performance.

**Implementation**:
- Constructor accepts no dependencies (queries DB directly)
- Method `calculate(string $runnerType, ?int $jobId = null): TrustScore`
  - Query runs with STAR metadata within sliding window
  - Compute `StarMetrics` from aggregated data
  - Weight metrics to produce final score
  - Apply confidence based on sample size
- Method `getMetrics(string $runnerType, ?int $jobId = null): StarMetrics`
  - Raw aggregation query results

**Score Calculation Formula**:
```
base_score = (
    star_completion_rate * 0.15 +
    task_correct_rate * 0.35 +      # TASK step is most predictive
    first_pass_success_rate * 0.30 +
    recovery_rate * 0.20
)

penalty = (failure_mode_type_1_rate * 0.15)  # Type 1 is worst

final_score = clamp(base_score - penalty, 0.0, 1.0)
```

**Fallback Hierarchy**:
1. If jobId provided and has min_job_runs history: use job-level metrics
2. If runner_type has history: use runner_type-level metrics
3. Otherwise: return default_score (0.5) with source='default'

**Caching**:
- Cache computed scores for 5 minutes
- Cache key: `trust_score:{runner_type}:{job_id}`

### 4.4 Database Migration: delegatee_profiles trust columns

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_trust_columns_to_delegatee_profiles.php`

```php
Schema::table('delegatee_profiles', function (Blueprint $table) {
    $table->decimal('trust_score', 3, 2)->nullable()->after('is_active');
    $table->timestamp('trust_updated_at')->nullable()->after('trust_score');
});
```

### 4.5 DelegateeProfile Model Update

**File**: `app/Models/DelegateeProfile.php`

Add casts:
```php
'trust_score' => 'decimal:2',
'trust_updated_at' => 'datetime',
```

### 4.6 Trust Score Recalculation Job

**File**: `app/Jobs/RecalculateTrustScoresJob.php`

Create job that recalculates trust scores for all active delegatee profiles.

**Implementation**:
- Query all active DelegateeProfile records
- For each, call `TrustScoreCalculator->calculate(profile->runner_type)`
- Update profile's `trust_score` and `trust_updated_at`
- Batch update to minimize DB queries

**Scheduling**:
- Dispatch after every N runs complete (configurable via `trust.recalc_interval_runs`)
- Or on a timer (e.g., every 30 minutes via scheduler)

### 4.7 ExecuteAgentRunJob Trust Recalc Trigger

**File**: `app/Jobs/ExecuteAgentRunJob.php`

Add trigger to dispatch trust recalculation after runs complete.

**Integration** (in finalizeTerminal, after existing logic):
- Increment a counter in cache: `trust_recalc_run_count`
- If counter >= config threshold, dispatch `RecalculateTrustScoresJob` and reset counter

### 4.8 Configuration

**File**: `config/agent.php`

Add trust configuration block:

```php
'trust' => [
    'window_size' => (int) env('AGENT_TRUST_WINDOW_SIZE', 50),
    'default_score' => (float) env('AGENT_TRUST_DEFAULT_SCORE', 0.5),
    'min_job_runs' => (int) env('AGENT_TRUST_MIN_JOB_RUNS', 10),
    'recalc_interval_runs' => (int) env('AGENT_TRUST_RECALC_INTERVAL', 10),
],
```

### 4.9 API Endpoint: Get Trust Score

**File**: `app/Http/Controllers/Api/V1/DelegateeProfileController.php`

Add new method `trust()`:

```php
public function trust(Request $request, int $id, TrustScoreCalculator $calculator): JsonResponse
```

**Behavior**:
- Validate profile belongs to user
- Call calculator to get current trust score
- Return score with component breakdown

**Route**: `GET /delegation/delegatee-profiles/{id}/trust`

**File**: `routes/api.php`

Add route within delegation group (after line 174):
```php
Route::get('/delegatee-profiles/{id}/trust', [DelegateeProfileController::class, 'trust']);
```

### 4.10 Delegation Graph Executor Trust Enforcement

**File**: Create `app/Support/Delegation/DelegationGraphExecutor.php` (if not exists)

**Note**: Based on grep results, this class doesn't exist yet. Create stub with trust enforcement hooks.

**Implementation**:
- When executing delegation tasks, query `DelegateeProfile.trust_score`
- Low trust (<0.4): Set `verification_required = true` for every sub-task
- Medium trust (0.4-0.8): Standard verification rules apply
- High trust (>0.8): Skip intermediate verification, verify only final output
- STAR preamble always applies regardless of trust level

### 4.11 Delegatee Profile UI: Trust Score Display

**File**: `resources/js/Pages/Agent/Delegation/ProfileIndex.vue`

Add trust score column to profiles list.

**Implementation**:
- Add "Trust Score" column to profiles table
- Display score as percentage with color coding:
  - Red (<40%): Low trust
  - Yellow (40-80%): Medium trust
  - Green (>80%): High trust
- Show "N/A" for profiles without computed score

**File**: `resources/js/Pages/Agent/Delegation/ProfileForm.vue`

Add trust score display in profile detail/edit view.

**Implementation**:
- Read-only trust score display (not editable)
- Show component breakdown via tooltip or expandable section
- Last updated timestamp
- "Recalculate" button that triggers manual recalc (calls trust endpoint)

**Location**: In profile detail header or sidebar

### 4.12 Dashboard Widget: Trust Overview

**File**: `resources/js/Pages/Dashboard.vue` (or Agent dashboard if exists)

Add trust scores overview widget.

**Implementation**:
- Show summary of trust scores across all delegatee profiles
- Distribution chart: how many profiles in each trust tier
- Link to full delegatee profiles page

**Note**: This is optional enhancement; prioritize ProfileIndex and ProfileForm first

### 4.13 Unit Tests

**File**: `tests/Unit/Support/Delegation/TrustScoreCalculatorTest.php`

Test cases:
- `test_calculate_returns_score_within_bounds()`
- `test_calculate_uses_job_level_metrics_when_available()`
- `test_calculate_falls_back_to_runner_type_metrics()`
- `test_calculate_returns_default_for_cold_start()`
- `test_metrics_aggregates_correctly_within_window()`
- `test_confidence_reflects_sample_size()`

### 4.14 Feature Tests

**File**: `tests/Feature/Http/Controllers/Api/V1/DelegateeProfileControllerTrustTest.php`

Test cases:
- `test_trust_endpoint_returns_score_with_components()`
- `test_trust_endpoint_requires_auth()`
- `test_trust_endpoint_returns_default_for_new_profile()`

**File**: `tests/Feature/Jobs/RecalculateTrustScoresJobTest.php`

Test cases:
- `test_job_updates_all_active_profiles()`
- `test_job_skips_inactive_profiles()`
- `test_job_handles_empty_profile_set()`

---

## Navigation and Discoverability

### Job Form STAR Configuration
- **Location**: `/agent/jobs/create` and `/agent/jobs/{id}/edit`
- **Access**: Direct navigation from Jobs list → Create/Edit button
- **Discoverability**: New "Reasoning & Retry" section appears in job form with help text explaining STAR benefits

### Monitor UI Enhancements
- **Location**: `/agent/monitor`
- **Access**: Direct navigation from main nav → Monitor
- **Discoverability**:
  - Reasoning step badges appear inline with event content (no separate navigation)
  - Suggested lesson banner appears automatically when applicable
  - Retry chain breadcrumbs appear in run detail header
  - Manual retry button appears in run actions for failed STAR runs

### Delegatee Profile Trust Display
- **Location**: `/agent/delegatee-profiles`
- **Access**: Direct navigation from Delegation nav → Delegatee Profiles
- **Discoverability**:
  - Trust score column in profiles table is visible by default
  - Score uses color coding for at-a-glance assessment
  - Detail view shows component breakdown

---

## Migration Execution Order

1. `add_star_columns_to_agent_jobs.php` (Work Item 1)
2. `add_star_ab_group_to_agent_job_runs.php` (Work Item 1)
3. `add_reasoning_step_to_agent_run_events.php` (Work Item 2)
4. `add_trust_columns_to_delegatee_profiles.php` (Work Item 4)

All migrations add nullable columns and are backward compatible.

---

## Dependency Graph

```
Work Item 1: STAR Preamble Generator
    └── LessonsManager extension (runner_type filter)
    └── ExecuteAgentRunJob integration
    └── Database: agent_jobs columns
    └── Database: agent_job_runs star_ab_group
    └── Config: star_preamble section
    └── Job Form UI updates

Work Item 2: Structured Reasoning Capture (depends on 1)
    └── ReasoningStepParser
    └── FailureModeClassifier
    └── RunEventWriter extension
    └── Database: agent_run_events reasoning_step
    └── ExecuteAgentRunJob monitoring loop integration
    └── Monitor UI: reasoning step indicators
    └── Monitor UI: suggested lesson display
    └── API: confirm-lesson endpoint

Work Item 3: Targeted Retry (depends on 2)
    └── TargetedRetryService
    └── ExecuteAgentRunJob finalization integration
    └── Config: targeted_retry section
    └── Monitor UI: retry chain visualization
    └── Monitor UI: manual retry button
    └── API: retry endpoint

Work Item 4: Trust Calibration (depends on 1, 2)
    └── StarMetrics DTO
    └── TrustScore DTO
    └── TrustScoreCalculator
    └── RecalculateTrustScoresJob
    └── Database: delegatee_profiles trust columns
    └── DelegationGraphExecutor trust enforcement
    └── Delegatee Profile UI: trust display
    └── API: trust endpoint
    └── Config: trust section
```

---

## Test File Summary

### Unit Tests
- `tests/Unit/Support/Agent/StarPreambleGeneratorTest.php`
- `tests/Unit/Support/Compliance/LessonsManagerRunnerTypeTest.php`
- `tests/Unit/Support/Agent/ReasoningStepParserTest.php`
- `tests/Unit/Support/Agent/FailureModeClassifierTest.php`
- `tests/Unit/Support/Agent/TargetedRetryServiceTest.php`
- `tests/Unit/Support/Delegation/TrustScoreCalculatorTest.php`

### Feature Tests
- `tests/Feature/Jobs/ExecuteAgentRunJobStarPreambleTest.php`
- `tests/Feature/Jobs/ExecuteAgentRunJobReasoningCaptureTest.php`
- `tests/Feature/Jobs/ExecuteAgentRunJobTargetedRetryTest.php`
- `tests/Feature/Http/Controllers/Api/V1/AgentRunControllerRetryTest.php`
- `tests/Feature/Http/Controllers/Api/V1/DelegateeProfileControllerTrustTest.php`
- `tests/Feature/Jobs/RecalculateTrustScoresJobTest.php`

## Sections

- Work Item 1: STAR Preamble Generator
- Work Item 2: Structured Reasoning Capture
- Work Item 3: Targeted Retry
- Work Item 4: Trust Calibration
- Navigation and Discoverability
- Migration Execution Order
- Dependency Graph
- Test File Summary


## Risks

- STAR preamble may increase token usage and runner execution time, potentially impacting costs and job throughput
- ReasoningStepParser depends on runners outputting STAR sections with expected markdown headers - custom runners may produce non-standard output
- Failure mode classification heuristics may produce false positives or miss actual failure patterns until tuned with production data
- Automatic retry on STAR failures could create unexpected job chains if max_retries is set too high
- Trust score computation queries historical runs which may be expensive; caching strategy must be validated under load
- A/B testing random assignment may not achieve balanced groups for jobs with low run frequency
- Monitor UI changes add complexity to an already dense event tail view; UX testing needed to validate readability
- LessonsManager extension with runner_type filter adds new query paths that need performance validation


## Assumptions

- ExecuteAgentRunJob spawns subprocesses via Symfony Process and captures stdout/stderr in a polling loop - integration points are well-defined
- LessonsManager already supports TaskCategory filtering and can be extended with runner_type filtering without breaking existing callers
- The task markdown file is read from disk and can be replaced with a temporary enhanced version containing the STAR preamble
- RunEventWriter.createEvent() can be extended to accept a nullable reasoning_step parameter without changing existing callers
- DelegationGraphExecutor does not exist yet and will need to be created with trust enforcement hooks
- Monitor UI (Index.vue) currently renders events via formatAgentRunEventEntries and can be extended to show reasoning step badges inline
- The delegatee_profiles table exists with is_active column, trust columns can be added as nullable
- Rate-limit hold state is tracked in UsageLimitState service and can be queried to block retries
- A/B test assignment uses simple random sampling; no sophisticated randomization infrastructure required
- Failure mode classification is heuristic-based keyword matching; no ML models or external services involved

