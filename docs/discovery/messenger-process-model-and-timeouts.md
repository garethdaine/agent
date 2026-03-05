# Messenger process model and timeouts

## Do we invoke a new CLI instance every time?

**Yes.** There is no long-lived CLI or REPL. Each user message that triggers CLI execution gets a **new process**:

| Path | When | What runs |
|------|------|-----------|
| **General task** (no runtime session) | User sends a free-form message (e.g. “what can you do?”) | `ProcessChatIntent` (queue: `messenger-default`) runs. It sends “Thinking…”, then `GeneralTaskHandler` starts **one** process: `claude -p --system-prompt <system> <user prompt>`. Process runs to completion (or timeout), stdout is streamed to Discord, then the process exits. |
| **Runtime** (active runtime session) | User sends a message while in an active runtime session | `ProcessChatIntent` dispatches `ProcessRuntimeTurnJob` (queue: `agent`) and shows “Processing request…”. The job runs **one** process per turn: writes a temp task file with the user message, runs the runner (e.g. `claude` with `{{task_markdown_path}}`), waits for exit, parses output, sends the result to Discord. No shared process across turns. |

So: **one CLI process per message** (general task) or **per turn** (runtime). When the process exits, that invocation is done.

---

## How the process works (general task)

1. User sends a message → webhook/gateway stores it and dispatches `ProcessChatIntent` to `messenger-default`.
2. `ProcessChatIntent` parses intent; if it’s a “general task”, it sends the “Thinking…” placeholder (if the adapter supports editing).
3. `GeneralTaskHandler::handleStreaming()` (or `handle()`) builds a system prompt + user prompt and runs:
   - `Process::timeout(45)->start([ 'claude', '-p', '--system-prompt', $systemPrompt, $fullPrompt ])` (streaming)
   - or `Process::timeout(45)->run(...)` (non-streaming).
4. The worker reads stdout in a loop and passes chunks to `StreamingResponseWriter`, which **edits** the same Discord message (the placeholder).
5. When the process exits (or hits 45s), the job formats the result (or error), edits the placeholder with the final text, and ends.

So the **only** thing that keeps state across messages is the Laravel app (session, DB). The CLI is stateless and one-shot per message.

---

## Why the timeouts are what they are

| Timeout | Value | Where | Reason |
|--------|--------|--------|--------|
| **General-task process** | 45s (default; env: `MESSENGER_GENERAL_TASK_CLI_TIMEOUT`) | `GeneralTaskHandler`, `config/messenger.php` | Keep chat responsive; avoid one slow request holding the worker too long. Set higher if you want longer replies; keep `HORIZON_MESSENGER_TIMEOUT` greater than this. |
| **Horizon messenger job** | 120s (default, env: `HORIZON_MESSENGER_TIMEOUT`) | `config/horizon.php` | Must be **longer** than the general-task process (45s) + overhead (parsing, Discord API, etc.). Previously 60s, which could kill the job after “Thinking…” was sent but before the reply was written → stuck “Thinking…”. |
| **Runtime CLI process** | 300s (default, env: `RUNTIME_CLI_TIMEOUT`) | `config/runtime.php` → `CliRuntimeExecutor` | Runtime turns can do multi-step tool use (browser, fs, etc.), so they need a longer run. 300s balances “enough time” vs “don’t hang forever”. |
| **ProcessRuntimeTurnJob** | 300s | `ProcessRuntimeTurnJob::$timeout` | Must be at least as long as the CLI process (300s) so the job isn’t killed while the process is still running. |

So the “low” timeouts you see are mainly:

- **General task**: 45s is intentionally short for a single quick Q&A in the messenger; the problem was the **Horizon** timeout (60s) being too low relative to that, not the 45s itself.
- **Runtime**: 300s is the main limit; it’s already high for a single turn.

If you want general-task replies to run longer (e.g. 90s), set `MESSENGER_GENERAL_TASK_CLI_TIMEOUT=90` and ensure `HORIZON_MESSENGER_TIMEOUT` is higher (e.g. 120 or 150).

---

## Summary

- **New CLI per message/turn**: no persistent CLI; each message (general task) or turn (runtime) gets a new process that exits when done.
- **General task**: 45s process timeout; 120s Horizon job timeout (so the job can finish and update the “Thinking…” message).
- **Runtime**: 300s process and job timeout for long-running turns with tools.
