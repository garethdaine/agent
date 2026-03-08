# Requirements Discovery Summary

Session: 5

# Content Security & Prompt Injection Protection Layer — Final Requirements Specification

## Architecture Decision: Application-Layer Only Enforcement
All security enforced in PHP code. Zero reliance on LLM compliance. LLM-readable content markers added only for observability when `content_wrapping_markers` config is enabled (default false).

## Core Components (12 New)

### 1. SecurityToolResult (Wrapper DTO)
Composes `App\DTOs\Runtime\ToolResult` (readonly, unmodified). Adds: `contentTrustLevel` (enum), `injectionScore` (float 0.0–1.0), `injectionAction` (enum), `sanitized` (bool), `originalTokenCount` (int), `truncated` (bool), `sourceIdentifier` (string).

### 2. ContentTrustClassifier
Assigns trust levels based on content source. **TRUSTED**: system prompts, platform-generated instructions, direct user messages from verified accounts. **CONTEXTUAL**: session context (feature_brief, tech stacks, discovery findings) from authenticated users. **UNTRUSTED**: all external content — web fetch, browser DOM, file contents, MCP results, attachments.

### 3. InjectionDetectionEngine
Pattern-based + heuristic scoring. Regex rules from `security_detection_rules` table. Heuristic indicators: instruction density, imperative verb frequency, role-switching patterns, authority claims. Returns confidence score 0.0–1.0. Action policies: STRIP, WARN, BLOCK, LOG. Per-mode thresholds (Safe=0.3, Standard=0.5, Full=0.7). Threshold floor: max configurable value 0.9.

### 4. ContentSanitizer
Strips HTML/JS when `strip_html` enabled. Enforces token limits per tool result (default 8000, max 32000). Token estimation: 4 chars/token fast path + `yethee/tiktoken-php` cl100k_base for budget-critical paths. Content type enforcement on tool results.

### 5. PromptIsolationService
Wraps injected context in XML-style delimited boundaries (`<user_context>`, `<session_context>`). Validates user-provided prompts from InterrogationSetting. Sanitizes discovery findings. Enforces context token budget (default 4000, existing pattern from MemoryContextBuilder).

### 6. TurnSecurityContext
Turn-level taint propagation. Once any UNTRUSTED tool result appears in a turn, all subsequent tool calls in that turn are treated as tainted. Solves the action attribution problem without LLM introspection. Tracks: `tainted` (bool), `taintSource` (string), `taintedAt` (timestamp), `toolCallCount` (int), `untrustedResultCount` (int).

### 7. InstructionFirewall
Intercepts tool calls via TurnSecurityContext taint state. When `default_deny_external` enabled, requires user confirmation for mutations/external calls when turn is tainted. Sensitive tool categories always escalate when tainted: `web.fetch` (non-allowlisted), `fs.write`, `runtime.exec`, `mcp.call`.

### 8. ExfiltrationDetector
Monitors outbound requests. 5 response inspection patterns: (1) session token echo-back, (2) conversation history reflection, (3) system prompt leakage, (4) credential patterns (API keys, tokens, passwords), (5) user PII echo. Checks POST bodies to non-allowlisted domains. Each pattern independently testable.

### 9. SecurityConfigProvider
Reads `env()` directly (not `config()`) to bypass Laravel config cache for hot-reload. Database overrides via 60s Redis TTL. Immutable core: `content_trust_enabled`, `injection_detection_enabled`, `exfiltration_detection` cannot be disabled. Per-account overrides stored in `account_security_overrides` table.

### 10. ContentSecurityMiddleware
Composed service injected into ToolGateway (not Laravel HTTP middleware). Orchestrates: classify → detect → sanitize → sandbox. Creates SecurityToolResult wrapper from ToolResult.

### 11. FileProvenanceRegistry
Tracks file origins for trust classification. Redis primary (hash per session, 24h TTL) + JSONB `file_provenance` database column backup. Entries: `{filePath, origin, trustLevel, createdAt, toolCallId}`. Purge job: `SecurityMaintenanceJob` on `supervisor-memory-formation` queue, daily schedule, purges records older than configurable retention (default 30 days), batch deletes 1000 rows/iteration, logs purge counts to security_events.

### 12. MessengerSecurityGuard
Attachment scanning via InjectionDetectionEngine before ChatIntentParser inlining. Group channel policy: `ignore` (drop non-paired user messages) or `low_trust` (process at UNTRUSTED level). Per-user rate limiting (default 20 actions/5min). Response sanitization on outbound messages. High-impact confirmation in Standard mode independent of taint state.

## Integration Hooks (8)

| # | Location | Type |
|---|----------|------|
| 1 | `ToolGateway.call()` post-execution | SecurityToolResult wrapping |
| 2 | `SystemPromptResolver.resolveForPhase()` | PromptIsolationService replacement |
| 3 | `ChatIntentParser.parseWithAI()` | Attachment scanning |
| 4 | `MessengerRuntimeOrchestrator` response | Output sanitization |
| 5 | `ApprovalGate` | Trust-aware + messenger high-impact escalation |
| 6 | `ToolGateway.call()` pre-execution | TurnSecurityContext taint check + InstructionFirewall |
| 7 | `WebToolAdapter` / `BrowserToolAdapter` | ExfiltrationDetector on outbound requests |
| 8 | `CliRuntimeExecutor.executeTurn()` | Pre-execution content scanning (separate from ToolGateway pipeline) |

## Database Changes

### Modified Tables
- **`runtime_tool_calls`**: Add `content_trust_level` ENUM, `injection_score` DECIMAL(3,2), `injection_action` VARCHAR(10), `content_sanitized` BOOLEAN
- **`runtime_sessions`**: Add `security_config_json` JSONB nullable, `file_provenance` JSONB nullable

### New Tables
- **`security_detection_rules`**: id (UUID), pattern (TEXT), pattern_type ENUM('regex','keyword','heuristic'), severity ENUM, weight DECIMAL(3,2), enabled BOOLEAN, timestamps
- **`security_events`**: id (UUID), runtime_session_id (UUID), runtime_tool_call_id (UUID nullable), event_type ENUM('injection_detected','content_blocked','content_stripped','exfiltration_attempt','escalation_triggered','rule_purged'), severity, details_json (JSONB), created_at
- **`account_security_overrides`**: id (UUID), account_id (UUID), config_key VARCHAR, config_value TEXT, created_by (UUID), timestamps

## Configuration Keys (18)

| Key | Type | Default | Immutable |
|-----|------|---------|-----------|
| `content_trust_enabled` | bool | true | YES |
| `injection_detection_enabled` | bool | true | YES |
| `injection_threshold_safe` | float | 0.3 | No (floor 0.9) |
| `injection_threshold_standard` | float | 0.5 | No (floor 0.9) |
| `injection_threshold_full` | float | 0.7 | No (floor 0.9) |
| `injection_action` | enum | WARN | No |
| `tool_result_max_tokens` | int | 8000 | No (max 32000) |
| `strip_html` | bool | true | No |
| `default_deny_external` | bool | false | No |
| `exfiltration_detection` | bool | true | YES |
| `messenger_rate_limit` | int | 20 | No |
| `messenger_group_policy` | enum | ignore | No |
| `prompt_isolation` | bool | true | No |
| `context_budget_tokens` | int | 4000 | No |
| `content_wrapping_markers` | bool | false | No |
| `file_provenance_retention_days` | int | 30 | No |
| `security_purge_batch_size` | int | 1000 | No |
| `messenger_high_impact_confirmation` | bool | true | No |

## Enums (7 New)

1. `ContentTrustLevel`: TRUSTED, CONTEXTUAL, UNTRUSTED
2. `InjectionAction`: STRIP, WARN, BLOCK, LOG
3. `InjectionSeverity`: LOW, MEDIUM, HIGH, CRITICAL
4. `DetectionPatternType`: REGEX, KEYWORD, HEURISTIC
5. `SecurityEventType`: INJECTION_DETECTED, CONTENT_BLOCKED, CONTENT_STRIPPED, EXFILTRATION_ATTEMPT, ESCALATION_TRIGGERED, RULE_PURGED
6. `MessengerGroupPolicy`: IGNORE, LOW_TRUST
7. `ExfiltrationPattern`: SESSION_TOKEN_ECHO, CONVERSATION_REFLECTION, SYSTEM_PROMPT_LEAK, CREDENTIAL_PATTERN, PII_ECHO

## Token Estimation Strategy
Hybrid approach: 4 chars/token fast approximation for general use (matches existing MemoryContextBuilder pattern). `yethee/tiktoken-php` with cl100k_base encoding for budget-critical paths (context budget enforcement, tool result truncation decisions). No LLM-based detection — keeps latency under 50ms per tool result.

## Trust Override UX
No per-content trust overrides. Session-level only via `TrustOverrideToken`: cryptographic token with 30-minute TTL (matches existing ApprovalGate pattern), scoped to session, logged to security_events, does not override immutable config keys. Triggered by user responding to an escalation prompt.

## Goals

- Implement SecurityToolResult wrapper DTO that composes readonly ToolResult with security metadata (trustLevel, injectionScore, injectionAction, sanitized, originalTokenCount, truncated, sourceIdentifier)
- Implement ContentTrustClassifier service that assigns TRUSTED/CONTEXTUAL/UNTRUSTED to all content based on source origin
- Implement InjectionDetectionEngine with pattern-based regex matching and heuristic scoring (instruction density, imperative verbs, role-switching, authority claims)
- Seed security_detection_rules table with 50+ known injection patterns from public research
- Implement ContentSanitizer with HTML/JS stripping and configurable token truncation using hybrid estimation (4 chars/token fast + tiktoken cl100k_base precise)
- Implement PromptIsolationService with XML-delimited context boundaries, prompt validation, and token budget enforcement
- Implement TurnSecurityContext for turn-level taint propagation — once any UNTRUSTED result appears, all subsequent calls in turn are tainted
- Implement InstructionFirewall that blocks/escalates tainted tool calls for sensitive categories (web.fetch non-allowlisted, fs.write, runtime.exec, mcp.call)
- Implement ExfiltrationDetector with 5 response inspection patterns: session token echo, conversation reflection, system prompt leakage, credential patterns, PII echo
- Implement SecurityConfigProvider reading env() directly (bypass config cache), with 60s Redis TTL database overrides and immutable core keys
- Implement ContentSecurityMiddleware as composed service in ToolGateway orchestrating classify→detect→sanitize→sandbox pipeline
- Implement FileProvenanceRegistry with Redis primary (24h TTL) and JSONB database backup for origin-aware file trust classification
- Implement MessengerSecurityGuard with attachment scanning, group channel policy (ignore/low_trust), rate limiting, and response sanitization
- Implement SecurityMaintenanceJob for daily purge of expired file provenance and security events (batch 1000, configurable retention)
- Wire Hook 1: ToolGateway.call() post-execution — SecurityToolResult wrapping via ContentSecurityMiddleware
- Wire Hook 2: SystemPromptResolver.resolveForPhase() — replace string interpolation with PromptIsolationService
- Wire Hook 3: ChatIntentParser.parseWithAI() — attachment scanning before AI context inlining
- Wire Hook 4: MessengerRuntimeOrchestrator response — output sanitization before channel delivery
- Wire Hook 5: ApprovalGate — trust-aware escalation + independent messenger high-impact confirmation
- Wire Hook 6: ToolGateway.call() pre-execution — TurnSecurityContext taint check + InstructionFirewall
- Wire Hook 7: WebToolAdapter/BrowserToolAdapter — ExfiltrationDetector on outbound requests
- Wire Hook 8: CliRuntimeExecutor.executeTurn() — pre-execution content scanning separate from ToolGateway
- Create database migrations: modify runtime_tool_calls (4 columns), runtime_sessions (2 columns), create security_detection_rules, security_events, account_security_overrides
- Create all 7 new enums: ContentTrustLevel, InjectionAction, InjectionSeverity, DetectionPatternType, SecurityEventType, MessengerGroupPolicy, ExfiltrationPattern
- Extend SecurityAuditService with checks for all 18 configuration keys and immutable core validation
- Install yethee/tiktoken-php composer dependency for precise token counting on budget-critical paths


## Constraints

- ToolResult is readonly — security metadata MUST use SecurityToolResult wrapper (composition), not modification
- Application-layer only enforcement — zero reliance on LLM compliance for any security decision
- Immutable core config: content_trust_enabled, injection_detection_enabled, exfiltration_detection cannot be disabled by any override
- Injection threshold floor: maximum configurable value is 0.9 for all three mode thresholds
- Tool result max tokens ceiling: maximum configurable value is 32000
- SecurityConfigProvider must read env() directly, not config(), to support hot-reload without config:cache invalidation
- Database config overrides cached in Redis with 60s TTL — not queried per-request
- Content wrapping markers (LLM-readable) default to false — observability aid only, not a security mechanism
- Messenger group channel policy defaults to 'ignore' (drop messages from non-paired users)
- Trust override tokens are session-scoped with 30-minute TTL matching existing ApprovalGate pattern
- Trust overrides cannot override immutable config keys
- No per-content trust overrides — session-level only
- FileProvenanceRegistry: Redis primary with 24h TTL, JSONB database column as backup, not a separate table
- SecurityMaintenanceJob runs on existing supervisor-memory-formation queue, not a new supervisor
- Injection detection latency must not exceed 50ms per tool result for pattern-based mode
- Total security pipeline overhead must not exceed 200ms per turn
- All security decisions must be logged to security_events with full context for forensic analysis
- All new features must be feature-flagged — existing sessions continue working without changes
- Messenger high-impact confirmation is independent of taint state — applies to all high-impact calls from messenger in Standard mode
- CliRuntimeExecutor hook (Hook 8) is separate from ToolGateway pipeline — it runs CLI subprocess directly
- Token estimation uses existing 4 chars/token pattern from MemoryContextBuilder for fast path
- No LLM-based injection detection — pattern and heuristic only to maintain latency budget


## Acceptance Criteria

- T1: At least 20 known injection patterns detected with score > threshold in Safe mode (0.3)
- T2: 100% of tool results from web/browser/file/MCP sources tagged UNTRUSTED automatically
- T3a: Legitimate feature_brief content passes through PromptIsolationService unchanged and is readable in system prompt
- T3b: Adversarial feature_brief containing 'ignore previous instructions' is stripped/escaped and does not appear as executable instruction in system prompt
- T4: All UNTRUSTED tool results wrapped in SecurityToolResult with correct metadata fields populated
- T5a: Tool results exceeding configured token limit are truncated with truncated=true flag
- T5b: Session context injection stays within context_budget_tokens limit
- T6: No raw HTML or JavaScript passes through ContentSanitizer when strip_html is enabled
- T7: Agent refuses to execute mutations triggered by tainted turn context when default_deny_external is enabled, requiring user confirmation
- T8: POST of session data to non-allowlisted host detected and blocked by ExfiltrationDetector
- T9: Injected instructions in text attachments detected and flagged before ChatIntentParser inlining
- T10: Commands exceeding per-user messenger rate limit (default 20/5min) rejected with informative message
- T11: Existing test suite passes with all non-immutable security features set to most permissive values
- T12: Security pipeline overhead measured at < 200ms per turn; injection detection < 50ms per tool result
- T13: All security decisions (classify, detect, block, escalate, strip) logged to security_events with session_id, tool_call_id, and details_json
- T14: SecurityConfigProvider reflects env() changes and database override changes (within 60s TTL) without restart
- T15: SecurityToolResult correctly composes ToolResult without modifying the original readonly object
- T16: TurnSecurityContext correctly propagates taint — once any UNTRUSTED result appears, subsequent calls show tainted=true
- T17: Immutable config keys (content_trust_enabled, injection_detection_enabled, exfiltration_detection) cannot be set to false via database overrides or env
- T18: Injection thresholds reject values above 0.9; tool_result_max_tokens rejects values above 32000
- T19: FileProvenanceRegistry correctly tracks file origins in Redis and falls back to JSONB on Redis miss
- T20: SecurityMaintenanceJob purges records older than retention period in batches, logs purge counts
- T21: ExfiltrationDetector independently detects all 5 pattern types: session token echo, conversation reflection, system prompt leak, credential patterns, PII echo
- T22: Messenger group channel policy 'ignore' drops messages from non-paired users; 'low_trust' processes them as UNTRUSTED
- T23: Content wrapping markers only present when content_wrapping_markers config is true
- T24: CliRuntimeExecutor Hook 8 scans pre-execution content independently of ToolGateway pipeline
- T25: Messenger high-impact confirmation triggers in Standard mode regardless of taint state
- T26: Trust override tokens expire after 30 minutes and are logged to security_events on creation and use

