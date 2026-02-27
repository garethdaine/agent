# Requirements Discovery Summary

Session: 10

# STAR Reasoning & Delegation Framework Integration

## Overview
Integrate STAR (Situation, Task, Action, Result) structured reasoning into Agent's job dispatch pipeline, with reasoning capture, targeted retry on failure, and trust calibration for delegation. Based on research showing STAR goal articulation produces +85pp accuracy improvement on implicit constraint reasoning.

## Work Items (Build Order)

### Work Item 1: STAR Preamble Generator
Create `StarPreambleGenerator` service that prepends a structured reasoning preamble to task markdown before runner execution.

**Components:**
- `app/Support/Agent/StarPreambleGenerator.php` — generates STAR preamble with hardcoded template
- Integration point: `ExecuteAgentRunJob` after compliance pre-run gate, before process spawn
- A/B testing: random per-run assignment with configurable percentage split
- Lessons enrichment: inject filtered lessons from `LessonsManager` (by runner_type and TaskCategory)

**A/B Testing Implementation:**
- `agent.star_preamble.ab_test_enabled` (bool, default false)
- `agent.star_preamble.ab_test_treatment_percent` (int 0-100, default 50)
- Random assignment per run, stored in `star_ab_group` column

**LessonsManager Extension:**
- Add `runner_type` parameter to `queryLessons()` method
- Store `runner_type` in lesson context when appending
- Filter lessons by runner_type in addition to existing TaskCategory filter

### Work Item 2: Structured Reasoning Capture
Extend event infrastructure to capture and tag STAR reasoning steps from runner output.

**Components:**
- `app/Support/Agent/ReasoningStepParser.php` — stateful parser tracking STAR sections in stdout stream
- `app/Support/Agent/FailureModeClassifier.php` — heuristic keyword matching to classify Type 1/2/3 failures
- Extend `RunEventWriter` to accept optional reasoning_step tag
- Extract `reasoning_summary` to `AgentJobRun.metadata_json` on run finalization

**Failure Mode Classification (Heuristic):**
- Type 1 (Distance Heuristic): TASK step missing expected subjects from job context
- Type 2 (Environmental Rationalization): ACTION contradicts TASK framing
- Type 3 (Ironic Self-Awareness): TASK correct but ACTION diverges

**Semi-Automatic Lesson Generation:**
- On classified failure, generate suggested lesson text
- Surface in Monitor UI for user confirmation before appending to lessons.md

### Work Item 3: Targeted Retry
Build retry service that constructs corrective reframe prompts targeting the specific failed STAR step.

**Components:**
- `app/Support/Agent/TargetedRetryService.php`
  - `shouldRetry(AgentJobRun $failedRun): bool`
  - `buildRetryPrompt(AgentJobRun $failedRun): string`
  - `dispatchRetry(AgentJobRun $failedRun): AgentJobRun`
- Integration: `ExecuteAgentRunJob::finalizeTerminal()` — auto-trigger on STAR-structured failure
- API: `POST /runs/{id}/retry` with optional `retry_prompt` body parameter

**Retry Behavior:**
- Automatic trigger when STAR-structured run fails (respecting max_retries)
- Include original SITUATION verbatim in retry prompt
- Create new `AgentJobRun` linked via `metadata_json.retry_of_run_id`
- Respect rate-limit holds (block retry if job on hold)

### Work Item 4: Trust Calibration
Compute trust scores from STAR performance metrics, modulate delegation verification frequency.

**Components:**
- `app/Support/Delegation/TrustScoreCalculator.php`
  - `calculate(string $runnerType, ?int $jobId = null): TrustScore`
  - `getMetrics(string $runnerType, ?int $jobId = null): StarMetrics`
- `app/DTOs/TrustScore.php` — value object (score, confidence, component breakdown)
- `app/DTOs/StarMetrics.php` — value object (per-step rates, failure mode distribution)
- API: `GET /delegation/profiles/{id}/trust`

**Trust Score Granularity:**
- Per runner_type level (baseline)
- Per job level (when sufficient history exists)
- Fallback: job → runner_type → default 0.5

**Trust Enforcement:**
- Low trust (&lt;0.4): Mandatory verification after every sub-task
- Medium trust (0.4-0.8): Standard monitoring
- High trust (&gt;0.8): Skip intermediate verification in delegation
- STAR preamble always applies regardless of trust level

## Database Migrations

### agent_jobs table
- `star_preamble_enabled` (boolean, nullable) — null inherits global config
- `targeted_retry_enabled` (boolean, nullable) — null inherits global config
- `max_retries` (integer, nullable) — null inherits global config

### agent_job_runs table
- `star_ab_group` (string, nullable) — 'control' or 'treatment' for A/B tracking

### agent_run_events table
- `reasoning_step` (string, nullable) — 'situation', 'task', 'action', 'result', or null

### delegatee_profiles table
- `trust_score` (decimal 3,2, nullable)
- `trust_updated_at` (timestamp, nullable)

## Configuration (`config/agent.php`)

```php
'star_preamble' => [
    'enabled' => env('AGENT_STAR_PREAMBLE_ENABLED', true),
    'ab_test_enabled' => env('AGENT_STAR_AB_TEST_ENABLED', false),
    'ab_test_treatment_percent' => env('AGENT_STAR_AB_TEST_PERCENT', 50),
],

'targeted_retry' => [
    'enabled' => env('AGENT_TARGETED_RETRY_ENABLED', false),
    'max_retries' => env('AGENT_TARGETED_RETRY_MAX', 1),
],

'trust' => [
    'window_size' => env('AGENT_TRUST_WINDOW_SIZE', 50),
    'default_score' => env('AGENT_TRUST_DEFAULT_SCORE', 0.5),
    'min_job_runs' => env('AGENT_TRUST_MIN_JOB_RUNS', 10),
    'recalc_interval_runs' => env('AGENT_TRUST_RECALC_INTERVAL', 10),
],
```

## Monitor UI Updates
- Reasoning step indicators on tagged events (visual badge showing SITUATION/TASK/ACTION/RESULT phase)
- Retry chain visualization (original → retry → retry links)
- Suggested lesson UI for failure mode classifications (confirm to append)
- Trust score display on delegatee profiles dashboard

## Goals

- Implement STAR preamble injection for all job dispatch executions with per-job override capability
- Build A/B testing infrastructure with random per-run assignment and configurable treatment percentage
- Extend LessonsManager to filter by runner_type in addition to TaskCategory
- Create ReasoningStepParser to tag stdout events with STAR section identifiers
- Implement FailureModeClassifier using heuristic keyword matching against job context
- Build TargetedRetryService with automatic trigger on STAR-structured failures
- Create TrustScoreCalculator with per-runner and per-job granularity with fallback hierarchy
- Enforce mandatory sub-task verification for low-trust runners in delegation graph executor
- Add Monitor UI components for reasoning step indicators and retry chain visualization
- Implement semi-automatic lesson generation with UI confirmation workflow


## Constraints

- STAR preamble template must be hardcoded in StarPreambleGenerator for consistency
- reasoning_step column must be string type with application-layer validation (not database enum)
- STAR preamble always applies regardless of trust score - trust only affects verification frequency
- Trust score computation must be async and cached, not on job dispatch hot path
- Targeted retry must respect rate-limit holds - no retry while job is on rate-limit hold
- Retry prompts must include original SITUATION verbatim, not regenerated from current context
- Event write latency must remain within acceptable bounds after adding reasoning step parsing
- ReasoningStepParser must degrade gracefully when runner output lacks STAR sections
- Failure mode classification is advisory only - stored in metadata, never blocks execution
- Job-specific trust scores require minimum configurable runs before considered valid (default 10)


## Acceptance Criteria

- STAR preamble prepended to task markdown for all enabled jobs before runner execution
- A/B test assignment stored in star_ab_group column with configurable percentage split
- LessonsManager.queryLessons() accepts and filters by runner_type parameter
- STAR section events tagged with correct reasoning_step value in agent_run_events
- reasoning_summary appears in run metadata_json after STAR sections complete
- Failed runs with STAR output receive failure_mode_hint in metadata
- Automatic retry fires when STAR-structured run fails, respecting max_retries config
- Retry runs linked to originals via metadata_json.retry_of_run_id
- POST /runs/{id}/retry accepts optional retry_prompt body parameter with fallback to auto-generated
- Trust scores computed from historical STAR performance with sliding window
- Cold-start jobs fall back to runner-level trust score when below min_job_runs threshold
- Low-trust runners (<0.4) trigger mandatory verification after every delegation sub-task
- Monitor UI displays reasoning step indicators on tagged events
- Monitor UI shows retry chain visualization linking original to retry runs
- Suggested lesson text surfaces in UI for user confirmation before appending
- Global toggles in config with per-job override via nullable database columns
- Existing jobs without column values inherit global config behavior

