# Plan: Persistent Messenger Sessions + Runner Selection

## Goals

1. **Persistent CLI process per messenger session** so that:
   - Conversation context is maintained across messages.
   - Long-running tasks (research, code analysis, business workflows) are possible with no (or very high) timeout.
   - Compaction and auto-compactification are used (summary + recent messages injected as context).
   - `/new` gives users explicit control to start a fresh conversation (new process, new context).

2. **Runner selection when creating agents** so that users can choose claude / codex / custom in both UI and CLI. This includes:
   - **Scheduled agent jobs** (AgentJob): runner type in Job form and in any CLI that creates jobs.
   - **Messenger connectors**: when creating or editing a connector (UI or `agent:install`), the user selects the **runner type** (claude / codex / custom) so that when chatting via that connector, the correct CLI process is used.

3. **Memory artifacts from messenger chat** so that conversation and turn content from messenger/runtime sessions are persisted into the memory layers (conversation logs, embeddings, graph when API mode is on).

---

## Current State (Problems)

| Area | Current behaviour | Problem |
|------|-------------------|--------|
| **Routing** | Free-form messages without an active runtime session go to **GeneralTaskHandler**: one new CLI process per message (`claude -p` with single prompt). Messages with an active runtime session go to **ProcessRuntimeTurnJob** → **CliRuntimeExecutor**: still **one process per turn** (temp file, run, exit). | No conversation context, no long-running tasks, timeouts (45s / 60s) kill requests. |
| **Context** | General task sends only the current user message. Runtime turn sends only the current user message (temp file). | Compaction summary and session history are never passed to the agent. |
| **/new** | Stops current RuntimeSession and creates a new one. No process is running yet; next message starts a new turn (new process). | /new is correct semantically but doesn’t “kill” a long-lived process because we don’t have one. |
| **Agent creation** | **UI**: JobForm has Runner Type dropdown (claude/codex/custom) and submits it. **CLI**: No artisan command to create agent jobs; only API. | If the UI hides the field or doesn’t pass it in some flows, or if users create jobs via API/script, runner_type might be missing or wrong. Need to verify and add CLI creation with runner choice. |
| **Memory** | **Scheduled runs only**: Working Memory is filled by `RunEventWriter` during **ExecuteAgentRunJob** (agent_job_run_id). **MemoryFormationJob** is dispatched when a run reaches terminal status; **MemoryFormationPipeline** reads Working Memory, persists to `memory_conversation_logs`, then extraction/embeddings/graph. **Runtime/messenger**: `RuntimeSessionManager::dispatchMemoryFormation` only writes a markdown file to `storage/app/memory/context/runtime/{id}.md`; that file is **not** ingested into any memory layer. No conversation logs, embeddings, or graph entries are created from messenger chat. | Messenger/runtime conversations never become memory artifacts; retrieval and long-term memory only reflect scheduled job runs. |

---

## Part 1: Persistent Session Process

### 1.1 Design: One process per RuntimeSession

- **Owner**: One long-lived CLI process per **RuntimeSession** (and thus per messenger chat until `/new`).
- **Lifecycle**:
  - **Start**: When the first message is routed to runtime for that chat, we **get-or-create** RuntimeSession, then **start** the session process (or attach to an existing one). Process stays alive until `/stop` or `/new`.
  - **Turn**: Each user message is sent into the process (e.g. written to stdin or via a simple protocol). Response is read from stdout and streamed to Discord. No process exit between turns.
  - **Stop**: `/new` or `/stop` stops the RuntimeSession and **terminates** the process.

- **Context for each turn (Phase 2 – long-lived process):**
  - The process retains conversation context in memory. **Only the new user message** is sent into the process (no need to inject summary + recent messages each turn). Optionally run **CompactionService::compactIfNeeded** when context is running low (same as Phase 1 rule: only when thresholds exceeded).
  - (Phase 1 uses per-turn context injection in `CliRuntimeExecutor` because each turn is a new process with no memory; Phase 2 removes that need.)

- **Timeout**: No (or very high) process timeout while the session is active. Only explicit stop or crash ends the process.

### 1.2 CLI capability: session vs one-shot

Current CLIs (claude, codex) are used **one-shot**: one prompt per invocation, then exit. To keep one process and send multiple messages we need one of:

- **A) REPL/session mode** (if supported): CLI reads prompts from stdin in a loop and writes responses to stdout. We’d start the process once and pipe messages in/out.
- **B) Wrapper/sidecar**: A small process (script or binary) that:
  - Starts the real CLI (or uses the API) and maintains conversation state.
  - Exposes a simple protocol (e.g. stdin: one JSON object per message; stdout: streamed response then “turn complete”).
  - We start the wrapper once per session and communicate via its stdin/stdout.
- **C) API + stored history (fallback)**: No persistent process. Each “turn” we call the Anthropic/OpenAI API with **full conversation history** (compaction summary + recent messages) and stream the response. Context is preserved in the app, not in a process. Less ideal than A/B but achievable without CLI changes.

**Recommendation**: Implement in two phases.

- **Phase 1 – Context without persistent process (fast win)**
  - Route **all** free-form messenger chat to runtime (get-or-create session per chat).
  - Keep **one process per turn** but pass **conversation context** into each turn: compaction summary + recent N messages from `ChatSessionManager::getSessionHistory` and `getCompactionSummary`.
  - Build a single “context block” (summary + recent transcript) and prepend to the task file (or system prompt) for `CliRuntimeExecutor`.
  - Run compaction (sync or async) **only when context is running low** (see task 3 below).
  - Effect: Context and compaction are used; long-running **single** turn still limited by current timeout; multi-turn conversations gain context.
  - **Note:** This per-turn context injection is a Phase 1 workaround because each turn starts a new process with no memory. In Phase 2 it is not needed (see below).

- **Phase 2 – Persistent process (required)**
  - Long-running sessions that **maintain state and context in-process** require **one long-lived process per RuntimeSession**. Without this, the design is incomplete.
  - Research (done): claude/codex do not support a headless stdin-loop; we need a **wrapper** (see `docs/discovery/runtime-cli-session-repl-capability.md`).
  - Implement **SessionProcessManager** so it starts **one wrapper process per RuntimeSession**, writes messages to stdin, reads streamed output from stdout, and streams to the messenger. On `/new` and `/stop`: **terminate** the process.
  - **Context:** The long-lived process **retains conversation context in memory**. Only the new user message is sent each turn; no per-turn context injection.
  - **Phase 2 "light" (session-resume)** is an interim step (already implemented). **Phase 2 full** (wrapper + one process per session) is **required** for long-running sessions with in-process state and context.

### 1.3 Implementation tasks (Part 1)

**Phase 1 – Context and routing (no persistent process yet)**

1. **Route all free-form chat to runtime**
   - In `ProcessChatIntent`, after slash-command handling and before intent parsing for “general task”:
     - For messages that would otherwise be parsed as GENERAL_TASK (or that are not a structured action), **get-or-create** RuntimeSession for this chat (same as `/ask` flow).
     - Dispatch **ProcessRuntimeTurnJob** with the message content and send “Thinking…” / “Processing request…”.
     - Remove (or narrow) the path that sends free-form messages to **GeneralTaskHandler** so that conversational messages always go through runtime and get session + context.
   - Ensure **ProcessRuntimeTurnJob** receives `chat_session_id` and `connector_account_id` so it can load ChatSession and run compaction / fetch history.

2. **Inject conversation context into each turn**
   - In **CliRuntimeExecutor** (or in **MessengerRuntimeOrchestrator** before calling it):
     - Load **ChatSession** from the runtime session’s `chat_session_id`.
     - Call **CompactionService::compactIfNeeded** (sync) for that ChatSession (optional instructions can be null).
     - Get **ChatSessionManager::getCompactionSummary** and **getSessionHistory** (with a sensible limit, e.g. 20).
     - Build a context string: e.g. “Previous context (summary): …” + “Recent messages: …” (formatted as User/Assistant lines).
     - Prepend this to the user message in the temp task file (or pass as a separate “system” or “context” block if the runner supports it).
   - Ensure **RuntimeTurn** and **ChatMessage** are still created/updated so compaction and history remain accurate.

3. **Auto-compaction (only when context is running low)**
   - **Do not** dispatch compaction after every turn unconditionally. Only trigger compaction when context is running low (e.g. message count or estimated tokens exceed the same thresholds used by `CompactionService`: `messenger.compaction.trigger_message_count`, `messenger.compaction.trigger_estimated_tokens`).
   - After a completed turn (in **ProcessRuntimeTurnJob**), if the linked ChatSession exists: check whether compaction is needed (e.g. use `ContextUsageEstimator::estimate` or a small `CompactionService::isCompactionNeeded(ChatSession)`-style check). Only then dispatch **CompactionJob** for that ChatSession. This avoids unnecessary queue jobs and keeps compaction on-demand.
   - Keep existing **/compact** slash command as manual trigger.

4. **Timeouts**
   - For Phase 1, keep **ProcessRuntimeTurnJob** and **CliRuntimeExecutor** timeouts high (e.g. 300s or configurable) so long single-turn tasks don’t get killed. Document that true “no timeout” requires Phase 2 (persistent process).

**Phase 2 – Persistent process (required)**

5. **Research** (done)
   - Documented in `docs/discovery/runtime-cli-session-repl-capability.md`: CLIs do not support headless stdin-loop; a **wrapper** is required for one process per session.

6. **Wrapper** (implemented)
   - `bin/session-wrapper` PHP script: started once per RuntimeSession; reads JSON messages from stdin; invokes CLI with `--resume` for continuity; streams output to stdout; writes `turn_complete` marker. Config: `runtime.cli.wrapper_enabled`, `runtime.cli.wrapper_idle_timeout`.

7. **Session process manager (full)** (implemented)
   - `SessionProcessManager` extended with `startWrapper()`, `sendMessage()`, `terminateSession()`, `hasActiveWrapper()`, `isWrapperEnabled()`. Manages wrapper process lifecycle via `proc_open`; reads turn responses from stdout pipes; extracts runner session ID; handles process crash detection.

8. **Orchestrator + job integration** (implemented)
   - `MessengerRuntimeOrchestrator::executeTurn()` routes through wrapper when `runtime.cli.wrapper_enabled` is true; auto-starts wrapper on first message via `executeViaWrapper()`; falls back to direct `CliRuntimeExecutor` when wrapper disabled.

9. **Stop / new** (implemented)
   - `RuntimeSessionManager::stopSession()` terminates the wrapper process (if running) via `SessionProcessManager::terminateSession()`; clears cache and runner session ID.

10. **Tests** (implemented)
    - `SessionProcessManagerWrapperTest` (8 tests): wrapper config, active detection, start/terminate lifecycle, message routing.
    - `MessengerRuntimeOrchestratorWrapperTest` (3 tests): wrapper routing, fallback, start failure.

---

## Part 2: Runner selection when creating agents

### 2.1 UI (AgentJob – scheduled jobs)

- **Current**: `JobForm.vue` has a “Runner Type” dropdown (claude, codex, custom) and submits `runner_type` in the payload. `StoreAgentJobRequest` validates `runner_type` in `['claude','codex','custom']`.
- **Tasks**:
  1. **Verify** that on **Create** and **Edit** the Runner Type field is visible and that the saved job has the selected `runner_type` (check DB and API response).
  2. If any Create/Edit flow uses a different form or doesn’t bind `runner_type`, add or fix the binding so the user can always select the runner.

### 2.2 Messenger connectors (runner type for chat)

- **Current**: Runtime uses a single global `config('runtime.cli.runner_type')` for all messenger chat. Connector accounts do not store or expose runner type.
- **Tasks** (implemented):
  1. **Connector config**: Store `runner_type` (claude | codex | custom) in each connector’s `config` (e.g. `config.runner_type`). Validate in API store/update; expose in connector resource.
  2. **Runtime**: When processing a turn, load the connector account and use `config['runner_type']` if set; otherwise fall back to global config. Pass the override into `MessengerRuntimeOrchestrator::executeTurn` and `CliRuntimeExecutor::executeTurn`.
  3. **agent:install**: Add `--runner-type=claude|codex|custom` and/or an interactive “Runner type (CLI for chat)” prompt when configuring each connector. Persist `runner_type` into the connector’s config when creating or updating.
  4. **UI (Tools → Messenger)**: On “Add connector” and “Edit connector”, show a “Runner type (CLI for chat)” dropdown (Claude / Codex / Custom). Send and load `config.runner_type` in the API payload and response.

### 2.3 CLI (AgentJob creation)

- **Current**: There is no artisan command to create agent jobs; creation is via API only.
- **Tasks**:
  1. Add an artisan command (e.g. `agent:job:create` or `agent:jobs:create`) that:
     - Accepts required job fields (name, schedule, etc.) and **optional** `--runner-type` (values: claude, codex, custom; default from config or e.g. codex).
     - Calls the same validation and creation logic as the API (or reuses a service) and creates the job for the given user (e.g. `--user=id` or current user).
  2. Document the command and the `--runner-type` option in AGENTS.md or the docs.

### 2.4 Other “agents” (if applicable)

- **Delegation profiles** (DelegateeProfile): Already have `runner_type` and UI (ProfileForm.vue) with runner selection; verify it’s saved and used.
- **Org agents** (OrgAgentProfile): If they have a runner or template, ensure runner type (or equivalent) is selectable in the UI and any CLI.

---

## Verification

1. **Context and compaction**
   - Send several messages in one chat; then ask something that requires prior context (e.g. “What was the first thing I asked?”). Expect correct answer.
   - Trigger compaction (many messages or /compact); send another message; expect behaviour consistent with summary + recent messages.

2. **/new**
   - Send messages, then `/new`, then ask about the previous topic. Expect no memory of pre-/new messages (new session, new context).

3. **Long-running**
   - Phase 1: A single long turn (e.g. “analyze this repo”) should complete if within the turn timeout (e.g. 300s).
   - Phase 2 full (required): Multi-step long-running task over several messages completes without per-message timeout; one process per session maintains state and context.

4. **Runner selection**
   - Create an agent job from the UI with runner type “claude”; verify DB and list/detail show claude. Repeat for codex and custom.
   - Create an agent job via `agent:job:create --runner-type=claude` (or equivalent); verify the job has `runner_type=claude`.

5. **Memory artifacts**
   - Chat via messenger over several turns; end the session with `/stop` or `/new`. Verify `memory_conversation_logs` (and, if API mode on, embeddings/graph) contain entries for that runtime session and user.

---

## File / component checklist (summary)

| Item | Location / action |
|------|--------------------|
| Route free-form chat to runtime | `ProcessChatIntent` |
| Get-or-create session for chat | Already via `RuntimeSessionManager::getOrCreateSessionForChat` |
| Load ChatSession, run compaction, get history | `ProcessRuntimeTurnJob` or `MessengerRuntimeOrchestrator` / `CliRuntimeExecutor` |
| Build context string (summary + history) | New helper or inside executor/orchestrator |
| Prepend context to turn input (Phase 1 only) | `CliRuntimeExecutor::executeTurn` (task file or template); Phase 2 skips (process retains context) |
| Dispatch CompactionJob only when context low | `ProcessRuntimeTurnJob`: check thresholds (e.g. ContextUsageEstimator / isCompactionNeeded), then dispatch job |
| SessionProcessManager (Phase 2) | New service + integration in job/orchestrator |
| Stop process on session stop | `RuntimeSessionManager::stopSession` + SessionProcessManager |
| Verify JobForm runner_type | `JobForm.vue`, Create.vue, Edit.vue, API store/update |
| Connector runner_type (config + API) | `MessengerConnectorResource`, store/update validation |
| Runtime use connector runner_type | `ProcessRuntimeTurnJob`, `MessengerRuntimeOrchestrator`, `CliRuntimeExecutor` |
| agent:install runner type | `AgentInstallCommand`: option + prompt, merge into config |
| Messenger UI runner type | `Tools/Messenger/Index.vue`: connect form + edit form |
| Artisan command create job | New command, `--runner-type` option |
| Memory: schema for runtime_session_id | Migration, `MemoryConversationLog` model |
| Memory: processRuntimeSession / pipeline | `MemoryFormationPipeline` or new pipeline class |
| Memory: RuntimeMemoryFormationJob + dispatch | New job; `RuntimeSessionManager::stopSession` |

---

## Dependencies and order

- Phase 1 can be done first and delivers context + compaction + routing without a persistent process.
- Phase 2 depends on CLI/wrapper capability and adds the persistent process and “no timeout” behaviour.
- Runner selection (Part 2) is independent and can be done in parallel or first.

Suggested order: **Part 2 (runner selection)** → **Phase 1 (context + routing)** → **Part 3 (memory)** → **Phase 2 (persistent process)** after research.

---

## Part 3: Memory artifacts from messenger/runtime

### 3.1 Current gap

- **Memory layers** are fed only by **scheduled agent runs**:
  - **Working Memory**: `RunEventWriter::appendOutput()` dispatches `MemoryWorkingBufferJob` with `run_id` (agent_job_run_id) during a run.
  - **Formation**: When the run reaches terminal status, `ExecuteAgentRunJob` dispatches `MemoryFormationJob($run->id)`. The pipeline reads Working Memory by `run_id`, persists to `memory_conversation_logs` (with `run_id`, `job_id`), then runs extraction, embeddings, and graph storage (when API mode is on).
- **Runtime/messenger**:
  - `RuntimeSessionManager::dispatchMemoryFormation(RuntimeSession)` is called when a session is **stopped**. It only writes a markdown file (`storage/app/memory/context/runtime/{session_id}.md`) with turn summaries. Nothing reads that file into `memory_conversation_logs`, `memory_embeddings`, or the graph.
  - No Working Memory is written for runtime turns; no `MemoryFormationJob` is dispatched for runtime sessions.
- **Result**: Chats via messenger produce no memory artifacts. Retrieval and long-term memory only reflect scheduled job runs.

### 3.2 Goal

When users chat with agents via messenger (runtime turns), the conversation should be persisted into the same memory layers:

- **Conversation logs**: Persist user/assistant turn content (and optionally tool/thinking) to the long-term conversation store so it can be searched and used for retrieval.
- **API mode**: When memory API is enabled, run the same extraction, importance scoring, embeddings, and graph storage so messenger conversations contribute to semantic and graph memory.

### 3.3 Design options

- **Option A – Extend pipeline for runtime sessions**
  - **Schema**: Extend `memory_conversation_logs` to support a runtime source: e.g. nullable `run_id`/`job_id` plus `runtime_session_id` (UUID FK to `runtime_sessions`). Same for `memory_embeddings` / formation failures if they are run-scoped (add optional `runtime_session_id` or a generic `source_type`/`source_id`).
  - **Pipeline**: Add a method (e.g. `processRuntimeSession(RuntimeSession $session)`) that:
    - Builds “working memory” entries from the session’s **RuntimeTurns** (user message + assistant summary/text) and optionally from **ChatMessage** rows for that chat session (for full fidelity).
    - Persists those entries to `memory_conversation_logs` with `runtime_session_id` set and `run_id`/`job_id` null (or as per schema choice).
    - Runs the same extraction, importance, embedding, and graph steps as the run-based pipeline, scoped by user and `runtime_session_id` (or source_id).
  - **Trigger**: Dispatch a job (e.g. `RuntimeMemoryFormationJob`) when a runtime session is **stopped** (in `RuntimeSessionManager::stopSession`), or after each completed turn (lighter weight but more jobs). Recommended: on session stop, to batch the full conversation.

- **Option B – Synthetic run**
  - Create a synthetic `AgentJobRun` (and maybe `AgentJob`) per runtime session for memory only; write turn content into Working Memory keyed by that run_id; dispatch existing `MemoryFormationJob`. Would require not listing synthetic runs in the UI and could complicate run/job semantics. Not recommended.

### 3.4 Implementation tasks (Part 3)

1. **Schema**
   - Migration: add `runtime_session_id` (nullable UUID, FK to `runtime_sessions`) to `memory_conversation_logs`. Make `run_id` and `job_id` nullable (or add a check constraint so exactly one of run_id or runtime_session_id is set). Update `MemoryConversationLog` model and any unique/sequence logic (e.g. sequence per runtime_session_id when run_id is null).
   - If embeddings and formation failures are run-only, add optional `runtime_session_id` (or `source_type`/`source_id`) so formation can be keyed by runtime session.

2. **Pipeline**
   - Add `processRuntimeSession(RuntimeSession $session)` (or a dedicated `RuntimeMemoryFormationPipeline`) that:
     - Loads the session’s turns (and optionally the linked ChatSession’s messages) and builds an array of entries in the same shape as Working Memory entries (role, content, metadata, timestamp).
     - Persists to `memory_conversation_logs` with `runtime_session_id` and user_id; run_id/job_id null.
     - Reuses (or duplicates) the same extraction, importance, embedding, and graph logic as the run-based pipeline, scoped by user and runtime_session_id.

3. **Job and trigger**
   - Add **RuntimeMemoryFormationJob** that accepts `runtime_session_id`, loads the session, and calls the new pipeline method. Dispatch it from **RuntimeSessionManager::stopSession** after updating the session status (and after the existing “write markdown file” step, or replace that with this path). Guard with the same feature flag as memory (`FeatureFlagManager::MEMORY_ENABLED`).

4. **Verification**
   - Chat via messenger over several turns; call `/stop` or `/new` to end the session. Assert that `memory_conversation_logs` contains entries for that runtime_session_id and user. With API mode on, assert that embeddings (and graph, if configured) include content from the messenger conversation.

### 3.5 File / component checklist (Part 3)

| Item | Location / action |
|------|--------------------|
| Migration: runtime_session_id on memory_conversation_logs | New migration; nullable run_id/job_id or constraint |
| MemoryConversationLog: support runtime_session_id | Model, getNextSequence (or equivalent for session), scopes |
| processRuntimeSession (or RuntimeMemoryFormationPipeline) | New method or class in Support/Memory |
| RuntimeMemoryFormationJob | New job; dispatch from RuntimeSessionManager::stopSession |
| Feature flag guard | Same as MEMORY_ENABLED in dispatch path |
