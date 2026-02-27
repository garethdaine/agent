# Requirements Discovery Summary

Session: 6

# Figma Make UI Parity Migration

## Overview
Full UI redesign of Laravel 12 + Inertia + Vue 3 application to match Figma Make design system while preserving all existing functionality, routes, API contracts, and Jetstream features.

## Design System Foundation

### CSS Variables & Tokens
Adopt Figma `theme.css` wholesale, converting `@theme inline` syntax to Tailwind v3-compatible configuration:

**Light Theme (`:root`):**
- `--background: #f8f9fb` / `--foreground: #0f172a`
- `--card: #ffffff` / `--card-foreground: #0f172a`
- `--primary: #0284c7` / `--primary-foreground: #ffffff`
- `--secondary: #f1f5f9` / `--secondary-foreground: #334155`
- `--muted: #f1f5f9` / `--muted-foreground: #64748b`
- `--accent: #e2e8f0` / `--accent-foreground: #0f172a`
- `--destructive: #dc2626` / `--destructive-foreground: #ffffff`
- `--success: #059669` / `--success-foreground: #ffffff`
- `--warning: #d97706` / `--warning-foreground: #ffffff`
- `--border: #e2e8f0` / `--input: #e2e8f0` / `--ring: #0284c7`
- `--radius: 0.5rem`

**Dark Theme (`.dark`):**
- `--background: #0c1222` / `--foreground: #e2e8f0`
- `--card: #131b2e` / `--primary: #38bdf8`
- `--destructive: #ef4444` / `--success: #34d399` / `--warning: #fbbf24`
- `--border: #1e293b` / `--ring: #38bdf8`

### Typography
- **Font families:** DM Sans (sans), JetBrains Mono (mono)
- **Loading:** Google Fonts `@import` in app.css
- **Base size:** 15px root
- **Weights:** 400 (normal), 500 (medium), 600 (semibold)

### Layout
- **Content max-width:** 1440px (replacing max-w-7xl)
- **Spacing:** Tailwind defaults with semantic token references

## Component Library

### Button.vue
Unified component with CVA-style class composition:
- **Variants:** default, destructive, outline, secondary, ghost, link
- **Sizes:** default (h-9), sm (h-8), lg (h-10), icon (size-9)
- **Props:** `variant`, `size`, `disabled`, `asChild`

### Form Controls
**Input.vue:**
- Sizes: default (h-9), sm (h-8), lg (h-10)
- States: default, focus, error, disabled
- Dark mode via CSS variables

**Select.vue:**
- Native select with consistent styling
- Size variants matching Input

**Textarea.vue:**
- Consistent border/focus styling
- Auto-resize option

### Badge.vue
- **Variants:** default, secondary, destructive, outline
- **Usage:** Status indicators, filter chips, tags
- **Props:** `variant`, `asChild`

### Card System
- **Card.vue:** Container with `bg-card`, `border`, `rounded-xl`
- **CardHeader.vue:** Grid layout with optional action slot
- **CardTitle.vue:** h4 styling
- **CardDescription.vue:** Muted text
- **CardContent.vue:** Padded content area
- **CardFooter.vue:** Flex footer with border-top option

### Table System
- **Table.vue:** Responsive container with overflow-x-auto
- **TableHeader.vue:** Sticky header support
- **TableBody.vue:** Last-row border removal
- **TableRow.vue:** Hover states, selected state
- **TableHead.vue:** Uppercase tracking, muted color
- **TableCell.vue:** Consistent padding

### Loading States
- **Skeleton.vue:** `bg-accent animate-pulse rounded-md`
- **Spinner.vue:** Border-based spinner with size variants
- **Usage:** Replace all "Loading..." text and inline spinners

### Modal/Dialog System
Restyle existing components (preserve slot-based API):
- **Modal.vue:** Centered, `bg-black/50` overlay, fade animation
- **ConfirmationModal.vue:** Semantic colors for destructive actions
- **DialogModal.vue:** Form-friendly variant

### Icons
- **Package:** `lucide-vue-next`
- **Migration:** Replace all inline Heroicon SVGs with Lucide components
- **Common icons:** Clock, LayoutDashboard, Briefcase, Activity, MessageSquare, Wrench, GitBranch, ChevronDown, Menu, X, Moon, Sun, LogOut, User, Key, Plus, Trash2, Pencil, RefreshCw, etc.

## App Shell Migration

### AppLayout.vue Changes
- Sticky top nav with `backdrop-blur-sm bg-opacity-95`
- Logo: 32x32 rounded-lg primary background with Clock icon
- Desktop nav: Horizontal links with active indicator (bottom border)
- Mobile nav: Sheet component (slide from right)
- User dropdown: Profile photo or initials, name, chevron
- **Preserve:** Team switcher, team settings, profile photo conditional, `delegationEnabled` check

### Navigation Items
Maintain existing routes:
- Dashboard → `/dashboard`
- Jobs → `/agent/jobs`
- Monitor → `/agent/monitor`
- Messenger → `/tools/messenger`
- Tools → `/tools`
- Delegation → `/agent/delegation` (conditional on `delegationEnabled`)

## Page Migration Scope

### Phase 1: Auth + Shell
- Login, Register, ForgotPassword, ResetPassword
- TwoFactorChallenge, ConfirmPassword, VerifyEmail
- Welcome page
- Terms, Privacy pages
- Messenger link pages (valid/invalid token)
- AppLayout.vue shell

### Phase 2: Dashboard + Jobs + Monitor
- Dashboard with KPI cards, window selector
- Jobs Index (filters, pagination, row actions)
- Jobs Create/Edit forms
- Monitor Index (health cards, runs table, event tail, intervention modals)

### Phase 3: Tools + Discovery
- Tools hub cards
- Discovery Index, Create, Settings, SessionSettings
- Discovery Wizard (style-only, preserve all logic)

### Phase 4: Messenger + Backups + Features
- Messenger control plane (schema-driven forms, connectors, sessions)
- Backups settings
- Features settings

### Phase 5: Delegation + Profile
- Delegation Index, Create, Show, TaskDetail
- Delegatee ProfileIndex, ProfileForm
- VerificationApproval
- Profile (info, password, 2FA, sessions, delete account)
- API Tokens management

## High-Risk Surface Handling

### Discovery Wizard (Wizard.vue)
- **Approach:** Style-only migration
- **Preserve:** All 10 phases, 50+ actions, Echo realtime, polling, computed properties
- **Change:** Markup/styles only using new components and tokens
- **Components to use:** Card, Button, Input, Badge, Skeleton, Table

### Monitor Page (Index.vue)
- **Approach:** Style-only migration
- **Preserve:** Health polling, approval/clarification/rate-limit modals, auto-follow
- **Change:** Markup/styles only

## Animations
Essential animations only:
- Modal/dialog: fade-in/out, zoom-in/out
- Skeleton: animate-pulse
- Dropdown/sheet: slide transitions
- Focus rings: transition-shadow

## Verification Strategy

### Manual Checklists
Create per-page verification checklists covering:
- All buttons/actions function correctly
- All filters/toggles work
- All forms submit successfully
- All modals open/close properly
- Responsive behavior (desktop/mobile)
- Dark mode appearance

### E2E Tests (Playwright)
Add before migrating high-risk surfaces:
- **Wizard:** Phase transitions, answer submission, plan generation, build task CRUD
- **Monitor:** Run selection, event polling, approval flow, clarification flow

## File Changes Summary

### New Files
- `resources/css/theme.css` (CSS variables)
- `resources/js/Components/ui/Button.vue`
- `resources/js/Components/ui/Input.vue`
- `resources/js/Components/ui/Select.vue`
- `resources/js/Components/ui/Textarea.vue`
- `resources/js/Components/ui/Badge.vue`
- `resources/js/Components/ui/Card.vue` (+ sub-components)
- `resources/js/Components/ui/Table.vue` (+ sub-components)
- `resources/js/Components/ui/Skeleton.vue`
- `resources/js/Components/ui/Spinner.vue`
- `tests/e2e/wizard.spec.ts`
- `tests/e2e/monitor.spec.ts`

### Modified Files
- `resources/css/app.css` (import theme.css, fonts)
- `tailwind.config.js` (extend theme with CSS variables)
- `package.json` (add lucide-vue-next, playwright)
- `resources/js/Components/Modal.vue` (restyle)
- `resources/js/Components/ConfirmationModal.vue` (restyle)
- `resources/js/Components/DialogModal.vue` (restyle)
- `resources/js/Layouts/AppLayout.vue` (full restyle)
- All page components (update markup/styles)

### Deprecated Files
- `resources/js/Components/PrimaryButton.vue` (replace with Button variant="default")
- `resources/js/Components/SecondaryButton.vue` (replace with Button variant="secondary")
- `resources/js/Components/DangerButton.vue` (replace with Button variant="destructive")
- `resources/js/Components/TextInput.vue` (replace with Input)

## Goals

- Adopt Figma Make design system across entire product while preserving all existing functionality
- Implement CSS variable-based theming with ~50 tokens for light/dark modes
- Build unified Vue component library with variant props (Button, Input, Select, Badge, Card, Table, Skeleton)
- Restyle app shell to Figma direction while keeping all Jetstream features (teams, profile photo, conditional nav)
- Migrate all 30+ pages using style-only approach for complex surfaces (Wizard, Monitor)
- Replace inline Heroicon SVGs with Lucide Vue components
- Standardize loading states across 18 pages with Skeleton and Spinner components
- Update content max-width from 1280px to 1440px across all pages
- Implement essential animations for modals, loading states, and focus rings
- Verify functional parity with manual checklists and E2E tests for high-risk surfaces


## Constraints

- Keep stack as-is: Laravel 12 + Inertia + Vue 3 - no React runtime in production
- Treat /figma code as design reference only, not drop-in implementation
- Preserve existing route structure (/agent/jobs, /agent/monitor, /tools/discovery/{id}, etc.)
- Preserve all API contracts under /agent/api/v1/*
- Preserve all control surfaces including destructive actions (delete/restore/retry/restart/run-now/pause/resume)
- Preserve Jetstream/Fortify auth behavior (profile, 2FA, API tokens, browser sessions, account deletion, teams)
- Preserve feature-flag gated visibility (delegationEnabled for Delegation nav/UI)
- No regressions in accessibility, responsive behavior, keyboard navigation, or validation states
- No route changes without explicit approval
- No API contract changes without explicit approval
- No removal of existing buttons/actions/filters/toggles
- No loss of destructive-action confirmations
- No new dependency that forces React into app runtime


## Acceptance Criteria

- CSS variables defined in theme.css with all ~50 tokens for light and dark themes
- Tailwind config extended to reference CSS variables for colors, fonts, radius
- Google Fonts loading DM Sans (variable 100-1000) and JetBrains Mono (400-600)
- Button.vue supports 6 variants (default, destructive, outline, secondary, ghost, link) and 4 sizes
- Input.vue, Select.vue, Textarea.vue have consistent h-9/h-10 heights with focus rings
- Badge.vue supports 4 variants (default, secondary, destructive, outline)
- Card system includes Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter components
- Table system includes Table, TableHeader, TableBody, TableRow, TableHead, TableCell components
- Skeleton.vue renders with animate-pulse and bg-accent styling
- Spinner.vue renders with size variants (sm, default, lg)
- Modal/ConfirmationModal/DialogModal restyled with fade/zoom animations and semantic colors
- AppLayout.vue has sticky nav, 1440px max-width, Figma styling, all Jetstream features intact
- All inline Heroicon SVGs replaced with lucide-vue-next components
- All hardcoded dark: classes replaced with semantic CSS variable references
- Discovery Wizard preserves all 10 phases, 50+ actions, polling, Echo realtime after migration
- Monitor page preserves health polling, intervention modals, auto-follow after migration
- Playwright E2E tests pass for Wizard phase transitions and Monitor approval flow
- Manual verification checklists completed for each migrated page group
- npm run build succeeds after each migration phase
- No console errors or Vue warnings after migration

