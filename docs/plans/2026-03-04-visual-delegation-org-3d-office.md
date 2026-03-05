# Visual Delegation Graph, Org Layer Builder & 3D Agent Office — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add three visual features: (1) a drag/drop/connect visual builder for delegation DAGs, (2) a drag/drop/connect visual builder for the org layer (agents + reporting edges), and (3) a stylized 3D (Three.js) “virtual office” that visualizes agents running and working together with thinking popups, communication, and animation.

**Architecture:** The two 2D builders use a shared node-canvas approach (Vue Flow) and existing Laravel APIs (delegation graphs/tasks/dependencies, org agents/reporting edges). The 3D office is a separate Vue page/component that consumes live or replayed run state (delegation events, org ritual runs) and renders a Three.js scene with agent avatars, thought bubbles, and connection arcs. All three are feature-gated and live under the existing Agent UI (Vue 3 + Inertia).

**Tech Stack:** Vue 3, Inertia.js, Tailwind CSS, Vue Flow (@vue-flow/core + @vue-flow/background + @vue-flow/controls), Three.js (three), Vite. Backend: existing Laravel API (DelegationGraphController, OrgAgentController, org reporting edges, delegation events).

---

## Scope

- **In:** Visual delegation graph builder (create/edit DAG by drag/drop/connect); visual org layer builder (agents as nodes, reporting edges as connections); 3D virtual office view (Three.js) showing agents, thinking popups, communication, movement/animations; feature flags and routes; tests for critical paths.
- **Out:** Changes to backend delegation/org domain logic (use existing APIs); full real-time sync in 3D (can use polling or replay first); mobile-specific 3D optimizations.

---

## Phase 0: Shared Foundation (Graph Canvas & Dependencies)

### Task 0.1: Add frontend dependencies

**Files:**
- Modify: `package.json`

**Steps:**
1. Add Vue Flow and Three.js: `@vue-flow/core`, `@vue-flow/background`, `@vue-flow/controls`, `three`. Use current stable versions (e.g. `^1.x` for vue-flow, `^0.170` for three).
2. Run `npm install`.
3. Verify build: `npm run build` (no new errors).

**Commit:** `chore: add Vue Flow and Three.js for visual builders and 3D office`

---

### Task 0.2: Create shared graph canvas wrapper (Vue Flow)

**Files:**
- Create: `resources/js/Components/GraphCanvas/GraphCanvas.vue`
- Create: `resources/js/Components/GraphCanvas/types.ts` (optional, for shared node/edge typings)

**Steps:**
1. Implement a minimal Vue Flow wrapper: `<VueFlow>` with `<Background>` and `<Controls>`, accept `nodes` and `edges` as props, emit `node-click`, `edge-click`, `connect`, `nodes-change`, `edges-change`. Support readonly vs edit mode (connectable / draggable / deletable).
2. Use Tailwind for container; ensure no layout clash with existing app shell.
3. Export the component from a barrel if desired (`resources/js/Components/GraphCanvas/index.ts`).

**Commit:** `feat(ui): add shared GraphCanvas Vue Flow wrapper`

---

## Phase 1: Visual Delegation Graph Builder

### Task 1.1: Delegation graph builder page and route

**Files:**
- Create: `resources/js/Pages/Agent/Delegation/GraphBuilder.vue`
- Modify: `routes/web.php` (or existing Agent delegation route group)

**Steps:**
1. Add a route for the visual graph builder (e.g. `/agent/delegation/graphs/builder` or `/agent/delegation/graphs/new/builder`). Guard with delegation feature gate on backend if not already; ensure frontend checks `delegation.ui_enabled` (or equivalent) before showing nav.
2. Create `GraphBuilder.vue`: Inertia page that hosts the canvas; toolbar with “Add task”, “Save draft”, “Validate”, “Start”; load delegatee profiles and capabilities for task config (use existing API).
3. Wire “Add task” to append a new node to local state (node type: task; default name “Task N”, contract placeholder). Node position from canvas or grid.

**Commit:** `feat(delegation): add visual graph builder page and route`

---

### Task 1.2: Delegation nodes and edges on canvas

**Files:**
- Create: `resources/js/Components/Delegation/DelegationTaskNode.vue` (custom node for Vue Flow)
- Modify: `resources/js/Pages/Agent/Delegation/GraphBuilder.vue`

**Steps:**
1. Register a custom node type `delegationTask` that renders `DelegationTaskNode.vue`: task name, status (when present), capability badge; compact card layout.
2. In GraphBuilder: map local nodes/edges to Vue Flow format; on `connect` create an edge (source → target = depends_on → task). Enforce DAG: when adding an edge, run a simple cycle check (e.g. BFS from target; if source is reachable, reject). Show validation error in UI.
3. Support delete node and delete edge from canvas (only in draft). On “Save draft”, persist graph via `POST /agent/api/v1/delegation/graphs` or `PUT .../graphs/{id}` with payload: `{ name, description, tasks: [...], dependencies: [{ task_id, depends_on_task_id }] }`. Use existing API shape; if API expects different shape (e.g. linear chain or DAG JSON), add a small adapter in the page or a composable.

**Commit:** `feat(delegation): delegation task nodes, edges, DAG validation, save draft`

---

### Task 1.3: Task config panel and delegatee assignment

**Files:**
- Create: `resources/js/Components/Delegation/TaskConfigPanel.vue`
- Modify: `resources/js/Pages/Agent/Delegation/GraphBuilder.vue`

**Steps:**
1. On task node click, open a side panel (or modal) `TaskConfigPanel.vue`: name, contract fields (required_capability, criticality, authority_scope summary, verification strategy), optional delegatee assignment (dropdown of delegatee profiles).
2. Load delegatee profiles from `GET /agent/api/v1/delegation/delegatees` (or existing delegatee list endpoint); load capabilities from config or `GET .../capabilities` if available. Save panel state back to the selected node’s data.
3. When saving the graph, include full contract_json and assigned_delegatee_profile_id per task so backend can persist them.

**Commit:** `feat(delegation): task config panel and delegatee assignment in graph builder`

---

### Task 1.4: Open existing graph in builder (draft only)

**Files:**
- Modify: `resources/js/Pages/Agent/Delegation/GraphBuilder.vue`
- Modify: `routes/web.php` (optional: route param for graph id)

**Steps:**
1. Support opening an existing graph in the builder: e.g. `/agent/delegation/graphs/{id}/builder`. Only allow when graph status is `draft`. Load graph with tasks and dependencies via `GET /agent/api/v1/delegation/graphs/{id}`.
2. Map API response to Vue Flow nodes/edges; assign default positions if not stored (e.g. dagre layout or simple row/column). When saving, call `PUT .../graphs/{id}` with updated tasks and dependencies.
3. Add a “Back to graph” link to the graph show/detail page.

**Commit:** `feat(delegation): open existing draft graph in visual builder`

---

### Task 1.5: Tests for delegation graph builder

**Files:**
- Create: `tests/Feature/Delegation/DelegationGraphBuilderAccessTest.php` (or Pest equivalent)
- Optional: `tests/Feature/Delegation/DelegationGraphBuilderSaveTest.php`

**Steps:**
1. Test that builder route returns 200 when delegation is enabled and user is authenticated; 404 when delegation is disabled.
2. Test that saving a draft from the builder (via API) creates or updates a graph and task dependencies (integration test hitting API with valid payload, or E2E with Playwright).
3. Run test suite: `php artisan test --filter=Delegation`

**Commit:** `test(delegation): add tests for graph builder access and save`

---

## Phase 2: Visual Org Layer Builder

### Task 2.1: Org layer builder page and route

**Files:**
- Create: `resources/js/Pages/Agent/Org/OrgLayerBuilder.vue`
- Modify: `routes/web.php` (org route group)

**Steps:**
1. Add route for org layer builder (e.g. `/agent/org/builder`). Guard with org feature gate (`agent.org.enabled`). Create Inertia page that hosts the canvas and a toolbar (“Add agent”, “Save”).
2. Load existing org agents and reporting edges: `GET /agent/api/v1/org/agents`, and a dedicated endpoint or inclusion for reporting edges (e.g. `GET .../org/reporting-edges` or edges embedded in agents). If no reporting-edges endpoint exists, add a minimal one in `routes/api.php` and `OrgReportingEdgeController@index` (or extend OrgAgentController to return edges).
3. Map agents to Vue Flow nodes (one node per org agent), edges to Vue Flow edges (subordinate_agent_id → manager_agent_id). Default positions: simple layout (e.g. by hierarchy level or grid).

**Commit:** `feat(org): add org layer builder page and route`

---

### Task 2.2: Org agent nodes and reporting edges

**Files:**
- Create: `resources/js/Components/Org/OrgAgentNode.vue`
- Modify: `resources/js/Pages/Agent/Org/OrgLayerBuilder.vue`

**Steps:**
1. Register custom node type `orgAgent` rendering `OrgAgentNode.vue`: agent name, role/summary if available, avatar placeholder. Allow drag and drop.
2. On “Add agent”: create a new node with temporary client-side id; on “Save” create org agent via `POST /agent/api/v1/org/agents` and then create reporting edges via appropriate API (if edges are created separately, add `POST .../org/reporting-edges` or equivalent). When drawing a new edge (connect), subordinate = source node, manager = target node. Enforce one manager per agent (at most one incoming “reporting” edge per node); show validation error otherwise.
3. Support delete node (remove agent and its reporting edges) and delete edge (remove reporting relationship). Persist on Save.

**Commit:** `feat(org): org agent nodes, reporting edges, one-manager rule, save`

---

### Task 2.3: Org agent config and councils/rituals link

**Files:**
- Create: `resources/js/Components/Org/OrgAgentConfigPanel.vue`
- Modify: `resources/js/Pages/Agent/Org/OrgLayerBuilder.vue`

**Steps:**
1. On agent node click, open side panel `OrgAgentConfigPanel.vue`: name, description, link to delegatee profile if applicable, parent (manager) selector. Optionally list councils/rituals this agent belongs to (read-only links).
2. Load list of agents for “parent” dropdown (existing agents). Save changes to node data and persist on Save (PUT agent, update reporting edge if parent changed).
3. Add nav entry to Org Layer builder from Org index or Agents index page.

**Commit:** `feat(org): agent config panel and link to councils/rituals`

---

### Task 2.4: Tests for org layer builder

**Files:**
- Create: `tests/Feature/Org/OrgLayerBuilderAccessTest.php`

**Steps:**
1. Test builder route 200 when org enabled, 404 when disabled. Test that saving agents and reporting edges (via API) succeeds with valid payload.
2. Run: `php artisan test --filter=Org`

**Commit:** `test(org): add tests for org layer builder access and save`

---

## Phase 3: 3D Virtual Office (Three.js)

### Task 3.1: Three.js scene and Vue integration

**Files:**
- Create: `resources/js/Pages/Agent/Office/Office3D.vue`
- Create: `resources/js/Composables/useThreeScene.ts` (or `useOfficeScene.ts`) to init Three.js scene, camera, renderer, resize, dispose
- Modify: `routes/web.php` (e.g. `/agent/office` or `/agent/delegation/office`, `/agent/org/office`)

**Steps:**
1. Add route for 3D office view; page accepts optional query params (e.g. `graph_id`, `ritual_run_id`) to scope data.
2. In `Office3D.vue`, use a container ref and onMounted create Scene, PerspectiveCamera, WebGLRenderer; add lights and a simple floor/room. Use `useThreeScene` to encapsulate init/animation loop/resize. Start animation loop with requestAnimationFrame.
3. Ensure no memory leaks: dispose renderer and cancel animation frame on unmount.

**Commit:** `feat(office): add Three.js 3D office page and scene composable`

---

### Task 3.2: Agent avatars and layout

**Files:**
- Create: `resources/js/Support/Office/AgentAvatar.ts` (or .vue if using Troika/React Three Fiber; here assume vanilla Three.js for Vue)
- Modify: `resources/js/Pages/Agent/Office/Office3D.vue` and composable

**Steps:**
1. Define “agent” entities: each has a position (Vector3), a simple 3D representation (e.g. box or low-poly character, or stylized capsule). Create one mesh/group per agent; add to scene. Load agent list from props or API (delegation graph run or org ritual run).
2. Layout agents in a virtual office: e.g. desks or standing positions in a grid or around a table. Store positions in a small layout module or config (x, z per agent id).
3. Style with distinct colors or labels (agent name as sprite or DOM overlay) so each agent is identifiable.

**Commit:** `feat(office): agent avatars and office layout`

---

### Task 3.3: Thinking popups and communication

**Files:**
- Create: `resources/js/Support/Office/ThinkingPopup.ts` (or similar)
- Modify: `resources/js/Pages/Agent/Office/Office3D.vue`

**Steps:**
1. “Thinking” popups: for each agent that has an active task or recent event, show a small thought bubble (CSS overlay or Three.js sprite/CSS2DRenderer) above the avatar with truncated text (e.g. “Running: task name” or “Thinking…”). Data can come from delegation task status or org ritual run status (polling or WebSocket later).
2. Communication: when task A delegates to task B (or agent A reports to B), draw a visual connection (line or arc between two avatars). Use Line2 or simple LineGeometry; animate dash or glow optionally. Data source: delegation dependencies or org reporting edges plus “currently active” run state.
3. Optionally drive state from `GET /agent/api/v1/delegation/graphs/{id}/events` or org ritual run status; poll every 5–10s for MVP.

**Commit:** `feat(office): thinking popups and communication lines in 3D office`

---

### Task 3.4: Movement and animation

**Files:**
- Modify: `resources/js/Support/Office/AgentAvatar.ts` and scene logic
- Modify: `resources/js/Pages/Agent/Office/Office3D.vue`

**Steps:**
1. Animate agents: idle (e.g. subtle bounce or rotate), “working” (e.g. different animation or particle effect), “communicating” (face each other or highlight line). State derived from run status (pending, running, succeeded).
2. When a new event arrives (e.g. task started), briefly animate the corresponding agent (e.g. pulse or move toward delegatee). Keep animations short and loopable to avoid performance issues.
3. Add simple camera controls (OrbitControls or similar) so user can rotate/zoom the office. Ensure controls are disposed on unmount.

**Commit:** `feat(office): agent movement and animation, camera controls`

---

### Task 3.5: 3D office tests and cleanup

**Files:**
- Create: `tests/Feature/Office/Office3DAccessTest.php` (or E2E: load page, no console errors)
- Modify: `resources/js/Pages/Agent/Office/Office3D.vue` (error boundary, loading state)

**Steps:**
1. Test office route returns 200 when feature is enabled; optional E2E that page loads and WebGL context is created (no hard assertion on 3D content).
2. Add loading state and a minimal error boundary (e.g. “3D view unavailable”) if WebGL fails. Document in plan or README that 3D office is best-effort and may be disabled on unsupported devices.
3. Run full test suite and fix regressions.

**Commit:** `test(office): add 3D office access test and error handling`

---

## Phase 4: Integration and Polish

### Task 4.1: Feature flags and nav

**Files:**
- Modify: `config/delegation.php` and `config/agent.php` (or existing org config)
- Modify: `resources/js/Pages/Agent/*` (layout or nav components that show Delegation / Org links)
- Modify: `resources/views/app.blade.php` or shared layout if nav is server-rendered

**Steps:**
1. Ensure `delegation.ui_enabled` and `agent.org.enabled` (or equivalent) are respected; add `agent.office_3d_enabled` if you want a separate kill switch for 3D office.
2. Add “Graph builder” link in Delegation section; “Org builder” in Org section; “3D Office” link in a sensible place (Delegation or Org or both). Hide links when feature is disabled.
3. Smoke-test: enable flags, open each builder and office page, then disable and confirm 404 or hidden nav.

**Commit:** `chore: wire feature flags and nav for builders and 3D office`

---

### Task 4.2: Documentation and open questions

**Files:**
- Create or update: `docs/features/visual-delegation-org-3d-office.md` (short user/developer note)
- Update: `AGENTS.md` or README if new scripts or env vars are added

**Steps:**
1. Document the three features: where to find them, how to enable (env/config), and that 3D office is optional and may require a capable GPU.
2. List open questions (see below) in the doc or at the end of this plan.

**Commit:** `docs: add visual builders and 3D office feature notes`

---

## Open questions

- **Delegation graph API shape:** Does `PUT /graphs/{id}` accept a full DAG payload (tasks + dependencies) or only partial updates? If partial, builder may need to compute diff and send task creates/updates/deletes and dependency creates/deletes separately.
- **Org reporting edges API:** Is there a dedicated REST resource for reporting edges (list/create/update/delete), or are edges managed only through agent update? If missing, add `GET/POST/DELETE .../org/reporting-edges` (or nested under agents) in this plan’s scope.
- **3D data source:** Prefer live delegation/org events over WebSocket vs polling vs “replay from run id”? MVP can use polling; WebSocket can be a follow-up for real-time popups and animations.
- **3D office scope:** Should the office show one delegation graph run, one org ritual run, or a “global” view of all active work? Starting with one run (graph_id or ritual_run_id) keeps scope smaller.

---

## Summary checklist

- [ ] **0.1** Add Vue Flow and Three.js; `npm run build` passes
- [ ] **0.2** Shared `GraphCanvas.vue` Vue Flow wrapper
- [ ] **1.1** Delegation graph builder page and route
- [ ] **1.2** Delegation task nodes, edges, DAG validation, save draft
- [ ] **1.3** Task config panel and delegatee assignment
- [ ] **1.4** Open existing draft graph in builder
- [ ] **1.5** Delegation graph builder tests
- [ ] **2.1** Org layer builder page and route
- [ ] **2.2** Org agent nodes, reporting edges, save
- [ ] **2.3** Org agent config panel
- [ ] **2.4** Org layer builder tests
- [ ] **3.1** Three.js office page and scene
- [ ] **3.2** Agent avatars and layout
- [ ] **3.3** Thinking popups and communication lines
- [ ] **3.4** Movement, animation, camera controls
- [ ] **3.5** 3D office tests and error handling
- [ ] **4.1** Feature flags and nav
- [ ] **4.2** Documentation

Plan complete. Two execution options:

1. **Subagent-driven (this session)** — Implement task-by-task with review between tasks.
2. **Parallel session** — Open a new session in the same repo and run with **executing-plans** for batch execution with checkpoints.

Which approach do you prefer?
