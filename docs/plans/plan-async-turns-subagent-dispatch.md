# Plan: Async Turn Execution + Sub-Agent Dispatch

## Situation

The messenger runtime executes every turn **synchronously**: `ProcessRuntimeTurnJob` dispatches to `MessengerRuntimeOrchestrator::executeTurn()` which calls `SessionProcessManager::sendMessage()`, which blocks in `readTurnResponse()` until `turn_complete` or the 300s deadline. A complex task (e.g. "apply for this job position" involving web browsing, writing, and creating a gist) exceeds 300s and produces `"Error: Request timed out after 300s."`.

OpenClaw solves this with three mechanisms Agent Ops lacks:

1. **Auto-background exec** (`yieldMs`): Commands that don't finish within a yield window are backgrounded; the turn returns immediately with `{ status: "running", sessionId }` and the process continues. The agent can poll later via the `process` tool.
2. **Sub-agent spawn** (`sessions_spawn`): Non-blocking child sessions that run independently and announce results back to the requester channel.
3. **Exit notification** (`notifyOnExit`): System events + heartbeat wake when a background process exits, so the agent is informed without polling.

## Task

Deliver three incremental capabilities:

1. **Phase 1**: Higher timeout + progress heartbeats to the channel (fixes the immediate failure).
2. **Phase 2**: Yield-based turn execution so the queue worker is not blocked indefinitely.
3. **Phase 3**: Sub-agent dispatch with announce-back (OpenClaw parity for `sessions_spawn`).

Each phase is independently deployable. No regressions to existing synchronous turn flow.

## Architecture Constraints

**Why we can't directly copy OpenClaw's in-memory patterns:**

- OpenClaw is a single long-running Node.js gateway; it uses in-memory `Map<string, ProcessSession>` for process tracking. Agent Ops uses Horizon queue workers; `SessionProcessManager::$activeProcesses` is static per-worker, and pipes created by `proc_open` are only accessible in the worker that started them.
- A re-dispatched job may land on a different worker, losing access to the wrapper's pipes.
- Therefore: any approach that reads wrapper output must either (a) run on the same worker that owns the pipes, or (b) use an out-of-band communication channel (Redis, filesystem).

**Key decision: Phase 1-2 keep the blocking-read model** (the job stays on the worker that owns the pipes) but with a much higher timeout and periodic heartbeat updates to the channel. Phase 3 (sub-agents) dispatches child sessions as separate jobs that block their own workers, and completion is announced via a separate job on the messenger queue.

---

## Phase 1: Higher Timeout + Progress Heartbeats

### Goal

Stop the 300s timeout failure. The user sees periodic progress updates instead of silence followed by an error.

### Changes

#### 1.1 Increase default timeout

**File**: `config/runtime.php` line 202

Change default from `300` to `1800` (30 minutes, matches OpenClaw's `tools.exec.timeoutSec` default).

```php
'timeout_seconds' => (int) env('RUNTIME_CLI_TIMEOUT', 1800),
```

This propagates automatically to:
- `ProcessRuntimeTurnJob::$timeout` (reads same config in constructor)
- `MessengerRuntimeOrchestrator::executeViaWrapper()` → `sendMessage($session, $msg, $timeout)`
- `CliRuntimeExecutor` → `Process::setTimeout($timeout)`
- `SessionProcessManager::readTurnResponse()` deadline

The Horizon `supervisor-1` for the `agent` queue already has `timeout => 86500`, so no Horizon change needed.

#### 1.2 Add progress callback to `SessionProcessManager::readTurnResponse()`

**File**: `app/Services/Runtime/SessionProcessManager.php`

Add an optional `$onProgress` closure parameter. During the read loop, every `$heartbeatInterval` seconds (default 30), invoke the callback with the current state.

```php
public function readTurnResponse(
    string $runtimeSessionId,
    int $timeoutSeconds,
    ?Closure $onProgress = null,
    int $heartbeatInterval = 30,
): array
```

Inside the `while (time() < $deadline)` loop, track `$lastHeartbeat`. When `time() - $lastHeartbeat >= $heartbeatInterval` and `$onProgress !== null`, invoke:

```php
$onProgress([
    'elapsed_seconds' => time() - $startTime,
    'has_partial_output' => $fragments !== [],
    'fragment_count' => count($fragments),
]);
```

The callback is **optional** — `null` preserves existing behaviour exactly. The `sendMessage()` method passes it through:

```php
public function sendMessage(
    string $runtimeSessionId,
    string $message,
    int $timeoutSeconds = 1800,
    ?Closure $onProgress = null,
    int $heartbeatInterval = 30,
): array
```

#### 1.3 Wire progress callback in `ProcessRuntimeTurnJob`

**File**: `app/Jobs/Runtime/ProcessRuntimeTurnJob.php`

Before calling `$orchestrator->executeTurn()`, build a progress callback closure that updates the placeholder message:

```php
$progressCallback = function (array $state) use ($adapter, $chatSession, $placeholderMessageId) {
    if ($placeholderMessageId === null || !$adapter->supportsMessageEditing()) {
        return;
    }
    $elapsed = $state['elapsed_seconds'];
    $minutes = intdiv($elapsed, 60);
    $seconds = $elapsed % 60;
    $timeStr = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
    $adapter->editMessage($chatSession, $placeholderMessageId, "⏳ Working on it… ({$timeStr} elapsed)");
};
```

Pass this through the orchestrator to the session process manager. This requires `MessengerRuntimeOrchestrator::executeTurn()` to accept and forward the optional callback.

#### 1.4 Pass callback through orchestrator

**File**: `app/Services/Runtime/MessengerRuntimeOrchestrator.php`

Add optional parameter to `executeTurn()`:

```php
public function executeTurn(
    RuntimeSession $session,
    string $userMessage,
    ?string $runnerTypeOverride = null,
    ?string $systemPrompt = null,
    ApprovalMode $approvalMode = ApprovalMode::Autonomous,
    ?Closure $onProgress = null,
): array
```

In `executeViaWrapper()`, pass `$onProgress` to `$this->sessionProcessManager->sendMessage()`.

### Files touched (Phase 1)

| File | Change type |
|------|-------------|
| `config/runtime.php` | Default value change (300 → 1800) |
| `app/Services/Runtime/SessionProcessManager.php` | Additive: optional `$onProgress` param on `sendMessage()` and `readTurnResponse()` |
| `app/Services/Runtime/MessengerRuntimeOrchestrator.php` | Additive: optional `$onProgress` param on `executeTurn()`, forwarded to wrapper/CLI path |
| `app/Jobs/Runtime/ProcessRuntimeTurnJob.php` | Additive: build progress callback, pass to orchestrator |

### Risk assessment

**Very low.** All new parameters are optional with `null` defaults. Existing callers (tests, other dispatchers) pass no callback and get identical behaviour. The only non-optional change is the default timeout value, which is strictly more permissive (longer, not shorter).

### Tests

- Existing `SessionProcessManagerWrapperTest` and `MessengerRuntimeOrchestratorWrapperTest` pass unchanged (callback is null).
- New test: `SessionProcessManagerHeartbeatTest` — mock a slow wrapper, verify `$onProgress` is called at expected intervals.
- New test: `ProcessRuntimeTurnJobHeartbeatTest` — mock orchestrator + adapter, verify placeholder is edited during long turn.

---

## Phase 2: Yield-Based Turn Execution

### Goal

For very long turns (>5 minutes), free the queue worker periodically so other jobs can be processed, while maintaining the turn's progress.

### Design

Inspired by OpenClaw's `yieldMs` pattern but adapted for the Laravel queue architecture.

#### The constraint

The wrapper process pipes (`stdout`/`stdin`) are in `SessionProcessManager::$activeProcesses` (static per-worker). Only the worker that started the wrapper can read from those pipes. A re-dispatched job might land on a different worker.

#### Solution: Yield to file, resume on same worker

1. During `readTurnResponse()`, if the turn hasn't completed within `yieldAfterSeconds` (configurable, default 120s), the method:
   - Writes all accumulated fragments to a Redis key (`runtime:turn_buffer:{turnId}`)
   - Returns `['status' => 'yielded', 'turn_id' => $turnId]`

2. The `ProcessRuntimeTurnJob` receives `status: yielded` and:
   - Sends a progress update to the channel: "Still working on this — I'll report back when done."
   - Dispatches a **`ResumeRuntimeTurnJob`** with a short delay (5s)
   - Returns (frees the worker for the 5s delay window)

3. `ResumeRuntimeTurnJob` is dispatched on the **same queue** (`agent`) with the same session ID. Because of session affinity (documented requirement for wrapper mode), it will land on the same worker.
   - It calls `SessionProcessManager::resumeReadTurnResponse($turnId, $remainingTimeout)` which:
     - Loads buffered fragments from Redis
     - Continues reading from stdout until `turn_complete` or next yield
   - If complete: dispatches `RuntimeTurnCompletedJob` on `messenger-default` queue
   - If yield again: re-dispatches itself

4. `RuntimeTurnCompletedJob` (on `messenger-default`) sends the final response to the channel.

#### Config

```php
// config/runtime.php → cli
'yield_after_seconds' => (int) env('RUNTIME_YIELD_AFTER', 120),
'yield_enabled' => (bool) env('RUNTIME_YIELD_ENABLED', false),
```

`yield_enabled` defaults to `false` — **existing blocking behaviour is the default**. Yield is opt-in.

### Files

| File | Change type |
|------|-------------|
| `config/runtime.php` | New config keys |
| `app/Services/Runtime/SessionProcessManager.php` | New: `resumeReadTurnResponse()`, yield logic in `readTurnResponse()` |
| `app/Jobs/Runtime/ProcessRuntimeTurnJob.php` | Handle `status: yielded` → dispatch ResumeRuntimeTurnJob |
| `app/Jobs/Runtime/ResumeRuntimeTurnJob.php` | **New job**: resume reading, re-yield or complete |
| `app/Jobs/Runtime/RuntimeTurnCompletedJob.php` | **New job**: send result to channel (extracted from ProcessRuntimeTurnJob) |

### Risk assessment

**Low.** Behind `yield_enabled` feature flag (default false). When disabled, zero code path changes. The response-sending logic in `RuntimeTurnCompletedJob` is extracted from existing `ProcessRuntimeTurnJob::handle()` (DRY, not new logic).

### Session affinity note

The wrapper architecture already requires session affinity (documented in `SessionProcessManager` class docblock). For yield to work, the `agent` queue must be configured so that a session's jobs consistently route to the same worker. With `maxProcesses: 1` on the `agent` supervisor (single worker), this is guaranteed. With multiple workers, a hash-based routing strategy is needed (future enhancement).

---

## Phase 3: Sub-Agent Dispatch + Announce-Back

### Goal

The messenger agent can spawn independent sub-agents for long-running work. Sub-agents run in their own sessions and announce results back to the parent's channel when complete.

### OpenClaw reference

| OpenClaw concept | File | Agent Ops equivalent |
|---|---|---|
| `sessions_spawn` tool | `src/agents/subagent-spawn.ts` (`spawnSubagentDirect()`) | New `SubAgentSpawner` service |
| `SubagentRunRecord` registry | `src/agents/subagent-registry.ts` (in-memory Map) | `runtime_sessions` table with `parent_session_id` |
| Announce flow | `src/agents/subagent-announce.ts` (`deliverSubagentAnnouncement()`) | `SubAgentCompletionJob` on messenger queue |
| Concurrency limits | `src/config/agent-limits.ts` (`maxConcurrent`, `maxSpawnDepth`, `maxChildrenPerAgent`) | Config in `runtime.php` |
| `/subagents` commands | `src/auto-reply/reply/commands-subagents/*.ts` | New slash command handler |

### 3.1 Data model changes

**Migration**: Add to `runtime_sessions`:

```php
$table->uuid('parent_session_id')->nullable()->index();
$table->unsignedTinyInteger('spawn_depth')->default(0);
$table->foreign('parent_session_id')->references('id')->on('runtime_sessions')->nullOnDelete();
```

### 3.2 SubAgentSpawner service

**File**: `app/Services/Runtime/SubAgentSpawner.php`

```php
class SubAgentSpawner
{
    public function spawn(
        RuntimeSession $parentSession,
        string $task,
        ?string $label = null,
        ?string $model = null,
    ): array  // ['status' => 'spawned', 'child_session_id' => string] or ['status' => 'rejected', 'reason' => string]
```

**Logic**:
1. Check concurrency: count active children for parent (`max_children_per_session`, default 4)
2. Check depth: `$parentSession->spawn_depth` < `max_spawn_depth` (default 1)
3. Create child `RuntimeSession`:
   - `parent_session_id` = parent's ID
   - `spawn_depth` = parent's + 1
   - `chat_session_id` = parent's (same channel for announce-back)
   - `user_id`, `team_id` = parent's
   - `status` = `active`
4. Dispatch `ProcessRuntimeTurnJob` for the child on the `subagent` queue:
   - `runtimeSessionId` = child ID
   - `userMessage` = task
   - `chatSessionId` = parent's chat session
   - `connectorAccountId` = parent's connector
   - `placeholderMessageId` = null (sub-agents don't edit the parent's placeholder)
5. Return `['status' => 'spawned', 'child_session_id' => $child->id]`

### 3.3 Sub-agent queue

**File**: `config/horizon.php`

Add a new supervisor for sub-agent work:

```php
'supervisor-subagent' => [
    'connection' => 'redis',
    'queue' => ['subagent'],
    'balance' => 'auto',
    'autoScalingStrategy' => 'time',
    'maxProcesses' => max(1, min(4, (int) env('HORIZON_SUBAGENT_MAX_PROCESSES', 2))),
    'maxTime' => 0,
    'maxJobs' => 0,
    'memory' => 128,
    'tries' => 1,
    'backoff' => 0,
    'timeout' => (int) env('HORIZON_SUBAGENT_TIMEOUT', 3600),
    'nice' => 0,
],
```

Sub-agent turns dispatch on `subagent` queue, not `agent`, so they don't compete with the parent session's workers.

### 3.4 Announce-back on completion

**File**: `app/Jobs/Runtime/ProcessRuntimeTurnJob.php`

After the turn completes (success or failure), check if the session has a `parent_session_id`. If so, dispatch `SubAgentCompletionJob`:

```php
if ($session->parent_session_id !== null) {
    SubAgentCompletionJob::dispatch(
        childSessionId: $session->id,
        parentSessionId: $session->parent_session_id,
        status: $result['status'],
        text: $result['text'] ?? null,
        error: $result['error'] ?? null,
        label: $session->title,
    );
}
```

**File**: `app/Jobs/Runtime/SubAgentCompletionJob.php` (new)

- Queue: `messenger-default` (quick delivery, no long blocking)
- Loads the parent session's `chat_session_id` and `connector_account_id`
- Sends an announce message to the parent's channel:
  ```
  ✅ Sub-agent completed: {label}
  Status: {status}
  Duration: {duration}

  {result text, truncated to 2000 chars}
  ```
- For failures: `❌ Sub-agent failed: {label} — {error}`
- Updates child session status to `ended`

### 3.5 Slash commands

**File**: `app/Services/Messenger/SlashCommands/SubAgentsCommand.php` (new)

Register in the slash command router.

| Command | Action |
|---------|--------|
| `/subagents list` | List active + recent sub-agents for current session |
| `/subagents spawn <task>` | Spawn a sub-agent with the given task |
| `/subagents kill <id>` | Terminate a sub-agent (calls `RuntimeSessionManager::stopSession`) |
| `/subagents log <id>` | Show last N turns/output for a sub-agent session |

### 3.6 Config

```php
// config/runtime.php
'subagents' => [
    'enabled' => (bool) env('RUNTIME_SUBAGENTS_ENABLED', false),
    'max_concurrent_per_session' => (int) env('RUNTIME_SUBAGENT_MAX_CONCURRENT', 4),
    'max_spawn_depth' => (int) env('RUNTIME_SUBAGENT_MAX_DEPTH', 1),
    'default_timeout_seconds' => (int) env('RUNTIME_SUBAGENT_TIMEOUT', 1800),
    'queue' => env('RUNTIME_SUBAGENT_QUEUE', 'subagent'),
],
```

### Files (Phase 3)

| File | Change type |
|------|-------------|
| Migration: add `parent_session_id`, `spawn_depth` to `runtime_sessions` | New migration |
| `app/Models/Runtime/RuntimeSession.php` | Add fillable fields, `parent()` / `children()` relationships |
| `app/Services/Runtime/SubAgentSpawner.php` | **New service** |
| `app/Jobs/Runtime/SubAgentCompletionJob.php` | **New job** |
| `app/Jobs/Runtime/ProcessRuntimeTurnJob.php` | Announce-back check after turn completion |
| `app/Services/Messenger/SlashCommands/SubAgentsCommand.php` | **New command handler** |
| `config/runtime.php` | New `subagents` config block |
| `config/horizon.php` | New `supervisor-subagent` supervisor |

### Risk assessment

**Low.** Behind `subagents.enabled` feature flag (default false). The only change to existing code is the announce-back check in `ProcessRuntimeTurnJob` which is guarded by `$session->parent_session_id !== null` (always null for existing sessions). New migration is additive (nullable columns).

---

## Delivery Order

```
Phase 1 (1-2 days)  → Fixes the immediate timeout failure
    ↓
Phase 2 (2-3 days)  → Yield-based execution for very long turns
    ↓
Phase 3 (3-5 days)  → Sub-agent dispatch + announce-back
```

Phase 1 can ship immediately and alone. Phase 2 requires Phase 1's higher timeout. Phase 3 is independent of Phase 2 but benefits from it.

## What This Fixes

**RevenueCat scenario (screenshot)**: With Phase 1 alone, the job application task gets 1800s (30 min) instead of 300s, and the user sees "Working on it… (2m elapsed)" instead of silence. With Phase 3, the agent spawns a sub-agent: "I'm working on the RevenueCat application — I'll report back when done" and the sub-agent announces the result when complete.

## OpenClaw Parity Summary

| OpenClaw feature | Agent Ops Phase | Parity level |
|---|---|---|
| `exec` foreground with high timeout | Phase 1 | Full (1800s default) |
| `exec` background via `yieldMs` | Phase 2 | Adapted (yield-to-queue, not in-memory) |
| `process poll/log/list/kill` | Phase 2+3 | Partial (sub-agent list/kill; no generic process registry) |
| `sessions_spawn` non-blocking | Phase 3 | Full |
| Announce-back on completion | Phase 3 | Full |
| `notifyOnExit` system events | Phase 3 | Adapted (SubAgentCompletionJob) |
| `/subagents` slash commands | Phase 3 | Core subset (list, spawn, kill, log) |
| Lane-based concurrency | Phase 3 | Adapted (Horizon supervisor queues) |
| `maxSpawnDepth` / `maxChildrenPerAgent` | Phase 3 | Full |
| Draft streaming (send + edit) | Phase 1 | Partial (heartbeat edits, not full content streaming) |

## Pre-Implementation Checklist

- [ ] Verify `agent` queue session affinity (single worker or hash routing) for Phase 2
- [ ] Confirm Horizon restart behaviour after config changes
- [ ] Review whether `CliRuntimeExecutor` (non-wrapper path) also needs progress callback
- [ ] Decide: should Phase 3 `/subagents spawn` auto-detect long tasks, or manual only?
