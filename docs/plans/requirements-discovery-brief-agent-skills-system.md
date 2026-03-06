# Implementation Plan

Derived from discovery session 3.

# Agent Skills (Pluggable Capability Layer) — Implementation Plan

## Section 1: Database Foundation

### 1.1 Migration: `agent_skills` table
Create migration `2026_03_07_000001_create_agent_skills_table.php` following existing project convention (Blueprint-based, PostgreSQL-aware).

**Columns** (per locked summary — `team_id` as `BIGINT UNSIGNED` FK to `teams(id)`):
- `id` UUID primary key (use `HasUuids` trait pattern from `OrgAgentProfile`)
- `team_id` unsignedBigInteger, foreign key to `teams(id)`, NOT NULL
- `name` varchar(64) NOT NULL
- `slug` varchar(64) NOT NULL
- `description` text NOT NULL
- `version` varchar(20) NOT NULL
- `author` varchar(128) NOT NULL
- `risk_level` varchar(20) NOT NULL default `standard`
- `status` varchar(20) NOT NULL default `active` (active|paused|failed_validation|pending_review)
- `industries` jsonb default `[]`
- `memory_blocks` jsonb default `[]`
- `mcp_dependencies` jsonb default `[]`
- `tools_required` jsonb default `[]`
- `trigger_keywords` jsonb default `[]`
- `run_after` jsonb default `[]`
- `requires_approval` boolean default false
- `skill_path` text NOT NULL
- `checksum` varchar(64) NOT NULL
- `file_hashes` jsonb NOT NULL
- `validation_result` jsonb NOT NULL
- `is_global` boolean default false
- `installed_by` unsignedBigInteger nullable FK `users(id)`
- `installed_at` timestamp NOT NULL default NOW()
- `updated_by` unsignedBigInteger nullable FK `users(id)`
- `updated_at` timestamp NOT NULL default NOW()
- `paused_by` unsignedBigInteger nullable FK `users(id)`
- `paused_at` timestamp nullable
- `last_invoked_at` timestamp nullable
- `invocation_count` integer default 0
- UNIQUE constraint on `(team_id, slug)`

**Indexes:**
- `idx_skills_team_status` on `(team_id, status)`
- `idx_skills_team_industry` GIN on `industries`
- `idx_skills_trigger_keywords` GIN on `trigger_keywords`

### 1.2 Migration: `agent_skill_validations` table
Create migration `2026_03_07_000002_create_agent_skill_validations_table.php`.

**Columns:**
- `id` UUID primary key
- `skill_name` varchar(64) NOT NULL
- `team_id` unsignedBigInteger NOT NULL FK `teams(id)`
- `validation_result` jsonb NOT NULL
- `risk_score` decimal(4,3) NOT NULL
- `overall_pass` boolean NOT NULL
- `source` varchar(20) NOT NULL (upload|url|library)
- `validated_by` unsignedBigInteger nullable FK `users(id)`
- `created_at` timestamp NOT NULL default NOW()

**Index:** `idx_skill_validations_name` on `(skill_name, created_at)`

### 1.3 Model: `AgentSkill`
Location: `app/Models/AgentSkill.php`

- Use `HasUuids` trait (same as `OrgAgentProfile`)
- Use `HasFactory` trait
- Define `$fillable` array with all columns
- Define `casts()`: jsonb columns → `array`, booleans → `boolean`, timestamps → `datetime`, `invocation_count` → `integer`
- Relationships: `belongsTo(Team::class)`, `belongsTo(User::class, 'installed_by')`, `belongsTo(User::class, 'updated_by')`, `belongsTo(User::class, 'paused_by')`
- Scopes: `scopeActive(Builder)`, `scopeForTeam(Builder, int $teamId)`, `scopeGlobal(Builder)`, `scopeByRiskLevel(Builder, string $level)`
- Status constants: `STATUS_ACTIVE`, `STATUS_PAUSED`, `STATUS_FAILED_VALIDATION`, `STATUS_PENDING_REVIEW`
- Risk level constants: `RISK_LOW`, `RISK_STANDARD`, `RISK_ELEVATED`, `RISK_CRITICAL`

### 1.4 Model: `AgentSkillValidation`
Location: `app/Models/AgentSkillValidation.php`

- Use `HasUuids`, `HasFactory`
- Define `$fillable`, `casts()` (jsonb → array, boolean, decimal)
- Relationship: `belongsTo(Team::class)`, `belongsTo(User::class, 'validated_by')`

---

## Section 2: Configuration & Feature Flags

### 2.1 Config extension: `config/agent.php`
Add `skills` key to the existing return array in `config/agent.php` (after line ~278, before closing bracket):

```php
'skills' => [
    'storage_path' => storage_path('app/skills'),
    'global_storage_path' => storage_path('app/skills/global'),
    'library_manifest_path' => base_path('skill-library/manifest.json'),
    'max_skills_per_invocation' => (int) env('AGENT_SKILLS_MAX_PER_INVOCATION', 5),
    'max_skills_per_team' => (int) env('AGENT_SKILLS_MAX_PER_TEAM', 50),
    'max_skill_archive_size_bytes' => 10 * 1024 * 1024, // 10MB
    'max_skill_file_count' => 50,
    'max_skill_body_lines_warning' => 500,
    'risk_level_trust_thresholds' => [
        'low' => 0.0,
        'standard' => 0.5,
        'elevated' => 0.7,
        'critical' => 0.9,
    ],
    'validation' => [
        'weights_without_llm' => [
            'pattern_match' => 0.35,
            'boundary_analysis' => 0.25,
            'authority_escalation' => 0.25,
            'exfiltration' => 0.15,
        ],
        'weights_with_llm' => [
            'pattern_match' => 0.25,
            'boundary_analysis' => 0.20,
            'authority_escalation' => 0.20,
            'exfiltration' => 0.10,
            'llm_review' => 0.25,
        ],
        'thresholds' => [
            'clean' => 0.2,
            'warn' => 0.5,
            'admin_confirm' => 0.8,
        ],
        'script_language_allowlist' => ['python', 'bash', 'javascript', 'php'],
        'dangerous_functions' => ['eval', 'exec', 'system', 'shell_exec', 'passthru'],
    ],
    'drift_check' => [
        'enabled' => (bool) env('AGENT_SKILLS_DRIFT_CHECK_ENABLED', true),
    ],
    'trigger_keyword_extraction' => [
        'max_keywords' => 20,
        'min_keyword_length' => 3,
    ],
],
```

### 2.2 Feature flags registration
Add 6 new constants to `app/Support/Agent/FeatureFlagManager.php` (after existing constants around line 73):

```php
public const SKILLS_ENABLED = 'skills.enabled';
public const SKILLS_UI_ENABLED = 'skills.ui_enabled';
public const SKILLS_LIBRARY_ENABLED = 'skills.library_enabled';
public const SKILLS_AUTO_RESOLVE = 'skills.auto_resolve';
public const SKILLS_VALIDATION_LLM_REVIEW = 'skills.validation.llm_review';
public const SKILLS_VALIDATION_STRICT_MODE = 'skills.validation.strict_mode';
```

Seed default values in `AgentFeatureSetting` table via a seeder or migration (all defaulting to `false`).

### 2.3 Horizon supervisor
Add to `config/horizon.php` defaults array (after existing supervisors):

```php
'supervisor-skill-validation' => [
    'connection' => 'redis',
    'queue' => ['skill-validation'],
    'balance' => 'auto',
    'autoScalingStrategy' => 'time',
    'maxProcesses' => max(1, min(4, (int) env('HORIZON_SKILL_VALIDATION_MAX_PROCESSES', 2))),
    'maxTime' => 0,
    'maxJobs' => 0,
    'memory' => 128,
    'tries' => 3,
    'backoff' => [10, 30, 60],
    'timeout' => 120,
    'nice' => 0,
],
```

Add corresponding wait entry: `'redis:skill-validation' => 30`.

Add environment overrides in `production` and `local` blocks matching existing pattern.

### 2.4 Storage directories
Create `.gitkeep` files at:
- `storage/app/skills/.gitkeep`
- `storage/app/skills/global/.gitkeep`
- `skill-library/.gitkeep`

Add `storage/app/skills` to `config/agent.php` `allowed_task_markdown_bases` so skills can be read by the agent runtime.

---

## Section 3: Skill Format Parser

### 3.1 `SkillParser` service
Location: `app/Services/Skills/SkillParser.php`

**Responsibility:** Parse SKILL.md files — extract YAML frontmatter (standard fields at root + `x-agent` namespace block), markdown body, and trigger keywords.

**Methods:**
- `parse(string $skillMdContent): SkillParseResult` — Main entry. Splits YAML frontmatter from markdown body. Parses standard agentskills.io fields (`name`, `description`, `version`, `author`, `license`). Parses `x-agent` block for platform extensions (`industries`, `risk_level`, `requires_approval`, `memory_blocks`, `mcp_dependencies`, `tools`, `trigger_keywords`, `run_after`, `compatibility`). Unknown keys in `x-agent` produce warnings, not errors.
- `extractTriggerKeywords(string $description, array $authorKeywords): array` — Hybrid keyword extraction. Auto-extracts from description using NLP (stopword removal, noun phrase extraction, n-gram extraction). Merges with author-provided `x-agent.trigger_keywords`. Deduplicates. Caps at configured `max_keywords`.
- `validateFrontmatter(array $parsed): array` — Returns array of validation issues (blocking errors vs warnings).

**DTOs:**
- `app/Services/Skills/DTOs/SkillParseResult.php` — Contains: `name`, `description`, `version`, `author`, `license`, `riskLevel`, `industries[]`, `memoryBlocks[]`, `mcpDependencies[]`, `toolsRequired[]`, `triggerKeywords[]`, `runAfter[]`, `requiresApproval`, `compatibility`, `markdownBody`, `warnings[]`, `rawFrontmatter[]`.

**Implementation notes:**
- Use `symfony/yaml` for YAML parsing (already available in Laravel)
- Frontmatter delimited by `---` markers
- Description max 1024 chars, name max 64 chars lowercase+hyphens, version must be valid semver
- Risk level defaults to `standard` if missing/invalid (with warning)
- Unknown root-level keys ignored silently; unknown `x-agent` keys produce warnings

### 3.2 Trigger keyword NLP extraction
Location: Within `SkillParser::extractTriggerKeywords()`

**Approach:** Lightweight PHP-native extraction (no external NLP service needed):
1. Tokenize description, remove stopwords (hardcoded English stopword list)
2. Extract significant nouns and noun phrases via regex patterns
3. Extract domain terms (multi-word phrases in quotes or capitalized sequences)
4. Score by TF-IDF-like frequency against a generic corpus baseline
5. Return top N keywords sorted by relevance score
6. Merge with author keywords, deduplicate

---

## Section 4: Validation Pipeline

### 4.1 `SkillValidator` orchestrator
Location: `app/Services/Skills/SkillValidator.php`

**Responsibility:** Orchestrate the 5-stage validation pipeline. Each stage returns a stage result. Pipeline short-circuits on any BLOCK-severity failure.

**Method:** `validate(string $extractedPath, SkillParseResult $parsed, string $source, int $teamId): SkillValidationResult`

**DTO:** `app/Services/Skills/DTOs/SkillValidationResult.php` — Contains: `overallPass`, `riskScore`, `stages[]` (each with `name`, `passed`, `checks`, `failures`, `warnings`, `details`), `warnings[]`, `blockingErrors[]`.

### 4.2 Stage 1: `FormatValidator`
Location: `app/Services/Skills/Validation/FormatValidator.php`

**Checks (all BLOCK severity):**
- Archive extracted successfully (valid directory structure)
- `SKILL.md` exists at root
- `SKILL.md` parseable (valid YAML + valid Markdown)
- Total uncompressed size < 10MB (`max_skill_archive_size_bytes` from config)
- File count ≤ 50 (`max_skill_file_count` from config)
- No symlinks (recursive scan)
- No path traversal (all resolved paths within skill directory)

### 4.3 Stage 2: `SchemaValidator`
Location: `app/Services/Skills/Validation/SchemaValidator.php`

**Checks:**
- BLOCK: Required fields present (`name`, `description`, `version`, `author`)
- BLOCK: Name format (lowercase alphanumeric + hyphens, ≤64 chars, regex: `/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/`)
- BLOCK: Name uniqueness per team (query `agent_skills` for `team_id` + `slug`)
- BLOCK: Description length 20–1024 chars
- BLOCK: Version is valid semver (regex validation)
- WARN: Risk level valid enum value (defaults to `standard` if invalid)
- WARN: Industry tags in configured taxonomy (log unrecognized)
- WARN: SKILL.md body >500 lines
- WARN: Unknown `x-agent` keys

### 4.4 Stage 3: `ContentAnalysisValidator`
Location: `app/Services/Skills/Validation/ContentAnalysisValidator.php`

**Sub-analyzers** (each returns a 0.0–1.0 score):

1. **`PatternMatchAnalyzer`** (`app/Services/Skills/Validation/Analyzers/PatternMatchAnalyzer.php`):
   - 200+ regex patterns stored in a config file `config/skills-injection-patterns.php`
   - Categories: role hijacking, instruction override, jailbreak, system prompt extraction
   - Score = matched_patterns / (total_content_tokens * sensitivity_factor), clamped to [0, 1]

2. **`BoundaryAnalyzer`** (`app/Services/Skills/Validation/Analyzers/BoundaryAnalyzer.php`):
   - Detects concealed directives in markdown (hidden in HTML comments, invisible unicode U+200B/U+200C/U+200D/U+FEFF, zero-width joiners)
   - Detects base64-encoded payloads
   - Structural analysis for directive-like patterns in non-instruction sections

3. **`AuthorityEscalationAnalyzer`** (`app/Services/Skills/Validation/Analyzers/AuthorityEscalationAnalyzer.php`):
   - NLP classification of phrases: "as an admin", "ignore previous instructions", "you are now", "override safety", "disregard", "forget your instructions"
   - Phrase dictionary + fuzzy matching
   - Score weighted by phrase severity

4. **`ExfiltrationAnalyzer`** (`app/Services/Skills/Validation/Analyzers/ExfiltrationAnalyzer.php`):
   - Detects URL construction from variables/concatenation
   - Detects encoded data transmission patterns (base64 encoding calls in scripts)
   - Detects hidden API calls, webhook URLs in scripts
   - Scans both SKILL.md and `scripts/` directory

5. **`LlmReviewAnalyzer`** (`app/Services/Skills/Validation/Analyzers/LlmReviewAnalyzer.php`):
   - Wraps `AdversarialReviewerService` with a skill-injection-detection prompt
   - Returns score 0.0–1.0 based on reviewer verdict
   - Graceful fallback: if service unavailable, returns null (triggers weight redistribution)
   - **Mandatory** for elevated/critical risk skills regardless of `skills.validation.llm_review` flag

**Scoring logic:**
- Read `skills.validation.llm_review` feature flag
- Determine if LLM review needed: flag enabled OR risk_level in [elevated, critical]
- If LLM review active and service available: use 5-strategy weights from config
- If LLM review active but service unavailable: use 4-strategy weights, log warning
- If LLM review not needed: use 4-strategy weights
- `injection_risk_score = sum(weight_i * score_i)`
- Apply thresholds: <0.2 clean, 0.2–0.5 warn, 0.5–0.8 admin_confirm, ≥0.8 block

### 4.5 Stage 4: `CodeSafetyValidator`
Location: `app/Services/Skills/Validation/CodeSafetyValidator.php`

Scans only the `scripts/` directory (skipped if no scripts present).

**Checks:**
- BLOCK: Script language in allowlist (detect by extension: .py, .sh, .js, .php)
- BLOCK: No binary executables (detect by file magic bytes / extension)
- BLOCK: No undeclared network calls (regex scan for `curl`, `wget`, `fetch`, `http`, `requests.get`, `file_get_contents` with URL patterns)
- BLOCK: No filesystem escape (paths with `../`, absolute paths outside skill dir)
- BLOCK: No process spawning beyond declared scope (`fork`, `exec`, `spawn`, `popen`, `proc_open`)
- BLOCK: AST analysis for dangerous functions (`eval`, `exec`, `system`, `shell_exec`, `passthru`) — PHP uses `token_get_all`, JS/Python use regex-based detection
- WARN: Dependency audit — `import`/`require` statements checked against an allowlist

### 4.6 Stage 5: `IntegrityValidator`
Location: `app/Services/Skills/Validation/IntegrityValidator.php`

**Checks:**
- BLOCK: SHA-256 checksum matches manifest (for library installs; skipped for uploads)
- AUDIT: Record per-file SHA-256 hashes for drift detection
- WARN: HMAC signature validation (Phase 1 — warn only, infrastructure for future enforcement)

### 4.7 Injection pattern configuration
Location: `config/skills-injection-patterns.php`

Returns array of categorized regex patterns:
```php
return [
    'role_hijacking' => [...],
    'instruction_override' => [...],
    'jailbreak' => [...],
    'system_prompt_extraction' => [...],
    'authority_assertion' => [...],
    'data_exfiltration' => [...],
];
```

---

## Section 5: Skill Installer

### 5.1 `SkillInstaller` service
Location: `app/Services/Skills/SkillInstaller.php`

**Responsibility:** Orchestrate the full install flow: unpack → parse → validate → store → persist DB record.

**Methods:**
- `installFromFile(string $filePath, int $teamId, int $userId, bool $isGlobal = false): SkillInstallResult`
- `installFromUrl(string $url, int $teamId, int $userId): SkillInstallResult`
- `installFromLibrary(string $slug, int $teamId, int $userId): SkillInstallResult`
- `update(string $skillId, string $filePath, int $userId): SkillInstallResult` — Re-validates, replaces files, updates DB
- `remove(string $skillId, int $userId): void` — Delete files + soft-handle DB (delete row or mark removed)

**Install flow:**
1. Extract ZIP to temp directory (`storage/app/skills/tmp/{uuid}`)
2. Run `SkillParser::parse()` on SKILL.md
3. Run `SkillValidator::validate()` — full 5-stage pipeline
4. If validation fails with BLOCK: return failure result, clean up temp
5. If risk_score ≥ 0.5 and < 0.8: return `requires_admin_confirmation` in result
6. If risk_score ≥ 0.8: return blocked result
7. On success: move from temp to `storage/app/skills/{team_id}/{slug}/` (or `global/{slug}/`)
8. Create `AgentSkill` row with all parsed metadata, validation result, file hashes
9. Create `AgentSkillValidation` audit row
10. Clean up temp directory

**DTO:** `app/Services/Skills/DTOs/SkillInstallResult.php` — Contains: `success`, `skillId`, `validationResult`, `requiresAdminConfirmation`, `blocked`, `errors[]`, `warnings[]`.

### 5.2 `ValidateSkillJob`
Location: `app/Jobs/Skills/ValidateSkillJob.php`

- Queue: `skill-validation` (uses new Horizon supervisor)
- For async validation when installing from URL (download + validate)
- Dispatched by `SkillInstaller` for URL-based installs
- Retries: 3, backoff: [10, 30, 60]

---

## Section 6: Skill Library

### 6.1 `SkillLibrary` service
Location: `app/Services/Skills/SkillLibrary.php`

**Responsibility:** Read `skill-library/manifest.json` at runtime. No database table.

**Methods:**
- `browse(array $filters = []): Collection` — List all library skills, optionally filtered by category, industry, risk_level
- `search(string $query): Collection` — Search by name/description
- `find(string $slug): ?SkillLibraryEntry` — Find single skill by slug
- `getManifest(): array` — Parse and cache manifest.json (cache with file mtime invalidation)

**DTO:** `app/Services/Skills/DTOs/SkillLibraryEntry.php` — slug, name, description, version, author, category, industries[], risk_level, filePath, checksum.

### 6.2 Manifest file structure
Location: `skill-library/manifest.json`

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
      "category": "compliance",
      "industries": ["financial-services"],
      "risk_level": "elevated",
      "file": "skills/financial-compliance-check.skill",
      "checksum": "sha256:..."
    }
  ]
}
```

### 6.3 Initial skills authoring (31 skills)
Location: `skill-library/skills/` directory

Each skill is a `.skill` ZIP file containing:
- `SKILL.md` with proper frontmatter (standard fields + `x-agent` block)
- Optional `references/` with domain-specific documentation
- Optional `assets/` with templates

**Skills by category** (per locked summary):

Accounting & Financial (4): `financial-compliance-check`, `client-report-generator`, `engagement-letter-generator`, `insurance-renewal-processor`

Legal (3): `contract-clause-reviewer`, `client-communication-reviewer`, `case-file-summarizer`

Healthcare & Care (3): `care-compliance-checker`, `rota-optimizer`, `clinical-document-processor`

Facilities & Construction (3): `compliance-inspection-reporter`, `job-costing-analyzer`, `waste-transfer-processor`

Logistics & Supply Chain (3): `shipment-document-processor`, `delivery-performance-analyzer`, `supplier-compliance-monitor`

Recruitment (2): `candidate-screening-processor`, `placement-compliance-checker`

Property (2): `property-listing-generator`, `tenancy-compliance-checker`

Food & Manufacturing (2): `food-safety-compliance`, `stock-reconciliation`

Cross-Industry Utilities (9): `output-fact-checker`, `pii-detector-redactor`, `sop-generator`, `multi-format-reporter`, `meeting-intelligence`, `escalation-drafter`, `data-reconciliation`, `vendor-contract-reviewer`, `timesheet-analyzer`

Each `.skill` must pass the full validation pipeline before inclusion. Generate SHA-256 checksums for manifest.

---

## Section 7: Runtime Integration

### 7.1 `SkillResolver` service
Location: `app/Services/Skills/SkillResolver.php`

**Responsibility:** Hybrid skill resolution — keyword pre-filter then LLM ranking.

**Methods:**
- `resolve(string $taskDescription, int $teamId, DelegateeProfile $delegatee, array $availableMcpTools = [], array $availableMemoryBlocks = []): array` — Returns ordered array of `ResolvedSkill` DTOs (max N from config).

**Resolution algorithm:**
1. Load all active skills for team + global skills where `is_global = true`
2. **Pre-filter (keyword/tag matching):**
   - Compare task description tokens against each skill's `trigger_keywords` (intersection scoring)
   - Compare task `industries` context (if available) against skill `industries`
   - Score = keyword_overlap * 0.7 + industry_overlap * 0.3
   - Keep top 15 candidates (configurable) that score above minimum threshold
3. **Apply hard filters on candidates:**
   - `status = active`
   - Delegatee `trust_score` >= skill risk_level threshold (from `config('agent.skills.risk_level_trust_thresholds')`)
   - Required MCP dependencies available (check `$availableMcpTools`)
   - Required memory blocks accessible (check `$availableMemoryBlocks`)
   - Compliance policy permits (invoke `OrchestrationPolicyService` if compliance enabled)
4. **LLM ranking** (when `skills.auto_resolve` flag enabled):
   - Send filtered candidates' descriptions + task description to LLM for relevance ranking
   - Use `RuntimeLlmClient` (existing service) with a ranking prompt
   - Parse ranked results
   - Fallback: if LLM unavailable, use keyword pre-filter scores as final ranking
5. **Select top N** (default 5 from config)
6. **Order by `run_after` DAG** — topological sort respecting declared dependencies

**DTO:** `app/Services/Skills/DTOs/ResolvedSkill.php` — `skillId`, `name`, `slug`, `description`, `riskLevel`, `relevanceScore`, `triggerKeywords`, `runAfter`, `skillPath`.

### 7.2 `SkillContextInjector` service
Location: `app/Services/Skills/SkillContextInjector.php`

**Responsibility:** Inject skill metadata into delegation context. Manages progressive disclosure.

**Methods:**
- `injectMetadata(array $resolvedSkills, string $existingContext): string` — Injects skill metadata block after STAR preamble, before memory context. Each skill rendered as: name, description, trigger keywords. ~100 tokens per skill.
- `loadFullInstructions(string $skillId): string` — Loads full SKILL.md body on-demand (<5000 tokens target). Called when agent decides to use a specific skill.
- `loadResource(string $skillId, string $resourcePath): string` — Loads bundled resource file on-demand (references/, assets/).

**Context injection point:**
Modify `app/Jobs/ExecuteAgentRunJob.php` — after STAR preamble injection (around line ~80 area where `StarPreambleGenerator` is used) and before memory context injection (where `MemoryContextBuilder` is called). Add skill context injection step:
1. Check `skills.enabled` feature flag
2. Invoke `SkillResolver::resolve()` with task description
3. Invoke `SkillContextInjector::injectMetadata()` with resolved skills
4. Append to task markdown content

**Token budget allocation for ordered chains:**
When skills declare `run_after` dependencies, allocate tokens in DAG order. Earlier skills get priority. Track consumed tokens. Later skills receive remaining budget and degrade gracefully (shorter instructions, skip resources).

### 7.3 `SkillDriftDetector` service
Location: `app/Services/Skills/SkillDriftDetector.php`

**Responsibility:** Dual-trigger drift detection.

**Methods:**
- `checkOnInvocation(AgentSkill $skill): DriftCheckResult` — Lightweight hash comparison of all installed files against stored `file_hashes`. On mismatch: return drift-detected result. Caller blocks invocation, sets status to `pending_review`, alerts operator.
- `checkAll(int $teamId = null): Collection` — Scans all installed skills (or for specific team). Returns collection of drift results. Used by scheduled command.
- `computeFileHashes(string $skillPath): array` — SHA-256 hash each file in skill directory.

**DTO:** `app/Services/Skills/DTOs/DriftCheckResult.php` — `hasDrift`, `skillId`, `changedFiles[]`, `missingFiles[]`, `addedFiles[]`.

### 7.4 `SkillTelemetryRecorder` service
Location: `app/Services/Skills/SkillTelemetryRecorder.php`

**Responsibility:** Dual-write on skill invocation events.

**Methods:**
- `recordInvocation(AgentSkill $skill, string $runAttemptId, string $workflowKey, string $outcome, int $durationMs, int $tokenUsage, ?string $failureReason = null): void`

**Dual-write logic:**
1. Insert into `telemetry_event_ledger` (append-only):
   - `event_type`: `skill.invoked`, `skill.completed`, or `skill.failed`
   - `payload_json`: `{ "skill_id": "...", "skill_version": "...", "team_id": ..., "duration_ms": ..., "token_usage": ..., "outcome": "...", "skill_name": "..." }`
   - Use `IngestionService` (existing telemetry service) for ledger insert
2. Atomic update on `agent_skills` row:
   - `DB::table('agent_skills')->where('id', $skillId)->update(['invocation_count' => DB::raw('invocation_count + 1'), 'last_invoked_at' => now()])`

---

## Section 8: CLI Commands

### 8.1 `skill:install`
Location: `app/Console/Commands/Skills/SkillInstallCommand.php`
Signature: `skill:install {path-or-url} {--team= : Team ID} {--global : Install as global skill}`

- Detects if argument is URL or file path
- Resolves team from `--team` option or current user's personal team
- For global: validates user is personal team owner
- Calls `SkillInstaller::installFromFile()` or `installFromUrl()`
- Outputs validation progress and results
- If `requires_admin_confirmation`: prompts with `$this->confirm()`
- On success: displays skill name, version, risk level, status

### 8.2 `skill:list`
Location: `app/Console/Commands/Skills/SkillListCommand.php`
Signature: `skill:list {--team= : Team ID} {--status= : Filter by status}`

- Table output: name, version, status, risk_level, invocation_count, last_invoked_at
- Includes global skills

### 8.3 `skill:validate`
Location: `app/Console/Commands/Skills/SkillValidateCommand.php`
Signature: `skill:validate {path}`

- Runs full 5-stage validation pipeline without installing
- Outputs detailed per-stage results
- Returns exit code 0 on pass, 1 on fail

### 8.4 `skill:remove`
Location: `app/Console/Commands/Skills/SkillRemoveCommand.php`
Signature: `skill:remove {name} {--team= : Team ID} {--force : Skip confirmation}`

- Confirmation prompt unless `--force`
- Calls `SkillInstaller::remove()`
- Deletes files and DB record

### 8.5 `skill:library`
Location: `app/Console/Commands/Skills/SkillLibraryCommand.php`
Signature: `skill:library {--category= : Filter by category} {--industry= : Filter by industry}`

- Lists available library skills from manifest.json
- Table output: slug, name, category, risk_level, version

### 8.6 `skill:library:install`
Location: `app/Console/Commands/Skills/SkillLibraryInstallCommand.php`
Signature: `skill:library:install {slug} {--team= : Team ID}`

- Finds skill in manifest
- Calls `SkillInstaller::installFromLibrary()`

### 8.7 `skill:drift-check`
Location: `app/Console/Commands/Skills/SkillDriftCheckCommand.php`
Signature: `skill:drift-check {--team= : Team ID}`

- Calls `SkillDriftDetector::checkAll()`
- For each drifted skill: sets status to `pending_review`, logs to telemetry ledger
- Table output of results
- Schedule: Register in `app/Console/Kernel.php` (or `routes/console.php`) as `$schedule->command('skill:drift-check')->daily()`

---

## Section 9: API Endpoints

### 9.1 Controller
Location: `app/Http/Controllers/Api/V1/Skills/SkillController.php`

### 9.2 Routes
Add to API routes file (likely `routes/api.php` in the `agent/api/v1` prefix group):

```php
Route::middleware(['auth:sanctum'])->prefix('agent/api/v1')->group(function () {
    Route::post('/skills/install', [SkillController::class, 'install']);
    Route::get('/skills', [SkillController::class, 'index']);
    Route::get('/skills/library', [SkillController::class, 'library']);
    Route::post('/skills/library/{slug}/install', [SkillController::class, 'installFromLibrary']);
    Route::get('/skills/{id}', [SkillController::class, 'show']);
    Route::patch('/skills/{id}', [SkillController::class, 'update']);
    Route::delete('/skills/{id}', [SkillController::class, 'destroy']);
    Route::post('/skills/{id}/validate', [SkillController::class, 'revalidate']);
});
```

### 9.3 Endpoint implementations

**POST /skills/install:**
- Request: multipart form-data with `.skill` file OR JSON body with `url` field
- Authorization: Team owner/admin (check via Jetstream team membership + role)
- Calls `SkillInstaller`
- Response: 201 with skill data + validation result, or 422 with validation errors

**GET /skills:**
- Query params: `status`, `risk_level`, `industry`, `page`, `per_page`
- Returns paginated list of installed skills for current team + global skills
- Authorization: Any authenticated team member

**GET /skills/{id}:**
- Returns full skill detail including validation_result, telemetry summary (invocation_count, last_invoked_at)
- Telemetry summary: query `telemetry_event_ledger` for recent skill events
- Authorization: Any authenticated team member

**PATCH /skills/{id}:**
- Body: `{ "status": "paused" | "active" }` for pause/resume
- Or multipart with new `.skill` file for update (re-validates)
- Authorization: Team owner/admin

**DELETE /skills/{id}:**
- Authorization: Team owner/admin
- Calls `SkillInstaller::remove()`

**POST /skills/{id}/validate:**
- Re-runs validation on existing installed skill
- Authorization: Team owner/admin

**GET /skills/library:**
- Query params: `category`, `industry`, `search`
- Returns library entries from manifest
- Gated behind `skills.library_enabled` flag
- Authorization: Any authenticated team member

**POST /skills/library/{slug}/install:**
- Authorization: Team owner/admin
- Calls `SkillInstaller::installFromLibrary()`

### 9.4 Form Requests
- `app/Http/Requests/Skills/InstallSkillRequest.php`
- `app/Http/Requests/Skills/UpdateSkillRequest.php`

### 9.5 API Resources
- `app/Http/Resources/Skills/SkillResource.php`
- `app/Http/Resources/Skills/SkillLibraryResource.php`
- `app/Http/Resources/Skills/SkillValidationResource.php`

---

## Section 10: UI Surface

### 10.1 Route registration
Add to `routes/web.php` inside the authenticated middleware group, after existing tools routes (around line 238):

```php
// Skills routes (guarded by skills UI feature flag)
Route::middleware(['skills.ui'])->group(function () {
    Route::get('/tools/skills', function () {
        return Inertia::render('Tools/Skills/Index');
    })->name('tools.skills.index');

    Route::get('/tools/skills/install', function () {
        return Inertia::render('Tools/Skills/Install');
    })->name('tools.skills.install');

    Route::get('/tools/skills/library', function () {
        return Inertia::render('Tools/Skills/Library');
    })->name('tools.skills.library');

    Route::get('/tools/skills/{id}', function (string $id) {
        return Inertia::render('Tools/Skills/Show', ['skillId' => $id]);
    })->name('tools.skills.show');
});
```

### 10.2 Middleware: `skills.ui`
Location: `app/Http/Middleware/EnsureSkillsUiEnabled.php`

- Check `FeatureFlagManager::SKILLS_ENABLED` AND `FeatureFlagManager::SKILLS_UI_ENABLED`
- Redirect to `/tools` with flash message if disabled

Register in `bootstrap/app.php` or middleware aliases.

### 10.3 Vue pages
Location: `resources/js/Pages/Tools/Skills/`

**Index.vue** — Installed skills grid/list:
- Fetches from `GET /agent/api/v1/skills`
- Displays: name, description, version, risk_level (color-coded badge), status (active/paused/pending_review), invocation_count, last_invoked_at
- Actions: Pause/Resume toggle, Remove button, link to detail
- Install button linking to `/tools/skills/install`
- Library button linking to `/tools/skills/library`

**Install.vue** — Install flow:
- Three tabs: Upload File, Paste URL, Browse Library
- Upload: drag-and-drop `.skill` file upload
- URL: text input for skill URL
- Both trigger validation, show progress, display validation results
- On `requires_admin_confirmation`: show confirmation dialog with risk details
- On success: redirect to skill detail

**Library.vue** — Browse library:
- Fetches from `GET /agent/api/v1/skills/library`
- Grid of available skills with category filters, industry filters, search
- Each card: name, description, category, risk_level badge, install button
- Install triggers `POST /skills/library/{slug}/install`

**Show.vue** — Skill detail:
- Fetches from `GET /agent/api/v1/skills/{id}`
- Sections: Metadata, Validation Result, Execution History, Controls
- Metadata: name, version, author, description, risk_level, industries, status
- Validation: expandable per-stage results, risk score visualization
- History: recent invocations from telemetry (fetched via separate API call)
- Controls: Pause/Resume, Remove, Re-validate, View SKILL.md source (modal)

### 10.4 Navigation integration
Update `resources/js/Pages/Tools/Index.vue` — add Skills card/link to the Tools index page:
- Card with title "Skills", description "Manage agent skill plugins", icon
- Link to `/tools/skills`
- Conditionally shown when `skills.ui_enabled` flag is true (pass from backend via Inertia props)

Update Tools/Index route handler in `routes/web.php` to pass skills availability flag via Inertia props (same pattern as `codeAnalysis.available`).

### 10.5 Acceptance: in-app discoverability
- `/tools` page shows Skills card when `skills.ui_enabled` is true
- Skills card links to `/tools/skills`
- `/tools/skills` is navigable and renders the installed skills list
- Install and Library sub-pages are reachable from the Skills index
- Skill detail page reachable by clicking any skill row
- Navigation breadcrumbs: Tools → Skills → [Skill Name]

---

## Section 11: Messenger Integration

### 11.1 Slash command handlers
Register new commands in `app/Services/Messenger/CommandRouter.php` `$handlers` array:

```php
'skills' => SkillsCommandHandler::class,
```

### 11.2 `SkillsCommandHandler`
Location: `app/Messenger/SlashCommands/SkillsCommandHandler.php`

Implements `SlashCommandHandlerInterface`. Parses subcommands:

- `/skills list` — Lists installed skills with status
- `/skills install {name}` — Install from library with confirmation flow (use `ConfirmationManager` from existing messenger services)
- `/skills pause {name}` — Pause skill, requires confirmation per messenger contract
- `/skills resume {name}` — Resume skill, requires confirmation per messenger contract
- `/skills info {name}` — Show skill detail + recent telemetry

**Confirmation flow:** Uses existing `PendingConfirmation` model and `ConfirmationManager` service (already in codebase) for install/pause/resume actions.

**Formatting:** Use `ChatResponseFormatter` (existing service) for provider-specific output formatting.

---

## Section 12: Dashboard Widgets

### 12.1 New widget: SkillUsage
Add skill usage data to dashboard endpoint (extend `DashboardController`):
- Top skills by invocation count (last 30 days)
- Average duration per skill
- Success rate per skill
- Query `telemetry_event_ledger` WHERE `event_type` IN ('skill.completed', 'skill.failed') GROUP BY skill_id

### 12.2 New widget: SkillHealth
- Per-skill reliability (success/total from telemetry)
- Last validation result status
- Drift alert status (any skills in `pending_review`)

### 12.3 Extended existing widgets
**ReliabilityScore:** Add skill-attributed failure rate. Query skill.failed events as proportion of total workflow events.

**BudgetUtilization:** Add per-skill token spend. Sum `token_usage` from `payload_json` in skill events.

**EscalationEvents:** Add skill-triggered escalations (validation failures flagged as escalation events).

---

## Section 13: Org Layer Integration

### 13.1 Skill access profiles on `OrgAgentProfile`
Add `skill_access_profile` JSONB column to `org_agent_profiles` table via migration:

```php
Schema::table('org_agent_profiles', function (Blueprint $table) {
    $table->jsonb('skill_access_profile')->nullable()->after('authority_overrides');
});
```

Structure:
```json
{
  "allowed_skills": ["financial-compliance-check", "pii-detector-redactor"],
  "denied_skills": [],
  "max_risk_level": "elevated",
  "custom_trust_override": null
}
```

### 13.2 `SkillOrgIntegrator` service
Location: `app/Services/Skills/SkillOrgIntegrator.php`

**Methods:**
- `getAccessibleSkills(OrgAgentProfile $agent, int $teamId): Collection` — Filters installed skills by agent's skill access profile
- `validateRitualSkillRequirements(OrgRitualTemplate $ritual): array` — Checks all required skills are installed and active
- `resolveCouncilSkills(OrgCouncilTemplate $council, int $teamId): array` — Maps council participants to their accessible skills for multi-agent composition

### 13.3 Ritual template skill requirements
Add `required_skills` JSONB column to `org_ritual_templates` table via migration:

```php
Schema::table('org_ritual_templates', function (Blueprint $table) {
    $table->jsonb('required_skills')->nullable()->after('template_json');
});
```

### 13.4 Council skill composition
Extend council execution to pass each participant's accessible skills into their delegation context. Modify `OrgCouncilController` and council execution logic to call `SkillOrgIntegrator::resolveCouncilSkills()`.

---

## Section 14: Compliance Integration

### 14.1 Compliance gate evaluation
Modify `OrchestrationPolicyService` (at `app/Support/Compliance/OrchestrationPolicyService.php`):
- Add skill context to plan gate evaluation — complex skill chains (>3 skills with `run_after` dependencies) trigger planning requirement
- Add skill outputs to verification gate — skill-generated content passes through `VerificationEvidenceEvaluator`

### 14.2 Lessons system
Modify `LessonsManager` (at `app/Support/Compliance/LessonsManager.php`):
- Capture skill execution outcomes as lessons
- Include `skill_id` and `skill_name` in lesson metadata

---

## Section 15: Testing Strategy

### 15.1 Unit tests
Location: `tests/Unit/Skills/`

- `SkillParserTest.php` — YAML frontmatter parsing, x-agent block extraction, trigger keyword extraction, validation of malformed SKILL.md
- `FormatValidatorTest.php` — Archive size limits, symlink detection, path traversal
- `SchemaValidatorTest.php` — Required fields, name format, uniqueness
- `ContentAnalysisValidatorTest.php` — Pattern matching scores, boundary analysis, authority escalation detection, exfiltration detection, weight calculation for 4-strategy and 5-strategy modes, threshold application
- `CodeSafetyValidatorTest.php` — Language allowlist, dangerous function detection, filesystem escape
- `IntegrityValidatorTest.php` — Checksum verification, file hash generation
- `SkillResolverTest.php` — Keyword matching, trust score filtering, DAG ordering
- `SkillContextInjectorTest.php` — Context injection order, token budget allocation
- `SkillDriftDetectorTest.php` — Hash comparison, drift detection
- `SkillTelemetryRecorderTest.php` — Dual-write verification
- `SkillLibraryTest.php` — Manifest parsing, search, browse

### 15.2 Feature tests
Location: `tests/Feature/Skills/`

- `SkillInstallApiTest.php` — Full install flow via API (upload, URL, library), authorization checks
- `SkillManagementApiTest.php` — List, show, pause, resume, delete, re-validate
- `SkillLibraryApiTest.php` — Browse, search, install from library
- `SkillInstallCommandTest.php` — CLI install, list, validate, remove
- `SkillDriftCheckCommandTest.php` — Scheduled drift detection
- `SkillMessengerCommandTest.php` — Messenger slash commands
- `SkillRuntimeIntegrationTest.php` — End-to-end: install skill → resolve → inject context → record telemetry
- `SkillValidationPipelineTest.php` — Full pipeline with known-good and known-bad skills
- `SkillOrgIntegrationTest.php` — Access profiles, ritual requirements, council composition

### 15.3 Test fixtures
Location: `tests/Fixtures/Skills/`

- `valid-skill/` — A complete, valid skill for positive testing
- `valid-skill.skill` — Zipped version
- `invalid-no-frontmatter/` — SKILL.md without YAML
- `injection-attempt/` — SKILL.md with prompt injection patterns
- `dangerous-scripts/` — Scripts with eval/exec calls
- `oversized/` — Exceeds size limits
- `symlink-attack/` — Contains symlinks

---

## Section 16: Implementation Sequence (Dependency Order)

**Phase A — Foundation (no external dependencies):**
1. Database migrations (agent_skills, agent_skill_validations)
2. Models (AgentSkill, AgentSkillValidation)
3. Config extension (agent.php skills key)
4. Feature flag constants (FeatureFlagManager)
5. Horizon supervisor configuration
6. Storage directories and .gitkeep files
7. Injection pattern configuration file

**Phase B — Core Services (depends on Phase A):**
1. SkillParser + DTOs (SkillParseResult)
2. FormatValidator
3. SchemaValidator
4. ContentAnalysisValidator + 4 analyzers (PatternMatch, Boundary, AuthorityEscalation, Exfiltration)
5. LlmReviewAnalyzer (depends on existing AdversarialReviewerService)
6. CodeSafetyValidator
7. IntegrityValidator
8. SkillValidator orchestrator
9. SkillInstaller + ValidateSkillJob

**Phase C — Library & CLI (depends on Phase B):**
1. SkillLibrary service
2. CLI commands (all 7)
3. Author and validate all 31 initial skills
4. Generate manifest.json with checksums
5. Test fixtures

**Phase D — Runtime Integration (depends on Phase B):**
1. SkillResolver
2. SkillContextInjector
3. Modify ExecuteAgentRunJob for context injection
4. SkillDriftDetector
5. SkillTelemetryRecorder
6. Schedule skill:drift-check command

**Phase E — API & UI (depends on Phase B, D):**
1. API controller, routes, form requests, resources
2. skills.ui middleware
3. Vue pages (Index, Install, Library, Show)
4. Navigation integration in Tools/Index
5. Dashboard widget extensions

**Phase F — Integration Layer (depends on Phase D, E):**
1. Messenger command handler
2. Org layer integration (migrations, SkillOrgIntegrator)
3. Compliance integration (OrchestrationPolicyService, LessonsManager modifications)
4. Dashboard widgets (SkillUsage, SkillHealth)

**Phase G — Testing & Hardening (parallel with all phases):**
1. Unit tests per service (written alongside each service)
2. Feature tests (written after API/CLI complete)
3. Integration tests (after full runtime integration)
4. Security testing with injection fixtures

## Sections

- Section 1: Database Foundation — Migrations and models for agent_skills and agent_skill_validations tables with team_id FK, UUID primary keys, JSONB columns, GIN indexes
- Section 2: Configuration & Feature Flags — config/agent.php skills key extension, 6 feature flag constants in FeatureFlagManager, Horizon supervisor-skill-validation, storage directories
- Section 3: Skill Format Parser — SkillParser service for SKILL.md frontmatter extraction (standard + x-agent namespace), trigger keyword NLP extraction, SkillParseResult DTO
- Section 4: Validation Pipeline — 5-stage validator (Format, Schema, ContentAnalysis, CodeSafety, Integrity) with 4/5-strategy content analysis scoring, injection pattern config, LlmReviewAnalyzer wrapping AdversarialReviewerService
- Section 5: Skill Installer — SkillInstaller service orchestrating unpack→parse→validate→store→persist flow, ValidateSkillJob for async URL installs, SkillInstallResult DTO
- Section 6: Skill Library — SkillLibrary service reading manifest.json at runtime, 31 initial .skill files authored across 9 verticals + cross-industry, manifest with SHA-256 checksums
- Section 7: Runtime Integration — SkillResolver (hybrid keyword+LLM), SkillContextInjector (after STAR preamble, before memory), SkillDriftDetector (on-invocation + daily), SkillTelemetryRecorder (dual-write to ledger + row update)
- Section 8: CLI Commands — 7 Artisan commands: skill:install, skill:list, skill:validate, skill:remove, skill:library, skill:library:install, skill:drift-check (scheduled daily)
- Section 9: API Endpoints — 8 REST endpoints under /agent/api/v1/skills with SkillController, form requests, API resources, team owner/admin authorization
- Section 10: UI Surface — 4 Vue pages at /tools/skills (Index, Install, Library, Show), skills.ui middleware, navigation integration in Tools/Index, breadcrumbs
- Section 11: Messenger Integration — SkillsCommandHandler registered in CommandRouter, /skills subcommands (list, install, pause, resume, info), confirmation flows via ConfirmationManager
- Section 12: Dashboard Widgets — SkillUsage and SkillHealth new widgets, ReliabilityScore/BudgetUtilization/EscalationEvents extended with skill telemetry data
- Section 13: Org Layer Integration — skill_access_profile JSONB on OrgAgentProfile, required_skills on OrgRitualTemplate, SkillOrgIntegrator service, council skill composition
- Section 14: Compliance Integration — OrchestrationPolicyService extended for skill chain plan gate, LessonsManager extended for skill execution outcomes
- Section 15: Testing Strategy — Unit tests per service, feature tests for API/CLI/messenger, integration tests for runtime flow, test fixtures with valid/invalid/malicious skills
- Section 16: Implementation Sequence — 7 dependency-ordered phases: Foundation → Core Services → Library & CLI → Runtime Integration → API & UI → Integration Layer → Testing & Hardening


## Risks

- Prompt injection detection is probabilistic — novel injection techniques may evade the 200+ pattern library and 4-strategy scoring. Mitigation: defense-in-depth with 5 validation stages, mandatory LLM review for elevated/critical skills, continuous pattern library updates, and existing compliance verification pipeline as runtime safety net.
- Trigger keyword extraction quality directly determines skill resolution accuracy — poor keyword extraction causes false triggers or missed matches. Mitigation: hybrid approach with author override capability, configurable extraction parameters, and LLM ranking as a second-pass filter.
- Context window pressure from skill metadata injection — with 50 installed skills and 5 injected per invocation, metadata alone consumes ~500 tokens plus full SKILL.md loads up to 5000 tokens each. Mitigation: progressive disclosure (3 levels), configurable max skills per invocation, token budget tracking with first-come DAG allocation.
- AdversarialReviewerService is currently designed for interrogation review, not skill content analysis — adapting it for injection detection requires a new prompt template and may produce different reliability characteristics. Mitigation: graceful fallback to 4-strategy scoring on service unavailability, never block installation on LLM service failure.
- Skill drift detection on every invocation adds latency — computing SHA-256 hashes of all skill files before each execution. Mitigation: cache file hashes in memory with filesystem mtime check as fast-path (only recompute full hashes when mtime changes).
- ZIP archive processing is a security surface — malicious archives can exploit path traversal, symlinks, or zip bombs. Mitigation: dedicated format validation stage with explicit symlink rejection, path traversal detection, and file count/size limits before any content processing.
- 31 initial skills require significant domain expertise to author — low-quality skill instructions degrade agent output quality. Mitigation: each skill must pass the full validation pipeline, establish authoring guide with examples, and test each skill against representative tasks.
- Org layer integration touches multiple existing models (OrgAgentProfile, OrgRitualTemplate, OrgCouncilTemplate) with schema additions — migration coordination risk. Mitigation: additive-only column additions (nullable JSONB), no breaking changes to existing columns or behavior.


## Assumptions

- The existing telemetry_event_ledger table (append-only with trigger guard) accepts new event_type values without schema changes — skill.invoked, skill.completed, skill.failed are string values in the event_type varchar(160) column and payload_json JSONB column accommodates arbitrary skill telemetry data.
- The AdversarialReviewerService can be adapted for skill content analysis by providing a different prompt template — the service's subprocess execution pattern (ClaudeAdapter) is reusable for injection detection review.
- The existing RuntimeLlmClient service is available for LLM-based skill ranking in the resolver — no new LLM client infrastructure needed.
- Jetstream team membership and roles (owner, admin, member) are queryable and enforceable for authorization — the existing Team model and Membership model provide role checking capabilities.
- The existing ConfirmationManager and PendingConfirmation model in the messenger system support the confirmation flows needed for skill install/pause/resume via chat.
- The OrchestrationPolicyService (compliance layer) accepts extension for skill-specific policy evaluation without breaking existing plan gate and verification gate behavior.
- PHP's built-in ZipArchive extension is available for .skill file unpacking — standard in most PHP 8.3 installations.
- The symfony/yaml package is available for YAML frontmatter parsing — included transitively via Laravel dependencies.
- The existing DelegateeProfile.trust_score field is populated and queryable for trust-score-gated skill access filtering.
- Storage at storage/app/skills/ is writable by the web server process and survives deployments (not ephemeral storage).
- The DashboardController and its Vue page can be extended with new widget sections without requiring a major refactor of the dashboard architecture.

