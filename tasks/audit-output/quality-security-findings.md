# Phase 2-3 — Code Quality and Security Audit Findings

**Session ID:** 6 | **Task Sequence:** 4
**Generated:** 2026-03-08
**Scope:** 77 controllers, 55 services, 29 actions, 94 jobs, 78 models, 910 PHP files

---

## Issue Register

| # | Category | File:Line | Issue | Smell / OWASP | Severity | Effort | Priority |
|---|----------|-----------|-------|---------------|----------|--------|----------|
| 1 | Security | 57 models (see §6.1) | `$guarded = []` across all models — mass-assignment vulnerability | OWASP A01:2025 | Critical | Medium | **P0** |
| 2 | Security | `.github/workflows/` | No CI quality gate workflows — no automated tests, linting, or security scans | OWASP A03:2025 | Critical | High | **P0** |
| 3 | AI Security | `DelegateeProfileController.php:324` | `soul_json` (system prompts) exposed in API response | LLM07:2025 System Prompt Leakage | Critical | Low | **P0** |
| 4 | AI Security | `MemoryFormationPipeline.php:196-204` | Memory poisoning — unsanitized entities stored in Neo4j | Agentic: Memory Poisoning | Critical | High | **P0** |
| 5 | AI Security | `AttemptSpawner.php:125-131` | `--dangerously-skip-permissions` auto-injected in delegation | Agentic: Permission Attenuation | High | Medium | **P0** |
| 6 | Code Quality | `InterrogationSessionController.php` | God Class — 4,021 lines, 37 public + 53 private methods | Large Class | High | High | **P1** |
| 7 | Code Quality | `RunEventWriter.php` | God Class — 1,169 lines, 4 public + 42 private methods, hidden complexity | Large Class | High | High | **P1** |
| 8 | Code Quality | `RepoAnalysisSessionController.php` | God Class — 1,118 lines, 21 public + 10 private methods | Large Class | High | High | **P1** |
| 9 | AI Security | `GeneralTaskHandler.php:178,184` | Unescaped soul data concatenated into system prompts | LLM01:2025 Prompt Injection | High | Low | **P1** |
| 10 | Security | `.github/workflows/docs-deploy-sync.yml:25,28` | `actions/checkout@v4` and `shivammathur/setup-php@v2` not SHA-pinned | OWASP A03:2025 Supply Chain | Medium | Low | **P1** |
| 11 | Security | `routes/api.php:79` | N8n webhook endpoint missing auth middleware/signature verification | OWASP A07:2025 | Medium | Low | **P1** |
| 12 | Code Quality | `AgentRunController.php:249-436` | `stop()` method is 188 lines — critical Long Method | Long Method | High | Medium | **P1** |
| 13 | Code Quality | `InterrogationSessionController.php:1507-1656` | `startBuild()` method is 150 lines | Long Method | High | Medium | **P1** |
| 14 | Code Quality | `InterrogationTaskProviderSyncService.php:24-203` | `syncBuildTasks()` method is 180 lines | Long Method | High | Medium | **P1** |
| 15 | Code Quality | 77 controllers | Business logic in controllers — direct DB queries, `::where`, `->save()`, `->create()`, `->update()`, `->delete()` | Missing Action Pattern / Feature Envy | Medium | High | **P1** |
| 16 | Compliance | 504/910 PHP files | Missing `declare(strict_types=1)` — 55.4% non-compliant | Primitive Obsession | Medium | Low | **P1** |
| 17 | AI Security | `HumanApprovalStep.php:35-72` | No pre-execution confirmation gate — tasks execute before approval | Agentic: Tool Misuse | Medium | Medium | **P1** |
| 18 | AI Security | `RunEventWriter.php:61-73` | AI responses logged without PII redaction | LLM02:2025 Sensitive Info Disclosure | Medium | Medium | **P1** |
| 19 | AI Security | `DelegateeProfile.php:56-65` | No validation on soul content — users can embed secrets | LLM06:2025 | Medium | Low | **P1** |
| 20 | AI Security | `AiCriticStep.php:154` | `str_replace` prompt template injection in verification | LLM01:2025 Prompt Injection | Medium | Low | **P1** |
| 21 | Security | `MessengerConnectorController.php:103,208,359` | `$request->all()` passed to Validator without `->only()` | OWASP A01:2025 | Low | Low | **P2** |
| 22 | Security | `WhatsAppAdapter.php:105`, `DiscordAdapter.php:117` | `$request->all()` in webhook adapters | OWASP A01:2025 | Low | Low | **P2** |
| 23 | Security | 10 Vue files | `v-html` usage without sanitization | OWASP A03:2021 XSS | Low | Low | **P2** |
| 24 | Code Quality | 87 files > 300 lines | Files exceeding 300-line threshold | Large Class | Medium | High | **P2** |
| 25 | Code Quality | 5 duplicate validation patterns | Repeated validation rules across 7+ Form Requests | Duplicate Code | Low | Low | **P2** |
| 26 | Code Quality | `ExecuteInterrogationRoundJob.php` | 1,427 lines — mixed orchestration + business logic concerns | Large Class / SRP | Medium | High | **P2** |
| 27 | Code Quality | `CodexAdapter.php` / `ClaudeAdapter.php` | Massive schema/parsing duplication (15+ methods each with embedded JSON schemas) | Duplicate Code / Large Class | Medium | High | **P2** |
| 28 | Code Quality | `OrgCostGovernanceService.php:168` | 1 TODO comment — configurable threshold evaluation | Dead Code | Low | Low | **P2** |
| 29 | Security | `DB::raw()` in 9 locations | Raw SQL usage in aggregation queries | OWASP A03:2021 SQL Injection | Low | Low | **P2** |

---

## §1. Code Quality — God Class Detection (Large Class)

### SEVERE (Immediate refactoring needed)

| Rank | File | Lines | Public | Private | Responsibilities |
|------|------|-------|--------|---------|-----------------|
| 1 | `app/Http/Controllers/Api/V1/InterrogationSessionController.php` | 4,021 | 37 | 53 | Session CRUD, state transitions, event coordination, plan revisions, build task management, approval flows, export, cleanup, restore |
| 2 | `app/Support/Agent/RunEventWriter.php` | 1,169 | 4 | 42 | Event capture, output chunking, rate-limit detection, approval/permission/clarification pattern matching, redaction, MCP error parsing, failure backoff, memory integration |
| 3 | `app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php` | 1,118 | 21 | 10 | Session CRUD, snapshot generation, task planning/execution/retry, coverage validation, report generation, pause/resume/retry workflows |

### SIGNIFICANT (Architectural decomposition needed)

| Rank | File | Lines | Public | Private | Responsibilities |
|------|------|-------|--------|---------|-----------------|
| 4 | `app/Jobs/ExecuteInterrogationRoundJob.php` | 1,427 | 3 | 25 | Conversation reconstruction, semantic deduplication (3 thresholds), question bank generation, state transitions, duplicate recovery |
| 5 | `app/Support/Interrogation/Adapters/CodexAdapter.php` | 1,194 | 15 | 30 | 15 build command methods + 8 parse methods; embedded JSON schemas; duplicated with ClaudeAdapter |
| 6 | `app/Support/Interrogation/Adapters/ClaudeAdapter.php` | 1,161 | 18 | 22 | 18 build command methods + 8 parse methods + reviewer methods; embedded JSON schemas |

### MODERATE (Extract responsibilities)

| Rank | File | Lines | Public | Private | Responsibilities |
|------|------|-------|--------|---------|-----------------|
| 7 | `app/Support/RepoAnalysis/ReportComposer.php` | 1,196 | 2 | 27 | Repository profile building, AI section extraction, markdown composition, payload transformation, statistics collection |
| 8 | `app/Console/Commands/AgentInstallCommand.php` | 1,171 | 1 | 27 | Installation wizard: env setup, PHP verification, credential validation, migrations, health checks |
| 9 | `app/Support/Messenger/Adapters/DiscordAdapter.php` | 1,168 | 13 | 25 | Signature verification (Ed25519), message parsing/sending/editing, threading, reactions, streaming |
| 10 | `app/Jobs/ExecuteInterrogationBuildJob.php` | 1,140 | 3 | 22 | Task sequencing, backup management, finalization, policy orchestration, state transitions |

**Total files > 300 lines:** 87 (see initial scan output)

---

## §2. Code Quality — Long Method Detection

### Critical Methods (>100 lines)

| File | Method | Lines | Line Range |
|------|--------|-------|------------|
| `AgentRunController.php` | `stop()` | 188 | 249-436 |
| `InterrogationTaskProviderSyncService.php` | `syncBuildTasks()` | 180 | 24-203 |
| `InterrogationSessionController.php` | `startBuild()` | 150 | 1507-1656 |
| `ReportComposer.php` | `buildRepositoryProfile()` | 136 | 131-266 |
| `InterrogationSessionController.php` | `reconcileOpenQuestionsWithAnsweredEvents()` | 134 | 3815-3948 |
| `AgentJobController.php` | `runNow()` | 134 | 453-586 |
| `InterrogationSessionController.php` | `transformBuildState()` | 125 | 2902-3026 |
| `AgentJobController.php` | `update()` | 122 | 235-356 |
| `InterrogationSessionController.php` | `retry()` | 121 | 2084-2204 |
| `ReportComposer.php` | `inferredStackFromDependencies()` | 116 | 742-857 |
| `InterrogationSessionController.php` | `clarifyBuild()` | 110 | 1833-1942 |
| `RunEventWriter.php` | `appendChunk()` | 105 | 99-203 |
| `ReportComposer.php` | `compose()` | 102 | 29-130 |
| `AgentJobController.php` | `index()` | 100 | 27-126 |

**InterrogationSessionController** alone has 35 methods exceeding 30 lines.

---

## §3. Code Quality — Controller Business Logic (Missing Action Pattern)

Direct database queries found in controllers (sample of 80 occurrences across 25+ controllers):

**Highest concentration:**
- `RepoAnalysisSessionController.php` — 14 direct DB operations (`:create`, `->save()`, `->delete()`, `->update()`)
- `DelegationGraphController.php` — 5 direct operations (`->update()`, `->save()`, `->delete()`)
- `ChatSessionController.php` — 5 direct operations (`::where`, `->update()`)
- `MessengerConnectorController.php` — 4 direct operations (`->save()`, `->update()`)
- `ConnectorConnectionController.php` — 4 direct operations (`->update()`, `::where`)

**Pattern:** Controllers contain inline business logic (DB queries, state transitions, complex conditionals) that should be extracted into Action classes per engineering rules v2.0.

---

## §4. Code Quality — Debug Code

**No debug code found.** Grep for `dd(`, `var_dump(`, `dump(`, `console.log(` returned zero matches in `app/` and `resources/js/` (excluding false positives like `.add()`, `->errors()->add()`).

---

## §5. Code Quality — DRY Violations

### Top 5 Duplicated Validation Rule Patterns

| # | Pattern | Occurrences | Files |
|---|---------|-------------|-------|
| 1 | `'nullable', 'array'` | 12 fields across 7 files | StoreAgentJobRequest, UpdateAgentJobRequest, StoreOrgAgentRequest, UpdateOrgAgentRequest, StoreOrgCouncilRequest, UpdateOrgCouncilRequest, AppendWorkingMemoryRequest |
| 2 | `'sometimes', 'boolean'` | 6 fields across 3 files | StoreAgentJobRequest, UpdateAgentJobRequest, StoreOrgCouncilRequest |
| 3 | `'nullable', 'string', 'max:2000'` | 5 fields across 5 files | StoreOrgCouncilRequest, StoreOrgRitualRequest, UpdateOrgCouncilRequest, StoreAgentJobRequest, UpdateAgentJobRequest |
| 4 | `'required', 'string', 'max:1024'` | 4 fields across 4 files | StoreRepoAnalysisSessionRequest, StoreInterrogationSessionRequest, StoreAgentJobRequest, UpdateAgentJobRequest |
| 5 | `'required_with:active_hours_config', 'date_format:H:i'` | 4 fields across 2 files | StoreAgentJobRequest, UpdateAgentJobRequest |

### Adapter Duplication
`CodexAdapter.php` (1,194 lines) and `ClaudeAdapter.php` (1,161 lines) share massive structural duplication: both implement 15+ build command methods and 8+ parse response methods with embedded JSON schemas. These should be refactored to share a common base adapter with provider-specific overrides.

---

## §6. Security — Mass Assignment (Grouped P0)

### 57 Models with `$guarded = []`

| Model | File:Line |
|-------|-----------|
| AgentRunEvent | `app/Models/AgentRunEvent.php:12` |
| ChatMessage | `app/Models/ChatMessage.php:25` |
| ChatAction | `app/Models/ChatAction.php:22` |
| MemoryEmbedding | `app/Models/MemoryEmbedding.php:22` |
| MessengerEventDeduplication | `app/Models/MessengerEventDeduplication.php:18` |
| DelegationTaskDependency | `app/Models/DelegationTaskDependency.php:16` |
| DelegationAttempt | `app/Models/DelegationAttempt.php:17` |
| DocumentationFragment | `app/Models/DocumentationFragment.php:16` |
| AgentJobRun | `app/Models/AgentJobRun.php:41` |
| DocumentationLink | `app/Models/DocumentationLink.php:13` |
| AgentAuditLog | `app/Models/AgentAuditLog.php:13` |
| InterrogationBuildTask | `app/Models/InterrogationBuildTask.php:12` |
| MemoryProviderUsage | `app/Models/MemoryProviderUsage.php:23` |
| RepoAnalysisReport | `app/Models/RepoAnalysisReport.php:12` |
| NlParseAttempt | `app/Models/NlParseAttempt.php:13` |
| SchedulerHeartbeat | `app/Models/SchedulerHeartbeat.php:11` |
| DocumentationEntry | `app/Models/DocumentationEntry.php:15` |
| MessengerIdentityLink | `app/Models/MessengerIdentityLink.php:25` |
| ChatAttachment | `app/Models/ChatAttachment.php:19` |
| MemoryConversationLog | `app/Models/MemoryConversationLog.php:22` |
| InterrogationSession | `app/Models/InterrogationSession.php:21` |
| MemoryConsolidationLog | `app/Models/MemoryConsolidationLog.php:20` |
| ConnectorAccount | `app/Models/ConnectorAccount.php:23` |
| ConnectedProvider | `app/Models/ConnectedProvider.php:17` |
| CredentialVault | `app/Models/CredentialVault.php:17` |
| RepoAnalysisEvent | `app/Models/RepoAnalysisEvent.php:13` |
| RepoAnalysisSession | `app/Models/RepoAnalysisSession.php:22` |
| DelegationEvent | `app/Models/DelegationEvent.php:17` |
| MemorySetting | `app/Models/MemorySetting.php:18` |
| AgentFeatureSetting | `app/Models/AgentFeatureSetting.php:9` |
| RepoAnalysisTask | `app/Models/RepoAnalysisTask.php:13` |
| DelegateeMetric | `app/Models/DelegateeMetric.php:17` |
| AgentJob | `app/Models/AgentJob.php:24` |
| MemoryFormationFailure | `app/Models/MemoryFormationFailure.php:18` |
| InterrogationTechStack | `app/Models/InterrogationTechStack.php:14` |
| EscalationIncident | `app/Models/EscalationIncident.php:12` |
| AgentBackupSetting | `app/Models/AgentBackupSetting.php:13` |
| MemoryCoreBlock | `app/Models/MemoryCoreBlock.php:22` |
| AccountLinkToken | `app/Models/AccountLinkToken.php:22` |
| AgentSystemState | `app/Models/AgentSystemState.php:19` |
| MessengerDeadLetter | `app/Models/MessengerDeadLetter.php:33` |
| NlOrgParseAttempt | `app/Models/NlOrgParseAttempt.php:13` |
| TunnelSetting | `app/Models/TunnelSetting.php:13` |
| PendingConfirmation | `app/Models/PendingConfirmation.php:19` |
| DelegationGraph | `app/Models/DelegationGraph.php:20` |
| ChatSession | `app/Models/ChatSession.php:20` |
| DelegateeProfile | `app/Models/DelegateeProfile.php:21` |
| DelegationVerificationResult | `app/Models/DelegationVerificationResult.php:80` |
| RepoAnalysisArtifact | `app/Models/RepoAnalysisArtifact.php:12` |
| DelegationTask | `app/Models/DelegationTask.php:19` |
| RunClassification | `app/Models/RunClassification.php:12` |
| InterrogationEvent | `app/Models/InterrogationEvent.php:13` |
| WorkflowGateTransition | `app/Models/WorkflowGateTransition.php:12` |
| AgentMaintenanceCheckpoint | `app/Models/AgentMaintenanceCheckpoint.php:9` |
| DelegationCapability | `app/Models/DelegationCapability.php:16` |
| ApiDocArtifact | `app/Models/ApiDocArtifact.php:15` |
| InterrogationSetting | `app/Models/InterrogationSetting.php:10` |

**Recommendation:** Replace `$guarded = []` with explicit `$fillable` arrays on all 57 models.

---

## §7. Security — Auth Middleware Coverage

### Findings

| Route | Middleware | Status | Risk |
|-------|-----------|--------|------|
| `POST agent/api/v1/n8n/webhook` | `api`, `AgentApiVersionHeader` | **Missing auth** | P1 — no signature verification documented |
| `GET/PUT storage/{path}` | None | **Unprotected** | P1 — unauthenticated file access possible |
| `POST agent/api/v1/connectors/{name}/webhooks/{event}` | `api`, `AgentApiVersionHeader` | Expected — signature verified in controller | OK |
| `GET agent/api/v1/connectors/callback` | `api`, `AgentApiVersionHeader` | Expected — OAuth callback | OK |
| `POST stripe/webhook` | None | Expected — Cashier HMAC verification | OK |
| `GET up`, `GET messenger/health`, `GET agent/health/deployment` | `web` | Expected — health checks | OK |
| Messenger webhooks | `VerifyWebhookSignature`, `ReplayProtection`, `CorrelationId` | Well-secured | OK |

**Strengths:** API endpoints consistently use `auth:sanctum` + `license` middleware. Messenger webhooks properly implement signature verification.

---

## §8. Security — Input Validation

### `$request->all()` Usage (10 occurrences)

| File:Line | Context | Risk |
|-----------|---------|------|
| `MessengerConnectorController.php:103` | Passed to `Validator::make()` | Low — validated before use |
| `MessengerConnectorController.php:208` | Passed to `Validator::make()` | Low — validated before use |
| `MessengerConnectorController.php:359` | Passed to `Validator::make()` | Low — validated before use |
| `SystemDirectoryPickerController.php:17` | Passed to `Validator::make()` | Low — validated before use |
| `WorkflowGovernanceController.php:121` | Passed to `Validator::make()` | Low — validated before use |
| `WhatsAppAdapter.php:105` | Webhook payload processing | Medium — all fields accessible |
| `DiscordAdapter.php:117` | Webhook payload processing | Medium — all fields accessible |
| `WebhookController.php:60,100,149` | 3 occurrences in webhook handling | Medium — all fields accessible |

### Unescaped Blade Output (`{!!`)
No matches found — no Blade templates use unescaped output.

### `v-html` Usage (10 occurrences)

| File | Context | Risk |
|------|---------|------|
| `PrivacyPolicy.vue:20` | Server-rendered policy HTML | Low — trusted content |
| `TermsOfService.vue:20` | Server-rendered terms HTML | Low — trusted content |
| `Docs/Show.vue:541` | Documentation body HTML | Medium — if user content |
| `Docs/Index.vue:557` | Documentation body HTML | Medium — if user content |
| `TwoFactorAuthenticationForm.vue:146,150` | QR code + setup key | Low — Jetstream-generated |
| `DeadLetters/Index.vue:354,359` | Pagination labels | Low — Laravel paginator |
| `Runtime/Index.vue:148,153` | Pagination labels | Low — Laravel paginator |

### Raw SQL (`DB::raw()`) — 9 occurrences
All in aggregation contexts (`COUNT(*)`, `SUM()`, `invocation_count + 1`). No user input interpolation detected. **Low risk.**

---

## §9. Security — Hardcoded Secrets Scan

| File:Line | Finding | Risk |
|-----------|---------|------|
| `AgentInstallCommand.php:1124` | `putenv('DB_PASSWORD='.$dbPassword)` | Low — install wizard only |
| `CliRuntimeExecutor.php:95` | `'ANTHROPIC_API_KEY' => $apiKey` | Low — runtime env injection from config |
| `SessionProcessManager.php:111` | `'ANTHROPIC_API_KEY' => $apiKey` | Low — runtime env injection from config |
| `RunnerModelsController.php:50` | `config('runtime.llm.anthropic.api_key') ?? env('ANTHROPIC_API_KEY', '')` | Low — reading from config/env |
| `RunnerModelsController.php:98` | `env('OPENAI_API_KEY', '')` | Low — reading from env |
| `RunEventWriter.php:416` | Redaction regex for API keys | **Good** — active redaction pattern |

**No hardcoded secret values found.** All API key references properly use `config()` or `env()`.

---

## §10. AI-Specific Security (OWASP LLM 2025 + Agentic 2026)

### LLM07:2025 — System Prompt Leakage (CRITICAL)

**File:** `app/Http/Controllers/Api/V1/DelegateeProfileController.php:324`

The `transformProfile()` method exposes `soul_json` (containing personality, system_prompt, user_context) in API responses via `$profile->getSoul()`. Both `index()` and `show()` endpoints return this to authenticated users. The `MessengerConnectorController.php:349` has a separate `soul()` endpoint returning system_prompt with max 5000 chars.

**Attack vector:** Authenticated user enumerates system prompts across all delegatee profiles.

### LLM01:2025 — Prompt Injection (HIGH)

**File:** `app/Messenger/ChatAction/Handlers/GeneralTaskHandler.php:178,184`

Unescaped soul data directly concatenated into system prompts:
- Line 178: `$parts[] = $soul['personality']` — no escaping
- Line 184: `$parts[] = "About the user: {$soul['user_context']}"` — no escaping

**File:** `app/Support/Delegation/Verification/AiCriticStep.php:154`

`str_replace("{{{$key}}}", (string) $value, $prompt)` — task context substituted into AI critic prompt without sanitization. `task->name` and `task->contract_json['prompt']` are user-controllable.

### Agentic — Memory Poisoning (CRITICAL)

**File:** `app/Support/Memory/MemoryFormationPipeline.php:196-204`

User-supplied conversation content flows directly into Neo4j without sanitization:
- Line 196: Raw entities stored via `$this->graphStore->storeEntities()`
- Lines 200-204: Relationships stored with only basic structure validation

**Attack vector:** Crafted conversation content creates poisoned graph entities (e.g., `{type: "Person", name: "admin_password: xyz123"}`) that surface in future memory queries.

### Agentic — Permission Attenuation (HIGH)

**File:** `app/Support/Delegation/AttemptSpawner.php:125-131`

Permission bypass flags auto-injected during delegation:
- Claude: `--dangerously-skip-permissions` injected (line 125-127)
- Codex: `--dangerously-bypass-approvals-and-sandbox` injected (line 129-131)

The `ContractEnforcer` validates scope narrowly, but `AttemptSpawner` defeats it by injecting unsafe flags. `DelegationCoordinator.php:103-114` does NOT re-validate permissions post-execution.

### Agentic — Tool Misuse (MEDIUM)

**File:** `app/Support/Delegation/Verification/HumanApprovalStep.php:35-72`

Human approval is asynchronous — tasks execute immediately via `AttemptSpawner->spawn()`, then verification happens after. No pre-execution confirmation gate for irreversible actions.

### LLM02:2025 — Sensitive Information Disclosure (MEDIUM)

**File:** `app/Support/Agent/RunEventWriter.php:61-73`

All agent output (including assistant responses) logged to `agent_run_events` without PII redaction. The writer has a 5000-char cap but no content filtering for emails, API keys, or credentials in error messages.

### LLM06:2025 — Sensitive Information in System Prompt (MEDIUM)

**File:** `app/Models/DelegateeProfile.php:56-65`

`setSoul()` accepts and stores system prompts with no content validation. No policy prevents storing secrets in soul fields.

---

## §11. Supply Chain Security

### Dependency Audits
- **`composer audit`:** No security vulnerability advisories found. ✓
- **`npm audit`:** 0 vulnerabilities found. ✓

### GitHub Actions SHA Pinning (P1)
**File:** `.github/workflows/docs-deploy-sync.yml`
- Line 25: `uses: actions/checkout@v4` — version tag, not SHA-pinned
- Line 28: `uses: shivammathur/setup-php@v2` — version tag, not SHA-pinned

### Missing CI Quality Gate Workflows (P0)
Only 1 workflow exists (`docs-deploy-sync.yml`). Missing:
1. PHP unit/feature tests (`php artisan test`)
2. PHPStan static analysis (`./vendor/bin/phpstan analyse`)
3. Pint code style (`./vendor/bin/pint --test`)
4. ESLint frontend linting
5. Vitest frontend tests
6. `composer audit` in CI
7. `npm audit` in CI
8. `npm run build` verification

---

## §12. Compliance — `declare(strict_types=1)`

- **Files with declaration:** 406 / 910 (44.6%)
- **Files missing declaration:** 504 / 910 (55.4%)
- **Engineering rules v2.0 requirement:** All PHP files must have `declare(strict_types=1)`

---

## Verification Checklist

- [x] All 57 `$guarded = []` models listed in single grouped P0 finding (§6.1)
- [x] `soul_json` documented as LLM07:2025 finding (§10)
- [x] GitHub Actions SHA-pinning documented as P1 (§11)
- [x] Missing CI workflows documented as P0 (§11)
- [x] Top 10 God classes identified with line counts and responsibility assessment (§1)
- [x] Controller business logic violations documented with Refactoring Guru smell names (§3)
- [x] AI-specific security section (§10) uses OWASP LLM 2025 + Agentic 2026 categories
- [x] No debug code found (§4)
- [x] No hardcoded secrets found (§9)
- [x] Only 1 TODO comment found (§5)
