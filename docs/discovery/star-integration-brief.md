# Requirements Discovery Brief: STAR Reasoning & Delegation Framework Integration

## Context for the Session

You are working on **Agent**, a local-first Laravel 12 + Jetstream application for managing and executing scheduled agent jobs. It dispatches to queue workers that spawn local subprocesses (Claude, Codex, or custom runners). The codebase lives at the project root and is fully built through Phase 8, with workflow orchestration (compliance gates, verification, lessons injection) actively rolling out.

This brief defines four integration work items derived from two research papers:

1. **"Prompt Architecture Determines Reasoning Quality"** (Jo, 2026) — the "Car Wash paper." A variable isolation study showing that structured goal articulation (STAR: Situation, Task, Action, Result) accounts for +85pp accuracy improvement on implicit constraint reasoning, outperforming direct context injection by 2.83×.
2. **"Intelligent AI Delegation"** (Tomasev, Franklin, Osindero — Google DeepMind, 2026) — a framework for safe, verifiable multi-agent delegation with nine building blocks including task decomposition, monitoring, adaptive coordination, and trust calibration.

The analysis below maps specific findings from both papers to Agent's existing architecture and identifies exactly what to build.

---

## Architecture You Need to Know

### Job Dispatch Pipeline (Current)
1. `agent:dispatch-due` command evaluates cron expressions every minute
2. Creates `AgentJobRun` (status: `queued`), dispatches `ExecuteAgentRunJob` to Redis/Horizon
3. `ExecuteAgentRunJob` validates state, runs optional compliance pre-run gate, spawns subprocess via Symfony Process
4. `CommandTemplateRenderer.renderTokens()` interpolates placeholders (`{{run_id}}`, `{{task_markdown_path}}`, etc.) into the command template
5. Runner executes with the task markdown file as its prompt input
6. Live monitoring loop polls every 250ms, writes to `AgentRunEvent` (stdout/stderr/lifecycle)
7. On completion: exit code evaluation, optional compliance completion gate, terminal state transition

### Prompt Construction (Current)
- **Simple token rendering**: `CommandTemplateRenderer` does placeholder interpolation on command templates
- **Task markdown**: Stored via `TaskMarkdownStorage`, served as file path to runners
- **No structured reasoning injection**: The task markdown is passed as-is. There is no pre-execution goal articulation step.
- **Interrogation system** has its own multi-phase prompt pipeline (`SystemPromptResolver`) but this is separate from regular job dispatch.

### Retry/Recovery (Current)
- **No automatic retry**: `tries=1`, `backoff=0` on queue jobs
- **Rate-limit recovery**: Detects upstream rate-limit markers in output, calculates hold window, skips job during hold
- **Path failure streak**: Tracks consecutive scheduled failures, may escalate to disabled
- **No targeted re-prompting**: On failure, the only option is manual re-run. There is no mechanism to diagnose which reasoning step failed or reframe accordingly.

### Audit/Logging (Current)
- `AgentAuditLog`: Immutable append-only records of all mutating actions (before/after diffs)
- `AgentRunEvent`: Sequence-numbered stdout/stderr/lifecycle events per run
- **Captures outputs only**: No structured capture of intermediate reasoning steps

### Compliance/Orchestration (Current)
- `OrchestrationPolicyService`: Evaluates complexity, checks plan/verification gates
- Enforcement modes: disabled (default), advisory, blocking
- `VerificationEvidenceEvaluator`: Maps task categories to required evidence types
- Lessons injection from `/tasks/lessons.md` into build context

### Delegation Graph (Current — Partial)
- Models exist: `DelegationGraph`, `DelegationTask`, `DelegateeProfile`, `DelegationVerificationResult`
- DAG structure with verification cycles
- **Not yet connected to STAR reasoning or trust calibration**

### Key Models
- `AgentJob` — scheduled job definition (cron, runner_type, command_template, task_markdown_path)
- `AgentJobRun` — individual execution (status lifecycle, metadata_json, exit_code, events)
- `AgentRunEvent` — output stream (event_type: stdout|stderr|lifecycle, payload, sequence)
- `InterrogationSession` — multi-phase discovery (10 phases, summary/plan/annotations JSON)
- `InterrogationBuildTask` — individual build work units with verification
- `DelegationTask` — unit within a delegation graph

### File Locations
- Job dispatch: `app/Support/Agent/DispatchDueService.php`
- Job execution: `app/Jobs/ExecuteAgentRunJob.php`
- Command rendering: `app/Support/Agent/CommandTemplateRenderer.php`
- Task markdown: `app/Support/Agent/TaskMarkdownStorage.php`
- Run events: `app/Support/Agent/RunEventWriter.php`
- Compliance: `app/Support/Compliance/OrchestrationPolicyService.php`
- Evidence evaluation: `app/Support/Compliance/VerificationEvidenceEvaluator.php`
- Audit logging: `app/Support/Agent/AuditLogger.php`
- Delegation: `app/Support/Delegation/`
- Interrogation jobs: `app/Jobs/ExecuteInterrogation*Job.php`
- Config: `config/agent.php`
- Lessons: `tasks/lessons.md`

---

## Work Item 1: STAR Preamble Generator for Job Dispatch

### Research Basis
The Car Wash study found that goal articulation before reasoning is the single highest-leverage intervention. STAR alone accounts for +85pp (0% → 85%). The mechanism is autoregressive conditioning: once the model writes "Task: Get your car to the car wash," every subsequent token is conditioned on that explicit constraint. Implicit requirements become explicit in the context window.

Key data points:
- Bare prompt: 0% (0/20)
- Role only: 0% (0/20)
- Role + STAR: 85% (17/20)
- Role + Profile: 30% (6/20) — having all the facts but no reasoning structure
- Role + STAR + Profile: 95% (19/20)
- Full stack (Role + STAR + Profile + RAG): 100% (20/20)

The critical insight: structured reasoning outperforms context injection by 2.83× (p = 0.001). How the model processes information matters more than how much information it receives.

### What to Build
A `StarPreambleGenerator` service that constructs a STAR reasoning preamble and injects it into the job payload before the runner begins work.

### Behavior
When `ExecuteAgentRunJob` prepares to spawn the runner subprocess:

1. **Before writing the task markdown to the runner**, prepend a STAR preamble block
2. The preamble is a structured template the runner must fill in before beginning work
3. The preamble content depends on the job's task category and available context

### Preamble Template
```
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

### Design Constraints
- The STAR preamble goes in the **system prompt / prepended to the task markdown**, not as optional metadata. The Car Wash study showed the mechanism is autoregressive conditioning — it must be in the generation path.
- The preamble should be in the **system prompt layer** (structural), while job-specific context (profile, RAG) goes in the **user message layer** (instance-specific). This follows the paper's layered architecture: Role definition → STAR framework → Profile/context → RAG retrieval.
- Must be **toggleable** via config (`agent.star_preamble.enabled`, default `true`) and per-job override (`AgentJob.star_preamble_enabled` column, nullable boolean — null inherits global config).
- Must work with all runner types (claude, codex, custom). For custom runners, prepend to the task markdown file content. For claude/codex, inject into the constructed prompt.

### Implementation Approach
1. Create `app/Support/Agent/StarPreambleGenerator.php`
2. Method: `generate(AgentJob $job, AgentJobRun $run): string` — returns the preamble text
3. The generator can optionally enrich the preamble with:
   - Job name and description (for SITUATION context)
   - Task category from compliance classification (for TASK framing)
   - Verification requirements from `VerificationEvidenceEvaluator` (for RESULT section)
   - Relevant lessons from `LessonsManager` (for ACTION guardrails)
4. Integrate into `ExecuteAgentRunJob` — after compliance pre-run gate, before process spawn
5. The prepended content should be written to a temporary enhanced task markdown file (or piped via stdin depending on runner type)
6. Add config keys to `config/agent.php`
7. Add nullable `star_preamble_enabled` column to `agent_jobs` migration
8. Add to API request validation and job form UI

### Acceptance Criteria
- [ ] STAR preamble is prepended to task markdown for all enabled jobs before runner execution
- [ ] Preamble is visible in `AgentRunEvent` stream (the runner's first output should be the filled-in STAR sections)
- [ ] Global toggle in config, per-job override in database
- [ ] Custom runners receive the preamble in the task markdown file
- [ ] Claude/Codex runners receive it in the appropriate prompt position
- [ ] Existing jobs without the column set inherit global config behavior
- [ ] No change to job execution when feature is disabled

---

## Work Item 2: Structured Reasoning Capture in Audit Events

### Research Basis
The Car Wash study's failure mode taxonomy identified three failure patterns:
- **Type 1 — Distance Heuristic (~70%)**: Model treats the problem as distance optimization, never considers what needs to be at the destination
- **Type 2 — Environmental Rationalization (~20%)**: Model builds secondary justifications for the wrong answer
- **Type 3 — Ironic Self-Awareness (~10%)**: Model acknowledges the constraint, then ignores it

The Delegation paper (§4.5) distinguishes outcome-level vs process-level monitoring. Currently Agent only captures outputs (outcome-level). To diagnose which STAR step produced an error, we need process-level monitoring that captures the intermediate reasoning steps.

### What to Build
Extend the `RunEventWriter` and event schema to capture and tag structured reasoning steps from runner output.

### Behavior
1. **Parse runner output** for STAR section markers in the stdout stream
2. **Tag events** with a new `reasoning_step` field when they correspond to SITUATION, TASK, ACTION, or RESULT sections
3. **Store a `reasoning_summary`** in `AgentJobRun.metadata_json` after the STAR sections are complete, containing:
   - Each STAR step's content (truncated to reasonable length)
   - Whether each step was completed
   - Timestamp of each step
4. **Classify failure modes** when a run fails after completing STAR steps:
   - If TASK step framed the goal around the wrong subject → potential Type 1 (distance heuristic)
   - If ACTION step includes rationalizations contradicting TASK → potential Type 2
   - If TASK is correct but ACTION diverges → potential Type 3

### Design Constraints
- Parsing should be **best-effort and non-blocking** — if the runner doesn't output STAR sections (e.g., STAR is disabled, or custom runner ignores it), the system degrades gracefully
- The new `reasoning_step` field on `AgentRunEvent` should be nullable (null for non-reasoning events)
- Failure mode classification is **advisory only** — stored in metadata, never blocks execution
- Must not increase event write latency meaningfully (the monitoring loop runs every 250ms)

### Implementation Approach
1. Add nullable `reasoning_step` enum column to `agent_run_events` (`situation`, `task`, `action`, `result`, null)
2. Create `app/Support/Agent/ReasoningStepParser.php` — stateful parser that tracks which STAR section is currently being output based on markdown headers in stdout
3. Extend `RunEventWriter` to accept optional reasoning step tag
4. In `ExecuteAgentRunJob`'s monitoring loop, pipe stdout chunks through the parser before writing events
5. On run finalization, extract reasoning summary from tagged events into `metadata_json.reasoning_summary`
6. Create `app/Support/Agent/FailureModeClassifier.php` — analyzes reasoning_summary on failed runs, adds `failure_mode_hint` to metadata
7. Surface reasoning steps in the Monitor UI event stream (visual indicator per event showing which STAR phase it belongs to)

### Acceptance Criteria
- [ ] STAR section events are tagged with the correct `reasoning_step` value
- [ ] `reasoning_summary` appears in run metadata after STAR sections complete
- [ ] Non-STAR output events have null `reasoning_step` (backward compatible)
- [ ] Parser handles partial/malformed STAR output gracefully (no crashes, no false tags)
- [ ] Failed runs with STAR output get a `failure_mode_hint` in metadata
- [ ] Monitor UI shows reasoning step indicators on tagged events
- [ ] Event write latency remains within acceptable bounds (measure before/after)

---

## Work Item 3: Targeted Retry for Structured Reasoning Failures

### Research Basis
The Car Wash study's **recovery paradox**: STAR-structured responses that fail are *harder* to correct than unstructured ones. Recovery rate was 67% for STAR vs 95-100% for bare/role-only prompts. The mechanism is token-level: the model has already built a coherent structured argument for the wrong answer. A generic "try again" prompt must overcome the autoregressive momentum of that entire argument.

The Delegation paper's adaptive coordination (§4.4) describes internal triggers for re-delegation: performance degradation, verification failure, unresponsive agents. The recovery paradox adds critical nuance: *how* you re-delegate after a structured reasoning failure matters. A verification failure on a STAR-reasoned task should not trigger a simple retry — it should trigger a re-decomposition that targets the specific STAR step that went wrong.

### What to Build
A `TargetedRetryService` that, when a STAR-structured run fails verification, constructs a targeted retry prompt that reframes from the specific failed reasoning step rather than re-running the entire job.

### Behavior
1. When a run fails and has `reasoning_summary` in metadata:
   - Analyze which STAR step likely produced the error (using `FailureModeClassifier` from Work Item 2)
   - Construct a retry prompt that:
     a. Acknowledges the previous attempt
     b. Quotes the specific STAR step that went wrong
     c. Provides a corrective reframe for that step
     d. Asks the runner to redo from that point forward
2. When a run fails without STAR metadata (unstructured failure):
   - Fall back to simple retry (re-run with same prompt)
3. The retry creates a **new `AgentJobRun`** linked to the original via `metadata_json.retry_of_run_id`

### Retry Prompt Template (for STAR failures)
```
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

### Design Constraints
- Targeted retry is **opt-in per job** via `AgentJob.targeted_retry_enabled` (nullable boolean, inherits global config)
- Maximum retry count configurable globally (`agent.targeted_retry.max_retries`, default 1) and per-job
- Retry runs are distinguishable in the UI (labeled as retry, linked to original)
- The retry mechanism works through the existing job dispatch pipeline — it creates a new run and queues it
- Must integrate with rate-limit hold logic (don't retry if job is on rate-limit hold)
- The retry prompt is constructed *before* dispatch, written as a modified task markdown

### Implementation Approach
1. Create `app/Support/Agent/TargetedRetryService.php`
   - Method: `shouldRetry(AgentJobRun $failedRun): bool`
   - Method: `buildRetryPrompt(AgentJobRun $failedRun): string`
   - Method: `dispatchRetry(AgentJobRun $failedRun): AgentJobRun`
2. Integrate into `ExecuteAgentRunJob::finalizeTerminal()` — after a run is marked failed, check if targeted retry should fire
3. Add `retry_of_run_id`, `retry_count`, `retry_reasoning_target` to run metadata schema
4. Add nullable `targeted_retry_enabled` and `max_retries` columns to `agent_jobs`
5. Add config keys to `config/agent.php`
6. Add retry chain visualization to Monitor UI (show original → retry → retry chain)
7. API endpoints: `POST /runs/{id}/retry` for manual targeted retry trigger

### Acceptance Criteria
- [ ] Failed STAR-structured runs trigger targeted retry with corrective reframe
- [ ] Failed unstructured runs trigger simple retry (if retry is enabled)
- [ ] Retry prompt references the specific failed STAR step
- [ ] Retry runs are linked to originals via metadata
- [ ] Max retry count is respected (no infinite retry loops)
- [ ] Rate-limit holds block retries
- [ ] Manual retry available via API
- [ ] Retry chain visible in Monitor UI
- [ ] Feature is opt-in, disabled by default

---

## Work Item 4: Trust Calibration via STAR Success Rates

### Research Basis
The Delegation paper (§4.6) proposes trust calibration based on behavioral metrics: transparency scores, historical performance, and capability matching. The Car Wash study provides a concrete metric to feed into this: per-runner STAR step success rates.

A runner that consistently formulates the TASK step correctly (car is the subject, not the person) demonstrates reliable implicit constraint reasoning. One that exhibits Type 1 failures (distance heuristic / shortcut reasoning) needs tighter monitoring. This maps directly to the Delegation paper's trust model where trust earns autonomy.

### What to Build
Extend `DelegateeProfile` with trust scoring derived from STAR reasoning performance, and use these scores in the delegation graph executor to modulate monitoring intensity.

### Behavior
1. **Aggregate STAR metrics per runner type** (and optionally per-job or per-task-category):
   - STAR completion rate (did the runner fill in all four sections?)
   - Per-step correctness rate (how often does TASK step correctly identify the goal?)
   - First-pass success rate (pass without retry)
   - Recovery rate (pass after targeted retry)
   - Failure mode distribution (% Type 1, Type 2, Type 3)
2. **Compute a trust score** (0.0–1.0) from these metrics:
   - High trust (>0.8): Runner can be granted more autonomy (less frequent process-level monitoring, larger task scopes in delegation)
   - Medium trust (0.4–0.8): Standard monitoring
   - Low trust (<0.4): Tighter monitoring (more frequent checkpoints, smaller task granularity, mandatory verification on every sub-task)
3. **Feed trust scores into delegation graph execution**:
   - When `DelegationTask` assignment considers capability matching, weight by trust score
   - Adjust verification frequency based on trust (high-trust runners skip intermediate verification; low-trust runners verify every sub-task)
   - Trust score visible on `DelegateeProfile` in UI

### Design Constraints
- Trust scores are **computed, not manually set** — derived from historical run data
- Scoring must handle cold-start (new runners with no history get a default trust of 0.5)
- Trust scores update after each completed run (sliding window, configurable via `agent.trust.window_size`, default 50 runs)
- Trust score computation must not be on the hot path of job dispatch (compute async, cache result)
- This builds on Work Items 1-2 (needs STAR preamble and reasoning capture to generate the input metrics)

### Implementation Approach
1. Create `app/Support/Delegation/TrustScoreCalculator.php`
   - Method: `calculate(string $runnerType, ?int $jobId = null): TrustScore`
   - Method: `getMetrics(string $runnerType, ?int $jobId = null): StarMetrics` (the raw aggregates)
2. Create `app/DTOs/TrustScore.php` — value object with score, confidence, component breakdown
3. Create `app/DTOs/StarMetrics.php` — value object with per-step rates, failure mode distribution
4. Add `trust_score` (decimal, nullable) and `trust_updated_at` (timestamp, nullable) to `delegatee_profiles`
5. Schedule trust score recalculation (e.g., after every N runs or on a timer) via a lightweight job
6. Integrate into delegation graph executor: when assigning tasks, query `DelegateeProfile.trust_score` to modulate verification requirements
7. API endpoint: `GET /delegation/profiles/{id}/trust` — returns trust score with component breakdown
8. Dashboard widget showing trust scores per runner

### Acceptance Criteria
- [ ] Trust scores computed from historical STAR performance data
- [ ] Cold-start runners get default 0.5 trust score
- [ ] Trust score updates after runs complete (within configured window)
- [ ] Delegation graph executor uses trust scores for task assignment weighting
- [ ] Verification frequency modulated by trust score
- [ ] Trust scores and components visible in API and UI
- [ ] Computation is async (not on dispatch hot path)
- [ ] Depends on Work Items 1 and 2 being complete first

---

## Implementation Priority & Dependencies

```
Work Item 1: STAR Preamble Generator ──────────┐
  (No dependencies, highest standalone impact)  │
                                                 ├──→ Work Item 4: Trust Calibration
Work Item 2: Reasoning Capture ─────────────────┤    (Requires 1 + 2 for input metrics)
  (Depends on 1 for STAR output to parse)       │
                                                 │
Work Item 3: Targeted Retry ────────────────────┘
  (Depends on 2 for failure mode classification)
```

**Recommended build order:**
1. **Work Item 1** — Small surface area, outsized impact. Touches `ExecuteAgentRunJob`, adds one new service, one config section, one migration. Can be built and validated in isolation.
2. **Work Item 2** — Extends existing event infrastructure. One migration (nullable column on events), one new parser, one classifier. Validates by checking tagged events appear in monitor.
3. **Work Item 3** — Builds on 1 + 2. New service, metadata schema extension, API endpoint, UI. Validate by triggering a failure and confirming targeted retry fires with correct reframe.
4. **Work Item 4** — Builds on all previous. New calculator, DTOs, delegation integration. Validate by checking trust scores appear and modulate delegation behavior.

---

## Technical Notes

### Config Schema Addition (`config/agent.php`)
```php
'star_preamble' => [
    'enabled' => env('AGENT_STAR_PREAMBLE_ENABLED', true),
],

'targeted_retry' => [
    'enabled' => env('AGENT_TARGETED_RETRY_ENABLED', false),
    'max_retries' => env('AGENT_TARGETED_RETRY_MAX', 1),
],

'trust' => [
    'window_size' => env('AGENT_TRUST_WINDOW_SIZE', 50),
    'default_score' => env('AGENT_TRUST_DEFAULT_SCORE', 0.5),
    'recalc_interval_runs' => env('AGENT_TRUST_RECALC_INTERVAL', 10),
],
```

### Migration Summary
1. `agent_jobs`: Add `star_preamble_enabled` (boolean, nullable), `targeted_retry_enabled` (boolean, nullable), `max_retries` (integer, nullable)
2. `agent_run_events`: Add `reasoning_step` (string/enum, nullable)
3. `delegatee_profiles`: Add `trust_score` (decimal 3,2, nullable), `trust_updated_at` (timestamp, nullable)

### Test Strategy
- **Unit**: `StarPreambleGenerator` produces correct output for each runner type and config combination
- **Unit**: `ReasoningStepParser` correctly tags STAR sections from sample stdout streams (including partial, malformed, and absent STAR output)
- **Unit**: `FailureModeClassifier` correctly categorizes known failure patterns
- **Unit**: `TargetedRetryService` builds correct retry prompts for each failure mode
- **Unit**: `TrustScoreCalculator` computes correct scores from sample metric sets (including cold-start)
- **Feature**: End-to-end job run with STAR preamble → reasoning capture → failure → targeted retry
- **Feature**: Trust score recalculation after run completion
- **Integration**: Monitor UI displays reasoning step indicators and retry chains

---

## Scoping Decisions & Known Gaps

### Included but Underspecified: Lessons Injection
The `LessonsManager` already exists and injects lessons into interrogation build context. Work Item 1 references lessons enrichment in the STAR preamble (for ACTION guardrails), but does not fully specify:
- How lessons are filtered by runner type or failure mode for injection into STAR preambles
- Whether targeted retry prompts (Work Item 3) should include relevant lessons from previous failures
- How new lessons are automatically generated from failure mode classifications (Work Item 2)

**Recommendation**: When building Work Item 1, extend `LessonsManager` to support querying by runner type and failure mode. When building Work Item 3, include relevant lessons in the retry prompt's "corrective reframe" section. This is a natural extension, not a separate work item.

### Deferred: Contract-First Task Decomposition for Delegation Sub-Tasks
The Delegation paper's §4.1 says sub-task decomposition should follow a "contract-first" pattern where each sub-task carries an explicit goal statement and verification condition — not just a description of work. This aligns with STAR (the T and R steps map directly to goal + verification). The `DelegationTask` model exists but does not yet enforce this structure.

**Why deferred**: The delegation graph executor is partially built. Adding contract-first decomposition requires designing how `DelegationTask` stores and validates goal/verification contracts, how the graph executor enforces them, and how STAR preambles compose with delegation-level goals. This is a larger architectural change that should be its own brief after Work Items 1-4 prove the STAR mechanism works in the simpler job dispatch path.

**When to revisit**: After Work Item 4 (trust calibration) is running and producing data. The trust scores will inform how tightly delegation sub-tasks need to be contracted.

### Out of Scope
- **Changes to the Interrogation system**: The interrogation multi-phase pipeline already has its own structured reasoning flow. These work items target the regular job dispatch pipeline. Future work may unify the two.
- **Multi-model STAR validation**: The Car Wash study only tested Claude Sonnet 4.5. Whether STAR produces the same lift on Codex or custom runners is unknown. Work Item 1 should include a flag to A/B test STAR-enabled vs disabled runs for empirical validation.
- **Cryptographic verification / ZK-SNARKs**: The Delegation paper discusses these for high-trust environments. Out of scope for Agent's local-first architecture.
- **Messenger control plane integration**: STAR and retry features should eventually be triggerable via chat commands. Deferred.

---

## References

1. Jo, H. (2026). "Prompt Architecture Determines Reasoning Quality: A Variable Isolation Study on the Car Wash Problem." arXiv:2602.21814v1.
2. Tomasev, N., Franklin, M., Osindero, S. (2026). "Intelligent AI Delegation." Google DeepMind.
3. Wei, J. et al. (2022). "Chain-of-Thought Prompting Elicits Reasoning in Large Language Models." NeurIPS 2022.
