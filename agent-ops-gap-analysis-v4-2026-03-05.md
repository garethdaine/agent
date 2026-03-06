**AGENT OPS**

Platform Gap Analysis & Outreach Readiness Report

5 March 2026 \| Confidential \| Internal Review \| v4

**EXECUTIVE SUMMARY**

This report cross-references what the investor pack (v8) and investor deck promise against what is currently built in the Agent Ops codebase (93 migrations, 75 models, 66 services, 356 test files with 81K LOC, 176 Vue components). The platform core is substantially complete at \~86% across 42 tracked features. Recent builds have closed major gaps: Teams/Multi-Tenancy (80%), Billing/Subscriptions (75%), Credential Vault (80%), Onboarding (90%), Runtime Session Layer (70%), and OpenClaw Security Parity Phases A+B. The remaining outreach blockers are marketing website deployment (agent-website is built; needs production deploy) and client deployment packaging.

The platform is production-grade for internal use and approaching pilot-readiness. An architectural decision has been made (5 March 2026) to adopt a Laravel Nova/Spark-style distribution model: the platform codebase is the product (distributed as a private Composer package), the marketing website is a separate project (agent-website), and billing/licensing operates via a standalone licensing portal. This reclassifies the marketing website as out-of-scope for this codebase and introduces new P0 requirements: a licensing/distribution system and package restructuring. 3-5 weeks of focused work on P0/P1 gaps (package restructuring, licensing system, install wizard, billing/tenancy polish, first vertical template) would close the distance between what exists and what is promised. An additional 11 discovery briefs define the Phase 2/3 roadmap (\~35 weeks of build work).

**1. Overall Feature Completion**

Across 42 tracked feature areas, the weighted completion stands at approximately 86%. The core platform (job management, reliability, cost governance, messenger, delegation, memory) is effectively complete. Recent builds have added Client Infrastructure (Teams, Billing, Credentials, Security) and a Runtime Agent Layer. Remaining gaps concentrate in deployment packaging, marketing, and final polish on the newly-built subsystems.

  ------------------------------ ----------- ---------------- --------------------------------
  **Feature Area**               **Items**   **Completion**   **Status**
  **Core Platform**              8           **99%**          Production ready
  **Reliability & Governance**   6           **88%**          Near complete, gaps identified
  **Messenger Control Plane**    4           **100%**         Production ready
  **Delegation Engine**          4           **96%**          Production ready
  **Memory System**              3           **100%**         Production ready
  **UI/UX**                      2           **88%**          Near complete, gaps identified
  **Discovery & Analysis**       3           **90%**          Production ready
  **Org Layer**                  2           **73%**          Near complete, gaps identified
  **Infrastructure**             3           **98%**          Production ready
  **Client Infrastructure**      4           **76%**          Near complete, gaps identified
  **Runtime Agent Layer**        2           **80%**          Near complete, gaps identified
  **OVERALL**                    41          **92%**          See detailed breakdown below
  ------------------------------ ----------- ---------------- --------------------------------

**2. Detailed Feature Matrix**

Every feature tracked against the specs in /docs, the investor pack v8, and the investor deck.

  --------------------------------------------- ---------- ------------------- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  **Feature**                                   **%**      **Status**          **Notes**
  **Agent Job CRUD + Scheduling**               **100%**   **Complete**        Full lifecycle: create, edit, enable/disable, cron expressions, runner config, runner selection (Claude/Codex/custom)
  **Job Execution Engine**                      **100%**   **Complete**        Subprocess spawning, event capture, timeout/cooldown, overlap prevention, targeted retry
  **Run Monitor + Event Tail**                  **100%**   **Complete**        Real-time polling, auto-follow, pause/resume, structured events, operator + system overview dashboards
  **Approval Workflow**                         **100%**   **Complete**        Runner-aware templates, approve/deny handling, PendingConfirmation model, tool auto-approvals in runtime
  **Audit Log**                                 **100%**   **Complete**        Immutable trail for all mutating actions from run \#1, full RBAC enforcement
  **Path/Command/Env Policy**                   **100%**   **Complete**        PathPolicy, CommandPolicy, EnvPolicy enforcement, workspace boundary constraints
  **Natural Language Scheduling**               **100%**   **Complete**        Rule-based parser + LLM fallback, rate limited, NlParseAttempt model, cleanup command
  **Onboarding Flow**                           **90%**    **Near-complete**   NEW: First-run onboarding routes, middleware, first-job wizard, completion tracking. Doctor/diagnostics command. Minor polish remaining
  **Telemetry Ledger (append-only)**            **95%**    **Near-complete**   Ingestion, dedupe, schema pinning, ObservabilitySnapshotService built. Full replay tooling verification outstanding
  **Reliability Scoring + Gates**               **100%**   **Complete**        RunClassifier, WeightedReliabilityScorer, GateEvaluator, FailureTaxonomyMapper, AssistedSlaExpiryReclassifier, burst detection, audit trail
  **Cost Governance**                           **100%**   **Complete**        CanonicalCostCalculator, WorkflowBudgetEnforcer, rate-card versioning, warning/block thresholds
  **Escalation Engine**                         **95%**    **Near-complete**   EscalationIncident model, IncidentLifecycleService, DailyAlertSuppressionService, WorkflowGovernanceService. Full outage auto-protect verification pending
  **Projection Replay Architecture**            **65%**    **In Progress**     WorkflowGovernanceSnapshotService, replay-builds route. Concurrency guard and parity validation not fully verified
  **Observability Dashboards**                  **75%**    **Partial**         Monitor dashboard, operator dashboard, system overview, replay builds, diagnostics, logs viewer, audit page, compliance metrics endpoint. Some advanced ingest-lag/backlog dashboards outstanding
  **Multi-provider Webhooks**                   **100%**   **Complete**        Slack, Telegram, Discord, WhatsApp adapters + credential validators. HMAC verification, replay protection, dead-letter queue
  **Chat Flows + Slash Commands**               **100%**   **Complete**        CommandRouter, AgentRouter, ChatIntentParser, ChatActionExecutor, StreamingResponseWriter, CompactionService, ConfirmationManager. Full slash command registry
  **Account Linking + Identity**                **100%**   **Complete**        MessengerIdentityLink with status field, cryptographic token exchange, pairing approve/revoke flow, DM policy enforcement
  **Local Gateway + Sessions**                  **100%**   **Complete**        MessengerGatewayManager, persistent sessions with \--resume, runner selection per connector, context compaction, health dashboard, metrics endpoint
  **DAG Builder + Executor**                    **100%**   **Complete**        DelegationGraphBuilder, DelegationGraphExecutor, DelegateeAssigner, ContractValidator, ContractEnforcer, cycle detection, 25-task limit
  **Verification Pipeline**                     **100%**   **Complete**        3-step: AutomatedCheckStep, AiCriticStep, HumanApprovalStep. Full verification result model
  **Trust Scoring + Recovery**                  **100%**   **Complete**        TrustScoreCalculator with STAR metrics integration, DelegateeMetricsRecomputer, retry/re-delegate/escalate chain
  **Delegation UI (Vue)**                       **85%**    **Near-complete**   CRUD pages, graph builder, delegatee profiles, task approval UI. Feature-gated. DAG visualization improvements and advanced WebSocket updates remaining
  **4-Layer Architecture**                      **100%**   **Complete**        CoreMemoryManager, WorkingMemoryBuffer, HybridRetriever, MemoryFormationPipeline. Multi-provider (Anthropic, OpenAI, custom). Runtime-linked conversation logs
  **Hybrid Retrieval**                          **100%**   **Complete**        BM25 keyword + pgvector semantic + Neo4j graph. MemoryCapabilityResolver for no-API/API/degraded modes. Provider rate limiting
  **Consolidation + Forgetting**                **100%**   **Complete**        ConsolidationService, ForgettingService, WorkingMemorySummarizer, pruning jobs. Scheduled compaction
  **Figma Design Parity**                       **80%**    **Partial**         176 Vue components, design tokens, dark mode. Design system foundation in progress. Screen-by-screen migration ongoing
  **Operator Dashboard**                        **95%**    **Near-complete**   Dashboard, jobs, monitor, tools, messenger, delegation, org, runtime, office, onboarding pages. System overview + diagnostics. 176 Vue pages total
  **Requirements Discovery**                    **90%**    **Near-complete**   Full multi-panel wizard, session APIs, queue workers, Q&A rounds, plan generation, build task execution, adversarial review, Linear integration, export service. 2 runner adapters (Claude + Codex)
  **Code Analysis**                             **80%**    **Partial**         NEW: 10 specialized analyzers, full pipeline (snapshot→plan→execute→validate→report), models, jobs, API routes, wizard UI. Feature-gated. Coverage validation gate. Export service
  **Adversarial Reviewer**                      **100%**   **Complete**        AdversarialReviewerService, ReviewerPayloadGuard, ReviewerPayloadNormalizer, summary + plan gating, bounded retry, shadow mode, feature flag
  **AI Agent Profiles + Hierarchy**             **75%**    **Partial**         NEW: 7 models (OrgAgentProfile, OrgReportingEdge, etc), API routes, web UI pages (dashboard, builder, agents, costs). Service layer partially implemented
  **Ritual Templates + Councils**               **70%**    **Partial**         NEW: OrgRitualTemplate, OrgRitualRun, OrgCouncilTemplate models. RitualToDelegationMapper. Jobs for dispatch/execute/timeout. API + web routes. Cost ledger tracking
  **Test Suite**                                **95%**    **Near-complete**   356 files, 81K LOC. Comprehensive unit/feature/integration tests. UI and data-integrity tests outstanding
  **Feature Flags (DB-backed)**                 **100%**   **Complete**        Laravel Pennant integration, FeatureFlagManager, UI management page, feature-gated routes/middleware
  **STAR Reasoning Integration**                **100%**   **Complete**        STARPreambleGenerator in config/agent.php, per-job override, reasoning capture, trust calibration via delegation
  **Teams / Multi-Tenancy**                     **80%**    **Partial**         NEW: Team model, team\_id on agent + runtime + token tables. Jetstream integration. Workspace isolation at DB level. Client-facing tenant separation needs verification
  **Billing / Subscriptions**                   **75%**    **Partial**         NEW: Cashier integration, subscriptions + subscription\_items tables, metered billing (meter\_id, meter\_event\_name), billing portal route. Pricing tiers and plan management need wiring
  **Credential Vault**                          **80%**    **Partial**         NEW: CredentialVault model, encrypted storage, CredentialsManager, provider validators (Slack/Telegram/Discord/WhatsApp), CRUD API + settings UI. OAuth lifecycle and key rotation outstanding
  **Security & Governance (OpenClaw Parity)**   **70%**    **Partial**         NEW: Phases A+B done. Security audit, tool deny/allow lists, DM policy, pairing approve flow, audit logging, gateway tokens, config schema, log redaction. Phases C-E outstanding
  **Runtime Session + Tool Gateway**            **70%**    **Partial**         NEW: RuntimeSession/Turn/ToolCall/Approval/Artifact/PolicySnapshot models. ToolGateway, ApprovalGate, PolicyEngine, MessengerRuntimeOrchestrator. 7 tool adapters (Fs/Runtime/Web/Browser/Discovery/AgentApi/Mcp). Session-resume working. Full end-to-end verification needed
  **Task Provider Integration**                 **90%**    **Near-complete**   Linear.io integration operational. TaskManagementProviderManager, OAuth flow, sync jobs for tasks + status. Provider driver contract for future integrations
  --------------------------------------------- ---------- ------------------- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

**3. Investor Claims vs Reality**

Cross-referencing specific claims in the investor deck (16 slides) and investor pack v8 against the codebase.

**3.1 Claims That Are Fully Supported**

  --------------------------------------------------------------- -----------------------------------------------------------------------------------------------------------------------------
  **Investor Claim**                                              **Evidence in Codebase**
  Telemetry Ledger: append-only, immutable                        Append-only DB trigger, ingestion service, dedupe, schema pinning
  Reliability Scoring: measurable gate                            RunClassifier, WeightedReliabilityScorer, GateEvaluator, 14d/50-run windows
  Cost Governance: per-workflow budgets                           CanonicalCostCalculator, WorkflowBudgetEnforcer, rate-card versioning
  Approval Gates: human-in-the-loop                               Approval workflow, runner-aware templates, approve/deny handling
  Lifecycle Control: pause/resume/retry/cancel                    Full state machine, graceful termination, retry logic
  Local-First: data stays in infrastructure                       All execution local, no cloud dependency, PostgreSQL/Redis local
  96%+ run success rate                                           Verified: 1651 tests passing, production use with 2,202+ runs
  Run \#2,202 live                                                Confirmed in codebase audit and run history
  Audit log from Run \#1                                          AgentAuditLog immutable trail, full coverage
  Multi-provider messenger (Slack, Telegram, Discord, WhatsApp)   All 4 providers with webhooks, account linking, 7 chat commands, persistent sessions, runtime orchestrator
  Teams and workspace isolation                                   Team model with Jetstream, team\_id on core tables, workspace isolation at DB level
  Client onboarding experience                                    Onboarding routes, middleware, first-job wizard, completion tracking, diagnostics command
  Encrypted credential management                                 CredentialVault with envelope encryption, provider validators, CRUD API, settings UI
  Security governance and audit                                   OpenClaw parity Phases A+B: tool deny/allow lists, DM policy, pairing approve, audit logging, gateway tokens, log redaction
  --------------------------------------------------------------- -----------------------------------------------------------------------------------------------------------------------------

**3.2 Claims That Are Partially Supported or at Risk**

  -------------------------------------------- ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- ----------------
  **Claim**                                    **Current Reality**                                                                                                                                                     **Risk Level**
  **Operator dashboard a COO can read**        Dashboard comprehensive (95%) with 176 Vue pages, but some views remain developer-grade. Simplified operator summary needed.                                            **MEDIUM**
  **Workflow templates for fast onboarding**   Template concept exists (command templates, vertical\_templates config). No sector-specific playbooks authored yet.                                                     **MEDIUM**
  **2-week time to first value**               More plausible now with onboarding flow (90%) and deployment infrastructure. Still requires Gareth to manually configure client environments.                           **MEDIUM**
  **£500-£2,500/mo platform licence**          Billing infrastructure now exists (Cashier, subscriptions, metered billing). Pricing tiers and plan management UI need final wiring.                                    **MEDIUM**
  **890-company named pipeline**               Referenced as external data. Not integrated into platform CRM or outreach tooling.                                                                                      **MEDIUM**
  **Intelligent task delegation (Phase 2)**    Delegation engine fully built (100%). Runtime session layer (70%) adds tool-orchestrated task execution. \'Intelligent\' multi-agent coordination partially realised.   **MEDIUM**
  **agent-ops.com website**                    agent-website project is built (auth, checkout, webhook, portal, GitHub service, 60+ tests). Remaining: production deployment and live domain. Risk reduced from sole P0 blocker.                                                                                    **MEDIUM**
  -------------------------------------------- ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- ----------------

**4. Gaps: Prioritised Build List**

Items ordered by priority for outreach readiness. P0 items block pilot outreach entirely. P1 items will be needed during or immediately after first pilot conversations. P2/P3 items can follow.

  ----------------------- --------------------------------------------- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- ------------------------
  **Priority**            **Gap**                                       **Description**                                                                                                                                                                                     **Effort Est.**
  **P0 - Blocker**        **Marketing Website Deployment (agent-ops.com)**         agent-website project is built (landing, features, pricing, docs, auth, checkout, webhook, customer portal, GitHub access). Remaining: production deployment to agent-ops.com, Satis repo authentication wiring. No longer a build gap; deployment and DNS configuration.     3-5 days
  **P0 - Blocker**        **Client Deployment Packaging**               No installer, setup wizard, or deployment script for client environments. Platform only runs in Gareth\'s local dev environment. Docker Compose stack exists but not client-packaged.               1-2 weeks
  **P1 - Critical**       **Multi-Tenancy Completion (80% built)**      Team model, Jetstream integration, and workspace isolation in place. Remaining: client-facing tenant admin UI, cross-tenant data boundary verification, tenant-scoped API tokens.                   1 week
  **P1 - Critical**       **Billing Completion (75% built)**            Cashier integration, subscription tables, metered billing wired. Remaining: pricing tier configuration, plan management UI, customer billing portal polish, webhook handlers for failed payments.   1 week
  **P1 - Critical**       **Vertical Workflow Templates**               Investor pack promises sector-specific automations (accountancy reporting, MSP health checks, legal deadline tracking). No templates exist. Each pilot will require bespoke build.                  1-2 weeks per vertical
  **P1 - Critical**       **Operator Dashboard Polish**                 Dashboard is comprehensive (95%) but still developer-grade in places. Investor pack promises \'operator dashboard a COO can read.\' Need simplified summary view with business-language metrics.    1 week
  **P2 - Important**      **Replay Architecture Completion**            Projection replay tooling (D21), dual-write adapters, parity validation before activation not verified complete. Needed for production reliability claims.                                          1-2 weeks
  **P2 - Important**      **Client Documentation**                      No operator manual, API docs for clients, or help centre. Clients will need guidance independent of Gareth. Documentation Artisan commands exist but no content authored.                           1-2 weeks
  **P2 - Important**      **Onboarding Polish (90% built)**             Onboarding flow, first-job wizard, and completion tracking implemented. Remaining: guided tour, contextual tooltips, and edge-case handling for different client profiles.                          3-5 days
  **P2 - Important**      **OpenClaw Security Phases C-E**              Phases A+B done (audit, deny/allow lists, DM policy, pairing, gateway tokens, log redaction). Phases C-E outstanding: advanced rate limiting, cross-tenant security rules, compliance reporting.    2-3 weeks
  **P2 - Important**      **Credential Vault Completion (80% built)**   Encrypted storage, provider validators, CRUD API, settings UI in place. Remaining: OAuth token refresh lifecycle, key rotation automation, additional provider drivers.                             1 week
  **P3 - Nice to Have**   **Org Layer Completion (70-75%)**             7 models, services, API routes, web UI, cost ledger, ritual dispatch. Remaining: advanced council workflows, cross-agent orchestration, reporting dashboards.                                       2-3 weeks
  **P3 - Nice to Have**   **Runtime Session End-to-End Verification**   Runtime session layer (70%) has models, tool gateway, 7 adapters, orchestrator. Needs full integration testing and edge-case handling before production use.                                        2-3 weeks
  **P3 - Nice to Have**   **Figma Route Alignment**                     Design paths differ from app routes (/jobs\* vs /agent/jobs\*). Missing lifecycle states in UI. 176 Vue components exist but design token alignment ongoing.                                        3-5 days
  **P3 - Nice to Have**   **SOC2 / Security Audit Track**               Investor pack allocates 20% of funds to infrastructure including SOC2. No current compliance documentation. OpenClaw security parity work is a step toward this.                                    Ongoing
  ----------------------- --------------------------------------------- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- ------------------------

**5. Discovery Briefs: Planned Features Beyond Core**

Eleven discovery briefs exist in /docs/discovery with associated plans. Several have seen significant implementation since last review: Credentials Manager (80%), Messenger Agent Runtime (70%), Code Analysis (80%), and Local-First Messaging (60%). None are required for pilot outreach, but the Messenger Agent Runtime and Credentials Manager are critical for the product roadmap. Three briefs remain unstarted (Runtime Mode, Cognitive Continuity, Atom-of-Thought). The overall discovery brief completion has jumped from \~6% average to \~24% average.

  ----------------------------------------------- ------------- --------- --------------------- ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  **Feature**                                     **Phase**     **%**     **Effort**            **Summary**
  **Agent MCP v1**                                **Phase 1**   **5%**    3-4 weeks             Production-ready Laravel MCP server enabling external clients to execute discovery, interrogation, summary, planning, and build-task workflows. 18 stable tools, cursor-based event delivery, scoped auth. Foundation for all downstream integrations.
  **Credentials Manager**                         **Phase 1**   **80%**   1 week remaining      Credential Vault with encrypted storage now implemented. CredentialsManager service, provider validators (Slack/Telegram/Discord/WhatsApp), CRUD API, settings UI. Remaining: OAuth token refresh lifecycle, key rotation, additional provider drivers (OpenAI, Anthropic, GitHub).
  **Local-First Messaging Completion**            **Phase 1**   **60%**   2-3 weeks             Complete the local-first messenger experience: Slack Socket Mode, Telegram long-polling, Discord gateway. Current implementation is webhook-only despite UI claiming local-first. Mode-aware validation, webhook ingress productisation.
  **Runtime Mode + Multi-Provider**               **Phase 2**   **0%**    4-6 weeks             CLI vs API mode selection per job. Support OpenAI, Anthropic, and custom OpenAI-compatible local endpoints (DGX Spark, Qwen). Provider profiles, capability matrix, tool-call normalisation, connection tests.
  **Cognitive Continuity (AgentKeeper)**          **Phase 2**   **0%**    2-3 weeks             Critical fact continuity lane in memory subsystem. Budget-aware selection (critical facts first), delegation-scoped continuity, reconstruction engine. Guarantees high-priority facts survive retries and handoffs.
  **Atom-of-Thought Reasoning**                   **Phase 2**   **0%**    3-4 weeks             AoT-style decomposition for planning and build-task generation. Atom graph with dependencies, validation checks, parallelisation hints. Shadow/advisory/enforced rollout modes. Complements existing STAR workflow.
  **n8n Workflow Automation**                     **Phase 2**   **0%**    3-4 weeks             Event-driven automation via n8n: outbound signed webhooks on run lifecycle triggers, inbound scoped API for run control, automation templates (Slack alert on fail, Linear incident, digest reports). Automation runs dashboard.
  **Native Research + Grounded Answers**          **Phase 3**   **0%**    6-8 weeks             7-stage research pipeline: query normalisation, retrieval (SERP/SearXNG), content extraction, semantic chunking, hybrid ranking, evidence selection, grounded generation with inline citations. Scheduled research jobs.
  **Messenger Agent Runtime (OpenClaw Parity)**   **Phase 2**   **70%**   3-4 weeks remaining   Runtime session layer now implemented: 6 new models (RuntimeSession/Turn/ToolCall/Approval/Artifact/PolicySnapshot), ToolGateway, ApprovalGate, PolicyEngine, MessengerRuntimeOrchestrator, 7 tool adapters (Fs/Runtime/Web/Browser/Discovery/AgentApi/Mcp), session resume. Remaining: end-to-end integration testing, browser sidecar (agent-browser) integration, advanced policy modes, streaming response polish.
  **Documentation & Tooltip System**              **Phase 2**   **15%**   3-5 weeks remaining   Documentation Artisan commands exist. In-app documentation platform with searchable human docs, inline tooltips, API documentation via Scramble/OpenAPI, Scout/Typesense indexing planned. Coverage tracking config (docs\_coverage.php) in place. Content authoring not started.
  **Code Analysis Tool**                          **Phase 2**   **80%**   1-2 weeks remaining   10 specialized analyzers built, full pipeline (snapshot→plan→execute→validate→report), dedicated models, jobs, API routes, wizard UI. Feature-gated with coverage validation gate. Export service. Remaining: advanced report templates, LLM narrative synthesis post-gate, edge-case handling.
  ----------------------------------------------- ------------- --------- --------------------- ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

**5.1 Discovery Brief Dependency Chain**

Several briefs have sequencing dependencies. The good news: three of the most critical (Credentials Manager, Messenger Agent Runtime, Code Analysis) have seen substantial implementation, significantly de-risking the roadmap.

**Credentials Manager (80% built)** is a prerequisite for Runtime Mode, n8n Integration, and Research subsystem. Only 1 week of work remains (OAuth lifecycle, key rotation, additional provider drivers). Complete this first.

**Agent MCP v1 (5%)** is the foundation for all external integrations and ecosystem engagement. Critical for partner/reseller model promised in investor deck. Still requires full build.

**Local-First Messaging (60%)** is partially shipped but the gap brief reveals the current implementation is webhook-only despite claiming local-first. This is a truth-in-advertising issue that should be resolved before any investor demo that references messenger control.

**Messenger Agent Runtime (70% built)** is the most transformative brief and has seen the biggest jump. 6 models, 7 tool adapters, ToolGateway, ApprovalGate, PolicyEngine, and MessengerRuntimeOrchestrator are all in place. Remaining work (3-4 weeks) is integration testing, browser sidecar, advanced policy modes, and streaming polish. This feature changes the product story from \'agent job scheduler\' to \'conversational agent platform.\'

**Code Analysis Tool (80% built)** has 10 specialized analyzers, full pipeline, wizard UI, and export service. Only 1-2 weeks remaining for report templates and LLM narrative synthesis.

**Runtime Mode + Multi-Provider** enables cost optimisation and local model support. Blocked by Credentials Manager completion.

**n8n Integration** provides the operational automation layer (incident response, alerts, ticketing). High value for pilot clients but blocked by Credentials Manager.

**AoT Reasoning and Cognitive Continuity** improve planning and memory quality respectively. Both are quality-of-output improvements, not capability gaps. Can be deferred safely.

**Documentation & Tooltip System (15%)** has config and Artisan commands in place. Bulk of work is content authoring and search indexing. Not a blocker for outreach but important for client self-service.

**Native Research** is a Phase 3 capability. Largest single-brief effort estimate (6-8 weeks). No dependency on pilot outreach.

**5.2 Discovery Brief Impact on Completion Numbers**

Including the 11 discovery briefs in the overall assessment: the 42 currently-tracked features average \~86% complete. Adding 11 discovery features (now averaging \~24% complete, up from \~6% in the previous review) brings the full-scope platform completion to approximately 73%. This is a significant improvement from the 67% reported previously, driven by the Credentials Manager (0%→80%), Messenger Agent Runtime (0%→70%), and Code Analysis (10%→80%) implementations. The remaining discovery work is approximately 35 weeks (down from \~50 weeks). The discovery briefs remain roadmap items not required for pilot outreach.

**6. Recommended Build Order for Outreach Readiness**

**Phase A: Outreach Blockers (Weeks 1-2)**

**Week 1:** Marketing website (landing page + screenshots + pilot CTA). This unblocks all outbound activity. Sole remaining hard P0.

**Week 1-2:** Client deployment packaging (Docker Compose client bundle, setup wizard, environment configuration). This unblocks pilot delivery.

**Phase B: Pilot Readiness (Weeks 2-4)**

**Week 2:** Multi-tenancy completion (tenant admin UI, cross-tenant boundary verification). Foundation is built at 80%.

**Week 2-3:** Billing completion (pricing tier config, plan management UI, billing portal polish). Cashier integration at 75%.

**Week 3:** Operator dashboard polish (simplified COO-readable summary view). Dashboard at 95%.

**Week 3-4:** First vertical workflow template (accountancy client reporting pack, per the investor pack ICP).

**Phase C: Production Hardening (Weeks 4-6)**

**Week 4:** Onboarding polish (guided tour, contextual tooltips). Onboarding at 90%.

**Week 4-5:** Credential Vault completion + replay architecture verification.

**Week 5:** Client documentation (operator manual, help centre skeleton).

**Week 5-6:** Second vertical template (MSP health checks) + OpenClaw security Phases C-E.

After Phase C, the platform is pilot-ready for the first 3-5 clients as described in the investor pack. Total time to pilot-ready: 4-6 weeks (down from 6-8 weeks in previous review). The P3 items (org layer completion, runtime session verification, Figma alignment, SOC2 track) can proceed in parallel with pilot delivery.

**6.1 Discovery Brief Build Sequence (Post-Outreach)**

After pilot outreach begins, the remaining discovery work should be completed in this order. Total remaining effort: \~35 weeks (down from \~50 weeks).

**1. Credentials Manager Completion** (1 week remaining) --- 80% built. OAuth lifecycle and key rotation. Unblocks Runtime Mode, n8n, and Research.

**2. Code Analysis Tool Completion** (1-2 weeks remaining) --- 80% built. Report templates and LLM narrative synthesis. Quick win.

**3. Local-First Messaging Completion** (2-3 weeks) --- 60% built. Resolve truth-in-advertising gap. Slack Socket Mode + Telegram long-poll.

**4. Messenger Agent Runtime Completion** (3-4 weeks remaining) --- 70% built. Integration testing, browser sidecar, advanced policy modes. The transformative feature that converts messenger into full conversational agent platform.

**5. Agent MCP v1** (3-4 weeks) --- 5% built. Opens ecosystem integration. Required for partner/reseller model.

**6. Runtime Mode + Multi-Provider** (4-6 weeks) --- Not started. Enables cost optimisation, local model support. Depends on Credentials Manager.

**7. n8n Workflow Automation** (3-4 weeks) --- Not started. Operational automation layer. High pilot client value.

**8. Documentation & Tooltip System** (3-5 weeks remaining) --- 15% built. In-app docs, searchable help, API documentation. Reduces support burden.

**9. Cognitive Continuity** (2-3 weeks) --- Not started. Memory quality improvement. Can slot in between larger features.

**10. Atom-of-Thought Reasoning** (3-4 weeks) --- Not started. Planning quality improvement. Shadow mode rollout.

**11. Native Research** (6-8 weeks) --- Not started. Phase 3 capability. Defer until pilot traction confirmed.

**7. What Is Strong (Investor-Ready)**

The following elements are genuinely differentiated and would withstand investor or technical due diligence:

**Technical depth:** 93 migrations, 75 models, 66 services, 356 test files (81K LOC), 176 Vue components, 29 Artisan commands, 36 job classes, 150+ API routes, 37 config files. This is not a prototype --- it is a mature full-stack application.

**Reliability contract:** Deterministic classification (Success/Assisted/Degraded/Failed), weighted scoring, gate evaluation with rolling windows, failure taxonomy, burst detection. Production-grade governance.

**Cost governance:** Immutable rate-card versioning, per-workflow budget enforcement, canonical cost calculation, org-level cost ledger tracking. Investor-grade auditability.

**Telemetry architecture:** Append-only ledger with DB-level mutation guard, active-build read scoping, projection isolation, observability snapshots. The architecture LangSmith does not have for operators.

**Test coverage:** 81,000+ lines of test code across 356 files. Feature tests, unit tests, integration tests, API contract tests. Unusual discipline for a solo founder.

**Messenger integration:** 4 providers with full chat flow parity, persistent sessions with resume, circuit breakers, dead-letter queues, runtime orchestrator, 7 tool adapters. Beyond demo --- approaching conversational agent platform.

**Memory system:** 4-layer hybrid retrieval (BM25 + pgvector + Neo4j knowledge graph), consolidation/forgetting services, runtime-linked conversation logs. Materially ahead of any SME-focused competitor.

**Client infrastructure:** Teams/multi-tenancy (80%), billing/subscriptions (75%), credential vault (80%), onboarding (90%). Major gap-closers built since last review.

**Runtime agent layer:** Full tool gateway with 7 adapters, approval gates, policy engine, session management. This is the foundation for conversational agent platform evolution.

**8. Bottom Line**

+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
| **Built features (42 tracked): \~86% complete**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
|                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| **Full scope incl. 11 discovery briefs (53 items): \~73% complete**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
|                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| **Outreach readiness: \~75%**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
|                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| Time to pilot-ready: 4-6 weeks (P0/P1 gaps only)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
|                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| Time to full conversational agent platform: +3-4 weeks (Runtime 70% built)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
|                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| Remaining discovery roadmap: \~35 weeks (down from \~50 weeks)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
|                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| Significant progress since last review. Client infrastructure (Teams 80%, Billing 75%, Credentials 80%, Onboarding 90%) and the Runtime Agent Layer (70%) have closed major gaps. The agent-website (marketing/licensing) is now built (~90%) with auth, checkout, webhook, customer portal, and GitHub access. Remaining P0: production deployment to agent-ops.com. The go-to-market gap has narrowed substantially --- deploy the website and close P1 items to match every claim in the investor deck. The 11 discovery briefs represent a Phase 2/3 pipeline (\~35 weeks remaining) that deepens the moat. The Messenger Agent Runtime (now 70% built) is approaching the tipping point where the product category shifts from agent job scheduler to conversational agent platform. |
+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+

9\. Platform Distribution Architecture (Revision - 5 March 2026)

An architectural decision has been made to adopt a distribution model modelled on Laravel Nova and Laravel Spark. This fundamentally changes the gap analysis in several areas and introduces new platform requirements. The platform codebase (this repo) is the product. It will be distributed as a private Composer package that clients install into their own Laravel application.

9.1 Distribution Model

The Agent Ops platform will be distributed as a full application via a private Git repository, not as a Composer package or hosted SaaS. Clients clone the repository using credentials tied to their license key, similar to how Laravel Nova provides access via Composer authentication but distributing the entire application. Each installation generates a unique instance fingerprint (based on APP\_KEY hash, database name, APP\_URL domain, and a persistent salt) which is bound to the license key on first validation.

Private Git Repository: A private GitHub repository serves the full application codebase. Clients receive repository access upon license purchase. The distribution model is \"clone and configure\" rather than \"require via Composer\". This preserves the full Laravel application structure and allows clients to customise their installation while receiving updates via git pull.

Install Command: The agent:install Artisan command (restructured from 835 lines) now handles the complete installation flow: (1) Welcome banner with version display, (2) Optional license key input and remote validation, (3) Preflight checks (database, Redis, storage), (4) Database migrations, (5) Initial admin user creation via agent:user sub-command, (6) Optional messenger connector configuration. New flags: \--skip-license, \--license-key, \--skip-migrations, \--skip-health-check. STATUS: IMPLEMENTED.

License Validation: A remote license validation system checks the license key against the licensing API (hosted on agent-website). Results cached for configurable TTL (default 1 hour). License checks bypassed for .test and .local domains and localhost. Per-instance binding via deterministic fingerprint ensures one license = one installation. The InstanceFingerprint class generates a SHA-256 hash from APP\_KEY, database name, APP\_URL domain, and a persistent salt stored in agent\_system\_state. STATUS: IMPLEMENTED. LicenseService, InstanceFingerprint, EnsureLicenseValid middleware, agent:check-license command all built and tested (53 tests passing).

Access Gate: A viewAgent gate controls who can access the agent platform in non-local environments. Defined in AppServiceProvider, defaulting to allow all authenticated users. Clients can override the gate closure to implement custom access policies (e.g., restrict to admin users, specific roles, or IP ranges). STATUS: IMPLEMENTED in AppServiceProvider.

9.2 Three Separate Projects

The overall product ecosystem consists of three separate projects:

1\. Platform Codebase (agent, this repo): The full application, distributed via private Git repository. Contains all agent functionality: runtime, messenger, reliability, telemetry, memory, delegation, billing. Includes the licensing client (LicenseService, InstanceFingerprint, EnsureLicenseValid middleware) that validates against the remote licensing API. Clients clone, configure .env, and run php artisan agent:install.

2\. Marketing & Licensing Website (agent-website, BUILT): Separate Laravel 12 project at `/Users/garethdaine/Code/agent-website` (workspace root, outside agent folder). Public-facing marketing site with landing, features, pricing, docs, and docs/installation pages. Laravel Breeze auth (Blade, dark theme). License management API: POST /api/v1/license/validate, GET /api/v1/licenses (Sanctum). Per-instance license binding with fingerprint verification. Stripe checkout flow (CheckoutController, success/cancel pages). Stripe webhook handler creates License on checkout.session.completed, suspends on customer.subscription.deleted. GitHubAccessService for invite/revoke collaborators (config/licensing.php: plans, Stripe price IDs, GitHub settings). Customer license portal: dashboard, license detail, validation history, billing page. Marketing nav wired to auth (Login/Register/Dashboard/Logout). Pricing buttons linked to checkout routes. User model has github_username. License, LicenseValidation models. STATUS: BUILT with 60+ tests passing (Checkout, Webhook, Portal, MarketingPages, GitHubAccessService, LicenseValidation, Auth, Profile).

**3. Client Documentation (TBD, may be part of website):** Public documentation site for clients. Installation guide, configuration reference, API docs, operator manual. May be part of the marketing website or a separate docs site. Status: Partial (Scramble OpenAPI exists in platform).

9.3 Revised Gap Classification

The following changes apply to Section 4 (Gaps: Prioritised Build List):

**RECLASSIFIED - Marketing Website:** Previously listed as P0 platform blocker (1 week effort). Now a separate project (agent-website) at workspace root. Build complete (~90%); remaining work is deployment to agent-ops.com and Satis repo authentication wiring (3-5 days). No longer a build gap in the platform codebase.

REMOVED - Package Restructuring: The decision to distribute as a full application (not a Composer package) eliminates the need for package restructuring. The application is distributed as-is via private Git repository. No service provider publishing, no package discovery, no asset publishing required. This removes approximately 2-3 weeks of estimated work from the build order.

COMPLETED - Licensing & Distribution System: The per-instance licensing system is fully implemented. Includes: (a) License configuration in config/agent.php with env-driven key, validation URL, cache TTL, and bypass domains. (b) InstanceFingerprint class with deterministic hash generation and persistent salt. (c) LicenseService with remote validation, response caching, bypass logic for development domains, and graceful degradation. (d) EnsureLicenseValid middleware for web (403 view) and API (JSON error) routes. (e) agent:check-license Artisan command. (f) viewAgent gate in AppServiceProvider. All components have comprehensive test coverage.

COMPLETED - Install Wizard Restructure: The agent:install command has been restructured to follow the licensing-first flow: (1) License key input and validation, (2) Preflight checks, (3) Migrations, (4) Admin user creation via agent:user, (5) Optional messenger connector setup. New supporting commands: agent:user (create admin with personal team), agent:update (post-pull migrations, cache clear, license re-validation). Hardcoded paths removed from config/agent.php, now fully env-driven. Version tracking added (config agent.version). All commands tested.

**REVISED - Client Deployment:** Previously described as Docker Compose packaging. In the Nova/Spark model, clients run the platform in their own Laravel app. Client deployment becomes: composer require + agent:install, environment configuration (.env), queue worker setup (Horizon), WebSocket setup (Reverb). Docker Compose remains an optional convenience for infrastructure dependencies (Postgres, Redis).

**UNCHANGED - Billing (P1):** Billing remains a P1 gap. The platform-side Cashier integration (75%) handles subscription management within the installed app. The licensing portal (website project) handles license purchases and Satis authentication separately. These are complementary but distinct billing concerns.

9.4 Revised Build Order

The recommended build order from Section 6 is revised:

Phase A: Distribution Foundation (Weeks 1-2) \-- STATUS: COMPLETED

Week 1: Licensing system implementation. License key validation, remote check API, InstanceFingerprint generation, LicenseService with caching and bypass, EnsureLicenseValid middleware, agent:check-license command, viewAgent gate. All built with comprehensive test coverage. STATUS: COMPLETED.

Week 1-2: Install wizard restructure. agent:install restructured for licensing-first flow with admin user creation (agent:user), config cleanup (env-driven paths), agent:update command for post-pull updates, and version tracking. Messenger setup retained as optional sub-step. STATUS: COMPLETED.

Week 2: Agent-website scaffolding. Laravel 12 project with license validation API (POST /api/v1/license/validate), License model with per-instance binding, Stripe Cashier, marketing pages (home, features, pricing, docs). STATUS: SCAFFOLDED (superseded by Phase B Week 3 build).

**Phase B: Pilot Readiness (Weeks 3-5) \-- Week 3 LARGELY COMPLETED**

Week 3: Marketing/licensing website MVP (agent-website project). Landing page, license purchase flow (Stripe checkout), Stripe webhook (license create/suspend), customer portal (dashboard, license detail, validation history, billing), GitHub repo access service (invite/revoke). STATUS: LARGELY COMPLETED. Remaining: Satis repo authentication wiring, production deployment to agent-ops.com.

Week 3-4: Multi-tenancy completion + billing completion (as previously scoped in Section 6).

Week 4-5: First vertical workflow template + operator dashboard polish.

**Phase C: Production Hardening (Weeks 5-7)**

Unchanged from Section 6 Phase C, with the addition of end-to-end install testing on a clean Laravel application to verify the full composer require -\> agent:install -\> configure -\> run workflow.

9.5 Reference Implementations

The following reference implementations inform the distribution design:

**Laravel Nova (nova.laravel.com):** Private Satis repo with HTTP basic auth (email + license key). nova:install publishes provider, config, assets. License validation via remote API with 1-hour cache. viewNova gate for access control. A working Nova v5 installation exists in the Zilliqa project for reference: \~/Library/Mobile Documents/com\~apple\~CloudDocs/external/zilliqa/vendor/laravel/nova/

**Laravel Spark (spark.laravel.com):** Same Satis pattern. spark:install publishes config/spark.php with plan definitions. Supports Stripe and Paddle. Self-contained billing portal for plan management, payment methods, invoices.

9.6 Impact on Completion Numbers

Adding the licensing/distribution requirements as tracked features: Package Restructuring (REMOVED - not applicable with full app distribution), Licensing System (100% - fully implemented), Install Wizard Revision (100% - restructured with licensing-first flow), Agent Website (~90% - BUILT with auth, checkout, webhook, customer portal, GitHub access service, license validation API; remaining: Satis repo auth wiring, production deployment to agent-ops.com).

Revised platform completion including distribution requirements: approximately 90% (up from 88%, as agent-website marketing/licensing MVP is now largely complete). The remaining gaps are: agent-website deployment and Satis wiring, multi-tenancy polish, vertical workflow templates, and operator dashboard. Full-scope including discovery briefs: approximately 75%. Time to pilot-ready: 2-4 weeks (improved from 3-5 weeks due to agent-website build completion).
