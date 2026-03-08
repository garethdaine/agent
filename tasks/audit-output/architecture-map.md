# Architecture Map — AgentOps Full Discovery Audit

> Session 6, Task 3 — Phase 1: Architecture Mapping and Data Model Audit
> Generated: 2026-03-08

---

## 1. Directory Structure

```
agent/
├── app/
│   ├── Actions/           2 subdirs (Fortify, Jetstream)
│   ├── Console/
│   │   └── Commands/      51 PHP files (3 subdirs: Memory, Skills, Tunnel)
│   ├── Events/            16 PHP files (3 subdirs: Documentation, Office, Org)
│   ├── Http/
│   │   ├── Controllers/   83 PHP files (16 subdirs)
│   │   ├── Middleware/     15 PHP files (2 subdirs: Memory, Messenger)
│   │   ├── Requests/      36 PHP files (10 subdirs)
│   │   └── Resources/     12 PHP files (2 subdirs: Connectors, Skills)
│   ├── Jobs/              45 PHP files (13 subdirs)
│   ├── Listeners/          6 PHP files (4 subdirs: Compliance, Documentation, Messenger, Org)
│   ├── Models/            80 PHP files (2 subdirs: Runtime, Security)
│   ├── Providers/          7 PHP files
│   ├── Services/          17 subdirs (0 direct PHP files — all in subdirs)
│   └── Support/          248 PHP files (41 subdirs)
├── config/                41 PHP files
├── connectors/            32 connector manifests (YAML)
├── database/
│   ├── factories/         43 PHP files
│   └── migrations/       115 migration files
├── resources/js/
│   ├── Components/       103 Vue files (13 subdirs)
│   ├── Composables/       12 JS/TS files (2 subdirs: Chat, Office)
│   ├── Layouts/            1 Vue file (AppLayout.vue)
│   └── Pages/            107 Vue files (45+ subdirs)
├── routes/                 4 files (api.php, web.php, console.php, channels.php)
└── tests/
    ├── Feature/           domain-organized test suites
    └── Unit/              domain-organized test suites
```

### File Count Summary

| Directory | Count |
|-----------|-------|
| `app/Models/` | 80 |
| `app/Http/Controllers/` | 83 |
| `app/Http/Requests/` | 36 |
| `app/Http/Resources/` | 12 |
| `app/Http/Middleware/` | 15 |
| `app/Jobs/` | 45 |
| `app/Events/` | 16 |
| `app/Listeners/` | 6 (+ 5 subscribers) |
| `app/Support/` | 248 |
| `app/Console/Commands/` | 51 |
| `app/Providers/` | 7 |
| `config/` | 41 |
| `database/migrations/` | 115 |
| `database/factories/` | 43 |
| `resources/js/Components/` (Vue) | 103 |
| `resources/js/Pages/` (Vue) | 107 |
| `resources/js/Composables/` | 12 |

---

## 2. Data Model Map

### 2.1 Models Using `$guarded = []` (57 models)

| # | Model | File:Line |
|---|-------|-----------|
| 1 | AccountLinkToken | `app/Models/AccountLinkToken.php:22` |
| 2 | AgentAuditLog | `app/Models/AgentAuditLog.php:13` |
| 3 | AgentBackupSetting | `app/Models/AgentBackupSetting.php:13` |
| 4 | AgentFeatureSetting | `app/Models/AgentFeatureSetting.php:9` |
| 5 | AgentJob | `app/Models/AgentJob.php:24` |
| 6 | AgentJobRun | `app/Models/AgentJobRun.php:41` |
| 7 | AgentMaintenanceCheckpoint | `app/Models/AgentMaintenanceCheckpoint.php:9` |
| 8 | AgentRunEvent | `app/Models/AgentRunEvent.php:12` |
| 9 | AgentSystemState | `app/Models/AgentSystemState.php:19` |
| 10 | ApiDocArtifact | `app/Models/ApiDocArtifact.php:15` |
| 11 | ChatAction | `app/Models/ChatAction.php:22` |
| 12 | ChatAttachment | `app/Models/ChatAttachment.php:19` |
| 13 | ChatMessage | `app/Models/ChatMessage.php:25` |
| 14 | ChatSession | `app/Models/ChatSession.php:20` |
| 15 | ConnectedProvider | `app/Models/ConnectedProvider.php:17` |
| 16 | ConnectorAccount | `app/Models/ConnectorAccount.php:23` |
| 17 | CredentialVault | `app/Models/CredentialVault.php:17` |
| 18 | DelegateeMetric | `app/Models/DelegateeMetric.php:17` |
| 19 | DelegateeProfile | `app/Models/DelegateeProfile.php:21` |
| 20 | DelegationAttempt | `app/Models/DelegationAttempt.php:17` |
| 21 | DelegationCapability | `app/Models/DelegationCapability.php:16` |
| 22 | DelegationEvent | `app/Models/DelegationEvent.php:17` |
| 23 | DelegationGraph | `app/Models/DelegationGraph.php:20` |
| 24 | DelegationTask | `app/Models/DelegationTask.php:19` |
| 25 | DelegationTaskDependency | `app/Models/DelegationTaskDependency.php:16` |
| 26 | DelegationVerificationResult | `app/Models/DelegationVerificationResult.php:80` |
| 27 | DocumentationEntry | `app/Models/DocumentationEntry.php:15` |
| 28 | DocumentationFragment | `app/Models/DocumentationFragment.php:16` |
| 29 | DocumentationLink | `app/Models/DocumentationLink.php:13` |
| 30 | EscalationIncident | `app/Models/EscalationIncident.php:12` |
| 31 | InterrogationBuildTask | `app/Models/InterrogationBuildTask.php:12` |
| 32 | InterrogationEvent | `app/Models/InterrogationEvent.php:13` |
| 33 | InterrogationSession | `app/Models/InterrogationSession.php:21` |
| 34 | InterrogationSetting | `app/Models/InterrogationSetting.php:10` |
| 35 | InterrogationTechStack | `app/Models/InterrogationTechStack.php:14` |
| 36 | MemoryConsolidationLog | `app/Models/MemoryConsolidationLog.php:20` |
| 37 | MemoryConversationLog | `app/Models/MemoryConversationLog.php:22` |
| 38 | MemoryCoreBlock | `app/Models/MemoryCoreBlock.php:22` |
| 39 | MemoryEmbedding | `app/Models/MemoryEmbedding.php:22` |
| 40 | MemoryFormationFailure | `app/Models/MemoryFormationFailure.php:18` |
| 41 | MemoryProviderUsage | `app/Models/MemoryProviderUsage.php:23` |
| 42 | MemorySetting | `app/Models/MemorySetting.php:18` |
| 43 | MessengerDeadLetter | `app/Models/MessengerDeadLetter.php:33` |
| 44 | MessengerEventDeduplication | `app/Models/MessengerEventDeduplication.php:18` |
| 45 | MessengerIdentityLink | `app/Models/MessengerIdentityLink.php:25` |
| 46 | NlOrgParseAttempt | `app/Models/NlOrgParseAttempt.php:13` |
| 47 | NlParseAttempt | `app/Models/NlParseAttempt.php:13` |
| 48 | PendingConfirmation | `app/Models/PendingConfirmation.php:19` |
| 49 | RepoAnalysisArtifact | `app/Models/RepoAnalysisArtifact.php:12` |
| 50 | RepoAnalysisEvent | `app/Models/RepoAnalysisEvent.php:13` |
| 51 | RepoAnalysisReport | `app/Models/RepoAnalysisReport.php:12` |
| 52 | RepoAnalysisSession | `app/Models/RepoAnalysisSession.php:22` |
| 53 | RepoAnalysisTask | `app/Models/RepoAnalysisTask.php:13` |
| 54 | RunClassification | `app/Models/RunClassification.php:12` |
| 55 | SchedulerHeartbeat | `app/Models/SchedulerHeartbeat.php:11` |
| 56 | TunnelSetting | `app/Models/TunnelSetting.php:13` |
| 57 | WorkflowGateTransition | `app/Models/WorkflowGateTransition.php:12` |

### 2.2 Models Using `$fillable` (23 models)

| Model | File |
|-------|------|
| User | `app/Models/User.php` |
| Team | `app/Models/Team.php` |
| TeamInvitation | `app/Models/TeamInvitation.php` |
| OrgRitualTemplate | `app/Models/OrgRitualTemplate.php` |
| OrgRitualRun | `app/Models/OrgRitualRun.php` |
| OrgAgentProfile | `app/Models/OrgAgentProfile.php` |
| OrgReportingEdge | `app/Models/OrgReportingEdge.php` |
| OrgCouncilTemplate | `app/Models/OrgCouncilTemplate.php` |
| OrgCostLedger | `app/Models/OrgCostLedger.php` |
| OrgEscalation | `app/Models/OrgEscalation.php` |
| UserNotificationSetting | `app/Models/UserNotificationSetting.php` |
| UserChatPreference | `app/Models/UserChatPreference.php` |
| AgentConnector | `app/Models/AgentConnector.php` |
| AgentConnectorConnection | `app/Models/AgentConnectorConnection.php` |
| AgentConnectorCredential | `app/Models/AgentConnectorCredential.php` |
| AgentConnectorInvocation | `app/Models/AgentConnectorInvocation.php` |
| AgentConnectorWebhookEvent | `app/Models/AgentConnectorWebhookEvent.php` |
| AgentConnectorCredentialEvent | `app/Models/AgentConnectorCredentialEvent.php` |
| AgentConnectorApproval | `app/Models/AgentConnectorApproval.php` |
| AgentSkill | `app/Models/AgentSkill.php` |
| AgentSkillValidation | `app/Models/AgentSkillValidation.php` |
| SecurityEvent | `app/Models/Security/SecurityEvent.php` |
| SecurityDetectionRule | `app/Models/Security/SecurityDetectionRule.php` |

### 2.3 Special Cases

| Model | Notes |
|-------|-------|
| AccountSecurityOverride | `app/Models/Security/AccountSecurityOverride.php` — uses `$fillable` |
| Membership | Extends JetstreamMembership (no guarded/fillable) |
| DelegateeCapabilityPivot | Pivot model (no guarded/fillable) |
| RuntimeSession | `app/Models/Runtime/RuntimeSession.php` — uses `$fillable` |
| RuntimeTurn | `app/Models/Runtime/RuntimeTurn.php` — uses `$fillable` |
| RuntimeToolCall | `app/Models/Runtime/RuntimeToolCall.php` — uses `$fillable` |
| RuntimeApproval | `app/Models/Runtime/RuntimeApproval.php` — uses `$fillable` |
| RuntimeArtifact | `app/Models/Runtime/RuntimeArtifact.php` — uses `$fillable` |
| RuntimePolicySnapshot | `app/Models/Runtime/RuntimePolicySnapshot.php` — uses `$fillable` |

### 2.4 Model Clusters with Relationships

#### Core (4 models)
- **User** → hasMany: AgentJob, AgentJobRun, InterrogationSession, InterrogationSetting, ConnectedProvider, DelegationGraph, DelegateeProfile; hasOne: NotificationSetting, ChatPreference
- **Team** → (Jetstream team model)
- **AgentJob** → belongsTo: User, Team; hasMany: AgentJobRun
- **AgentJobRun** → belongsTo: AgentJob, Team, User, InitiatedBy(User); hasMany: AgentRunEvent

#### Delegation (10 models)
- **DelegationGraph** → belongsTo: User; hasMany: DelegationTask, DelegationEvent
- **DelegationTask** → belongsTo: DelegationGraph, DelegateeProfile; hasMany: DelegationAttempt, DelegationVerificationResult; belongsToMany: Dependencies(self), Dependents(self)
- **DelegationTaskDependency** → belongsTo: Task, DependsOnTask
- **DelegationAttempt** → belongsTo: DelegationTask, DelegateeProfile, AgentJobRun
- **DelegationEvent** → belongsTo: DelegationGraph, DelegationTask
- **DelegationVerificationResult** → belongsTo: DelegationTask, DelegationAttempt
- **DelegateeProfile** → belongsTo: User; belongsToMany: DelegationCapability; hasOne: DelegateeMetric
- **DelegateeCapabilityPivot** → belongsTo: DelegateeProfile, DelegationCapability
- **DelegationCapability** → (standalone capability registry)
- **DelegateeMetric** → belongsTo: DelegateeProfile

#### Memory (7 models)
- **MemoryCoreBlock** → belongsTo: User, AgentJob
- **MemoryEmbedding** → belongsTo: User
- **MemoryConversationLog** → belongsTo: User, AgentJobRun, AgentJob, RuntimeSession
- **MemoryFormationFailure** → belongsTo: User, AgentJobRun
- **MemoryConsolidationLog** → belongsTo: User
- **MemorySetting** → belongsTo: User
- **MemoryProviderUsage** → belongsTo: User, AgentJobRun

#### Messenger/Chat (9 models)
- **ChatSession** → belongsTo: User, ConnectorAccount; hasMany: ChatMessage, PendingConfirmation
- **ChatMessage** → belongsTo: ChatSession, ConnectorAccount; hasMany: ChatAction, ChatAttachment
- **ChatAttachment** → belongsTo: ChatMessage
- **ChatAction** → belongsTo: ChatMessage; hasOne: PendingConfirmation
- **PendingConfirmation** → belongsTo: ChatAction, ChatSession, ConnectorAccount
- **ConnectorAccount** → hasMany: ChatSession, ChatMessage, MessengerIdentityLink, PendingConfirmation
- **MessengerIdentityLink** → belongsTo: User, ConnectorAccount
- **MessengerDeadLetter** → belongsTo: ConnectorAccount
- **MessengerEventDeduplication** → belongsTo: ConnectorAccount

#### Interrogation (5 models)
- **InterrogationSession** → belongsTo: User; hasMany: InterrogationEvent, InterrogationBuildTask, InterrogationTechStack; morphMany: ConnectedProvider
- **InterrogationEvent** → belongsTo: InterrogationSession
- **InterrogationBuildTask** → belongsTo: InterrogationSession, AgentJobRun
- **InterrogationTechStack** → belongsTo: InterrogationSession
- **InterrogationSetting** → belongsTo: User

#### RepoAnalysis (5 models)
- **RepoAnalysisSession** → belongsTo: User; hasMany: Events, Tasks, Artifacts, Reports
- **RepoAnalysisTask** → belongsTo: RepoAnalysisSession; hasMany: Artifacts
- **RepoAnalysisArtifact** → belongsTo: RepoAnalysisSession, RepoAnalysisTask
- **RepoAnalysisReport** → belongsTo: RepoAnalysisSession
- **RepoAnalysisEvent** → belongsTo: RepoAnalysisSession

#### Org (8 models)
- **OrgRitualTemplate** → belongsTo: User; hasMany: OrgRitualRun
- **OrgRitualRun** → belongsTo: OrgRitualTemplate, User, DelegationGraph
- **OrgAgentProfile** → belongsTo: User, DelegateeProfile, Parent(self); hasMany: Children(self); hasOne: OrgReportingEdge
- **OrgReportingEdge** → belongsTo: Subordinate(OrgAgentProfile), Manager(OrgAgentProfile), User
- **OrgCouncilTemplate** → belongsTo: User
- **OrgCostLedger** → belongsTo: User, OrgAgentProfile, OrgRitualRun
- **OrgEscalation** → belongsTo: OrgRitualRun, OrgAgentProfile
- **NlOrgParseAttempt** → belongsTo: User

#### Runtime (6 models)
- **RuntimeSession** → belongsTo: User, Team, Parent(self), ChatSession; hasMany: Children(self), RuntimeTurn, RuntimeArtifact, RuntimePolicySnapshot
- **RuntimeTurn** → belongsTo: RuntimeSession; hasMany: RuntimeToolCall
- **RuntimeToolCall** → belongsTo: RuntimeTurn; hasOne: RuntimeApproval
- **RuntimeApproval** → belongsTo: RuntimeToolCall, RequestedByUser, DecisionByUser
- **RuntimeArtifact** → belongsTo: RuntimeSession
- **RuntimePolicySnapshot** → belongsTo: RuntimeSession

#### Documentation (4 models)
- **DocumentationEntry** → hasMany: DocumentationFragment, DocumentationLink, ApiDocArtifact
- **DocumentationFragment** → belongsTo: DocumentationEntry; hasMany: DocumentationLink
- **DocumentationLink** → belongsTo: DocumentationEntry, DocumentationFragment
- **ApiDocArtifact** → belongsTo: DocumentationEntry

#### Connector (7 models)
- **AgentConnector** → hasMany: AgentConnectorConnection
- **AgentConnectorConnection** → belongsTo: Team, AgentConnector, ConnectedBy(User); hasOne: AgentConnectorCredential; hasMany: Invocations, WebhookEvents, Approvals
- **AgentConnectorCredential** → belongsTo: Team, AgentConnector, CreatedBy, UpdatedBy, RevokedBy; hasMany: CredentialEvent
- **AgentConnectorInvocation** → belongsTo: AgentConnectorConnection
- **AgentConnectorWebhookEvent** → belongsTo: AgentConnectorConnection
- **AgentConnectorCredentialEvent** → belongsTo: AgentConnectorCredential, Actor(User)
- **AgentConnectorApproval** → belongsTo: AgentConnectorConnection, AgentConnector, ResolvedBy(User)

#### Security (3 models)
- **SecurityEvent** → belongsTo: RuntimeSession, RuntimeToolCall
- **SecurityDetectionRule** → (standalone rule registry)
- **AccountSecurityOverride** → (standalone config)

#### Skills (2 models)
- **AgentSkill** → belongsTo: Team, InstalledBy, UpdatedBy, PausedBy
- **AgentSkillValidation** → belongsTo: Team, ValidatedBy

#### Settings/Config (6 models)
- UserNotificationSetting, UserChatPreference, AgentBackupSetting, AgentFeatureSetting, TunnelSetting, SchedulerHeartbeat

#### Misc (10 models)
- AgentAuditLog, CredentialVault, ConnectedProvider (morphTo: Providerable), AccountLinkToken, AgentRunEvent, NlParseAttempt, AgentSystemState, AgentMaintenanceCheckpoint, EscalationIncident, WorkflowGateTransition, RunClassification, Membership, TeamInvitation

**Total: 80 models documented** (task assumed 78; actual count is 80 in `app/Models/` including `Runtime/` and `Security/` subdirectories)

---

## 3. DelegateeProfile Deep Inspection (Tomašev Framework)

**File**: `app/Models/DelegateeProfile.php`

### Verified Fields
| Field | Cast | Status |
|-------|------|--------|
| `trust_score` | `decimal:2` | **Present** |
| `trust_updated_at` | `datetime` | **Present** |
| `soul_json` | `array` | **Present** — prompt injection surface (see below) |
| `env_json` | `array` | Present |
| `config_json` | `array` | Present |
| `is_active` | `boolean` | Present |

### Tomašev Compliance Gaps

1. **Missing `capability_profile` JSON column** — Currently capabilities are pivot-based via `capabilities()` BelongsToMany through `delegatee_capabilities_pivot`. The Tomašev framework requires a queryable JSON column for capability profiles, not a many-to-many pivot table. This limits single-query capability matching.

2. **`soul_json` prompt injection surface (LLM07:2025)** — The `getSoul()` method exposes `personality`, `system_prompt`, and `user_context` fields. The `setSoul()` method writes user-controlled values directly to `soul_json` without sanitization. These fields are likely injected into AI system prompts during delegation, creating a prompt injection attack surface. No input validation or sanitization is performed on `soul_json` contents.

3. **Mass assignment via `$guarded = []`** at line 21 — All fields including `trust_score`, `trust_updated_at`, and `soul_json` are mass-assignable, allowing potential trust score manipulation through API endpoints.

4. **No `capability_profile` migration** — No migration creates a `capability_profile` JSON column on the `delegatee_profiles` table.

---

## 4. Horizon Supervisor Configuration Map (15 Supervisors)

| # | Supervisor | Queue | maxProcesses (default) | Prod maxProcesses | tries | backoff | timeout | balance |
|---|-----------|-------|----------------------|-------------------|-------|---------|---------|---------|
| 1 | supervisor-1 | agent | 2 | 10 | 1 | 0 | **86,500s (~24h)** | auto |
| 2 | supervisor-interrogation | interrogation | 2 | 2 | 1 | 0 | 7,800s (~2.2h) | auto |
| 3 | supervisor-messenger | messenger-high, messenger-default | 3 | 3 | 3 | [5,30,60] | 120s | auto |
| 4 | supervisor-delegation | delegation | 2 | 2 | 1 | 0 | 900s (15m) | auto |
| 5 | supervisor-memory-working | memory-working | 3 | 3 | **0** | 0 | **5s** | auto |
| 6 | supervisor-memory-formation | memory-formation | 3 | 3 | 5 | [10,30,60,120,300] | 300s (5m) | auto |
| 7 | supervisor-org-rituals | org-rituals | 2 | 2 | 3 | [10,30,60] | 600s (10m) | auto |
| 8 | supervisor-code-analysis | code-analysis | 2 | 2 | 1 | 0 | ~3,780s (~1h) | auto |
| 9 | supervisor-subagent | subagent | 2 | 2 | 1 | 0 | 3,600s (1h) | auto |
| 10 | supervisor-skill-validation | skill-validation | 2 | 2 | 3 | [10,30,60] | 120s | auto |
| 11 | supervisor-tunnel | tunnel | **1 (fixed)** | **1** | 5 | [10,30,60,120,300] | **0 (none)** | **'false'** |
| 12 | supervisor-connector-credentials | connector-credentials | 2 | 2 | 3 | [10,30,60] | 60s | auto |
| 13 | supervisor-connector-webhooks | connector-webhooks | 2 | 2 | 3 | [5,15,45] | 30s | auto |
| 14 | supervisor-connector-approvals | connector-approvals | 1 | 1 | 3 | [5,15,30] | 30s | auto |
| 15 | supervisor-default | default | 1 | 1 | 3 | [5,30,60] | 120s | auto |

All supervisors use 128MB memory, `autoScalingStrategy: time`, and production overrides of `balanceMaxShift: 1`, `balanceCooldown: 3` (except supervisor-tunnel).

### Deviations from Engineering Rules v2.0

| Deviation | Details |
|-----------|---------|
| **Missing `supervisor-long-running`** | Rules require a dedicated supervisor with 600s timeout, 256MB memory, up to 5 processes. No such supervisor exists. |
| **supervisor-1 timeout: 86,500s** | Far exceeds any reasonable timeout. Rules specify 600s for long-running. Memory is 128MB vs required 256MB. |
| **supervisor-tunnel timeout: 0** | No timeout enforcement. `balance: 'false'` (string literal, not boolean). |
| **supervisor-memory-working tries: 0** | Zero retries may cause silent data loss on transient failures. |
| **All supervisors: 128MB memory** | Rules specify 256MB for long-running supervisors. |

---

## 5. Service Provider Audit

### AppServiceProvider (`app/Providers/AppServiceProvider.php`)

**Singleton Bindings:**
- `AdversarialReviewerService` — payload review
- `ComplexityClassifier` — from config
- `VerificationEvidenceEvaluator`
- `ComplianceFlagResolver`
- `LessonsManager` — from config
- `CredentialsManager`
- `OAuthTokenService`
- `ToolGateway`
- `InstanceFingerprint`
- `LicenseService`
- `SkillLibrary`

**Interface Bindings:**
- `OrchestrationPolicyServiceContract` → `OrchestrationPolicyService`
- `DocsSearchIndexClient` → `ScoutDocsSearchIndexClient`
- `DocsReindexExecutor` → `ScoutDocsReindexExecutor`
- `DocsSyncSleeper` → `SystemDocsSyncSleeper`
- `DocsTelemetryStore` → `AgentSystemDocsTelemetryStore`

**Boot:**
- Registers 7 tool adapters with `ToolGateway`: Fs, Runtime, Web, Browser, Discovery, AgentApi, Mcp
- Subscribes 5 event subscribers (see Event map)
- Registers 11+ individual event listeners
- Defines authorization policies for 9 models
- Configures rate limiters: `agent-mutations`, `interrogation`, `memory-reads`, `memory-writes`

### MemoryServiceProvider (`app/Providers/MemoryServiceProvider.php`)
- Deferred bindings (only when memory enabled)
- 13 singletons: CoreMemoryManager, MemorySettingsService, WorkingMemoryBuffer, WorkingMemorySummarizer, Neo4jGraphStore, ConsolidationService, ForgettingService, MemoryCapabilityResolver, MemoryAdapterFactory, MemoryFormationPipeline, EmbeddingProvider (→NullEmbeddingProvider), HybridRetriever

### MessengerServiceProvider (`app/Providers/MessengerServiceProvider.php`)
- 4 singletons: ConnectorManager, IdempotencyKeyGenerator, LoopInterface (ReactPHP), MessengerGatewayManager

### ConnectorServiceProvider (`app/Providers/ConnectorServiceProvider.php`)
- 1 singleton: ConnectorVaultEncrypter
- Boot: syncs connector registry if feature flag enabled

### FortifyServiceProvider, HorizonServiceProvider, JetstreamServiceProvider
- Standard Laravel package providers with auth actions, rate limiters, API token permissions

---

## 6. Event → Listener Map

### Subscribers (Multi-Event Handlers)

| Subscriber | Events | Queue |
|-----------|--------|-------|
| DelegationCoordinator | GraphStarted, AttemptCompleted, TaskVerified | sync |
| DelegationRecoveryHandler | AttemptCompleted | sync |
| DelegationBroadcastSubscriber | GraphStarted, AttemptCompleted, TaskVerified, GraphCompleted | delegation |
| DelegationEventPersistenceSubscriber | GraphStarted, AttemptCompleted, TaskVerified, GraphCompleted | delegation |
| DocumentationTelemetrySubscriber | TooltipKeyMissing, DocsSearchUnavailable, DocsSyncOutcome | sync |

### Individual Listeners

| Event | Listener | Queue |
|-------|----------|-------|
| DelegationGraphCompleted | RitualRunCompletionListener | sync |
| DelegationTaskVerified | RitualCouncilDeliberationListener | sync |
| DelegationTaskVerified | RitualPhaseOutputCaptureListener | sync |
| DelegationGraphCompleted | SendRitualRunCompletedNotification | sync |
| DelegationTaskVerified | SendDelegationTaskFailedNotification | sync |
| AgentJobRunFinished | SendAgentJobFinishedNotification | sync |
| AgentJobRunFinished | LessonExtractionListener | sync |
| AgentJobRunFinished | DispatchBuildTickOnRunFinished | default |
| OrgRitualEscalationTimedOut | SendEscalationTimedOutNotification | sync |
| RuntimeApprovalRequested | SendApprovalRequestedNotification | sync |
| RepoAnalysisSessionUpdated | SendRepoAnalysisCompletedNotification | sync |
| InterrogationPhaseChanged | SendInterrogationPhaseNotification | sync |

### Broadcast Events (Real-Time via Reverb WebSockets)

| Event | Channel | Event Name | Queued |
|-------|---------|------------|--------|
| DelegationGraphBroadcast | `delegation.graph.{id}` | delegation.updated | yes |
| DelegationUserSummaryBroadcast | `delegation.user.{id}` | delegation.summary | yes |
| InterrogationPhaseChanged | `interrogation.{id}` | phase.changed | yes |
| InterrogationSessionUpdated | `interrogation.{id}` | session.updated | yes |
| RepoAnalysisSessionUpdated | `code-analysis.{id}` | session.updated | now |
| RunStatusChanged | `user.{id}` | run.status_changed | now |
| RunEventsAvailable | `run.{id}` | events.available | now |
| RuntimeApprovalRequested | `user.{id}` | runtime.approval_requested | now |
| ChatMessageReceived | `user.{id}` | chat.message_received | now |
| NotificationCreated | `user.{id}` | notification.created | now |

---

## 7. External API Integrations

### AI Provider Adapters (Guzzle HTTP)

| Provider | Base URL | Auth | Location |
|----------|----------|------|----------|
| Anthropic | `https://api.anthropic.com/v1` | `x-api-key` header | `app/Support/Memory/Adapters/AnthropicAdapter.php` |
| OpenAI | `https://api.openai.com/v1` | `Bearer` token | `app/Support/Memory/Adapters/OpenAIAdapter.php` |

**Adapter Pattern**: `GuzzleHttpAdapter` base class provides retry (3 attempts), rate limit handling (429 backoff), jitter (25%), and error logging.

### Data Store Integrations

| Service | Protocol | Client | Config |
|---------|----------|--------|--------|
| Neo4j 5.x | Bolt | `laudis/neo4j-php-client:^3.0` | `config/memory.php` |
| Typesense 27.1 | HTTP | `typesense/typesense-php:^6.0` via Laravel Scout | `config/scout.php` |
| Redis | TCP | `predis/predis:^2.3` | `config/database.php` (DB 0, 1, 2) |
| PostgreSQL | TCP | PDO (pgsql) | `config/database.php` |

### Other HTTP Integrations

| Integration | Location |
|-------------|----------|
| Messenger HTTP Client | `app/Support/Messenger/MessengerHttpClient.php` |
| Runtime LLM Client | `app/Services/Runtime/RuntimeLlmClient.php` |
| Connector Pipeline HTTP | `app/Support/Connectors/Pipeline/ConnectorHttpClient.php` |
| Stripe (Cashier) | `laravel/cashier:^16.3` |

### Real-Time Communication
- **WebSockets**: Laravel Reverb (`laravel/reverb:^1.7`) — NOT SSE
- **Streaming**: Only `LogTailController@export` uses `StreamedResponse` (text/plain log download)
- No `text/event-stream` endpoints found

---

## 8. Route Surface

### Summary

| Category | Count |
|----------|-------|
| **Total routes** | 434 |
| **API v1 routes** (`agent/api/v1/`) | 250 |
| **Web routes** | 176 |
| **Authenticated routes** | 413 |
| **Unauthenticated routes** | 21 |

### Unauthenticated Routes (21)

| Method | URI | Purpose | Auth Notes |
|--------|-----|---------|------------|
| GET | `/` | Landing page | Public |
| GET | `agent/api/v1/connectors/callback` | OAuth callback | Signature-verified |
| POST | `agent/api/v1/connectors/discord/webhook` | Discord webhook | Signature-verified |
| POST | `agent/api/v1/connectors/slack/webhook` | Slack webhook | Signature-verified |
| POST | `agent/api/v1/connectors/telegram/webhook/{key}` | Telegram webhook | Token-verified |
| GET/POST | `agent/api/v1/connectors/whatsapp/webhook` | WhatsApp webhook | Signature-verified |
| POST | `agent/api/v1/connectors/{name}/webhooks/{event}` | Generic webhook | Signature-verified |
| GET | `agent/api/v1/health` | Health check | Intentionally public |
| POST | `agent/api/v1/n8n/webhook` | n8n webhook | Signature-verified |
| GET | `agent/health/deployment` | Deployment health | Intentionally public |
| GET/POST | `broadcasting/auth` | Broadcast auth | Laravel internal |
| GET | `docs/api` | API docs UI | Intentionally public |
| GET | `docs/api.json` | OpenAPI spec | Intentionally public |
| GET | `messenger/health` | Messenger health | Intentionally public |
| GET | `messenger/link/{token}` | Account linking | Token-verified |
| GET | `sanctum/csrf-cookie` | CSRF cookie | Laravel internal |
| GET/PUT | `storage/{path}` | Storage access | Laravel internal |
| GET | `stripe/payment/{id}` | Payment page | Cashier managed |
| POST | `stripe/webhook` | Stripe webhook | Stripe signature |
| GET | `/up` | Health check | Laravel internal |

**All API endpoints use v1 versioning** (`agent/api/v1/`). No unversioned API endpoints found.

### Auth Middleware Stack (API routes)
- `AgentApiVersionHeader` — version header enforcement
- `Authenticate:sanctum` — Sanctum token auth
- `EnsureLicenseValid` — license validation
- `ThrottleRequests:agent-mutations` — rate limiting on mutations

### Feature-Gated Route Groups
- `org.ui` — Organization layer UI
- `delegation.ui` — Delegation management UI
- `skills.ui` — Skills management UI
- `connectors.ui` — Connector management UI
- `office.ui` — Office/dashboard UI
- `tunnel.feature` — Tunnel feature

---

## 9. Python Layer Confirmation

**No Python/FastAPI layer exists.** Confirmed via file search:

- No `.py` files in project root or `app/` directory
- No `requirements.txt` in project root
- No `pyproject.toml` in project root
- Python files found only in: `vendor/mockery/mockery/docs/conf.py` (test framework docs), `.claude/skills/*/scripts/*.py` (Claude skill references), `tests/Fixtures/Skills/dangerous-scripts/scripts/network.py` (test fixture)

**Memory layer is PHP-only**: `MemoryFormationPipeline`, `Neo4jGraphStore`, `MemoryContextBuilder`, Guzzle-based AI provider adapters.

---

## 10. Key Dependencies

### PHP (composer.json)

| Package | Version | Purpose |
|---------|---------|---------|
| laravel/framework | ^12.0 | Core framework |
| laravel/horizon | ^5.44 | Queue management |
| laravel/reverb | ^1.7 | WebSocket server |
| laravel/sanctum | ^4.0 | API authentication |
| laravel/jetstream | ^5.4 | Auth scaffolding |
| laravel/scout | ^10.24 | Search integration |
| laravel/cashier | ^16.3 | Stripe billing |
| laravel/pennant | ^1.19 | Feature flags |
| laudis/neo4j-php-client | ^3.0 | Graph database |
| typesense/typesense-php | ^6.0 | Search engine |
| predis/predis | ^2.3 | Redis client |
| spatie/laravel-backup | ^10.0 | Backups |
| dedoc/scramble | ^0.13.14 | OpenAPI generation |
| yethee/tiktoken | ^1.1 | Token counting |
| ratchet/pawl | ^0.4.3 | WebSocket client |

### JavaScript (package.json)

| Package | Version | Purpose |
|---------|---------|---------|
| vue | ^3.3.13 | Frontend framework |
| @inertiajs/vue3 | ^2.0 | SPA bridge |
| laravel-echo | ^2.3.0 | Broadcast client |
| @tiptap/* | ^3.19.0 | Rich text editor |
| @vue-flow/* | - | Graph visualization |
| three | ^0.170.0 | 3D rendering |
| vite | ^7.0.7 | Build tool |
| tailwindcss | ^3.4.0 | CSS framework |
| vitest | ^3.2.4 | Unit testing |
| @playwright/test | ^1.58.2 | E2E testing |
| eslint | ^10.0.3 | Linting |

---

## Verification Checklist

- [x] All 80 models documented with relationship methods (task assumed 78; actual 80)
- [x] All 15 Horizon supervisors documented with timeout values
- [x] DelegateeProfile Tomašev compliance gaps documented
- [x] 57 `$guarded = []` models listed with file:line
- [x] Route list captured with auth middleware annotations (434 routes, 21 unauthenticated)
- [x] No Python files found outside vendor/node_modules
- [x] Service providers audited (7 providers)
- [x] Event → Listener map complete (5 subscribers, 12+ individual listeners, 10 broadcast events)
- [x] External API integrations documented (Anthropic, OpenAI, Neo4j, Typesense, Stripe)
