# Implementation Plan

Derived from discovery session 6.

# Figma Make UI Parity Migration - Implementation Plan

## Phase 1: Design System Foundation

### 1.1 CSS Variables & Theme Configuration
Create `resources/css/theme.css` with complete token definitions:
- Light mode `:root` block with ~50 tokens (background, foreground, card, primary, secondary, muted, accent, destructive, success, warning, border, input, ring, radius, chart colors, sidebar colors)
- Dark mode `.dark` block with corresponding dark theme values
- Font family variables: `--font-sans: 'DM Sans'`, `--font-mono: 'JetBrains Mono'`
- Base typography styles for h1-h4, label, button, input elements

Update `resources/css/app.css`:
- Add Google Fonts import for DM Sans (variable 100-1000) and JetBrains Mono (400-600)
- Import theme.css
- Retain existing dark mode form input overrides

Update `tailwind.config.js`:
- Extend colors with CSS variable references (background, foreground, card, primary, secondary, muted, accent, destructive, success, warning, border, input, ring)
- Extend fontFamily with DM Sans (sans) and JetBrains Mono (mono)
- Add radius tokens (sm, md, lg, xl) referencing CSS variables
- Keep existing plugins (forms, typography)

**Acceptance checks:**
- `npm run build` succeeds
- Theme tokens visible in browser DevTools under `:root` and `.dark`
- Tailwind classes like `bg-primary`, `text-foreground`, `border-border` render correctly

### 1.2 Package Dependencies
Update `package.json`:
- Add `lucide-vue-next` for icon library
- Add `@playwright/test` as devDependency for E2E tests

Run `npm install` and verify no errors.

**Acceptance checks:**
- `import { Clock } from 'lucide-vue-next'` compiles without error
- Playwright test runner initializes

---

## Phase 2: Core UI Component Library

### 2.1 Button Component
Create `resources/js/Components/ui/Button.vue`:
- Props: `variant` (default, destructive, outline, secondary, ghost, link), `size` (default h-9, sm h-8, lg h-10, icon size-9), `disabled`, `asChild`
- Use computed class composition matching Figma CVA pattern
- Include focus ring states: `focus-visible:ring-ring/50 focus-visible:ring-[3px]`
- Support slot content and SVG icon sizing

**Acceptance checks:**
- All 6 variants render distinct styles
- All 4 sizes render correct heights
- Disabled state shows `opacity-50` and `pointer-events-none`
- Focus visible shows ring effect

### 2.2 Input Component
Create `resources/js/Components/ui/Input.vue`:
- Props: `modelValue`, `type`, `size` (default h-9, sm h-8, lg h-10), `disabled`, `error`
- Emit `update:modelValue` for v-model compatibility
- Classes: border, rounded-md, `bg-input-background`, placeholder styling, focus ring
- Error state: `aria-invalid`, `border-destructive`, destructive ring

**Acceptance checks:**
- v-model binding works
- Focus shows `border-ring` and ring effect
- Error prop triggers destructive styling

### 2.3 Select Component
Create `resources/js/Components/ui/Select.vue`:
- Native select with consistent styling matching Input
- Props: `modelValue`, `options` (array of {value, label}), `size`, `disabled`
- ChevronDown icon indicator

**Acceptance checks:**
- Options render correctly
- Consistent height with Input at same size

### 2.4 Textarea Component
Create `resources/js/Components/ui/Textarea.vue`:
- Props: `modelValue`, `rows`, `disabled`, `error`
- Consistent border/focus styling with Input
- Optional auto-resize via CSS `field-sizing: content`

**Acceptance checks:**
- v-model binding works
- Focus ring matches Input behavior

### 2.5 Badge Component
Create `resources/js/Components/ui/Badge.vue`:
- Props: `variant` (default, secondary, destructive, outline)
- Classes: inline-flex, rounded-md, px-2, py-0.5, text-xs, font-medium
- Icon support with gap-1 spacing

**Acceptance checks:**
- All 4 variants render distinct styles
- Badge displays correctly with and without icons

### 2.6 Card System
Create components under `resources/js/Components/ui/`:
- `Card.vue`: Container with `bg-card`, `border`, `rounded-xl`, flex-col, gap-6
- `CardHeader.vue`: Grid layout with action slot support, px-6 pt-6
- `CardTitle.vue`: h4 element with leading-none
- `CardDescription.vue`: p element with `text-muted-foreground`
- `CardAction.vue`: Grid positioning for header actions
- `CardContent.vue`: px-6, last-child pb-6
- `CardFooter.vue`: flex, px-6 pb-6

**Acceptance checks:**
- Card composition works with all sub-components
- CardAction renders in header grid correctly

### 2.7 Table System
Create components under `resources/js/Components/ui/`:
- `Table.vue`: Responsive container with `overflow-x-auto`, caption-bottom
- `TableHeader.vue`: thead with `[&_tr]:border-b`
- `TableBody.vue`: tbody with `[&_tr:last-child]:border-0`
- `TableRow.vue`: tr with `hover:bg-muted/50`, `data-[state=selected]:bg-muted`, border-b
- `TableHead.vue`: th with h-10, px-2, `text-foreground`, font-medium, whitespace-nowrap
- `TableCell.vue`: td with p-2, whitespace-nowrap

**Acceptance checks:**
- Table renders with proper borders and spacing
- Hover state on rows works
- Last row has no bottom border

### 2.8 Loading Components
Create `resources/js/Components/ui/Skeleton.vue`:
- Props: `class` (passthrough)
- Classes: `bg-accent animate-pulse rounded-md`

Create `resources/js/Components/ui/Spinner.vue`:
- Props: `size` (sm 16px, default 24px, lg 32px)
- Border-based spinner animation

**Acceptance checks:**
- Skeleton pulses with correct background
- Spinner rotates at all sizes

---

## Phase 3: Modal/Dialog Restyling

### 3.1 Modal.vue Restyle
Update `resources/js/Components/Modal.vue`:
- Overlay: `bg-black/50` instead of `bg-gray-500 opacity-75`
- Content: `bg-card` instead of `bg-white dark:bg-gray-800`
- Border: add `border border-border`
- Animation: keep existing fade/scale transitions
- Preserve all existing logic (show, closeable, maxWidth, Escape handling)

**Acceptance checks:**
- Modal opens with fade-in overlay
- Content has card background and border
- Escape key closes when closeable=true
- maxWidth variants work (sm, md, lg, xl, 2xl)

### 3.2 ConfirmationModal.vue Restyle
Update `resources/js/Components/ConfirmationModal.vue`:
- Apply card styling to content
- Use semantic colors for destructive confirmations
- Preserve existing slot structure

**Acceptance checks:**
- Destructive actions show red-themed buttons
- Cancel/confirm buttons render correctly

### 3.3 DialogModal.vue Restyle
Update `resources/js/Components/DialogModal.vue`:
- Apply consistent styling with Modal
- Preserve form-friendly layout

**Acceptance checks:**
- Forms inside dialog submit correctly
- Padding and spacing match design

---

## Phase 4: App Shell Migration

### 4.1 AppLayout.vue Restyle
Update `resources/js/Layouts/AppLayout.vue`:
- Header: `sticky top-0 z-50 bg-card border-b border-border backdrop-blur-sm bg-opacity-95`
- Max width: `max-w-[1440px]` (replacing max-w-7xl)
- Height: h-14 (replacing h-16)
- Logo: Replace ApplicationLogo with Clock icon in 32x32 primary rounded-lg container
- App name: "Agent Scheduler" with font-semibold at 15px

Nav links:
- Desktop: horizontal with active indicator (bottom border primary, bg-primary/8)
- Replace inline Heroicon SVGs with Lucide icons (LayoutDashboard, Briefcase, Activity, MessageSquare, Wrench, GitBranch)
- Preserve `v-if="$page.props.delegationEnabled"` condition for Delegation link

User dropdown:
- Preserve profile photo conditional (`managesProfilePhotos`)
- Replace avatar fallback with initials in primary/10 background
- Add ChevronDown icon

Mobile nav:
- Convert responsive menu to Sheet-style slide from right
- Preserve all nav items with icons
- Preserve team switcher and settings

Teams dropdown:
- Preserve all team features (`hasTeamFeatures`, `canCreateTeams`)
- Preserve team switching form submission
- Style checkmark icon with Lucide Check

Page header slot:
- Update to `bg-card` and `max-w-[1440px]`

Main content:
- Update to `max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6`
- Background: `bg-background`

**Acceptance checks:**
- Navigation shows all 6 nav items (Dashboard, Jobs, Monitor, Messenger, Tools, Delegation)
- Delegation link only visible when delegationEnabled=true
- Team switcher works with multiple teams
- Profile dropdown shows Profile, API Tokens, Log Out
- Mobile hamburger opens sheet with all nav items
- Active state shows bottom border indicator

### 4.2 NavLink.vue Update
Update `resources/js/Components/NavLink.vue`:
- Active state: `text-primary bg-primary/8` with bottom border indicator
- Inactive state: `text-muted-foreground hover:text-foreground hover:bg-muted`
- Font: 13px, font-medium

**Acceptance checks:**
- Active route shows correct styling
- Hover state changes background

### 4.3 ResponsiveNavLink.vue Update
Update `resources/js/Components/ResponsiveNavLink.vue`:
- Active state: `bg-primary/8 text-primary`
- Inactive state: `text-muted-foreground hover:bg-muted hover:text-foreground`
- Add icon prop support

**Acceptance checks:**
- Mobile nav items show icons
- Active state styling correct

### 4.4 Dropdown Components Update
Update `resources/js/Components/Dropdown.vue` and `resources/js/Components/DropdownLink.vue`:
- Use card background and border
- Add consistent padding and hover states
- Replace inline SVGs with Lucide icons where applicable

**Acceptance checks:**
- Dropdown opens/closes correctly
- Items show hover state
- Separators render with border-border

---

## Phase 5: Auth Pages Migration

### 5.1 Authentication Card Restyle
Update `resources/js/Components/AuthenticationCard.vue`:
- Apply card styling with rounded-xl, border, shadow-lg
- Center content with proper padding

Update `resources/js/Components/AuthenticationCardLogo.vue`:
- Replace with Clock icon in primary background (matching app shell)

**Acceptance checks:**
- Auth pages show centered card with new styling

### 5.2 Login Page
Update `resources/js/Pages/Auth/Login.vue`:
- Replace TextInput with Input component
- Replace PrimaryButton with Button component (variant="default")
- Replace Checkbox styling
- Add form validation error states

**Acceptance checks:**
- Login form submits correctly
- Validation errors show with destructive styling
- Remember me checkbox works

### 5.3 Register Page
Update `resources/js/Pages/Auth/Register.vue`:
- Replace all form inputs with new components
- Replace buttons with Button component
- Preserve terms checkbox if enabled

**Acceptance checks:**
- Registration form submits correctly
- Password confirmation validation works

### 5.4 Password Reset Pages
Update `ForgotPassword.vue`, `ResetPassword.vue`:
- Apply new component styling
- Preserve existing form logic

**Acceptance checks:**
- Email sends for password reset
- New password can be set

### 5.5 Two-Factor Challenge
Update `TwoFactorChallenge.vue`:
- Style recovery code and OTP inputs
- Apply Button component for submit

**Acceptance checks:**
- OTP input accepts 6 digits
- Recovery code fallback works

### 5.6 Other Auth Pages
Update `ConfirmPassword.vue`, `VerifyEmail.vue`:
- Apply consistent styling
- Preserve existing logic

### 5.7 Messenger Link Pages
Update `resources/js/Pages/Messenger/LinkAccount.vue`:
- Style valid and invalid token states
- Apply card and button components

**Acceptance checks:**
- Valid token shows success state
- Invalid token shows error state

### 5.8 Terms and Privacy Pages
Update `TermsOfService.vue`, `PrivacyPolicy.vue`:
- Apply typography styling
- Use card container

**Acceptance checks:**
- Content renders with proper markdown/typography styling

---

## Phase 6: Dashboard Migration

### 6.1 Dashboard Page
Update `resources/js/Pages/Dashboard.vue`:
- Apply Card components for KPI cards
- Use new Button for window selector and refresh
- Apply Badge for status indicators
- Replace loading text with Skeleton components
- Style scheduler health semantics (healthy/warning/critical)

**Acceptance checks:**
- KPI cards render with correct values
- Window selector (1h, 6h, 24h, 7d) works
- Refresh button fetches new data
- Health status shows semantic colors

---

## Phase 7: Jobs Pages Migration

### 7.1 Jobs Index
Update `resources/js/Pages/Agent/Jobs/Index.vue`:
- Apply Table components for jobs list
- Replace filter chips with Badge components
- Apply Button components for row actions (Run Now, Edit, Delete/Restore)
- Replace loading with Skeleton
- Preserve source filter (user vs build)
- Preserve deleted filter toggle
- Preserve pagination

**Acceptance checks:**
- Jobs table renders with all columns
- Filters (source, deleted, search) work
- Pagination navigates correctly
- Run Now action triggers job
- Edit navigates to edit page
- Delete shows confirmation modal
- Deleted jobs show Restore action

### 7.2 Jobs Create/Edit
Update `resources/js/Pages/Agent/Jobs/Create.vue`, `Edit.vue`, `Partials/JobForm.vue`:
- Apply Card components for form container
- Replace TextInput, Select with new components
- Apply Button components
- Preserve advanced schedule controls
- Preserve runner permission profile controls

**Acceptance checks:**
- Job creation form submits correctly
- Job edit pre-fills existing values
- Schedule configuration works
- Cancel navigates back to index

---

## Phase 8: Monitor Page Migration

### 8.1 Monitor Index
Update `resources/js/Pages/Agent/Monitor/Index.vue`:
- Apply Card components for health cards
- Apply Table components for runs table
- Style event/log tail with monospace font
- Preserve auto-follow toggle
- Apply semantic status colors (running/success/failed)
- Style intervention modals (approval, clarification, rate-limit)

**Preserve all existing functionality:**
- Health polling
- Run selection and detail view
- Event streaming/polling
- Auto-follow scrolling
- Approval flow modal
- Clarification flow modal
- Rate-limit handling modal

**Acceptance checks:**
- Health cards show correct status
- Runs table displays with proper styling
- Event tail scrolls and auto-follows
- Approval modal opens and submits correctly
- Clarification modal opens and submits correctly
- Rate-limit modal shows options

---

## Phase 9: Tools Hub Migration

### 9.1 Tools Index
Update `resources/js/Pages/Tools/Index.vue`:
- Apply Card components for tool hub cards
- Add icons from Lucide (Search, Database, Cog, MessageSquare)
- Apply hover states

**Acceptance checks:**
- Tool cards display correctly
- Click navigation works to sub-pages

---

## Phase 10: Discovery Suite Migration

### 10.1 Discovery Index
Update `resources/js/Pages/Tools/Discovery/Index.vue`:
- Apply Card and Table components
- Style session status badges
- Apply Button for actions

**Acceptance checks:**
- Discovery sessions list renders
- Create new session navigates correctly

### 10.2 Discovery Create
Update `resources/js/Pages/Tools/Discovery/Create.vue`:
- Apply Card and form components
- Preserve all creation options

**Acceptance checks:**
- Session creation form works
- Redirects to wizard on success

### 10.3 Discovery Settings
Update `resources/js/Pages/Tools/Discovery/Settings.vue`:
- Apply Card and form components
- Preserve all settings fields

**Acceptance checks:**
- Settings save correctly

### 10.4 Discovery SessionSettings
Update `resources/js/Pages/Tools/Discovery/SessionSettings.vue`:
- Apply Card and form components
- Preserve all session-specific settings

**Acceptance checks:**
- Session settings save correctly

### 10.5 Discovery Wizard (Style-Only Migration)
Update `resources/js/Pages/Tools/Discovery/Wizard.vue`:

**CRITICAL: Style-only changes - preserve all existing logic**

Components to restyle:
- PhaseStepper: Apply new styling with active/completed indicators
- QaHistoryPanel: Apply Card and list styling
- QuestionRenderer: Apply typography and spacing
- AnswerInput: Use new Input/Textarea components
- StatusCard: Apply Card and Badge components
- StatsPanel: Apply Card styling
- SummaryViewer: Apply typography and Card
- PlanViewer: Apply Card, Table, and Badge components
- BuildPanel: Apply Card, Table, and Button components

Actions to preserve (all 50+):
- Phase navigation
- Answer submission and editing
- Continue to next phase
- Summary confirm/revise
- Plan generate/approve/revise/regenerate/export
- Build task CRUD/regenerate/approve
- Build start/pause/resume/clarify
- Retry/restart/delete/restore session

Provider integration to preserve:
- Linear OAuth connect/disconnect
- Team/project selection
- Tech stack add/remove

Realtime/polling to preserve:
- Echo channel subscription
- Event polling
- Status updates
- Progress indicators

**Acceptance checks:**
- All 10 phases accessible and functional
- Question/answer flow works
- Summary generation and approval works
- Plan generation and approval works
- Build task management works
- Build execution controls work
- Linear integration works
- Tech stack management works
- Echo realtime updates work
- No console errors or Vue warnings

---

## Phase 11: Messenger Control Plane Migration

### 11.1 Messenger Index
Update `resources/js/Pages/Tools/Messenger/Index.vue`:
- Apply Card components for connector cards
- Style health metrics with semantic colors
- Preserve schema-driven dynamic form fields
- Apply Button for connector actions (create, test, disconnect)
- Apply Table for session list
- Preserve session drill-down navigation

**Acceptance checks:**
- Connector list renders
- Dynamic form fields render based on schema
- Create connector flow works
- Test connection works
- Disconnect works
- Session list shows with drill-down

---

## Phase 12: Backups and Features Settings Migration

### 12.1 Backups Settings
Update `resources/js/Pages/Tools/Backups/Settings.vue`:
- Apply Card and form components
- Preserve all backup configuration options

**Acceptance checks:**
- Settings save correctly

### 12.2 Features Settings
Update `resources/js/Pages/Tools/Features/Settings.vue`:
- Apply Card components for feature toggles
- Preserve all feature flag controls

**Acceptance checks:**
- Feature toggles work correctly

---

## Phase 13: Delegation Suite Migration

### 13.1 Delegation Index
Update `resources/js/Pages/Agent/Delegation/Index.vue`:
- Apply Card and Table components
- Style graph visualization
- Apply Badge for status indicators

**Acceptance checks:**
- Delegation graphs list renders
- Click navigates to detail

### 13.2 Delegation Create
Update `resources/js/Pages/Agent/Delegation/Create.vue`:
- Apply Card and form components
- Preserve all creation options

**Acceptance checks:**
- Graph creation works

### 13.3 Delegation Show (Detail)
Update `resources/js/Pages/Agent/Delegation/Show.vue`:
- Apply Card components for graph detail
- Style task list with Table
- Preserve graph visualization

**Acceptance checks:**
- Graph detail renders
- Task list shows correctly

### 13.4 Delegation TaskDetail
Update `resources/js/Pages/Agent/Delegation/TaskDetail.vue`:
- Apply Card components
- Style status and output
- Preserve all task actions

**Acceptance checks:**
- Task detail renders correctly
- Actions work

### 13.5 Verification Approval
Update `resources/js/Pages/Agent/Delegation/VerificationApproval.vue`:
- Apply Card and Button components
- Style approval/rejection flow

**Acceptance checks:**
- Approval submission works
- Rejection with reason works

### 13.6 Delegatee Profiles
Update `ProfileIndex.vue`, `ProfileForm.vue`:
- Apply Card and Table components
- Apply form components for profile editing

**Acceptance checks:**
- Profile list renders
- Create/edit profile works

---

## Phase 14: Profile and API Tokens Migration

### 14.1 Profile Show
Update `resources/js/Pages/Profile/Show.vue`:
- Apply Card components for each section
- Style section separators

**Acceptance checks:**
- All profile sections render

### 14.2 Profile Information Form
Update `UpdateProfileInformationForm.vue`:
- Apply Card and form components
- Preserve photo upload if enabled

**Acceptance checks:**
- Profile info saves correctly
- Photo upload works

### 14.3 Password Form
Update `UpdatePasswordForm.vue`:
- Apply Card and form components

**Acceptance checks:**
- Password update works with validation

### 14.4 Two-Factor Form
Update `TwoFactorAuthenticationForm.vue`:
- Apply Card and Button components
- Style QR code display
- Preserve recovery codes display

**Acceptance checks:**
- 2FA enable/disable works
- Recovery codes can be regenerated

### 14.5 Browser Sessions Form
Update `LogoutOtherBrowserSessionsForm.vue`:
- Apply Card and Table components
- Style device list

**Acceptance checks:**
- Sessions list renders
- Logout other sessions works

### 14.6 Delete Account Form
Update `DeleteUserForm.vue`:
- Apply Card components
- Style destructive confirmation modal

**Acceptance checks:**
- Delete confirmation modal opens
- Account deletion works

### 14.7 API Tokens
Update `resources/js/Pages/API/Index.vue` and `Partials/ApiTokenManager.vue`:
- Apply Card and Table components
- Style token creation modal
- Style permission checkboxes
- Preserve token display modal (one-time view)

**Acceptance checks:**
- Token list renders
- Create token works
- Permissions can be selected
- New token displays once
- Revoke token works

---

## Phase 15: Icon Migration

### 15.1 Replace Inline SVGs
Across all migrated components and pages:
- Replace inline Heroicon SVGs with Lucide Vue imports
- Use consistent sizing (w-4 h-4 for inline, w-5 h-5 for buttons)

Common replacements:
- Chevron icons → ChevronDown, ChevronUp, ChevronLeft, ChevronRight
- Menu/Close → Menu, X
- User icons → User, Users
- Action icons → Plus, Trash2, Pencil, RefreshCw, Play, Pause
- Status icons → Check, AlertCircle, AlertTriangle, Info
- Navigation icons → LayoutDashboard, Briefcase, Activity, MessageSquare, Wrench, GitBranch

**Acceptance checks:**
- No inline SVG elements remain in migrated files
- All icons render at correct sizes
- Icons align properly with text

---

## Phase 16: Deprecated Component Cleanup

### 16.1 Mark Deprecated Components
Add deprecation comments to:
- `PrimaryButton.vue` → Use `Button variant="default"`
- `SecondaryButton.vue` → Use `Button variant="secondary"`
- `DangerButton.vue` → Use `Button variant="destructive"`
- `TextInput.vue` → Use `Input`

### 16.2 Usage Migration
Update all usages across pages to use new components.

**Acceptance checks:**
- No imports of deprecated components in migrated pages
- Deprecated files contain migration guidance comments

---

## Phase 17: E2E Test Suite

### 17.1 Playwright Setup
Create `playwright.config.ts`:
- Configure base URL
- Set up test directories
- Configure browser(s)

Create `tests/e2e/` directory structure.

### 17.2 Wizard E2E Tests
Create `tests/e2e/wizard.spec.ts`:
- Test phase transitions
- Test answer submission
- Test summary generation flow
- Test plan generation flow
- Test build task CRUD
- Test build execution controls

**Acceptance checks:**
- Tests run without flakiness
- All critical paths covered

### 17.3 Monitor E2E Tests
Create `tests/e2e/monitor.spec.ts`:
- Test run selection
- Test event polling
- Test approval flow
- Test clarification flow

**Acceptance checks:**
- Tests run without flakiness
- Intervention flows covered

---

## Phase 18: Verification

### 18.1 Manual Verification Checklists
Create per-page verification checklists covering:
- All buttons/actions function correctly
- All filters/toggles work
- All forms submit successfully
- All modals open/close properly
- Responsive behavior (desktop/mobile)
- Dark mode appearance
- Keyboard navigation
- Focus states

### 18.2 Build Verification
After each migration phase:
- Run `npm run build`
- Check for TypeScript/Vue warnings
- Check browser console for errors

### 18.3 Visual Comparison
For each migrated page:
- Compare against Figma site reference
- Compare against `/figma` component reference
- Verify token usage matches design

**Acceptance checks:**
- All checklists completed
- No build errors
- No console errors
- Visual parity achieved

## Sections

- Phase 1: Design System Foundation
- Phase 2: Core UI Component Library
- Phase 3: Modal/Dialog Restyling
- Phase 4: App Shell Migration
- Phase 5: Auth Pages Migration
- Phase 6: Dashboard Migration
- Phase 7: Jobs Pages Migration
- Phase 8: Monitor Page Migration
- Phase 9: Tools Hub Migration
- Phase 10: Discovery Suite Migration
- Phase 11: Messenger Control Plane Migration
- Phase 12: Backups and Features Settings Migration
- Phase 13: Delegation Suite Migration
- Phase 14: Profile and API Tokens Migration
- Phase 15: Icon Migration
- Phase 16: Deprecated Component Cleanup
- Phase 17: E2E Test Suite
- Phase 18: Verification


## Risks

- Discovery Wizard complexity (1571 lines, 10 phases, 50+ actions) requires careful style-only migration to avoid breaking Echo realtime, polling, and phase state management
- Monitor page intervention flows (approval, clarification, rate-limit) must be tested thoroughly as they involve modal state and async operations
- Tailwind v3 to CSS variable integration may require careful configuration to ensure proper dark mode switching without breaking existing dark: class usage during incremental migration
- Messenger schema-driven dynamic form fields must continue to render correctly based on connector schemas after component replacement
- Team switcher and profile photo conditionals from Jetstream must be preserved exactly to avoid breaking team-based access
- Delegation feature flag gating (delegationEnabled) must remain functional in both desktop and mobile navigation
- Icon migration from inline Heroicons to Lucide may miss some icons or use incorrect mappings if not systematically audited
- Content max-width change from 1280px to 1440px may cause layout shifts on existing pages if not applied consistently
- E2E tests for Wizard and Monitor require stable test fixtures and may be flaky if relying on polling/realtime behavior


## Assumptions

- Figma Make code in /figma directory represents authoritative design reference for tokens, component patterns, and styling
- Tailwind CSS v3.4 remains the production version (not upgrading to v4 which uses different @theme syntax)
- Google Fonts CDN is acceptable for DM Sans and JetBrains Mono loading
- lucide-vue-next package is compatible with Vue 3.3 and Vite build
- Existing Echo/Pusher realtime infrastructure remains unchanged
- All existing Laravel routes, controllers, and API endpoints remain unchanged
- Jetstream configuration (teams, API tokens, 2FA, profile photos) remains unchanged
- Feature flags (DELEGATION_ENABLED, DELEGATION_UI_ENABLED) continue to be passed via Inertia shared data
- Playwright is acceptable E2E test framework for verification
- Dark mode toggle mechanism remains unchanged (document.documentElement.classList.toggle('dark'))

