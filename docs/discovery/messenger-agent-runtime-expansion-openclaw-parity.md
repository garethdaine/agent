# Messenger Agent Runtime Expansion (OpenClaw-Style) — Discovery Brief

## 1. Executive Summary

This brief defines how to evolve the current Messenger Control Plane from a job/run command interface into a full conversational agent runtime that can execute local computer tasks through chat, including:

1. Computer and file control
2. Web browsing and research workflows
3. Requirements discovery flows
4. Agent API + MCP tool orchestration
5. General long-lived chat with operational controls

The recommended direction is to keep the existing Laravel messenger ingress and identity model, then add a dedicated `Agent Runtime Session` layer with a policy-driven tool gateway (filesystem/runtime/web/browser/MCP), explicit approval rails, and deterministic slash-command controls.

## 2. Problem Statement

Today, messenger supports connector lifecycle, identity linking, and a finite set of action handlers (`jobs.*`, `runs.*`). It does **not** yet provide OpenClaw-style “chat-first agent operator” behavior where users can request arbitrary tasks and the agent dynamically selects tools and executes multi-step plans.

This creates a capability gap:

- Users can control scheduled jobs, but not ask the agent to perform broad ad-hoc local tasks.
- Execution is action-handler bound, not tool-orchestrated.
- Browser automation exists in pieces, but not as a first-class chat capability with approvals and observability.
- Requirements discovery exists in web UX but is not fully exposed as a messenger-native conversation flow.

## 3. Goals and Non-Goals

### 3.1 Goals

- Deliver a messenger-native agent that can handle broad natural-language tasks with local execution.
- Introduce a unified tool capability model comparable to OpenClaw’s grouped tools (runtime/fs/web/ui/sessions/memory).
- Add robust safety controls: allowlists, approvals, scopes, audit logs, and per-channel policy.
- Enable browser-driven tasks through a production-ready browser lane (with `agent-browser` as a first-class option).
- Bridge to Agent API and future MCP server(s) without reworking channel adapters.

### 3.2 Non-Goals (Initial Expansion)

- Full OpenClaw protocol/runtime compatibility.
- Cross-host mesh networking in v1 (single-host first).
- Autonomous “always-on” agents without policy constraints.
- Replacing existing AgentJob scheduler internals.

## 4. Current-State Baseline (Repository)

### 4.1 Strengths Already Present

- Multi-provider connector support and config:
  - Slack, Telegram, Discord, WhatsApp adapters in [config/messenger.php](/Users/garethdaine/Code/agent/config/messenger.php)
- Local-mode gateway process and workers:
  - [MessengerGatewayCommand.php](/Users/garethdaine/Code/agent/app/Console/Commands/MessengerGatewayCommand.php)
  - [MessengerGatewayManager.php](/Users/garethdaine/Code/agent/app/Messenger/Gateway/MessengerGatewayManager.php)
- Identity-link flow and session/message persistence:
  - [ProcessInboundMessage.php](/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessInboundMessage.php)
- Intent parsing and execution pipeline:
  - [ChatIntentParser.php](/Users/garethdaine/Code/agent/app/Services/Messenger/ChatIntentParser.php)
  - [ProcessChatIntent.php](/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessChatIntent.php)
  - [ChatActionExecutor.php](/Users/garethdaine/Code/agent/app/Services/Messenger/ChatActionExecutor.php)

### 4.2 Hard Gaps Against Requested Scope

- Action surface is fixed to job/run lifecycle, not general agent tasks:
  - [ChatActionType.php](/Users/garethdaine/Code/agent/app/Enums/Messenger/ChatActionType.php)
- No generic “tool plan + tool execution loop” per chat session.
- No first-class runtime process control abstraction for arbitrary user tasks in messenger.
- No browser abstraction in messenger intent flow beyond provider-level messaging operations.
- No MCP capability bridge in chat orchestration path.

## 5. Reference Model Insights (OpenClaw + Browser Tooling)

OpenClaw shows a coherent pattern we can map:

- Single long-lived gateway controlling channels and clients ([Chat Channels](https://docs.openclaw.ai/channels/index), [Gateway Architecture](https://docs.openclaw.ai/concepts/architecture))
- Tool policy model with grouped capabilities (`group:runtime`, `group:fs`, `group:web`, `group:ui`, etc.) and allow/deny profiles ([Tools](https://docs.openclaw.ai/tools))
- Chat command layer for control and approvals (`/status`, `/approve`, `/skill`, `/subagents`, `/exec`, `/elevated`) ([Slash Commands](https://docs.openclaw.ai/tools/slash-commands))
- Node and browser proxy model for distributed execution and browser control ([node](https://docs.openclaw.ai/cli/node))

For browser integration specifically, `agent-browser` provides:

- Agent-oriented CLI workflow with refs/snapshots and JSON mode ([agent-browser.dev](https://agent-browser.dev/agent-mode))
- Session/profile isolation and persistent auth state ([Sessions](https://agent-browser.dev/sessions))
- CDP attach mode for local or remote browser targets ([CDP Mode](https://agent-browser.dev/cdp-mode))
- Optional live streaming channel for “pair browsing” ([Streaming](https://agent-browser.dev/streaming))
- Mature command surface and client-daemon architecture in upstream README ([GitHub](https://github.com/vercel-labs/agent-browser))

## 6. Target Product Scope

### 6.1 User Experience (Messenger-Native)

Users should be able to send messages like:

- “Find the latest competitor pricing and write a report in `/tmp/pricing.md`.”
- “Open the app, test login with this account, and summarize failures.”
- “Run requirements discovery for feature X in this repo.”
- “Spin up a coding agent and implement the approved plan.”

And use control commands such as:

- `/status` (current runtime/session/tool state)
- `/approve <id>` and `/deny <id>`
- `/runs` (active runtime sessions)
- `/stop <session|run>`
- `/mode safe|standard|full`
- `/browser start|stop|reset`

### 6.2 Capability Domains

1. `computer/fs`: read, write, edit, patch, move, delete (policy constrained)
2. `runtime`: command execution, background process management, streaming logs
3. `web`: search + fetch + summarize
4. `browser`: navigate, inspect, act, capture, export
5. `discovery`: run requirements-discovery workflow from chat
6. `orchestration`: Agent API actions and future MCP tool invocation

## 7. Proposed Architecture

### 7.1 New Core Layer: Agent Runtime Session

Add a new runtime layer independent of fixed chat actions:

- `MessengerRuntimeOrchestrator`
- `RuntimeSessionManager`
- `ToolGateway`
- `ApprovalGate`
- `PolicyEngine`

Flow:

1. Inbound message resolved to `ChatSession`.
2. Router decides: `command`, `simple action`, or `agent runtime turn`.
3. Runtime turn creates/continues `runtime_session`.
4. Planner selects tools (within policy scope).
5. Tool calls execute via adapters (fs/runtime/web/browser/mcp).
6. Outputs are summarized and sent back to messenger.
7. Audit trail and metrics persisted.

### 7.2 Command Router Split

Keep deterministic commands separate from agent reasoning:

- `CommandRouter`: `/status`, `/approve`, `/stop`, `/runs`, `/mode`, etc.
- `AgentRouter`: free-form prompts delegated to runtime orchestrator.

This preserves reliability for operational controls.

### 7.3 Tool Gateway Contracts

Introduce interface-based tool adapters:

- `ToolAdapterInterface`
  - `name()`
  - `schema()`
  - `authorize(context, args)`
  - `execute(context, args)`

Adapters:

- `FsToolAdapter`
- `RuntimeToolAdapter`
- `WebToolAdapter`
- `BrowserToolAdapter`
- `DiscoveryToolAdapter`
- `AgentApiToolAdapter`
- `McpToolAdapter` (feature-flagged until MCP server rollout)

### 7.4 Policy and Safety Model

Policy dimensions:

- Channel scope: DM-only vs group-enabled
- User scope: linked identity + role
- Workspace scope: allowed directory roots
- Tool scope: allowed groups and explicit denies
- Execution scope: safe/standard/full mode
- Approval scope: mutation/elevated/network-sensitive actions

Recommended defaults:

- Default mode: `safe`
- `safe`: read + query + browser snapshot only
- `standard`: bounded writes + normal runtime commands + browser actions
- `full`: elevated host control, explicit approval required per high-risk call

## 8. Browser Integration Strategy

### 8.1 Option A: Native Playwright Service in Laravel

Pros:

- Full control in-app
- Unified logging model

Cons:

- Higher implementation/maintenance burden
- More lifecycle complexity in PHP long-lived workers

### 8.2 Option B: `agent-browser` Sidecar (Recommended for v1)

Approach:

- Run `agent-browser` as a managed dependency.
- Expose a constrained wrapper service in Laravel (allowed command subset).
- Persist session/profile mapping per `runtime_session`.
- Stream command output into `runtime_events`.

Pros:

- Faster delivery using mature CLI and workflow conventions
- Strong AI-agent ergonomics (refs/snapshots, JSON mode, session isolation)
- Supports CDP attach and optional live viewport streaming

Cons:

- External binary dependency and version pinning required
- Need hardened wrapper to prevent arbitrary flag abuse

### 8.3 Option C: Remote Browser Provider via CDP

Use when local browser isolation is insufficient or scale increases.

Pros:

- Better isolation and scalability
- Offloads browser resource overhead

Cons:

- More infra cost + network dependency
- More complex secrets and tenancy controls

### 8.4 Recommendation

- Phase 1: Option B (`agent-browser` sidecar) with strict wrapper.
- Phase 2: Add pluggable provider abstraction for local/remote CDP targets.
- Phase 3: Evaluate native browser runtime only if sidecar limits are material.

## 9. Data Model Additions

Add new tables:

1. `runtime_sessions`
- `id`, `chat_session_id`, `user_id`, `status`, `mode`, `title`, `started_at`, `ended_at`

2. `runtime_turns`
- `id`, `runtime_session_id`, `sequence`, `input_message_id`, `output_message_id`, `status`, `summary`

3. `runtime_tool_calls`
- `id`, `runtime_turn_id`, `tool_name`, `arguments_json`, `result_json`, `status`, `duration_ms`, `requires_approval`, `approved_at`

4. `runtime_approvals`
- `id`, `runtime_tool_call_id`, `requested_by`, `state`, `decision_by`, `decision_reason`, `expires_at`

5. `runtime_artifacts`
- `id`, `runtime_session_id`, `type`, `path`, `metadata_json`

6. `runtime_policy_snapshots`
- immutable policy capture per session start for forensic audit

## 10. API and Event Surface

### 10.1 API Extensions

- `GET /agent/api/v1/chat/runtime/sessions`
- `GET /agent/api/v1/chat/runtime/sessions/{id}`
- `POST /agent/api/v1/chat/runtime/sessions/{id}/stop`
- `POST /agent/api/v1/chat/runtime/tool-calls/{id}/approve`
- `POST /agent/api/v1/chat/runtime/tool-calls/{id}/deny`

### 10.2 Event Types

- `runtime.session.started`
- `runtime.turn.started|completed|failed`
- `runtime.tool_call.requested|approved|denied|completed|failed`
- `runtime.artifact.created`

### 10.3 Messenger UX Events

- Reply streaming (chunked updates)
- Approval cards/buttons where provider supports interactions
- Fallback text prompts where buttons are unavailable

## 11. Requirements Discovery in Messenger

Leverage existing discovery infrastructure as a tool in runtime:

- `discovery.start`
- `discovery.answer`
- `discovery.generate_summary`
- `discovery.generate_plan`
- `discovery.generate_build_tasks`
- `discovery.start_build`

Runtime can drive these while preserving the current web UX as the canonical detailed view.

## 12. Agent API + MCP Integration Path

### 12.1 Agent API Adapter (Near-Term)

- Wrap existing internal services/endpoints as typed tool calls.
- Use per-user authorization and ownership checks.
- Return compact summaries by default, with optional detail expansion.

### 12.2 MCP Adapter (When MCP Lands)

- Register MCP tools/resources in `ToolGateway` through provider adapters.
- Enforce policy at adapter boundary (tool allowlists + argument guards).
- Add stable mapping layer so messenger commands do not depend on specific MCP server naming.

## 13. Security, Trust, and Compliance

Mandatory controls:

1. Explicit tool allowlist per connector account.
2. Workspace boundary enforcement for fs/runtime tools.
3. Redaction for secrets in tool args/results.
4. Approval required for:
- destructive file ops
- elevated/runtime host commands
- outbound network actions outside allowlist
5. Full immutable audit events for every tool invocation and approval decision.
6. Session TTL and idle timeout controls.
7. Group channel safety: default no elevated commands in groups.

## 14. Delivery Plan (Phased)

### Phase 0: Foundations (1-2 weeks)

- Runtime session schema + event model
- Command router split
- Basic `/status`, `/runs`, `/stop` command path

### Phase 1: Tool Gateway MVP (2-3 weeks)

- Fs/runtime/web tool adapters (safe subset)
- Approval gate + policy engine
- Runtime turn orchestrator with concise response formatting

### Phase 2: Browser Lane (2 weeks)

- `agent-browser` wrapper integration
- Session/profile mapping and artifact capture
- Browser-specific approvals + logging

### Phase 3: Discovery + Agent API Bridge (2 weeks)

- Discovery tool adapter integration
- Agent API adapter integration
- Chat command set expansion (`/mode`, `/approve`, `/deny`)

### Phase 4: MCP Adapter + Hardening (2-3 weeks)

- MCP adapter under feature flag
- Reliability, retry, loop guardrails, load testing
- Security review + runbooks

## 15. Acceptance Criteria

1. User can request and complete at least one cross-domain task (fs + web + runtime) from messenger.
2. All high-risk actions require explicit approval and are blockable.
3. Runtime sessions are resumable with persisted turn history.
4. Browser tasks execute through the chosen browser adapter with artifacts returned.
5. Discovery workflow can be initiated and advanced from messenger.
6. Agent API operations are callable via tool gateway with policy checks.
7. Audit trail links message -> runtime turn -> tool calls -> approvals -> outputs.
8. Group-channel safety restrictions are enforced by default.

## 16. Risks and Mitigations

### 16.1 Risk: Unbounded Local Execution

Mitigation: strict mode defaults, path allowlists, approval gate, deny-by-default tool policy.

### 16.2 Risk: Prompt Injection via Web/Browser Content

Mitigation: treat fetched/browser content as untrusted; isolate tool output from control instructions; require confirmation for side effects.

### 16.3 Risk: Operational Complexity (sidecar/browser)

Mitigation: supervised sidecar health checks, version pinning, restart policies, explicit diagnostics endpoint.

### 16.4 Risk: Command/Intent Ambiguity

Mitigation: command router precedence + clear fallback prompts + optional `/plan` dry-run mode.

## 17. Open Questions

1. Should “full mode” require per-session re-auth or just approval prompts?
2. Should group chats allow any runtime mode above `safe`?
3. Should browser sessions persist by default or be ephemeral per runtime session?
4. For MCP, do we prioritize local stdio servers or remote HTTP/SSE servers first?
5. What is the hard cap on concurrent runtime sessions per user/connector?

## 18. Recommendation

Proceed with phased implementation using the existing messenger gateway and identity infrastructure, add a new `Agent Runtime Session` layer, and adopt `agent-browser` as the initial browser execution backend behind a strict wrapper.

This gives the fastest path to practical OpenClaw-style parity while preserving safety boundaries and keeping migration cost low when Agent API and MCP capabilities expand.

## 19. External References

- OpenClaw Chat Channels: [https://docs.openclaw.ai/channels/index](https://docs.openclaw.ai/channels/index)
- OpenClaw Gateway Architecture: [https://docs.openclaw.ai/concepts/architecture](https://docs.openclaw.ai/concepts/architecture)
- OpenClaw Tools (profiles/groups/tool inventory): [https://docs.openclaw.ai/tools](https://docs.openclaw.ai/tools)
- OpenClaw Slash Commands: [https://docs.openclaw.ai/tools/slash-commands](https://docs.openclaw.ai/tools/slash-commands)
- OpenClaw Node Host + Browser Proxy: [https://docs.openclaw.ai/cli/node](https://docs.openclaw.ai/cli/node)
- agent-browser GitHub README: [https://github.com/vercel-labs/agent-browser](https://github.com/vercel-labs/agent-browser)
- agent-browser docs (Agent mode): [https://agent-browser.dev/agent-mode](https://agent-browser.dev/agent-mode)
- agent-browser docs (Sessions): [https://agent-browser.dev/sessions](https://agent-browser.dev/sessions)
- agent-browser docs (CDP mode): [https://agent-browser.dev/cdp-mode](https://agent-browser.dev/cdp-mode)
- agent-browser docs (Streaming): [https://agent-browser.dev/streaming](https://agent-browser.dev/streaming)
