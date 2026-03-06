# Requirements Discovery Summary

Session: 3

# Agent Skills (Pluggable Capability Layer) — Final Requirements Summary

## Architecture Overview

A first-class skill system layered on the existing delegation runtime, messenger control plane, memory system, and compliance layer. Skills are domain-specific capability bundles (SKILL.md + resources) that agents invoke at runtime. Skills extend agent capabilities without modifying core platform code.

**Stack:** Laravel 12 / PHP 8.3, PostgreSQL, Redis, Horizon, Vue 3 + Inertia.js. Multi-tenancy via Jetstream `team_id`.

---

## Skill Format

### File Structure
```
skill-name/
├── SKILL.md              # Required — YAML frontmatter + markdown instructions
├── scripts/              # Optional — Python, Bash, JS, PHP helpers
├── references/           # Optional — detailed docs loaded on demand
└── assets/               # Optional — templates, schemas, static files
```

### SKILL.md Frontmatter
Standard Agent Skills (agentskills.io) fields at root level. Platform extensions in `x-agent` namespace block:
```yaml
---
name: financial-compliance-check
description: |
  Validates agent outputs against financial compliance requirements...
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [financial-services]
  risk_level: elevated        # low | standard | elevated | critical
  requires_approval: false
  memory_blocks: [compliance-rules]
  mcp_dependencies: [database]
  tools: [file-read, web-search]
  trigger_keywords: []        # Optional — auto-extracted from description if empty
  run_after: []               # Ordered composition dependencies
  compatibility: "Agent Platform >= 1.0"
---
```

### Trigger Keywords
Hybrid approach: keywords auto-extracted from description at install time via NLP. Authors can optionally override with explicit `trigger_keywords` in `x-agent` block. Both sources merged and stored on the `agent_skills` row.

### Progressive Disclosure (3 levels)
| Level | Content | When Loaded | Budget |
|-------|---------|-------------|--------|
| Metadata | name + description + trigger_keywords | Always (agent startup) | ~100 tokens/skill |
| Instructions | SKILL.md body | When skill triggers | <5,000 tokens |
| Resources | scripts/, references/, assets/ | On-demand from instructions | Unlimited (lazy) |

### Packaging
`.skill` files = ZIP archives. Distributed via library manifest or direct upload.

---

## Database Schema (2 tables)

### `agent_skills`
```sql
CREATE TABLE agent_skills (
    id                  UUID PRIMARY KEY,
    team_id             BIGINT UNSIGNED NOT NULL REFERENCES teams(id),
    name                VARCHAR(64) NOT NULL,
    slug                VARCHAR(64) NOT NULL,
    description         TEXT NOT NULL,
    version             VARCHAR(20) NOT NULL,
    author              VARCHAR(128) NOT NULL,
    risk_level          VARCHAR(20) NOT NULL DEFAULT 'standard',
    status              VARCHAR(20) NOT NULL DEFAULT 'active',
    industries          JSONB DEFAULT '[]',
    memory_blocks       JSONB DEFAULT '[]',
    mcp_dependencies    JSONB DEFAULT '[]',
    tools_required      JSONB DEFAULT '[]',
    trigger_keywords    JSONB DEFAULT '[]',
    run_after           JSONB DEFAULT '[]',
    requires_approval   BOOLEAN DEFAULT FALSE,
    skill_path          TEXT NOT NULL,
    checksum            VARCHAR(64) NOT NULL,
    file_hashes         JSONB NOT NULL,
    validation_result   JSONB NOT NULL,
    is_global           BOOLEAN DEFAULT FALSE,
    installed_by        BIGINT UNSIGNED REFERENCES users(id),
    installed_at        TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_by          BIGINT UNSIGNED REFERENCES users(id),
    updated_at          TIMESTAMP NOT NULL DEFAULT NOW(),
    paused_by           BIGINT UNSIGNED REFERENCES users(id),
    paused_at           TIMESTAMP,
    last_invoked_at     TIMESTAMP,
    invocation_count    INTEGER DEFAULT 0,
    UNIQUE (team_id, slug)
);
```

### `agent_skill_validations`
```sql
CREATE TABLE agent_skill_validations (
    id                  UUID PRIMARY KEY,
    skill_name          VARCHAR(64) NOT NULL,
    team_id             BIGINT UNSIGNED NOT NULL REFERENCES teams(id),
    validation_result   JSONB NOT NULL,
    risk_score          DECIMAL(4,3) NOT NULL,
    overall_pass        BOOLEAN NOT NULL,
    source              VARCHAR(20) NOT NULL,
    validated_by        BIGINT UNSIGNED REFERENCES users(id),
    created_at          TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### Indexes
```sql
CREATE INDEX idx_skills_team_status ON agent_skills (team_id, status);
CREATE INDEX idx_skills_team_industry ON agent_skills USING GIN (industries);
CREATE INDEX idx_skills_trigger_keywords ON agent_skills USING GIN (trigger_keywords);
CREATE INDEX idx_skill_validations_name ON agent_skill_validations (skill_name, created_at);
```

### Telemetry Storage
No dedicated invocations table. Skill events written to existing `telemetry_event_ledger` table with event types `skill.invoked`, `skill.completed`, `skill.failed` in `event_type` column. Skill-specific data (`skill_id`, `skill_version`, `duration_ms`, `token_usage`, `outcome`, `team_id`) stored in `payload_json` JSONB column. `SkillTelemetryRecorder` does dual-write: ledger event insert + atomic `agent_skills` row update (`invocation_count` increment, `last_invoked_at` timestamp).

### Skill Library Storage
No database table. `SkillLibrary` service reads `skill-library/manifest.json` at runtime. JSON manifest contains slug, name, description, version, author, category, industries, risk_level, file path, and SHA-256 checksum per skill.

---

## Storage Location

Installed skills stored at: `storage/app/skills/{team_id}/{skill-slug}/`

Global skills (installed by personal team owners acting as platform admins): `storage/app/skills/global/{skill-slug}/`

---

## Validation Pipeline (5 stages, mandatory at install)

```
Upload/URL → Unpack → Format → Schema → ContentAnalysis → CodeSafety → Integrity → Install
```

### Stage 1: Format Validation
Archive format, SKILL.md exists and parseable, <10MB uncompressed, ≤50 files, no symlinks, no path traversal. All BLOCK severity.

### Stage 2: Schema Validation
Required fields (name, description, version, author), name format (lowercase+hyphens, ≤64 chars), name uniqueness per team, description 20–1024 chars, valid semver, valid risk_level, valid industry tags, body length warning >500 lines. `x-agent` block parsed separately — unknown keys warned, not blocked.

### Stage 3: Content Analysis (Prompt Injection Detection)
4-strategy default scoring (no LLM):
| Strategy | Weight | Method |
|----------|--------|--------|
| Pattern matching | 0.35 | 200+ regex patterns for role hijacking, instruction override, jailbreak, system prompt extraction |
| Instruction boundary analysis | 0.25 | Structural markdown analysis for concealed directives, invisible unicode, zero-width chars, base64 payloads |
| Authority escalation detection | 0.25 | NLP classification of authority-asserting phrases |
| Data exfiltration patterns | 0.15 | Encoded data transmission, URL construction from variables, hidden API calls |

5-strategy scoring when `skills.validation.llm_review` flag enabled:
| Strategy | Weight |
|----------|--------|
| Pattern matching | 0.25 |
| Boundary analysis | 0.20 |
| Authority escalation | 0.20 |
| Exfiltration | 0.10 |
| LLM review (AdversarialReviewerService) | 0.25 |

LLM review is **mandatory** for elevated/critical risk_level skills regardless of flag.

When LLM review enabled but AdversarialReviewerService unavailable: fall back to 4-strategy weights with a logged warning. Never block installation due to LLM service failure alone.

Thresholds: <0.2 install clean, 0.2–0.5 install with warnings, 0.5–0.8 require admin confirmation, ≥0.8 block.

### Stage 4: Code Safety Scan
Script language allowlist (Python, Bash, JS, PHP), no binaries, no undeclared network calls, no filesystem escape, no process spawning, dependency audit against allowlist, AST analysis for dangerous functions (eval, exec, system, shell_exec, passthru).

### Stage 5: Integrity Verification
SHA-256 checksum match (library installs), file hashes recorded at install for drift detection, HMAC signature (warn-only Phase 1).

---

## Runtime Integration

### Skill Resolution (Hybrid)
1. **Pre-filter:** keyword/tag matching against task description using `trigger_keywords` and `industries`
2. **LLM ranking:** matched candidates ranked by LLM for relevance to specific task
3. **Filters applied:** status=active, delegatee trust ≥ risk_level threshold, MCP deps available, memory blocks accessible, compliance policy permits
4. **Selection:** top N skills (configurable, default 5) injected into context

### Context Injection Order
After STAR preamble, before memory context. Skill metadata block injected at delegation attempt creation time. Full SKILL.md loaded on-demand when agent decides to use skill.

### Skill Access by Risk Level
| Risk Level | Trust Score Required | Approval Required |
|------------|---------------------|-------------------|
| low | Any | No |
| standard | ≥ 0.5 | No |
| elevated | ≥ 0.7 | Configurable |
| critical | ≥ 0.9 | Always (human-in-loop) |

### Ordered Composition
Skills declare `run_after` dependencies in `x-agent` block. Runtime builds execution DAG. Token budget allocated first-come by DAG order — earlier skills in the chain get priority allocation; later skills receive remaining budget and degrade gracefully if insufficient.

### Drift Detection (Dual-trigger)
1. **On-invocation:** lightweight file hash comparison before each skill execution. On mismatch: block invocation, alert operator, set status to `pending_review`.
2. **Scheduled:** daily `skill:drift-check` Artisan command scans all installed skills. Logged to telemetry ledger.

### Runtime Adversarial Review
When `skills.validation.llm_review` flag enabled, skill outputs pass through AdversarialReviewerService with skill instructions included in review context. This is in addition to existing verification pipeline (AutomatedCheckStep, AiCriticStep, optional HumanApprovalStep).

### Script Execution
Scripts run in existing agent sandbox (CommandPolicy, PathPolicy, EnvPolicy). No separate process isolation.

---

## Service Layer

| Service | Responsibility |
|---------|---------------|
| `SkillParser` | Parse SKILL.md frontmatter (including x-agent block) + body, extract trigger keywords via NLP |
| `SkillInstaller` | Unpack .skill ZIP, orchestrate validation pipeline, store to filesystem, create DB record |
| `SkillValidator` | 5-stage validation pipeline orchestrator, produces validation result JSON |
| `SkillLibrary` | Read skill-library/manifest.json at runtime, serve browse/search/install-from-library |
| `SkillResolver` | Hybrid keyword pre-filter + LLM ranking, apply trust/policy/dependency filters |
| `SkillContextInjector` | Inject skill metadata after STAR preamble, manage progressive disclosure loading |
| `SkillDriftDetector` | On-invocation hash check + daily scheduled scan, alert on mismatch |
| `SkillTelemetryRecorder` | Dual-write: telemetry_event_ledger insert + atomic agent_skills row update |
| `SkillOrgIntegrator` | Org layer integration: skill access profiles, ritual template requirements, council composition |

---

## Interfaces

### CLI Commands (7)
```bash
php artisan skill:install {path-or-url}
php artisan skill:list
php artisan skill:validate {path}
php artisan skill:remove {name}
php artisan skill:library
php artisan skill:library:install {slug}
php artisan skill:drift-check              # Scheduled daily
```

### API Endpoints (8)
```
POST   /agent/api/v1/skills/install
GET    /agent/api/v1/skills
GET    /agent/api/v1/skills/{id}
PATCH  /agent/api/v1/skills/{id}
DELETE /agent/api/v1/skills/{id}
POST   /agent/api/v1/skills/{id}/validate
GET    /agent/api/v1/skills/library
POST   /agent/api/v1/skills/library/{slug}/install
```

### UI Surface
Location: `/tools/skills` (new page in Tools settings section)
- Installed Skills grid/list with status, risk level, telemetry summary
- Install Skill: upload .skill file, paste URL, or browse library
- Skill Detail: metadata, execution history, telemetry, controls (pause/remove/update)

### Messenger Commands
Via existing CommandRouter registration:
- `install skill {name}` — with confirmation flow
- `list skills` — status summary
- `pause skill {name}` / `resume skill {name}`
- `skill info {name}` — detail + recent telemetry

### Dashboard Widgets (existing + 2 new)
Extended: ReliabilityScore (skill failure contribution), BudgetUtilization (per-skill token spend), EscalationEvents (skill-triggered escalations)
New: SkillUsage (top skills by invocation, duration, success rate), SkillHealth (per-skill reliability, validation status, drift alerts)

---

## Org Layer Integration
- Named AI employees get skill access profiles (which skills they can invoke)
- Ritual templates declare skill requirements
- Council skill composition enables multi-agent skill chains
- Skill access governed by delegatee trust score thresholds

---

## Authorization

| Action | Required Role |
|--------|--------------|
| Install/update skill | Team owner or admin |
| Remove skill | Team owner or admin |
| Pause/resume | Team owner or admin |
| View installed skills | Any authenticated team member |
| Browse library | Any authenticated team member |
| Install global skill | Personal team owner (platform admin) |

---

## Feature Flags (6)
| Flag | Description |
|------|-------------|
| `skills.enabled` | Master toggle |
| `skills.ui_enabled` | UI pages |
| `skills.library_enabled` | Library access |
| `skills.auto_resolve` | Automatic skill matching during delegation |
| `skills.validation.llm_review` | LLM-assisted content analysis via AdversarialReviewerService |
| `skills.validation.strict_mode` | Block on any warning (not just errors) |

---

## Horizon Supervisor
New supervisor: `supervisor-skill-validation` for async validation jobs.

---

## Config Extension
`config/agent.php` extended with `skills` key containing: storage paths, default limits (5 skills per invocation), risk level trust thresholds, validation weights, drift check schedule, trigger keyword extraction settings, and skill library manifest path.

---

## Canonical Reuse Contracts
- **Delegation:** Skills injected at attempt creation, governed by CommandPolicy/PathPolicy/EnvPolicy, outputs through verification pipeline, trust-gated access
- **Memory:** Skills declare memory block requirements, execution captured in working memory, cannot bypass token budgets, graceful degradation without memory
- **Messenger:** Install/remove via confirmation flow, async mutations with thread-aware progress, provider-specific formatting
- **Compliance:** Plan gate evaluation, verification gate, lessons system integration, enforcement mode applies identically
- **Telemetry:** Events in existing ledger, included in weighted reliability calculations, cost attributed to workflow budget

---

## Initial Skill Library: 31 Skills

**Accounting & Financial (4):** financial-compliance-check, client-report-generator, engagement-letter-generator, insurance-renewal-processor

**Legal (3):** contract-clause-reviewer, client-communication-reviewer, case-file-summarizer

**Healthcare & Care (3):** care-compliance-checker, rota-optimizer, clinical-document-processor

**Facilities & Construction (3):** compliance-inspection-reporter, job-costing-analyzer, waste-transfer-processor

**Logistics & Supply Chain (3):** shipment-document-processor, delivery-performance-analyzer, supplier-compliance-monitor

**Recruitment (2):** candidate-screening-processor, placement-compliance-checker

**Property (2):** property-listing-generator, tenancy-compliance-checker

**Food & Manufacturing (2):** food-safety-compliance, stock-reconciliation

**Cross-Industry Utilities (9):** output-fact-checker, pii-detector-redactor, sop-generator, multi-format-reporter, meeting-intelligence, escalation-drafter, data-reconciliation, vendor-contract-reviewer, timesheet-analyzer

---

## Success Metrics
| Metric | Target |
|--------|--------|
| Install success rate | >95% |
| Validation false positive rate | <5% |
| Validation false negative rate | <1% |
| Skill resolution accuracy | >85% |
| Skill-attributed reliability | No degradation |
| Time to install (median) | <30 seconds |
| Initial library | 31 skills across 9 verticals + cross-industry |

## Goals

- Deliver a skill format extending the Agent Skills open standard (agentskills.io) with platform-specific fields in a namespaced x-agent extension block, preserving base-format portability
- Provide install interfaces (UI at /tools/skills, 8 API endpoints, 7 CLI commands, messenger chat commands) for adding skills from .skill files, URLs, or the bundled skill library
- Ship a curated skill library of 31 initial skills targeting 9 industry verticals (Accounting, Legal, Healthcare, Facilities/Construction, Logistics, Recruitment, Property, Food/Manufacturing, plus cross-industry utilities) distributed as manifest.json + bundled .skill files read at runtime
- Build a 5-stage automated skill validator and security scanner (Format → Schema → ContentAnalysis → CodeSafety → Integrity) that runs mandatorily at install time with configurable LLM-assisted adversarial review
- Integrate skills into the existing delegation runtime with hybrid resolution (keyword pre-filter + LLM ranking), trust-score-gated access by risk level, ordered composition via run_after DAG, and progressive disclosure context injection after STAR preamble
- Expose skill reliability, cost, and usage telemetry through existing telemetry_event_ledger (payload_json JSONB) with dual-write to agent_skills row, plus 2 new dashboard widgets (SkillUsage, SkillHealth) and 3 extended existing widgets
- Implement dual-trigger drift detection (on-invocation hash check + daily scheduled skill:drift-check command) that blocks invocation and alerts on file tampering
- Integrate skills with org layer: skill access profiles for named AI employees, ritual template skill requirements, council skill composition for multi-agent chains
- Support team-scoped skills (team_id FK) with global skill option for personal team owners acting as platform admins, stored at storage/app/skills/global/{skill-slug}/


## Constraints

- Multi-tenancy uses team_id (BIGINT UNSIGNED FK to teams table) — not tenant_id — consistent with Jetstream teams throughout the codebase
- All skill execution flows through existing delegation components: CommandPolicy, PathPolicy, EnvPolicy — a skill cannot expand an agent's authority beyond its existing policy boundaries
- Scripts run in existing agent sandbox — no separate process isolation for Phase 1
- No dedicated skill invocations table — telemetry events go to existing telemetry_event_ledger with skill data in payload_json JSONB column
- No database table for skill library — SkillLibrary service reads skill-library/manifest.json at runtime
- Skill context injected after STAR preamble, before memory context, at delegation attempt creation time
- Platform extensions use x-agent namespace block in SKILL.md frontmatter — not flat fields — preserving agentskills.io base-format portability
- Content analysis uses 4-strategy default scoring (pattern_match 0.35, boundary_analysis 0.25, authority_escalation 0.25, exfiltration 0.15) without LLM; 5-strategy with redistributed weights when skills.validation.llm_review flag enabled
- LLM review is mandatory for elevated/critical risk_level skills regardless of flag setting
- When LLM review enabled but AdversarialReviewerService unavailable: fall back to 4-strategy weights with logged warning — never block installation due to LLM service failure alone
- Maximum 5 skills per invocation (configurable default), with first-come token budget allocation by DAG order for ordered chains
- Skill install/update/remove restricted to team owner or admin role; global skill install restricted to personal team owners
- All 6 delivery phases implemented in this build run — no phased rollout
- Feature gated behind 6 flags: skills.enabled (master), skills.ui_enabled, skills.library_enabled, skills.auto_resolve, skills.validation.llm_review, skills.validation.strict_mode
- Installed skills stored at storage/app/skills/{team_id}/{skill-slug}/ with global skills at storage/app/skills/global/{skill-slug}/
- Trigger keywords: hybrid approach — auto-extracted from description at install time via NLP, with optional author override in x-agent.trigger_keywords; both merged and stored on agent_skills row
- Drift detection blocks invocation on mismatch (not just warn) and sets skill status to pending_review


## Acceptance Criteria

- SKILL.md parser correctly extracts standard fields at root and platform extensions from x-agent namespace block, with unknown x-agent keys producing warnings not errors
- 5-stage validation pipeline runs mandatorily on every install — no skill can be installed without passing all BLOCK-severity checks across Format, Schema, ContentAnalysis, CodeSafety, and Integrity stages
- Content analysis produces injection_risk_score using correct weights for 4-strategy (no LLM) and 5-strategy (with LLM) modes, with thresholds: <0.2 clean, 0.2-0.5 warn, 0.5-0.8 admin confirm, >=0.8 block
- LLM review via AdversarialReviewerService is mandatory for elevated/critical risk skills even when skills.validation.llm_review flag is disabled; graceful fallback to 4-strategy on service unavailability
- Hybrid skill resolver performs keyword/tag pre-filter then LLM ranking, applying trust score >= risk_level threshold filter, and selects top N (default 5) skills
- Skill context injected after STAR preamble and before memory context at delegation attempt creation time, with progressive disclosure loading full SKILL.md only on agent decision to use skill
- Ordered composition respects run_after DAG with first-come token budget allocation — earlier skills get priority, later skills degrade gracefully on insufficient budget
- SkillTelemetryRecorder performs dual-write: telemetry_event_ledger insert (with team_id, skill_id, skill_version, duration_ms, token_usage, outcome in payload_json) + atomic agent_skills row update (invocation_count increment, last_invoked_at)
- On-invocation drift detection compares file hashes before execution — on mismatch: blocks invocation, sets status to pending_review, alerts operator
- Daily skill:drift-check command scans all installed skills and logs results to telemetry ledger
- agent_skills table uses team_id BIGINT UNSIGNED FK, supports is_global boolean, enforces UNIQUE(team_id, slug)
- agent_skill_validations table records every validation attempt with full result JSON, risk score, and source (upload/url/library)
- All 7 CLI commands functional: skill:install, skill:list, skill:validate, skill:remove, skill:library, skill:library:install, skill:drift-check
- All 8 API endpoints return correct responses with proper authorization (team owner/admin for mutations, any team member for reads)
- UI at /tools/skills renders installed skills grid, install flow (upload/URL/library), and skill detail view with telemetry
- Messenger commands (install/list/pause/resume/info) registered in CommandRouter with confirmation flows per messenger contract
- 31 initial skills authored, validated, and included in skill-library/manifest.json with SHA-256 checksums
- SkillLibrary service reads manifest.json at runtime — no database table for library catalog
- Global skills installable by personal team owners, stored at storage/app/skills/global/{skill-slug}/, visible to all teams
- Org layer integration: skill access profiles on named AI employees, ritual templates declare skill requirements, council composition supports multi-agent skill chains
- All 6 feature flags registered in AgentFeatureSetting and functional as gates
- New Horizon supervisor supervisor-skill-validation configured for async validation jobs
- Dashboard extended: SkillUsage and SkillHealth new widgets, ReliabilityScore/BudgetUtilization/EscalationEvents extended with skill data
- Compliance contract honored: skill invocations subject to plan gate, verification gate, lessons system, enforcement mode

