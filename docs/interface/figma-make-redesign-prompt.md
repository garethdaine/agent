# Figma Make Prompt: Agent Ops Full UI Redesign

Use this prompt in Figma Make to redesign the entire product interface from scratch.

---

You are designing a complete, production-ready UI system and full-screen set for a web app called **Agent Ops**.

## 1) Project Goal

Create a **sleek, modern, premium admin/product interface** that feels effortless for technical users (developers/operators) and approachable for non-technical users. The existing design feels dated; replace it with a cleaner, clearer, highly usable experience.

Primary goals:

1. Reduce visual noise and increase clarity of key actions.
2. Make system health/status information immediately understandable.
3. Improve scannability of dense operational screens.
4. Keep interactions consistent and predictable.
5. Support both **light and dark themes** with strong contrast and hierarchy.

## 2) Technical Constraints and Design System Direction

Design with implementation in mind for **React + [shadcn/ui](https://ui.shadcn.com/)**.

Also enforce parity with **[shadcn-vue](https://www.shadcn-vue.com/)** so components can be mirrored in the Vue app.

Requirements:

1. Use shadcn-style primitives/patterns (Card, Button, Input, Select, Tabs, Table, Badge, Dialog, Sheet, DropdownMenu, Tooltip, Alert, Switch, Checkbox, Textarea, Separator, ScrollArea, Skeleton, Toast).
2. Define design tokens for color, radius, spacing, typography, shadows, borders, focus states.
3. Avoid generic default look. Make it feel intentionally designed and premium.
4. Keep interaction density high but readable for ops workflows.
5. Use a professional palette (no purple bias). Suggested direction:
   - Neutrals: slate/stone style foundation
   - Primary accent: electric blue/cyan family
   - Secondary accent: emerald for success
   - Warning: amber
   - Danger: red
6. Typography:
   - Primary UI font: modern grotesk/sans (not Inter/Roboto/Arial defaults)
   - Monospace for logs/CLI/technical fields
7. Accessibility:
   - WCAG AA contrast
   - clear keyboard focus rings
   - minimum 44px target for primary actions on mobile

## 3) Deliverables to Generate in Figma

Produce:

1. A **global design system page** (tokens, components, usage states).
2. A **desktop app shell** and **mobile shell**.
3. Full screen designs for every screen listed in section 6.
4. Key component states (empty/loading/error/success, dialogs, dropdown open states, mobile nav open).
5. Click-through prototype flows:
   - Login -> Dashboard
   - Jobs list -> Create -> Edit
   - Discovery list -> New session -> Wizard -> Session settings
   - Tools hub -> Backups/Features/Messenger
   - Delegation index -> graph -> task -> verification approval
   - Profile -> delete account confirmation

## 4) IA / Navigation Model

Top-level authenticated nav:

1. Dashboard
2. Jobs
3. Monitor
4. Messenger
5. Tools
6. Delegation
7. User menu (Profile, Logout, API tokens if enabled)

Authenticated shell must include:

1. Top bar with product identity and primary nav.
2. Context-aware page title + action bar.
3. Consistent content container width.
4. Optional right-side contextual panel patterns for dense pages (logs/details/metadata).
5. Mobile variant with hamburger menu and slide-out navigation sheet.

## 5) Global UX Rules

Apply across all screens:

1. Prefer progressive disclosure for advanced fields.
2. Group related controls into clear sections/cards.
3. Keep primary CTA obvious and singular per section.
4. Use status badges with consistent color semantics.
5. Provide inline helper copy for complex fields (cron, runner permissions, paths, credentials).
6. For destructive actions, require confirmation dialog.
7. All tables need:
   - sticky header (desktop)
   - clear empty state
   - loading skeleton
   - row-level actions
8. All forms need:
   - inline validation
   - success/error banner region
   - disabled/loading button states

## 6) Screen-by-Screen Requirements

Use these existing screens as content/functionality references (see screenshots in `docs/interface/*.png`).

### Public / Auth

1. Welcome/Landing
   - Hero with concise value proposition
   - Product feature highlights
   - Strong sign-in/register CTAs
2. Login
   - Email/password
   - remember me
   - forgot password link
3. Register
   - Standard account creation
4. Forgot Password
5. Reset Password
6. Two-factor challenge page
7. Messenger link success/valid-token state
8. Messenger link invalid/expired state

### Dashboard

1. Time-window selector and refresh action.
2. KPI cards:
   - runs today
   - success rate
   - average duration
   - backlog count
   - oldest queued age
   - scheduler health
3. Strong visual status semantics for healthy/degraded/down/unknown.

### Jobs

1. Jobs index:
   - search + filter row (status, runner, source, deleted)
   - quick filter chips
   - jobs table with:
     - name/description
     - enabled state
     - runner type
     - cron + timezone
     - next run
     - last run
     - row actions (run now, toggle, delete/restore, edit)
2. Create job form:
   - basic metadata
   - schedule builder (daily/hourly/weekly/monthly/interval)
   - runner type + command template + permissions profile
   - task markdown source (path/inline)
   - working directory + env JSON overrides
3. Edit job form:
   - same structure as create
   - updated/last run metadata prominence

### Monitor

1. Active/completed run list with statuses.
2. Run detail with event stream/log tail.
3. Scheduler health and queue lag indicators.
4. Auto-follow toggle.
5. Approval/clarification/rate-limit intervention states and dialogs.

### Tools Hub

1. Four primary cards:
   - Requirements Discovery
   - Database Backup Settings
   - Messenger Control Plane
   - Feature Settings

### Discovery (Interrogation)

1. Discovery sessions index:
   - search/filters for status/type/runner/deleted
   - table with session status badges and actions (retry, restart, rename, delete/restore, open, settings)
2. New discovery session:
   - name
   - runner
   - project directory
   - interrogation type
   - feature brief (long-form textarea)
3. Discovery settings (global):
   - defaults and behavior controls for sessions
4. Discovery wizard:
   - session header (status/phase)
   - phase stepper
   - question/answer workflow
   - QA history
   - summary viewer
   - plan viewer
   - build panel
   - context stats/status cards
5. Session settings:
   - session name + feature brief
   - provider integration controls (Linear OAuth/connect/disconnect)
   - provider team/project selection
   - tech stack list add/remove

### Backups Settings

1. Toggle enable daily backup.
2. Timezone + run hour/minute + retention days.
3. Readout card showing configured runtime and last run status/error.
4. Save settings and run backup now actions.

### Feature Flags Settings

1. List of flags with:
   - label
   - description
   - key
   - default state
   - override indicator
2. Toggle controls and save action.
3. Empty-state support if no managed flags.

### Messenger Control Plane

1. Health/status summary region.
2. Connector metrics and connector list.
3. Sessions/messages/actions panel.
4. Connect provider form:
   - provider select
   - connection mode
   - credentials fields
   - confirmation/default verbosity controls
5. Handle credential field errors and connection success/error feedback.

### Profile / Security

1. Profile information section.
2. Password update section.
3. Two-factor authentication controls.
4. Browser sessions section.
5. Delete account section with confirmation modal.

### Delegation

1. Delegation graph index.
2. Create delegation graph form.
3. Graph details page.
4. Task detail page.
5. Human verification approval page.
6. Delegatee profile index/create/edit.

## 7) Required UI States Per Major Screen

For Dashboard, Jobs, Monitor, Discovery, Messenger, and Delegation pages, provide:

1. Default populated state
2. Loading state (skeleton preferred)
3. Empty state
4. Error state
5. Success/confirmation feedback state

Also include:

1. Desktop user menu open state
2. Mobile nav open state
3. Profile delete-account dialog open state

## 8) Interaction and Motion

Use subtle, meaningful motion only:

1. Smooth panel/table row transitions (150-250ms)
2. Toast/alert entrance motion
3. Dialog/sheet transitions
4. Hover and focus interactions that increase clarity without distraction

No excessive animation; keep enterprise-professional.

## 9) Responsive Rules

Produce desktop first plus mobile variants for:

1. Dashboard
2. Jobs index
3. Discovery wizard
4. Messenger control plane
5. Profile

Mobile rules:

1. Nav via sheet/drawer
2. Stack cards vertically
3. Collapse dense table data into card rows with action menus
4. Keep primary action pinned/visible for long forms

## 10) shadcn/ui + shadcn-vue Parity Mapping

While generating React-oriented designs, ensure patterns map directly to Vue equivalents:

1. Button/Input/Select/Textarea/Switch/Checkbox
2. Card/Badge/Alert/Tooltip/Separator
3. Table/Tabs/DropdownMenu/Dialog/Sheet
4. Toast and form validation patterns

Do not rely on components with no straightforward shadcn-vue analogue.

## 11) File/Frame Naming in Figma

Use clear naming:

1. `Foundations / Color`
2. `Foundations / Typography`
3. `Components / Inputs`
4. `Components / Data Display`
5. `Screens / Auth / Login`
6. `Screens / Dashboard / Default`
7. `Screens / Jobs / Index`
8. `Screens / Discovery / Wizard`
9. `Screens / Messenger / Control Plane`
10. `Screens / Delegation / Graph Detail`

## 12) Output Quality Gate

Before finalizing, verify:

1. Every screen in section 6 is present.
2. Each major workflow can be prototyped end-to-end.
3. Light and dark variants are complete.
4. Component reuse is consistent.
5. Design looks modern and premium, not generic boilerplate.
6. Ready for implementation with React shadcn/ui and parity in shadcn-vue.

---

Reference screenshots are in:

- `docs/interface/README.md`
- `docs/interface/*.png`

