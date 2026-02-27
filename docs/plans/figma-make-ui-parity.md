# Implementation Plan

Derived from discovery session 6.

# Figma Make UI Parity Migration - Implementation Plan

## Overview
Full UI redesign of Laravel 12 + Inertia + Vue 3 application to match Figma Make design system. This is a style-only migration preserving all existing functionality, routes, API contracts, and Jetstream features.

---

## Section 1: Design System Foundation

### 1.1 Create CSS Variables Theme File
**File:** `resources/css/theme.css`

**Implementation:**
- Copy CSS variable definitions from `/figma/src/styles/theme.css`
- Define `:root` block with ~50 light theme tokens
- Define `.dark` block with dark theme tokens
- Include semantic colors: primary, secondary, muted, accent, destructive, success, warning
- Include layout tokens: radius, border, input, ring
- Include sidebar tokens for future sidebar components
- Remove Tailwind v4 `@theme inline` block (incompatible with v3)
- Remove `@custom-variant dark` syntax

**Tokens to include:**
- `--background`, `--foreground`
- `--card`, `--card-foreground`
- `--popover`, `--popover-foreground`
- `--primary`, `--primary-foreground`
- `--secondary`, `--secondary-foreground`
- `--muted`, `--muted-foreground`
- `--accent`, `--accent-foreground`
- `--destructive`, `--destructive-foreground`
- `--success`, `--success-foreground`
- `--warning`, `--warning-foreground`
- `--border`, `--input`, `--input-background`
- `--ring`, `--radius`
- `--font-sans`, `--font-mono`, `--font-size`
- `--font-weight-normal`, `--font-weight-medium`
- Chart colors `--chart-1` through `--chart-5`

### 1.2 Update app.css
**File:** `resources/css/app.css`

**Implementation:**
- Add Google Fonts import for DM Sans and JetBrains Mono
- Import theme.css before Tailwind directives
- Update `@layer base` rules for dark mode form inputs to use CSS variables
- Add base body styles using semantic tokens

**Font import URL:**
```
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:wght@400;500;600&display=swap');
```

### 1.3 Extend Tailwind Configuration
**File:** `tailwind.config.js`

**Implementation:**
- Replace Figtree font-family with DM Sans and JetBrains Mono
- Extend colors to reference CSS variables using `rgb(var(--color) / <alpha>)` pattern
- Add semantic color utilities: `bg-background`, `text-foreground`, `bg-primary`, `text-primary-foreground`, etc.
- Extend borderRadius with `radius-sm`, `radius-md`, `radius-lg`, `radius-xl`
- Add ring color utilities referencing `--ring`

**Color configuration pattern:**
```javascript
colors: {
  background: 'var(--background)',
  foreground: 'var(--foreground)',
  card: { DEFAULT: 'var(--card)', foreground: 'var(--card-foreground)' },
  primary: { DEFAULT: 'var(--primary)', foreground: 'var(--primary-foreground)' },
  // ... additional semantic colors
}
```

### 1.4 Add Lucide Vue Icons Package
**File:** `package.json`

**Implementation:**
- Add `lucide-vue-next` dependency
- Add `@playwright/test` devDependency for E2E tests

---

## Section 2: Vue Component Library

### 2.1 Button Component
**File:** `resources/js/Components/ui/Button.vue`

**Implementation:**
- Create unified button with CVA-style class composition
- Props: `variant` (default, destructive, outline, secondary, ghost, link), `size` (default, sm, lg, icon), `disabled`, `asChild`
- Default slot for content
- Base classes: `inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50`
- Variant-specific classes using semantic color tokens

**Variant mappings:**
- `default`: `bg-primary text-primary-foreground hover:bg-primary/90`
- `destructive`: `bg-destructive text-destructive-foreground hover:bg-destructive/90`
- `outline`: `border border-input bg-background hover:bg-accent hover:text-accent-foreground`
- `secondary`: `bg-secondary text-secondary-foreground hover:bg-secondary/80`
- `ghost`: `hover:bg-accent hover:text-accent-foreground`
- `link`: `text-primary underline-offset-4 hover:underline`

**Size mappings:**
- `default`: `h-9 px-4 py-2`
- `sm`: `h-8 rounded-md px-3 text-xs`
- `lg`: `h-10 rounded-md px-8`
- `icon`: `size-9`

### 2.2 Input Component
**File:** `resources/js/Components/ui/Input.vue`

**Implementation:**
- Props: `modelValue`, `size` (default, sm, lg), `type`, `placeholder`, `disabled`, `class`
- Emit: `update:modelValue`
- Base classes: `flex w-full rounded-md border border-input bg-input-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50`

**Size mappings:**
- `default`: `h-9`
- `sm`: `h-8 text-xs`
- `lg`: `h-10`

### 2.3 Select Component
**File:** `resources/js/Components/ui/Select.vue`

**Implementation:**
- Props: `modelValue`, `size`, `disabled`, `class`
- Emit: `update:modelValue`
- Default slot for option elements
- Consistent styling with Input component
- Base classes matching input with select-specific adjustments

### 2.4 Textarea Component
**File:** `resources/js/Components/ui/Textarea.vue`

**Implementation:**
- Props: `modelValue`, `rows`, `placeholder`, `disabled`, `class`, `autoResize`
- Emit: `update:modelValue`
- Consistent styling with Input component
- Optional auto-resize behavior using computed height

### 2.5 Badge Component
**File:** `resources/js/Components/ui/Badge.vue`

**Implementation:**
- Props: `variant` (default, secondary, destructive, outline), `asChild`
- Default slot for content
- Base classes: `inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2`

**Variant mappings:**
- `default`: `border-transparent bg-primary text-primary-foreground hover:bg-primary/80`
- `secondary`: `border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80`
- `destructive`: `border-transparent bg-destructive text-destructive-foreground hover:bg-destructive/80`
- `outline`: `text-foreground`

### 2.6 Card System Components
**Files:**
- `resources/js/Components/ui/Card.vue`
- `resources/js/Components/ui/CardHeader.vue`
- `resources/js/Components/ui/CardTitle.vue`
- `resources/js/Components/ui/CardDescription.vue`
- `resources/js/Components/ui/CardContent.vue`
- `resources/js/Components/ui/CardFooter.vue`

**Implementation:**
- Card.vue: `rounded-xl border bg-card text-card-foreground shadow-sm`
- CardHeader.vue: `flex flex-col space-y-1.5 p-6` with optional action slot
- CardTitle.vue: `font-semibold leading-none tracking-tight`
- CardDescription.vue: `text-sm text-muted-foreground`
- CardContent.vue: `p-6 pt-0`
- CardFooter.vue: `flex items-center p-6 pt-0` with optional border-top

### 2.7 Table System Components
**Files:**
- `resources/js/Components/ui/Table.vue`
- `resources/js/Components/ui/TableHeader.vue`
- `resources/js/Components/ui/TableBody.vue`
- `resources/js/Components/ui/TableRow.vue`
- `resources/js/Components/ui/TableHead.vue`
- `resources/js/Components/ui/TableCell.vue`

**Implementation:**
- Table.vue: Wrapper with `relative w-full overflow-auto` container
- TableHeader.vue: `[&_tr]:border-b`
- TableBody.vue: `[&_tr:last-child]:border-0`
- TableRow.vue: `border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted`
- TableHead.vue: `h-10 px-2 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0`
- TableCell.vue: `p-2 align-middle [&:has([role=checkbox])]:pr-0`

### 2.8 Loading State Components
**Files:**
- `resources/js/Components/ui/Skeleton.vue`
- `resources/js/Components/ui/Spinner.vue`

**Implementation:**
- Skeleton.vue: Props for `class`; base classes `animate-pulse rounded-md bg-accent`
- Spinner.vue: Props for `size` (sm, default, lg); border-based spinner with `animate-spin`

**Spinner size mappings:**
- `sm`: `size-4 border-2`
- `default`: `size-6 border-2`
- `lg`: `size-8 border-3`

---

## Section 3: Modal System Restyle

### 3.1 Update Modal.vue
**File:** `resources/js/Components/Modal.vue`

**Implementation:**
- Update overlay background: `bg-black/50` (currently `bg-gray-500 opacity-75`)
- Update modal container: `bg-card rounded-xl shadow-lg` (currently `bg-white dark:bg-gray-800 rounded-lg`)
- Preserve all existing props: `show`, `maxWidth`, `closeable`
- Preserve all existing emit: `close`
- Preserve escape key handling and scroll lock
- Preserve Vue transition classes
- Update transition classes to match semantic tokens

### 3.2 Update ConfirmationModal.vue
**File:** `resources/js/Components/ConfirmationModal.vue`

**Implementation:**
- Use restyled Modal as base
- Ensure destructive actions use `bg-destructive text-destructive-foreground`
- Preserve slot structure

### 3.3 Update DialogModal.vue
**File:** `resources/js/Components/DialogModal.vue`

**Implementation:**
- Use restyled Modal as base
- Update any hardcoded colors to semantic tokens
- Preserve slot structure

---

## Section 4: App Shell Migration

### 4.1 Update AppLayout.vue
**File:** `resources/js/Layouts/AppLayout.vue`

**Implementation:**
- Update nav bar: `bg-card/95 backdrop-blur-sm border-b border-border sticky top-0 z-50`
- Update max-width from `max-w-7xl` to custom `max-w-[1440px]`
- Replace ApplicationLogo with Clock icon in rounded-lg primary background container (32x32)
- Replace inline Heroicon SVGs with Lucide Vue components
- Update navigation link styling to use semantic tokens
- Add active indicator as bottom border for desktop nav
- Update mobile hamburger menu sheet transition
- Update dropdown styling to use semantic tokens
- Update page header background: `bg-card border-b border-border`

**Preserve:**
- All route references (dashboard, agent.jobs.index, agent.monitor.index, tools.messenger.index, tools.index, agent.delegation.index)
- `$page.props.delegationEnabled` conditional rendering
- `$page.props.jetstream.hasTeamFeatures` team dropdown
- `$page.props.jetstream.managesProfilePhotos` profile photo conditional
- `$page.props.jetstream.hasApiFeatures` API tokens link
- `$page.props.jetstream.canCreateTeams` create team link
- Team switcher functionality
- Logout form submission
- Responsive navigation toggle

**Lucide icons to use:**
- Menu (hamburger)
- X (close)
- ChevronDown (dropdowns)
- Check (team switcher checkmark)
- Clock (logo)

### 4.2 Update NavLink.vue
**File:** `resources/js/Components/NavLink.vue`

**Implementation:**
- Update active/inactive classes to use semantic tokens
- Active state: `text-foreground border-b-2 border-primary`
- Inactive state: `text-muted-foreground hover:text-foreground`

### 4.3 Update ResponsiveNavLink.vue
**File:** `resources/js/Components/ResponsiveNavLink.vue`

**Implementation:**
- Update styling to use semantic tokens
- Active state: `bg-primary/10 text-primary`
- Inactive state: `text-foreground hover:bg-accent`

### 4.4 Update Dropdown.vue
**File:** `resources/js/Components/Dropdown.vue`

**Implementation:**
- Update dropdown panel styling: `bg-popover text-popover-foreground rounded-lg shadow-lg border border-border`
- Preserve trigger slot, content slot, alignment, width props

### 4.5 Update DropdownLink.vue
**File:** `resources/js/Components/DropdownLink.vue`

**Implementation:**
- Update styling to use semantic tokens
- Hover state: `hover:bg-accent`

---

## Section 5: Auth Pages Migration

### 5.1 Update AuthenticationCard.vue
**File:** `resources/js/Components/AuthenticationCard.vue`

**Implementation:**
- Update background: `bg-background`
- Update card container: `bg-card rounded-xl shadow-lg border border-border`

### 5.2 Update Auth Pages
**Files:**
- `resources/js/Pages/Auth/Login.vue`
- `resources/js/Pages/Auth/Register.vue`
- `resources/js/Pages/Auth/ForgotPassword.vue`
- `resources/js/Pages/Auth/ResetPassword.vue`
- `resources/js/Pages/Auth/TwoFactorChallenge.vue`
- `resources/js/Pages/Auth/ConfirmPassword.vue`
- `resources/js/Pages/Auth/VerifyEmail.vue`

**Implementation per page:**
- Replace PrimaryButton with Button (variant="default")
- Replace TextInput with Input component
- Replace InputLabel with styled label using semantic tokens
- Update InputError to use `text-destructive`
- Replace inline Heroicon SVGs with Lucide components
- Preserve all form logic, validation, Inertia form handling

### 5.3 Update Welcome.vue
**File:** `resources/js/Pages/Welcome.vue`

**Implementation:**
- Update background and text colors to semantic tokens
- Update button/link styling
- Preserve auth conditional rendering

### 5.4 Update Terms and Privacy Pages
**Files:**
- `resources/js/Pages/TermsOfService.vue`
- `resources/js/Pages/PrivacyPolicy.vue`

**Implementation:**
- Update styling to use semantic tokens
- Update prose styling for dark mode

### 5.5 Update Messenger Link Pages
**File:** `resources/js/Pages/Messenger/LinkAccount.vue`

**Implementation:**
- Update styling for valid and invalid token states
- Use success and destructive semantic colors appropriately

---

## Section 6: Dashboard Migration

### 6.1 Update Dashboard.vue
**File:** `resources/js/Pages/Dashboard.vue`

**Implementation:**
- Update max-width from `max-w-7xl` to `max-w-[1440px]`
- Replace inline card styles with Card component
- Update KPI card styling to use semantic tokens
- Update select and button styling to match new components
- Update scheduler health color logic to use semantic tokens (success, warning, destructive)
- Replace loading "…" with Skeleton components
- Preserve all data loading, window selector, refresh functionality

**Skeleton implementation:**
- Use Skeleton component for metric values during loading
- Approximate text size for metric placeholders

---

## Section 7: Jobs Pages Migration

### 7.1 Update Jobs Index
**File:** `resources/js/Pages/Agent/Jobs/Index.vue`

**Implementation:**
- Update max-width to `max-w-[1440px]`
- Replace filter inputs with Input and Select components
- Replace filter chip buttons with Badge-style buttons or dedicated FilterChip component
- Replace inline table with Table system components
- Update "Loading..." row with Skeleton components
- Replace action buttons with Button components (variant="outline" or "ghost")
- Update Link buttons to use Button styling
- Update pagination buttons with Button components
- Replace inline Heroicon SVGs with Lucide components
- Update error message styling to use destructive semantic colors

**Preserve:**
- All filter reactive state
- All API calls (load, toggle, runNow, removeJob, restoreJob, setPage)
- Filter chip click handlers
- Pagination logic
- Build job badge
- Delete/restore conditional rendering

### 7.2 Update Jobs Create
**File:** `resources/js/Pages/Agent/Jobs/Create.vue`

**Implementation:**
- Update page styling to semantic tokens
- Use Card component for form container
- Use Input, Select, Textarea components
- Use Button components
- Preserve form submission logic

### 7.3 Update Jobs Edit
**File:** `resources/js/Pages/Agent/Jobs/Edit.vue`

**Implementation:**
- Same approach as Jobs Create
- Preserve existing job data loading and update logic

### 7.4 Update JobForm Partial
**File:** `resources/js/Pages/Agent/Jobs/Partials/JobForm.vue`

**Implementation:**
- Update all form controls to new component library
- Preserve all form validation and submission logic

---

## Section 8: Monitor Page Migration

### 8.1 Update Monitor Index
**File:** `resources/js/Pages/Agent/Monitor/Index.vue`

**Style-only migration approach:**
- Update max-width to `max-w-[1440px]`
- Replace inline health card styling with Card components
- Replace inline table styling with Table system components
- Update run status colors to semantic tokens
- Update event/log tail styling
- Update modal styling using restyled Modal components
- Replace inline buttons with Button components
- Update auto-follow toggle styling
- Replace loading states with Skeleton components
- Replace inline Heroicon SVGs with Lucide components

**Preserve (do not modify):**
- All reactive state declarations
- All computed properties
- All polling logic (BASE_POLL_MS, INACTIVE_POLL_MS, HIDDEN_POLL_MS, BACKOFF)
- All API calls
- approvalStateForRun function
- clarificationStateForRun function
- rateLimitStateForRun function
- All modal open/close handlers
- All approval/clarification/rate-limit submission handlers
- formattedEvents computed
- autoFollow toggle logic
- Echo channel subscriptions (if present)

---

## Section 9: Tools Hub Migration

### 9.1 Update Tools Index
**File:** `resources/js/Pages/Tools/Index.vue`

**Implementation:**
- Update max-width to `max-w-[1440px]`
- Replace inline card styling with Card components
- Update hover states to use semantic tokens
- Update category badge colors to semantic tokens
- Preserve all Link hrefs

---

## Section 10: Discovery Pages Migration

### 10.1 Update Discovery Index
**File:** `resources/js/Pages/Tools/Discovery/Index.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card, Table, Button components
- Replace loading states with Skeleton
- Preserve all data loading and session list functionality

### 10.2 Update Discovery Create
**File:** `resources/js/Pages/Tools/Discovery/Create.vue`

**Implementation:**
- Update form styling with new components
- Preserve form submission logic

### 10.3 Update Discovery Settings
**File:** `resources/js/Pages/Tools/Discovery/Settings.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card and form components
- Preserve settings logic

### 10.4 Update Discovery SessionSettings
**File:** `resources/js/Pages/Tools/Discovery/SessionSettings.vue`

**Implementation:**
- Update styling to semantic tokens
- Use form components
- Preserve session settings logic

### 10.5 Update Discovery Wizard (Style-Only)
**File:** `resources/js/Pages/Tools/Discovery/Wizard.vue`

**Critical style-only migration:**
- Update all inline styling to semantic tokens
- Replace inline buttons with Button components
- Replace inline inputs with Input components
- Replace loading states with Skeleton/Spinner components
- Update Card-like containers to use Card components
- Update Badge-like elements to use Badge component
- Replace inline Heroicon SVGs with Lucide components
- Update error/notice/status styling to semantic tokens

**Preserve absolutely (do not modify):**
- All 10 PHASE constants
- All reactive state declarations (session, events, loading, error, busy, notice, polling, action states, provider states, tech stack states)
- All computed properties (latestQuestion, selectedQuestion, etc.)
- All phase transition logic
- All answer submission handlers
- All summary/plan generation/revision/approval handlers
- All build task CRUD handlers
- All build execution control handlers (start, pause, resume, clarify)
- All provider integration handlers (connect, disconnect, settings, context loading)
- All tech stack handlers
- All Echo realtime subscriptions
- All polling timer logic
- All error formatting utilities

**Interrogation sub-components to also update:**
- `resources/js/Components/Interrogation/AnswerInput.vue`
- `resources/js/Components/Interrogation/PhaseStepper.vue`
- `resources/js/Components/Interrogation/QaHistoryPanel.vue`
- `resources/js/Components/Interrogation/QuestionRenderer.vue`
- `resources/js/Components/Interrogation/SessionStatusBadge.vue`
- `resources/js/Components/Interrogation/StatsPanel.vue`
- `resources/js/Components/Interrogation/StatusCard.vue`
- `resources/js/Components/Interrogation/SummaryViewer.vue`
- `resources/js/Components/Interrogation/PlanViewer.vue`
- `resources/js/Components/Interrogation/BuildPanel.vue`

---

## Section 11: Messenger Pages Migration

### 11.1 Update Messenger Index
**File:** `resources/js/Pages/Tools/Messenger/Index.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card components for connector cards and metrics
- Use Table components for sessions table
- Use Button components for actions
- Update schema-driven dynamic form fields styling
- Replace loading states with Skeleton

**Preserve:**
- Schema-driven connector form field rendering
- Connector lifecycle actions (create, test, disconnect)
- Session drill-down navigation
- All API integrations

---

## Section 12: Backups and Features Settings

### 12.1 Update Backups Settings
**File:** `resources/js/Pages/Tools/Backups/Settings.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card, Input, Select, Button components
- Preserve all backup configuration and action logic

### 12.2 Update Features Settings
**File:** `resources/js/Pages/Tools/Features/Settings.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card, Table, Button components
- Preserve feature flag toggle logic

---

## Section 13: Delegation Pages Migration

### 13.1 Update Delegation Index
**File:** `resources/js/Pages/Agent/Delegation/Index.vue`

**Implementation:**
- Update max-width to `max-w-[1440px]`
- Update styling to semantic tokens
- Use Card, Table, Button components
- Preserve graph listing functionality

### 13.2 Update Delegation Create
**File:** `resources/js/Pages/Agent/Delegation/Create.vue`

**Implementation:**
- Update form styling with new components
- Preserve graph creation logic

### 13.3 Update Delegation Show
**File:** `resources/js/Pages/Agent/Delegation/Show.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card components for graph details
- Preserve graph detail functionality

### 13.4 Update Delegation TaskDetail
**File:** `resources/js/Pages/Agent/Delegation/TaskDetail.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card, Badge, Button components
- Preserve task detail functionality

### 13.5 Update Delegation VerificationApproval
**File:** `resources/js/Pages/Agent/Delegation/VerificationApproval.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card, Button components
- Update approval/rejection button colors to semantic tokens
- Preserve verification approval logic

### 13.6 Update Delegatee ProfileIndex
**File:** `resources/js/Pages/Agent/Delegation/ProfileIndex.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Table, Button components
- Preserve profile listing functionality

### 13.7 Update Delegatee ProfileForm
**File:** `resources/js/Pages/Agent/Delegation/ProfileForm.vue`

**Implementation:**
- Update form styling with new components
- Preserve profile create/edit logic

---

## Section 14: Profile and API Token Pages Migration

### 14.1 Update Profile Show
**File:** `resources/js/Pages/Profile/Show.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card components for sections
- Preserve all Jetstream profile section rendering

### 14.2 Update Profile Partials
**Files:**
- `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`
- `resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue`
- `resources/js/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue`
- `resources/js/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue`
- `resources/js/Pages/Profile/Partials/DeleteUserForm.vue`

**Implementation per partial:**
- Replace PrimaryButton/SecondaryButton/DangerButton with Button variants
- Replace TextInput with Input component
- Update inline styling to semantic tokens
- Preserve all Inertia form handling and Jetstream integration

### 14.3 Update API Tokens Index
**File:** `resources/js/Pages/API/Index.vue`

**Implementation:**
- Update styling to semantic tokens
- Use Card, Table, Button components
- Preserve API token management functionality

### 14.4 Update API Token Manager
**File:** `resources/js/Pages/API/Partials/ApiTokenManager.vue`

**Implementation:**
- Update modal and form styling
- Use new component library
- Preserve permission selection, token creation/deletion logic

---

## Section 15: Shared Components Migration

### 15.1 Update Remaining Shared Components
**Files:**
- `resources/js/Components/ActionMessage.vue`
- `resources/js/Components/ActionSection.vue`
- `resources/js/Components/Banner.vue`
- `resources/js/Components/Checkbox.vue`
- `resources/js/Components/ConfirmsPassword.vue`
- `resources/js/Components/FormSection.vue`
- `resources/js/Components/InputError.vue`
- `resources/js/Components/InputLabel.vue`
- `resources/js/Components/SectionBorder.vue`
- `resources/js/Components/SectionTitle.vue`
- `resources/js/Components/Welcome.vue`

**Implementation:**
- Update all inline styling to semantic tokens
- Replace hardcoded dark: classes with semantic references
- Preserve all existing functionality

### 15.2 Update Markdown Components
**Files:**
- `resources/js/Components/Markdown/MarkdownRenderer.vue`
- `resources/js/Components/Markdown/MarkdownEditor.vue`

**Implementation:**
- Update styling to semantic tokens
- Preserve markdown rendering/editing functionality

---

## Section 16: Icon Migration

### 16.1 Replace Inline SVGs Across Codebase
**Scope:** All Vue files with inline Heroicon SVGs

**Implementation:**
- Import Lucide Vue components where needed
- Replace inline `<svg>` elements with Lucide components
- Common replacements:
  - Chevron icons → ChevronDown, ChevronUp, ChevronLeft, ChevronRight
  - Check/checkmark → Check, CheckCircle
  - X/close → X, XCircle
  - Menu hamburger → Menu
  - User → User
  - Cog/settings → Settings
  - Plus → Plus
  - Trash → Trash2
  - Edit/pencil → Pencil
  - Refresh → RefreshCw
  - Clock → Clock
  - Activity → Activity
  - Briefcase → Briefcase
  - Layout → LayoutDashboard
  - Message → MessageSquare
  - Wrench → Wrench
  - GitBranch → GitBranch
  - Moon/Sun → Moon, Sun
  - Logout → LogOut
  - Key → Key

---

## Section 17: E2E Test Setup

### 17.1 Playwright Configuration
**Files:**
- `playwright.config.ts`
- `tests/e2e/` directory

**Implementation:**
- Install Playwright dependencies
- Configure test directory and base URL
- Set up test fixtures for authenticated user state

### 17.2 Wizard E2E Tests
**File:** `tests/e2e/wizard.spec.ts`

**Test cases:**
- Navigate to discovery wizard from index
- Phase transitions through interrogation flow
- Answer submission and question progression
- Summary generation trigger and display
- Plan generation and revision
- Build task list display
- Build task CRUD operations
- Build execution start/pause/resume

### 17.3 Monitor E2E Tests
**File:** `tests/e2e/monitor.spec.ts`

**Test cases:**
- Run list display
- Run selection and event loading
- Approval modal open and submission
- Clarification modal open and submission
- Auto-follow toggle behavior

---

## Section 18: Deprecation and Cleanup

### 18.1 Mark Legacy Components for Deprecation
**Files:**
- `resources/js/Components/PrimaryButton.vue`
- `resources/js/Components/SecondaryButton.vue`
- `resources/js/Components/DangerButton.vue`
- `resources/js/Components/TextInput.vue`

**Implementation:**
- Add deprecation comments to these files
- Do not delete until all usages are migrated
- Create migration guide mapping old to new components

### 18.2 Update Import Statements
**Scope:** All pages using deprecated components

**Implementation:**
- Replace imports of PrimaryButton with Button
- Replace imports of SecondaryButton with Button (variant="secondary")
- Replace imports of DangerButton with Button (variant="destructive")
- Replace imports of TextInput with Input

---

## Section 19: Verification

### 19.1 Manual Verification Checklists

**Per-page checklist template:**
- [ ] All buttons clickable and trigger expected actions
- [ ] All form submissions work correctly
- [ ] All filters/toggles update state correctly
- [ ] All modals open/close properly
- [ ] Loading states appear during data fetching
- [ ] Error states display correctly
- [ ] Desktop layout correct (≥1024px)
- [ ] Mobile layout correct (<768px)
- [ ] Dark mode appearance correct
- [ ] Focus states visible on keyboard navigation
- [ ] No console errors or Vue warnings

**Create checklists for:**
- Auth pages (Login, Register, ForgotPassword, ResetPassword, TwoFactorChallenge, ConfirmPassword, VerifyEmail)
- Dashboard
- Jobs (Index, Create, Edit)
- Monitor
- Tools hub
- Discovery (Index, Create, Settings, SessionSettings, Wizard)
- Messenger
- Backups settings
- Features settings
- Delegation (Index, Create, Show, TaskDetail, VerificationApproval, ProfileIndex, ProfileForm)
- Profile (Show + all partials)
- API Tokens

### 19.2 Build Verification

**After each section:**
- Run `npm run build`
- Verify no build errors
- Verify no TypeScript/ESLint errors in changed files

### 19.3 Functional Parity Verification

**High-risk surfaces requiring extra verification:**

**Wizard verification:**
- All 10 phases accessible
- Provider connection flow works (Linear OAuth)
- Tech stack add/remove works
- All 50+ actions functional
- Echo realtime updates work
- Polling updates work

**Monitor verification:**
- Health polling works
- Run selection updates events
- All intervention modals work (approval, clarification, rate-limit)
- Auto-follow scrolls correctly

**Jobs verification:**
- All filters work
- Pagination works
- Run now action works
- Toggle enable/disable works
- Delete/restore works

---

## Discoverability and Navigation Exposure

### Navigation Verification
All navigation items must remain accessible:
- Dashboard: Always visible in desktop and mobile nav
- Jobs: Always visible in desktop and mobile nav
- Monitor: Always visible in desktop and mobile nav
- Messenger: Always visible in desktop and mobile nav
- Tools: Always visible in desktop and mobile nav
- Delegation: Conditionally visible when `$page.props.delegationEnabled` is true

### Route Preservation
No route changes permitted. All existing routes remain:
- `/dashboard`
- `/agent/jobs`, `/agent/jobs/create`, `/agent/jobs/{id}/edit`
- `/agent/monitor`
- `/tools`, `/tools/messenger`, `/tools/discovery/*`, `/tools/backups/settings`, `/tools/features/settings`
- `/agent/delegation/*`
- `/user/profile`, `/user/api-tokens`
- `/login`, `/register`, `/forgot-password`, `/reset-password`, `/two-factor-challenge`, `/confirm-password`, `/verify-email`
- `/terms-of-service`, `/privacy-policy`
- `/messenger/link-account/*`

### Feature Flag UI Preservation
Delegation navigation item visibility controlled by `$page.props.delegationEnabled` must be preserved in both desktop and mobile navigation.

## Sections

- Design System Foundation
- Vue Component Library
- Modal System Restyle
- App Shell Migration
- Auth Pages Migration
- Dashboard Migration
- Jobs Pages Migration
- Monitor Page Migration
- Tools Hub Migration
- Discovery Pages Migration
- Messenger Pages Migration
- Backups and Features Settings
- Delegation Pages Migration
- Profile and API Token Pages Migration
- Shared Components Migration
- Icon Migration
- E2E Test Setup
- Deprecation and Cleanup
- Verification


## Risks

- Discovery Wizard complexity (1571 lines, 10 phases, 50+ actions) - style-only changes could inadvertently break logic if markup structure changes affect computed properties or DOM queries
- Monitor page intervention modals (approval, clarification, rate-limit) rely on specific run metadata structure - modal styling changes must preserve data binding
- Echo realtime subscriptions in Wizard may break if component lifecycle hooks are affected by structural changes
- Tailwind v3 does not support @theme inline syntax from Figma reference - manual conversion required for all token references
- Package.json shows Tailwind v3.4.0 but @tailwindcss/vite v4.0.0 - potential version mismatch may cause CSS compilation issues
- lucide-vue-next icon names differ from Heroicons - incorrect mappings could cause missing icons
- CSS variable fallback in Tailwind v3 requires specific syntax (color: rgb(var(--color))) that differs from Figma reference
- max-w-7xl to max-w-[1440px] change affects 15+ pages - inconsistent application would create visual inconsistency
- Dark mode implementation using .dark class selector must be preserved - semantic tokens depend on class presence on parent element
- Playwright E2E tests require authenticated session setup - may need Laravel session/auth scaffolding
- Jetstream profile photo conditional rendering ($page.props.jetstream.managesProfilePhotos) must be preserved in AppLayout
- Team features conditional rendering ($page.props.jetstream.hasTeamFeatures) controls entire team dropdown visibility
- Feature flag delegation conditional ($page.props.delegationEnabled) controls navigation visibility - must not be accidentally removed
- Form validation error states must use destructive semantic colors - InputError component updates critical
- Loading state replacement (text to Skeleton) in tables may affect layout during transitions
- Modal z-index layering with sticky nav header requires careful coordination
- Browser sessions logout and account deletion are destructive Jetstream actions - confirmation modal styling changes must not break functionality


## Assumptions

- Figma /figma directory contains React reference code (based on .tsx search returning no results in components/ui) - design tokens are the primary reference, not component implementations
- Current Tailwind version is 3.4.0 as shown in package.json despite @tailwindcss/vite being v4 - CSS variable syntax must target v3 compatibility
- Google Fonts are acceptable for web font loading (DM Sans, JetBrains Mono)
- lucide-vue-next package is compatible with Vue 3.3.13 and Vite 7
- Playwright can be integrated without conflicting with existing test setup (no tests/e2e directory exists currently)
- CSS variable approach with semantic tokens is supported in all target browsers
- Existing Laravel Echo integration continues working with restyled components
- Inertia.js v2 page props structure ($page.props.auth, $page.props.jetstream, $page.props.delegationEnabled) remains stable
- All existing API endpoints under /agent/api/v1/* maintain their current request/response contracts
- vite build and vite build --ssr continue working with added CSS imports
- Dark mode toggle mechanism exists or is handled by OS preference (no explicit toggle implementation needed)
- Badge component in Figma serves same purpose as filter chips in Jobs index
- Card component can replace current border/rounded-lg/bg-white patterns across all pages
- Table component overflow handling matches current inline table implementations
- 15px base font size from Figma reference is acceptable (current is browser default 16px)
- No server-side rendering issues will arise from component changes (SSR build must still pass)

