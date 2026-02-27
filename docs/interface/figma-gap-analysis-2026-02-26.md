# Figma Make Gap Analysis (2026-02-26)

## Scope Reviewed
- Published Figma Make site: `https://pause-kindle-31517753.figma.site/dashboard`
- Figma React codebase: `/Users/garethdaine/Code/agent-figma`
- Current app screens/evidence: `/Users/garethdaine/Code/agent/docs/interface/README.md` and screenshots `01..40`

## What Exists In Published Figma Design
Published Figma currently includes these implemented routes/screens:
- `/dashboard`
- `/jobs`, `/jobs/create`, `/jobs/:id/edit`
- `/monitor`
- `/messenger`
- `/tools`, `/tools/discovery`, `/tools/discovery/create`, `/tools/discovery/wizard`, `/tools/backups`, `/tools/features`
- `/delegation`
- `/profile`
- `/login`, `/register`, `/forgot-password`, `/reset-password`, `/two-factor`

Evidence screenshots captured from published Figma:
- `/Users/garethdaine/Code/agent/docs/interface/figma-published-*.png`

## High-Priority Missing Screens (Present In App, Missing In Figma)
1. Welcome/Landing page (`/`) is missing.
2. Messenger account-link screens are missing:
- valid token state (`/messenger/link/{token}` success path)
- invalid/expired token state
3. Discovery settings screens are missing:
- global settings (`/tools/discovery/settings`)
- per-session settings (`/tools/discovery/{id}/settings`)
4. Delegation flow is incomplete. Missing screens:
- create graph (`/agent/delegation/create`)
- graph detail (`/agent/delegation/{id}`)
- task detail (`/agent/delegation/{graphId}/tasks/{taskId}`)
- verification approval (`/agent/delegation/{graphId}/tasks/{taskId}/approve`)
- delegatee profiles index/create/edit (`/agent/delegatee-profiles*`)
5. API tokens screen is missing (`/api-tokens` behavior represented in current app inventory).
6. Legal/static pages linked from auth are missing:
- Terms of Service
- Privacy Policy

## Route/IA Mismatch Gaps (Design-to-App Parity Risk)
Current Figma paths differ from app routes in several places:
- Jobs: Figma `/jobs*` vs app `/agent/jobs*`
- Monitor: Figma `/monitor` vs app `/agent/monitor`
- Delegation: Figma `/delegation` vs app `/agent/delegation*`
- Profile: Figma `/profile` vs app `/user/profile`
- Discovery create: Figma `/tools/discovery/create` vs app `/tools/discovery/new`
- Discovery wizard: Figma `/tools/discovery/wizard` vs app `/tools/discovery/{id}`
- Backups: Figma `/tools/backups` vs app `/tools/backups/settings`
- Features: Figma `/tools/features` vs app `/tools/features/settings`
- 2FA challenge: Figma `/two-factor` vs app `/two-factor-challenge`
- Reset password: Figma `/reset-password` vs app `/reset-password/{token}`

## Partial Feature Gaps On Existing Screens
1. Jobs index:
- Current app has source + deleted filters and restore flows; Figma omits deleted filter/restore state.
- Action handling states (busy/confirmation/error/success) are not represented.
2. Job form:
- Current app has deeper command/template policy guidance and validation states.
- Figma is close structurally but lacks full validation/error/success coverage.
3. Monitor:
- Current app includes intervention panels/states (approval needed, rate limit, clarification).
- Figma only shows baseline health + log tail.
4. Discovery sessions index:
- Current app includes deleted-only filtering and restore lifecycle.
- Figma design does not fully represent deleted/restore lifecycle states.
5. Messenger control plane:
- Current app is schema-driven (provider-specific credentials, connection mode, confirm-required, verbosity, retest/disconnect, session detail/actions).
- Figma currently uses a simplified connect dialog and lighter detail coverage.
6. Profile/security:
- Missing explicit API token management entry/screen parity.
- Lacks complete account-management state map (e.g., confirmation flows).
7. Auth:
- Register lacks Terms/Privacy acceptance treatment parity.
- Reset flow does not model token-bound route state.

## Control-State Coverage Gaps
Compared with app control inventory (`34..40`), Figma needs explicit frames for:
- desktop nav default
- user menu open
- mobile nav open
- profile delete modal open
- discovery table actions open
- jobs filters + table control states
- messenger connectors/sessions/action detail states

## Quality/Consistency Gaps
1. Screen-level loading/empty/error/success variants are not consistently present.
2. Some auth inputs in published version emit autocomplete/accessibility warnings.
3. Page title/metadata still reflects temporary title (`Review app screenshots`) instead of production naming.

## Recommended Priority Order
1. Fill missing screens (welcome, link-account states, discovery settings, full delegation suite, api tokens, legal pages).
2. Align route/IA naming to app paths (or define explicit aliasing spec in handoff).
3. Add missing lifecycle states (loading/empty/error/success + destructive confirmations).
4. Deepen Messenger + Monitor + Discovery feature parity.
5. Finalize design-system tokens/components for React shadcn/ui with shadcn-vue parity.
