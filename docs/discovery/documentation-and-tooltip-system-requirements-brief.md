# Requirements Discovery Brief — Unified Documentation and Tooltip System

## 1. Executive Summary

The platform needs a first-class documentation system that does two jobs:

1. Human docs: explain every section, setting, workflow, and edge case in clear product language.
2. Inline guidance: contextual helper text and tooltip content directly inside the app UI.

The system must support search (via Laravel Scout), authoring workflows, versioning, and coverage tracking so documentation stays synchronized with product behavior.

Recommendation: treat API docs and human docs as related but distinct products under one architecture. Keep `Scramble` for OpenAPI generation, and build a first-party in-app documentation domain for user docs + tooltip definitions + Scout indexing, with optional static export later.

## 2. Current-State Findings

## 2.1 Product Surfaces Requiring Documentation Coverage

Based on current app routes/pages, coverage must include:

- Dashboard
- Agent Jobs (create/edit/list, schedule, runner config, env, task source, STAR/retry toggles)
- Monitor (run states, approvals, retries, clarifications, rate-limit behavior)
- Messenger control plane (connectors, sessions/actions, health, dead letters)
- Tools:
  - Requirements Discovery wizard + settings + session settings
  - Backups settings
  - Feature flags settings
  - Memory settings/diagnostics
- Delegation (graphs, tasks, verification, delegatee profiles)
- Org layer (agents, rituals, councils, escalations, costs)
- Profile/security/account pages
- API/token and integration flows (including OAuth callback behavior)

## 2.2 Documentation System Gaps

- No unified human-docs product exists for end users.
- API docs exist only partially (`docs/openapi/messenger.yaml`) and are not integrated into a complete developer portal.
- Helper text exists only in scattered inline strings and is not centrally managed.
- No searchable docs index or coverage scoring for "undocumented" UI/API surfaces.
- No package boundary yet (`./packages` does not currently exist), so open-source packaging is not immediately scaffolded.

## 3. Problem Statement

Users cannot reliably self-serve understanding of:

- What each area does
- What each setting means
- When to use each option
- Expected outcomes and examples
- Common failure modes and remediation

This increases onboarding time, support load, and configuration errors.

## 4. Goals and Non-Goals

## 4.1 Goals

- Provide complete, searchable, human-readable docs for all user-facing app areas.
- Provide consistent, context-aware helper text and tooltip definitions across the UI.
- Preserve strong API docs for developers and integrators.
- Support versioned docs and content lifecycle (draft/review/published/archived).
- Enable fast lookup with Scout-backed search.
- Design architecture so it can be extracted to an open-source package later.

## 4.2 Non-Goals (Phase 1)

- Public docs site with full multi-language support.
- AI-generated docs without human review.
- Full CMS complexity (roles/workflows beyond immediate need).

## 5. User Personas and Primary Jobs-To-Be-Done

- Operator/Admin: "Tell me exactly what this setting changes and what safe defaults are."
- Product/Support: "Find canonical explanation and examples I can share."
- Integrator/Developer: "Understand API contracts and operational behaviors quickly."
- New user: "Understand each screen without leaving the page."

## 6. Functional Requirements

## 6.1 Documentation Domains

The system must support three content domains:

- Product Docs (human docs)
- Tooltip/Helper Content (micro-doc fragments bound to UI keys)
- API Docs (OpenAPI + endpoint narratives)

## 6.2 Human Docs Requirements

- Rich Markdown/MDX body with support for:
  - Headings, tables, callouts, code blocks
  - Embedded examples (JSON/cURL/UI walkthrough)
- Structured metadata:
  - `slug`, `title`, `summary`, `section`, `audience`, `status`, `version`, `tags`, `owner`
- Hierarchical navigation:
  - Area -> Page -> Topic -> FAQ/Troubleshooting
- Related-content linking:
  - Link docs to route names, feature flags, and setting keys
- Lifecycle:
  - Draft -> Review -> Published -> Deprecated/Archived
- Change log:
  - Track content revision history and last reviewed date

## 6.3 Tooltip/Helper Requirements

- Every non-trivial input/control/state badge should support attached docs metadata.
- A tooltip entry should support:
  - `ui_key` (stable id, e.g. `jobs.form.runner.permission_profile`)
  - Short text (<= 240 chars)
  - Optional long text / "Learn more" doc link
  - Severity/type (info, warning, risk)
  - Feature-flag gating
- Tooltip delivery modes:
  - Hover/focus tooltip
  - Inline helper text under fields
  - Context panel on demand (optional phase)
- Accessibility requirements:
  - Keyboard focus + dismiss
  - ARIA labeling
  - Mobile tap behavior fallback

## 6.4 Search Requirements (Laravel Scout)

- Search should index:
  - Product docs
  - Tooltip/helper entries
  - API endpoint descriptions and operation metadata
- Query requirements:
  - Full-text relevance ranking
  - Tag/section filters
  - "Route-aware" results (boost content linked to current screen)
- Result payload:
  - Title, summary snippet, domain, section, route affinity, last updated

## 6.5 API Docs Requirements

- Generate OpenAPI spec from code annotations/reflection.
- Publish rendered API reference from generated spec.
- Link API endpoints to human guides ("How to use this endpoint in product workflows").

## 7. Non-Functional Requirements

- Performance: search response p95 <= 300 ms for typical query sets.
- Reliability: docs APIs available at parity with core app APIs.
- Security: docs editing restricted by role/capability; view access follows app auth.
- Auditing: content create/update/publish actions logged.
- Maintainability: content keys stable and testable in CI.

## 8. Tooling Analysis

## 8.1 Scramble

- Best fit: OpenAPI spec generation from Laravel routes/controllers.
- Limitation: not a complete human-doc CMS/knowledge base.
- Recommendation: use for API spec generation only.

## 8.2 Scribe

- Best fit: API docs rendering and endpoint examples.
- Limitation: API-centric, not designed as full in-app user-doc + tooltip platform.
- Recommendation: optional alternative to Scramble for API docs, not primary for human docs.

## 8.3 LaRecipe

- Best fit: Laravel-focused docs site generation from Markdown.
- Risks: lower ecosystem momentum for modern Laravel 12 workflows; advisory history should be treated carefully.
- Recommendation: avoid as core foundation for this feature.

## 8.4 Jigsaw

- Best fit: static docs site generation with Blade/Markdown.
- Limitation: static output; not ideal for dynamic in-app tooltip context and live content domain.
- Recommendation: good for optional public docs export, not primary authoring runtime.

## 8.5 Docusaurus

- Best fit: polished static docs portal (versioning, search integrations, strong IA tooling).
- Limitation: separate stack/runtime; weak direct coupling to in-app tooltip keys unless custom sync layer is built.
- Recommendation: good external/public docs front-end, but use internal source-of-truth first.

## 8.6 Summary Decision

Use a hybrid architecture:

- In-app first-party documentation domain as source of truth (human docs + tooltips + search index).
- Scramble for OpenAPI generation.
- Optional static export adapter (Docusaurus or Jigsaw) for public docs distribution later.

## 9. Recommended Architecture

## 9.1 Core Components

- `DocumentationEntry` model (human docs pages/topics)
- `DocumentationFragment` model (tooltip/helper fragments)
- `DocumentationLink` model (map docs/fragments to route names, feature flags, setting keys)
- `ApiDocArtifact` model (generated OpenAPI metadata + deep links)
- Scout searchable index over these models

## 9.2 UI Integration

- Add a reusable `HelpHint` UI component:
  - accepts `uiKey`
  - resolves fragment content via local preloaded payload + API fallback
  - supports tooltip and "learn more" link
- Build docs center page:
  - `/docs` route in-app
  - searchable and filterable by section/domain
- Add "contextual docs" panel to key pages (phase 2)

## 9.3 Content Storage Strategy

Phase 1 recommendation:

- Author content in database-backed models for dynamic updates and Scout indexing.
- Optional export/import pipeline to Markdown in `docs/product/` for git-based review and portability.

Alternative (not preferred for phase 1):

- Markdown-first with build-time import to DB.

## 9.4 Packaging Strategy for `./packages`

Current repo has no `packages/` directory. Plan for extraction:

- Build in-app under `app/Support/Documentation` + `app/Models` first.
- Keep boundaries clean (service interfaces + isolated migrations/views).
- Once stable, extract to `packages/docs-platform` with service provider and publishable assets.

## 10. Scout Search Design

## 10.1 Engine Recommendation

- Preferred: `Laravel Scout + Typesense` for local-first operation, relevance quality, and typo tolerance.
- Fallback: Scout database engine for low-infra local mode (reduced ranking quality).

## 10.2 Indexing Schema

Common indexed fields:

- `domain` (`product_doc`, `tooltip`, `api_doc`)
- `title`
- `summary`
- `body`
- `tags`
- `section`
- `route_names`
- `setting_keys`
- `updated_at`

## 10.3 Ranking Strategy

- Exact title and `ui_key` matches highest
- Then summary/body relevance
- Boost by current route affinity when searching from a given page

## 10.4 Local Infrastructure Convention (Docker Compose)

- Reuse the existing root `docker-compose.yml` as the shared local infra stack for containerized dependencies.
- Treat it as the catch-all for external services needed by the app (for example: Neo4j, Typesense, and future infra services).
- Do not create one-off compose files per feature unless there is a hard isolation requirement.
- Use consistent patterns across services:
  - Named volumes for persistent data
  - Healthchecks for readiness
  - Environment-driven configuration via `.env`
  - Optional Compose `profiles` for selective startup when service count grows

## 11. Authoring and Governance Requirements

- Roles:
  - Viewer (read docs)
  - Editor (draft/edit)
  - Publisher (approve/publish)
- Mandatory metadata on publish:
  - Owner, review date, affected app area
- CI checks:
  - No orphan `ui_key` references
  - No missing docs for required critical routes/settings
  - Broken-link checks

## 11.1 Commit Automation Requirement (Claude Hooks)

- Add commit automation requirements for both:
  - Claude tooling commits (Claude hooks)
  - All local git commits (standard git `pre-commit` hook)
- Requirement intent: docs/tooltips synchronization must run before **any** local commit is finalized, regardless of commit entrypoint.
- This is a product requirement for the documentation platform rollout, not an immediate implementation in this discovery brief.
- Hook behavior target:
  - Trigger on commit commands
  - Regenerate documentation and helper/tooltip artifacts
  - Block commit if sync fails
  - Allow explicit bypass for emergency/manual flows
- Implementation-direction requirement: use one shared sync script/pipeline and invoke it from both Claude hook and git `pre-commit` hook paths to avoid drift.
- Reference: https://code.claude.com/docs/en/hooks

## 12. Coverage and Quality Requirements

Define coverage KPI categories:

- Screen coverage: % of app screens with at least one top-level doc
- Settings coverage: % of configurable fields with helper text/tooltip
- API coverage: % of authenticated endpoints with API + narrative docs

Definition of done for "fully documented area":

- Area overview page
- Setting reference with examples
- Troubleshooting section
- Tooltip keys for critical controls
- Search returns relevant results for 3-5 expected queries

## 13. Delivery Plan (Phased)

## Phase 0: Foundations

- Define taxonomy and content model
- Add database tables/models/policies
- Add Scout integration and indexing jobs

## Phase 1: Docs MVP (In-App)

- Build docs CRUD + publish flow
- Build docs center searchable UI
- Seed docs for Jobs, Monitor, Discovery, Messenger

## Phase 2: Tooltip System

- Introduce `HelpHint` component and `ui_key` registry
- Integrate tooltips/helper text in Jobs, Discovery, Memory, Messenger
- Add coverage reporting for missing keys

## Phase 3: API Docs Unification

- Add Scramble-generated OpenAPI pipeline
- Build API docs section inside docs center
- Cross-link API endpoints to user guides

## Phase 4: Externalization + OSS Readiness

- Optional static export pipeline (Docusaurus/Jigsaw)
- Extract to package structure and prepare open-source release artifacts

## 14. Risks and Mitigations

- Risk: content drift from product behavior.
  - Mitigation: owner/review metadata + coverage CI checks.
- Risk: over-coupling docs and frontend keys.
  - Mitigation: stable key naming standard and lint checks.
- Risk: search quality mismatch if DB driver only.
  - Mitigation: prefer Typesense for production-like search behavior in local and hosted environments.
- Risk: large scope across all app areas.
  - Mitigation: phase by criticality and use content templates.

## 15. Open Questions Requiring Product Decision

- Should docs be private-only initially or partially public?
- Is localization required in first release?
- Should tooltip content be editable in-app by non-developers?
- Should published docs be versioned per app release tag?
- Do we require per-team docs scope in multi-team contexts?

## 16. Acceptance Criteria for Discovery Completion

This discovery is complete when:

- Architecture direction is approved (in-app source-of-truth + Scramble for API docs).
- Content model and key taxonomy are accepted.
- Search engine decision is made (Typesense preferred, DB fallback acknowledged).
- Rollout phases and ownership are agreed.
- Success metrics and coverage gates are accepted.

## 17. Recommendation Snapshot

- Proceed with first-party in-app docs + tooltip domain now.
- Keep Scramble for OpenAPI generation.
- Do not rely on LaRecipe as core platform.
- Use Docusaurus or Jigsaw only as optional export target, not primary source-of-truth.
- Design implementation so extraction to `./packages` is straightforward once stabilized.

## 18. Reference Links (Reviewed 2026-03-02)

- Scramble: https://scramble.dedoc.co/
- Scribe (Laravel): https://scribe.knuckles.wtf/laravel
- LaRecipe: https://larecipe.saleem.dev/
- Jigsaw: https://jigsaw.tighten.com/docs/installation/
- Docusaurus docs: https://docusaurus.io/docs
- Docusaurus search: https://docusaurus.io/docs/search
- Laravel Scout docs: https://laravel.com/docs/12.x/scout
- Typesense docs: https://typesense.org/docs/
- LaRecipe advisory reference: https://github.com/saleem-hadad/larecipe/security/advisories/GHSA-g9r4-3hm2-g96c
