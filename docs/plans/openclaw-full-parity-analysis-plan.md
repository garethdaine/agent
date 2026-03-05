# OpenClaw Full Parity Analysis Plan — Agent Ops

**Purpose:** Systematically analyse every section of [OpenClaw docs](https://docs.openclaw.ai/) and [OpenClaw repo](https://github.com/openclaw/openclaw) to bring Agent Ops to full parity, with **security, governance, ease of use, quality UX, and dashboard/messenger management** as primary concerns. Agent Ops is an **enterprise tool**; data safety and security are non-negotiable.

**Status:** Plan (analysis phase)  
**Created:** 2026-03-04  
**References:** [messenger-agent-runtime-expansion-openclaw-parity.md](../discovery/messenger-agent-runtime-expansion-openclaw-parity.md), [OpenClaw docs index](https://docs.openclaw.ai/llms.txt), [OpenClaw GitHub](https://github.com/openclaw/openclaw)

---

## 1. Executive Summary

- **Goal:** Full parity with OpenClaw’s capabilities where they align with Agent Ops’ enterprise, local-first, Laravel-based product—prioritising security, governance, UX, and operational clarity.
- **Scope:** Every documented area of OpenClaw (docs + repo structure) mapped to Agent Ops gaps and actionable work items.
- **Principles:**
  - **Security and data safety first** — enterprise-grade controls, auditability, least privilege.
  - **Ease of use** — onboarding, configuration, and daily operations must be straightforward.
  - **Quality UX** — dashboard and messenger experiences must be consistent, reliable, and manageable.
  - **Governance** — policies, approvals, and compliance-friendly behaviour throughout.

---

## 2. OpenClaw Documentation Sections (Full Inventory)

Use this as the checklist for “analyse every single section”. Each row is a doc area; analysis tasks are in §4.

### 2.1 Core / Getting Started


| Doc area                 | URL / path                                                                                                                     | Agent Ops relevance                             |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------- |
| OpenClaw index           | [docs.openclaw.ai](https://docs.openclaw.ai/)                                                                                  | Positioning, messaging, quick start             |
| Getting Started          | [start/getting-started](https://docs.openclaw.ai/start/getting-started.md)                                                     | Onboarding flow parity                          |
| Onboarding Overview      | [start/onboarding-overview](https://docs.openclaw.ai/start/onboarding-overview.md)                                             | Wizard vs dashboard setup                       |
| Onboarding Wizard (CLI)  | [start/wizard](https://docs.openclaw.ai/start/wizard.md)                                                                       | CLI vs web onboarding                           |
| Onboarding (macOS App)   | [start/onboarding](https://docs.openclaw.ai/start/onboarding.md)                                                               | N/A (desktop app) or “first run” web equivalent |
| Personal Assistant Setup | [start/openclaw](https://docs.openclaw.ai/start/openclaw.md)                                                                   | Use-case alignment                              |
| Setup                    | [start/setup](https://docs.openclaw.ai/start/setup.md)                                                                         | Install + first-run parity                      |
| Bootstrapping            | [start/bootstrapping](https://docs.openclaw.ai/start/bootstrapping.md)                                                         | Agent identity/bootstrap                        |
| Docs directory / Hubs    | [start/docs-directory](https://docs.openclaw.ai/start/docs-directory.md), [start/hubs](https://docs.openclaw.ai/start/hubs.md) | In-app docs / help                              |
| Showcase                 | [start/showcase](https://docs.openclaw.ai/start/showcase.md)                                                                   | Community / examples (optional)                 |
| CLI Automation           | [start/wizard-cli-automation](https://docs.openclaw.ai/start/wizard-cli-automation.md)                                         | Automatable setup for enterprise                |
| CLI Onboarding Reference | [start/wizard-cli-reference](https://docs.openclaw.ai/start/wizard-cli-reference.md)                                           | Reference for parity                            |


### 2.2 Concepts (Architecture & Behaviour)


| Doc area                   | URL / path                                                                                                                                              | Agent Ops relevance                    |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- |
| Gateway Architecture       | [concepts/architecture](https://docs.openclaw.ai/concepts/architecture.md)                                                                              | Single control plane, WS, sessions     |
| Agent Runtime              | [concepts/agent](https://docs.openclaw.ai/concepts/agent.md)                                                                                            | Runtime session model                  |
| Agent Loop                 | [concepts/agent-loop](https://docs.openclaw.ai/concepts/agent-loop.md)                                                                                  | Turn/tool loop                         |
| Agent Workspace            | [concepts/agent-workspace](https://docs.openclaw.ai/concepts/agent-workspace.md)                                                                        | Workspace roots, isolation             |
| Session Management         | [concepts/session](https://docs.openclaw.ai/concepts/session.md)                                                                                        | Sessions, keys, lifecycle              |
| Session Pruning            | [concepts/session-pruning](https://docs.openclaw.ai/concepts/session-pruning.md)                                                                        | Retention, cleanup                     |
| Session Tools              | [concepts/session-tool](https://docs.openclaw.ai/concepts/session-tool.md)                                                                              | sessions_* tools (list/history/send)   |
| Multi-Agent Routing        | [concepts/multi-agent](https://docs.openclaw.ai/concepts/multi-agent.md)                                                                                | Workspace/agent routing                |
| Context                    | [concepts/context](https://docs.openclaw.ai/concepts/context.md)                                                                                        | Context assembly                       |
| Compaction                 | [concepts/compaction](https://docs.openclaw.ai/concepts/compaction.md)                                                                                  | /compact, context size                 |
| Memory                     | [concepts/memory](https://docs.openclaw.ai/concepts/memory.md)                                                                                          | Memory model (we have memory pipeline) |
| Messages                   | [concepts/messages](https://docs.openclaw.ai/concepts/messages.md)                                                                                      | Message format, chunking               |
| Streaming and Chunking     | [concepts/streaming](https://docs.openclaw.ai/concepts/streaming.md)                                                                                    | Streaming UX in messenger              |
| Model Providers / Failover | [concepts/model-providers](https://docs.openclaw.ai/concepts/model-providers.md), [model-failover](https://docs.openclaw.ai/concepts/model-failover.md) | Multi-provider, failover               |
| OAuth                      | [concepts/oauth](https://docs.openclaw.ai/concepts/oauth.md)                                                                                            | Auth for providers                     |
| Presence                   | [concepts/presence](https://docs.openclaw.ai/concepts/presence.md)                                                                                      | Who’s connected                        |
| Command Queue              | [concepts/queue](https://docs.openclaw.ai/concepts/queue.md)                                                                                            | Debounce, cap, drop                    |
| Retry Policy               | [concepts/retry](https://docs.openclaw.ai/concepts/retry.md)                                                                                            | Channel/API retries                    |
| Typing Indicators          | [concepts/typing-indicators](https://docs.openclaw.ai/concepts/typing-indicators.md)                                                                    | Messenger UX                           |
| Usage Tracking             | [concepts/usage-tracking](https://docs.openclaw.ai/concepts/usage-tracking.md)                                                                          | Cost/quota in dashboard                |
| System Prompt              | [concepts/system-prompt](https://docs.openclaw.ai/concepts/system-prompt.md)                                                                            | Default AGENTS / system prompt         |
| Features                   | [concepts/features](https://docs.openclaw.ai/concepts/features.md)                                                                                      | Capability list                        |
| Markdown Formatting        | [concepts/markdown-formatting](https://docs.openclaw.ai/concepts/markdown-formatting.md)                                                                | Safe markdown in channels              |


### 2.3 Gateway (Ops & Config)


| Doc area                           | URL / path                                                                                                           | Agent Ops relevance            |
| ---------------------------------- | -------------------------------------------------------------------------------------------------------------------- | ------------------------------ |
| Gateway Runbook                    | [gateway/index](https://docs.openclaw.ai/gateway/index.md)                                                           | Ops runbook parity             |
| Configuration                      | [gateway/configuration](https://docs.openclaw.ai/gateway/configuration.md)                                           | Central config model           |
| Configuration Reference            | [gateway/configuration-reference](https://docs.openclaw.ai/gateway/configuration-reference.md)                       | Full config schema             |
| Configuration Examples             | [gateway/configuration-examples](https://docs.openclaw.ai/gateway/configuration-examples.md)                         | Example configs                |
| Authentication                     | [gateway/authentication](https://docs.openclaw.ai/gateway/authentication.md)                                         | Token/password, device pairing |
| Discovery and Transports           | [gateway/discovery](https://docs.openclaw.ai/gateway/discovery.md)                                                   | How clients find gateway       |
| Gateway Protocol                   | [gateway/protocol](https://docs.openclaw.ai/gateway/protocol.md)                                                     | WS API contract                |
| Health Checks                      | [gateway/health](https://docs.openclaw.ai/gateway/health.md)                                                         | Health endpoints               |
| Heartbeat                          | [gateway/heartbeat](https://docs.openclaw.ai/gateway/heartbeat.md)                                                   | Liveness                       |
| Gateway Lock                       | [gateway/gateway-lock](https://docs.openclaw.ai/gateway/gateway-lock.md)                                             | Single instance                |
| Logging                            | [gateway/logging](https://docs.openclaw.ai/gateway/logging.md)                                                       | Structured logs                |
| Doctor                             | [gateway/doctor](https://docs.openclaw.ai/gateway/doctor.md)                                                         | Diagnostics CLI                |
| Remote Access                      | [gateway/remote](https://docs.openclaw.ai/gateway/remote.md)                                                         | SSH/tailnet patterns           |
| Remote Gateway Setup               | [gateway/remote-gateway-readme](https://docs.openclaw.ai/gateway/remote-gateway-readme.md)                           | Remote deployment              |
| Tailscale                          | [gateway/tailscale](https://docs.openclaw.ai/gateway/tailscale.md)                                                   | Serve/Funnel, auth             |
| Sandboxing                         | [gateway/sandboxing](https://docs.openclaw.ai/gateway/sandboxing.md)                                                 | Non-main session sandbox       |
| Sandbox vs Tool Policy vs Elevated | [gateway/sandbox-vs-tool-policy-vs-elevated](https://docs.openclaw.ai/gateway/sandbox-vs-tool-policy-vs-elevated.md) | Policy layers                  |
| Secrets Management                 | [gateway/secrets](https://docs.openclaw.ai/gateway/secrets.md)                                                       | Secrets storage                |
| Security (gateway)                 | [gateway/security/index](https://docs.openclaw.ai/gateway/security/index.md)                                         | Gateway-specific security      |
| Network model                      | [gateway/network-model](https://docs.openclaw.ai/gateway/network-model.md)                                           | Bind, expose                   |
| Multiple Gateways                  | [gateway/multiple-gateways](https://docs.openclaw.ai/gateway/multiple-gateways.md)                                   | Multi-instance (optional)      |
| Background Exec / Process Tool     | [gateway/background-process](https://docs.openclaw.ai/gateway/background-process.md)                                 | Long-running exec              |
| Bonjour Discovery                  | [gateway/bonjour](https://docs.openclaw.ai/gateway/bonjour.md)                                                       | mDNS (optional)                |
| Bridge Protocol                    | [gateway/bridge-protocol](https://docs.openclaw.ai/gateway/bridge-protocol.md)                                       | Bridge clients                 |
| CLI Backends                       | [gateway/cli-backends](https://docs.openclaw.ai/gateway/cli-backends.md)                                             | CLI ↔ gateway                  |
| Local Models                       | [gateway/local-models](https://docs.openclaw.ai/gateway/local-models.md)                                             | Self-hosted models             |
| OpenAI Chat Completions            | [gateway/openai-http-api](https://docs.openclaw.ai/gateway/openai-http-api.md)                                       | OpenAI-compat API              |
| OpenResponses API                  | [gateway/openresponses-http-api](https://docs.openclaw.ai/gateway/openresponses-http-api.md)                         | Responses API                  |
| Tools Invoke API                   | [gateway/tools-invoke-http-api](https://docs.openclaw.ai/gateway/tools-invoke-http-api.md)                           | Tool invocation HTTP           |
| Troubleshooting                    | [gateway/troubleshooting](https://docs.openclaw.ai/gateway/troubleshooting.md)                                       | Ops troubleshooting            |
| Gateway-Owned Pairing              | [gateway/pairing](https://docs.openclaw.ai/gateway/pairing.md)                                                       | Device/channel pairing         |


### 2.4 Security (Dedicated)


| Doc area                              | URL / path                                                                               | Agent Ops relevance                          |
| ------------------------------------- | ---------------------------------------------------------------------------------------- | -------------------------------------------- |
| Security (main)                       | [security](https://docs.openclaw.ai/security)                                            | **Critical** — trust model, audit, hardening |
| Formal Verification (Security Models) | [security/formal-verification](https://docs.openclaw.ai/security/formal-verification.md) | Threat model, verification                   |


### 2.5 Channels


| Doc area                                    | URL / path                                                                                                                          | Agent Ops relevance         |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | --------------------------- |
| Chat Channels index                         | [channels/index](https://docs.openclaw.ai/channels/index.md)                                                                        | Multi-channel model         |
| Channel Routing                             | [channels/channel-routing](https://docs.openclaw.ai/channels/channel-routing.md)                                                    | Routing rules               |
| Groups                                      | [channels/groups](https://docs.openclaw.ai/channels/groups.md)                                                                      | Group rules, mention gating |
| Group Messages                              | [channels/group-messages](https://docs.openclaw.ai/channels/group-messages.md)                                                      | Group UX                    |
| Pairing                                     | [channels/pairing](https://docs.openclaw.ai/channels/pairing.md)                                                                    | DM pairing, allowlist       |
| WhatsApp                                    | [channels/whatsapp](https://docs.openclaw.ai/channels/whatsapp.md)                                                                  | We have adapter             |
| Telegram                                    | [channels/telegram](https://docs.openclaw.ai/channels/telegram.md)                                                                  | We have adapter             |
| Discord                                     | [channels/discord](https://docs.openclaw.ai/channels/discord.md)                                                                    | We have adapter             |
| Slack                                       | [channels/slack](https://docs.openclaw.ai/channels/slack.md)                                                                        | We have adapter             |
| Google Chat                                 | [channels/googlechat](https://docs.openclaw.ai/channels/googlechat.md)                                                              | Gap                         |
| Microsoft Teams                             | [channels/msteams](https://docs.openclaw.ai/channels/msteams.md)                                                                    | Gap                         |
| Signal                                      | [channels/signal](https://docs.openclaw.ai/channels/signal.md)                                                                      | Gap                         |
| BlueBubbles / iMessage                      | [channels/bluebubbles](https://docs.openclaw.ai/channels/bluebubbles.md), [imessage](https://docs.openclaw.ai/channels/imessage.md) | Gap (optional)              |
| IRC, Matrix, Feishu, LINE, Mattermost, etc. | (see llms.txt)                                                                                                                      | Optional channels           |
| Channel Troubleshooting                     | [channels/troubleshooting](https://docs.openclaw.ai/channels/troubleshooting.md)                                                    | Support runbook             |


### 2.6 Tools & Slash Commands


| Doc area                    | URL / path                                                                                                                 | Agent Ops relevance               |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------- | --------------------------------- |
| Tools index                 | [tools/index](https://docs.openclaw.ai/tools/index.md)                                                                     | Tool groups, profiles             |
| Slash Commands              | [tools/slash-commands](https://docs.openclaw.ai/tools/slash-commands.md)                                                   | **Critical** — command set parity |
| Exec Tool                   | [tools/exec](https://docs.openclaw.ai/tools/exec.md)                                                                       | Runtime exec                      |
| Exec Approvals              | [tools/exec-approvals](https://docs.openclaw.ai/tools/exec-approvals.md)                                                   | Approval flow                     |
| Elevated Mode               | [tools/elevated](https://docs.openclaw.ai/tools/elevated.md)                                                               | /elevated, host control           |
| Browser (OpenClaw-managed)  | [tools/browser](https://docs.openclaw.ai/tools/browser.md)                                                                 | Browser tool                      |
| Web Tools                   | [tools/web](https://docs.openclaw.ai/tools/web.md)                                                                         | Fetch/search                      |
| Skills                      | [tools/skills](https://docs.openclaw.ai/tools/skills.md), [skills-config](https://docs.openclaw.ai/tools/skills-config.md) | Skills registry, config           |
| Creating Skills             | [tools/creating-skills](https://docs.openclaw.ai/tools/creating-skills.md)                                                 | Skill authoring                   |
| ClawHub                     | [tools/clawhub](https://docs.openclaw.ai/tools/clawhub.md)                                                                 | Skill discovery (optional)        |
| Sub-Agents                  | [tools/subagents](https://docs.openclaw.ai/tools/subagents.md)                                                             | Sub-agent control                 |
| ACP Agents                  | [tools/acp-agents](https://docs.openclaw.ai/tools/acp-agents.md)                                                           | ACP runtime (optional)            |
| Thinking Levels             | [tools/thinking](https://docs.openclaw.ai/tools/thinking.md)                                                               | /think                            |
| Tool-loop detection         | [tools/loop-detection](https://docs.openclaw.ai/tools/loop-detection.md)                                                   | Loop guardrails                   |
| Multi-Agent Sandbox & Tools | [tools/multi-agent-sandbox-tools](https://docs.openclaw.ai/tools/multi-agent-sandbox-tools.md)                             | Sandbox tool policy               |
| Reactions                   | [tools/reactions](https://docs.openclaw.ai/tools/reactions.md)                                                             | UI reactions                      |
| Firecrawl, PDF, Diffs, etc. | (see llms.txt)                                                                                                             | Optional tool integrations        |


### 2.7 Web / Dashboard / Control UI


| Doc area   | URL / path                                                   | Agent Ops relevance                        |
| ---------- | ------------------------------------------------------------ | ------------------------------------------ |
| Web index  | [web/index](https://docs.openclaw.ai/web/index.md)           | Bind, security                             |
| Control UI | [web/control-ui](https://docs.openclaw.ai/web/control-ui.md) | **Critical** — feature list, auth, pairing |
| Dashboard  | [web/dashboard](https://docs.openclaw.ai/web/dashboard.md)   | **Critical** — dashboard UX, token, open   |
| WebChat    | [web/webchat](https://docs.openclaw.ai/web/webchat.md)       | In-browser chat                            |
| TUI        | [web/tui](https://docs.openclaw.ai/web/tui.md)               | Terminal UI (optional)                     |


### 2.8 Automation


| Doc area     | URL / path                                                                     | Agent Ops relevance                 |
| ------------ | ------------------------------------------------------------------------------ | ----------------------------------- |
| Cron Jobs    | [automation/cron-jobs](https://docs.openclaw.ai/automation/cron-jobs.md)       | Scheduled tasks (we have scheduler) |
| Webhooks     | [automation/webhook](https://docs.openclaw.ai/automation/webhook.md)           | Inbound webhooks                    |
| Hooks        | [automation/hooks](https://docs.openclaw.ai/automation/hooks.md)               | Lifecycle hooks                     |
| Gmail PubSub | [automation/gmail-pubsub](https://docs.openclaw.ai/automation/gmail-pubsub.md) | Optional                            |
| Polls        | [automation/poll](https://docs.openclaw.ai/automation/poll.md)                 | Polling (optional)                  |


### 2.9 CLI Reference


| Doc area                                              | URL / path                                         | Agent Ops relevance |
| ----------------------------------------------------- | -------------------------------------------------- | ------------------- |
| CLI Reference                                         | [cli/index](https://docs.openclaw.ai/cli/index.md) | CLI surface         |
| gateway, agent, message, channels                     | [cli/*](https://docs.openclaw.ai/cli/)             | Core commands       |
| doctor, config, pairing, devices, sessions, approvals | [cli/](https://docs.openclaw.ai/cli/)*             | Ops + security      |
| onboard, update, security                             | [cli/*](https://docs.openclaw.ai/cli/)             | Setup + audit       |


### 2.10 Nodes (Mobile / Desktop)


| Doc area                             | URL / path                                             | Agent Ops relevance     |
| ------------------------------------ | ------------------------------------------------------ | ----------------------- |
| Nodes index                          | [nodes/index](https://docs.openclaw.ai/nodes/index.md) | Device nodes (optional) |
| Camera, Audio, Voice Wake, Talk Mode | [nodes/*](https://docs.openclaw.ai/nodes/)             | Optional for enterprise |


### 2.11 Install / Platforms / Providers


| Doc area                                        | URL / path                                                 | Agent Ops relevance                 |
| ----------------------------------------------- | ---------------------------------------------------------- | ----------------------------------- |
| Install index                                   | [install/index](https://docs.openclaw.ai/install/index.md) | Install flow                        |
| Docker, Nix, Fly, Render, etc.                  | [install/*](https://docs.openclaw.ai/install/)             | Deployment options                  |
| Platforms (macOS, iOS, Android, Linux, Windows) | [platforms/](https://docs.openclaw.ai/platforms/)*         | Optional                            |
| Model Providers                                 | [providers/*](https://docs.openclaw.ai/providers/)         | Multi-provider (we have LLM client) |


### 2.12 Reference


| Doc area                       | URL / path                                                                                                                                       | Agent Ops relevance       |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------- |
| AGENTS default, Templates      | [reference/](https://docs.openclaw.ai/reference/)*                                                                                               | System prompt, templates  |
| Session Management Deep Dive   | [reference/session-management-compaction](https://docs.openclaw.ai/reference/session-management-compaction.md)                                   | Session design            |
| API Usage and Costs, Token Use | [reference/api-usage-costs](https://docs.openclaw.ai/reference/api-usage-costs.md), [token-use](https://docs.openclaw.ai/reference/token-use.md) | Cost visibility           |
| Prompt Caching                 | [reference/prompt-caching](https://docs.openclaw.ai/reference/prompt-caching.md)                                                                 | Optimisation              |
| RPC Adapters                   | [reference/rpc](https://docs.openclaw.ai/reference/rpc.md)                                                                                       | Pi/agent RPC (conceptual) |


### 2.13 Help & Troubleshooting


| Doc area        | URL / path                                                               | Agent Ops relevance         |
| --------------- | ------------------------------------------------------------------------ | --------------------------- |
| Help index      | [help/index](https://docs.openclaw.ai/help/index.md)                     | Help hub                    |
| Troubleshooting | [help/troubleshooting](https://docs.openclaw.ai/help/troubleshooting.md) | User-facing troubleshooting |
| Debugging       | [help/debugging](https://docs.openclaw.ai/help/debugging.md)             | Debug flows                 |
| FAQ             | [help/faq](https://docs.openclaw.ai/help/faq.md)                         | FAQ                         |


---

## 3. OpenClaw Repo Structure (Analysis Checklist)

Analyse the following repo areas (via GitHub or clone):


| Repo area                                         | Purpose of analysis                                                     |
| ------------------------------------------------- | ----------------------------------------------------------------------- |
| `apps/`                                           | Packaged apps (macOS, etc.) — optional for Agent Ops                    |
| `packages/`                                       | Shared packages (gateway, channels, tools) — API and behaviour patterns |
| `src/`                                            | Core gateway/runtime logic — session, routing, protocol                 |
| `ui/`                                             | Control UI implementation — dashboard and WebChat UX patterns           |
| `docs/`                                           | Source for docs — ensure no doc section missed                          |
| `skills/`                                         | Built-in skills — skill contract and discovery                          |
| `extensions/`                                     | Plugins (e.g. Mattermost) — extension model if we want plugins          |
| `.agent/`, `.agents/`                             | Agent/workflow config — agent loop configuration                        |
| Gateway protocol (TypeBox schemas, WS methods)    | Formal API contract for parity                                          |
| Config schema (openclaw.json)                     | Config shape and validation                                             |
| Security: SECURITY.md, threat model, audit script | Security process and defaults                                           |


---

## 4. Analysis Tasks (By Priority)

Execute these in order; each produces a short “findings + actions” note.

### Phase A: Security & Governance (Front of Mind)

1. **Security docs deep dive**
  - Read [Security](https://docs.openclaw.ai/security), [gateway/security](https://docs.openclaw.ai/gateway/security/index.md), [Sandboxing](https://docs.openclaw.ai/gateway/sandboxing.md), [Sandbox vs Tool Policy vs Elevated](https://docs.openclaw.ai/gateway/sandbox-vs-tool-policy-vs-elevated.md).
  - Extract: trust model, audit commands, hardened baseline, credential storage, DM/group policies, tool profiles (messaging / minimal / full), exec/elevated allowlists.
  - **Output:** `docs/discovery/openclaw-security-parity.md` — gaps and required Agent Ops controls (enterprise: RBAC, audit log, data retention, secrets).
2. **Gateway auth & device pairing**
  - Read [Authentication](https://docs.openclaw.ai/gateway/authentication.md), [Gateway-Owned Pairing](https://docs.openclaw.ai/gateway/pairing.md), [channels/pairing](https://docs.openclaw.ai/channels/pairing.md), Control UI device pairing.
  - **Output:** Auth and pairing parity list (token/password, device approval, allowlists) and where Jetstream/Sanctum already cover us.
3. **Tool policy and exec approvals**
  - Read [tools/exec](https://docs.openclaw.ai/tools/exec.md), [tools/exec-approvals](https://docs.openclaw.ai/tools/exec-approvals.md), [tools/elevated](https://docs.openclaw.ai/tools/elevated.md), [Multi-Agent Sandbox & Tools](https://docs.openclaw.ai/tools/multi-agent-sandbox-tools.md).
  - Map to Agent Ops: PolicyEngine, ApprovalGate, RuntimeToolAdapter, FsToolAdapter, BrowserToolAdapter.
  - **Output:** Tool profile matrix (allow/deny by group), approval flow UX, and enterprise hardening (audit every approval, time-bound, reason).
4. **Secrets and config**
  - Read [gateway/secrets](https://docs.openclaw.ai/gateway/secrets.md), [configuration-reference](https://docs.openclaw.ai/gateway/configuration-reference.md).
  - **Output:** Secrets management parity (env vs vault vs Laravel secrets) and config schema approach for dashboard.

### Phase B: Ease of Use & Onboarding

1. **Getting started and onboarding**
  - Read Getting Started, Onboarding Overview, Wizard (CLI), Setup.
  - **Output:** Side-by-side: OpenClaw wizard vs Agent Ops first-run (dashboard + optional CLI). List missing “easy path” steps (e.g. “connect first channel in 3 clicks”).
2. **Configuration UX**
  - Read Configuration, Configuration Examples, config schema/form in Control UI.
  - **Output:** How we expose config in Agent Ops (env, database, UI) and what “sensible defaults + one place to edit” means for us.
3. **Doctor and diagnostics**
  - Read [Doctor](https://docs.openclaw.ai/gateway/doctor.md), [Troubleshooting](https://docs.openclaw.ai/gateway/troubleshooting.md), [help/troubleshooting](https://docs.openclaw.ai/help/troubleshooting.md).
  - **Output:** Agent Ops “doctor” checklist (scheduler, queue, messenger, runtime, DB, Redis) and a troubleshooting runbook page.

### Phase C: Dashboard & Control UI (Quality UX)

1. **Control UI capabilities**
  - Read [Control UI](https://docs.openclaw.ai/web/control-ui.md) (“What it can do”), [Dashboard](https://docs.openclaw.ai/web/dashboard.md).
  - List: Chat, Channels (status, QR, config), Instances/presence, Sessions (list, patch), Cron, Skills, Nodes, Exec approvals, Config (view/edit/schema/form), Debug (status, health, models, event log, RPC), Logs (tail), Update.
  - **Output:** Feature matrix: OpenClaw Control UI vs Agent Ops dashboard (per area). Prioritise: sessions, runtime approvals, config, logs, health.
2. **Web surfaces security**
  - Read [Web index](https://docs.openclaw.ai/web/index.md), bind modes, allowedOrigins, allowInsecureAuth, dangerouslyDisableDeviceAuth.
  - **Output:** Secure-by-default rules for Agent Ops dashboard (HTTPS in prod, CORS, token storage, no unsafe overrides).
3. **WebChat and chat behaviour**
  - Read [WebChat](https://docs.openclaw.ai/web/webchat.md), Control UI “Chat behavior” (send, abort, idempotency, history size, inject, stop).
    - **Output:** WebChat parity for Agent Ops (streaming, stop, history, partial on abort) and where our messenger UX already matches.

### Phase D: Messenger & Slash Commands

1. **Slash commands full list**
  - Read [Slash Commands](https://docs.openclaw.ai/tools/slash-commands.md) in full (config, command list, directives, inline shortcuts, usage surfaces).
    - **Output:** Table: OpenClaw command → Agent Ops status (implemented / partial / missing). Focus: /status, /approve, /deny, /runs, /stop, /mode, /new, /compact, /think, /verbose, /usage, /allowlist, /config (if we support).
2. **Channel routing and groups**
  - Read [Channel Routing](https://docs.openclaw.ai/channels/channel-routing.md), [Groups](https://docs.openclaw.ai/channels/groups.md), [Group Messages](https://docs.openclaw.ai/channels/group-messages.md).
    - **Output:** Routing and group rules we need (mention gating, allowlists, per-group policy) and current adapter behaviour.
3. **Streaming and chunking**
  - Read [Streaming and Chunking](https://docs.openclaw.ai/concepts/streaming.md), provider-specific limits.
    - **Output:** Chunking and streaming behaviour for each Agent Ops connector and any limits we must document.

### Phase E: Sessions, Memory, and Agent Loop

1. **Session and context model**
  - Read Session Management, Session Pruning, Context, Compaction, Session Tools.
    - **Output:** Session key design, pruning/retention, context assembly, and sessions_* tools parity (list/history/send).
2. **Memory**
  - Read [Memory](https://docs.openclaw.ai/concepts/memory.md); compare to Agent Ops memory pipeline (formation, consolidation, pruning).
    - **Output:** What we already do vs OpenClaw memory; any missing “memory in chat” behaviour.
3. **Agent loop and tools**
  - Read [Agent Runtime](https://docs.openclaw.ai/concepts/agent.md), [Agent Loop](https://docs.openclaw.ai/concepts/agent-loop.md), [Tools index](https://docs.openclaw.ai/tools/index.md).
    - **Output:** Turn lifecycle, tool dispatch, and tool groups (runtime, fs, web, browser, etc.) vs our ToolGateway and adapters.

### Phase F: Automation, Cron, Webhooks

1. **Cron and webhooks**
  - Read [Cron Jobs](https://docs.openclaw.ai/automation/cron-jobs.md), [Webhooks](https://docs.openclaw.ai/automation/webhook.md).
    - **Output:** Cron delivery (announce, webhook, none), webhook auth, and comparison to our scheduler + job runs.

### Phase G: Reference and Repo

1. **Config reference and schema**
  - Scan [Configuration Reference](https://docs.openclaw.ai/gateway/configuration-reference.md) (full field list).
    - **Output:** Config keys we should support for parity (or explicitly document as out of scope) and schema-driven UI feasibility.
2. **Gateway protocol**
  - Read [Gateway Protocol](https://docs.openclaw.ai/gateway/protocol.md); if available, TypeBox schemas or OpenAPI in repo.
    - **Output:** WS methods we might adopt for dashboard (e.g. sessions.list, config.get, approvals) vs REST-only.
3. **Repo structure**
  - Review `packages/`, `src/`, `ui/` for gateway core, channels, tools, and Control UI components.
    - **Output:** Short “patterns we can reuse” note (e.g. approval flow, session key format, config apply flow).

---

## 5. Deliverables


| Deliverable                 | Description                                                                                                             |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **OpenClaw doc inventory**  | §2 (this plan) — done.                                                                                                  |
| **Security & governance**   | `docs/discovery/openclaw-security-parity.md` (Phase A).                                                                 |
| **Auth & pairing**          | Section in security parity or separate `openclaw-auth-parity.md`.                                                       |
| **Tool policy & approvals** | Section in security parity or `openclaw-tool-policy-parity.md`.                                                         |
| **Ease of use**             | `docs/discovery/openclaw-onboarding-config-parity.md` (Phases B, C ease-of-use).                                        |
| **Dashboard & Control UI**  | `docs/discovery/openclaw-dashboard-control-ui-parity.md` (Phase C).                                                     |
| **Messenger & commands**    | `docs/discovery/openclaw-messenger-slash-commands-parity.md` (Phase D).                                                 |
| **Sessions, memory, loop**  | `docs/discovery/openclaw-sessions-memory-agent-parity.md` (Phase E).                                                    |
| **Automation**              | Section in one of the above or `openclaw-automation-parity.md` (Phase F).                                               |
| **Config & protocol**       | In dashboard parity or `openclaw-config-protocol-parity.md` (Phase G).                                                  |
| **Master gap list**         | `docs/plans/openclaw-parity-gap-list.md` — consolidated gaps and priorities (security, UX, dashboard, messenger first). |


---

## 6. UI Surfacing (Surface Everything as Features Complete)

**Rule:** For every capability we add or parity we close, **surface it in the UI** so operators and users can manage it without reading config or CLI only.

### 6.1 Where to Surface What


| Capability area                     | Primary UI location                                                        | Secondary                                              |
| ----------------------------------- | -------------------------------------------------------------------------- | ------------------------------------------------------ |
| Security & audit                    | Settings → Security; Dashboard → Audit (or dedicated Audit page)           | CLI: `agent:security-audit`                            |
| Gateway / API tokens                | Settings → API or Settings → Gateway                                       | —                                                      |
| Runtime sessions & approvals        | Messenger → Runtime (list + session detail with pending approvals)         | Messenger slash: `/runs`, `/approve`, `/deny`, `/mode` |
| Tool policy (mode, deny/allow list) | Settings → Runtime; per-session mode in Runtime → [Session]                | Messenger: `/mode`                                     |
| Connectors & channels               | Tools → Messenger (existing); Connectors → [connector] for DM/group policy | —                                                      |
| Pairing (DM access)                 | Messenger → Pairing or Connectors → [connector] → Pairing                  | CLI: `messenger:pairing`                               |
| Config (runtime, messenger)         | Settings → Runtime, Settings → Messenger (forms + validation)              | —                                                      |
| Diagnostics / doctor                | Settings → Diagnostics or Dashboard → Health                               | CLI: `agent:doctor` (when added)                       |
| Cron / scheduled jobs               | Existing Jobs UI                                                           | —                                                      |
| Logs                                | Settings → Logs or Dashboard → Logs (when added)                           | —                                                      |


### 6.2 Checklist When Completing a Feature

- Feature is implemented and tested.
- **UI:** Added to the appropriate screen (Settings, Messenger, Dashboard) per table above.
- **API:** If needed, REST/API endpoint exists and is documented.
- **Messenger:** If user-facing, slash command or in-chat flow exists where applicable.
- **Docs:** Product/docs updated (or linked from discovery doc).
- **Master gap list:** Item marked complete and UI location noted in [openclaw-parity-gap-list.md](openclaw-parity-gap-list.md).

### 6.3 Execution Notes

- **Browser / Firecrawl:** Use when a doc page is large or needs screenshots (e.g. Control UI “What it can do”). Prefer `mcp_web_fetch` for text; use browser for interactive or visual verification.
- **Repo:** Use GitHub (or clone) to inspect `packages/`, `src/`, `ui/`; no need to clone entire repo for high-level parity.
- **Enterprise emphasis:** Every analysis section should call out:
  - **Security:** RBAC, audit log, data retention, secrets, least privilege.
  - **Governance:** Approval workflows, policy versioning, compliance-friendly defaults.
  - **UX:** Clarity, minimal steps, consistent terminology, and manageable dashboard/messenger.

---

## 7. Success Criteria

- Every OpenClaw doc section in §2 has been read and either mapped to Agent Ops or marked out of scope with reason.
- Security and governance are documented first and drive the master gap list.
- Ease of use and quality UX (dashboard + messenger) are explicitly addressed with actionable gaps.
- A single, prioritised gap list exists for implementation planning (security and data safety as key themes).

---

## 8. References

- [OpenClaw docs](https://docs.openclaw.ai/)
- [OpenClaw docs index (llms.txt)](https://docs.openclaw.ai/llms.txt)
- [OpenClaw GitHub](https://github.com/openclaw/openclaw)
- [OpenClaw Security](https://docs.openclaw.ai/security)
- [OpenClaw Control UI](https://docs.openclaw.ai/web/control-ui.md)
- [OpenClaw Slash Commands](https://docs.openclaw.ai/tools/slash-commands.md)
- Agent Ops: [messenger-agent-runtime-expansion-openclaw-parity.md](../discovery/messenger-agent-runtime-expansion-openclaw-parity.md)
- Agent Ops: [AGENTS.md](/AGENTS.md), [dashboard overview](../product/dashboard/overview.md)

