# Figma Make Prompt: Close UI Gaps + Modernize Agent Scheduler

Use this prompt in Figma Make to update the existing design (do not start from generic templates).

---

You are redesigning and completing the **Agent Scheduler** product UI.

Your goal is to keep what already works in the current Figma design, but close all feature/screen/control gaps versus the real app and deliver a polished, modern, high-clarity interface.

## 0) Critical Context

Use these references:
- Current app screenshot inventory: `/Users/garethdaine/Code/agent/docs/interface/README.md`
- Current app screenshots: `/Users/garethdaine/Code/agent/docs/interface/01_*.png` through `40_*.png`
- Published Figma screenshots (current baseline): `/Users/garethdaine/Code/agent/docs/interface/figma-published-*.png`
- Existing Figma React codebase: `/Users/garethdaine/Code/agent-figma`

## 1) Design Direction (replace “old hat” light/dark look)

Create a **sleek, modern, premium operational interface** that is clear for power users and friendly for non-technical users.

Visual direction requirements:
- No generic white/purple template look.
- Use a refined neutral base + strong semantic accents:
  - Primary: blue/cyan
  - Success: emerald
  - Warning: amber
  - Danger: red
- Strong visual hierarchy, cleaner spacing rhythm, and lower visual noise.
- Keep dense data screens highly scannable.
- Light and dark themes must both feel intentional (not simple inversion).

## 2) Component/Implementation Constraints

Figma Make uses React, but design must map cleanly to both:
- [shadcn/ui](https://ui.shadcn.com/) (implementation target)
- [shadcn-vue](https://www.shadcn-vue.com/) (parity target)

Use component primitives compatible with both ecosystems:
- `Button`, `Input`, `Textarea`, `Select`, `Checkbox`, `Switch`, `Tabs`
- `Card`, `Table`, `Badge`, `Alert`, `Tooltip`, `Separator`
- `DropdownMenu`, `Dialog`, `AlertDialog`, `Sheet`, `Popover`
- `Skeleton`, `Toast`, `ScrollArea`

Define reusable tokens:
- color, typography, spacing, radius, borders, shadows, focus ring, motion timing.

## 3) Routing + IA Parity Requirements

Design routes and screen naming to match current app mental model. Use these canonical surfaces:

Public/Auth:
- `/`
- `/login`
- `/register`
- `/forgot-password`
- `/reset-password/{token}`
- `/two-factor-challenge`
- `/messenger/link/{token}` valid and invalid states
- Terms + Privacy pages

Authenticated:
- `/dashboard`
- `/agent/jobs`
- `/agent/jobs/create`
- `/agent/jobs/{id}/edit`
- `/agent/monitor`
- `/tools`
- `/tools/discovery`
- `/tools/discovery/new`
- `/tools/discovery/settings`
- `/tools/discovery/{id}` (wizard)
- `/tools/discovery/{id}/settings`
- `/tools/backups/settings`
- `/tools/features/settings`
- `/tools/messenger`
- `/agent/delegation`
- `/agent/delegation/create`
- `/agent/delegation/{id}`
- `/agent/delegation/{graphId}/tasks/{taskId}`
- `/agent/delegation/{graphId}/tasks/{taskId}/approve`
- `/agent/delegatee-profiles`
- `/agent/delegatee-profiles/create`
- `/agent/delegatee-profiles/{id}/edit`
- `/user/profile`
- API tokens screen (account management)

## 4) Required Figma Pages (Output Structure)

Create these Figma pages in this order:
1. `00 Foundations`
2. `01 Shell + Navigation`
3. `02 Public + Auth`
4. `03 Dashboard`
5. `04 Jobs`
6. `05 Monitor`
7. `06 Tools Hub`
8. `07 Discovery`
9. `08 Messenger`
10. `09 Delegation`
11. `10 Profile + Account`
12. `11 States + QA`
13. `12 Prototype Flows`

## 5) Step-by-Step Execution

### Step 1: Foundations
- Create type scale, spacing scale, elevation system, radii, semantic colors.
- Define accessible focus styles and interactive states (default/hover/active/disabled).
- Build base components and variants for light + dark.

### Step 2: Shell + Navigation
- Desktop top navigation and mobile sheet navigation.
- Include user menu with: Profile, API Tokens, Logout.
- Create dedicated frames for:
  - desktop nav default
  - user menu open
  - mobile nav open

### Step 3: Public/Auth Completion
Design complete screens for:
- Welcome/landing page
- Login, Register, Forgot Password, Reset Password (token-aware)
- Two-factor challenge
- Messenger link token screens: valid flow + invalid/expired flow
- Terms of Service and Privacy Policy pages

Auth parity details:
- Register includes terms/privacy acceptance state.
- Error/success/validation states included on each auth form.

### Step 4: Dashboard
- KPI cards: runs today, success rate, avg duration, backlog count, oldest queued age, scheduler health.
- Time-range selector + refresh.
- Recent runs table.
- Include loading/empty/error variants.

### Step 5: Jobs
Create full suite:
- Jobs index table and filters
- Create form
- Edit form

Jobs index requirements:
- Filters: search, status, runner, source, deleted.
- Quick chips: all/user/build.
- Row actions: run now, toggle, edit, delete/restore.
- Include table-empty and deleted-only states.

Job form requirements:
- Basic + advanced schedule modes.
- Runner type, command template, permission profile.
- Task markdown source: file path vs inline markdown.
- Working directory + env JSON.
- Validation/error/success states and helper text.

### Step 6: Monitor
- Health cards (scheduler, queue lag, poll status).
- Latest runs list and event tail.
- Auto-follow control.
- Add intervention state frames:
  - approval needed
  - rate limit detected
  - clarification requested

### Step 7: Tools Hub
- Keep four primary cards:
  - Discovery
  - Backups
  - Messenger
  - Feature Flags
- Improve hierarchy and action affordance.

### Step 8: Discovery (complete, not partial)
Design these screens:
- Sessions index
- New session
- Global settings
- Wizard
- Session settings

Sessions index:
- status/type/runner/deleted filters
- row actions: retry/restart/rename/delete/restore/open/settings

Wizard:
- multi-phase stepper and persistent stats
- setup -> tech stack -> discovery -> interrogation -> summary -> planning -> rules -> tasks -> build
- include setup/provider controls (Linear connect/disconnect/team/project)
- include left panel history + main work surface + right stats panel for relevant phases

Session settings:
- session details
- provider connection controls
- team/project selection
- tech stack add/remove

### Step 9: Backups + Feature Flags
Backups:
- daily backup toggle
- timezone/hour/minute/retention
- status card (last run/next run/storage/retention)
- save + run-now actions

Feature Flags:
- list with key, description, default, current override
- toggle controls + save
- include no-flags empty state

### Step 10: Messenger Control Plane (full parity)
- Health summary cards
- Connector list/table with status and counts
- Dynamic connect form surface (provider + mode + credential fields + options)
- Retest + disconnect actions
- Sessions panel + session detail + actions/messages stream
- Success/error/validation states for connector operations

### Step 11: Delegation Full Suite
Create all delegation screens:
- graph index
- create graph
- graph detail
- task detail
- human verification approval
- delegatee profiles index/create/edit

Include:
- status badges
- task progress and event context
- actions (start/cancel/clone/approve/reject/edit/delete)
- loading/empty/error states

### Step 12: Profile + Account
- Profile information
- Password update
- Two-factor controls
- Browser sessions
- Delete account dialog
- API tokens management screen/state

### Step 13: State Matrix (mandatory)
For each major area (Dashboard, Jobs, Monitor, Discovery, Messenger, Delegation, Profile), include explicit frames for:
- default
- loading
- empty
- validation error
- API/error alert
- success feedback

Also include:
- profile delete modal open
- discovery row-actions menu open
- jobs filters/table control focus
- messenger connectors/sessions control focus

### Step 14: Prototype Flows
Create click-through prototypes for:
1. Login -> Dashboard
2. Jobs index -> Create -> Edit
3. Tools -> Discovery index -> New -> Wizard -> Session settings
4. Tools -> Messenger connect flow -> session detail
5. Delegation index -> graph -> task -> approve/reject
6. Profile -> delete account confirmation

## 6) Content + Copy Rules
- Use concise, operational language.
- Keep terminology consistent with existing app labels.
- Avoid lorem ipsum in final frames.
- Use realistic values (timestamps, statuses, paths, IDs) matching app context.

## 7) Accessibility + Responsiveness
- WCAG AA contrast in both themes.
- Keyboard/focus-visible states for all interactive controls.
- Minimum touch target size for mobile critical actions.
- Provide desktop + mobile variants for auth, dashboard, jobs, monitor, discovery, messenger, delegation, profile.

## 8) Final Handoff Artifacts (in Figma)
At the end, provide:
1. Complete screen set above.
2. Component inventory and token inventory.
3. Route-to-frame map.
4. Implementation notes mapping components to shadcn/ui + shadcn-vue equivalents.
5. Explicit list of old frames replaced/retired.

Do not skip missing surfaces. Prioritize route parity, state completeness, and usability clarity over decorative styling.
