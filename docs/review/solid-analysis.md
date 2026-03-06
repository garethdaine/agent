# SOLID Principles Analysis Report

**Project:** Agent Scheduler (Laravel 12 / PHP 8.3)
**Date:** 2026-03-06
**Scope:** `app/Http/Controllers`, `app/Services`, `app/Support`, `app/Models`
**Graph:** SOLID Analysis | Task ID: 37 | Attempt: 2

---

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | 2 |
| High | 25 |
| Medium | 25 |
| Low | 8 |
| **Total** | **60** |

| Principle | Violations |
|-----------|-----------|
| Single Responsibility (SRP) | 30 |
| Open/Closed (OCP) | 13 |
| Liskov Substitution (LSP) | 2 |
| Interface Segregation (ISP) | 2 |
| Dependency Inversion (DIP) | 13 |

**Top structural risks:**
1. `InterrogationSessionController` (4124 lines, 90 methods) — critical SRP violation
2. `SessionProcessManager` (727 lines, 7 responsibilities) — needs decomposition
3. `ChatIntentParser` (500 lines, 8 responsibilities) — needs decomposition
4. Several models embed business logic (pricing, encryption, state machines) that belongs in services

---

## 1. Controllers (`app/Http/Controllers`)

### 1.1 InterrogationSessionController — SRP (Critical)

**File:** `app/Http/Controllers/Api/V1/InterrogationSessionController.php`
**Lines:** 1–4124 (entire file)

The controller contains 90 methods (44 public, 46 private) spanning session lifecycle, workflow orchestration, build task management, summary/plan normalization, compliance extraction, event writing, and data transformation. This is the single largest SRP violation in the codebase.

**Additionally violates OCP** at lines 241–700: multiple phase/status transition methods hardcode state machine logic with nested if-else chains. New phase transitions require controller modification.

**Remediation:**
- Extract `InterrogationSessionService` for lifecycle management
- Extract `InterrogationBuildService` for build task orchestration
- Extract `InterrogationNormalizationService` for payload normalization
- Use `InterrogationSessionResource` (Laravel API Resource) for transformation
- Implement `PhaseTransitionRegistry` or state machine pattern for transitions

---

### 1.2 OfficeStateController — SRP (High)

**File:** `app/Http/Controllers/Api/V1/OfficeStateController.php`
**Lines:** 1–349

Single `__invoke` method aggregates 8 different state components (agents, system, delegation, messenger, memory, jobs, tools, escalations) via 8 private methods with heavy query/aggregation logic.

**Remediation:** Create `OfficeStateBuilder` service with domain-specific sub-builders implementing a `StateComponentBuilder` interface.

---

### 1.3 MessengerConnectorController — SRP + OCP (High)

**File:** `app/Http/Controllers/Api/V1/MessengerConnectorController.php`
**Lines:** 98–693

`store` and `update` methods (150+ lines each) handle credential validation, normalization, testing, config building, and model creation. Four private methods (`testSlackCredentials`, `testTelegramCredentials`, `testDiscordCredentials`, `testWhatsAppCredentials`) duplicate HTTP request logic. Adding new providers requires controller modification.

**Remediation:** Strategy pattern — create `ConnectorProviderRegistry` with provider-specific credential testers implementing a common `CredentialTester` interface.

---

### 1.4 Messenger/WebhookController — OCP (High)

**File:** `app/Http/Controllers/Api/V1/Messenger/WebhookController.php`
**Lines:** 31–189

Separate `handleSlack`, `handleTelegram`, `handleDiscord`, `handleWhatsApp` methods duplicate logic for bot detection, logging, and job dispatching.

**Remediation:** Implement `MessengerProvider` interface with a single `handle(Request): Response` dispatch method.

---

### 1.5 AgentJobController — SRP (High)

**File:** `app/Http/Controllers/Api/V1/AgentJobController.php`
**Lines:** 27–410

Mixes query building (filtering, sorting, pagination) with job creation/update logic and markdown storage handling.

**Remediation:** Extract `JobQueryBuilder` service and `TaskMarkdownStorageService`.

---

### 1.6 RepoAnalysisSessionController — SRP (High)

**File:** `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`
**Lines:** 1–1118

21 public methods mixing session CRUD, analysis workflow execution, event management, and inline authorization logic (`Gate::denies()`).

**Remediation:** Extract `RepoAnalysisSessionService`. Use Laravel Policy classes for authorization.

---

### 1.7 DelegationGraphController — SRP (High)

**File:** `app/Http/Controllers/Api/V1/DelegationGraphController.php`
**Lines:** 1–562

Handles graph validation, state transitions, and inline transformation logic.

**Remediation:** Create `DelegationGraphOrchestrator` service. Use `DelegationGraphResource` for transformation.

---

### 1.8 OperatorPageController — SRP (High)

**File:** `app/Http/Controllers/Agent/OperatorPageController.php`
**Lines:** 24–135

`deployments` method orchestrates querying multiple projection tables, governance snapshots, and budget utilization calculations inline.

**Remediation:** Create `DeploymentsDataProvider` service.

---

### 1.9 ConfigurationController — DIP (High)

**File:** `app/Http/Controllers/Api/V1/ConfigurationController.php`
**Lines:** 98–138

`writeEnvValues` directly manipulates `.env` file with `file_get_contents`/`preg_replace`/`file_put_contents`. `configKeyToEnv` uses hardcoded match statement.

**Remediation:** Create `EnvConfigWriter` interface. Move mappings to configuration.

---

### 1.10 MessengerHealthController — SRP (Medium)

**File:** `app/Http/Controllers/Messenger/MessengerHealthController.php`
**Lines:** 63–115

Mixes health check orchestration with dependency-specific checking (database, Redis, queue).

**Remediation:** Create `HealthChecker` interface with dedicated implementations; use `HealthCheckRegistry`.

---

### 1.11 RepoAnalysisSessionController — SRP (Medium)

**File:** `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php`
**Lines:** 78–156

Authorization checks via `Gate::denies()` scattered throughout action methods.

**Remediation:** Implement `RepoAnalysisSessionPolicy` and use `$this->authorize()`.

---

### 1.12 AgentJobController — OCP (Medium)

**File:** `app/Http/Controllers/Api/V1/AgentJobController.php`
**Lines:** 77–87

Hardcoded sort field validation list requires controller modification for new fields.

**Remediation:** Move sort field configuration to config file or `SortFieldRegistry`.

---

### 1.13 DelegateeProfileController — SRP (Medium)

**File:** `app/Http/Controllers/Api/V1/DelegateeProfileController.php`
**Lines:** 33–98

`index` method contains repeated filtering patterns for query building.

**Remediation:** Extract `DelegateeProfileQueryBuilder`.

---

### 1.14 DelegationTaskController — SRP (Medium)

**File:** `app/Http/Controllers/Api/V1/DelegationTaskController.php`
**Lines:** 18–78

`index` method mixes query building, status filtering, sorting, and pagination.

**Remediation:** Extract `DelegationTaskQueryBuilder`.

---

### 1.15 WebhookController — OCP (Medium)

**File:** `app/Http/Controllers/Api/V1/Messenger/WebhookController.php`
**Lines:** 15–29

Discord type and response constants hardcoded in controller.

**Remediation:** Move to provider implementation classes.

---

## 2. Services (`app/Services`)

### 2.1 SessionProcessManager — SRP (High)

**File:** `app/Services/Runtime/SessionProcessManager.php`
**Lines:** 17–727

Handles 7 distinct responsibilities: runner session ID caching, wrapper process lifecycle, message sending, output reading/parsing, JSON fragment extraction, live progress persistence, and turn yielding/resumption.

**Remediation:** Split into `RunnerSessionIdManager`, `WrapperProcessManager`, `ProcessMessageHandler`, `StreamFragmentParser`, `LiveProgressTracker`, `TurnYieldManager`.

---

### 2.2 MessengerRuntimeOrchestrator — SRP (High)

**File:** `app/Services/Runtime/MessengerRuntimeOrchestrator.php`
**Lines:** 14–314

Handles 8 concerns: turn execution, CLI wrapper execution, policy retrieval, system prompt building, tool extraction, text block extraction, tool result formatting, session title derivation.

**Remediation:** Extract `CliWrapperExecutor`, `RuntimeContextBuilder`, `LlmResponseParser`, `TitleDerivationService`.

---

### 2.3 CliRuntimeExecutor — OCP + SRP (High)

**File:** `app/Services/Runtime/CliRuntimeExecutor.php`
**Lines:** 149–291

Long if-else chain for runner type-specific command building (`claude` vs `codex`). Also mixes process execution, output parsing, session ID extraction, and stream event unwrapping.

**Remediation:** Introduce `RuntimeCommandBuilder` interface with per-runner implementations. Extract `ProcessRunner`, `OutputParser`, `SessionIdExtractor`.

---

### 2.4 ChatIntentParser — SRP (High)

**File:** `app/Services/Messenger/ChatIntentParser.php`
**Lines:** 15–500

Handles 8 concerns: pattern matching, AI-based parsing, attachment context building, MIME checking, session context building, AI response parsing, JSON extraction, schema generation.

**Remediation:** Extract `PatternMatcher`, `AttachmentContextBuilder`, `AiIntentParser`, `ResponseParser`.

---

### 2.5 ToolGateway — SRP (Medium)

**File:** `app/Services/Runtime/ToolGateway.php`
**Lines:** 12–307

Mixes adapter registration, tool schema generation, call routing, approval gate checking, call recording, and qualified name resolution.

**Remediation:** Extract `ToolSchemaBuilder`, `ToolCallRecorder`, `QualifiedNameResolver`.

---

### 2.6 RuntimeLlmClient — DIP (Medium)

**File:** `app/Services/Runtime/RuntimeLlmClient.php`
**Lines:** 30–65

Tightly coupled to Anthropic API with hardcoded endpoint URL and config paths. No abstraction for LLM provider switching.

**Remediation:** Create `LlmProviderInterface` and `LlmProviderFactory`.

---

### 2.7 ApprovalGate — DIP (Medium)

**File:** `app/Services/Runtime/ApprovalGate.php`
**Lines:** 24–57

Hardcoded `MUTATION_TOOLS` and `EXTERNAL_TOOLS` arrays. Cannot extend tool categorization without code changes.

**Remediation:** Move to `config/runtime.php` or create `ToolCategoryRegistry` interface.

---

### 2.8 ChatActionExecutor — DIP (Medium)

**File:** `app/Services/Messenger/ChatActionExecutor.php`
**Lines:** 36–262

Hardcoded handler class string array. No interface for handler registration.

**Remediation:** Implement `HandlerRegistry` interface with dependency-based registration.

---

### 2.9 CommandRouter — DIP (Medium)

**File:** `app/Services/Messenger/CommandRouter.php`
**Lines:** 52–70

Hardcoded handler class references instead of injectable registry.

**Remediation:** Create `SlashCommandRegistry` interface and use DI.

---

### 2.10 SlashCommandRegistrar — SRP (Medium)

**File:** `app/Services/Messenger/SlashCommandRegistrar.php`
**Lines:** 19–306

Mixes command schema definition, Discord API registration, version tracking, metadata management, and error response parsing.

**Remediation:** Extract `DiscordCommandSchema`, `DiscordApiClient`, `VersionManager`.

---

### 2.11 RuntimeSessionManager — SRP (Medium)

**File:** `app/Services/Runtime/RuntimeSessionManager.php`
**Lines:** 19–261

Mixes session lifecycle, memory context file writing, mode changes, session queries, and concurrent limit enforcement.

**Remediation:** Extract `MemoryContextWriter`, `ConcurrentSessionLimiter`.

---

### 2.12 IngestionService — SRP (Medium)

**File:** `app/Services/Telemetry/IngestionService.php`
**Lines:** 11–240

Handles 8 concerns: envelope validation, time normalization, payload normalization, sequence violation detection, telemetry estimation, database insertion, duplicate detection, terminal event projection.

**Remediation:** Extract `EventValidator`, `EventNormalizer`, `SequenceValidator`, `TelemetryEstimator`.

---

### 2.13 IncidentLifecycleService — OCP (Medium)

**File:** `app/Services/Escalation/IncidentLifecycleService.php`
**Lines:** 145–153

State transitions hardcoded in match statement.

**Remediation:** Implement `StateTransitionValidator` interface with pluggable state machines.

---

### 2.14 ConfigSchemaService — LSP (Medium)

**File:** `app/Services/Agent/ConfigSchemaService.php`
**Lines:** 118–140

Match statement for validation rules doesn't guarantee correct rules for all field types.

**Remediation:** Make field type validation extensible via `FieldValidator` interface.

---

### 2.15 AbstractToolAdapter — ISP (Low)

**File:** `app/Services/Runtime/Adapters/AbstractToolAdapter.php`
**Lines:** 8–43

All adapters inherit `authorize()` and `getRequiredCapability()` even if they don't need mode-based authorization.

**Remediation:** Create separate `AuthorizedToolAdapter` interface.

---

### 2.16 ProjectionBuildManager — ISP (Low)

**File:** `app/Services/Telemetry/ProjectionBuildManager.php`
**Lines:** 19–21

Complex result objects force clients to understand multiple result types.

**Remediation:** Create generic `BuildOperationResult` interface.

---

## 3. Support (`app/Support`)

### 3.1 DelegationReconciler — SRP (High)

**File:** `app/Support/Delegation/DelegationReconciler.php`
**Lines:** 24–275

Has 4 distinct responsibilities: expired approval handling, blocked task retry with backoff, stuck graph resolution, and force-kill after cancellation timeout.

**Remediation:** Extract `ExpiredApprovalHandler`, `BlockedTaskRetryService`, `StuckGraphResolver`, `GracefulCancellationEnforcer`. Keep reconciler as thin orchestrator.

---

### 3.2 TrustScoreCalculator — SRP (High)

**File:** `app/Support/Delegation/TrustScoreCalculator.php`
**Lines:** 11–217

Mixes trust score calculation, metrics aggregation with STAR component extraction, database querying, and caching logic.

**Remediation:** Extract `StarMetricsAggregator`, `AgentMetricsQuery`. Keep calculator focused on score formula and trust level mapping.

---

### 3.3 AttemptSpawner — SRP + OCP (High)

**File:** `app/Support/Delegation/AttemptSpawner.php`
**Lines:** 28–179

Mixes attempt creation, markdown file generation, template validation, and job/run dispatch. `ensureAutonomousTemplate()` has runner-type if-else chain.

**Remediation:** Extract `CommandTemplateNormalizer` interface per runner type (Strategy pattern). Extract `TaskMarkdownGenerator`, `AgentJobFactory`.

---

### 3.4 HybridRetriever — SRP (Medium)

**File:** `app/Support/Memory/HybridRetriever.php`
**Lines:** 30–327

Handles 7 concerns: semantic search, keyword search, graph traversal, RRF fusion, pgvector detection, query normalization, SQL placeholder generation.

**Remediation:** Extract `SemanticSearchStrategy`, `KeywordSearchStrategy`, `GraphSearchStrategy`, `ReciprocalRankFusion`. Create `SearchStrategy` interface.

---

### 3.5 CoreMemoryManager — SRP (Medium)

**File:** `app/Support/Memory/CoreMemoryManager.php`
**Lines:** 30–251

Mixes CRUD operations, classification validation, access control enforcement, and audit logging.

**Remediation:** Extract `BlockClassificationValidator`, `BlockAccessControl`, `MemoryBlockFactory`.

---

### 3.6 VerificationPipeline — OCP (Medium)

**File:** `app/Support/Delegation/VerificationPipeline.php`
**Lines:** 126–142

Hardcoded match expression routes step types to concrete step classes.

**Remediation:** Create `VerificationStepInterface` and `VerificationStepRegistry`.

---

### 3.7 MemoryAdapterFactory — OCP (Medium)

**File:** `app/Support/Memory/MemoryAdapterFactory.php`
**Lines:** 258–271

Hardcoded match expression for provider instantiation (`openai`, `anthropic`).

**Remediation:** Use configuration-driven adapter registry.

---

### 3.8 OrchestrationPolicyService — DIP (Medium)

**File:** `app/Support/Compliance/OrchestrationPolicyService.php`
**Lines:** 146–163

`resolveCategory()` uses `instanceof` checks against `AgentJobRun` vs `InterrogationBuildTask`.

**Remediation:** Create `Categorizable` interface. Have both classes implement it. Remove instanceof checks.

---

### 3.9 AdversarialReviewerService — SRP (Medium)

**File:** `app/Support/Interrogation/AdversarialReviewerService.php`
**Lines:** 22–189

Mixes test mode management, subprocess execution, and two prompt builders (summary review, plan review).

**Remediation:** Extract `SummaryReviewPromptBuilder`, `PlanReviewPromptBuilder`, `ReviewerProcessExecutor`.

---

### 3.10 DelegationGraphBuilder — SRP (Medium)

**File:** `app/Support/Delegation/DelegationGraphBuilder.php`
**Lines:** 30–317

Mixes input normalization, task limit validation, graph analysis (adjacency/in-degrees), cycle detection (Kahn's algorithm), sequence order computation (BFS), and database record creation.

**Remediation:** Extract `DelegationGraphValidator`, `DelegationGraphSequenceCalculator`.

---

### 3.11 NlScheduleParserService — SRP (Low-Medium)

**File:** `app/Support/NlSchedule/NlScheduleParserService.php`
**Lines:** 34–242

Mixes input validation, idempotency checking, rule-based parsing, confidence evaluation, alternative generation, response building, and redacted logging.

**Remediation:** Extract `AlternativeSuggestionGenerator`, `ParseResponseBuilder`.

---

### 3.12 ContractValidator / ContractEnforcer — SRP (Low-Medium)

**Files:** `app/Support/Delegation/ContractValidator.php` (40–180), `app/Support/Delegation/ContractEnforcer.php` (34–181)

Duplicated max_runtime validation logic between the two classes.

**Remediation:** Extract shared `RuntimeConstraintValidator`.

---

### 3.13 ExportService — SRP (Low-Medium)

**File:** `app/Support/Interrogation/ExportService.php`
**Lines:** 7–133

Mixes filesystem operations, markdown content generation, and session slug generation.

**Remediation:** Extract `SessionFilePathResolver`, `MarkdownContentBuilder`.

---

### 3.14 State Transition Services — DIP (Low)

**Files:** `app/Support/Interrogation/SessionStateTransitionService.php` (25–28), `app/Support/Delegation/TaskStateTransitionService.php` (34–37)

Directly use Eloquent models in query building instead of repository abstractions.

**Remediation:** Create `StateTransitionRepository` interface.

---

### 3.15 ConnectorManager / TaskManagementProviderManager — DIP (Low)

**Files:** `app/Support/Messenger/ConnectorManager.php` (30–41), `app/Support/TaskProviders/TaskManagementProviderManager.php` (11–17)

Hardcoded provider names and direct `app()` resolution.

**Remediation:** Use configuration files to define provider mappings.

---

### 3.16 ComplexityClassifier — SRP (Low)

**File:** `app/Support/Compliance/ComplexityClassifier.php`
**Lines:** 44–53

Mixes override logic with heuristics evaluation.

**Remediation:** Extract `ComplexityOverrideResolver`.

---

## 4. Models (`app/Models`)

### 4.1 CredentialVault — SRP + DIP (Critical)

**File:** `app/Models/CredentialVault.php`
**Lines:** 33–66

Contains encryption/decryption business logic (`getDecryptedValue`, lines 33–46) and audit formatting logic (`redactForAudit`, lines 53–65). Directly depends on `Crypt` and `Log` facades.

**Remediation:** Extract `CredentialEncryptionService` interface. Extract `CredentialAuditFormatter`. Inject via service container.

---

### 4.2 ConnectorAccount — SRP + OCP + DIP (High)

**File:** `app/Models/ConnectorAccount.php`
**Lines:** 108–287

18 public methods implement configuration management business logic: runtime state updates, DM/group policies, session scopes, streaming overrides, soul management — all manipulating nested `config` JSON. `getPublicConfig()` hardcodes which keys to strip.

**Remediation:** Create `ConnectorConfigurationManager` service. Implement `ConfigurationPolicy` interface with Strategy pattern.

---

### 4.3 User — SRP + OCP + DIP (High)

**File:** `app/Models/User.php`
**Lines:** 143–172

Role authorization logic implemented directly with `hasRole()` and `getRoles()`. Depends on configuration (`config('agent.roles.admin_user_ids')`) directly. `getRoles()` uses hardcoded if statements.

**Remediation:** Create `RoleProvider` interface. Implement `ConfigBasedRoleProvider`. Use Laravel gates/policies for authorization.

---

### 4.4 MemorySetting — SRP (High)

**File:** `app/Models/MemorySetting.php`
**Lines:** 36–109

Implements key-value settings repository pattern with 6 static helper methods plus sensitive value masking business logic.

**Remediation:** Create `MemorySettingsRepository`. Create `CredentialMaskingFormatter`.

---

### 4.5 MemoryConversationLog — SRP + DIP (High)

**File:** `app/Models/MemoryConversationLog.php`
**Lines:** 165–186

Contains validation logic (`isValidRole`, `isValidEventType`) and sequence generation (`getNextSequence`) with hardcoded database queries.

**Remediation:** Create `ConversationLogValidator`. Create `SequenceNumberProvider` interface.

---

### 4.6 MemoryEmbedding — SRP (High)

**File:** `app/Models/MemoryEmbedding.php`
**Lines:** 100–177

Contains content hashing, decay score calculations (complex mathematical formula), and content deduplication logic.

**Remediation:** Extract `DecayScoreCalculator`, `EmbeddingDeduplicator`.

---

### 4.7 MemoryProviderUsage — SRP + OCP (High)

**File:** `app/Models/MemoryProviderUsage.php`
**Lines:** 137–221

Implements pricing calculations, usage recording, and complex aggregation/statistics. Acts as data store + pricing engine + analytics aggregator. Pricing hardcoded to config path.

**Remediation:** Create `PricingCalculator`, `UsageRecorder`, `UsageAnalytics` services. Create `PricingStrategy` interface.

---

### 4.8 MemoryCoreBlock — SRP + LSP (Medium)

**File:** `app/Models/MemoryCoreBlock.php`
**Lines:** 140–209

`getContent()` returns `mixed` with different behavior based on internal state (array vs string). `setContent()` mutates different fields based on input type. Also contains classification hierarchy authorization logic.

**Remediation:** Create `getJsonContent()` and `getTextContent()` with explicit return types. Extract `BlockClassificationValidator`, `ClassificationPolicyChecker`.

---

### 4.9 RuntimeSession — SRP (Medium)

**File:** `app/Models/Runtime/RuntimeSession.php`
**Lines:** 58–79

Implements tool approval management business logic (`isToolAutoApproved`, `addToolAutoApproval`, `removeToolAutoApproval`).

**Remediation:** Create `ToolApprovalManager` service.

---

## 5. Recommendations Summary

### Immediate Priorities (Critical/High)

1. **Decompose `InterrogationSessionController`** — 4124 lines is unsustainable. Extract into 4+ dedicated services.
2. **Decompose `SessionProcessManager`** — 7 responsibilities need splitting into focused classes.
3. **Extract business logic from models** — `CredentialVault`, `ConnectorAccount`, `MemoryProviderUsage`, and `MemoryEmbedding` contain significant service-level logic.
4. **Implement Strategy pattern for providers** — `MessengerConnectorController`, `WebhookController`, `CliRuntimeExecutor`, `MemoryAdapterFactory` all have provider-specific hardcoded logic.

### Architectural Patterns to Adopt

| Pattern | Where to Apply |
|---------|---------------|
| Strategy | Provider-specific logic (Messenger, Runtime runners, Memory adapters) |
| Registry | Command handlers, verification steps, tool categories |
| State Machine | Interrogation phases, incident lifecycle, delegation task states |
| Repository | Model static queries (`MemorySetting`, `MemoryConversationLog`) |
| API Resources | All controller data transformation (replace inline `->map()` closures) |
| Builder | Complex query construction in controllers |

### Quick Wins (Low Effort, High Impact)

1. Move hardcoded constants to `config/` files (tool categories, sort fields, provider lists)
2. Use Laravel API Resources for all controller response transformation
3. Use Laravel Policies for scattered `Gate::denies()` checks
4. Extract duplicated `max_runtime` validation to shared validator
