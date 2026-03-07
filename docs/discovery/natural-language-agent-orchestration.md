# Requirements Discovery Summary

Session: 1

## NL Org Layer Builder — Feature Requirements Summary

### Overview
Add a natural language interface for building agent org layers. Users can describe org structures in plain English via a chat-style sidebar panel that coexists with the existing `OrgLayerBuilder.vue` drag-and-drop graph canvas. The system parses NL input into structured org entity payloads, renders a visual diff preview on the canvas, and applies changes upon user confirmation.

### Existing Infrastructure (Discovery Findings)

**Org Domain (fully built):**
- **Models**: `OrgAgentProfile`, `OrgReportingEdge`, `OrgRitualTemplate`, `OrgCouncilTemplate`, `OrgCostLedger`, `OrgEscalation` (runtime event model — pending/approved/rejected, **not** a configuration entity)
- **Services**: `OrgAgentProfileService` (full CRUD), plus controllers for rituals, councils, costs, escalations
- **API**: Complete REST surface in `api.php` via `OrgAgentController` and related controllers, using flat `/api/org/*` prefix pattern
- **UI**: `OrgLayerBuilder.vue` — visual drag-and-drop graph canvas for composing agent hierarchies
- **Migration**: `2026_03_06_100000_add_soul_json_to_delegatee_and_org_profiles.php` exists but `OrgAgentProfile` model does not yet include `soul_json` in its `$fillable` array — must be fixed as a prerequisite
- **Note**: `OrgEscalation` is a runtime event model (tracks escalation state: pending/approved/rejected), **not** a configuration entity. No escalation path configuration model currently exists. Future escalation path configuration will be embedded as a JSON column on `OrgAgentProfile` (each agent defines its own escalation config inline), but this is deferred from v1.

**NL Schedule Pipeline (pattern to adapt, not mirror):**
- `NlScheduleParserService.php` — rule-based parser → LLM fallback, confidence scoring
- `NlParseAttempt.php` — idempotent parse attempt tracking model
- `NlParseAttemptPolicy.php` — authorization policy for parse attempts
- `RuleBasedScheduleParser.php` — regex/rule-based first pass
- `NlScheduleInput.vue` — Vue component for NL text input with inline feedback
- `ParseResult` DTO — structured output with confidence, parsed data, error annotations
- **Key difference**: Org builder will be **LLM-first** (no rule-based layer) since org structures are too semantically varied for regex rules

### Architecture Decisions

**Parsing Strategy**: LLM-first. No rule-based parser layer. Send NL input directly to LLM with a structured output schema prompt. Use the existing Guzzle-based LLM client (no `laravel/ai` package available). Reuse the `NlParseAttempt` idempotency model pattern and confidence scoring from NlSchedule.

**LLM Model Configuration**: Share the agent execution model from `config/agent.php` (same model and temperature). No separate `nl_org.model` config key needed — the parser uses the existing agent execution model settings directly.

**Conversational Context**: Include up to the last N chat turns (user input + parse results) as LLM context for follow-up commands, enabling pronoun and back-reference resolution (e.g., "make that agent senior level"). Provide a "Clear Context" button in the chat sidebar to reset conversation history and start fresh. The `NlOrgPromptBuilder` appends chat history after the current org state in the prompt. Chat history is stored in-memory on the frontend (session-scoped, not persisted to database).

**Parser Output Schema**: Directly output arrays of payloads matching existing Org API contracts:
- `OrgAgentProfile` create/update payloads (including `soul_json`)
- `OrgReportingEdge` payloads (parent/child agent references)
- `OrgRitualTemplate` payloads (with embedded `phase_graph` — rituals are modeled as templates with phase graphs, **not** separate rule entities)
- `OrgCouncilTemplate` payloads (decision-making group configurations)
- `OrgCostLedger` payloads (cost/budget constraints per agent)
- Each entity tagged with a diff operation: `add`, `update`, or `remove`

**Idempotency / Merge Model**: Parse NL into a diff against the current org layer state. The parser receives the current org graph as context and outputs a changeset (additions, modifications, removals). Never wholesale-replace the org layer — always merge.

**Entity Resolution**: Users can reference existing agent profiles by name or role (e.g., "use the QA agent template"). The parser must resolve references against `OrgAgentProfileService` results before generating payloads. Unresolved references should be flagged as ambiguous.

**Missing Participant Handling**: When a user describes a ritual or council without specifying which agents participate (e.g., "add a daily standup ritual"), the parser must **not** infer participants or create the entity with an empty participant list. Instead, it flags the entity as ambiguous and returns an inline annotation asking the user to specify which agents participate. The changeset for that entity is withheld until the user provides the missing references in a follow-up message. Other fully-specified entities in the same prompt are still included in the changeset.

**Org Layer Size Handling**: No cap on org layer size for holistic NL prompts. The LLM context window is the natural limit. If the serialized org state + NL prompt + chat history + schema exceeds the context window, `NlOrgParserService` returns a structured error suggesting the user switch to incremental mode. `NlOrgPromptBuilder` calculates approximate token usage before sending and fails fast if over budget.

**Parse Attempt Retention**: `NlOrgParseAttempt` records persist indefinitely — no automatic cleanup or expiration. All parse attempts (confirmed and unconfirmed) are retained for full audit trail.

**Future Escalation Path Configuration**: When escalation path configuration is added in a future version, it will be embedded as a JSON column on `OrgAgentProfile` — each agent defines its own escalation path configuration inline. No separate `OrgEscalationPathConfig` model will be needed. This is deferred from v1 but informs schema design: the `OrgAgentProfile` payload schema should be designed to accommodate future JSON columns without breaking changes.

**Authorization**: `NlOrgParseAttemptPolicy` governs access to parse attempts, following the existing `NlParseAttemptPolicy` pattern. `NlOrgController` applies policy checks and org middleware consistent with existing `OrgAgentController` authorization. Parse attempts are scoped to `user_id` (the authenticated user who initiated the parse).

### Input Modes

**Holistic Mode**: User describes an entire org layer in one prompt (e.g., "Create a 3-tier team with a lead, two developers, and a QA agent. The QA agent reports to the lead and runs a daily review ritual."). System generates the full graph diff.

**Incremental Mode**: User issues single commands against an existing org layer (e.g., "Add a security reviewer reporting to the lead" or "Remove the cost ledger from the QA agent"). System generates a targeted diff. Conversational context from prior turns enables pronoun/back-reference resolution without repeating entity names.

Both modes use the same parser pipeline — the LLM prompt includes the current org state and chat history as context, making incremental commands naturally produce partial diffs.

### UI / UX Design

**Input Surface**: Chat-style sidebar panel (`NlOrgChat.vue`) rendered alongside `OrgLayerBuilder.vue`. The sidebar maintains a scrollable conversation history of NL inputs and system responses within the session. A "Clear Context" button resets the conversation history for a fresh start.

**Confirmation Flow**: Every NL parse result renders a visual preview diff on the graph canvas before persistence. Added nodes/edges are highlighted (e.g., green), removals are struck-through/red, modifications are amber. User must click "Apply" to persist or "Discard" to cancel.

**Error Feedback**: Inline annotations within the chat panel. Ambiguous portions of the user's input are highlighted with suggested corrections or clarifications. Partial parses are rendered — understood entities appear on the preview diff, and unparsed portions are annotated with reasons and example rephrasing. Rituals or councils missing agent participants are specifically annotated with a prompt to specify participants. Context window overflow returns a user-friendly error suggesting incremental mode.

### Backend Components to Build

1. **`NlOrgParserService`** (`app/Support/NlOrg/NlOrgParserService.php`) — orchestrates LLM call, entity resolution, diff generation. Accepts NL string + current org state + chat history, returns `NlOrgParseResult`. Validates that rituals and councils have explicit participant references; withholds entities with missing participants from the changeset and emits annotations. Detects context window overflow and returns structured error suggesting incremental mode.
2. **`NlOrgParseResult`** DTO (`app/Support/NlOrg/NlOrgParseResult.php`) — contains: `confidence` (float 0-1), `changeset` (array of entity payloads with diff ops), `annotations` (array of inline error/ambiguity annotations with char offsets, including missing-participant flags), `resolved_references` (map of name→profile ID), `error` (optional structured error for context overflow or other failures).
3. **`NlOrgParseAttempt`** model (`app/Models/NlOrgParseAttempt.php`) — tracks parse attempts for idempotency and auditing. Columns: `id`, `user_id` (foreign key to users, scopes ownership), `raw_input`, `parsed_result` (JSON), `confidence`, `applied_at`, `created_at`, `updated_at`. Records persist indefinitely for audit trail — no automatic cleanup.
4. **`NlOrgParseAttemptPolicy`** (`app/Policies/NlOrgParseAttemptPolicy.php`) — authorization policy following `NlParseAttemptPolicy` pattern. Ensures users can only view/apply their own parse attempts.
5. **`NlOrgPromptBuilder`** (`app/Support/NlOrg/NlOrgPromptBuilder.php`) — constructs the LLM prompt including current org state serialization, output JSON schema definition, entity resolution context, few-shot examples, and appended chat history for conversational context. Describes `OrgRitualTemplate` with embedded `phase_graph` structure (not separate rule entities). Instructs the LLM to flag rituals/councils without explicit participant references as ambiguous rather than inferring. Calculates approximate token usage before sending and fails fast if the combined prompt exceeds the context window.
6. **`NlOrgDiffApplier`** (`app/Support/NlOrg/NlOrgDiffApplier.php`) — takes a confirmed `NlOrgParseResult` changeset and calls existing Org services (`OrgAgentProfileService`, etc.) to persist changes transactionally.
7. **`NlOrgController`** (`app/Http/Controllers/Api/NlOrgController.php`) — API endpoints using flat `/api/org/*` prefix: `POST /api/org/nl-parse` (parse NL, return preview; accepts optional `chat_history` array in request body), `POST /api/org/nl-apply` (apply confirmed changeset). Controller applies `NlOrgParseAttemptPolicy` checks and org middleware consistent with existing org controllers.
8. **Migration** for `nl_org_parse_attempts` table (columns: `id`, `user_id`, `raw_input`, `parsed_result`, `confidence`, `applied_at`, `created_at`, `updated_at`).
9. **Fix**: Add `soul_json` to `OrgAgentProfile::$fillable`.

### Frontend Components to Build

1. **`NlOrgChat.vue`** (`resources/js/Components/Org/NlOrgChat.vue`) — chat sidebar with text input, scrollable conversation history, inline error annotations with highlights (including missing-participant warnings for rituals/councils and context overflow errors), suggested corrections, and a "Clear Context" button to reset conversation history.
2. **`OrgDiffPreview`** integration into `OrgLayerBuilder.vue` — overlay layer on the graph canvas showing added (green), removed (red), and modified (amber) nodes/edges with Apply/Discard buttons.
3. **API composable** (`resources/js/Composables/useNlOrgParser.ts`) — wraps parse and apply API calls, manages loading/error state, holds pending changeset for preview, maintains in-memory chat history array (session-scoped), and sends chat history with each parse request.

### Entity Coverage (v1)
5 org entity types are in scope for NL parsing:
- `OrgAgentProfile` (agents with soul_json, role, capabilities)
- `OrgReportingEdge` (parent-child reporting relationships)
- `OrgRitualTemplate` (recurring rituals with embedded `phase_graph` — no separate rule model; **requires explicit agent participant references**)
- `OrgCouncilTemplate` (decision-making group configurations; **requires explicit agent participant references**)
- `OrgCostLedger` (cost/budget constraints per agent)

**Explicitly excluded from v1:**
- `OrgEscalation` — runtime event model, not configuration. Future escalation path configuration will be embedded as a JSON column on `OrgAgentProfile` (deferred).

### Configuration
- LLM model and temperature: shared from existing `config/agent.php` agent execution settings (no separate nl_org.model key)
- Add `config/agent.php` key `nl_org.max_input_length` (default: 2000 chars)
- Add `config/agent.php` key `nl_org.confidence_threshold` (default: 0.7) — below this, all annotations are shown as warnings
- Add `config/agent.php` key `nl_org.max_chat_history_turns` (default: 10) — maximum number of prior turns included in LLM context

## Goals

- Implement an LLM-first NL parser (NlOrgParserService) that accepts natural language input plus current org state plus chat history and outputs structured changesets matching existing Org API contracts (OrgAgentProfile, OrgReportingEdge, OrgRitualTemplate, OrgCouncilTemplate, OrgCostLedger)
- Build a chat-style sidebar panel (NlOrgChat.vue) coexisting with OrgLayerBuilder.vue that supports both holistic org creation prompts and incremental editing commands with conversational context
- Implement merge-based diff semantics: parser outputs add/update/remove operations against the current org layer, never wholesale replacement
- Enable entity resolution so NL input can reference existing agent profiles by name or role (e.g., 'use the QA agent template') alongside creating new agents
- Render visual preview diffs on the OrgLayerBuilder graph canvas (green=add, amber=modify, red=remove) with explicit Apply/Discard confirmation before any persistence
- Surface inline error annotations in the chat panel highlighting ambiguous NL portions with suggested corrections, including missing-participant warnings for rituals/councils and context overflow errors
- Create NlOrgParseAttempt model (scoped to user_id, persisted indefinitely for audit trail) for idempotent parse tracking
- Create NlOrgParseAttemptPolicy authorization policy following existing NlParseAttemptPolicy pattern
- Build NlOrgDiffApplier service that transactionally applies confirmed changesets via existing Org services
- Add API endpoints POST /api/org/nl-parse and POST /api/org/nl-apply using flat /api/org/* prefix consistent with existing org routes
- Support conversational context with chat history in LLM prompts and a Clear Context button for explicit reset
- Implement context window overflow detection in NlOrgPromptBuilder with fail-fast error suggesting incremental mode
- Fix OrgAgentProfile model to include soul_json in $fillable array (prerequisite)


## Constraints

- LLM-first parsing only — no rule-based parser layer; org structures are too semantically varied for regex/rule approaches
- Parser output must directly match existing Org API contract schemas (OrgAgentProfile, OrgReportingEdge, OrgRitualTemplate, OrgCouncilTemplate, OrgCostLedger) — no intermediate DSL or representation
- Must use Guzzle HTTP client for LLM calls — laravel/ai package is not available on packagist
- LLM model and temperature shared from existing config/agent.php agent execution settings — no separate model configuration
- All org modifications require explicit user confirmation via visual preview diff — never auto-apply regardless of confidence score
- Diff/merge semantics only — repeated or edited NL submissions produce changesets against current state, never replace the entire org layer
- NL input max length capped at configurable limit (default 2000 chars) via config/agent.php nl_org.max_input_length
- Chat history limited to configurable max turns (default 10) via config/agent.php nl_org.max_chat_history_turns; stored in-memory on frontend, not persisted to database
- No cap on org layer size — LLM context window is the natural limit; return structured error suggesting incremental mode on overflow
- Tests must mock LLM responses — no real LLM calls in CI; use fixture NL inputs with expected parse outputs
- Must reuse existing Org services (OrgAgentProfileService, etc.) for persistence — NlOrgDiffApplier delegates to them, does not bypass them
- Entity resolution must query OrgAgentProfileService to resolve name/role references — unresolved references flagged as ambiguous annotations
- Parse attempts must be tracked via NlOrgParseAttempt model scoped to user_id (not org_layer_id) for idempotency and auditing
- NlOrgParseAttempt records persist indefinitely — no automatic cleanup or expiration; full audit trail retention
- OrgAgentProfile.soul_json must be added to $fillable before NL parser can create agents with soul definitions
- Frontend sidebar must coexist with OrgLayerBuilder.vue without replacing or breaking existing drag-and-drop functionality
- API routes must use flat /api/org/* prefix pattern consistent with existing org routes — not nested /api/org-layers/{orgLayer}/*
- NlOrgController must apply NlOrgParseAttemptPolicy authorization checks and org middleware consistent with existing org controllers
- OrgEscalation is a runtime event model — do not include escalation path configuration in NL-parseable entities in v1; future escalation config will be embedded as JSON column on OrgAgentProfile
- OrgRitualTemplate uses embedded phase_graph — no separate OrgRitualRule model exists; parser schema must reflect the actual OrgRitualTemplate structure
- No references to OrgRitualRule, OrgCostProfile, OrgEscalationPath, or OrgCouncil (use corrected names: OrgRitualTemplate, OrgCostLedger, OrgCouncilTemplate) in any new code
- Rituals and councils described without explicit agent participant references must be flagged as ambiguous — never infer participants or create entities with empty participant lists


## Acceptance Criteria

- NlOrgParserService accepts a natural language string, serialized current org state, and optional chat history array, calls LLM via Guzzle, and returns NlOrgParseResult with changeset, confidence score, and annotations
- NlOrgParseResult changeset contains arrays of entity payloads (OrgAgentProfile, OrgReportingEdge, OrgRitualTemplate with phase_graph, OrgCouncilTemplate, OrgCostLedger) each tagged with diff operation (add, update, remove)
- Holistic prompt (e.g., 'Create a team with a lead, two devs, and a QA agent reporting to the lead') produces a complete multi-entity changeset with agents and reporting edges
- Incremental prompt against an existing org layer (e.g., 'Add a security reviewer reporting to the lead') produces a targeted partial changeset with only the new agent and edge
- Conversational follow-up (e.g., 'make that agent senior level' after creating an agent) correctly resolves the pronoun to the previously created agent using chat history context
- Entity resolution: prompt referencing an existing profile by name (e.g., 'use the QA agent template') resolves to the correct OrgAgentProfile ID and reuses its configuration
- Unresolved entity references produce annotations with ambiguity flags and suggested corrections in the NlOrgParseResult
- Ritual or council described without explicit agent participants (e.g., 'add a daily standup ritual') is withheld from the changeset and produces an inline annotation asking the user to specify participating agents
- When a prompt contains both fully-specified entities and a ritual/council missing participants, the fully-specified entities appear in the changeset while the incomplete entity is annotated separately
- When the serialized org state + prompt + chat history exceeds the LLM context window, NlOrgPromptBuilder fails fast and NlOrgParserService returns a structured error suggesting incremental mode
- NlOrgChat.vue renders as a sidebar panel alongside OrgLayerBuilder.vue with text input, scrollable conversation history, inline error annotations with highlighted ambiguous portions (including missing-participant warnings and context overflow errors), and a Clear Context button
- Clear Context button resets the in-memory chat history and subsequent parse requests are sent without prior turn context
- Visual preview diff overlay on OrgLayerBuilder canvas shows added nodes/edges in green, modified in amber, removed in red, with Apply and Discard action buttons
- Clicking Apply calls POST /api/org/nl-apply, which delegates to NlOrgDiffApplier to transactionally persist all changes via existing Org services
- Clicking Discard clears the preview diff and returns the canvas to its pre-parse state without any persistence
- NlOrgParseAttempt record is created for each parse request with user_id, raw_input, parsed_result JSON, confidence, and applied_at (null until confirmed)
- NlOrgParseAttempt records persist indefinitely with no automatic cleanup — both confirmed and unconfirmed attempts are retained for audit trail
- POST /api/org/nl-parse accepts optional chat_history array in request body and returns the parse result with changeset and annotations without persisting any changes
- POST /api/org/nl-apply accepts a parse attempt ID, validates it belongs to the authenticated user via NlOrgParseAttemptPolicy, and persists the changeset transactionally
- NlOrgDiffApplier wraps all entity creation/update/deletion in a database transaction — partial failures roll back entirely
- Confidence score below config threshold (default 0.7) causes all annotations to render as warnings in the chat panel
- NlOrgController applies NlOrgParseAttemptPolicy authorization and org middleware checks on all endpoints
- NlOrgParseAttemptPolicy ensures users can only view and apply their own parse attempts, following NlParseAttemptPolicy pattern
- NlOrgPromptBuilder appends chat history (up to max_chat_history_turns) after current org state in the LLM prompt
- NlOrgPromptBuilder instructs the LLM to flag rituals/councils without explicit participant references as ambiguous rather than inferring participants
- NlOrgPromptBuilder calculates approximate token usage before sending and fails fast if over context window budget
- Unit tests cover NlOrgParserService with at least 7 fixture NL inputs (holistic creation, incremental add, incremental remove, entity reference, ambiguous input, ritual without participants, context overflow) using mocked LLM responses
- Unit tests cover conversational context: a fixture with chat history containing a prior agent creation followed by a pronoun-based follow-up command, verifying correct entity resolution
- Unit tests cover NlOrgDiffApplier verifying correct delegation to OrgAgentProfileService and related services for each diff operation type
- Unit tests cover NlOrgPromptBuilder verifying correct serialization of current org state, chat history, and output schema into the LLM prompt, including OrgRitualTemplate phase_graph structure and token budget calculation
- OrgAgentProfile model includes soul_json in $fillable and existing tests continue to pass
- NlOrgChat.vue does not interfere with existing OrgLayerBuilder drag-and-drop interactions when sidebar is open
- No references to OrgRitualRule, OrgCostProfile, OrgEscalationPath, or OrgCouncil exist in any new code — only corrected model names are used

