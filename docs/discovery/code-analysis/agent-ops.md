# Code Analysis Report

Session: 3
Runner: codex
Report hash: 7ba713bf33d6cd97a04ffed361fa7a9e5931815b6b49b8d11cf815595423b575
Generated at: 2026-03-03T19:37:29+00:00

## Full Repository Report

## Final Comprehensive Repository Report

### 1) Inspection Method and Evidence Scope
This report is based on direct repository inspection in `/Users/garethdaine/Code/agent` using file discovery/search/reads and Laravel route introspection.

1. Dependency manifests and runtime/build configs were inspected in [composer.json](/Users/garethdaine/Code/agent/composer.json), [composer.lock](/Users/garethdaine/Code/agent/composer.lock), [package.json](/Users/garethdaine/Code/agent/package.json), [package-lock.json](/Users/garethdaine/Code/agent/package-lock.json), [.env.example](/Users/garethdaine/Code/agent/.env.example).
2. Architecture composition and runtime wiring were inspected in [bootstrap/app.php](/Users/garethdaine/Code/agent/bootstrap/app.php), [bootstrap/providers.php](/Users/garethdaine/Code/agent/bootstrap/providers.php), [app/Providers/AppServiceProvider.php](/Users/garethdaine/Code/agent/app/Providers/AppServiceProvider.php).
3. Route surfaces were inspected in [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php), [routes/web.php](/Users/garethdaine/Code/agent/routes/web.php), [routes/channels.php](/Users/garethdaine/Code/agent/routes/channels.php), [routes/console.php](/Users/garethdaine/Code/agent/routes/console.php), and validated via `php artisan route:list --json`.
4. Domain features, service layer, and async workflows were inspected under [app/Support](/Users/garethdaine/Code/agent/app/Support), [app/Jobs](/Users/garethdaine/Code/agent/app/Jobs), [app/Events](/Users/garethdaine/Code/agent/app/Events), [app/Listeners](/Users/garethdaine/Code/agent/app/Listeners), [app/Http/Controllers/Api/V1](/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1).
5. Data model and schema were inspected in [app/Models](/Users/garethdaine/Code/agent/app/Models) and [database/migrations](/Users/garethdaine/Code/agent/database/migrations).
6. Code quality/testing posture was inspected in [.editorconfig](/Users/garethdaine/Code/agent/.editorconfig), [phpunit.xml](/Users/garethdaine/Code/agent/phpunit.xml), [vitest.config.js](/Users/garethdaine/Code/agent/vitest.config.js), [playwright.config.ts](/Users/garethdaine/Code/agent/playwright.config.ts), [.github/workflows/docs-deploy-sync.yml](/Users/garethdaine/Code/agent/.github/workflows/docs-deploy-sync.yml), [.githooks/pre-commit](/Users/garethdaine/Code/agent/.githooks/pre-commit), [scripts/docs/sync.sh](/Users/garethdaine/Code/agent/scripts/docs/sync.sh), [tests](/Users/garethdaine/Code/agent/tests).

### 2) Tech Stack and Dependencies

#### Backend stack
- Language/runtime: PHP `^8.2`.
- Framework: Laravel `^12.0`.
- Auth/account/security: Jetstream, Fortify, Sanctum.
- Queue/ops: Horizon.
- Realtime: Reverb.
- Search: Scout + Typesense.
- Feature flags package: Pennant.
- Additional integrations: Neo4j PHP client, PhpSpreadsheet, Ratchet Pawl, Spatie Backup, Ziggy.

Evidence: [composer.json](/Users/garethdaine/Code/agent/composer.json)

#### Frontend stack
- Vue 3 + Inertia (`@inertiajs/vue3`) with SSR entry.
- Vite build chain (`vite`, `laravel-vite-plugin`, `@vitejs/plugin-vue`).
- Tailwind packages (`@tailwindcss/forms`, `@tailwindcss/typography`, `@tailwindcss/vite`).
- Realtime/browser API support (`laravel-echo`, `pusher-js`, `axios`).
- Markdown/editor stack (`@crazydos/vue-markdown`, TipTap packages, `remark-gfm`).

Evidence: [package.json](/Users/garethdaine/Code/agent/package.json), [resources/js/app.js](/Users/garethdaine/Code/agent/resources/js/app.js), [resources/js/ssr.js](/Users/garethdaine/Code/agent/resources/js/ssr.js), [vite.config.js](/Users/garethdaine/Code/agent/vite.config.js)

#### Direct dependency inventory
- Composer `require`: 16 packages.
- Composer `require-dev`: 7 packages.
- npm `dependencies`: 8 packages.
- npm `devDependencies`: 20 packages.
- Lockfiles present for both ecosystems.

Evidence: [composer.json](/Users/garethdaine/Code/agent/composer.json), [composer.lock](/Users/garethdaine/Code/agent/composer.lock), [package.json](/Users/garethdaine/Code/agent/package.json), [package-lock.json](/Users/garethdaine/Code/agent/package-lock.json)

### 3) Codebase Structure and Scale

Observed structural counts from direct file inventory:
- Models: 62 in [app/Models](/Users/garethdaine/Code/agent/app/Models)
- Jobs: 32 in [app/Jobs](/Users/garethdaine/Code/agent/app/Jobs)
- Events: 22 in [app/Events](/Users/garethdaine/Code/agent/app/Events)
- Listeners: 4 in [app/Listeners](/Users/garethdaine/Code/agent/app/Listeners)
- API V1 controllers: 34 in [app/Http/Controllers/Api/V1](/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1)
- Support/service-layer files: 195 in [app/Support](/Users/garethdaine/Code/agent/app/Support)
- Console commands: 22 in [app/Console/Commands](/Users/garethdaine/Code/agent/app/Console/Commands)
- Migrations: 89 in [database/migrations](/Users/garethdaine/Code/agent/database/migrations)
- Vue pages: 65 in [resources/js/Pages](/Users/garethdaine/Code/agent/resources/js/Pages)
- Vue components: 67 in [resources/js/Components](/Users/garethdaine/Code/agent/resources/js/Components)

Composition is a Laravel modular monolith with domain partitioning by folders and corresponding route/controller/service/job/model clusters.

### 4) Feature Surface (Observed Domains)

#### Agent scheduling and run orchestration
- Job CRUD, run-now, stop/retry, run event streaming, dashboard/health endpoints.
- Runtime guardrails enforce command/path/env constraints and cron validation.

Evidence: [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php), [app/Models/AgentJob.php](/Users/garethdaine/Code/agent/app/Models/AgentJob.php), [app/Models/AgentJobRun.php](/Users/garethdaine/Code/agent/app/Models/AgentJobRun.php), [app/Jobs/ExecuteAgentRunJob.php](/Users/garethdaine/Code/agent/app/Jobs/ExecuteAgentRunJob.php), [app/Support/Agent/DispatchDueService.php](/Users/garethdaine/Code/agent/app/Support/Agent/DispatchDueService.php), [app/Support/Agent/CommandPolicy.php](/Users/garethdaine/Code/agent/app/Support/Agent/CommandPolicy.php), [app/Support/Agent/PathPolicy.php](/Users/garethdaine/Code/agent/app/Support/Agent/PathPolicy.php), [app/Support/Agent/EnvPolicy.php](/Users/garethdaine/Code/agent/app/Support/Agent/EnvPolicy.php), [app/Rules/NumericCronExpression.php](/Users/garethdaine/Code/agent/app/Rules/NumericCronExpression.php)

#### Interrogation/discovery workflow
- Multi-phase session lifecycle, eventing, summaries/plans/build tasks, provider/tech-stack settings, task sync endpoints.

Evidence: [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php), [app/Http/Controllers/Api/V1/InterrogationSessionController.php](/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/InterrogationSessionController.php), [app/Jobs/ExecuteInterrogationDiscoveryJob.php](/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationDiscoveryJob.php), [app/Jobs/ExecuteInterrogationPlanJob.php](/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php), [app/Jobs/ExecuteInterrogationBuildJob.php](/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationBuildJob.php), [app/Jobs/ExecuteInterrogationSummaryJob.php](/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php), [app/Support/Interrogation/AdapterFactory.php](/Users/garethdaine/Code/agent/app/Support/Interrogation/AdapterFactory.php)

#### Messenger control plane
- Connector management, webhook ingestion, chat sessions/actions, health and metrics surfaces, reliability/dedup/dead-letter handling.

Evidence: [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php), [config/messenger.php](/Users/garethdaine/Code/agent/config/messenger.php), [app/Http/Middleware/Messenger/VerifyWebhookSignature.php](/Users/garethdaine/Code/agent/app/Http/Middleware/Messenger/VerifyWebhookSignature.php), [app/Http/Middleware/Messenger/ReplayProtection.php](/Users/garethdaine/Code/agent/app/Http/Middleware/Messenger/ReplayProtection.php), [app/Jobs/Messenger/ProcessInboundMessage.php](/Users/garethdaine/Code/agent/app/Jobs/Messenger/ProcessInboundMessage.php), [app/Models/MessengerEventDeduplication.php](/Users/garethdaine/Code/agent/app/Models/MessengerEventDeduplication.php), [app/Models/MessengerDeadLetter.php](/Users/garethdaine/Code/agent/app/Models/MessengerDeadLetter.php)

#### Memory subsystem
- Memory settings/core blocks/retrieval/diagnostics with feature gating and hybrid retrieval behavior.

Evidence: [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php), [config/memory.php](/Users/garethdaine/Code/agent/config/memory.php), [app/Providers/MemoryServiceProvider.php](/Users/garethdaine/Code/agent/app/Providers/MemoryServiceProvider.php), [app/Support/Memory/MemoryCapabilityResolver.php](/Users/garethdaine/Code/agent/app/Support/Memory/MemoryCapabilityResolver.php), [app/Support/Memory/HybridRetriever.php](/Users/garethdaine/Code/agent/app/Support/Memory/HybridRetriever.php)

#### Delegation engine
- Delegatee profiles/capabilities/metrics, delegation graph/task orchestration, dependency and verification pipeline.

Evidence: [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php), [config/delegation.php](/Users/garethdaine/Code/agent/config/delegation.php), [app/Support/Delegation/DelegationGraphExecutor.php](/Users/garethdaine/Code/agent/app/Support/Delegation/DelegationGraphExecutor.php), [app/Support/Delegation/VerificationPipeline.php](/Users/garethdaine/Code/agent/app/Support/Delegation/VerificationPipeline.php)

#### Org layer
- Org agents, reporting edges, ritual templates/runs, councils, cost ledger, escalations.

Evidence: [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php), [app/Support/Org/OrgRitualRunService.php](/Users/garethdaine/Code/agent/app/Support/Org/OrgRitualRunService.php), [app/Support/Org/Synthesis/WeightedSynthesisStrategy.php](/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/WeightedSynthesisStrategy.php)

#### Repo analysis and documentation center
- Repo analysis sessions/tasks/artifacts/reports with snapshot/task-graph orchestration and optional AI-runner surfaces.
- Documentation ingestion/search/fragments/coverage/diagnostics plus deploy-time docs sync workflow.

Evidence: [config/repo_analysis.php](/Users/garethdaine/Code/agent/config/repo_analysis.php), [app/Support/RepoAnalysis/SnapshotBuilder.php](/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/SnapshotBuilder.php), [app/Support/RepoAnalysis/Analyzers/AnalyzerRegistry.php](/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/Analyzers/AnalyzerRegistry.php), [app/Support/RepoAnalysis/TaskGraphBuilder.php](/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/TaskGraphBuilder.php), [app/Support/RepoAnalysis/RepoAnalysisExecutionOrchestrator.php](/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/RepoAnalysisExecutionOrchestrator.php), [config/documentation.php](/Users/garethdaine/Code/agent/config/documentation.php), [app/Support/Documentation/DocsSyncService.php](/Users/garethdaine/Code/agent/app/Support/Documentation/DocsSyncService.php), [app/Support/Documentation/DocsSearchService.php](/Users/garethdaine/Code/agent/app/Support/Documentation/DocsSearchService.php), [.github/workflows/docs-deploy-sync.yml](/Users/garethdaine/Code/agent/.github/workflows/docs-deploy-sync.yml)

### 5) Data Model and Migrations

#### Overall schema footprint
- 62 Eloquent models and 89 migrations.

Evidence: [app/Models](/Users/garethdaine/Code/agent/app/Models), [database/migrations](/Users/garethdaine/Code/agent/database/migrations)

#### Core schema areas (representative evidence)
- Agent runtime tables: [2026_02_12_020511_create_agent_jobs_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_12_020511_create_agent_jobs_table.php), [2026_02_12_020512_create_agent_job_runs_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_12_020512_create_agent_job_runs_table.php), [2026_02_12_020513_create_agent_run_events_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_12_020513_create_agent_run_events_table.php)
- Agent constraints/checks/indexes: [2026_02_12_021800_add_agent_constraints.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_12_021800_add_agent_constraints.php)
- Interrogation: [2026_02_13_100000_create_interrogation_sessions_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_13_100000_create_interrogation_sessions_table.php), [2026_02_13_100001_create_interrogation_events_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_13_100001_create_interrogation_events_table.php), [2026_02_16_000001_create_interrogation_build_tasks_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_16_000001_create_interrogation_build_tasks_table.php)
- Delegation: [2026_02_17_200004_create_delegation_graphs_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_17_200004_create_delegation_graphs_table.php), [2026_02_17_200005_create_delegation_tasks_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_17_200005_create_delegation_tasks_table.php), [2026_02_17_200006_create_delegation_task_dependencies_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_17_200006_create_delegation_task_dependencies_table.php)
- Messenger/chat: [2026_02_20_150000_create_connector_accounts_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_20_150000_create_connector_accounts_table.php), [2026_02_20_150001_create_chat_sessions_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_20_150001_create_chat_sessions_table.php), [2026_02_21_100000_create_messenger_event_deduplication_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_21_100000_create_messenger_event_deduplication_table.php), [2026_02_28_100000_create_messenger_dead_letters_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_28_100000_create_messenger_dead_letters_table.php)
- Memory: [2026_02_28_120000_create_memory_settings_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_28_120000_create_memory_settings_table.php), [2026_02_28_120100_create_memory_core_blocks_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_28_120100_create_memory_core_blocks_table.php), [2026_02_28_120200_create_memory_embeddings_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_02_28_120200_create_memory_embeddings_table.php)
- Org: [2026_03_01_110000_create_org_agent_profiles_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_03_01_110000_create_org_agent_profiles_table.php), [2026_03_01_120001_create_org_ritual_runs_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_03_01_120001_create_org_ritual_runs_table.php), [2026_03_01_140001_create_org_escalations_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_03_01_140001_create_org_escalations_table.php)
- Docs: [2026_03_02_150000_create_documentation_entries_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_03_02_150000_create_documentation_entries_table.php), [2026_03_02_150100_create_documentation_fragments_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_03_02_150100_create_documentation_fragments_table.php)
- Repo analysis: [2026_03_02_190000_create_repo_analysis_sessions_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_03_02_190000_create_repo_analysis_sessions_table.php), [2026_03_02_190200_create_repo_analysis_tasks_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_03_02_190200_create_repo_analysis_tasks_table.php), [2026_03_02_190400_create_repo_analysis_reports_table.php](/Users/garethdaine/Code/agent/database/migrations/2026_03_02_190400_create_repo_analysis_reports_table.php)

### 6) Routes, Broadcast Channels, and Scheduled Workflows

#### API and web route footprint
- API route declarations in [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php): 181 observed route lines.
- Web route declarations in [routes/web.php](/Users/garethdaine/Code/agent/routes/web.php): 54 observed route lines.
- `php artisan route:list --path=agent/api/v1 --json` returned 179 API routes.

Largest API clusters (first path segment under `/agent/api/v1`):
- `interrogation`: 45
- `org`: 26
- `code-analysis`: 21
- `delegation`: 21
- `memory`: 12
- `jobs`: 9
- `messenger`: 8
- `chat`: 8

Evidence: [routes/api.php](/Users/garethdaine/Code/agent/routes/api.php), [routes/web.php](/Users/garethdaine/Code/agent/routes/web.php)

#### Broadcast channels
- 7 channels declared.
- Includes user-private, interrogation, delegation graph/user, code-analysis session, memory diagnostics.

Evidence: [routes/channels.php](/Users/garethdaine/Code/agent/routes/channels.php)

#### Scheduler
- 13 scheduled actions observed in console route schedule.
- Includes dispatch/prune/backup, messenger pruning, delegation reconciliation, NL parse cleanup, memory consolidate/prune, repo-analysis artifact prune, org ritual dispatch.

Evidence: [routes/console.php](/Users/garethdaine/Code/agent/routes/console.php)

### 7) Design Patterns and Architectural Conventions

Observed recurrent patterns:
1. Layered modular monolith (routes/controllers/models/support/jobs/events/listeners) with domain folder partitioning.
2. Dependency injection and service-provider composition for bindings, policies, event subscribers, and rate limiters.
3. Strategy/adapter/factory usage for interrogation runners, org synthesis modes, memory/provider selection, task provider drivers.
4. Event-subscriber coordination for delegation/docs telemetry.
5. Pipeline/workflow orchestration for verification, ingestion, memory formation, and repo-analysis execution.
6. State-transition services enforcing bounded lifecycle transitions.
7. Middleware pipeline for feature gates, API version header, and webhook security controls.

Evidence: [bootstrap/app.php](/Users/garethdaine/Code/agent/bootstrap/app.php), [app/Providers/AppServiceProvider.php](/Users/garethdaine/Code/agent/app/Providers/AppServiceProvider.php), [app/Support/Interrogation/AdapterFactory.php](/Users/garethdaine/Code/agent/app/Support/Interrogation/AdapterFactory.php), [app/Support/Org/Synthesis](/Users/garethdaine/Code/agent/app/Support/Org/Synthesis), [app/Support/Delegation/VerificationPipeline.php](/Users/garethdaine/Code/agent/app/Support/Delegation/VerificationPipeline.php), [app/Support/RepoAnalysis/RepoAnalysisExecutionOrchestrator.php](/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/RepoAnalysisExecutionOrchestrator.php), [app/Listeners/DelegationCoordinator.php](/Users/garethdaine/Code/agent/app/Listeners/DelegationCoordinator.php)

### 8) Coding Standards and Code Quality Posture

#### Standards/tooling present
- Baseline formatting: [.editorconfig](/Users/garethdaine/Code/agent/.editorconfig)
- Laravel Pint dependency present (manual usage expected).
- Quality/test commands exist in manifests (`composer test`, `npm run test:unit`, `npm run test:e2e`).

Evidence: [composer.json](/Users/garethdaine/Code/agent/composer.json), [package.json](/Users/garethdaine/Code/agent/package.json)

#### Enforcement posture
- CI: only docs deploy sync workflow detected; no repository CI workflow found that runs PHPUnit/Vitest/Playwright/Pint/static analysis by default.
- Pre-commit hook runs docs sync script; commit blocking is optional unless strict flag is enabled.

Evidence: [.github/workflows/docs-deploy-sync.yml](/Users/garethdaine/Code/agent/.github/workflows/docs-deploy-sync.yml), [.githooks/pre-commit](/Users/garethdaine/Code/agent/.githooks/pre-commit), [scripts/docs/sync.sh](/Users/garethdaine/Code/agent/scripts/docs/sync.sh)

#### Not observed in repository configs
- No committed ESLint/Prettier config surfaced.
- No committed PHPStan/Psalm config surfaced.

### 9) Test Surface and Coverage Signals

#### Test inventory
- Total tests directory files: 291.
- PHP tests: 271.
- Suite split: Unit 135, Feature 134, Integration 2.
- Vitest specs: 3.
- Playwright specs: 2 (+ auth setup file).

Evidence: [tests](/Users/garethdaine/Code/agent/tests), [phpunit.xml](/Users/garethdaine/Code/agent/phpunit.xml), [resources/js/Components/__tests__/HelpHint.spec.ts](/Users/garethdaine/Code/agent/resources/js/Components/__tests__/HelpHint.spec.ts), [resources/js/Pages/Tools/CodeAnalysis/__tests__/eventStream.spec.ts](/Users/garethdaine/Code/agent/resources/js/Pages/Tools/CodeAnalysis/__tests__/eventStream.spec.ts), [tests/e2e/monitor.spec.ts](/Users/garethdaine/Code/agent/tests/e2e/monitor.spec.ts), [tests/e2e/wizard.spec.ts](/Users/garethdaine/Code/agent/tests/e2e/wizard.spec.ts)

#### Reliability/testing signals
- `RefreshDatabase` is widely used (high isolation signal).
- `markTestSkipped(` occurrences: 22.
- `test.skip(` occurrences: 2.
- Last Playwright run artifact indicates failure status.

Evidence: [tests/TestCase.php](/Users/garethdaine/Code/agent/tests/TestCase.php), [test-results/.last-run.json](/Users/garethdaine/Code/agent/test-results/.last-run.json), [playwright.config.ts](/Users/garethdaine/Code/agent/playwright.config.ts)

### 10) Key Risks (Prioritized)

#### P0 (highest)
1. CI quality-gate gap.
- Risk: functional regressions can merge without mandatory test/lint/static-analysis runs.
- Evidence: only docs workflow in [.github/workflows/docs-deploy-sync.yml](/Users/garethdaine/Code/agent/.github/workflows/docs-deploy-sync.yml).

2. Operational risk from async breadth and scheduler density.
- Risk: many queues/supervisors/scheduled jobs create high coordination and failure-mode complexity.
- Evidence: [config/horizon.php](/Users/garethdaine/Code/agent/config/horizon.php), [config/queue.php](/Users/garethdaine/Code/agent/config/queue.php), [routes/console.php](/Users/garethdaine/Code/agent/routes/console.php).

#### P1
3. Complexity concentration in very large orchestration files.
- Risk: higher defect probability and slower safe change velocity.
- Evidence: [app/Http/Controllers/Api/V1/InterrogationSessionController.php](/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/InterrogationSessionController.php), [app/Support/RepoAnalysis/ReportComposer.php](/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/ReportComposer.php), [app/Jobs/ExecuteInterrogationRoundJob.php](/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationRoundJob.php), [app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php](/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php).

4. Messenger policy/validation duplication.
- Risk: drift/inconsistent authorization behavior due to parallel validator implementations.
- Evidence: [app/Services/Messenger/ChatActionPolicyValidator.php](/Users/garethdaine/Code/agent/app/Services/Messenger/ChatActionPolicyValidator.php), [app/Messenger/Validation/ChatActionPolicyValidator.php](/Users/garethdaine/Code/agent/app/Messenger/Validation/ChatActionPolicyValidator.php).

5. Uneven frontend automated test depth.
- Risk: UI regressions in complex pages (Discovery/Code Analysis/Messenger/Memory) may escape until runtime.
- Evidence: 3 Vitest specs and 2 Playwright spec files in [resources/js](/Users/garethdaine/Code/agent/resources/js) and [tests/e2e](/Users/garethdaine/Code/agent/tests/e2e).

#### P2
6. Route-layer business logic in web route closures.
- Risk: reduced testability and architectural drift from controller/service conventions.
- Evidence: code-analysis closures in [routes/web.php](/Users/garethdaine/Code/agent/routes/web.php).

7. Non-blocking pre-commit default for docs sync.
- Risk: local hygiene checks can be bypassed unintentionally.
- Evidence: [scripts/docs/sync.sh](/Users/garethdaine/Code/agent/scripts/docs/sync.sh), [.githooks/pre-commit](/Users/garethdaine/Code/agent/.githooks/pre-commit).

### 11) Prioritized Recommendations

#### Priority 0 (immediate)
1. Add a required CI workflow for backend + frontend quality gates.
- Include: `composer test`, `npm run test:unit`, targeted Playwright smoke, Pint check.
- Why: closes the largest regression path.
- Start points: [composer.json](/Users/garethdaine/Code/agent/composer.json), [package.json](/Users/garethdaine/Code/agent/package.json), [.github/workflows](/Users/garethdaine/Code/agent/.github/workflows).

2. Add queue/scheduler health assertions in CI smoke or scheduled workflow.
- Why: async orchestration is core and high-risk.
- Start points: [config/horizon.php](/Users/garethdaine/Code/agent/config/horizon.php), [routes/console.php](/Users/garethdaine/Code/agent/routes/console.php), [tests/Feature/AgentQueueRuntimeConfigTest.php](/Users/garethdaine/Code/agent/tests/Feature/AgentQueueRuntimeConfigTest.php).

#### Priority 1
3. Decompose oversized orchestration/controller files into service actions.
- Targets: interrogation session controller, repo-analysis controller, interrogation round job, report composer.
- Why: reduces blast radius and improves unit-test focus.
- Files: [app/Http/Controllers/Api/V1/InterrogationSessionController.php](/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/InterrogationSessionController.php), [app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php](/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/RepoAnalysisSessionController.php), [app/Jobs/ExecuteInterrogationRoundJob.php](/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationRoundJob.php), [app/Support/RepoAnalysis/ReportComposer.php](/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/ReportComposer.php).

4. Unify messenger action-policy validation into one canonical implementation.
- Why: prevents policy drift and simplifies maintenance.
- Files: [app/Services/Messenger/ChatActionPolicyValidator.php](/Users/garethdaine/Code/agent/app/Services/Messenger/ChatActionPolicyValidator.php), [app/Messenger/Validation/ChatActionPolicyValidator.php](/Users/garethdaine/Code/agent/app/Messenger/Validation/ChatActionPolicyValidator.php).

5. Expand frontend automated tests for highest-risk pages.
- Add Vitest coverage for forms/state transitions and Playwright flows for key tools pages.
- Start points: [resources/js/Pages/Tools/Discovery/Wizard.vue](/Users/garethdaine/Code/agent/resources/js/Pages/Tools/Discovery/Wizard.vue), [resources/js/Pages/Tools/CodeAnalysis/Wizard.vue](/Users/garethdaine/Code/agent/resources/js/Pages/Tools/CodeAnalysis/Wizard.vue), [resources/js/Pages/Tools/Messenger/Index.vue](/Users/garethdaine/Code/agent/resources/js/Pages/Tools/Messenger/Index.vue), [resources/js/Pages/Tools/Memory/Settings.vue](/Users/garethdaine/Code/agent/resources/js/Pages/Tools/Memory/Settings.vue).

#### Priority 2
6. Move route closure logic for code-analysis web screens into dedicated controllers.
- Why: improves boundary consistency and testability.
- File: [routes/web.php](/Users/garethdaine/Code/agent/routes/web.php).

7. Decide and codify local/CI strictness for docs hook behavior.
- Why: avoids ambiguity in enforcement and contributor expectations.
- Files: [.githooks/pre-commit](/Users/garethdaine/Code/agent/.githooks/pre-commit), [scripts/docs/sync.sh](/Users/garethdaine/Code/agent/scripts/docs/sync.sh), [docs/README.md](/Users/garethdaine/Code/agent/docs/README.md).

### 12) Assumptions and Uncertainty
1. This report describes repository-implemented capabilities and structure, not validated production runtime behavior.
2. Route and feature counts are snapshot-based and can change as code evolves.
3. “Not observed” for lint/static-analysis configs means not found in this snapshot, not impossible in developer-local/private tooling.
4. Coverage gap signals are based on inspected test inventory and structure, not mutation testing or full runtime path instrumentation.

## Repository Profile

### Overview

- Project directory: `/Users/garethdaine/Code/agent`
- Snapshot hash: `64be0495f440a6b08633bec0bd085f1895448660a550ad512a30c0aa1a0b762a`
- Files analyzed: 2745
- Inferred stack: `Frontend module tree`, `Inertia.js (Laravel adapter)`, `Inertia.js + Vue 3`, `JavaScript Testing Framework`, `JavaScript/TypeScript Tooling`, `Laravel`, `Laravel Horizon`, `Laravel Reverb`, `Laravel Sanctum`, `Node package ecosystem`, `PHP`, `PHP Testing Framework`, `PHP package ecosystem`, `Route-driven HTTP surface`, `Tailwind CSS`, `Vite`, `Vue`
- Language distribution: PHP (1016), Markdown (855), JavaScript (239), Vue (133), Python (57), YAML (52), JSON (41), CSS (13), TypeScript (7), Blade (2)

### Dependencies

- PHP runtime dependencies: 16
  - sample: `inertiajs/inertia-laravel` (^2.0), `laravel/framework` (^12.0), `laravel/horizon` (^5.44), `laravel/jetstream` (^5.4), `laravel/pennant` (^1.19), `laravel/reverb` (^1.7), `laravel/sanctum` (^4.0), `laravel/scout` (^10.24), `laravel/tinker` (^2.10.1), `laudis/neo4j-php-client` (^3.0), `php` (^8.2), `phpoffice/phpspreadsheet` (^5.4), `ratchet/pawl` (^0.4.3), `spatie/laravel-backup` (^10.0), `tightenco/ziggy` (^2.0), `typesense/typesense-php` (^6.0)
- PHP development dependencies: 7
  - sample: `fakerphp/faker` (^1.23), `laravel/pail` (^1.2.2), `laravel/pint` (^1.24), `laravel/sail` (^1.41), `mockery/mockery` (^1.6), `nunomaduro/collision` (^8.6), `phpunit/phpunit` (^11.5.3)
- Node runtime dependencies: 8
  - sample: `@crazydos/vue-markdown` (^1.1.4), `@tiptap/extension-link` (^3.19.0), `@tiptap/extension-placeholder` (^3.19.0), `@tiptap/markdown` (^3.19.0), `@tiptap/starter-kit` (^3.19.0), `@tiptap/vue-3` (^3.19.0), `lucide-vue-next` (^0.575.0), `remark-gfm` (^4.0.1)
- Node development dependencies: 20
  - sample: `@inertiajs/vue3` (^2.0), `@playwright/test` (^1.58.2), `@tailwindcss/forms` (^0.5.7), `@tailwindcss/typography` (^0.5.10), `@tailwindcss/vite` (^4.0.0), `@vitejs/plugin-vue` (^6.0.4), `@vue/server-renderer` (^3.3.13), `@vue/test-utils` (^2.4.6), `autoprefixer` (^10.4.16), `axios` (^1.11.0), `concurrently` (^9.0.1), `jsdom` (^26.1.0), `laravel-echo` (^2.3.0), `laravel-vite-plugin` (^2.0.0), `postcss` (^8.4.32), `pusher-js` (^8.4.0), `tailwindcss` (^3.4.0), `vite` (^7.0.7), `vitest` (^3.2.4), `vue` (^3.3.13)

### Codebase Structure

- Top-level directory distribution:
  - `app`: 515 files
  - `.codex`: 309 files
  - `.cursor`: 295 files
  - `.claude`: 291 files
  - `tests`: 291 files
  - `tasks`: 218 files
  - `docs`: 216 files
  - `resources`: 153 files
  - `public`: 134 files
  - `database`: 120 files
  - `bootstrap`: 99 files
  - `config`: 27 files
  - `playwright-report`: 24 files
  - `test-results`: 16 files
  - `prompts`: 6 files
  - `routes`: 4 files
  - `.DS_Store`: 1 files
  - `.editorconfig`: 1 files
  - `.env`: 1 files
  - `.env.example`: 1 files
- Notable paths (sample): `.DS_Store`, `.claude/hooks/pre-commit`, `.claude/skills/WriteStory/AestheticProfiles.md`, `.claude/skills/WriteStory/AntiCliche.md`, `.claude/skills/WriteStory/Critics.md`, `.claude/skills/WriteStory/RhetoricalFigures.md`, `.claude/skills/WriteStory/SKILL.md`, `.claude/skills/WriteStory/StorrFramework.md`, `.claude/skills/WriteStory/StoryLayers.md`, `.claude/skills/WriteStory/StoryStructures.md`, `.claude/skills/WriteStory/Workflows/BuildBible.md`, `.claude/skills/WriteStory/Workflows/Explore.md`, `.claude/skills/WriteStory/Workflows/Interview.md`, `.claude/skills/WriteStory/Workflows/Revise.md`, `.claude/skills/WriteStory/Workflows/WriteChapter.md`, `.claude/skills/ai-product/SKILL.md`, `.claude/skills/arc-check/README.md`, `.claude/skills/arc-check/SKILL.md`, `.claude/skills/book-cover-design/SKILL.md`, `.claude/skills/book-cover-design/_meta.json`

### Backend Surface

- Route files: 19
- Models: 77
- Migrations: 99
- Jobs: 323
- Events: 37
- Model sample: `AccountLinkToken.php`, `AgentAuditLog.php`, `AgentBackupSetting.php`, `AgentFeatureSetting.php`, `AgentJob.php`, `AgentJobRun.php`, `AgentMaintenanceCheckpoint.php`, `AgentRunEvent.php`, `AgentSystemState.php`, `ApiDocArtifact.php`, `ChatAction.php`, `ChatAttachment.php`
- Job sample: `2026_02_12_020512_create_agent_job_runs_table.php`, `2026_02_17_200006_create_delegation_task_dependencies_table.php`, `2026_02_26_224300_add_task_category_to_interrogation_build_tasks.php`, `2026_02_27_110001_add_star_ab_group_to_agent_job_runs.php`, `29_agent_delegation_task_detail.png`, `30_agent_delegation_task_approve.png`, `AiCriticCompletedJob.php`, `AiCriticCompletedJobTest.php`, `ChatActionHandlerInterface.php`, `Create.vue`, `DelegationAttemptCompletedJob.php`, `DiscordGatewayWorker.php`

### Frontend Surface

- Entrypoint/module file count: 147
- Package manifest detected: yes
- Entrypoint sample: `resources/js/Components/ActionMessage.vue`, `resources/js/Components/ActionSection.vue`, `resources/js/Components/Agent/LlmDegradationWarning.vue`, `resources/js/Components/Agent/NlScheduleInput.vue`, `resources/js/Components/Agent/ParseConfirmationModal.vue`, `resources/js/Components/AppConfirmDialog.vue`, `resources/js/Components/ApplicationLogo.vue`, `resources/js/Components/ApplicationMark.vue`, `resources/js/Components/AuthenticationCard.vue`, `resources/js/Components/AuthenticationCardLogo.vue`, `resources/js/Components/Banner.vue`, `resources/js/Components/Checkbox.vue`, `resources/js/Components/CodeAnalysis/ArtifactInspector.vue`, `resources/js/Components/CodeAnalysis/CoveragePanel.vue`, `resources/js/Components/CodeAnalysis/ReportViewer.vue`, `resources/js/Components/CodeAnalysis/TaskGraphPanel.vue`, `resources/js/Components/ConfirmationModal.vue`, `resources/js/Components/ConfirmsPassword.vue`, `resources/js/Components/DangerButton.vue`, `resources/js/Components/DialogModal.vue`

### Testing Surface

- Test file count: 294
- Test file sample: `resources/js/Components/__tests__/HelpHint.spec.ts`, `resources/js/Components/__tests__/StatusCard.spec.ts`, `resources/js/Pages/Tools/CodeAnalysis/__tests__/eventStream.spec.ts`, `tests/Feature/AdversarialReviewerDisabledTest.php`, `tests/Feature/AdversarialReviewerPlanTest.php`, `tests/Feature/AdversarialReviewerShadowModeTest.php`, `tests/Feature/AdversarialReviewerSummaryTest.php`, `tests/Feature/Agent/AgentJobActiveHoursTest.php`, `tests/Feature/Agent/DispatchActiveHoursTest.php`, `tests/Feature/AgentApiContractCoverageTest.php`, `tests/Feature/AgentApiWorkflowTest.php`, `tests/Feature/AgentBackupDatabaseCommandTest.php`, `tests/Feature/AgentDispatchDueCommandTest.php`, `tests/Feature/AgentJobValidationTest.php`, `tests/Feature/AgentMaintenancePruneTest.php`, `tests/Feature/AgentQueueRuntimeConfigTest.php`, `tests/Feature/AgentRunnerLifecycleTest.php`, `tests/Feature/AgentWebRouteAuthTest.php`, `tests/Feature/Api/ChatApiTest.php`, `tests/Feature/Api/ComplianceApiTest.php`

### Risk Hotspots

- Hotspot file count: 675
- Hotspot sample: `.claude/skills/developing-with-laravel/templates/controller.template.php`, `.claude/skills/developing-with-laravel/templates/migration.template.php`, `.claude/skills/developing-with-laravel/templates/service_provider.template.php`, `.codex/skills/developing-with-laravel/templates/controller.template.php`, `.codex/skills/developing-with-laravel/templates/migration.template.php`, `.codex/skills/developing-with-laravel/templates/service_provider.template.php`, `.cursor/skills/developing-with-laravel/templates/controller.template.php`, `.cursor/skills/developing-with-laravel/templates/migration.template.php`, `.cursor/skills/developing-with-laravel/templates/service_provider.template.php`, `app/Actions/Fortify/CreateNewUser.php`, `app/Actions/Fortify/PasswordValidationRules.php`, `app/Actions/Fortify/ResetUserPassword.php`, `app/Actions/Fortify/UpdateUserPassword.php`, `app/Actions/Fortify/UpdateUserProfileInformation.php`, `app/Actions/Jetstream/DeleteUser.php`, `app/Console/Commands/AgentBackupDatabaseCommand.php`, `app/Console/Commands/AgentBenchmarkSloCommand.php`, `app/Console/Commands/AgentDispatchDueCommand.php`, `app/Console/Commands/AgentInstallCommand.php`, `app/Console/Commands/AgentPruneCommand.php`

### Coverage Gate

- Passed: yes
- Tasks completed: 17 / 17
- Required artifact classes: `architecture_patterns`, `code_quality_standards`, `dependency_manifest`, `filesystem_manifest`, `risk_hotspot`, `test_coverage_map`

### Code Analysis Glossary

- Task graph: Deterministic analyzer DAG generated in phase 2, with dependency-ordered tasks executed in phase 3.
- Coverage gate: Phase 4 validation that blocks completion if required artifact classes are missing or critical task failures exist.
- Artifacts: Versioned outputs from snapshot/analyzers/coverage/reporting, each identified by artifact key and content hash.

### Limitations

- Analysis combines deterministic repository scanning with AI synthesis and does not execute full runtime behavior.

## Artifacts

- `ai_backend_surface:2fef8467ff339540c541ccbc.ai.ai_backend_surface.json` (ai_analysis_section) `e83bf7ed11a09d3ca0814bec039afad1dd6f0999fd735ff54f8f5f0d3865dba0`
- `ai_coding_standards_quality:3d3115133eb80296e657790b.ai.ai_coding_standards_quality.json` (ai_analysis_section) `e224d92c82d78f676e28499a75ae796491fcb1d2903973fb23d465ddbda821a5`
- `ai_design_patterns:21b0c17da26fde66cb2d9f14.ai.ai_design_patterns.json` (ai_analysis_section) `a7782ef8865cf84e04370ed46f6112da266effc98b05070b882ea097b46c64b4`
- `ai_final_report:2bf889edfc119bae2a533cf9.ai.ai_final_report.json` (ai_analysis_section) `97b799ab975a3eb2f49f5e66dd334a1746b52ea34c270fe961baa3d4d7d53ed2`
- `ai_frontend_surface:61931b035066f2a5c9a66afa.ai.ai_frontend_surface.json` (ai_analysis_section) `6dcc53a197b1cce68e52beb51904d34102e3ee996cf233d4aeb9c72297e1693b`
- `ai_overview:8fe2e2d3d042ebcfff5c8338.ai.ai_overview.json` (ai_analysis_section) `b4a8567d9b31531bc1f2ac829e48a54bfa3dcfdbfc2ddc787303319153781a8e`
- `ai_quality_risk:3c3c0cd171228457194655c0.ai.ai_quality_risk.json` (ai_analysis_section) `d51d13f89979999b40bd0d1b8574d8cabe4e935a54c2030cedd05b4df4f97900`
- `architecture_patterns:4246a8e851dbcf5058fb031d.architecture_patterns.json` (architecture_patterns) `4a5ada349b33cf60a4a2fab081bd3ad9ab56b1568e009e0b507b49ae5b9f72bc`
- `async_workflows_surface:57c1becf050d6651ee33b0f1.async_workflows_surface.json` (async_workflows_surface) `38b8cb9ea8c3e368573addc2c7269da6afbd91544005e13993d7d1ca8bf9951d`
- `code_quality_standards:1db005c0b51242b674bd712f.code_quality_standards.json` (code_quality_standards) `3c6589b9148d183285ac23898d6b1f89b56504c35919ee223ff8e14df42312a3`
- `coverage.validation.json` (coverage_validation) `31f02d3ce90e988a23295e55c04b4ff0607fede34273f469181bc2fcd743f736`
- `data_model_surface:70fe08e10cbdd391ad84205f.data_model_surface.json` (data_model_surface) `c5f65da9067f20fe09ee67bff359b28f1db70a223bc8c9b9827f3fd07d244734`
- `dependency_manifest:f6231870f917f71e0d046809.dependency_manifest.json` (dependency_manifest) `f72afaae3c502117cc8d966f618e152e224f6d922361dabb826abf8eb6304e90`
- `filesystem_manifest:a383428922834cede2490404.filesystem_manifest.json` (filesystem_manifest) `48188542c7a80db86d73295706f063f007cceebade378b437754d1911b2c9590`
- `frontend_surface:0e8688a3f8d0cd532955ae30.frontend_surface.json` (frontend_surface) `c7739341e68bfb60ccd72b28c1af0b08d136fb4948602e70d96e5679ee4f1a8f`
- `risk_hotspot:d0a6d4bb592c77070f3db3ce.risk_hotspot.json` (risk_hotspot) `c153d62d7f19bef9e35608042222c81ce323ef07cf2473a002b4791aa8adab05`
- `routing_surface:ce6dd0beaf52a4300ce35935.routing_surface.json` (routing_surface) `6313553b56e274c686eb2842b4e65e4213be7c9c6ce5aa14ced22a4d6000edff`
- `snapshot.manifest.json` (snapshot_manifest) `64be0495f440a6b08633bec0bd085f1895448660a550ad512a30c0aa1a0b762a`
- `test_coverage_map:9429d9669d175eb6bc916db7.test_coverage_map.json` (test_coverage_map) `f82def31cdf47142d892b37c8683d3eabd269624b97ece24b27c372ae5eef0c7`
