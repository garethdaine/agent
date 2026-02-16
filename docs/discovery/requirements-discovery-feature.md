# Requirements Discovery — Feature Specification

## 1. Overview

**Feature Name:** Requirements Discovery
**Navigation:** Under a new "Tools" section (card grid with live status — active sessions, last used)
**Purpose:** A GUI wizard that mirrors the CLI interrogation workflow (docs/interrogate.md), guiding users through structured requirements gathering with AI-generated questions, culminating in a confirmed summary, approved implementation plan, generated build tasks, and orchestrated build execution.
**Runner Support:** Both Claude CLI and Codex CLI, selectable at session start.

---

## 2. Workflow Phases

The wizard has visible phase indicators (stepper bar across the top):

| Phase | Name | Description |
|-------|------|-------------|
| 0 | Setup | Runner selection (Claude/Codex), directory selection (native HTML picker), interrogation type (New Feature / General), optional session name, feature brief (if New Feature) |
| 1 | Discovery | AI autonomously explores the target project. Single status card updates in real-time via stream-json. Parsed friendly messages from tool calls. |
| 2 | Interrogation | AI asks one question at a time. User answers via multiple choice, free text, or skip-with-reason. Structured JSON questions via `--json-schema` (Claude) / `--output-schema` (Codex). |
| 3 | Summary | AI produces a structured summary displayed as collapsible sections. User annotates (private notes + flag sections for AI revision). User confirms completeness. |
| 4 | Planning | Explicit "Generate Plan" button. AI generates an implementation plan (same CLI session resumed with elevated tool permissions). Once generated, user must click "Approve Plan" (`approved_at` set) before progressing. |
| 5 | Build Tasks | User generates build tasks from the approved plan. Tasks are persisted and shown with status/attempt/error metadata. |
| 6 | Build Execution | User starts execution, monitors active task/run output, handles approval/rate-limit prompts, submits clarifications, and can pause/resume/retry failed work. Session completes when all tasks finish. |

---

## 3. AI Backend Architecture

**Hybrid CLI approach:**
- **Discovery (Phase 1):** Stream-json output (`--output-format stream-json` for Claude, `--json` for Codex) for real-time activity feed.
- **Q&A rounds (Phase 2+):** Repeated print-mode invocations (`claude -p --resume <id>` / `codex resume <id>`) with structured JSON output enforced by schema flags.

**Adapter pattern:**
- `InterrogationRunnerAdapter` interface with `ClaudeAdapter` and `CodexAdapter` implementations.
- Each adapter builds the correct CLI command, delivers the system prompt via its runner's native mechanism (Claude: `--system-prompt`, Codex: PROMPT argument), and parses runner-specific output into a **unified internal event format**.
- Identical JSON question schema enforced across both runners.

**Phase-based tool restrictions:**
- Phases 0–3: Read-only tools (Read, Glob, Grep) only.
- Phases 4–6: Full tool access (Write/Edit enabled) since plan/build artifacts are generated and executed.

**Session resume:**
- Native CLI resume first (`--resume` / `codex resume`).
- If CLI session is gone, reconstruct full conversation from DB and start a fresh CLI session. Send entire history — no truncation.

---

## 4. Question JSON Schema

```json
{
  "phase": "string",
  "question_id": "string",
  "question_text": "string (markdown)",
  "answer_type": "choice | freetext",
  "options": ["string array, present when answer_type=choice"],
  "category": "string (domain/concern area tag)",
  "reasoning": "string (why the AI is asking this)",
  "progress_estimate": "number (0-100)"
}
```

**Answer types supported:** Multiple choice (radio buttons), free text, skip-with-reason (user must provide a brief reason: "Don't know yet", "Out of scope", "Depends on X").

---

## 5. Data Model

**Dedicated models (not reusing existing AgentJob/AgentRunEvent):**

- **InterrogationSession** — Session record with: user_id, name (optional, auto-generated default), runner_type, project_directory, interrogation_type (feature/general), feature_brief, status, phase, claude_session_id, summary, plan, soft deletes.
- **InterrogationEvent** — Dedicated event model per session with: session_id, sequence, event_type (discovery_activity, question, answer, phase_transition, summary, plan, error), payload (JSON), timestamps.

**Persistence:** Full conversation stored — every question, answer, phase transition, discovery activity, summary, and plan.

---

## 6. Session Management

| Aspect | Decision |
|--------|----------|
| Persistence | Full conversation in DB (dual persistence with CLI sessions) |
| Resume | Resumable — native CLI resume first, DB reconstruction fallback |
| Concurrency | Fully concurrent across users |
| Hard limit | 3 active (in-progress) sessions per user |
| Naming | Auto-generated default (project dir + type + timestamp), user can override at creation or rename later |
| Lifecycle actions | Resume, View, Export, Archive, Delete |
| Archive | Soft-delete with dedicated archive view, restorable |
| Pruning | Never auto-pruned — kept forever unless manually deleted |

**Session index page:** Dedicated page listing all sessions with filters, status, project path, dates, and lifecycle actions.

---

## 7. Page Layout (Three-Panel)

| Panel | Content |
|-------|---------|
| **Left sidebar** | Past Q&A history (scrollable). Editable — click to change a past answer (warning: subsequent Q&A marked as stale, AI's next question accounts for the edit). |
| **Center main** | Current question + answer input. Status card (single, real-time updating) shown during discovery and between questions. |
| **Right sidebar** | Stats panel: question count, time elapsed, topics/categories covered, progress estimate bar. |
| **Top** | Phase stepper spanning full width. |

---

## 8. Feature Brief (New Feature Type)

Rich input supporting:
- Markdown editor for formatted descriptions
- File attachments: text-based files (.md, .txt, .json, etc.) are sent to the AI as context
- Links to issues/specs or references to existing interrogation sessions

---

## 9. Directory Selection

- **Native HTML directory picker** at session start.
- Directories stored in DB, editable from a dedicated settings page.
- **Backend validates** selected path: confirms directory exists, is readable, and checks for project indicators.

---

## 10. Summary Phase (Phase 3)

**Display:** Structured collapsible sections (Purpose, Scope, Behavior, Edge Cases, Constraints, Dependencies, Open Decisions).

**Annotations:** Two modes:
- Private notes (user reference only, stored with session)
- Flag sections for AI revision (flagged sections trigger a refinement round with the AI)

**Export:**
- Stored in DB as part of session record
- Exportable (downloadable)
- Auto-written to target project's `docs/discovery/` directory
- Directory auto-created if missing
- Filename: kebab-case from session name (e.g., `user-auth-feature.md`)
- Version suffix on conflicts (`-v2`, `-v3`)
- Ready to pass directly into plan mode

---

## 11. Planning Phase (Phase 4)

- Triggered by explicit "Generate Plan" button after confirmed summary.
- **Same CLI session resumed** with elevated tool permissions (Write/Edit now allowed).
- Single-shot plan generation; user can request **structured revisions**: select a section, pick an action.
- **Revision actions:** Expand, Simplify, Add Examples, Rewrite, Split Into Steps, Add Acceptance Criteria.
- **Plan output:** Linked to session in DB + auto-exported to project's `docs/plans/` directory.
- Plan file includes a header reference to the discovery summary it was generated from.
- User must explicitly approve generated plan (`POST .../approve-plan`), which stamps `approved_at` and advances the session into Build Tasks (phase 5).

---

## 12. Infrastructure

| Concern | Decision |
|---------|----------|
| Queue | Dedicated `interrogation` queue in Horizon (separate from `agent` queue) |
| Process model | Queue jobs + streaming results via InterrogationEvent table |
| Real-time | **WebSocket via Reverb** — private channel per session (`private-interrogation.{sessionId}`). First feature to activate the existing Reverb infrastructure. |
| Rate limit | 120/minute for interrogation endpoints (separate from existing 30/min agent-mutations throttle) |
| Error handling | Auto-retry with exponential backoff first. If retries exhausted, show error and let user manually retry or save progress and exit. |

---

## 13. Settings

Dedicated settings page for Requirements Discovery (`/tools/discovery/settings` or similar):
- **Allowed directories** — stored in DB, manageable from settings UI
- **System prompt** — stored in DB, editable (loaded from docs/interrogate.md as initial default, adapter delivers via runner's native mechanism)
- **Runner configuration** — paths, defaults

---

## 14. Security & Auth

- Ownership-based: sessions belong to the authenticated user (user_id on InterrogationSession)
- User metadata passed to CLI subprocess as env vars for audit/logging
- Backend validates directory paths before allowing sessions
- Full audit logging of all lifecycle events to AgentAuditLog
- Phase-based tool restrictions prevent AI writes during interrogation phases

---

## 15. Testing

Full coverage from the initial build:
- Feature tests for all API endpoints
- Unit tests for models, services, adapters
- Integration tests for CLI subprocess interaction

## Build Lifecycle (Phases 5–6)

After plan approval, build execution runs on top of persisted `interrogation_build_tasks`.

### Build API Endpoints

- `POST /agent/api/v1/interrogation/sessions/{id}/approve-plan`
- `POST /agent/api/v1/interrogation/sessions/{id}/generate-build-tasks`
- `POST /agent/api/v1/interrogation/sessions/{id}/start-build`
- `POST /agent/api/v1/interrogation/sessions/{id}/pause-build`
- `POST /agent/api/v1/interrogation/sessions/{id}/resume-build`
- `POST /agent/api/v1/interrogation/sessions/{id}/build/clarify`

### Build Phase State Transitions

- Phase 4 (`planning`) + generated plan -> `approve-plan` -> phase 5 (`build_tasks`)
- Phase 5 + generated task list -> `start-build` -> phase 6 (`build_executing`)
- Phase 6 + all tasks terminal-success -> session `completed` (workflow complete across all 7 phases)

### Build State Contract (`session.build`)

`InterrogationSessionController@show` includes a `build` payload with:

- `status` (`idle|generating_tasks|ready|running|paused|failed|completed`)
- `summary` counts by task status
- ordered `tasks`
- `active_task` and `active_run`
- `flags.approval_required` and `flags.rate_limit_detected` (plus excerpts/reset timestamp)
- lifecycle timestamps and pause/error details

### Orchestration

- Build task generation uses the same runner adapter family (`ClaudeAdapter` / `CodexAdapter`) with build-task schema parsing.
- Execution is coordinated by `ExecuteInterrogationBuildJob` and delegated run creation via `BuildTaskRunFactory`.
- Each build task maps to an `AgentJobRun`, preserving normal run lifecycle, event capture, approval/rate-limit metadata, and stop semantics.
