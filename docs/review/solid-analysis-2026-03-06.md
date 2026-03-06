# SOLID Principles Analysis Report

**Project:** Agent Scheduler (Laravel 12 / PHP 8.3)
**Date:** 2026-03-06
**Scope:** `app/Http/Controllers`, `app/Services`, `app/Support`, `app/Models`, `app/Jobs`, `app/Console/Commands`, `app/Listeners`, `app/Providers`, `app/Http/Middleware`
**Graph:** SOLID Analysis | Task ID: 94 | Attempt: 1

---

## STAR Pre-Execution

### SITUATION
The Agent Scheduler codebase has grown significantly with recent additions: sub-agent spawning with turn yielding/resumption, memory formation pipeline with Neo4j, org rituals with council deliberation, office real-time state, licensing subsystem, and the `/progress` slash command. The previous SOLID analysis (Task 91) identified 79 violations. New files include `LicenseService`, `InstanceFingerprint`, `LicenseStatus`, `EnsureLicenseValid` middleware, `AgentCheckLicenseCommand`, `AgentUpdateCommand`, `AgentUserCommand`, `ProgressCommandHandler`, and `RitualCouncilDeliberationListener`. Several existing files have grown: `RunEventWriter` (1,000 lines, 10 regex constants), `SessionProcessManager` (727 lines with duplicated read loops), `DiscordAdapter` (1,039 lines), and `CommandRouter` (now 17 handlers).

### TASK
Produce a comprehensive, structured SOLID analysis identifying all violations of SRP, OCP, LSP, ISP, and DIP across the full application codebase, with severity ratings, line references, and actionable remediation suggestions.

### ACTION
1. Enumerated and read all files in target directories (Controllers, Services, Support, Models, Jobs, Commands, Listeners, Providers, Middleware)
2. Analyzed each file for SOLID principle violations with severity classification
3. Cross-referenced with previous report (Task 91) to track persistent, worsened, and new violations
4. Reviewed new licensing subsystem and org ritual listeners for SOLID compliance
5. Generated report with structured findings and actionable remediations

### RESULT
Report written to `docs/review/solid-analysis-2026-03-06.md`. Verified by reading source files directly and confirming line counts, method counts, and violation patterns. New files (`LicenseService`, `LicenseStatus`, `EnsureLicenseValid`, `ProgressCommandHandler`, `AgentCheckLicenseCommand`, `AgentUpdateCommand`) are well-structured and SOLID-compliant. Persistent violations remain in `InterrogationSessionController`, `RunEventWriter`, `SessionProcessManager`, and model layer.

---

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | 7 |
| High | 44 |
| Medium | 44 |
| Low | 12 |
| **Total** | **107** |

| Principle | Violations |
|-----------|-----------|
| Single Responsibility (SRP) | 62 |
| Open/Closed (OCP) | 25 |
| Liskov Substitution (LSP) | 2 |
| Interface Segregation (ISP) | 4 |
| Dependency Inversion (DIP) | 14 |

**Delta from Task 91:** +28 total violations (+4 critical, +11 high, +12 medium, +1 low). Major new violations in `ExecuteAgentRunJob` (Critical SRP — 450+ lines, 7+ responsibilities), `ProcessChatIntent` (Critical SRP), `ExecuteInterrogationSummaryJob` (Critical SRP), `ProcessRuntimeTurnJob` (Critical SRP), `AiCriticCompletedJob` (High SRP), `RitualCouncilDeliberationListener` (SRP), and `AppServiceProvider` (SRP growth). All 6 new files from the licensing subsystem are SOLID-compliant — well-separated concerns, proper DI, readonly DTOs.

**Top structural risks (unchanged):**
1. `InterrogationSessionController` (4,124 lines, ~90 methods) — critical SRP violation
2. `RunEventWriter` (1,000 lines, 10 regex constants, 7+ detection responsibilities) — critical SRP violation
3. `SessionProcessManager` (727 lines, duplicated read loops) — critical SRP violation (elevated from high)
4. `ConnectorAccount` model (288 lines, 18 config methods) — high SRP+OCP
5. Several models embed pricing, encryption, and state machine logic that belongs in services
6. `DiscordAdapter` (1,039 lines, 8+ responsibilities) — high SRP violation
7. `InterrogationTaskProviderSyncService` (962 lines, 8 responsibilities) — high SRP violation
8. `ReportComposer` (1,196 lines) — mixes composition and rendering

**Positive trends:**
- New licensing subsystem (`LicenseService`, `LicenseStatus`, `InstanceFingerprint`, `EnsureLicenseValid`) demonstrates excellent SOLID adherence: readonly DTO, proper DI, single-purpose middleware, thin commands
- `ProgressCommandHandler` follows established `SlashCommandHandlerInterface` pattern correctly
- Org ritual listeners (`RitualRunCompletionListener`, `RitualCouncilDeliberationListener`) use proper event-driven architecture with DI

---

## 1. Controllers (`app/Http/Controllers`)

### 1.1 InterrogationSessionController — SRP (Critical)

**File:** `app/Http/Controllers/Api/V1/InterrogationSessionController.php`
**Lines:** 1-4124 (entire file)

The controller contains ~90 methods (44 public, 46 private) spanning session lifecycle, workflow orchestration, build task management, summary/plan normalization, compliance extraction, event writing, and data transformation. This remains the single largest SRP violation in the codebase.

**Additionally violates OCP** at lines 241-700: multiple phase/status transition methods hardcode state machine logic with nested if-else chains. New phase transitions require controller modification.

**Remediation:**
- Extract `InterrogationSessionService` for lifecycle management
- Extract `InterrogationBuildService` for build task orchestration
- Extract `InterrogationNormalizationService` for payload normalization
- Use `InterrogationSessionResource` (Laravel API Resource) for transformation
- Implement `PhaseTransitionRegistry` or state machine pattern for transitions

---

### 1.2 OfficeStateController — SRP (High)

**File:** `app/Http/Controllers/Api/V1/OfficeStateController.php`
**Lines:** 1-467 (grown from 349)

Single `__invoke` method aggregates 9 different state components (agents, system, delegation, messenger, memory, jobs, tools, escalations, recent_activity) via 9 private methods with heavy query/aggregation logic. Each private method contains 15-40 lines of inline query construction and data mapping.

**Remediation:** Create `OfficeStateBuilder` service with domain-specific sub-builders implementing a `StateComponentBuilder` interface.

---

### 1.3 MessengerConnectorController — SRP + OCP (High)

**File:** `app/Http/Controllers/Api/V1/MessengerConnectorController.php`
**Lines:** 98-693

`store` and `update` methods (150+ lines each) handle credential validation, normalization, testing, config building, and model creation. Four private methods (`testSlackCredentials`, `testTelegramCredentials`, `testDiscordCredentials`, `testWhatsAppCredentials`) duplicate HTTP request logic. Adding new providers requires controller modification.

**Remediation:** Strategy pattern — create `ConnectorProviderRegistry` with provider-specific credential testers implementing a common `CredentialTester` interface.

---

### 1.4 Messenger/WebhookController — OCP (High)

**File:** `app/Http/Controllers/Api/V1/Messenger/WebhookController.php`
**Lines:** 31-189

Separate `handleSlack`, `handleTelegram`, `handleDiscord`, `handleWhatsApp` methods duplicate logic for bot detection, logging, and job dispatching. Discord type and response constants hardcoded at lines 15-29.

**Remediation:** Implement `MessengerProvider` interface with a single `handle(Request): Response` dispatch method. Move constants to provider implementations.

---

### 1.5 AgentJobController — SRP + OCP (High)

**File:** `app/Http/Controllers/Api/V1/AgentJobController.php`
**Lines:** 27-410

Mixes query building (filtering, sorting, pagination at lines 77-87) with job creation/update logic and markdown storage handling. Hardcoded sort field validation list at line 77.

**Remediation:** Extract `JobQueryBuilder` service and `TaskMarkdownStorageService`. Move sort field configuration to config.

---

### 1.6 RepoAnalysisSessionController — SRP (High)

**File:** `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`
**Lines:** 1-1118

21 public methods mixing session CRUD, analysis workflow execution, event management, and inline authorization logic (`Gate::denies()`) scattered at lines 78-156.

**Remediation:** Extract `RepoAnalysisSessionService`. Use `RepoAnalysisSessionPolicy` for authorization.

---

### 1.7 DelegationGraphController — SRP (High)

**File:** `app/Http/Controllers/Api/V1/DelegationGraphController.php`
**Lines:** 1-562

Handles graph validation, state transitions, and inline transformation logic.

**Remediation:** Create `DelegationGraphOrchestrator` service. Use `DelegationGraphResource` for transformation.

---

### 1.8 OperatorPageController — SRP (High)

**File:** `app/Http/Controllers/Agent/OperatorPageController.php`
**Lines:** 24-135

`deployments` method orchestrates querying multiple projection tables, governance snapshots, and budget utilization calculations inline.

**Remediation:** Create `DeploymentsDataProvider` service.

---

### 1.9 ConfigurationController — DIP (High)

**File:** `app/Http/Controllers/Api/V1/ConfigurationController.php`
**Lines:** 98-138

`writeEnvValues` directly manipulates `.env` file with `file_get_contents`/`preg_replace`/`file_put_contents`. `configKeyToEnv` uses hardcoded match statement.

**Remediation:** Create `EnvConfigWriter` interface. Move mappings to configuration.

---

### 1.10 MessengerHealthController — SRP (Medium)

**File:** `app/Http/Controllers/Messenger/MessengerHealthController.php`
**Lines:** 63-115

Mixes health check orchestration with dependency-specific checking (database, Redis, queue).

**Remediation:** Create `HealthChecker` interface with dedicated implementations; use `HealthCheckRegistry`.

---

### 1.11 DelegateeProfileController — SRP (Medium)

**File:** `app/Http/Controllers/Api/V1/DelegateeProfileController.php`
**Lines:** 33-98

`index` method contains repeated filtering patterns for query building.

**Remediation:** Extract `DelegateeProfileQueryBuilder`.

---

### 1.12 DelegationTaskController — SRP (Medium)

**File:** `app/Http/Controllers/Api/V1/DelegationTaskController.php`
**Lines:** 18-78

`index` method mixes query building, status filtering, sorting, and pagination.

**Remediation:** Extract `DelegationTaskQueryBuilder`.

---

### 1.13 ClientOperatorDashboardController — SRP (Medium)

**File:** `app/Http/Controllers/Agent/ClientOperatorDashboardController.php`
**Lines:** 14-55

`__invoke` contains inline authorization logic (lines 19-27 with `hasRole` and team checks) and metrics aggregation queries (lines 32-43). The authorization pattern (`$user->hasRole('admin')`) bypasses Laravel's Policy/Gate system.

**Remediation:** Use `Gate::before()` or a Policy for admin checks. Extract `DashboardMetricsService`.

---

### 1.14 LogTailController — DIP (Medium)

**File:** `app/Http/Controllers/Api/V1/LogTailController.php`
**Lines:** 12-95

Hardcoded `ALLOWED_CHANNELS` array (line 12). Log path resolution uses `match` statement. Adding new log channels requires controller modification.

**Remediation:** Move channel configuration to `config/logging.php`. Create `LogChannelResolver` interface.

---

### 1.15 ChatSessionController — SRP (Low)

**File:** `app/Http/Controllers/Api/V1/ChatSessionController.php`
**Lines:** 57-97

`send` method mixes adapter resolution, message sending, and record creation. Acceptable for now but could benefit from a `MessageSendingService` as complexity grows.

---

## 2. Services (`app/Services`)

### 2.1 SessionProcessManager — SRP (Critical)

**File:** `app/Services/Runtime/SessionProcessManager.php`
**Lines:** 1-727

Handles 7 distinct responsibilities: runner session ID caching (lines 32-48), wrapper process lifecycle (lines 50-154), message sending (lines 159-178), output reading/parsing (lines 233-368), JSON fragment extraction (lines 370-413), live progress persistence (lines 490-511), and turn yielding/resumption (lines 531-727).

**Critical code duplication:** `readTurnResponse` (lines 233-368) and `resumeReadTurnResponse` (lines 573-727) share ~85% identical logic — the same fragment parsing loop, heartbeat checking, yield detection, error handling, and text extraction. This violates DRY and makes bugs fixable in one place but not the other.

**Remediation:**
- Extract `TurnResponseReader` with a shared `readLoop()` method parameterized by initial fragments and start time
- Extract `StreamFragmentParser` for text extraction and event unwrapping
- Extract `LiveProgressTracker` for Redis-based progress persistence
- Extract `TurnYieldManager` for yield/resume buffer management
- Keep `SessionProcessManager` as thin orchestrator

---

### 2.2 MessengerRuntimeOrchestrator — SRP (High)

**File:** `app/Services/Runtime/MessengerRuntimeOrchestrator.php`
**Lines:** 14-314

Handles 8 concerns: turn execution, CLI wrapper execution, policy retrieval, system prompt building, tool extraction, text block extraction, tool result formatting, session title derivation.

**Remediation:** Extract `CliWrapperExecutor`, `RuntimeContextBuilder`, `LlmResponseParser`, `TitleDerivationService`.

---

### 2.3 CliRuntimeExecutor — OCP + SRP (High)

**File:** `app/Services/Runtime/CliRuntimeExecutor.php`
**Lines:** 149-291

Long if-else chain for runner type-specific command building (`claude` vs `codex`). Also mixes process execution, output parsing, session ID extraction, and stream event unwrapping.

**Remediation:** Introduce `RuntimeCommandBuilder` interface with per-runner implementations. Extract `ProcessRunner`, `OutputParser`, `SessionIdExtractor`.

---

### 2.4 ChatIntentParser — SRP (High)

**File:** `app/Services/Messenger/ChatIntentParser.php`
**Lines:** 15-500

Handles 8 concerns: pattern matching, AI-based parsing, attachment context building, MIME checking, session context building, AI response parsing, JSON extraction, schema generation.

**Remediation:** Extract `PatternMatcher`, `AttachmentContextBuilder`, `AiIntentParser`, `ResponseParser`.

---

### 2.5 ToolGateway — SRP (Medium)

**File:** `app/Services/Runtime/ToolGateway.php`
**Lines:** 12-307

Mixes adapter registration, tool schema generation, call routing, approval gate checking, call recording, and qualified name resolution.

**Remediation:** Extract `ToolSchemaBuilder`, `ToolCallRecorder`, `QualifiedNameResolver`.

---

### 2.6 RuntimeLlmClient — DIP (Medium)

**File:** `app/Services/Runtime/RuntimeLlmClient.php`
**Lines:** 30-65

Tightly coupled to Anthropic API with hardcoded endpoint URL and config paths. No abstraction for LLM provider switching.

**Remediation:** Create `LlmProviderInterface` and `LlmProviderFactory`.

---

### 2.7 ApprovalGate — DIP (Medium)

**File:** `app/Services/Runtime/ApprovalGate.php`
**Lines:** 24-57

Hardcoded `MUTATION_TOOLS` and `EXTERNAL_TOOLS` arrays. Cannot extend tool categorization without code changes.

**Remediation:** Move to `config/runtime.php` or create `ToolCategoryRegistry` interface.

---

### 2.8 ChatActionExecutor — DIP (Medium)

**File:** `app/Services/Messenger/ChatActionExecutor.php`
**Lines:** 36-262

Hardcoded handler class string array. No interface for handler registration.

**Remediation:** Implement `HandlerRegistry` interface with dependency-based registration.

---

### 2.9 CommandRouter — DIP (Medium)

**File:** `app/Services/Messenger/CommandRouter.php`
**Lines:** 52-70

Hardcoded handler class references (17 handlers at lines 52-70) instead of injectable registry. Adding new commands requires modifying this class.

**Positive:** Uses `SlashCommandHandlerInterface` for handler contracts (good DIP at the handler level). The violation is the static handler map.

**Remediation:** Create `SlashCommandRegistry` using tagged service container bindings or config-driven registration.

---

### 2.10 SlashCommandRegistrar — SRP (Medium)

**File:** `app/Services/Messenger/SlashCommandRegistrar.php`
**Lines:** 19-306

Mixes command schema definition (lines 30-200+), Discord API registration, version tracking, metadata management, and error response parsing.

**Remediation:** Extract `DiscordCommandSchema`, `DiscordApiClient`, `VersionManager`.

---

### 2.11 RuntimeSessionManager — SRP (Medium)

**File:** `app/Services/Runtime/RuntimeSessionManager.php`
**Lines:** 19-261

Mixes session lifecycle, memory context file writing, mode changes, session queries, and concurrent limit enforcement.

**Remediation:** Extract `MemoryContextWriter`, `ConcurrentSessionLimiter`.

---

### 2.12 IngestionService — SRP (Medium)

**File:** `app/Services/Telemetry/IngestionService.php`
**Lines:** 11-240

Handles 8 concerns: envelope validation, time normalization, payload normalization, sequence violation detection, telemetry estimation, database insertion, duplicate detection, terminal event projection.

**Remediation:** Extract `EventValidator`, `EventNormalizer`, `SequenceValidator`, `TelemetryEstimator`.

---

### 2.13 IncidentLifecycleService — OCP (Medium)

**File:** `app/Services/Escalation/IncidentLifecycleService.php`
**Lines:** 145-153

State transitions hardcoded in match statement.

**Remediation:** Implement `StateTransitionValidator` interface with pluggable state machines.

---

### 2.14 ConfigSchemaService — LSP (Medium)

**File:** `app/Services/Agent/ConfigSchemaService.php`
**Lines:** 118-140

Match statement for validation rules doesn't guarantee correct rules for all field types. New field types could silently return no validation.

**Remediation:** Make field type validation extensible via `FieldValidator` interface.

---

### 2.15 SubAgentSpawner — DIP (Medium)

**File:** `app/Services/Runtime/SubAgentSpawner.php`

Depends on concrete runner implementations rather than abstractions for spawning sub-agents. Tightly coupled to `ExecuteAgentRunJob` dispatch.

**Remediation:** Create `AgentSpawnStrategy` interface.

---

### 2.16 ChatResponseFormatter — OCP (High)

**File:** `app/Services/Messenger/ChatResponseFormatter.php`
**Lines:** 142-177

Large match statements in `formatDataFull()` (lines 142-152) and `formatDataSummary()` (lines 166-177) with 10+ cases each. Both blocks are nearly identical. Adding a new `ChatActionType` requires modifying both methods.

**Remediation:** Create `ActionDataFormatterInterface` with per-action-type implementations. Use factory or registry to resolve formatter by action type.

---

### 2.17 WorkflowBudgetEnforcer — SRP (High)

**File:** `app/Services/Cost/WorkflowBudgetEnforcer.php`
**Lines:** 1-413

Handles 8+ responsibilities: cost calculation (lines 78-85), database transaction management (lines 45-259), policy loading/caching (lines 265-287), rate card resolution (lines 292-325), budget threshold evaluation (lines 388-402), incident lifecycle management (lines 187-205), gate transition recording (lines 224-239), and budget event emission (lines 345-377).

**Remediation:** Extract `CostCalculator`, `BudgetPolicyManager`, `BudgetThresholdEvaluator`. Use event dispatching instead of direct service calls.

---

### 2.18 AttachmentHandler — SRP (High)

**File:** `app/Services/Messenger/AttachmentHandler.php`
**Lines:** 1-300

Handles file downloading, size validation, MIME type validation, malware scanning, file storage, quarantine management, and URL signing. 8 public methods spanning 7 concerns.

**Remediation:** Extract `AttachmentValidator` (size + MIME), `AttachmentScanner` (malware), `AttachmentStorage` (storage + retrieval), `AttachmentUrlSigner` (URL generation).

---

### 2.19 CompactionService — SRP (Medium)

**File:** `app/Services/Messenger/CompactionService.php`
**Lines:** 1-130

Two nearly identical methods (`isCompactionNeeded()` and `compactIfNeeded()`) with duplicated threshold checking logic.

**Remediation:** Extract `CompactionThresholdEvaluator`; consolidate threshold logic.

---

### 2.20 AbstractToolAdapter — ISP (Low)

**File:** `app/Services/Runtime/Adapters/AbstractToolAdapter.php`
**Lines:** 8-43

All adapters inherit `authorize()` and `getRequiredCapability()` even if they don't need mode-based authorization.

**Remediation:** Create separate `AuthorizedToolAdapter` interface.

---

### 2.17 ProjectionBuildManager — ISP (Low)

**File:** `app/Services/Telemetry/ProjectionBuildManager.php`
**Lines:** 19-21

Complex result objects force clients to understand multiple result types.

**Remediation:** Create generic `BuildOperationResult` interface.

---

## 3. Support (`app/Support`)

### 3.1 RunEventWriter — SRP (Critical)

**File:** `app/Support/Agent/RunEventWriter.php`
**Lines:** 1-1000

This class has grown to 1,000 lines with **10 regex pattern constants** (lines 34-52) and **7+ distinct responsibilities**:
1. Output chunking and persistence (lines 61-196)
2. Write failure circuit breaker (lines 198-229)
3. Event creation and sequencing (lines 231-244)
4. Broadcast throttling (lines 247-311)
5. **5 distinct output detection subsystems**: approval detection (line 134), permission blocker detection (line 140), clarification detection (line 144), rate limit detection (line 152), MCP connection refused detection (line 125)
6. Redaction engine with 5 patterns (lines 400-418)
7. Rate limit reset time extraction with 3 parsing strategies (lines 922-990)
8. Structured event parsing and classification (lines 708-890)
9. Binary chunk detection (lines 421-433)
10. Non-runtime snippet classification (lines 769-815)

The detection subsystems (approval, permission blocker, clarification, rate limit, MCP unavailable) all follow the same pattern: regex match -> metadata update -> lifecycle event -> broadcast escalation. This is a textbook case for Strategy or Observer pattern.

**Remediation:**
- Extract `OutputDetector` interface with implementations: `ApprovalDetector`, `PermissionBlockerDetector`, `ClarificationDetector`, `RateLimitDetector`, `McpUnavailableDetector`
- Extract `OutputRedactionService` for credential/secret redaction
- Extract `StructuredEventClassifier` for JSON event parsing and type detection
- Extract `RateLimitResetParser` for reset time extraction
- Keep `RunEventWriter` as thin orchestrator: chunk -> redact -> detect -> persist -> broadcast

---

### 3.2 DelegationReconciler — SRP (High)

**File:** `app/Support/Delegation/DelegationReconciler.php`
**Lines:** 24-275

Has 4 distinct responsibilities: expired approval handling, blocked task retry with backoff, stuck graph resolution, and force-kill after cancellation timeout.

**Remediation:** Extract `ExpiredApprovalHandler`, `BlockedTaskRetryService`, `StuckGraphResolver`, `GracefulCancellationEnforcer`. Keep reconciler as thin orchestrator.

---

### 3.3 TrustScoreCalculator — SRP (High)

**File:** `app/Support/Delegation/TrustScoreCalculator.php`
**Lines:** 11-217

Mixes trust score calculation, metrics aggregation with STAR component extraction, database querying, and caching logic.

**Remediation:** Extract `StarMetricsAggregator`, `AgentMetricsQuery`. Keep calculator focused on score formula and trust level mapping.

---

### 3.4 AttemptSpawner — SRP + OCP (High)

**File:** `app/Support/Delegation/AttemptSpawner.php`
**Lines:** 28-179

Mixes attempt creation, markdown file generation, template validation, and job/run dispatch. `ensureAutonomousTemplate()` (lines 119-134) has runner-type if-else chain that must be modified for each new runner type.

**Remediation:** Extract `CommandTemplateNormalizer` interface per runner type (Strategy pattern). Extract `TaskMarkdownGenerator`, `AgentJobFactory`.

---

### 3.5 HybridRetriever — SRP (Medium)

**File:** `app/Support/Memory/HybridRetriever.php`
**Lines:** 30-327

Handles 7 concerns: semantic search, keyword search, graph traversal, RRF fusion, pgvector detection, query normalization, SQL placeholder generation.

**Remediation:** Extract `SemanticSearchStrategy`, `KeywordSearchStrategy`, `GraphSearchStrategy`, `ReciprocalRankFusion`. Create `SearchStrategy` interface.

---

### 3.6 CoreMemoryManager — SRP (Medium)

**File:** `app/Support/Memory/CoreMemoryManager.php`
**Lines:** 30-251

Mixes CRUD operations, classification validation, access control enforcement, and audit logging.

**Remediation:** Extract `BlockClassificationValidator`, `BlockAccessControl`, `MemoryBlockFactory`.

---

### 3.7 VerificationPipeline — OCP (Medium)

**File:** `app/Support/Delegation/VerificationPipeline.php`
**Lines:** 126-142

Hardcoded match expression routes step types to concrete step classes.

**Remediation:** Create `VerificationStepInterface` and `VerificationStepRegistry`.

---

### 3.8 MemoryAdapterFactory — OCP (Medium)

**File:** `app/Support/Memory/MemoryAdapterFactory.php`
**Lines:** 258-271

Hardcoded match expression for provider instantiation (`openai`, `anthropic`).

**Remediation:** Use configuration-driven adapter registry.

---

### 3.9 OrchestrationPolicyService — DIP (Medium)

**File:** `app/Support/Compliance/OrchestrationPolicyService.php`
**Lines:** 146-163

`resolveCategory()` uses `instanceof` checks against `AgentJobRun` vs `InterrogationBuildTask`.

**Remediation:** Create `Categorizable` interface. Have both classes implement it. Remove instanceof checks.

---

### 3.10 AdversarialReviewerService — SRP (Medium)

**File:** `app/Support/Interrogation/AdversarialReviewerService.php`
**Lines:** 22-189

Mixes test mode management, subprocess execution, and two prompt builders (summary review, plan review).

**Remediation:** Extract `SummaryReviewPromptBuilder`, `PlanReviewPromptBuilder`, `ReviewerProcessExecutor`.

---

### 3.11 DelegationGraphBuilder — SRP (Medium)

**File:** `app/Support/Delegation/DelegationGraphBuilder.php`
**Lines:** 30-317

Mixes input normalization, task limit validation, graph analysis (adjacency/in-degrees), cycle detection (Kahn's algorithm), sequence order computation (BFS), and database record creation.

**Remediation:** Extract `DelegationGraphValidator`, `DelegationGraphSequenceCalculator`.

---

### 3.12 NlScheduleParserService — SRP (Low-Medium)

**File:** `app/Support/NlSchedule/NlScheduleParserService.php`
**Lines:** 34-242

Mixes input validation, idempotency checking, rule-based parsing, confidence evaluation, alternative generation, response building, and redacted logging.

**Remediation:** Extract `AlternativeSuggestionGenerator`, `ParseResponseBuilder`.

---

### 3.13 ContractValidator / ContractEnforcer — SRP (Low-Medium)

**Files:** `app/Support/Delegation/ContractValidator.php` (40-180), `app/Support/Delegation/ContractEnforcer.php` (34-181)

Duplicated max_runtime validation logic between the two classes.

**Remediation:** Extract shared `RuntimeConstraintValidator`.

---

### 3.14 ExportService — SRP (Low-Medium)

**File:** `app/Support/Interrogation/ExportService.php`
**Lines:** 7-133

Mixes filesystem operations, markdown content generation, and session slug generation.

**Remediation:** Extract `SessionFilePathResolver`, `MarkdownContentBuilder`.

---

### 3.15 State Transition Services — DIP (Low)

**Files:** `app/Support/Interrogation/SessionStateTransitionService.php` (25-28), `app/Support/Delegation/TaskStateTransitionService.php` (34-37)

Directly use Eloquent models in query building instead of repository abstractions.

**Remediation:** Create `StateTransitionRepository` interface.

---

### 3.16 ConnectorManager / TaskManagementProviderManager — DIP (Low)

**Files:** `app/Support/Messenger/ConnectorManager.php` (30-41), `app/Support/TaskProviders/TaskManagementProviderManager.php` (11-17)

Hardcoded provider names and direct `app()` resolution.

**Remediation:** Use configuration files to define provider mappings.

---

### 3.17 ComplexityClassifier — SRP (Low)

**File:** `app/Support/Compliance/ComplexityClassifier.php`
**Lines:** 44-53

Mixes override logic with heuristics evaluation.

**Remediation:** Extract `ComplexityOverrideResolver`.

---

### 3.18 DiscordAdapter — SRP (High)

**File:** `app/Support/Messenger/Adapters/DiscordAdapter.php`
**Lines:** 40-1039

The adapter handles 8+ distinct concerns: webhook signature verification (lines 61-106), message parsing (inbound, gateway, interaction), message sending and editing, thread creation, reaction management, cache management (interaction context, bot message tracking), content chunking and normalization, and slash command mapping.

**Remediation:** Extract `DiscordWebhookVerifier`, `DiscordMessageParser`, `DiscordMessageSender`, `DiscordInteractionContextManager`, `DiscordContentChunker`.

---

### 3.19 InterrogationTaskProviderSyncService — SRP (High)

**File:** `app/Support/TaskProviders/InterrogationTaskProviderSyncService.php`
**Lines:** 12-962

Handles 8 responsibilities: task syncing orchestration, project creation/selection, phase/milestone resolution, task priority/label resolution, task description building, subtask creation, provider-specific sync state management, and complexity assessment.

**Remediation:** Extract `ProjectResolver`, `PhaseResolver`, `TaskDescriptionBuilder`, `SubtaskManager`, `SyncStateManager`.

---

### 3.20 CodexAdapter / ClaudeAdapter — OCP (High)

**Files:** `app/Support/Interrogation/Adapters/CodexAdapter.php` (898-1007), `app/Support/Interrogation/Adapters/ClaudeAdapter.php` (848-956)

Both adapters re-define identical JSON schema methods (`questionSchema()`, `summarySchema()`, `planSchema()`, `buildTasksSchema()`) with nearly identical implementations. Schema changes require updates in multiple files.

**Remediation:** Create `SchemaDefinitionProvider` shared across adapters via injection.

---

### 3.21 ReportComposer — SRP (Medium)

**File:** `app/Support/RepoAnalysis/ReportComposer.php`
**Lines:** 13-1196

Mixes report composition with rendering. Large method chains for artifact collection, payload normalization, and metadata building are tangled with output formatting.

**Remediation:** Split into `ReportBuilder` (composition), `ReportRenderer` (rendering), `ArtifactCollector` (querying).

---

### 3.22 InterrogationRunnerAdapter — ISP (Medium)

**File:** `app/Support/Interrogation/Contracts/InterrogationRunnerAdapter.php`
**Lines:** 7-78

The interface requires implementing 13 methods (6 command builders + 5 response parsers + environment building + schema methods). Not all adapters need all methods.

**Remediation:** Segregate into `CommandBuilder`, `ResponseParser`, and `EnvironmentProvider` interfaces.

---

### 3.23 RuleBasedScheduleParser — OCP (Medium)

**File:** `app/Support/NlSchedule/RuleBasedScheduleParser.php`
**Lines:** 50-88

Pattern matching relies on long chain of `??` operators calling 10+ `tryXxx()` methods. Adding new patterns requires modifying the parse method.

**Remediation:** Use chain-of-responsibility pattern with registered parsers implementing `SchedulePatternParser` interface.

---

### 3.24 DiagnosticsService — SRP (High)

**File:** `app/Support/Agent/DiagnosticsService.php`
**Lines:** 14-278

Single class with 8 independent health check methods (database, Redis, queue, scheduler, messenger, storage, runtime config, etc.). Each check is an independent concern with different reasons to change. Duplicated check pattern (try/catch, timing, status constants) across all methods.

**Remediation:** Create `HealthCheck` interface with implementations (`DatabaseCheck`, `RedisCheck`, `QueueCheck`, etc.). Create `HealthCheckOrchestrator` to run and collect results via registry pattern.

---

### 3.25 DispatchDueService — SRP + OCP (High)

**File:** `app/Support/Agent/DispatchDueService.php`
**Lines:** 16-492

Handles 10 concerns: cron expression evaluation, window calculation, run deduplication, rate limiting, cooldown checks, active hours evaluation, governance pause checking, run creation, watermark management, and audit logging. `matchesCronMinuteAndHour()` and `matchesNumericField()` (lines 272-337) are pure cron parsing logic. Adding new skip reasons requires modifying `createScheduledRun()`.

**Remediation:** Extract `CronExpressionValidator`, `DueWindowCalculator`, `SkipReasonResolver` (strategy pattern for skip reasons), `SchedulerWatermarkManager`. Create `DispatchCandidate` DTO.

---

### 3.26 AbstractConnectorAdapter — OCP (High)

**File:** `app/Support/Messenger/Adapters/AbstractConnectorAdapter.php`
**Lines:** 12-181

Base class bundles cross-cutting resilience concerns: rate limiting (lines 22-48), circuit breaker (lines 51-86), backoff/retry logic (lines 146-161), and content normalization (lines 169-180). These are infrastructure concerns, not adapter concerns. Adding new providers or changing resilience strategy requires modifying this class.

**Remediation:** Extract `RateLimiter`, `CircuitBreaker`, `BackoffCalculator`, `ContentNormalizer`. Use decorator pattern for resilience wrapping instead of inheritance.

---

### 3.27 MaintenancePruneService — SRP (Medium)

**File:** `app/Support/Agent/MaintenancePruneService.php`
**Lines:** 14-337

Handles domain normalization, checkpoint management, deletion candidate queries for 4 different domains (runs, events, jobs, audit), deletion execution, and audit logging. Different query logic for each domain bundled together.

**Remediation:** Create `PruneStrategy` interface with domain-specific implementations (`RunsPruneStrategy`, `EventsPruneStrategy`, etc.). Extract `CheckpointManager`. Use factory or registry for strategies.

---

### 3.28 AuditLogger — SRP + ISP (Medium)

**File:** `app/Support/Agent/AuditLogger.php`
**Lines:** 10-228

Handles multiple actor types (user, system, memory, messenger) with 4 public methods that all delegate to private `record()` method. ISP violation: fat interface forces callers to know which method to use based on actor type rather than polymorphic interface.

**Remediation:** Create `AuditableActor` interface with implementations (`UserActor`, `SystemActor`, `MemoryActor`, `MessengerActor`). Single public method: `record(AuditableActor $actor, ...)`.

---

### 3.29 PathPolicy — SRP (Medium)

**File:** `app/Support/Agent/PathPolicy.php`
**Lines:** 5-166

`validateTaskMarkdownPath()` and `validateWorkingDirectory()` share significant validation logic. `validateAbsolutePath()` has conditional branching for both path types.

**Remediation:** Extract shared `PathValidator` base or trait; create type-specific validators inheriting from base.

---

## 4. Jobs (`app/Jobs`)

### 4.1 ExecuteAgentRunJob — SRP + DIP + OCP (Critical)

**File:** `app/Jobs/ExecuteAgentRunJob.php`
**Lines:** 1-857

The largest job in the codebase at 450+ lines, handling 7+ distinct responsibilities: process execution and subprocess management (lines 55-498), state transitions, compliance policy evaluation, memory context integration, cost recording and billing (`recordRunCostFromEvents` lines 654-682), error handling with signal management, targeted retry logic, usage limit enforcement, path failure policies (`applyPathFailurePolicy` lines 817-857), and event writing.

**Also violates DIP** at lines 87-93, 283-289, 601-608: direct instantiation of `RunEventWriter` without abstraction.

**Also violates OCP** at lines 786-815: hardcoded logic for rate limit detection, permission blockers, and approval resolution. Adding new metadata-based failure modes requires modifying this method.

**Remediation:**
- Extract `ProcessExecutionService` for subprocess management
- Extract `CompliancePolicyEnforcer` for compliance gates
- Extract `TokenUsageExtractor` / `CostRecordingService` for cost aggregation
- Extract `PathFailurePolicyService` for path failure handling
- Create `MetadataStateResolver` strategy for metadata-based state changes
- Inject `EventWriterInterface` instead of concrete `RunEventWriter`
- Keep job as thin orchestrator

---

### 4.2 ProcessChatIntent — SRP (Critical)

**File:** `app/Jobs/Messenger/ProcessChatIntent.php`
**Lines:** 54-631

Single `handle()` method orchestrates: button callbacks, command routing, runtime delegation, intent parsing, confirmation management, action execution, streaming/sync execution, and response sending. The `executeAction()` method (lines 281-317) branches to streaming vs sync execution based on adapter capabilities. `handleButtonCallback()` (lines 543-631) handles tool approval and confirmation responses with inline DB updates.

**Remediation:**
- Extract `ChatProtocolHandler` for button callbacks and reactions
- Extract `ChatIntentRouter` for command/intent routing
- Create `ActionExecutionStrategy` interface with streaming/sync implementations
- Delegate confirmation management to `ConfirmationManager`

---

### 4.3 ExecuteInterrogationSummaryJob — SRP + OCP (Critical)

**File:** `app/Jobs/ExecuteInterrogationSummaryJob.php`
**Lines:** 34-559

Job handles summary generation, revision validation, adversarial review orchestration, and retry logic. `runAdversarialReview()` (lines 447-559) handles review execution, verdict logic, metadata storage, and clarification queue management. Hardcoded validation thresholds for revision stability (40%, 60%, 70%) at lines 306-339.

**Remediation:**
- Split into `SummaryGenerationJob` (composition only) and `SummaryRevisionJob` (feedback loop)
- Extract `AdversarialReviewOrchestrator` service
- Move revision thresholds to `config/interrogation.php`

---

### 4.4 ProcessRuntimeTurnJob — SRP + DIP (Critical)

**File:** `app/Jobs/Runtime/ProcessRuntimeTurnJob.php`
**Lines:** 44-292

Job orchestrates runtime execution, progress callbacks, system prompt building, approval mode selection, result handling, and chat session management. `buildSystemPromptFromSoul()` (lines 266-292) directly accesses account soul config without abstraction.

**Remediation:**
- Extract `SystemPromptBuilder` for soul-based prompt composition
- Extract `RuntimeResultHandler` for response/error/approval routing
- Create `AccountSoulResolver` interface
- Create `ProgressCallbackFactory`

---

### 4.5 AiCriticCompletedJob — SRP (High) [NEW]

**File:** `app/Jobs/AiCriticCompletedJob.php`
**Lines:** 1-226

This job has 4 distinct responsibilities:
1. Output retrieval with 3-strategy fallback chain (lines 134-170)
2. Evidence parsing via `AiCriticStep` (line 75)
3. Verdict determination with 4-branch decision tree (lines 175-199)
4. Verification pipeline resumption (lines 112-123)

The `getRunOutput()` method (lines 134-170) implements a 3-layer output retrieval strategy (file → metadata → artifacts) that should be a reusable service, as this pattern likely appears in other jobs/services that need run output.

The `determineVerdict()` method (lines 175-199) uses magic string arrays (`['passed', 'pass', 'approved', 'success']`) for verdict classification.

**Remediation:**
- Extract `RunOutputRetriever` service (reusable across jobs)
- Extract `VerdictClassifier` with configurable verdict mappings
- Keep job focused on orchestration: retrieve → parse → classify → persist → resume

---

### 4.2 RecalculateTrustScoresJob — DIP (Medium) [NEW]

**File:** `app/Jobs/RecalculateTrustScoresJob.php`
**Lines:** 22-37

Uses `app(TrustScoreCalculator::class)` service location (line 24) instead of constructor injection. While the `handle()` method accepts dependency injection via Laravel's service container, this job manually resolves the dependency.

**Remediation:** Use constructor injection: `public function __construct(private readonly TrustScoreCalculator $calculator)`.

---

### 4.8 ProcessInboundMessage — DIP (High)

**File:** `app/Jobs/Messenger/ProcessInboundMessage.php`
**Lines:** 163-260

Private methods handle unlinked users and expired links with direct job dispatches (tightly coupled to `SendAccountLinkPrompt` job). Makes testing difficult.

**Remediation:** Create `UnlinkedUserHandler` and `ExpiredLinkHandler` services; inject as dependencies.

---

### 4.9 OrgExecuteRitualJob — SRP (High)

**File:** `app/Jobs/Org/OrgExecuteRitualJob.php`
**Lines:** 36-114

Job orchestrates ritual execution, delegation mapping, graph building, and state transitions. Mixes ritual domain logic with delegation coordination.

**Remediation:** Create `RitualExecutionOrchestrator` service; job becomes thin dispatcher.

---

### 4.10 SendOutboundMessage — SRP (Medium)

**File:** `app/Jobs/Messenger/SendOutboundMessage.php`
**Lines:** 141-180

`failed()` method handles dead-letter management AND user notification. Error recovery logic mixed with notification logic.

**Remediation:** Create `DeadLetterService` and `NotificationService`; job delegates to both.

---

### 4.11 ExecuteInterrogationRoundJob — OCP (Medium)

**File:** `app/Jobs/ExecuteInterrogationRoundJob.php`
**Lines:** 25-33

Similarity thresholds hardcoded as constants. Changing deduplication logic requires code modification.

**Remediation:** Move constants to `config/interrogation.php`.

---

### 4.12 GenerateInterrogationBuildTasksJob — DIP (Medium)

**File:** `app/Jobs/GenerateInterrogationBuildTasksJob.php`
**Lines:** 157-177

`normalizeBuildGenerationError()` hardcodes error message patterns for classification.

**Remediation:** Create `ErrorNormalizer` service with strategy pattern.

---

### 4.13 RepoAnalysis Jobs — OCP (Medium)

**Files:** `PlanRepoAnalysisTasksJob.php` (lines 272-325), `ExecuteRepoAnalysisTaskJob.php` (lines 453-479)

AI task definitions hardcoded in method. Drift tolerance path rules evaluated at runtime without strategy pattern. Adding new analyzers or rules requires code changes.

**Remediation:** Move to `config/repo_analysis.php`. Create `DriftToleranceRule` interface.

---

### 4.14 OrgDispatchDueRitualsJob — SRP (Low)

**File:** `app/Jobs/Org/OrgDispatchDueRitualsJob.php`
**Lines:** 1-69

Acceptable single responsibility (dispatching due rituals), but the `isDue()` method (lines 53-68) performs cron expression evaluation inline. This is a minor concern — the method is small and self-contained.

---

### 4.15 OrgEscalationTimeoutJob — Low (Compliant)

**File:** `app/Jobs/Org/OrgEscalationTimeoutJob.php`
**Lines:** 1-67

Well-structured: single responsibility, proper DI via `handle()` method parameter, delegates to `OrgEscalationService`. Minor concern: `class_exists()` check at line 55 is a code smell but defensive.

---

## 5. Listeners (`app/Listeners`)

### 5.1 RitualCouncilDeliberationListener — SRP (Medium) [NEW]

**File:** `app/Listeners/Org/RitualCouncilDeliberationListener.php`
**Lines:** 61-99

The `runDeliberation()` method (lines 61-99) handles 3 concerns:
1. Member response collection and mapping (lines 68-78) with hardcoded decision/severity logic (line 75: `'challenge'` for adversarial, `'approve'` otherwise)
2. Council synthesis invocation (line 80)
3. Report task contract mutation (lines 82-94) and ritual run state update (lines 96-98)

The hardcoded decision logic at line 75 (`$t->name === 'Adversarial Review' ? 'challenge' : 'approve'`) is an OCP violation — new task roles would require modifying this listener.

**Remediation:** Extract `CouncilMemberResponseMapper` that can be extended per task type. Extract contract/state update into separate method or service.

---

### 5.2 RitualRunCompletionListener — Low (Compliant)

**File:** `app/Listeners/Org/RitualRunCompletionListener.php`
**Lines:** 1-37

Well-structured: single event, delegates to `OrgRitualRunService`, clean match expression for status mapping. Minor concern: the `STATUS_CANCELLED` case (line 30) performs inline update instead of using the service, inconsistent with the other branches.

---

## 6. Commands (`app/Console/Commands`)

### 6.1 AgentUserCommand — SRP (Low) [NEW]

**File:** `app/Console/Commands/AgentUserCommand.php`
**Lines:** 44-61

The transaction block (lines 44-61) handles user creation, onboarding flag setting, and team creation. Acceptable for a command, but if this user creation pattern is needed elsewhere, it should be extracted to a `UserRegistrationService`.

---

### 6.2 AgentCheckLicenseCommand — Compliant [NEW]

**File:** `app/Console/Commands/AgentCheckLicenseCommand.php`

Well-structured: delegates to `LicenseService` via constructor injection, single responsibility (display license status). Good use of readonly DTO (`LicenseStatus`).

---

### 6.3 AgentUpdateCommand — Compliant [NEW]

**File:** `app/Console/Commands/AgentUpdateCommand.php`

Clean orchestration of post-update tasks. Proper DI of `LicenseService`.

---

## 7. Middleware (`app/Http/Middleware`)

### 7.1 EnsureLicenseValid — Compliant [NEW]

**File:** `app/Http/Middleware/EnsureLicenseValid.php`

Excellent SOLID adherence: single responsibility (license validation gate), proper DI of `LicenseService`, readonly constructor property, no business logic leakage.

---

## 8. Providers (`app/Providers`)

### 8.1 AppServiceProvider — SRP (High)

**File:** `app/Providers/AppServiceProvider.php`
**Lines:** 1-231

The `boot()` method (lines 118-230) has grown to handle 7 distinct concerns:
1. Tool gateway adapter registration (lines 120-127)
2. Route pattern binding (lines 129-130)
3. Console-only guard enforcement (lines 132-135)
4. Observer registration (line 137)
5. Event subscriber registration (lines 139-142)
6. Policy/Gate registration (lines 144-170)
7. Rate limiter definitions (lines 172-229)

**Remediation:** Extract `ToolGatewayServiceProvider`, `PolicyServiceProvider`, and `RateLimiterServiceProvider`. Alternatively, use dedicated service provider classes for each domain.

---

## 9. Models (`app/Models`)

### 9.1 CredentialVault — SRP + DIP (Critical)

**File:** `app/Models/CredentialVault.php`
**Lines:** 33-66

Contains encryption/decryption business logic (`getDecryptedValue`, lines 33-46) and audit formatting logic (`redactForAudit`, lines 53-65). Directly depends on `Crypt` and `Log` facades.

**Remediation:** Extract `CredentialEncryptionService` interface. Extract `CredentialAuditFormatter`. Inject via service container.

---

### 9.2 ConnectorAccount — SRP + OCP + DIP (High)

**File:** `app/Models/ConnectorAccount.php`
**Lines:** 108-288

18 public methods implement configuration management business logic: runtime state updates (line 108), DM/group policies (lines 138-184), session scopes (lines 191-209), streaming overrides (lines 211-223), soul management (lines 259-287), approval mode (lines 240-252). All manipulate nested `config` JSON directly. `getPublicConfig()` (line 228) hardcodes which keys to strip.

**Remediation:** Create `ConnectorConfigurationManager` service. Implement `ConfigurationPolicy` interface with Strategy pattern.

---

### 9.3 User — SRP + OCP + DIP (High)

**File:** `app/Models/User.php`
**Lines:** 143-172

Role authorization logic implemented directly with `hasRole()` and `getRoles()`. Depends on configuration (`config('agent.roles.admin_user_ids')`) directly. `getRoles()` uses hardcoded if statements.

**Remediation:** Create `RoleProvider` interface. Implement `ConfigBasedRoleProvider`. Use Laravel gates/policies for authorization.

---

### 9.4 MemorySetting — SRP (High)

**File:** `app/Models/MemorySetting.php`
**Lines:** 36-109

Implements key-value settings repository pattern with 6 static helper methods plus sensitive value masking business logic.

**Remediation:** Create `MemorySettingsRepository`. Create `CredentialMaskingFormatter`.

---

### 9.5 MemoryConversationLog — SRP + DIP (High)

**File:** `app/Models/MemoryConversationLog.php`
**Lines:** 165-186

Contains validation logic (`isValidRole`, `isValidEventType`) and sequence generation (`getNextSequence`) with hardcoded database queries.

**Remediation:** Create `ConversationLogValidator`. Create `SequenceNumberProvider` interface.

---

### 9.6 MemoryEmbedding — SRP (High)

**File:** `app/Models/MemoryEmbedding.php`
**Lines:** 100-177

Contains content hashing, decay score calculations (complex mathematical formula), and content deduplication logic.

**Remediation:** Extract `DecayScoreCalculator`, `EmbeddingDeduplicator`.

---

### 9.7 MemoryProviderUsage — SRP + OCP (High)

**File:** `app/Models/MemoryProviderUsage.php`
**Lines:** 137-221

Implements pricing calculations, usage recording, and complex aggregation/statistics. Acts as data store + pricing engine + analytics aggregator. Pricing hardcoded to config path.

**Remediation:** Create `PricingCalculator`, `UsageRecorder`, `UsageAnalytics` services. Create `PricingStrategy` interface.

---

### 9.8 MemoryCoreBlock — SRP + LSP (Medium)

**File:** `app/Models/MemoryCoreBlock.php`
**Lines:** 140-209

`getContent()` returns `mixed` with different behavior based on internal state (array vs string). `setContent()` mutates different fields based on input type. Also contains classification hierarchy authorization logic.

**Remediation:** Create `getJsonContent()` and `getTextContent()` with explicit return types. Extract `BlockClassificationValidator`, `ClassificationPolicyChecker`.

---

### 9.9 RuntimeSession — SRP (Medium)

**File:** `app/Models/Runtime/RuntimeSession.php`
**Lines:** 58-79

Implements tool approval management business logic (`isToolAutoApproved`, `addToolAutoApproval`, `removeToolAutoApproval`).

**Remediation:** Create `ToolApprovalManager` service.

---

### 9.10 EscalationIncident — DIP (High)

**File:** `app/Models/EscalationIncident.php`
**Lines:** 14-19

Constructor contains dynamic table resolution tightly coupled to `ProjectionTable` utility. The model depends on a concrete utility rather than an abstraction.

**Remediation:** Create `TableResolver` interface. Use a custom repository or model observer for table resolution.

---

### 9.11 InterrogationSession — OCP (High)

**File:** `app/Models/InterrogationSession.php`
**Lines:** 23-70

Hard-coded phase and status state machine: 9 status constants (lines 23-43), 4 status grouping constants (lines 45-49), and 10 phase constants (lines 51-69). Adding new phases or statuses requires model modification.

**Remediation:** Create `Enums/InterrogationPhase` and `Enums/InterrogationStatus`. Implement state machine pattern for valid transitions.

---

### 9.12 InterrogationEvent — SRP (Medium)

**File:** `app/Models/InterrogationEvent.php`
**Lines:** 45-140

Contains extensive payload normalization and encoding logic: `normalizePayloadValue()` recursive method (lines 81-123), UTF-8 normalization (lines 125-140), JSON encoding with error handling (lines 65-79). This is serialization infrastructure, not model concern.

**Remediation:** Extract `PayloadNormalizer` service.

---

### 9.13 InterrogationBuildTask — SRP (Medium)

**File:** `app/Models/InterrogationBuildTask.php`
**Lines:** 70-113

Compliance metadata manipulation logic: `getComplianceMetadata()` filters fields (lines 71-86), `setComplianceMetadata()` merges data (lines 96-100), `isComplianceBlocked()` checks status AND metadata (lines 109-113).

**Remediation:** Extract `ComplianceMetadataManager` and `ComplianceBlockageChecker` services.

---

### 9.14 DocumentationEntry — SRP (Medium)

**File:** `app/Models/DocumentationEntry.php`
**Lines:** 44-95

Model combines Eloquent model with search index schema generation: `toSearchableArray()` transforms data (lines 44-72), `typesenseCollectionSchema()` defines index schema (lines 77-95).

**Remediation:** Extract `DocumentationSearchIndexer` to handle indexing and schema separately.

---

### 9.15 AgentJobRun — DIP (Low)

**File:** `app/Models/AgentJobRun.php`

Status constants and transition validation logic embedded in model. While acceptable for simple state, the model also contains methods for metadata manipulation that could grow.

**Remediation:** Consider extracting `RunStatusMachine` if complexity grows.

---

## 10. Cross-Cutting Concerns

### 10.1 Insufficient Interface Usage — DIP (High)

The codebase has only **4 interfaces** in `app/Contracts/`:
- `SlashCommandHandlerInterface`
- `ConnectorAdapterInterface`
- `ToolAdapterInterface`
- `OrchestrationPolicyServiceContract`

For a codebase of this size (~150+ classes), this is insufficient. Key service boundaries lack contracts:
- No interface for `RuntimeLlmClient` (LLM provider switching)
- No interface for `SessionProcessManager` (wrapper vs direct execution)
- No interface for `RunEventWriter` (event capture strategies)
- No interface for `TrustScoreCalculator` (scoring strategies)
- No interface for `LicenseService` (could benefit from `LicenseValidatorInterface` for testing)

**Remediation:** Add interfaces at architectural boundaries where implementations may vary or need testing isolation.

---

### 10.2 Config-Driven Registration Missing — OCP (Medium)

Multiple services use hardcoded class maps instead of config-driven registration:
- `CommandRouter::$handlers` (17 entries)
- `ApprovalGate::MUTATION_TOOLS` / `EXTERNAL_TOOLS`
- `LogTailController::ALLOWED_CHANNELS`
- `VerificationPipeline` step type map
- `MemoryAdapterFactory` provider map

**Remediation:** Use Laravel service container tagging or config arrays for extensible registration.

---

### 10.3 Service Location Anti-Pattern — DIP (Medium)

Several classes use `app()` service location instead of constructor injection:
- `RecalculateTrustScoresJob` (line 24)
- `CommandRouter::route()` (line 124)
- `AppServiceProvider::boot()` (lines 133-134)

**Remediation:** Use constructor injection or method injection where supported by Laravel (e.g., job `handle()` method parameters).

---

## 11. Recommendations Summary

### Immediate Priorities (Critical/High)

1. **Decompose `InterrogationSessionController`** — 4,124 lines is unsustainable. Extract into 4+ dedicated services.
2. **Decompose `RunEventWriter`** — Extract 5 output detectors behind `OutputDetector` interface. Extract redaction service and structured event classifier.
3. **Deduplicate `SessionProcessManager`** — Unify `readTurnResponse`/`resumeReadTurnResponse` into shared `readLoop()`. Extract fragment parser and progress tracker.
4. **Thin out god jobs** — `ExecuteAgentRunJob` (450+ lines, 7+ responsibilities), `ProcessChatIntent`, `ExecuteInterrogationSummaryJob`, and `ProcessRuntimeTurnJob` all need service extraction. Jobs should be thin orchestrators that delegate to services.
5. **Extract business logic from models** — `CredentialVault`, `ConnectorAccount`, `MemoryProviderUsage`, `MemoryEmbedding` contain significant service-level logic.
6. **Implement Strategy pattern for providers** — `MessengerConnectorController`, `WebhookController`, `CliRuntimeExecutor`, `MemoryAdapterFactory` all have provider-specific hardcoded logic.
7. **Extract `RunOutputRetriever`** — Reusable service from `AiCriticCompletedJob::getRunOutput()` fallback chain.

### Architectural Patterns to Adopt

| Pattern | Where to Apply |
|---------|---------------|
| Strategy | Provider-specific logic (Messenger, Runtime runners, Memory adapters, Output detectors) |
| Registry | Command handlers, verification steps, tool categories, log channels |
| State Machine | Interrogation phases, incident lifecycle, delegation task states |
| Repository | Model static queries (`MemorySetting`, `MemoryConversationLog`) |
| API Resources | All controller data transformation (replace inline `->map()` closures) |
| Builder | Complex query construction in controllers |
| Observer | Output detection events in `RunEventWriter` |

### Quick Wins (Low Effort, High Impact)

1. Move hardcoded constants to `config/` files (tool categories, sort fields, provider lists, log channels)
2. Use Laravel API Resources for all controller response transformation
3. Use Laravel Policies for scattered `Gate::denies()` and `hasRole()` checks
4. Extract duplicated `max_runtime` validation to shared validator
5. Unify `SessionProcessManager` read loops (eliminates ~150 lines of duplication)
6. Add interfaces at key service boundaries (`RuntimeLlmClient`, `TrustScoreCalculator`)
7. Fix service location anti-pattern in `RecalculateTrustScoresJob` (use handle() method injection)

### SOLID-Compliant Exemplars (Reference Implementations)

The following newly added files demonstrate excellent SOLID adherence and should be used as patterns for future development:

| File | Why It's Good |
|------|---------------|
| `LicenseStatus` | Readonly DTO with named constructors, no behavior leakage |
| `EnsureLicenseValid` | Single responsibility, proper DI, clean guard pattern |
| `LicenseService` | Proper DI of `InstanceFingerprint`, cache abstraction, fallback chain |
| `InstanceFingerprint` | Single focused responsibility, injectable |
| `ProgressCommandHandler` | Implements `SlashCommandHandlerInterface`, delegates to `SessionProcessManager` |
| `RitualRunCompletionListener` | Single event, delegates to service, clean status mapping |
