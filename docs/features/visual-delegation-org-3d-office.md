# Visual Delegation, Org Layer Builder & 3D Office

Three visual features for building and viewing delegation graphs, org reporting structure, and a 3D agent office.

## Where to find them

- **Delegation Graph Builder** — Delegation → "Visual builder" (or `/agent/delegation/graphs/builder`). Create and edit delegation DAGs by dragging task nodes and connecting dependencies. Requires delegation UI enabled.
- **Org Layer Builder** — Agents (Org) → "Org Layer Builder" card (or `/agent/org/builder`). Drag org agents and connect reporting edges (subordinate → manager). Requires org UI enabled.
- **3D Office** — Sidebar → "3D Office" (or `/agent/office`). Three.js view of org agents as avatars with reporting lines and a simple status strip. Optional; can be disabled.

## Enabling

- **Delegation UI:** `delegation.ui_enabled` (config/database) or feature flag. See delegation docs.
- **Org UI:** `agent.org.enabled` (config) or `ORG_LAYER_ENABLED` env. See org docs.
- **3D Office:** `agent.office_3d_enabled` in `config/agent.php`, default `true`. Set `AGENT_OFFICE_3D_ENABLED=false` to disable.

## 3D Office notes

- Best-effort: requires WebGL. If unavailable, the page shows an error message instead of the canvas.
- Uses org agents (with reporting) from `GET /agent/api/v1/org/agents?with_reporting=1`. No delegation or ritual run scope in the initial release.
- Agent list and status strip appear below the canvas; future work can add polling or WebSocket for live task/event state.

## Tech

- **Builders:** Vue Flow (`@vue-flow/core`), shared `GraphCanvas` component.
- **3D Office:** Three.js, `useThreeScene` composable, OrbitControls, simple avatars and connection lines.

## E2E (Playwright)

- **Auth:** Run `E2E_SEED_USER=1 php artisan db:seed --class=UserSeeder` once to create (or update) the E2E user (`TEST_USER_EMAIL` / `TEST_USER_PASSWORD`, default `test@example.com` / `password`) with onboarding completed.
- **Visual features:** `tests/e2e/visual-builders-and-office.spec.ts` covers delegation graph builder, org layer builder, and 3D office. Tests skip when the relevant feature is disabled (403/404).
