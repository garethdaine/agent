# Requirements Discovery Brief — Agent Skills System

## 1. Overview

**Feature Name:** Agent Skills (Pluggable Capability Layer)

**Purpose:** Add a first-class skill system to the Agent platform so that users can install, manage, and compose domain-specific capabilities that agents (Jobs, Messenger, and Delegation/Org Layer) can invoke at runtime. Skills extend what agents can do without modifying core platform code, enabling vertical specialization, community contribution, and a marketplace distribution model.

This brief consolidates:

1. Competitive research across 16 agent orchestration and ops platforms.
2. Architecture study of the Agent Skills open standard (agentskills.io) and Claude Code's skill implementation.
3. Target industry analysis from investor research and outreach strategy.
4. Security and validation requirements for safe skill ingestion.

The Skills System is a **composition feature** layered on top of the existing delegation runtime, messenger control plane, memory system, and compliance layer — not a parallel engine.

---

## 2. Competitive Landscape & Gap Analysis

### 2.1 Market Context

The agent skills/plugin market is bifurcating into three tiers: developer frameworks (CrewAI, LangChain, OpenAI SDK), no-code platforms (Lindy.ai, Make.com, n8n, Gumloop), and enterprise platforms (Moveworks, Dust.tt, Salesforce Agentforce). Key findings:

- **MCP adoption has exploded** — 8.4M+ downloads, adopted by OpenAI, Google, and now governed by the Agentic AI Foundation under Linux Foundation. MCP is the de facto tool-access standard.
- **Skills are distinct from MCP tools.** MCP provides *access* (database connections, API calls). Skills provide *expertise* (domain logic, workflow orchestration, prompt patterns, verification steps). The two compose together.
- **No platform has solved skill security at scale.** Despite 57% of enterprises running agents in production, prompt injection, tool permission escalation, and malicious skill content remain unsolved. This is a $500M–2B market gap.
- **Vertical specialization is the defensible moat.** Moveworks dominates ITSM, Relevance AI owns Sales/GTM, Lindy.ai leads voice agents. Platforms that ship opinionated, industry-specific skills win.

### 2.2 Competitive Skills/Plugin Implementations

| Platform | Skill/Plugin System | Schema | Marketplace | Security |
|----------|-------------------|--------|-------------|----------|
| **CrewAI** | Tools (Python decorators), knowledge sources | Python `@tool` decorator | CrewAI Enterprise Hub | Basic sandboxing |
| **LangChain** | Tools + Toolkits + Runnables | Python/TypeScript classes | LangChain Hub | Schema validation only |
| **OpenAI Agents SDK** | Function tools + MCP integration | JSON Schema for params | None (bring your own) | Function-level permissions |
| **Claude Code** | SKILL.md + bundled resources | YAML frontmatter + Markdown | Anthropic Skills repo, community hubs | Filesystem sandbox, `allowed-tools` |
| **Lindy.ai** | Pre-built "Lindies" + custom skills | Visual builder + API configs | 3,000+ templates | Credential vault isolation |
| **n8n** | Community nodes + AI agent nodes | JSON workflow definitions | 1,000+ community nodes | Credential isolation, code review |
| **Dust.tt** | Data sources + custom actions | TypeScript actions | Internal only | Enterprise SSO + audit |
| **Relevance AI** | Tool builder + chains | JSON tool definitions | Template library | API key vault |

### 2.3 Highest-Value Gaps for Agent

From this competitive review, Agent's differentiation opportunity is:

1. **No platform combines skill orchestration with production reliability scoring.** Agent already has `WeightedReliability`, failure taxonomy, and cost governance — skills that degrade reliability or breach budgets can be automatically demoted or paused.
2. **No platform validates skills for prompt injection and malicious content at install time.** Every competitor relies on trust-based distribution or manual review.
3. **No platform composes skills with a delegation DAG.** Agent can assign skills to specific delegatees, chain skill execution through the verification pipeline, and use trust scoring to determine which agents earn access to which skills.
4. **No platform ties skills into a four-layer memory system.** Agent's memory architecture means skills can access institutional knowledge, learn from past executions, and build context over time.

---

## 3. Goals

1. Deliver a skill format that extends the Agent Skills open standard (agentskills.io) with Agent-platform-specific fields in a namespaced `x-agent` extension block, preserving base-format portability.
2. Provide an install interface (UI + API + CLI) for adding skills from files, URLs, or the skill library.
3. Ship a curated skill library with ~30 initial skills targeting 9 industry verticals derived from outreach data (890 companies): IT & Technology, Food & Hospitality, Healthcare & Care, Facilities & Environmental, Logistics & Freight, Construction, Accounting & Advisory, Recruitment & Staffing, Legal Services, Property, Insurance — plus cross-industry utilities.
4. Build an automated skill validator and security scanner that runs at install time.
5. Integrate skills into the existing delegation runtime, messenger control plane, and org layer.
6. Expose skill reliability, cost, and usage telemetry through existing operator surfaces.

---

## 4. Non-Goals (Phase 1)

1. Building a public skill marketplace with third-party submissions and payments.
2. Real-time skill hot-reloading during active job runs.
3. Skill versioning with rollback (version tracking yes, automated rollback no).
4. Cross-tenant skill sharing or a SaaS-hosted skill registry.
5. Visual skill builder UI (skills are authored as SKILL.md files).
6. Skill-level billing or per-invocation metering (use existing cost governance).

---

## 5. Canonical Reuse Contracts

### 5.1 Delegation Runtime Contract

Skill execution must flow through existing delegation components:

1. Skills are injected into agent context at delegation attempt creation time, not at runtime resolution.
2. Skill access is governed by `CommandPolicy`, `PathPolicy`, and `EnvPolicy` — a skill cannot expand an agent's authority beyond its existing policy boundaries.
3. Skills compose with the verification pipeline — skill outputs pass through `AutomatedCheckStep`, `AiCriticStep`, and optionally `HumanApprovalStep`.
4. Trust scoring determines skill access: delegatees below a configurable trust threshold cannot invoke high-risk skills.

### 5.2 Memory Contract

Skills must obey the four-layer memory architecture:

1. Skills can declare memory requirements (e.g., "needs access to core memory block: compliance-rules").
2. Skill execution context is captured in working memory and eligible for long-term formation.
3. Skills cannot bypass memory token budgets or force context injection beyond configured limits.
4. Memory failures never block skill execution; skills degrade gracefully without memory context.

### 5.3 Messenger Contract

Skills invoked via chat must obey messenger control plane lock-ins:

1. Skill installation and removal via chat require confirmation flow.
2. Skill-triggered mutations are async with thread-aware progress updates.
3. Skill outputs in chat respect provider-specific formatting constraints (Slack blocks, Telegram markdown, etc.).

### 5.4 Compliance Contract

All skill execution must flow through the compliance layer:

1. Skills are subject to plan gate evaluation (complex skill chains require planning).
2. Skill outputs are subject to verification gate (evidence by task category).
3. Skill execution events feed the lessons system for future improvement.
4. Compliance enforcement mode (advisory/warning/enforced) applies to skill invocations identically to all other agent actions.

### 5.5 Telemetry Contract

Skill invocations emit telemetry events into the existing append-only ledger:

1. Event type: `skill.invoked`, `skill.completed`, `skill.failed`.
2. Events include `skill_id`, `skill_version`, `duration_ms`, `token_usage`, `outcome`.
3. Skill events are included in weighted reliability calculations for the parent workflow.
4. Skill cost is attributed to the invoking workflow's budget.

---

## 6. Skill Format Specification

### 6.1 File Structure

```
skill-name/
├── SKILL.md              # Required — instructions + metadata
├── scripts/              # Optional — executable helpers
│   └── analyze.py
├── references/           # Optional — detailed docs loaded on demand
│   ├── compliance-rules.md
│   └── industry-templates.md
└── assets/               # Optional — templates, schemas, static files
    ├── report-template.docx
    └── checklist.json
```

### 6.2 SKILL.md Frontmatter Schema

```yaml
---
name: financial-compliance-check          # Required. Lowercase + hyphens, max 64 chars
description: |                            # Required. Max 1024 chars. Primary trigger mechanism.
  Validates agent outputs against financial services compliance requirements
  including FCA guidelines, SOX controls, and GDPR data handling rules.
  Use when agents produce financial reports, client communications,
  or data processing outputs in regulated environments.
version: "1.0.0"                          # Required for Agent platform
author: "agentops"                        # Required for Agent platform
license: "MIT"                            # Optional
industries:                               # Agent platform extension
  - financial-services
  - professional-services
risk_level: "standard"                    # Agent platform extension: low | standard | elevated | critical
requires_approval: false                  # Agent platform extension: human approval before execution
memory_blocks:                            # Agent platform extension: core memory blocks needed
  - compliance-rules
  - client-context
mcp_dependencies:                         # Agent platform extension: required MCP connections
  - database
tools:                                    # Agent platform extension: platform tools required
  - file-read
  - web-search
compatibility: "Agent Platform >= 1.0"    # Optional
---
```

### 6.3 Progressive Disclosure

Three-level loading strategy to preserve context window budget:

| Level | Content | When Loaded | Budget |
|-------|---------|-------------|--------|
| **Metadata** | `name` + `description` from frontmatter | Always (agent startup) | ~100 tokens per skill |
| **Instructions** | SKILL.md body (markdown) | When skill triggers | <5,000 tokens ideal |
| **Resources** | `scripts/`, `references/`, `assets/` | On-demand from instructions | Unlimited (lazy-loaded) |

### 6.4 Packaging

Skills are distributed as `.skill` files (ZIP archives preserving directory structure):

```bash
# Package
zip -r financial-compliance-check.skill financial-compliance-check/

# Install via CLI
php artisan skill:install financial-compliance-check.skill

# Install via API
POST /agent/api/v1/skills/install
Content-Type: multipart/form-data
```

---

## 7. Feature Components

### 7.1 Skill Install Interface

#### 7.1.1 UI Surface

Location: `/tools/skills` (new page in Tools settings section)

**Views:**

- **Installed Skills** — Grid/list of installed skills with name, description, version, risk level, status (active/paused/failed validation), install date, last invoked, invocation count, and reliability score.
- **Install Skill** — Upload `.skill` file, paste URL, or browse library. Shows validation progress and results before confirming install.
- **Skill Detail** — Full skill metadata, execution history, telemetry summary, and controls (pause, remove, update, view SKILL.md source).

**Actions:**

| Action | Requires Confirmation | Feature Flag |
|--------|----------------------|-------------|
| Install skill | Yes (after validation passes) | `skills.enabled` |
| Pause/resume skill | No | `skills.enabled` |
| Remove skill | Yes | `skills.enabled` |
| Update skill | Yes (re-validates) | `skills.enabled` |

#### 7.1.2 API Surface

```
POST   /agent/api/v1/skills/install          # Upload .skill file or URL
GET    /agent/api/v1/skills                   # List installed skills
GET    /agent/api/v1/skills/{id}              # Skill detail + telemetry
PATCH  /agent/api/v1/skills/{id}              # Pause/resume/update
DELETE /agent/api/v1/skills/{id}              # Remove skill
POST   /agent/api/v1/skills/{id}/validate     # Re-validate existing skill
GET    /agent/api/v1/skills/library            # Browse skill library
POST   /agent/api/v1/skills/library/{slug}/install  # Install from library
```

#### 7.1.3 CLI Surface

```bash
php artisan skill:install {path-or-url}       # Install from file or URL
php artisan skill:list                         # List installed skills
php artisan skill:validate {path}              # Validate without installing
php artisan skill:remove {name}                # Remove installed skill
php artisan skill:library                      # Browse available skills
php artisan skill:library:install {slug}       # Install from library
```

#### 7.1.4 Messenger Surface

Chat commands (via existing messenger control plane flows):

- `install skill {name}` — Install from library with confirmation
- `list skills` — Show installed skills with status
- `pause skill {name}` / `resume skill {name}` — Toggle skill availability
- `skill info {name}` — Show skill detail and recent telemetry

### 7.2 Skill Library

#### 7.2.1 Architecture

The skill library is a curated, versioned registry of skills shipped with the platform and available for one-click install. Phase 1 is a local registry (JSON manifest + bundled `.skill` files). Phase 2 can add a remote registry with CDN distribution.

**Registry Manifest** (`skill-library/manifest.json`):

```json
{
  "version": "1.0.0",
  "skills": [
    {
      "slug": "financial-compliance-check",
      "name": "Financial Compliance Check",
      "description": "Validates outputs against FCA, SOX, and GDPR requirements",
      "version": "1.0.0",
      "author": "agentops",
      "industries": ["financial-services"],
      "risk_level": "standard",
      "category": "compliance",
      "file": "skills/financial-compliance-check.skill",
      "checksum": "sha256:abc123..."
    }
  ]
}
```

#### 7.2.2 Library Categories

| Category | Description |
|----------|-------------|
| **Compliance & Governance** | Regulatory checks, audit trail generation, policy enforcement |
| **Data Analysis** | Report generation, data extraction, trend analysis |
| **Communication** | Client correspondence, internal reporting, stakeholder updates |
| **Operations** | Process automation, scheduling, resource planning |
| **Quality Assurance** | Output review, accuracy verification, consistency checks |
| **Industry-Specific** | Vertical domain expertise (finance, legal, professional services) |

### 7.3 Initial Skill Set

Based on outreach data covering 890 target companies across UK mid-market verticals, the initial skill set targets the highest-concentration industries: IT & Technology (100 companies), Food & Hospitality (94), Healthcare & Care (86), Facilities & Environmental (67), Manufacturing & Engineering (56), Logistics & Freight (53), Construction (51), Consulting & Transformation (47), Property & Estate Agency (36), Accounting & Advisory (32), Recruitment & Staffing (30), Legal Services (22), and Insurance (17).

#### 7.3.1 Accounting, Advisory & Financial Services Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `financial-compliance-check` | Validates agent outputs against FCA regulations, HMRC requirements, and GDPR data handling rules. Flags non-compliant language, missing disclosures, and data leakage risks. | elevated | Accounting & Advisory, Insurance |
| `client-report-generator` | Produces structured client reports (management accounts, P&L summaries, budget variance, VAT returns) from raw data with proper formatting, footnotes, and audit-ready sourcing. | standard | Accounting & Advisory |
| `engagement-letter-generator` | Produces engagement letters and SOWs from project parameters with proper legal language, scope boundaries, and fee structures aligned to ICAEW/ACCA standards. | elevated | Accounting & Advisory, Consulting |
| `insurance-renewal-processor` | Processes insurance renewal workflows: extracts expiring policy terms, compares market quotes, generates renewal recommendations, and drafts client communications. | standard | Insurance |

#### 7.3.2 Legal Services Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `contract-clause-reviewer` | Extracts and analyses key contractual clauses (liability caps, termination rights, auto-renewal, SLAs, payment terms) against firm/client policies. Flags risks and missing protections. | elevated | Legal Services, Property |
| `client-communication-reviewer` | Reviews outbound client communications for tone, accuracy, confidentiality compliance, privilege markers, and professional standards. | elevated | Legal Services, Accounting |
| `case-file-summarizer` | Produces structured case summaries from document bundles: chronology, key parties, issues, evidence inventory, and next-steps checklist. | standard | Legal Services |

#### 7.3.3 Healthcare & Care Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `care-compliance-checker` | Validates care documentation against CQC (Care Quality Commission) standards. Checks care plans, incident logs, and staff records for completeness and regulatory compliance. | elevated | Healthcare & Care |
| `rota-optimizer` | Analyses staff rotas for coverage gaps, Working Time Directive compliance, skill-mix requirements, and cost efficiency. Produces optimized schedule recommendations. | standard | Healthcare & Care, Hospitality |
| `clinical-document-processor` | Processes clinical and care documentation: extracts structured data from notes, flags missing mandatory fields, and generates summary reports for handover or audit. | elevated | Healthcare & Care, Veterinary |

#### 7.3.4 Facilities, Environmental & Construction Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `compliance-inspection-reporter` | Generates structured inspection and compliance reports from site visit data: H&S checks, fire safety, environmental audits, waste transfer notes. Follows HSE reporting formats. | standard | Facilities & Environmental, Construction |
| `job-costing-analyzer` | Analyses job/project costing data to identify margin erosion, scope creep, material waste, and labour efficiency patterns. Produces variance reports with root-cause analysis. | standard | Construction, Manufacturing |
| `waste-transfer-processor` | Processes waste transfer documentation: validates duty-of-care records, tracks consignment notes, flags overdue returns, and generates Environment Agency compliance reports. | elevated | Facilities & Environmental |

#### 7.3.5 Logistics, Freight & Supply Chain Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `shipment-document-processor` | Processes freight documentation: bills of lading, customs declarations, commercial invoices, packing lists. Extracts key fields, flags inconsistencies, and validates against booking data. | standard | Logistics & Freight |
| `delivery-performance-analyzer` | Analyses delivery/fulfilment performance data: on-time rates, damage rates, carrier comparison, route efficiency. Produces KPI dashboards and exception reports. | standard | Logistics & Freight, Food & Hospitality |
| `supplier-compliance-monitor` | Monitors supplier compliance against agreed SLAs, certification expiry dates, and insurance validity. Generates alerts and renewal chase communications. | standard | Logistics, Manufacturing, Facilities |

#### 7.3.6 Recruitment & Staffing Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `candidate-screening-processor` | Processes candidate profiles against role requirements: skills matching, availability checking, right-to-work validation reminders, and shortlist generation with ranking rationale. | standard | Recruitment & Staffing |
| `placement-compliance-checker` | Validates temporary/permanent placement documentation against AWR (Agency Workers Regulations), IR35 indicators, and client-specific compliance requirements. | elevated | Recruitment & Staffing |

#### 7.3.7 Property & Estate Agency Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `property-listing-generator` | Generates property listings from survey data and photographs: structured descriptions, EPC compliance checks, rightmove/zoopla formatting, and AML/KYC reminder prompts. | standard | Property & Estate Agency |
| `tenancy-compliance-checker` | Validates tenancy documentation against current legislation: deposit protection, gas/electrical certificates, EPC validity, right-to-rent checks. Flags expiring documents and missing records. | elevated | Property & Estate Agency |

#### 7.3.8 Food, Hospitality & Manufacturing Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `food-safety-compliance` | Validates food safety documentation against HACCP requirements, allergen labelling rules, and EHO inspection preparation checklists. Flags gaps and generates corrective action plans. | elevated | Food & Hospitality, Manufacturing |
| `stock-reconciliation` | Compares inventory counts against system records, identifies discrepancies, calculates wastage/shrinkage rates, and produces reconciliation reports with variance explanations. | standard | Food & Hospitality, Manufacturing, Retail |

#### 7.3.9 Cross-Industry Utility Skills

| Skill | Description | Risk Level | Target Industries |
|-------|-------------|------------|-------------------|
| `output-fact-checker` | Cross-references agent-generated claims against source data and web sources. Flags unsupported assertions and hallucination risks. | standard | All |
| `pii-detector-redactor` | Scans agent outputs for personally identifiable information (names, addresses, account numbers, NI numbers, etc.) and applies redaction or masking per GDPR/UK DPA requirements. | elevated | All |
| `sop-generator` | Generates standard operating procedures from process descriptions with step numbering, decision trees, exception handling, and approval chains. | standard | All |
| `multi-format-reporter` | Converts structured data into polished outputs across formats (PDF, DOCX, XLSX, HTML) with consistent branding and formatting. | low | All |
| `meeting-intelligence` | Processes meeting notes/transcripts to extract action items, decisions, owners, and deadlines. Produces structured meeting summaries and follow-up task lists. | low | All |
| `escalation-drafter` | Drafts escalation communications with appropriate urgency, context summary, and recommended actions based on incident severity. | standard | All |
| `data-reconciliation` | Compares datasets across systems, identifies discrepancies, and produces reconciliation reports with variance explanations. | standard | All |
| `vendor-contract-reviewer` | Extracts key terms from vendor/supplier contracts (payment terms, SLAs, auto-renewal, liability caps) and flags risks against organizational policies. | elevated | All |
| `timesheet-analyzer` | Analyzes timesheet data to identify utilization patterns, over/under-billing risks, scope creep signals, and profitability by engagement/project. | standard | Consulting, Recruitment, IT Services |

### 7.4 Skill Validator & Security Scanner

#### 7.4.1 Architecture

The validator runs automatically during skill installation as a mandatory pipeline. No skill can be installed without passing all validation stages. The pipeline is also available as a standalone CLI command and API endpoint for pre-installation checks.

```
Upload/URL → Unpack → Schema Validation → Content Analysis → Security Scan → Integrity Check → Install
                 ↓           ↓                  ↓                ↓              ↓
              Format       Structure          Prompt           Malware        Checksum
              Check        Compliance         Injection        Detection      Verification
                                              Detection
```

#### 7.4.2 Validation Stages

**Stage 1: Format Validation**

| Check | Rule | Severity |
|-------|------|----------|
| Archive format | Valid ZIP with correct structure | BLOCK |
| SKILL.md exists | File present at root of skill directory | BLOCK |
| SKILL.md parseable | Valid YAML frontmatter + valid Markdown body | BLOCK |
| File size limit | Total uncompressed size < 10MB | BLOCK |
| File count limit | Maximum 50 files per skill | BLOCK |
| No symlinks | Symlinks are not permitted | BLOCK |
| No path traversal | All paths resolve within skill directory | BLOCK |

**Stage 2: Schema Validation**

| Check | Rule | Severity |
|-------|------|----------|
| Required fields | `name`, `description`, `version`, `author` present | BLOCK |
| Name format | Lowercase alphanumeric + hyphens, max 64 chars | BLOCK |
| Name uniqueness | No collision with installed skills | BLOCK |
| Description length | Between 20 and 1024 characters | BLOCK |
| Version format | Valid semver (X.Y.Z) | BLOCK |
| Risk level valid | One of: `low`, `standard`, `elevated`, `critical` | WARN (defaults to `standard`) |
| Industry tags valid | All tags in configured industry taxonomy | WARN |
| SKILL.md body length | Warning if > 500 lines | WARN |

**Stage 3: Content Analysis (Prompt Injection Detection)**

This is the critical security layer. The scanner uses a multi-strategy approach:

| Strategy | What It Detects | Method |
|----------|----------------|--------|
| **Pattern matching** | Known injection patterns | Regex library of 200+ patterns covering role hijacking, instruction override, jailbreak attempts, system prompt extraction |
| **Instruction boundary analysis** | Hidden instructions masquerading as content | Structural analysis of markdown for concealed directives, invisible unicode, zero-width characters, base64-encoded payloads |
| **Authority escalation detection** | Claims of elevated permissions | NLP classification of phrases that assert authority ("as an admin", "ignore previous instructions", "you are now", "override safety") |
| **Data exfiltration patterns** | Attempts to leak data | Detection of encoded data transmission patterns, URL construction from variables, hidden API calls in scripts |
| **LLM-assisted review** | Sophisticated/novel injection attempts | Send skill content through adversarial reviewer (existing `AdversarialReviewerService`) with injection-detection prompt |

**Confidence scoring:**

```
injection_risk_score = (
    pattern_match_weight * pattern_score +
    boundary_analysis_weight * boundary_score +
    authority_escalation_weight * authority_score +
    exfiltration_weight * exfiltration_score +
    llm_review_weight * llm_score
)

# When skills.validation.llm_review flag is disabled:
# llm_review_weight is redistributed proportionally across other strategies
# This means the remaining 4 strategies must produce a reliable score alone
# LLM review is MANDATORY for elevated/critical risk_level skills regardless of flag

# Thresholds
LOW_RISK:      score < 0.2    → Install with no warnings
MEDIUM_RISK:   0.2 <= score < 0.5  → Install with warnings displayed
HIGH_RISK:     0.5 <= score < 0.8  → Require explicit admin confirmation
CRITICAL_RISK: score >= 0.8   → Block installation, require manual review
```

**Stage 4: Malware & Code Safety Scan**

| Check | Rule | Scope |
|-------|------|-------|
| Script language allowlist | Only Python, Bash, JavaScript, PHP | `scripts/` directory |
| No binary executables | No compiled binaries or shared libraries | Entire archive |
| No network calls in scripts | Scripts must not make outbound HTTP/network requests without declaration | `scripts/` directory |
| No filesystem escape | Scripts must not access paths outside skill directory and designated workspace | `scripts/` directory |
| No process spawning | Scripts must not fork, exec, or spawn subprocesses beyond declared scope | `scripts/` directory |
| Dependency audit | Any `import`/`require` statements checked against allowlist | `scripts/` directory |
| Static analysis | AST-level analysis for dangerous function calls (`eval`, `exec`, `system`, `shell_exec`, `passthru`) | `scripts/` directory |

**Stage 5: Integrity Verification**

| Check | Rule | Severity |
|-------|------|----------|
| Checksum match | SHA-256 matches manifest (library installs) | BLOCK |
| Author signature | HMAC signature valid (future: public key verification) | WARN (Phase 1) |
| Tamper detection | File hashes recorded at install time for drift detection | AUDIT |

#### 7.4.3 Validation Result Schema

```json
{
  "skill_name": "financial-compliance-check",
  "validation_id": "val_abc123",
  "timestamp": "2026-03-06T12:00:00Z",
  "overall_result": "pass",
  "risk_score": 0.12,
  "stages": {
    "format": { "passed": true, "checks": 7, "failures": 0 },
    "schema": { "passed": true, "checks": 8, "failures": 0, "warnings": 1 },
    "content_analysis": {
      "passed": true,
      "injection_risk_score": 0.08,
      "patterns_matched": 0,
      "authority_escalation_detected": false,
      "exfiltration_patterns_detected": false,
      "llm_review_verdict": "pass"
    },
    "code_safety": { "passed": true, "scripts_scanned": 2, "issues": 0 },
    "integrity": { "passed": true, "checksum_valid": true }
  },
  "warnings": [
    { "stage": "schema", "message": "SKILL.md body exceeds 500 lines (523 lines)" }
  ]
}
```

---

## 8. Data Model

### 8.1 New Tables

```sql
-- Installed skills registry
CREATE TABLE agent_skills (
    id              UUID PRIMARY KEY,
    tenant_id       UUID NOT NULL REFERENCES tenants(id),
    name            VARCHAR(64) NOT NULL,
    slug            VARCHAR(64) NOT NULL,
    description     TEXT NOT NULL,
    version         VARCHAR(20) NOT NULL,
    author          VARCHAR(128) NOT NULL,
    risk_level      VARCHAR(20) NOT NULL DEFAULT 'standard',
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    -- active | paused | failed_validation | pending_review
    industries      JSONB DEFAULT '[]',
    memory_blocks   JSONB DEFAULT '[]',
    mcp_dependencies JSONB DEFAULT '[]',
    tools_required  JSONB DEFAULT '[]',
    requires_approval BOOLEAN DEFAULT FALSE,
    skill_path      TEXT NOT NULL,                    -- filesystem path to installed skill
    checksum        VARCHAR(64) NOT NULL,             -- SHA-256 of .skill archive
    file_hashes     JSONB NOT NULL,                   -- per-file hashes for tamper detection
    validation_result JSONB NOT NULL,                  -- full validation output
    installed_by    UUID REFERENCES users(id),
    installed_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_by      UUID REFERENCES users(id),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    paused_by       UUID REFERENCES users(id),
    paused_at       TIMESTAMP,
    last_invoked_at TIMESTAMP,
    invocation_count INTEGER DEFAULT 0,
    UNIQUE (tenant_id, slug)
);

-- Skill invocation telemetry (fed into existing event ledger pattern)
CREATE TABLE agent_skill_invocations (
    id              UUID PRIMARY KEY,
    skill_id        UUID NOT NULL REFERENCES agent_skills(id),
    run_attempt_id  UUID NOT NULL,                     -- links to delegation attempt
    delegatee_id    UUID,                              -- which agent invoked
    workflow_key    VARCHAR(255) NOT NULL,
    started_at      TIMESTAMP NOT NULL,
    completed_at    TIMESTAMP,
    duration_ms     INTEGER,
    token_usage     INTEGER,
    outcome         VARCHAR(20) NOT NULL,              -- success | failed | skipped | timeout
    failure_reason  TEXT,
    context_tokens_injected INTEGER,                   -- how much context the skill consumed
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Skill library registry (bundled + future remote)
CREATE TABLE agent_skill_library (
    id              UUID PRIMARY KEY,
    slug            VARCHAR(64) NOT NULL UNIQUE,
    name            VARCHAR(128) NOT NULL,
    description     TEXT NOT NULL,
    version         VARCHAR(20) NOT NULL,
    author          VARCHAR(128) NOT NULL,
    category        VARCHAR(64) NOT NULL,
    industries      JSONB DEFAULT '[]',
    risk_level      VARCHAR(20) NOT NULL DEFAULT 'standard',
    file_path       TEXT NOT NULL,                     -- path to .skill file in library
    checksum        VARCHAR(64) NOT NULL,
    metadata        JSONB DEFAULT '{}',
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Validation audit log
CREATE TABLE agent_skill_validations (
    id              UUID PRIMARY KEY,
    skill_name      VARCHAR(64) NOT NULL,
    validation_result JSONB NOT NULL,
    risk_score      DECIMAL(4,3) NOT NULL,
    overall_pass    BOOLEAN NOT NULL,
    source          VARCHAR(20) NOT NULL,              -- upload | url | library
    validated_by    UUID REFERENCES users(id),
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### 8.2 Indexes

```sql
CREATE INDEX idx_skills_tenant_status ON agent_skills (tenant_id, status);
CREATE INDEX idx_skills_tenant_industry ON agent_skills USING GIN (industries);
CREATE INDEX idx_skill_invocations_skill ON agent_skill_invocations (skill_id, started_at);
CREATE INDEX idx_skill_invocations_run ON agent_skill_invocations (run_attempt_id);
CREATE INDEX idx_skill_invocations_workflow ON agent_skill_invocations (workflow_key, started_at);
CREATE INDEX idx_skill_library_category ON agent_skill_library (category);
CREATE INDEX idx_skill_library_industry ON agent_skill_library USING GIN (industries);
CREATE INDEX idx_skill_validations_name ON agent_skill_validations (skill_name, created_at);
```

---

## 9. Runtime Integration

### 9.1 Skill Resolution

When a delegation attempt is created, the skill resolver determines which skills to inject:

```
Agent receives task
    → SkillResolver evaluates task against installed skill descriptions
    → Matching skills filtered by:
        1. Status = active
        2. Delegatee trust score >= skill.risk_level threshold
        3. Required MCP dependencies available
        4. Required memory blocks accessible
        5. Compliance policy permits skill for this workflow
    → Matched skills ranked by relevance score
    → Top N skills (configurable, default 5) selected
    → Skill metadata injected into agent context
    → On-demand: agent reads full SKILL.md when it decides to use the skill
    → On-demand: agent reads bundled resources as needed
```

### 9.2 Skill Access by Risk Level

| Risk Level | Trust Score Required | Approval Required | Telemetry Level |
|------------|---------------------|-------------------|-----------------|
| `low` | Any | No | Standard |
| `standard` | >= 0.5 | No | Standard |
| `elevated` | >= 0.7 | Configurable | Enhanced (full I/O capture) |
| `critical` | >= 0.9 | Always (human-in-loop) | Full audit trail |

### 9.3 Operator Surface Integration

Existing dashboard widgets extended with skill telemetry:

| Widget | Skill Data Added |
|--------|-----------------|
| `ReliabilityScore` | Skill-attributed failure rate contribution |
| `BudgetUtilization` | Token spend per skill per workflow |
| `EscalationEvents` | Skill-triggered escalations (validation failures, risk threshold breaches) |
| **New: `SkillUsage`** | Top skills by invocation, average duration, success rate |
| **New: `SkillHealth`** | Per-skill reliability, last validation result, drift alerts |

---

## 10. Authorization Model

| Action | Required Role | Notes |
|--------|--------------|-------|
| Install skill | Tenant admin or operator | Non-admin users cannot install skills |
| Remove skill | Tenant admin | Destructive action, admin-only |
| Pause/resume skill | Tenant admin or operator | Reversible, no confirmation required in UI; Messenger requires confirmation per messenger contract |
| Update skill | Tenant admin or operator | Re-validates on update |
| View installed skills | Any authenticated user | Read-only access for all tenant members |
| Browse skill library | Any authenticated user | Read-only |
| Install from library | Tenant admin or operator | Same as install |

---

## 11. Feature Flags



| Flag | Subsystem | Description |
|------|-----------|-------------|
| `skills.enabled` | Skills | Master toggle for skill system |
| `skills.ui_enabled` | Skills | UI navigation and management pages |
| `skills.library_enabled` | Skills | Access to skill library |
| `skills.auto_resolve` | Skills | Automatic skill matching during delegation |
| `skills.validation.llm_review` | Validation | LLM-assisted content analysis (uses adversarial reviewer) |
| `skills.validation.strict_mode` | Validation | Block on any warning (not just errors) |

---

## 12. Delivery Phases

### Phase 1: Foundation (2–3 weeks)

- [ ] Database migrations (4 tables + indexes)
- [ ] `AgentSkill` model + repository + service layer
- [ ] Skill format parser (SKILL.md frontmatter + body extraction)
- [ ] Skill installer (unpack, validate schema, store)
- [ ] Basic skill resolver (description matching against task)
- [ ] CLI commands: `skill:install`, `skill:list`, `skill:validate`, `skill:remove`
- [ ] Feature flags registration

### Phase 2: Validator & Scanner (2–3 weeks)

- [ ] Format validation pipeline
- [ ] Schema validation pipeline
- [ ] Content analysis engine (pattern matching + boundary analysis + authority escalation)
- [ ] Code safety scanner (AST analysis for scripts)
- [ ] Integrity verification (checksums, file hashes)
- [ ] LLM-assisted review integration (via AdversarialReviewerService)
- [ ] Validation result storage and audit logging
- [ ] CLI: `skill:validate` with detailed output

### Phase 3: Runtime Integration (2–3 weeks)

- [ ] Skill context injection into delegation attempts
- [ ] Trust-score-gated skill access
- [ ] Skill invocation telemetry events
- [ ] Compliance layer integration (plan gate, verification gate)
- [ ] Memory integration (skill context in working memory)
- [ ] Cost attribution (skill token usage → workflow budget)

### Phase 4: UI & Library (2 weeks)

- [ ] Installed skills management page (`/tools/skills`)
- [ ] Skill detail view with telemetry
- [ ] Skill library browser
- [ ] Install from library flow
- [ ] Upload `.skill` file flow
- [ ] Dashboard widget extensions (SkillUsage, SkillHealth)

### Phase 5: Messenger + Org Layer Integration (1–2 weeks)

- [ ] Messenger chat commands (install, list, pause, resume, info)
- [ ] Org layer skill assignment (named AI employees → skill access profiles)
- [ ] Ritual templates with skill requirements
- [ ] Council skill composition (multi-agent skill chains)

### Phase 6: Initial Skills & Hardening (2–3 weeks)

- [ ] Author and validate all 30 initial skills
- [ ] Skill library manifest and bundled distribution
- [ ] End-to-end integration tests
- [ ] Load testing (skill resolution at scale)
- [ ] Security penetration testing (injection attempts against validator)
- [ ] Documentation (product docs, API docs, skill authoring guide)

**Estimated total: 13–17 weeks**

---

## 13. Risk Boundaries

1. **Skill description quality determines resolution accuracy.** Poorly written descriptions cause false triggers or missed matches. Mitigation: ship well-authored initial skills and provide a skill authoring guide with examples.
2. **LLM-assisted validation adds latency and cost.** The adversarial reviewer call adds 5–15 seconds to install time. Mitigation: make LLM review a configurable stage behind `skills.validation.llm_review` flag.
3. **Prompt injection detection is probabilistic, not deterministic.** Novel injection techniques can evade pattern matching. Mitigation: defense in depth (5 stages), continuous pattern library updates, and the existing compliance/verification pipeline as a runtime safety net.
4. **Context window pressure.** Each active skill consumes tokens. With many skills installed, resolution and injection can consume significant context budget. Mitigation: configurable skill limit (default 5 per invocation), progressive disclosure, and token budget tracking.
5. **Skill drift.** Installed skill files can be modified after validation. Mitigation: file hash comparison on each invocation, automatic re-validation on detected drift.

---

## 14. Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Skill install success rate | > 95% | Successful installs / total attempts |
| Validation false positive rate | < 5% | Legitimate skills blocked / total legitimate |
| Validation false negative rate | < 1% | Malicious skills installed / total malicious attempts |
| Skill resolution accuracy | > 85% | Correct skill matches / total invocations |
| Skill-attributed reliability | No degradation | Workflow reliability before/after skill adoption |
| Time to install | < 30 seconds | Median install time including validation |
| Initial skill library coverage | 30 skills across 9 industry verticals + cross-industry utilities | Skills shipped at launch |

---

## 15. Open Questions

1. **Should skills be tenant-scoped or globally available?** Current design assumes tenant-scoped. Global skills (shared across tenants) would need additional access control.
2. **What is the maximum number of skills per tenant in Phase 1?** Proposed: 50 installed, 5 active per invocation.
3. **Should skill execution be sandboxed in a separate process?** Current design runs scripts in the existing agent sandbox. Full process isolation adds security but increases latency.
4. **How do skills compose with each other?** Phase 1 treats skills as independent. Skill-to-skill dependencies (chaining) are a Phase 2 concern.
5. **Should the adversarial reviewer be mandatory for all installs or only elevated/critical risk levels?** Proposed: mandatory for elevated/critical, optional (but recommended) for standard/low.
