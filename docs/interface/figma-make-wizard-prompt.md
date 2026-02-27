# Figma Make Prompt: Discovery Wizard Full Redesign

## Overview

Redesign the **Discovery Wizard** — a multi-phase, AI-driven requirements discovery and planning tool. This is the most complex screen in the app. It's a full-screen wizard that guides users through 9 phases of AI-assisted project analysis, interrogation, planning, and build task generation.

The wizard uses a persistent layout shell with phase-specific content that changes as the user progresses. It supports real-time streaming events, interactive Q&A, document review/revision flows, and task management.

---

## Route: `/tools/discovery/wizard`

## Global Wizard Layout

The wizard uses a **3-zone layout** within the authenticated app shell:

```
┌─────────────────────────────────────────────────────────┐
│ HEADER BAR                                              │
│ Session name · Status badge · Action buttons            │
├─────────────────────────────────────────────────────────┤
│ PHASE STEPPER (horizontal, 9 steps)                     │
├─────────────────────────────────────────────────────────┤
│ Error/Notice banners (conditional)                      │
├────────┬──────────────────────────────┬─────────────────┤
│ LEFT   │ MAIN CONTENT                 │ RIGHT SIDEBAR   │
│ PANEL  │ (phase-specific)             │ (Stats Panel)   │
│        │                              │                 │
│ QA     │                              │ Questions: 12   │
│ History│                              │ Answers: 8      │
│ (only  │                              │ Elapsed: 14m    │
│ phases │                              │ Status: active  │
│ 4-5)   │                              │ Progress: 67%   │
│        │                              │ Categories: ... │
├────────┴──────────────────────────────┴─────────────────┤
```

### Header Bar
- **Left**: Session name (editable inline), project directory path (muted text)
- **Center**: Session status badge (setup / discovering / interrogating / summarizing / planning / build_rules / build_tasks / build_executing / completed / failed / paused)
- **Right**: Action buttons row:
  - "Retry" (outline, only on failed)
  - "Restart Fresh" (outline, destructive — requires confirmation dialog)
  - "Rename" (outline)
  - "Pause" / "Resume" toggle (outline)
  - "Delete" (ghost, destructive — requires confirmation dialog)
  - "Session Settings" (gear icon, outline)
  - "Back to Sessions" (ghost, with left arrow)

### Phase Stepper
- Horizontal stepper with **9 numbered circles** connected by lines:
  1. Setup
  2. Tech Stack
  3. Discovery
  4. Interrogation
  5. Summary
  6. Planning
  7. Rules
  8. Tasks
  9. Build
- **Completed phases**: Emerald/green background, white checkmark or number, green connecting line
- **Active phase**: Primary blue background, white number, pulse/glow subtle animation
- **Future phases**: Muted border, gray number, gray connecting line
- Each circle has a label below it
- Responsive: on mobile, show only active phase name with left/right arrows to see context

### Stats Panel (Right Sidebar)
Persistent across all phases. Card with:
- **Questions**: count
- **Answers**: count
- **Elapsed**: time since session start (e.g. "14m 32s" or "Not started")
- **Status**: current status badge
- **Progress**: percentage bar (0-100%)
- **Categories**: tag list of discovered categories (e.g. "architecture", "performance", "security")

---

## Phase 1: Setup — Provider Connection

**Purpose**: Optionally connect a task provider (Linear) for syncing generated tasks.

**Main Content Area**:
- Card titled "Task Provider (Optional)"
- Helper text: "Connect a task management provider to sync generated build tasks. You can skip this and connect later."
- **Connected state**: Shows provider logo + "Connected to Linear (User Name)" with team info, "Disconnect" button (outline, danger)
- **Disconnected state**: "Connect Linear" button (primary) that initiates OAuth flow
- Below connection: **Team selector** dropdown (only when connected)
  - Label: "Select Linear Team"
  - Dropdown of available teams
  - "Save Team" button (primary, small)
- **Project selector** dropdown (only after team saved)
  - Label: "Select Linear Project"
  - Dropdown of available projects
  - "Save Project" button (primary, small)
- Bottom action: "Continue to Tech Stack →" button (primary, full-width or right-aligned)
- Skip link: "Skip provider setup" (text link, muted)

**Left Panel**: Empty or hidden (no QA history yet)

---

## Phase 2: Tech Stack Setup

**Purpose**: Define the project's technology stack with documentation URLs for AI context.

**Main Content Area**:
- Card titled "Tech Stack"
- Helper text: "Add your project's technology stack with documentation URLs. These are used as context during discovery, planning, and build phases."
- **Add form** (inline, horizontal on desktop):
  - Input: "Stack name" (placeholder: "e.g. Laravel 12, Vue 3, PostgreSQL")
  - Input: "Documentation URL" (placeholder: "https://laravel.com/docs/12.x")
  - "Add" button (primary, icon: plus)
- **Stack list** below form:
  - Each entry: pill/badge with stack name, URL as small muted link, delete (X) icon button
  - Empty state: "No tech stack entries yet. Add at least one to proceed."
- **Bottom action**: "Start Discovery →" button (primary)
  - Disabled state with tooltip: "Add at least one tech stack entry to continue"

**Left Panel**: Empty or hidden

---

## Phase 3: Discovery — Repository Analysis

**Purpose**: AI analyzes the project repository. User watches progress.

**Main Content Area**:
- Large **status card** with animated border (subtle pulse/shimmer while active):
  - Heading: "Repository Discovery"
  - Status badge: "Analyzing..." (blue, with spinner)
  - Streaming markdown content showing what's being discovered:
    - File structure analysis
    - Dependency detection
    - Pattern recognition
    - Architecture inference
  - Content area is scrollable, auto-follows new content
  - Muted timestamp on each entry
- Below status card: "This usually takes 1-3 minutes depending on repository size."

**Completed state**:
- Status badge changes to "Complete" (green)
- Border animation stops
- "Continue to Interrogation →" button appears (primary)

**Failed state**:
- Status badge: "Failed" (red)
- Error message displayed
- "Retry Discovery" button (primary)

**Left Panel**: Empty or hidden

---

## Phase 4: Interrogation — AI Q&A

**Purpose**: The core wizard phase. AI asks questions about the project; user answers to refine understanding.

This is the **most complex phase** and uses the full 3-column layout.

### Left Panel: QA History
- Heading: "Q&A History"
- Badge: count of unanswered questions (e.g. "3 unanswered")
- Scrollable list of question/answer pairs:
  - Each item shows:
    - Question number (#1, #2, etc.)
    - Category tag (small, colored badge: e.g. "architecture" in blue, "performance" in amber)
    - First line of question text (truncated)
    - Answer preview or "Unanswered" / "Skipped" badge
  - Clicking an item scrolls main area to that question
  - Active/current question is highlighted with left border accent
- Compact spacing to fit many items

### Main Content Area
- **Current Question Card**:
  - Category badge (top-left, colored)
  - Progress indicator: "Question 8 of ~15" with thin progress bar
  - Question text (markdown rendered, can be multi-paragraph)
  - Collapsible "Show reasoning" section:
    - Toggle link: "Show reasoning ▼" / "Hide reasoning ▲"
    - Reasoning content in a muted background block

- **Answer Input** (below question):
  - **Choice mode** (when AI provides options):
    - Radio button or checkbox list of options (numbered 1-N)
    - Each option is a clickable card/row with radio/checkbox
    - Helper text: "Select one or more options" (if multi-select)
    - "Something else..." link to switch to free-text mode
    - "Confirm Selection" button (primary)
  - **Free-text mode**:
    - Textarea (4-6 rows, expandable)
    - Placeholder: "Type your answer..."
    - "Submit Answer" button (primary)
    - "Back to options" link (if choices were available)
  - **Skip controls** (always visible):
    - "Skip" dropdown button with reasons:
      - "Skip for now"
      - "I don't know yet"
      - "Need to research first"
      - "Not applicable"

- **Waiting state** (between questions):
  - Skeleton loader or spinner with "Generating next question..."
  - Subtle animation

### Right Panel: Stats
(As described in global layout above)

---

## Phase 5: Summary — Review & Confirm

**Purpose**: AI generates a summary of discoveries. User reviews, can request revisions or reopen interrogation.

**Left Panel**: QA History (same as Phase 4, now read-only reference)

**Main Content Area**:
- Card titled "Discovery Summary"
- **Summary content** (markdown rendered):
  - Structured sections:
    - **Goals** — bullet list of identified project goals
    - **Constraints** — bullet list of constraints/limitations
    - **Acceptance Criteria** — bullet list
    - **Open Questions** — bullet list of unresolved items
  - May include additional sections depending on discovery
- **Private Notes** section (collapsible):
  - Markdown-rendered internal notes from AI analysis

- **Action bar** (bottom of card):
  - "Confirm Summary" button (primary, emerald/green) — advances to Planning
  - "Revise Summary" button (outline) — opens revision panel:
    - **Revision form** (appears inline or as a sheet):
      - Radio group for revision action: Expand / Clarify / Focus / Refocus
      - Textarea: "Revision notes" (placeholder: "What should be changed?")
      - "Submit Revision" button (primary)
      - "Cancel" button (ghost)
  - "Continue Interrogation" button (outline) — reopens Phase 4 for more questions

- **Generating state** (when summary is being created):
  - Skeleton loader for content area
  - Status: "Generating summary..." with spinner

---

## Phase 6: Planning — Implementation Plan

**Purpose**: AI generates an implementation plan. User reviews, revises, and approves.

**Main Content Area**:
- Card titled "Implementation Plan"

- **No plan state**:
  - Empty state illustration/icon
  - "Generate your implementation plan based on the confirmed summary."
  - "Generate Plan" button (primary, large)

- **Plan content** (markdown rendered):
  - Structured sections:
    - **Sections** — numbered list of implementation sections/phases
    - **Risks** — bullet list of identified risks
    - **Assumptions** — bullet list
    - Additional plan-specific sections
  - Full markdown rendering with headers, lists, code blocks

- **Action bar** (bottom of card):
  - "Approve Plan" button (primary, emerald/green) — locks plan, advances to Build Rules
  - "Revise Plan" button (outline) — opens revision panel:
    - **Revision form**:
      - Radio group: Expand / Clarify / Reorganize / Focus
      - Multi-select: Which sections to revise (checkboxes from plan sections)
      - Textarea: "Revision notes"
      - "Request Revision" button (primary)
      - "Cancel" button (ghost)
  - "Regenerate Plan" button (outline, destructive) — requires confirmation dialog: "This will discard the current plan and generate a new one. Continue?"
  - "Export Plan" button (ghost, icon: download)

- **Generating/Revising state**:
  - Inline progress with streaming content updates
  - Status badge: "Generating..." or "Revising..."

**Left Panel**: Hidden (no QA history needed)

---

## Phase 7: Build Rules — Project Rules

**Purpose**: Define project-specific rules that constrain how build tasks are generated.

**Main Content Area**:
- Card titled "Project Rules"
- Helper text: "Define rules and constraints for build task generation. These ensure generated tasks follow your project's conventions and requirements."

- **Rules editor**:
  - Textarea (large, 10+ rows) for entering project rules as markdown/text
  - Placeholder: "e.g. All new components must use Composition API, Tests required for all new features, Follow existing naming conventions..."

- **File upload area**:
  - Drag-and-drop zone or file picker: "Upload additional rule files"
  - List of uploaded files with name, size, delete button

- **Existing rules list** (if rules already defined):
  - Each rule as an editable card with delete button
  - Inline editing capability

- **Bottom action**:
  - "Generate Build Tasks →" button (primary)
  - Disabled tooltip if no rules: "Add at least one rule to continue"

**Left Panel**: Hidden

---

## Phase 8: Build Tasks — Task Generation & Review

**Purpose**: AI generates build tasks from the approved plan + rules. User reviews, edits, and approves.

**Main Content Area**:
- Card titled "Build Tasks"

- **Generating state**:
  - Progress bar / spinner: "Generating tasks from plan..."
  - Streaming task cards appearing one by one

- **Task list** (populated state):
  - Each task is a card with:
    - **Title** (editable inline)
    - **Status badge**: Queued (gray) / Running (blue) / Completed (green) / Failed (red)
    - **Description** (expandable, markdown)
    - **Instructions** (expandable, markdown, monospace-friendly for code)
    - **Action buttons per task**:
      - "Edit" (outline, icon: pencil)
      - "Delete" (ghost, icon: trash) — requires confirmation
      - "Regenerate" (ghost, icon: refresh) — opens amend notes input
    - Drag handle for reordering (optional)

- **Add task** button (outline, icon: plus): Opens inline form:
  - Title input
  - Description textarea
  - Instructions textarea (monospace)
  - "Save Task" / "Cancel" buttons

- **Bottom action bar**:
  - "Approve Build Tasks" button (primary, emerald/green) — advances to Build Execution
  - Task count summary: "8 tasks ready"

**Left Panel**: Hidden

---

## Phase 9: Build Execution — Running Tasks

**Purpose**: Execute approved build tasks. Monitor progress and handle failures.

**Main Content Area**:
- **Execution header card**:
  - Overall progress bar (X of Y tasks complete)
  - Status: "Executing..." / "Paused" / "Completed" / "Failed"
  - Elapsed time

- **Task execution list**:
  - Each task shows:
    - Title + status badge
    - Expandable execution log (monospace, scrollable, dark background like a terminal)
    - Timestamps for start/end
    - Duration
  - Active task has animated left border or pulse
  - Failed tasks show error in red with "Retry" button

- **Recent activity feed** (right side or below):
  - Last 8 events with timestamps
  - Event types: task started, task completed, task failed, build paused, build resumed
  - Auto-scrolling feed

- **Action bar**:
  - "Start Build" button (primary) — begins execution (only if not started)
  - "Pause Build" button (outline, amber) — pauses mid-execution
  - "Resume Build" button (primary) — resumes paused build
  - "Clarify Build" button (outline) — opens clarification form:
    - Textarea for clarification notes
    - "Submit Clarification" button
  - "Retry Failed" button (outline, only if failures exist)
  - "Rerun All" button (outline, destructive — confirmation required)

- **Completed state**:
  - All tasks green
  - Success banner: "Build completed successfully!"
  - Summary stats: total duration, tasks succeeded/failed
  - "View Results" / "Back to Sessions" buttons

**Left Panel**: Hidden

---

## Session Settings Sheet/Dialog

Accessible from "Session Settings" gear button in header. Opens as a **slide-over sheet** from the right.

Contents:
- **Session Name** input (editable)
- **Feature Brief** textarea (long-form project description)
- **Task Provider** section:
  - Connected provider info
  - Team selector
  - Project selector
  - Connect/Disconnect button
- **Tech Stack** section:
  - List of current entries with delete
  - Add new entry form
- "Save Settings" button (primary)
- "Cancel" button (ghost)

---

## Key UI States to Design

For each phase, provide:
1. **Default/active state** — normal operation
2. **Loading/generating state** — AI processing with skeleton/spinner
3. **Empty state** — no data yet
4. **Error state** — error banner with retry action
5. **Completed state** — phase done, ready to advance

Additional states:
- **Session paused** — overlay or banner indicating pause, with Resume button
- **Session failed** — prominent error state with retry options
- **Mobile responsive** — stacked layout, collapsible panels, bottom action bar

---

## Design Notes

- Use the established design system (slate neutrals, electric blue primary, emerald success, amber warning, red danger)
- Phase stepper should feel like clear progress — satisfying to advance
- The interrogation phase (Phase 4) is where users spend the most time — make it comfortable for extended use
- Markdown rendering should look clean with proper typography hierarchy
- Terminal/log output sections should use monospace font on a dark/muted background
- All action buttons should have clear loading states with spinners
- Confirmation dialogs for destructive actions (restart, regenerate, delete)
- Toast notifications for async operations (summary generated, plan approved, etc.)
