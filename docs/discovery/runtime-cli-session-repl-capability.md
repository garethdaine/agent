# Runtime CLI: Session / REPL capability

## Goal

Determine whether the Claude and Codex CLIs support a **single long-lived process per session** that accepts multiple messages (e.g. via stdin) and streams responses (stdout), so we can implement Phase 2 (SessionProcessManager) without a custom wrapper.

## Findings

### Claude Code CLI

- **One-shot (current use)**: `claude -p "prompt"` runs one prompt and exits. We use this with a task file and `--output-format stream-json` for streaming.
- **Session continuity**: Claude supports resuming a conversation via session ID:
  - `--continue` continues the most recent conversation.
  - `--resume "$session_id"` continues a specific conversation.
  - Session ID is returned in JSON output (e.g. `--output-format json` → `.session_id`).
  - So we can run **one process per turn** but pass `--resume $session_id` so the runner keeps conversation context; we do **not** need to inject compaction summary + recent messages on subsequent turns.
- **No headless stdin-loop**: The docs describe interactive REPL and headless `-p`; there is no documented “read prompts from stdin in a loop and write responses to stdout” for headless/scripted use. So we cannot drive a single long-lived `claude` process with multiple messages without a wrapper or pseudo-TTY.

### Codex / Warp

- Codex in Warp is tied to the IDE (Agent Mode, Conversations). There is no documented standalone CLI that supports session or stdin-loop in the same way.
- The “Codex CLI” we configure in `agent.runner_executables` is typically the local codex binary; its session behaviour was not confirmed in this research.

## Conclusion

- **True one process per session**: Not supported by the CLIs out of the box. Would require either:
  - A **wrapper** process that runs the CLI (or Agent SDK) in a loop and speaks a simple protocol (e.g. stdin: one JSON message per line; stdout: streamed response + end marker), or
  - Using the Agent SDK (Python/TypeScript) with a long-lived loop in our own process.
- **Session-resume (no wrapper)**: Claude supports `--resume $session_id`. We can:
  - Persist the runner’s session ID (from CLI output) per `RuntimeSession`.
  - On subsequent turns, call the CLI with `--resume $session_id` and **only** the new user message (no per-turn context injection).
  - Still one process per turn, but conversation context lives in the runner’s session; we only send the new message.

## Recommendation

1. **Phase 2 “light” (implemented)**: Use **session-resume** for Claude (and any runner that returns a session ID): store runner session ID per runtime session, pass it on the next turn, and skip context injection when we have a session ID. Delivers “no per-turn context injection” and continuity without a wrapper.
   - **Implementation**: `SessionProcessManager` (cache-backed), `CliRuntimeExecutor` accepts optional `runnerSessionId`, injects `--resume` and uses only user message when set; parses `session_id` from stream-json and returns it; `MessengerRuntimeOrchestrator` gets/sets runner session id via manager; `RuntimeSessionManager::stopSession` clears it. Config: `runtime.cli.session_resume`.
2. **Phase 2 “full” (implemented)**: One long-lived wrapper process per RuntimeSession:
   - **Wrapper**: `bin/session-wrapper` (PHP script) started once per session. Reads JSON messages from stdin, invokes the CLI (with `--resume` for continuity), streams output to stdout, and writes a `turn_complete` marker at the end of each turn.
   - **SessionProcessManager**: Extended with `startWrapper()`, `sendMessage()`, `terminateSession()`, `hasActiveWrapper()`, and `isWrapperEnabled()`. Manages process lifecycle via `proc_open` and reads turn responses from stdout pipes.
   - **MessengerRuntimeOrchestrator**: Routes through wrapper when `runtime.cli.wrapper_enabled` is true; auto-starts wrapper on first message, falls back to direct executor when disabled.
   - **RuntimeSessionManager**: `stopSession()` terminates the wrapper process (if running) in addition to clearing cache.
   - **Config**: `runtime.cli.wrapper_enabled` (bool, default false), `runtime.cli.wrapper_idle_timeout` (seconds, default 3600).
   - **Tests**: `SessionProcessManagerWrapperTest` (8 tests), `MessengerRuntimeOrchestratorWrapperTest` (3 tests).

## References

- [Claude Code – Headless](https://code.claude.com/docs/en/headless)
- [Claude Code – Continue conversations](https://code.claude.com/docs/en/headless#continue-conversations)
- [Claude Code – Interactive mode](https://docs.anthropic.com/en/docs/claude-code/interactive-mode)
- Warp/Codex: Agent Mode and Oz CLI docs (session behaviour is IDE/cloud-oriented).
