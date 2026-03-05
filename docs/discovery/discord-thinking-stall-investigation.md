# Discord "Thinking…" stall investigation (02:09)

## What the user saw

- Sent a message to the AI via Discord at **02:09**.
- UI showed **"Thinking…"** and never updated; the request never completed.

## How "Thinking…" is shown

1. **Placeholder text**
   The text `⏳ Thinking…` is sent in `ProcessChatIntent::sendPlaceholder()` when the job is about to run a **sync or streaming action** (e.g. list jobs, or a **general task** that uses the Claude CLI). The same job is then supposed to **edit** that message with the real response (or an error).

2. **Two flows that can show a placeholder**
   - **Runtime path** (active runtime session): we send the placeholder, then immediately **edit** it to `⏳ Processing request…` and **return**; the actual reply is sent later by `ProcessRuntimeTurnJob` (new message). So the user would normally see "Processing request…", not a lasting "Thinking…".
   - **General task / other sync actions**: we send `⏳ Thinking…`, then in the **same** job we run `executeStreamingAction` (e.g. `GeneralTaskHandler` streaming) or `executeSyncAction`. When that finishes we **edit** the placeholder with the result. If the job never finishes or never reaches the edit, the user stays on "Thinking…".

So a **stuck "Thinking…"** (no edit, no second message) means the **ProcessChatIntent** job either never completed or never got to send/edit the final response.

## Likely causes

1. **Horizon job timeout (most likely)**
   - `ProcessChatIntent` runs on queue `messenger-default`.
   - Supervisor `supervisor-messenger` has **`timeout => 60`** (see `config/horizon.php`).
   - For a **general task**, the same job runs the Claude CLI via `GeneralTaskHandler::handleStreaming()` with **`Process::timeout(45)`**.
   - If the CLI or surrounding work goes over **60s** (e.g. slow API, long reply), Horizon kills the job. The placeholder has already been sent; the job never gets to edit it or send an error. Result: user sees "Thinking…" forever.

2. **Worker not running**
   If no worker was processing `messenger-default` at 02:09, the job would not run at all and the user would not see "Thinking…". So the fact they **did** see "Thinking…" implies the job **did** run and sent the placeholder, then something prevented completion (timeout, crash, or exception after placeholder).

3. **Worker crash / OOM**
   The worker process could have died after sending the placeholder and before finishing (e.g. OOM). Same outcome: no edit, no final message.

4. **Runtime path with failed edit**
   If the flow was runtime and the **edit** from "Thinking…" to "Processing request…" failed (e.g. Discord API error), the user might have been left on "Thinking…" while `ProcessRuntimeTurnJob` ran on the `agent` queue. If that job then never ran (no worker on `agent`) or failed before sending, they would still see only "Thinking…". Less likely than (1) but possible.

## How to confirm (logs from ~02:09)

If you still have logs from that night, check `storage/logs/laravel.log` around 02:09 for:

- **ProcessChatIntent**
  - `ProcessChatIntent: Action created` (action_type, session).
  - `ProcessChatIntent: Failed to send placeholder` (would mean "Thinking…" was never sent).
  - `ProcessChatIntent: Streaming action completed` or `ProcessChatIntent: Action executed` (would mean we tried to edit the placeholder).
  - `ProcessChatIntent: Streaming action failed` or `ProcessChatIntent: Action execution failed` (exception path).
  - `ProcessChatIntent: Failed to send/edit response` (edit to final content failed).

- **GeneralTaskHandler**
  - `GeneralTaskHandler: Claude CLI failed (streaming)` or `GeneralTaskHandler: Exception (streaming)`.

- **ProcessRuntimeTurnJob** (only if message was routed to runtime)
  - `ProcessRuntimeTurnJob: Starting turn`
  - `ProcessRuntimeTurnJob: Turn finished` or `ProcessRuntimeTurnJob: Turn execution failed`.

- **Horizon**
  - Job timeout or max execution logs if available.

Also check the `failed_jobs` table for rows around 02:09 (queue `messenger-default` or `agent`).

## Recommendations

1. **Messenger job timeout**
   `supervisor-messenger` timeout is configurable via **`HORIZON_MESSENGER_TIMEOUT`** (default **120** seconds). The previous default was 60s; if the Claude CLI run (45s limit) plus overhead exceeded 60s, Horizon killed the job and the user was left on "Thinking…". Set `HORIZON_MESSENGER_TIMEOUT=120` (or higher) in `.env` and restart Horizon so long-running turns can complete and the placeholder gets updated.

2. **Log when placeholder is sent**
   Log once when the "Thinking…" placeholder is successfully sent (session id, optional message id), so you can correlate with "action completed" / "action failed" and see if the job died between placeholder and completion.

3. **Optional: timeout safety**
   If the job is near its timeout (e.g. 50s into a 60s limit), consider sending a single "Request is taking longer than usual…" or "Request timed out" edit before the job is killed, so the user doesn’t stay on "Thinking…" forever. This would require a way to detect “near timeout” inside the job (e.g. checking elapsed time in the streaming loop).

4. **Ensure both queues are run**
   For runtime messages, `ProcessChatIntent` runs on `messenger-default` and `ProcessRuntimeTurnJob` on `agent`. Ensure Horizon (or equivalent) is running and processing both `messenger-default` and `agent` 24/7 so that a 02:09 message is handled and, for runtime, the turn job runs and sends the reply.
