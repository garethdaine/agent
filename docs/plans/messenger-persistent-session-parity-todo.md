# Plan parity: Persistent Messenger Sessions + Runner Selection

Analysis of `docs/plans/messenger-persistent-session-and-runner-selection.md` against the current codebase, and a concrete todo list to reach full parity.

---

## Summary: plan vs built

| Area | Plan | Built | Parity |
|------|------|--------|--------|
| **Phase 1** – Context and routing | Route free-form to runtime; inject context; auto-compact when low; timeouts | Yes | Done |
| **Part 2** – Runner selection | JobForm, connector, agent:job:create, Delegatee/OrgAgent | Yes | Done (verify 2.1, 2.4) |
| **Part 3** – Memory artifacts | Schema, processRuntimeSession, RuntimeMemoryFormationJob, dispatch on stop | Yes | Done |
| **Phase 2 light** – Session-resume | Store runner session ID; --resume; skip context when resumed | Yes | Done |
| **Phase 2 full** – Persistent process | Wrapper + one process per session; SessionProcessManager owns process; stop terminates | Yes | Done |

---

## Phase 1 (Context and routing) – done

| Task | Location | Status |
|------|----------|--------|
| 1. Route all free-form chat to runtime | `ProcessChatIntent`: GENERAL_TASK → getOrCreateSessionForChat + ProcessRuntimeTurnJob; active runtime session also routes to runtime | Done |
| 2. Inject conversation context | `CliRuntimeExecutor::buildTaskContent`: compactIfNeeded, getCompactionSummary, getSessionHistory (limit 20), prepend to task file | Done |
| 3. Auto-compaction only when low | `ProcessRuntimeTurnJob::handle`: after turn, `isCompactionNeeded($chatSession)` then dispatch CompactionJob | Done |
| 4. Timeouts | `config('runtime.cli.timeout_seconds', 300)`; ProcessRuntimeTurnJob uses it | Done |

---

## Part 2 (Runner selection) – done, verify

| Task | Location | Status |
|------|----------|--------|
| 2.1 Verify JobForm runner_type on Create/Edit | JobForm.vue, Create.vue, Edit.vue, StoreAgentJobRequest | Implemented; **verify** UI and API persist runner_type |
| 2.2 Connector runner_type | config, API, ProcessRuntimeTurnJob → orchestrator → executor, agent:install, Messenger Index.vue (connect + edit) | Done |
| 2.3 agent:job:create | AgentJobCreateCommand (--runner-type), AgentJobCreateCommandTest, AGENTS.md | Done |
| 2.4 DelegateeProfile / OrgAgentProfile | ProfileForm.vue, DelegateeProfileCreateModal; Org Agents Form | Implemented; **verify** runner_type saved and used |

---

## Part 3 (Memory artifacts) – done

| Task | Location | Status |
|------|----------|--------|
| Schema | Migration `runtime_session_id` on memory_conversation_logs; MemoryConversationLog::runtimeSession(), scopeForRuntimeSession(), getNextSequenceForRuntimeSession() | Done |
| Pipeline | MemoryFormationPipeline::processRuntimeSession(); persist logs, then extraction/embedding/graph when MEMORY_API_ENABLED | Done |
| Job + trigger | RuntimeMemoryFormationJob; RuntimeSessionManager::stopSession → dispatch after context file write; MEMORY_ENABLED guard | Done |

---

## Phase 2 light (Session-resume) – done

| Task | Location | Status |
|------|----------|--------|
| SessionProcessManager (cache) | getRunnerSessionId, setRunnerSessionId, clearSession; cache key per runtime_session_id | Done |
| CliRuntimeExecutor | Optional runnerSessionId; when set: task = user message only, --resume in command; extractRunnerSessionId from stream-json | Done |
| Orchestrator | Get runner session id before turn; pass to executor; set from result after success | Done |
| stopSession | RuntimeSessionManager::stopSession calls sessionProcessManager->clearSession() | Done |
| Config | runtime.cli.session_resume | Done |

---

## Phase 2 full (Persistent process) – done

| Plan task | Expected | Built | Status |
|-----------|----------|-------|--------|
| **6. Wrapper** | Binary/script: one process per RuntimeSession; stdin = messages; stdout = streamed response + turn-complete | `bin/session-wrapper` PHP script; reads JSON stdin, invokes CLI with --resume, streams stdout, writes turn_complete marker | Done |
| **7. Session process manager (full)** | Map session id to running wrapper process; start/send/read/terminate | `SessionProcessManager`: startWrapper(), sendMessage(), terminateSession(), hasActiveWrapper(), isWrapperEnabled(); proc_open lifecycle | Done |
| **8. Orchestrator integration** | Route through wrapper when enabled; fallback to executor | `MessengerRuntimeOrchestrator::executeViaWrapper()`; auto-starts wrapper on first message; wrapper_enabled config toggle | Done |
| **9. Stop / new** | stopSession terminates wrapper process | `RuntimeSessionManager::stopSession()` calls terminateSession() when wrapper active | Done |
| **10. Tests** | Unit tests for wrapper flow | SessionProcessManagerWrapperTest (8), MessengerRuntimeOrchestratorWrapperTest (3) | Done |

---

## Parity todo list (to reach full parity)

### Verification (quick)

- [x] **V1** Verify JobForm: Create and Edit job with runner_type claude/codex/custom; confirm DB and API show selected runner_type.
- [x] **V2** Verify DelegateeProfile / OrgAgentProfile: runner_type saved and used where applicable.

### Phase 2 full (required for parity)

- [x] **P2-1** **Wrapper**: Implement or adopt a wrapper process (script or binary) that:
  - Is invoked once per RuntimeSession (arguments or env: session id, runner type).
  - Reads messages from stdin (e.g. one JSON object per line: `{"message": "user text"}`).
  - Streams responses on stdout (e.g. stream-json or simple protocol with turn-complete marker).
  - Internally runs the CLI (or Agent SDK) in a loop so the runner keeps conversation state.
  - Config: e.g. `runtime.cli.wrapper_path`, `runtime.cli.wrapper_enabled` (feature flag).

- [x] **P2-2** **Config**: Add `runtime.cli.wrapper_path` (nullable), `runtime.cli.wrapper_enabled` (bool, default false) so Phase 2 full can be toggled.

- [x] **P2-3** **SessionProcessManager (full)**: Extend (or add) so that when wrapper is enabled:
  - `startSessionProcess(RuntimeSession $session)` (or equivalent): start wrapper process for session; store process handle/key per session.
  - `sendMessage(runtimeSessionId, userMessage)`: enqueue or write message to process stdin.
  - `getStreamedResponse(runtimeSessionId)` / blocking read: read stdout until turn-complete; return streamed chunks or final text.
  - `terminateSession(runtimeSessionId)`: kill wrapper process; remove from map.
  - Handle process crash/exit: mark session failed or optionally restart (policy TBD).

- [x] **P2-4** **ProcessRuntimeTurnJob**: When `runtime.cli.wrapper_enabled` (and session has a running wrapper): send message to SessionProcessManager for that session instead of calling CliRuntimeExecutor; stream or wait for response; send to messenger. When wrapper not enabled, keep current behaviour (executor + session-resume).

- [x] **P2-5** **RuntimeSessionManager**: On first message for a session when wrapper enabled, ensure wrapper process is started (or delegate to job/orchestrator). On `stopSession`, call SessionProcessManager to **terminate** the wrapper process (in addition to clearing runner session id).

- [x] **P2-6** **Tests**: Unit tests for SessionProcessManager (full) start/terminate/send; feature or integration test for “message → wrapper → response” when wrapper is enabled (or mocked).

- [x] **P2-7** **Docs**: Update `docs/discovery/runtime-cli-session-repl-capability.md` and plan with “Phase 2 full implemented” when done; document wrapper protocol and config.

---

## Suggested order

1. **V1, V2** – Verification (can be done anytime).
2. **P2-1** – Wrapper (design + implement or adopt).
3. **P2-2** – Config for wrapper path and enabled flag.
4. **P2-3** – SessionProcessManager (full) process lifecycle and message I/O.
5. **P2-4** – ProcessRuntimeTurnJob: use wrapper path when enabled.
6. **P2-5** – RuntimeSessionManager: start wrapper on first message (if needed), terminate on stop.
7. **P2-6** – Tests.
8. **P2-7** – Docs.

---

## References

- Plan: `docs/plans/messenger-persistent-session-and-runner-selection.md`
- Discovery: `docs/discovery/runtime-cli-session-repl-capability.md`
- SessionProcessManager: `app/Services/Runtime/SessionProcessManager.php`
- CliRuntimeExecutor: `app/Services/Runtime/CliRuntimeExecutor.php`
- ProcessRuntimeTurnJob: `app/Jobs/Runtime/ProcessRuntimeTurnJob.php`
- RuntimeSessionManager: `app/Services/Runtime/RuntimeSessionManager.php`
