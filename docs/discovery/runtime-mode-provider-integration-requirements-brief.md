# Requirements Discovery Brief — Runtime Mode + Multi-Provider Agent Execution

## 1. Objective
Enable in-app configuration to choose **CLI mode** or **API mode** for agent execution, and add provider integrations for:
- OpenAI API
- Anthropic API
- Custom API endpoint (local/self-hosted, optional API key), including OpenAI-compatible local servers for Qwen and other models

This brief is for kicking off an implementation session with clear decisions, scope, and acceptance criteria.

## 2. Problem Statement
Current execution flow is CLI-centric. You want runtime flexibility so jobs can run via:
- local CLI runners when preferred, or
- API providers (cloud or local) when faster/cheaper/more controllable.

You also want a path to run agentic workloads on local infrastructure (for example DGX Spark + Qwen family) through a configurable API endpoint.

## 3. Desired User Outcomes
- From Settings, user can configure provider credentials and defaults once.
- From Job create/edit, user can choose execution mode (CLI/API) and provider/model.
- API mode supports cloud and local endpoints without code changes.
- Agentic tasks (including tool-calling flows) run with the same product UX regardless of mode.

## 4. In Scope
- Settings UI + backend config model for runtime mode/provider configuration.
- Execution abstraction so job runtime can route to CLI adapters or API adapters.
- First-party API adapters for OpenAI + Anthropic.
- Custom provider adapter for arbitrary base URL + model + optional key.
- Support for OpenAI-compatible local servers (for example vLLM/SGLang-compatible endpoints).
- Validation, connectivity test, and model capability flags (tool calling, streaming, reasoning mode if supported).
- Audit/logging/telemetry parity across modes.

## 5. Out of Scope (Initial Release)
- Building model hosting/orchestration itself (vLLM/SGLang deployment automation).
- Fine-tuning or model quantization workflows.
- Provider-specific advanced features that break portability (unless explicitly gated).
- Auto-routing by benchmark score.

## 6. Core Requirements

### 6.1 Settings and Configuration
- Add `Execution Mode` default: `cli` or `api`.
- Add provider configuration section:
  - OpenAI: api key, default model, optional org/project fields.
  - Anthropic: api key, default model.
  - Custom: name, base URL, optional key, optional extra headers, default model, protocol type.
- Encrypt secrets at rest and redact in UI/logs.
- Add “Test Connection” per provider config.

### 6.2 Job-Level Overrides
- Job can inherit global defaults or override:
  - mode,
  - provider,
  - model,
  - endpoint profile (for custom/local API).
- Validation ensures required fields exist for selected mode/provider.

### 6.3 Runtime Abstraction
- Introduce execution interface contract (mode-agnostic):
  - `startRun`, `streamEvents`, `invokeTools`, `cancelRun`, `finalizeRun`.
- Implement adapters:
  - CLI adapter (existing behavior),
  - OpenAI adapter,
  - Anthropic adapter,
  - Custom OpenAI-compatible adapter.

### 6.4 Tool Calling and Agentic Behavior
- Capability matrix per model/profile (at minimum):
  - tool calling supported,
  - streaming supported,
  - max context,
  - multimodal support (optional).
- If a selected model lacks required capability for a task, fail fast with actionable error.
- Normalize tool-call events to existing internal event schema so monitor UI remains consistent.

### 6.5 Local API / Qwen Path
- Custom provider must support local URLs (http/https, non-public hosts).
- API key must be optional.
- Must work with OpenAI-compatible chat/function-calling endpoints.
- Provide explicit template guidance in UI docs for local stacks (example fields for vLLM/SGLang/llama.cpp-compatible gateways).

### 6.6 Reliability and Operations
- Retry policy per provider with bounded backoff.
- Timeout controls (connect/read/overall run).
- Circuit-breaker style protection for repeatedly failing provider profiles.
- Health status surfaced in settings: `healthy`, `degraded`, `failing`.

### 6.7 Security
- Secret encryption and least-privilege exposure.
- SSRF-safe URL validation for custom endpoints.
- Optional allowlist for local/private CIDRs based on deployment policy.
- Full audit trail for settings mutations and run-mode/provider selections.

## 7. Data Model / Config Additions (Proposed)
- `agent_provider_profiles` (id, type, name, base_url, encrypted_key, headers_json, default_model, capabilities_json, is_active).
- `agent_execution_defaults` (user/team scoped defaults for mode/provider/model).
- Extend `agent_jobs` with mode/provider/model/profile references (nullable if inheriting defaults).
- Extend run records with resolved runtime metadata (`resolved_mode`, `resolved_provider`, `resolved_model`).

## 8. UX Requirements
- Settings:
  - clear mode description (CLI vs API),
  - provider cards,
  - “Test connection” button with result details.
- Job form:
  - mode switch,
  - provider/model selectors filtered by mode,
  - validation hints before save.
- Monitor/Run details:
  - show resolved mode/provider/model,
  - preserve existing event feed semantics.

## 9. Non-Functional Requirements
- No regression for existing CLI jobs.
- API-mode p95 latency overhead added by platform abstraction should be minimal (target under 150ms excluding provider latency).
- Deterministic error taxonomy (`auth`, `capability_mismatch`, `timeout`, `network`, `provider_5xx`, `tool_schema_error`).
- Feature-flag rollout for safe adoption.

## 10. Key Risks and Mitigations
- **Tool-calling incompatibility across providers/models**
  - Mitigation: capability checks + provider-specific schema translators.
- **Custom endpoint misconfiguration**
  - Mitigation: strong validation + test connection + sample payload preview.
- **Security risk via arbitrary URLs**
  - Mitigation: URL policy, DNS/IP checks, optional strict allowlist.
- **Behavior divergence between CLI and API runs**
  - Mitigation: normalize runtime events and enforce shared acceptance tests.

## 11. Discovery Questions to Resolve Before Build
1. Scope of mode selection: global-only, per-job-only, or both (recommended: both with inheritance).
2. Multi-tenant policy: are provider profiles per-user, per-team, or both?
3. Custom provider protocol surface: strict OpenAI-compatible only for v1, or pluggable protocols immediately?
4. Minimum tool-calling contract: JSON schema/function call format and strictness.
5. Do we require model auto-discovery (`/models`) or manual model entry is sufficient for v1?
6. What outbound network restrictions are required in production for custom endpoints?
7. Should specific local-provider templates be bundled (vLLM, SGLang, Ollama-compatible where safe)?

## 12. Acceptance Criteria (Release Gate)
- User can configure OpenAI, Anthropic, and at least one custom provider profile in Settings.
- User can choose CLI or API mode per job (with default inheritance).
- API-mode run succeeds end-to-end with:
  - OpenAI provider,
  - Anthropic provider,
  - custom local OpenAI-compatible endpoint.
- Tool-calling tasks execute in API mode and produce normalized run events.
- Missing capability scenarios fail with explicit guidance.
- Existing CLI jobs continue to run unchanged.
- Secrets are encrypted and not exposed in logs/responses.

## 13. Suggested Delivery Phases
1. Domain contracts + data model + feature flags.
2. Settings UI/API + secure credential handling + connection tests.
3. Runtime abstraction + OpenAI/Anthropic adapters.
4. Custom provider adapter + local endpoint support.
5. Tool-calling normalization + capability enforcement.
6. End-to-end tests, migration plan, rollout controls, docs.

## 14. Session Kickoff Prompt (Copy/Paste)
"Implement runtime mode selection (CLI vs API) and multi-provider API execution for agent jobs.

Use `docs/discovery/runtime-mode-provider-integration-requirements-brief.md` as source of truth.

Start by confirming decisions for discovery questions in section 11, then produce an implementation plan with:
- schema changes,
- settings and job-form UX changes,
- runtime adapter architecture,
- provider-specific contracts (OpenAI/Anthropic/custom OpenAI-compatible),
- security controls for custom endpoints,
- test plan and rollout plan.

Do not implement until decisions are explicit and acceptance criteria in section 12 are mapped to tests."
