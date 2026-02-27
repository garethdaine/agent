# Requirements Discovery Brief — Figma Make UI Parity (Full App, No Regressions)

## 1. Objective

Adopt the new visual design from:

- Figma site: `https://pause-kindle-31517753.figma.site/`
- Local Figma Make code: `/Users/garethdaine/Code/agent/figma`

across the entire product while preserving all existing functionality, workflows, feature gating, and control surfaces in the current Laravel + Inertia + Vue app.

This is a UI/system redesign and parity migration, not a frontend framework rewrite.

## 2. Core Constraints (Non-Negotiable)

1. Keep stack as-is: Laravel 12 + Inertia + Vue 3. Do not introduce React runtime into production app pages.
2. Treat `/figma` code as design reference only (layout, tokens, component intent), not as drop-in implementation.
3. Preserve existing route structure and page ownership:
   1. Keep canonical app routes like `/agent/jobs`, `/agent/monitor`, `/tools/discovery/{id}`, `/agent/delegation/*`.
   2. Do not migrate to Figma shorthand routes (`/jobs`, `/monitor`, etc.).
4. Preserve all API integrations and payload contracts under `/agent/api/v1/*`.
5. Preserve all control surfaces/actions currently available in the app (including destructive and recovery actions like delete/restore/retry/restart/run-now/pause/resume).
6. Preserve auth/account behavior from Jetstream/Fortify (profile, 2FA, API tokens, browser sessions, account deletion, team features).
7. Preserve feature-flag and permission-gated visibility (for example Delegation navigation/UI).
8. No regressions in accessibility, responsive behavior, keyboard navigation, or validation/error feedback states.

## 3. Design System Migration Requirements

Implement a Vue-compatible design system layer inspired by `/figma`:

1. Tokenize colors, typography, radius, borders, shadows, focus rings, spacing, and motion using CSS variables and Tailwind theme extensions.
2. Use DM Sans + JetBrains Mono (or equivalent approved fallback) consistent with Figma direction.
3. Maintain intentional light/dark themes (not basic inversion).
4. Use semantic states consistently:
   1. Primary (blue/cyan)
   2. Success (emerald)
   3. Warning (amber)
   4. Danger (red)
5. Build/reuse Vue primitives matching current needs (buttons, inputs, selects, cards, badges, dialogs, tables, dropdowns, sheets, alerts, skeleton/loading states).

## 4. Full-Surface Scope (All Screens)

Redesign every current product surface while preserving behavior:

1. Public/Auth:
   1. Welcome
   2. Login/Register/Forgot/Reset
   3. Two-factor challenge
   4. Messenger link valid + invalid token states
   5. Terms + Privacy
2. App shell and navigation:
   1. Desktop nav
   2. Mobile nav sheet
   3. User menu open state
   4. Team-switcher/account controls
3. Dashboard:
   1. Window selector + refresh
   2. KPI cards
   3. Scheduler health semantics
4. Jobs:
   1. Index with filters/chips/pagination
   2. Create form
   3. Edit form
   4. Row actions: run now, toggle, edit, delete/restore
5. Monitor:
   1. Health cards
   2. Runs table
   3. Event/log tail
   4. Auto-follow
   5. Intervention flows: approval, clarification, rate-limit handling
6. Tools:
   1. Tools hub cards
   2. Discovery index/create/settings/wizard/session settings
   3. Backups settings
   4. Feature settings
   5. Messenger control plane
7. Delegation:
   1. Graph index/create/detail
   2. Task detail
   3. Verification approval
   4. Delegatee profiles index/create/edit
8. Profile + API tokens:
   1. Profile info
   2. Password
   3. Two-factor
   4. Browser sessions
   5. Delete account
   6. API token management modals and permission controls

## 5. Functional Parity Requirements by High-Risk Areas

1. Discovery Wizard parity is critical:
   1. Preserve multi-phase flow and state transitions.
   2. Preserve all phase actions (answer, edit answer, continue, confirm/revise summary, generate/approve/revise/regenerate/export plan, build-task CRUD/regenerate/approve, start/pause/resume/clarify build, retry/restart/delete/restore).
   3. Preserve provider integration controls (Linear OAuth connect/disconnect, team/project selection, tech stack add/remove).
   4. Preserve event polling/realtime behavior and status/notice/error surfaces.
2. Messenger Control Plane:
   1. Keep schema-driven dynamic connector form fields.
   2. Keep connector lifecycle actions (create/test/disconnect) and session drill-down (messages/actions).
3. Monitor:
   1. Keep existing guardrail/intervention actions and run control operations.
4. Jobs:
   1. Keep source/deleted filters and build-vs-user distinctions.
   2. Keep advanced schedule and runner permission profile controls.

## 6. Implementation Approach

1. Build shared foundation first:
   1. Tokens, typography, shell, shared components.
2. Migrate screen groups incrementally with page-by-page parity checks:
   1. Auth + shell
   2. Dashboard + Jobs + Monitor
   3. Tools hub + Discovery suite
   4. Messenger + Backups + Features
   5. Delegation + Profile/API tokens
3. For each migrated screen:
   1. Keep existing data-loading and mutation logic intact.
   2. Replace markup/styles/components only as needed.
   3. Preserve existing loading/empty/error/success states.

## 7. Regression Guardrails

1. No route changes without explicit approval.
2. No API contract changes without explicit approval.
3. No removal of existing buttons/actions/filters/toggles.
4. No loss of destructive-action confirmations.
5. No loss of feature-flag conditional rendering.
6. No new dependency that forces React into app runtime.

## 8. Verification Requirements

1. Visual parity:
   1. Compare each migrated page against Figma site and `/figma` reference components.
2. Functional parity:
   1. Verify every existing action/control still works per page.
3. Responsive/accessibility:
   1. Desktop + mobile nav and key workflows.
   2. Focus states and keyboard path for core forms/dialogs.
4. Quality gates:
   1. Run app test suite relevant to touched areas.
   2. Run frontend build (`npm run build`) after major UI batches.

## 9. Definition of Done

1. All current screens are redesigned to match new Figma direction.
2. Existing behavior/control surfaces are preserved across all screens.
3. No regressions in route behavior, API integration, feature gating, auth/account management, or critical workflows.
4. Verification evidence (functional + visual) is captured for each migrated surface.
