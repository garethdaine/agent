# Messenger: Context Usage, Auto Compaction, and New Slash Commands

## Overview

Add (1) context usage detection and reporting, (2) auto compaction of conversation history (OpenClaw-style), (3) a `/new` slash command to kill the current runtime session and start a new one, and (4) additional useful slash commands inspired by [OpenClaw's slash commands](https://docs.openclaw.ai/tools/slash-commands) and [compaction](https://docs.openclaw.ai/concepts/compaction).

## Current State

- **ChatSession** has many **ChatMessage**; **ChatSessionManager::getSessionHistory()** returns the last N messages (limit from `session_history_limit`, default 20). No token counts or compaction.
- **RuntimeSession** has **total_input_tokens**, **total_output_tokens**; **RuntimeTurn** has **input_tokens**, **output_tokens**, **summary**. Token usage is recorded but not exposed via slash commands.
- **MessengerRuntimeOrchestrator::executeTurn()** sends only the **current user message** to the LLM (no prior turns or chat history in the messages array). So the runtime has no in-context conversation memory today.
- Slash commands: **jobs**, **runs**, **status**, **sessions**, **mode**, **approve**, **deny**, **browser**, **ask**. CommandRouter handlers receive only `(User $user, array $args)`; they do not receive `chat_session_id` or `connector_account_id`.

## Goals

1. **Context usage detection and reporting** – Surface how much context is in use (message count, estimated or actual tokens) for the current chat and runtime session.
2. **Auto compaction** – When conversation history exceeds a threshold, summarize older messages (and optionally older runtime turns) into a compact summary, persist it, and serve “summary + recent messages” so context stays within limits while preserving continuity.
3. **/new** – Kill the current runtime session for the current chat and create a new one (OpenClaw-style “new session”).
4. **New slash commands** – Add `/help`, `/commands`, `/whoami`, `/context`, `/compact`, `/new`, and optionally `/usage`, so every appropriate agent function is available as a slash command and discoverable (e.g. in Discord autocomplete).

---

## Phase 1: Context Usage Detection and Reporting

### 1.1 Context usage data

- **Chat session**
  - **Message count**: `ChatSession::messages()->count()` (or limit to last N for “active” window).
  - **Estimated input size**: Either store an optional `estimated_tokens` (or `content_length`) per **ChatMessage** and sum, or compute on the fly: sum of `strlen(content)` over recent messages and divide by configurable `chars_per_token` (e.g. 4). Prefer on-the-fly for now to avoid schema change; add a small **ContextUsageEstimator** (or method on ChatSessionManager) that returns `{ message_count, estimated_tokens }` for a session.
- **Runtime session** (when linked to chat)
  - Use existing **RuntimeSession::total_input_tokens**, **total_output_tokens** (and optionally sum of last K **RuntimeTurn** tokens for “current window” if we later add turn history to context).
- **Config**
  - In `config/messenger.php` (or `config/runtime.php`): `context.chars_per_token` (default 4), `context.estimated_context_window` (e.g. 200_000) for reporting “X% of context window”.

### 1.2 Expose in /status and new /context

- **StatusCommandHandler**
  - Optionally accept a “context” or “verbose” flag (e.g. `/status context` or later `/context`). For minimal change, extend **StatusCommandHandler** to include one line of context usage when the user has an active runtime session for the current chat: e.g. “Session tokens: in X, out Y” (from RuntimeSession). If we don’t have chat session in the handler yet, defer full “chat + runtime” context to the new **/context** command once handler context is available (Phase 3).
- **New command: /context**
  - **ContextCommandHandler** with subcommands or single response:
    - `list` (default): Short summary – chat message count, estimated tokens for chat; for current runtime session (if any): total_input_tokens, total_output_tokens, and optionally “estimated context %” if we have a configured context window.
    - `detail`: Same plus last few turn summaries (turn id, sequence, token counts).
    - `json`: Machine-readable structure (message_count, estimated_chat_tokens, runtime_session_id, runtime_input_tokens, runtime_output_tokens, etc.).
  - To support “current” chat/runtime, **ContextCommandHandler** (and **NewCommandHandler**, **StatusCommandHandler** where needed) must receive **chat_session_id** and **connector_account_id** so they can resolve the active runtime session and chat message count. So Phase 1 can implement the **estimator** and the **ContextCommandHandler** structure, but full “current session” reporting may depend on Phase 3 (router context).

### 1.3 Implementation tasks (Phase 1)

- Add **ContextUsageEstimator** (or methods on **ChatSessionManager**): given a **ChatSession** and optional limit, return `message_count` and `estimated_tokens` (sum of content length / chars_per_token).
- Add config keys: `messenger.context.chars_per_token`, `messenger.context.estimated_context_window` (optional).
- Add **ContextCommandHandler** that:
  - Takes `(User $user, array $args)` and optionally `(User $user, array $args, ?string $chatSessionId, ?string $connectorAccountId)` once router is extended.
  - If chat session is available: report chat message count and estimated tokens; resolve active **RuntimeSession** for that chat and report its token totals; support `list` / `detail` / `json` via args (e.g. `context list`, `context detail`, `context json`).
  - If chat session is not available (e.g. when called without router context): report user-level stats only (e.g. total active sessions, aggregate tokens across user’s active sessions) so the command still works.
- Register **/context** in **SlashCommandRegistrar** (Discord) and **CommandRouter**.
- Optionally extend **StatusCommandHandler** to include a single-line context hint when possible (e.g. “Tokens: in X, out Y” for active session).

---

## Phase 2: Auto Compaction

### 2.1 Where compaction applies

- **Chat session history** – Used today by **ChatSessionManager::getSessionHistory()** (e.g. for future use or when we add history to the runtime). Compaction here: when “context size” (message count or estimated tokens) exceeds a threshold, produce a **summary** of older messages, persist it, and when building history return “compaction summary + recent messages” instead of raw older messages.
- **Runtime turn history** – Today the runtime sends only the current user message. If we later add “prior turns” to the runtime context (Phase 2.2), we can compact old turns into a summary and send “summary + recent turns” to the LLM.

### 2.2 Persistence of compaction

- **Option A (recommended):** Add a **compaction** table or columns to **chat_sessions**:
  - `compaction_summary` (text, nullable): summary of messages before the compaction point.
  - `compaction_message_count` (int, default 0): number of messages summarized.
  - `compaction_at` (timestamp, nullable): when compaction was last run.
  - When serving history: if `compaction_summary` is present, prepend it (e.g. as a single synthetic “system” or “summary” block) and then append recent messages after the compaction point (e.g. last N messages that were not summarized). We need a clear “compaction point”: e.g. “keep last M messages; summarize everything before that.”
- **Option B:** Store compaction as a special **ChatMessage** (e.g. `type = 'compaction_summary'`) with the summary in content. **ChatSessionManager::getSessionHistory()** would then return compaction message(s) + recent normal messages, and the query would need to treat compaction messages as a single logical “block” of older context.

Option A keeps schema simple and one summary per session; Option B avoids a migration on **chat_sessions** but complicates message ordering and querying. Plan assumes **Option A** with columns on **chat_sessions**.

### 2.3 Trigger and execution

- **Threshold:** Config keys e.g. `messenger.compaction.trigger_message_count` (default 30) and/or `messenger.compaction.trigger_estimated_tokens` (default 15000). If either is exceeded when preparing context (or in a scheduled job), run compaction.
- **Target:** After compaction, “summary + recent” should be under `messenger.compaction.target_message_count` (e.g. 10 recent messages) and under a target token estimate.
- **Execution:** 
  - **Synchronous (inline):** Before building context in **ChatSessionManager::getSessionHistory()**, check threshold; if exceeded, call a **CompactionService** that (1) loads messages older than the last K, (2) calls an LLM to summarize them (or a simple concatenation + truncation for MVP), (3) writes summary and compaction point to **chat_sessions**, (4) optionally marks or soft-deletes the summarized messages so we don’t re-summarize (or simply “messages before time T are represented by summary” and we don’t delete them). Inline compaction can be slow; prefer **async**.
  - **Asynchronous (recommended):** When a message is appended or when the runtime is about to run a turn, dispatch a **CompactionJob** that checks the threshold for that session and, if over, runs the summarization and updates **chat_sessions**. Next time **getSessionHistory()** is called, it uses the new summary + recent messages. Optionally run compaction after every N messages or on a schedule per active session.
- **CompactionService** responsibilities:
  - Given a **ChatSession**, compute message count and estimated tokens (use **ContextUsageEstimator**).
  - If below threshold, return.
  - Load messages ordered by created_at; take “older” set (all but last `target_message_count`).
  - Build a single summary text (LLM call: “Summarize this conversation preserving decisions, facts, and open questions”) or for MVP a truncated concatenation.
  - Update **chat_sessions**: set `compaction_summary`, `compaction_message_count`, `compaction_at`; optionally store “compaction boundary” (e.g. id of last message that was summarized) so we know which messages are “recent”.
  - Do not delete messages; keep them for audit. When building history, **getSessionHistory()** returns compaction summary (if present) as first logical “block” and then recent messages after the boundary.

### 2.4 ChatSessionManager and runtime integration

- **ChatSessionManager::getSessionHistory()** should return:
  - If session has `compaction_summary`: one synthetic “summary” entry (or a structure that the caller can interpret) plus the recent messages (after compaction boundary). Callers that expect a list of **ChatMessage** might need a DTO or a “context block” type that can be either a summary string or a message.
  - If no compaction: current behavior (last N messages).
- **Runtime:** Today **MessengerRuntimeOrchestrator** does not use **getSessionHistory()**. When we add “conversation context” to the runtime (optional in this plan), we would pass the result of **getSessionHistory()** (including compaction summary + recent messages) into the prompt or message list. That step can be a follow-up so that auto compaction is still valuable for (a) future use of chat history in the runtime, and (b) a dedicated **/context** and **/compact** command that show and trigger compaction.

### 2.5 Implementation tasks (Phase 2)

- Migration: add to **chat_sessions** (or new table if preferred): `compaction_summary` (text nullable), `compaction_message_count` (unsigned int default 0), `compaction_at` (timestamp nullable), and optionally `compaction_boundary_message_id` (uuid nullable) to know the last message id that was summarized.
- **CompactionService**: threshold check, load old messages, call LLM (or truncate for MVP), update session compaction fields. Method e.g. `compactIfNeeded(ChatSession $session): bool`.
- **CompactionJob**: receives `chat_session_id`, loads session, calls **CompactionService::compactIfNeeded()**. Dispatch from:
  - After appending a new message (e.g. in **ProcessInboundMessage** or wherever messages are created), or
  - From **ChatSessionManager::getSessionHistory()** when over threshold (dispatch job and return current history without waiting), or
  - A scheduled job that runs every few minutes for active sessions over threshold.
- **ChatSessionManager::getSessionHistory()**: when session has compaction, return compaction summary as first “block” and recent messages (after boundary) as the rest. Define a simple structure (e.g. `{ summary?: string, messages: ChatMessage[] }` or a single list with a “summary” placeholder type).
- Config: `messenger.compaction.trigger_message_count`, `messenger.compaction.trigger_estimated_tokens`, `messenger.compaction.target_message_count`, `messenger.compaction.enabled` (default true).

---

## Phase 3: /new and Router Context for Session-Scoped Commands

### 3.1 Router context (chat_session_id, connector_account_id)

- **CommandRouter::route()** today: `route(string $content, User $user): ?CommandResult`.
- Extend to: `route(string $content, User $user, ?string $chatSessionId = null, ?string $connectorAccountId = null): ?CommandResult`.
- **SlashCommandHandlerInterface::handle()** today: `handle(User $user, array $args): CommandResult`.
- Extend interface and all handlers to: `handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult`. Existing handlers can ignore the new parameters.
- **ProcessChatIntent**: when calling `$commandRouter->route($message->content, $user)`, pass `$session->id` and `$account->id` so that session-scoped commands can resolve the active runtime session for the current chat.

### 3.2 /new command

- **NewCommandHandler**:
  - Resolve active **RuntimeSession** for (user, chatSessionId). If none, create a new session and reply “New session started (no previous session).”
  - If one exists: call **RuntimeSessionManager::stopSession()** on it, then **createSession()** for the same user with `chat_session_id` and same options (mode, etc.). Reply “Previous session stopped. New session started.”
  - Return **CommandResult** with success message.
- Register **/new** in **SlashCommandRegistrar** (no subcommands, no options) and in **CommandRouter**.

### 3.3 Implementation tasks (Phase 3)

- Extend **SlashCommandHandlerInterface** and all existing handlers with optional `$chatSessionId` and `$connectorAccountId` (default null). Update **CommandRouter::route()** to pass these through.
- Update **ProcessChatIntent** to call `route(..., $session->id, $account->id)`.
- Add **NewCommandHandler**, register **/new** in registrar and router.
- **StatusCommandHandler** and **ContextCommandHandler** can use `chatSessionId` to report per-chat status and context (active session tokens, message count for that chat).

---

## Phase 4: Additional Slash Commands (OpenClaw-Inspired)

### 4.1 /help

- **HelpCommandHandler**: Returns a short message listing all available commands with one-line descriptions. Can be generated from **CommandRouter::getAvailableCommands()** if that exists, or from a static list that mirrors the registrar. Example: “**Commands:** /jobs, /runs, /status, /sessions, /mode, /approve, /deny, /browser, /ask, /new, /context, /compact, /help, /commands, /whoami. Use /commands for details.”

### 4.2 /commands

- **CommandsCommandHandler** (or same handler with subcommand): Same as /help but optionally more verbose (e.g. subcommand list for /jobs, /runs, /sessions, /browser). Can share logic with HelpCommandHandler and differ only in verbosity.

### 4.3 /whoami

- **WhoamiCommandHandler**: Returns the user’s id (and optionally email or name if safe) and connector identity (e.g. “Discord user id: …”). No args. Useful for debugging and support.

### 4.4 /context (already in Phase 1)

- Implemented in Phase 1; ensure it supports `list`, `detail`, `json` and uses router context from Phase 3.

### 4.5 /compact

- **CompactCommandHandler**: Optional args: `[instructions]` (free text). Calls **CompactionService** to run compaction for the current chat session (requires router context). If `instructions` provided, pass to LLM as “Use these instructions when summarizing: …”. Reply “Compaction run. Summary now includes the last N messages.” or “No compaction needed (under threshold).” Register **/compact** with one optional option `instructions` in Discord and router.

### 4.6 /usage (optional)

- **UsageCommandHandler**: Returns token/cost summary for the user’s current or recent runtime sessions (e.g. total input/output tokens, optional cost if we have pricing). Can merge into **/context** as `context json` or a dedicated **/usage** for familiarity with OpenClaw. If implemented, register **/usage** with optional subcommands (e.g. `off`, `tokens`, `cost`).

### 4.7 Registration and Discord

- Add to **SlashCommandRegistrar::getCommands()**: **help**, **commands**, **whoami**, **context** (with options for list/detail/json if needed), **compact** (optional `instructions`), **new**, and optionally **usage**.
- Update **DiscordAdapter::mapKnownSlashCommand()** and **buildSlashCommandLine()** for the new command names.
- Update **CommandRouter** handler map. Bump **COMMAND_VERSION** in the registrar so Discord re-registers.

---

## Phase 5: Tests and Docs

- Unit tests: **ContextUsageEstimator**, **CompactionService** (mocked LLM), **ContextCommandHandler**, **NewCommandHandler**, **HelpCommandHandler**, **WhoamiCommandHandler**, **CompactCommandHandler**.
- Feature/integration: CommandRouter with new commands and router context; ProcessChatIntent passes session/account into route.
- Update any docs that list slash commands (e.g. AGENTS.md or messenger docs).

---

## Dependencies and Order

- **Phase 1** (context usage + /context) can be done first; /context will be fully useful once Phase 3 adds router context.
- **Phase 2** (compaction) depends on Phase 1’s **ContextUsageEstimator** and config; can run in parallel with Phase 3.
- **Phase 3** (router context + /new) unblocks full /context and /compact behavior and should be before or with Phase 4.
- **Phase 4** (help, commands, whoami, compact, usage) after Phase 3 so all new commands can receive chat session when needed.
- **Phase 5** throughout.

---

## Config Summary

```php
// config/messenger.php additions
'context' => [
    'chars_per_token' => (int) env('MESSENGER_CONTEXT_CHARS_PER_TOKEN', 4),
    'estimated_context_window' => (int) env('MESSENGER_ESTIMATED_CONTEXT_WINDOW', 200_000),
],
'compaction' => [
    'enabled' => env('MESSENGER_COMPACTION_ENABLED', true),
    'trigger_message_count' => (int) env('MESSENGER_COMPACTION_TRIGGER_MESSAGE_COUNT', 30),
    'trigger_estimated_tokens' => (int) env('MESSENGER_COMPACTION_TRIGGER_TOKENS', 15_000),
    'target_message_count' => (int) env('MESSENGER_COMPACTION_TARGET_MESSAGES', 10),
],
```

---

## References

- [OpenClaw Slash Commands](https://docs.openclaw.ai/tools/slash-commands)
- [OpenClaw Compaction](https://docs.openclaw.ai/concepts/compaction)
