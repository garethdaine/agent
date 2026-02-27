import type { QAItem, BuildTask, TechStackEntry } from "./types";

export const mockTechStack: TechStackEntry[] = [
  { id: "1", name: "Laravel 12", url: "https://laravel.com/docs/12.x" },
  { id: "2", name: "Vue 3", url: "https://vuejs.org/guide/introduction.html" },
  { id: "3", name: "PostgreSQL", url: "https://www.postgresql.org/docs/" },
];

export const mockDiscoveryLog = [
  "[00:00] Starting repository analysis...",
  "[00:02] Scanning file structure — found 342 files across 48 directories",
  "[00:05] Detected package manager: Composer (PHP), npm (JavaScript)",
  "[00:08] Analyzing composer.json — Laravel 12.x framework detected",
  "[00:12] Analyzing package.json — Vue 3.4, Vite 6.x, Tailwind CSS 4.x",
  "[00:15] Found 24 Eloquent models in app/Models/",
  "[00:18] Found 32 API routes, 18 web routes",
  "[00:22] Detected test framework: PHPUnit + Pest (84 test files)",
  "[00:25] Analyzing database migrations — 42 migration files",
  "[00:28] Detected queue system: Redis with Horizon",
  "[00:31] Architecture pattern: Service/Repository with Actions",
  "[00:34] Found CI/CD config: GitHub Actions (3 workflows)",
  "[00:36] Repository analysis complete.",
];

export const mockQAItems: QAItem[] = [
  {
    id: 1,
    category: "architecture",
    categoryColor: "bg-primary/10 text-primary",
    question:
      "The repository uses a Service/Repository pattern with Action classes. Should the new feature follow the same architectural pattern, or would you prefer a different approach for this specific feature?",
    reasoning:
      "The existing codebase has a consistent pattern of Service classes that delegate to Repositories for data access, with Action classes for discrete operations. Maintaining consistency helps with long-term maintenance.",
    answer:
      "Yes, follow the existing Service/Repository pattern. Action classes should be used for discrete operations like sending notifications or syncing with external services.",
    options: [
      "Follow existing Service/Repository + Actions pattern",
      "Use a simpler Controller → Model approach for this feature",
      "Use CQRS (Command/Query Responsibility Segregation)",
      "Something else...",
    ],
  },
  {
    id: 2,
    category: "security",
    categoryColor: "bg-destructive/10 text-destructive",
    question:
      "What authentication and authorization model should be used? The project currently uses Laravel Sanctum for API tokens and Spatie Permission for role-based access. Should the new feature integrate with these existing systems?",
    reasoning:
      "I found Sanctum configuration in config/sanctum.php and Spatie Permission models referenced in multiple controllers. Understanding auth requirements early prevents security gaps.",
    answer:
      "Integrate with existing Sanctum + Spatie Permission. The new feature needs a new 'agent-manager' role with specific permissions for CRUD operations on agents and tasks.",
  },
  {
    id: 3,
    category: "performance",
    categoryColor: "bg-warning/10 text-warning",
    question:
      "The feature involves real-time agent status monitoring. What's the expected scale — how many concurrent agents and how frequently should status updates be pushed?",
    reasoning:
      "Real-time monitoring has significant performance implications. The choice between WebSockets, SSE, or polling depends on expected load and update frequency.",
    options: [
      "< 50 agents, updates every 30 seconds (polling is fine)",
      "50-500 agents, updates every 5 seconds (SSE recommended)",
      "500+ agents, sub-second updates (WebSockets required)",
      "Something else...",
    ],
  },
  {
    id: 4,
    category: "integration",
    categoryColor: "bg-[#7c3aed]/10 text-[#7c3aed]",
    question:
      "The Linear integration will sync generated build tasks bidirectionally. Should status changes in Linear automatically reflect in the Agent Scheduler, and vice versa?",
    reasoning:
      "Bidirectional sync adds complexity but improves workflow. A one-way sync (push only) is simpler to implement and maintain. Need to understand the team's workflow preference.",
  },
  {
    id: 5,
    category: "architecture",
    categoryColor: "bg-primary/10 text-primary",
    question:
      "Should the AI agent orchestration use a queue-based approach for task execution, or a direct synchronous pipeline? The project already has Redis + Horizon set up.",
    reasoning:
      "Queue-based execution provides better fault tolerance, retry capability, and scalability. The existing Horizon setup makes this straightforward to implement.",
    answer:
      "Use queue-based approach with Horizon. Each agent task should be a separate job that can be retried independently. Use job chains for dependent tasks.",
  },
];

export const mockSummaryLog = [
  "Analyzing 5 Q&A responses and repository context...",
  "Extracting architectural decisions and constraints...",
  "Identifying project goals from answers and repository patterns...",
  "Compiling acceptance criteria from requirements...",
  "Cataloging open questions and unresolved items...",
  "Cross-referencing with repository structure and dependencies...",
  "Generating compliance and architecture overview...",
  "Building service responsibility matrix...",
  "Finalizing summary with private notes and observations...",
  "Summary generation complete.",
];

export const mockPlanLog = [
  "Analyzing confirmed summary and architecture decisions...",
  "Identifying key implementation domains: orchestration, compliance, monitoring, integration...",
  "Mapping dependencies between Foundation Layer and Execution Engine...",
  "Generating task breakdown for Section 1: Foundation Layer (4 tasks)...",
  "Evaluating queue architecture for Section 2: Agent Execution Engine...",
  "Designing delegation graph traversal for Section 3: Delegation & Verification...",
  "Planning Linear OAuth integration flow for Section 4...",
  "Structuring API endpoints and test coverage for Section 5...",
  "Cross-referencing plan with project constraints and compliance requirements...",
  "Validating task ordering and dependency graph — no circular dependencies found.",
  "Estimating effort: 5 sections, 20 tasks, ~3-4 sprint cycles.",
  "Plan generation complete — ready for review.",
];

export const mockPlan = `## Implementation Plan

### Section 1: Foundation Layer
Set up the core database schema, models, and service classes for the agent orchestration system.

**Tasks:**
1. Create database migrations for agents, agent_tasks, delegation_graphs, delegation_nodes
2. Build Eloquent models with relationships
3. Implement AgentService and AgentRepository
4. Create AgentPolicy for authorization rules

### Section 2: Agent Execution Engine
Build the queue-based agent task execution pipeline using Horizon.

**Tasks:**
1. Implement ExecuteAgentTaskJob with retry logic
2. Build AgentTaskPipeline for chained execution
3. Create AgentMonitorService for health checks
4. Implement SSE endpoint for real-time status streaming

### Section 3: Delegation & Verification
Implement the human-in-the-loop delegation graph system.

**Tasks:**
1. Build DelegationGraphService with node traversal
2. Implement approval workflow with notification hooks
3. Create verification UI endpoints
4. Add automated escalation rules

### Section 4: Linear Integration
Build bidirectional task synchronization with Linear.

**Tasks:**
1. Implement Linear OAuth flow and token management
2. Build LinearSyncService for push/pull operations
3. Create webhook handlers for inbound Linear events
4. Implement conflict resolution for bidirectional sync

### Section 5: API & Testing
Complete API layer and comprehensive test coverage.

**Tasks:**
1. Build RESTful API controllers for all resources
2. Create API resource transformers
3. Write Pest feature tests for all endpoints
4. Write unit tests for services and actions

## Risks

- SSE connection management under high load may require connection pooling
- Bidirectional Linear sync conflict resolution edge cases
- Queue congestion during bulk agent operations

## Assumptions

- Redis is available and properly configured for Horizon
- Linear API rate limits are sufficient for expected sync frequency
- The existing CI/CD pipeline can handle additional test execution time`;

export const mockRules = `# Project Rules

1. **Architecture**: All new code must follow the Service/Repository + Actions pattern
2. **Testing**: Every new feature must have corresponding Pest tests (unit + feature)
3. **Naming**: Use PascalCase for classes, camelCase for methods, snake_case for database columns
4. **API**: All API responses must use Laravel API Resources for transformation
5. **Auth**: All new endpoints require Sanctum authentication and appropriate Spatie permissions
6. **Queue**: Long-running operations (> 2 seconds) must be dispatched to queues
7. **Logging**: All agent operations must log to a dedicated 'agent' channel
8. **Migrations**: Never modify existing migrations — always create new ones
9. **Dependencies**: New packages require team approval — prefer built-in Laravel features`;

export const mockBuildTasks: BuildTask[] = [
  {
    id: "1",
    title: "Create Agent database migrations",
    description:
      "Create migration files for agents, agent_tasks, agent_configs, and delegation tables with all required columns and indexes.",
    instructions:
      "php artisan make:migration create_agents_table\nphp artisan make:migration create_agent_tasks_table\nphp artisan make:migration create_delegation_graphs_table\nphp artisan make:migration create_delegation_nodes_table",
    status: "completed",
    startedAt: "2026-02-26T10:00:00Z",
    completedAt: "2026-02-26T10:02:15Z",
    log: "✓ Created migration: 2026_02_26_100000_create_agents_table\n✓ Created migration: 2026_02_26_100001_create_agent_tasks_table\n✓ Created migration: 2026_02_26_100002_create_delegation_graphs_table\n✓ Created migration: 2026_02_26_100003_create_delegation_nodes_table\n✓ All migrations created successfully",
  },
  {
    id: "2",
    title: "Build Eloquent models with relationships",
    description:
      "Create Agent, AgentTask, DelegationGraph, and DelegationNode models with proper relationships, casts, and scopes.",
    instructions:
      "Create models in app/Models/ following existing patterns.\nDefine belongsTo, hasMany, belongsToMany relationships.\nAdd relevant query scopes and attribute casts.",
    status: "completed",
    startedAt: "2026-02-26T10:02:30Z",
    completedAt: "2026-02-26T10:05:45Z",
    log: "✓ Created Agent model with hasMany tasks\n✓ Created AgentTask model with belongsTo agent\n✓ Created DelegationGraph model\n✓ Created DelegationNode model with tree relationships\n✓ All models passing static analysis",
  },
  {
    id: "3",
    title: "Implement AgentService and AgentRepository",
    description:
      "Build the service layer for agent CRUD operations and the repository for data access patterns.",
    instructions:
      "Create app/Services/AgentService.php\nCreate app/Repositories/AgentRepository.php\nImplement CRUD + status management methods.",
    status: "running",
    startedAt: "2026-02-26T10:06:00Z",
    log: "✓ Created AgentRepository with query methods\n⟳ Building AgentService — implementing createAgent()...",
  },
  {
    id: "4",
    title: "Create AgentPolicy for authorization",
    description:
      "Implement Laravel Policy for agent resource authorization using Spatie Permission roles.",
    instructions:
      "php artisan make:policy AgentPolicy --model=Agent\nDefine viewAny, view, create, update, delete methods.\nRegister in AuthServiceProvider.",
    status: "queued",
  },
  {
    id: "5",
    title: "Implement ExecuteAgentTaskJob",
    description:
      "Build the queue job for executing individual agent tasks with retry logic, error handling, and progress reporting.",
    instructions:
      "Create app/Jobs/ExecuteAgentTaskJob.php\nImplement ShouldQueue with retry, backoff, and timeout.\nDispatch status events for real-time monitoring.",
    status: "queued",
  },
  {
    id: "6",
    title: "Build SSE endpoint for real-time monitoring",
    description:
      "Create a Server-Sent Events endpoint for streaming agent status updates to the frontend.",
    instructions:
      "Create AgentStreamController with SSE response.\nImplement event broadcasting for agent status changes.\nAdd authentication middleware for SSE connections.",
    status: "queued",
  },
  {
    id: "7",
    title: "Implement Linear OAuth and sync service",
    description:
      "Build the Linear integration with OAuth flow, team/project selection, and bidirectional task sync.",
    instructions:
      "Create LinearAuthController for OAuth callback.\nBuild LinearSyncService for push/pull operations.\nImplement webhook handler for inbound events.",
    status: "queued",
  },
  {
    id: "8",
    title: "Write comprehensive Pest test suite",
    description:
      "Create feature and unit tests for all new services, controllers, and models.",
    instructions:
      "Create tests/Feature/AgentTest.php\nCreate tests/Feature/DelegationTest.php\nCreate tests/Unit/AgentServiceTest.php\nTarget: 90%+ coverage for new code.",
    status: "queued",
  },
];

export const mockSummary = `## Overview

A unified orchestration compliance layer that operationalizes workflow standards across both standard job execution (\`ExecuteAgentRunJob\`) and interrogation build execution (\`ExecuteInterrogationBuildJob\`). The system enforces plan-first behavior, verification gates, correction-driven lessons capture, and elegance checks through configurable, progressive policy enforcement.

## Architecture

New Namespace: \`app/Support/Compliance/\`

Dedicated services for each compliance concern:

- **ComplexityClassifier** — Hybrid heuristic + override classification (simple vs non-trivial)
- **PolicyEvaluator** — Main orchestration service evaluating all applicable gates
- **PlanVerifier** — Ensures plan-first behavior for non-trivial tasks
- **VerificationGateService** — Manages verification checkpoints and approval workflows
- **CorrectionLessonsService** — Captures and applies correction-driven learning
- **EleganceCheckService** — Validates output quality and code elegance standards

## Goals

- Build an **AI Agent Orchestration Layer** that manages multiple AI agents across project workflows
- Implement **real-time monitoring** for agent status, progress, and health metrics
- Create a **delegation graph system** for human-in-the-loop verification of agent outputs
- Integrate with **Linear** for bidirectional task synchronization
- Establish **compliance enforcement** across all execution paths
- Enable **progressive policy adoption** with configurable strictness levels
- Support **correction-driven learning** to improve output quality over time

## Constraints

- Must follow existing **Service/Repository + Actions** architecture pattern
- Authentication via **Laravel Sanctum** with **Spatie Permission** RBAC
- Queue-based execution using **Redis + Horizon** for agent task processing
- Real-time updates via **Server-Sent Events (SSE)** for 50-500 concurrent agents
- All API endpoints must have corresponding **Pest test coverage**
- Policy evaluations must complete in **< 200ms** to avoid blocking job execution
- Compliance rules must be **version-controlled** alongside migrations
- Zero downtime deployment — compliance changes must be **backward compatible**
- Database queries limited to **N+1 safe patterns** with eager loading
- All compliance decisions must be **auditable** with full decision trace logging

## Acceptance Criteria

- Agents can be created, configured, and assigned to projects
- Task delegation supports multi-level approval chains
- Agent execution logs are streamed in real-time to the dashboard
- Failed tasks can be retried individually or in batch
- Linear tasks are synced bidirectionally with webhook support
- ComplexityClassifier correctly categorizes > 95% of tasks against manual baseline
- All non-trivial tasks are blocked without an approved plan
- Verification gates pause execution and notify approvers within 30 seconds
- Correction lessons are captured and surfaced on subsequent similar tasks
- Elegance scores are computed and displayed for all completed build outputs
- Policy strictness can be configured per-project without code changes
- Full compliance audit log available via API with filtering and export
- Graceful degradation — if compliance service is unavailable, tasks proceed with warning
- Dashboard shows compliance health metrics and policy violation trends
- Bulk operations respect per-item compliance evaluation (no batch bypass)

## Open Questions

- Webhook retry strategy for failed Linear syncs — exponential backoff vs. dead letter queue?
- Should agent configuration support hot-reloading or require restart?
- Multi-tenant isolation strategy for shared agent pools
- What is the maximum acceptable performance overhead for policy evaluation at run/task startup?
- Should compliance violations generate alerts or just log entries?
- How should conflicting compliance rules be resolved — priority-based or fail-closed?

## Private Notes

- The existing \`ExecuteAgentRunJob\` has some tech debt around error handling that should be addressed as part of this work
- Team consensus is leaning toward fail-closed for compliance violations, but this needs PM sign-off
- Consider using the existing \`AuditLog\` trait for compliance decision logging rather than building a new system
- The elegance check concept is experimental — build it as a pluggable interface so it can be swapped or disabled easily
- Linear API rate limits have been an issue in the past — build in circuit breaker pattern from day one`;