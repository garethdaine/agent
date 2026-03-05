# Messenger Runtime CLI Issues — Investigation & Fix Plan

**Date**: 2026-03-05
**Session**: 019cbe0e-1c73-727d-bea3-36e87d1695f5

## Issues Reported

1. **Slow turns (~90s each)**: Even "What's your name?" takes 1:47. Follow-up turns are equally slow.
2. **No identity**: Bot says "I'm Claude" instead of using the connector name (AxiomSpark) or configured personality.
3. **"Reading a file" pollution**: Response starts with "I'll read the file you specified. This file contains a conversation starter…" — the CLI treats the task file as a file to read, not as the user's message.
4. **No system prompt/personality injection**: Connector settings (personality, system prompt, user context) configured in the Edit Connector UI are never passed to the CLI.
5. **Session resume doesn't eliminate startup cost**: `--resume` preserves conversation history but every turn is a new process that reinitializes all MCP servers.

---

## Root Cause Analysis

### Issue 1: Slow turns (MCP initialization on every turn)

**Evidence**: Both Turn 1 and Turn 2 logs show a `system/init` event with 80+ tools, taking ~90 seconds. The `session_id` is the same (proving `--resume` works for context), but every turn spawns a fresh `claude` process.

**Root cause**: The command template in `config/agent.php` is:

```
claude --verbose -p --output-format stream-json --include-partial-messages {{task_markdown_path}}
```

No `--mcp-config`, `--strict-mcp-config`, or `--tools` flags are set. The CLI inherits the user's global MCP configuration (`~/.claude/` or project-level `.mcp.json`), which includes Linear, Notion, Gmail, gateway, Context7, and more. Each MCP server must connect and register tools on every process start.

**Key insight**: `--resume` only preserves conversation history. It does NOT keep the process alive. A new process is spawned per turn, so MCP initialization repeats every time.

### Issue 2 & 4: No identity / No system prompt injection

**Evidence**: The `CliRuntimeExecutor::executeTurn()` signature accepts `$session`, `$userMessage`, `$runnerTypeOverride`, and `$runnerSessionId`. There is no parameter for personality, system prompt, or user context.

**Root cause**: The connector's "soul" configuration (`personality`, `system_prompt`, `user_context`) from `ConnectorAccount::getSoul()` is never read in the runtime turn path:

- `ProcessRuntimeTurnJob` only passes `$account->config['runner_type']` to the orchestrator.
- `MessengerRuntimeOrchestrator::executeTurn()` passes only the user message to the CLI executor.
- `CliRuntimeExecutor::buildCommand()` has no `--system-prompt` flag.
- The Claude CLI supports `--system-prompt <prompt>` and `--append-system-prompt <prompt>`, but neither is used.

The `GeneralTaskHandler` has a `buildSoulPrompt()` method that constructs personality, but this handler is never invoked for runtime turns. All GENERAL_TASK messages go directly to `ProcessRuntimeTurnJob` via `ProcessChatIntent`.

### Issue 3: "Reading a file" pollution

**Evidence**: The response literally says "I'll read the file you specified. This file contains a simple conversation starter: 'Hey, what's your name?'"

**Root cause**: The CLI invocation is:

```
claude -p --output-format stream-json /path/to/runtime-{uuid}.md
```

The last argument is a file path. Claude Code in `-p` mode with a file argument reads that file as a software engineering task. The user's message "Hey, what's your name?" is written to a `.md` file and then passed as a path argument. Claude Code interprets this as "read and process this file", producing the "I'll read the file" preamble.

This is fundamentally wrong for conversational use. Claude Code's `-p` mode is designed for software engineering tasks, not chat. Passing a message via file path triggers file-reading behaviour.

### Issue 5: Session resume doesn't help with speed

**Evidence**: Turn 2 has `--resume 6d4791f7-8f00-455f-9a06-9ce83b21b030` passed. The `system/init` event shows the same session ID. But the turn still takes 1:43 because MCP initialization is per-process, not per-session.

**Root cause**: The `--resume` flag continues a conversation by session ID. But since each turn launches a new `claude` process, the process lifecycle is:

1. Start new process → 2. Initialize all MCP servers (~80-90s) → 3. Load conversation from session ID → 4. Generate response (~2-5s) → 5. Exit

The wrapper process (`bin/session-wrapper`, disabled by default) was designed to solve this by keeping one long-lived process per session. But even the wrapper uses the same command construction, so it would have the same MCP initialization cost on first start (though subsequent turns in the same session would be fast since the process stays alive).

---

## Available CLI Flags (Reference)

| Flag | Purpose |
|------|---------|
| `--system-prompt <prompt>` | Set system prompt for the session |
| `--append-system-prompt <prompt>` | Append to the default system prompt |
| `--tools <tools...>` | Restrict built-in tools (`""` to disable all, `"default"` for all) |
| `--strict-mcp-config` | Only use MCP servers from `--mcp-config`, ignore global |
| `--mcp-config <configs...>` | Load MCP from specific JSON files/strings |
| `--no-session-persistence` | Don't save sessions to disk |
| `--input-format stream-json` | Accept streaming JSON input |

---

## Fix Plan

### Fix A: Eliminate MCP startup cost (Critical — fixes Issue 1 & 5)

**Approach**: Use `--strict-mcp-config` with an empty config to skip all MCP server initialization. For conversational chat, no MCP servers are needed.

**Changes**:
- `config/agent.php`: Add `--strict-mcp-config` to the default Claude template. Optionally pair with `--tools ""` to disable file/bash tools for pure chat, or `--tools "Read"` for minimal tooling.
- `bin/session-wrapper` `buildCommand()`: Add the same flags.
- Consider making this configurable per connector (some connectors may want full tooling).

**Expected impact**: Turns should drop from ~90s to ~5-10s.

### Fix B: Inject connector personality via `--system-prompt` (Fixes Issue 2 & 4)

**Approach**: Read the connector's "soul" (personality, system_prompt, user_context) from `ConnectorAccount` and pass it to the CLI via `--system-prompt`.

**Changes**:
- `ProcessRuntimeTurnJob`: Read `$account->getSoul()` and pass personality/system_prompt/user_context to the orchestrator.
- `MessengerRuntimeOrchestrator::executeTurn()`: Accept and forward the system prompt.
- `CliRuntimeExecutor::executeTurn()`: Accept `$systemPrompt` parameter.
- `CliRuntimeExecutor::buildCommand()`: Insert `--system-prompt "$systemPrompt"` into the command array.
- Build a composite prompt from the soul fields, e.g.:

```
Your name is {agent_name}.
{personality}
{system_prompt}

User context: {user_context}
```

### Fix C: Pass message via stdin instead of file path (Fixes Issue 3)

**Approach**: Instead of writing the message to a file and passing the path, pipe the message directly to the CLI's stdin. Alternatively, pass the message as a direct string argument to `-p`.

**Option C1 — Direct string argument**: `claude -p "Hey, what's your name?" --system-prompt "..."`. This avoids the file-reading behavior entirely. Limitation: Very long messages might hit argument length limits.

**Option C2 — Stdin pipe**: Pipe the message via stdin: `echo "message" | claude -p --system-prompt "..."`. The Symfony Process component supports this via `setInput()`.

**Option C3 — Keep file but prepend instruction**: If file path is retained, prepend the task file with an instruction like "Respond conversationally to the following user message:" to avoid the "reading a file" behaviour. This is the least clean option.

**Recommended**: Option C2 (stdin pipe via `Process::setInput()`). This is the most natural way to send a message to a CLI tool and avoids both file-reading behaviour and argument length limits.

**Changes**:
- `CliRuntimeExecutor::executeTurn()`: Instead of writing to a file and passing the path, use `$process->setInput($userMessage)` and remove `{{task_markdown_path}}` from the command.
- `config/agent.php`: Update the Claude template to not include `{{task_markdown_path}}`.
- Or: Keep the template with `{{task_markdown_path}}` for backward compat (agent jobs) but override it in `CliRuntimeExecutor` for runtime turns.

### Fix D: Enable wrapper for session persistence (Performance improvement)

**Approach**: The wrapper process (`bin/session-wrapper`) keeps a single Claude process alive per session. After Fix A eliminates MCP cost, the first turn in a session would take ~5-10s and subsequent turns would be near-instant (~2-3s) since the process is already running.

**Changes**: This is already implemented but disabled. Enabling it requires:
- Set `RUNTIME_WRAPPER_ENABLED=true` in `.env`
- Ensure the wrapper also uses `--strict-mcp-config` and `--system-prompt` (Fixes A & B applied to wrapper).
- The wrapper uses `--input-format stream-json` which is cleaner than file-based input.

### Fix E: Update placeholder message after response (Minor UX)

**Current behavior**: The "Processing request..." placeholder is never updated to reflect the actual response. It stays as "Processing request... (edited)" with the response appearing as a separate message below.

**Desired behavior**: Edit the placeholder to show the response (for short responses), or at minimum update it to "Completed" or remove the hourglass.

---

## Suggested Implementation Order

| Priority | Fix | Effort | Impact |
|----------|-----|--------|--------|
| 1 | **A** — `--strict-mcp-config` to skip MCP | Small | Drops turn time from ~90s to ~5-10s |
| 2 | **B** — `--system-prompt` for connector soul | Medium | Bot gets identity, personality, context |
| 3 | **C** — Stdin pipe instead of file path | Medium | Eliminates "reading a file" pollution |
| 4 | **D** — Enable wrapper for fast subsequent turns | Small (config) | Subsequent turns drop to ~2-3s |
| 5 | **E** — Placeholder update | Small | UX polish |

Fixes A, B, and C are independent and can be developed in parallel. Fix D depends on A and B being applied to the wrapper code as well.
