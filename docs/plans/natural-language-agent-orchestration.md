# Implementation Plan

Derived from discovery session 1.

# NL Org Layer Builder — Implementation Plan

## Section 0: Prerequisite Fix — OrgAgentProfile $fillable

### 0.1 Add `soul_json` to `OrgAgentProfile::$fillable`
**File**: `app/Models/OrgAgentProfile.php`
- Add `'soul_json'` to the `$fillable` array (after `'archived_at'`)
- Add `'soul_json' => 'array'` to the `casts()` return array
- The migration `2026_03_06_100000_add_soul_json_to_delegatee_and_org_profiles.php` already exists — no new migration needed
- Verify existing `OrgAgentProfileService` tests still pass after change (the `create()` and `update()` methods use mass-assignment via `OrgAgentProfile::create()` and `$profile->update()`)

### 0.2 Verify existing test suite
- Run `php artisan test --filter=OrgAgentProfile` to confirm no regressions
- Run `php artisan test --filter=OrgAgent` to confirm controller tests pass

---

## Section 1: Configuration

### 1.1 Add `nl_org` config key to `config/agent.php`
**File**: `config/agent.php`
- Add new `'nl_org'` key inside the return array (place it after the existing `'nl_parse'` block):
```php
'nl_org' => [
    'max_input_length' => (int) env('NL_ORG_MAX_INPUT_LENGTH', 2000),
    'confidence_threshold' => (float) env('NL_ORG_CONFIDENCE_THRESHOLD', 0.7),
    'max_chat_history_turns' => (int) env('NL_ORG_MAX_CHAT_HISTORY_TURNS', 10),
],
```
- LLM model and temperature are intentionally NOT configured here — they are read from existing agent execution settings elsewhere in `config/agent.php`

---

## Section 2: Database Migration & Model

### 2.1 Create migration for `nl_org_parse_attempts` table
**File**: `database/migrations/YYYY_MM_DD_HHMMSS_create_nl_org_parse_attempts_table.php`
- Columns:
  - `id` — UUID primary key (using `$table->uuid('id')->primary()`)
  - `user_id` — foreign key to `users.id` with `cascadeOnDelete()`
  - `raw_input` — `text` column (the NL string submitted)
  - `parsed_result` — `jsonb` column (the full `NlOrgParseResult` serialized)
  - `confidence` — `float` column, nullable (null if parse failed entirely)
  - `applied_at` — `timestamp` column, nullable (set when user clicks Apply)
  - `created_at` / `updated_at` — standard Laravel timestamps
- Index on `user_id` for ownership queries
- Index on `['user_id', 'created_at']` for idempotency lookups

### 2.2 Create `NlOrgParseAttempt` model
**File**: `app/Models/NlOrgParseAttempt.php`
- Uses `HasUuids` trait (consistent with `NlParseAttempt`)
- `$guarded = []` (consistent with `NlParseAttempt` pattern)
- Casts: `parsed_result` → `array`, `confidence` → `float`, `applied_at` → `datetime`
- Relationships: `user()` → `BelongsTo(User::class)`
- Scopes:
  - `scopeForUser($query, $userId)` — filters by `user_id`
  - `scopeUnapplied($query)` — filters where `applied_at` is null
  - `scopeForIdempotency($query, $userId, $rawInput)` — finds matching attempt by user + exact raw_input within a configurable idempotency window (60 seconds, matching NlParseAttempt pattern)
- No automatic cleanup/expiration — records persist indefinitely per requirements

---

## Section 3: NlOrgParseResult DTO

### 3.1 Create `NlOrgParseResult` DTO
**File**: `app/Support/NlOrg/NlOrgParseResult.php`
- Immutable DTO implementing `Arrayable` and `JsonSerializable` (mirrors `ParseResult` pattern from NlSchedule)
- Constructor parameters:
  - `float $confidence` — 0.0 to 1.0, validated in constructor
  - `array $changeset` — array of entity payloads, each with structure: `['entity_type' => string, 'operation' => 'add'|'update'|'remove', 'payload' => array, 'temp_id' => ?string]`
  - `array $annotations` — array of inline annotations: `['message' => string, 'type' => 'error'|'warning'|'ambiguity'|'missing_participants', 'char_offset_start' => ?int, 'char_offset_end' => ?int, 'suggestion' => ?string]`
  - `array $resolvedReferences` — map of `name => profile_id` for resolved entity references
  - `?string $error` — null on success; structured error string on failure (e.g., context overflow message)
- `toArray()` method serializes all fields
- `isHighConfidence(): bool` — checks against `config('agent.nl_org.confidence_threshold', 0.7)`
- `hasError(): bool` — returns `$this->error !== null`
- `getChangesetByEntityType(string $type): array` — filters changeset by entity_type
- Entity type constants: `TYPE_AGENT_PROFILE = 'org_agent_profile'`, `TYPE_REPORTING_EDGE = 'org_reporting_edge'`, `TYPE_RITUAL_TEMPLATE = 'org_ritual_template'`, `TYPE_COUNCIL_TEMPLATE = 'org_council_template'`, `TYPE_COST_LEDGER = 'org_cost_ledger'`
- Operation constants: `OP_ADD = 'add'`, `OP_UPDATE = 'update'`, `OP_REMOVE = 'remove'`

---

## Section 4: NlOrgPromptBuilder

### 4.1 Create `NlOrgPromptBuilder`
**File**: `app/Support/NlOrg/NlOrgPromptBuilder.php`
- Constructor receives: no dependencies (pure builder, config values read via `config()`)
- Primary method: `build(string $nlInput, array $currentOrgState, array $chatHistory = []): string`
  - Returns the complete LLM prompt string
  - Throws `NlOrgContextOverflowException` if approximate token count exceeds budget
- Prompt structure (assembled in order):
  1. **System instruction**: Role definition — "You are an org layer configuration parser..."
  2. **Output JSON schema definition**: Explicit schema describing the expected changeset format, entity types, diff operations, annotation format. Describes `OrgRitualTemplate` with `phase_graph` structure and `phase_role_mappings`. Describes `OrgCouncilTemplate` with `member_list` array structure. Explicitly instructs: "If a ritual or council is described without specifying which agent profiles participate, flag it as ambiguous with a missing_participants annotation. Do not infer participants or create the entity with an empty participant list."
  3. **Current org state serialization**: JSON representation of all existing `OrgAgentProfile` records (with id, name, role_slug, role_description, soul_json, capability_bindings), `OrgReportingEdge` records (subordinate_agent_id, manager_agent_id), `OrgRitualTemplate` records (id, name, phase_graph, phase_role_mappings), `OrgCouncilTemplate` records (id, name, member_list), and `OrgCostLedger` summary per agent
  4. **Few-shot examples**: 3-4 hardcoded examples showing holistic creation, incremental add, incremental remove, and entity reference resolution
  5. **Chat history**: Last N turns (up to `config('agent.nl_org.max_chat_history_turns', 10)`), formatted as `User: <input>\nAssistant: <summary of parse result>`
  6. **Current user input**: The NL string being parsed

### 4.2 Token budget calculation
- Method: `estimateTokenCount(string $prompt): int` — uses 4 chars/token heuristic (consistent with existing MemoryContextBuilder pattern)
- Method: `getContextWindowBudget(): int` — reads model context window from agent execution config; reserves 20% for LLM output tokens
- `build()` calls `estimateTokenCount()` on the assembled prompt before returning. If over budget, throws `NlOrgContextOverflowException` with a user-friendly message suggesting incremental mode

### 4.3 Create `NlOrgContextOverflowException`
**File**: `app/Support/NlOrg/Exceptions/NlOrgContextOverflowException.php`
- Extends `RuntimeException`
- Carries `estimatedTokens` and `budgetTokens` as public readonly properties for structured error reporting

### 4.4 Org state serializer helper
- Method: `serializeOrgState(User $user): array` — queries all active org entities for the user via their respective models and returns a structured array
- This is a method on `NlOrgPromptBuilder` (not a separate class), since it is only used for prompt construction
- Queries: `OrgAgentProfile::forUser($userId)->active()->with('reportingEdge')->get()`, `OrgRitualTemplate::forUser($userId)->active()->get()`, `OrgCouncilTemplate::forUser($userId)->active()->get()`, `OrgCostLedger::forUser($userId)->selectRaw('org_agent_id, SUM(estimated_cost_usd) as total_cost')->groupBy('org_agent_id')->get()`

---

## Section 5: NlOrgParserService

### 5.1 Create `NlOrgParserService`
**File**: `app/Support/NlOrg/NlOrgParserService.php`
- Constructor dependencies:
  - `NlOrgPromptBuilder $promptBuilder`
  - `OrgAgentProfileService $profileService` (for entity resolution)
- Primary method: `parse(User $user, string $input, array $chatHistory = []): NlOrgParseResult`

### 5.2 Parse flow
1. **Validate input length**: Check against `config('agent.nl_org.max_input_length', 2000)`. Throw `InvalidArgumentException` if exceeded.
2. **Build prompt**: Call `$this->promptBuilder->build($input, $orgState, $chatHistory)`. Catch `NlOrgContextOverflowException` and return an `NlOrgParseResult` with `error` set to the overflow message.
3. **Call LLM via Guzzle**: POST to the LLM API endpoint using config from `config/agent.php` agent execution settings. Parse the JSON response.
4. **Entity resolution**: For each entity in the LLM response that references existing profiles by name/role, query `OrgAgentProfile::forUser($user->id)->active()` to resolve names to IDs. Build `resolvedReferences` map. Flag unresolved references as ambiguity annotations.
5. **Missing participant validation**: Iterate changeset entries of type `org_ritual_template` and `org_council_template`. Check that ritual entries have non-empty `phase_role_mappings` referencing agent IDs, and council entries have non-empty `member_list` referencing agent IDs. Withhold entities with missing participants from the changeset array; move them to annotations with `type: 'missing_participants'`.
6. **Construct and return `NlOrgParseResult`**: Assemble confidence, changeset, annotations, resolvedReferences.

### 5.3 LLM client method
- Private method: `callLlm(string $prompt): array`
- Uses `Http::timeout(30)->post(...)` (Laravel HTTP client wrapping Guzzle)
- Reads endpoint URL and API key from existing agent execution config
- Parses JSON response; if malformed, returns `NlOrgParseResult` with error annotation
- Extracts the structured changeset JSON from the LLM response content

### 5.4 Redacted logging
- Follow `NlScheduleParserService` pattern: log first 80 chars + SHA-256 hash of input
- Log at key points: parse start, LLM call, entity resolution, result

---

## Section 6: NlOrgDiffApplier

### 6.1 Create `NlOrgDiffApplier`
**File**: `app/Support/NlOrg/NlOrgDiffApplier.php`
- Constructor dependencies:
  - `OrgAgentProfileService $profileService`
  - `OrgReportingEdgeService $edgeService`
  - `OrgRitualTemplateService $ritualService`
  - `OrgCouncilService $councilService`
- Primary method: `apply(User $user, NlOrgParseResult $result): void`

### 6.2 Transactional application
- Wraps all operations in `DB::transaction(function () { ... })`
- Processes changeset entries in dependency order:
  1. **Remove operations first** (to free up names/references): Process `remove` ops for edges, then cost ledger entries, then rituals, then councils, then agent profiles
  2. **Add/Update agent profiles**: Delegates to `OrgAgentProfileService::create()` or `OrgAgentProfileService::update()`. For `add` operations where the changeset uses temporary IDs (e.g., `temp_id: "new_agent_1"`), tracks the mapping from temp_id to real UUID for subsequent edge/ritual/council creation
  3. **Add/Update reporting edges**: Resolves agent references (real IDs or temp_id mappings) and delegates to `OrgReportingEdgeService`
  4. **Add/Update ritual templates**: Delegates to `OrgRitualTemplateService`
  5. **Add/Update council templates**: Delegates to `OrgCouncilService`
  6. **Add/Update cost ledger entries**: Delegates to direct `OrgCostLedger::create()` (cost ledger is a recording model, no dedicated service needed for NL-initiated entries — but review if `OrgCostGovernanceService` has relevant methods)
- On any exception within the transaction, the entire changeset rolls back — no partial application

### 6.3 Temp ID resolution
- Private method: `resolveTempIds(array $changeset): array` — builds a map of `temp_id => null` before application, populated with real UUIDs as agents are created
- All entity payloads referencing a temp_id in fields like `subordinate_agent_id`, `manager_agent_id`, `org_agent_id`, `member_list[].agent_id`, `phase_role_mappings` are resolved before delegation to services

---

## Section 7: Authorization — Policy & Controller

### 7.1 Create `NlOrgParseAttemptPolicy`
**File**: `app/Policies/NlOrgParseAttemptPolicy.php`
- Follows `NlParseAttemptPolicy` pattern exactly:
  - `viewAny(User $user): bool` — returns `$user->hasRole(['admin', 'analytics'])`
  - `view(User $user, NlOrgParseAttempt $attempt): bool` — returns `$user->id === $attempt->user_id || $user->hasRole(['admin', 'analytics'])`
  - `apply(User $user, NlOrgParseAttempt $attempt): bool` — returns `$user->id === $attempt->user_id` (only the owner can apply their parse)
- Register policy in `AuthServiceProvider` (or rely on auto-discovery if the project uses it — check `App\Providers\AuthServiceProvider` for existing policy registrations)

### 7.2 Create `NlOrgController`
**File**: `app/Http/Controllers/Api/V1/Org/NlOrgController.php`
- Namespace: `App\Http\Controllers\Api\V1\Org` (consistent with other Org controllers in this directory)
- Constructor dependencies: `NlOrgParserService $parser`, `NlOrgDiffApplier $applier`

### 7.3 `POST /api/org/nl-parse` endpoint — `parse()` method
- Request validation:
  - `input` — required, string, max length from config
  - `chat_history` — optional, array, each element has `role` (string, in:user,assistant) and `content` (string)
  - `current_org_state` — optional, array (if frontend sends pre-fetched state; otherwise controller fetches from DB)
- Flow:
  1. Validate request
  2. Call `$this->parser->parse($user, $input, $chatHistory)`
  3. Create `NlOrgParseAttempt` record with `user_id`, `raw_input`, `parsed_result` (serialized NlOrgParseResult), `confidence`, `applied_at` = null
  4. Return JSON response: `{ parse_attempt_id, status, changeset, annotations, confidence, resolved_references, error }`
- Does NOT persist any org changes — preview only

### 7.4 `POST /api/org/nl-apply` endpoint — `apply()` method
- Request validation:
  - `parse_attempt_id` — required, uuid, exists in `nl_org_parse_attempts`
- Flow:
  1. Load `NlOrgParseAttempt` by ID
  2. Authorize via `$this->authorize('apply', $attempt)` — uses `NlOrgParseAttemptPolicy`
  3. Check `applied_at` is null (prevent double-apply)
  4. Deserialize `parsed_result` into `NlOrgParseResult`
  5. Call `$this->applier->apply($user, $result)`
  6. Update `$attempt->applied_at = now()` and save
  7. Return JSON response: `{ success: true, applied_at }`
- On failure (e.g., DB transaction error), return 422 with error details

### 7.5 Register routes
**File**: `routes/api.php`
- Add inside the existing `Route::prefix('org')->middleware(['org'])->group(...)` block:
```php
// NL Org parsing
Route::post('/nl-parse', [Org\NlOrgController::class, 'parse'])
    ->middleware('throttle:agent-mutations');
Route::post('/nl-apply', [Org\NlOrgController::class, 'apply'])
    ->middleware('throttle:agent-mutations');
```
- This places routes at `POST /agent/api/v1/org/nl-parse` and `POST /agent/api/v1/org/nl-apply`, consistent with the flat `/api/org/*` prefix pattern used by all other org routes
- Routes inherit `auth:sanctum`, `license`, and `org` middleware from parent groups

---

## Section 8: Frontend — API Composable

### 8.1 Create `useNlOrgParser` composable
**File**: `resources/js/Composables/useNlOrgParser.ts`
- Reactive state:
  - `loading: Ref<boolean>` — true during parse or apply API calls
  - `error: Ref<string | null>` — error message from last operation
  - `parseResult: Ref<NlOrgParseResult | null>` — last parse result (changeset, annotations, confidence)
  - `parseAttemptId: Ref<string | null>` — ID of the last parse attempt
  - `chatHistory: Ref<ChatTurn[]>` — in-memory session-scoped array of `{ role: 'user' | 'assistant', content: string }` turns
- Methods:
  - `parse(input: string): Promise<void>` — calls `POST /agent/api/v1/org/nl-parse` with `{ input, chat_history: chatHistory.value }`. On success, pushes user turn and assistant summary to chatHistory. Trims chatHistory to max turns from config.
  - `apply(): Promise<void>` — calls `POST /agent/api/v1/org/nl-apply` with `{ parse_attempt_id: parseAttemptId.value }`. Clears parseResult on success.
  - `discard(): void` — clears parseResult and parseAttemptId without API call
  - `clearContext(): void` — resets chatHistory to empty array, clears parseResult
- TypeScript interfaces for `NlOrgParseResult`, `ChangesetEntry`, `Annotation`, `ChatTurn`

---

## Section 9: Frontend — NlOrgChat.vue Sidebar

### 9.1 Create `NlOrgChat.vue`
**File**: `resources/js/Components/Org/NlOrgChat.vue`
- Props: none required (composable provides all state)
- Emits:
  - `preview-changeset` — emitted with changeset data when a parse result arrives (consumed by OrgLayerBuilder to render diff overlay)
  - `apply-changeset` — emitted when user clicks Apply
  - `discard-changeset` — emitted when user clicks Discard
- Template structure:
  - Outer container: fixed-width sidebar panel (e.g., `w-96`) with flex column layout
  - **Header**: Title "NL Org Builder" + "Clear Context" button (calls `clearContext()`)
  - **Scrollable conversation area**: Renders chat history as alternating user/assistant message bubbles. Assistant messages include:
    - Summary of parse result (e.g., "Found 3 agents, 2 edges, 1 ritual")
    - Confidence indicator (color-coded: green ≥ threshold, amber < threshold)
    - Inline annotations rendered as highlighted callout boxes within the message. Annotation types:
      - `error` — red background
      - `warning` — amber background
      - `ambiguity` — yellow background with suggested correction text
      - `missing_participants` — orange background with prompt "Please specify which agents participate"
    - Context overflow errors rendered as a distinct error card suggesting incremental mode
  - **Input area**: Text input (textarea with auto-resize) + Send button. Input enforces max length from config with character counter. Disabled while `loading` is true.
  - **Action buttons** (visible only when parseResult is non-null): "Apply" (green) and "Discard" (red) buttons

### 9.2 Discoverability and navigation
- The sidebar is toggled via a button in the `OrgLayerBuilder.vue` toolbar/header area — icon + label "NL Builder" or similar
- When the sidebar is open, the graph canvas area narrows (e.g., `flex` layout with sidebar taking `w-96` and canvas taking remaining space) — no overlay that blocks drag-and-drop
- The toggle button uses a recognizable icon (e.g., `MessageSquare` from lucide-vue-next) to indicate chat/NL functionality

---

## Section 10: Frontend — OrgDiffPreview Integration

### 10.1 Modify `OrgLayerBuilder.vue` for diff preview
**File**: `resources/js/Pages/Agent/Org/OrgLayerBuilder.vue`
- Import and mount `NlOrgChat` component conditionally (sidebar toggle state)
- Add `showNlSidebar: Ref<boolean>` toggle state
- Add `pendingChangeset: Ref<ChangesetEntry[] | null>` — populated from `NlOrgChat`'s `preview-changeset` event
- Layout modification: Wrap existing canvas in a flex container. When `showNlSidebar` is true, render `NlOrgChat` to the right (or left) of the canvas

### 10.2 Diff overlay rendering on graph canvas
- When `pendingChangeset` is non-null, compute overlay nodes and edges:
  - `add` operations: Render new nodes/edges with green border/highlight (`border-green-500 bg-green-50` or similar). Use dashed borders to indicate "pending".
  - `update` operations: Render existing nodes with amber border/highlight (`border-amber-500 bg-amber-50`). Show tooltip or badge indicating what changed.
  - `remove` operations: Render existing nodes/edges with red border, reduced opacity, and strikethrough on the label.
- Overlay nodes are non-interactive (no drag-and-drop during preview) — existing nodes remain interactive
- Apply/Discard buttons rendered as a floating action bar at the bottom of the canvas (or within the sidebar)

### 10.3 Apply/Discard handlers
- `onApply()`: Calls `useNlOrgParser().apply()`. On success, refreshes the org data from the API (re-fetches agents, edges, rituals, councils) and clears the diff overlay. Emits success toast.
- `onDiscard()`: Calls `useNlOrgParser().discard()`. Clears `pendingChangeset` and removes overlay. No API call.

---

## Section 11: Backend Tests

### 11.1 `NlOrgParseResultTest`
**File**: `tests/Unit/Support/NlOrg/NlOrgParseResultTest.php`
- Test confidence validation (rejects < 0 and > 1)
- Test `toArray()` serialization
- Test `isHighConfidence()` against config threshold
- Test `hasError()` with and without error
- Test `getChangesetByEntityType()` filtering

### 11.2 `NlOrgPromptBuilderTest`
**File**: `tests/Unit/Support/NlOrg/NlOrgPromptBuilderTest.php`
- Test prompt contains current org state serialization (mock DB data, verify JSON appears in prompt)
- Test prompt contains output schema definition with all 5 entity types
- Test prompt contains ritual `phase_graph` structure description
- Test prompt includes chat history (verify last N turns appear)
- Test prompt respects `max_chat_history_turns` config (truncates older turns)
- Test token budget calculation — verify `estimateTokenCount()` uses 4 chars/token
- Test context overflow detection — provide a prompt that exceeds budget, verify `NlOrgContextOverflowException` is thrown
- Test prompt instructs LLM to flag rituals/councils without explicit participants

### 11.3 `NlOrgParserServiceTest`
**File**: `tests/Unit/Support/NlOrg/NlOrgParserServiceTest.php`
- All tests mock LLM responses via `Http::fake()` — no real LLM calls
- **Fixture 1 — Holistic creation**: Input "Create a team with a lead, two developers, and a QA agent. QA reports to lead." → Mock LLM returns changeset with 4 agent adds + 3 reporting edge adds. Assert all entities present with correct operations.
- **Fixture 2 — Incremental add**: Provide existing org state with 2 agents. Input "Add a security reviewer reporting to Agent Alpha." → Mock returns 1 agent add + 1 edge add. Assert only delta entities.
- **Fixture 3 — Incremental remove**: Provide existing org state. Input "Remove the cost ledger from Agent Beta." → Mock returns 1 cost ledger remove. Assert single remove operation.
- **Fixture 4 — Entity reference resolution**: Input "Use the QA agent template as basis for a new testing lead." → Mock returns agent add referencing existing profile. Seed `OrgAgentProfile` with name "QA agent". Assert `resolvedReferences` map contains `"QA agent" => <seeded_id>`.
- **Fixture 5 — Ambiguous input**: Input "Add an agent called either Reviewer or Auditor." → Mock returns changeset with ambiguity annotation. Assert annotation present with suggestion.
- **Fixture 6 — Ritual without participants**: Input "Add a daily standup ritual." → Mock returns ritual entity. Assert the ritual is withheld from changeset and an annotation with `type: 'missing_participants'` is present.
- **Fixture 7 — Context overflow**: Build prompt with artificially large org state that exceeds token budget. Assert `NlOrgParseResult` has error suggesting incremental mode.
- **Fixture 8 — Conversational follow-up**: Provide chat history with prior agent creation turn. Input "Make that agent senior level." → Mock returns update operation targeting the previously created agent ID. Assert correct entity resolution via chat context.
- **Fixture 9 — Mixed complete and incomplete entities**: Input "Add a lead agent, a daily standup ritual, and a reporting edge from dev to lead." → Mock returns all three. Assert lead agent and edge are in changeset, ritual is withheld with missing_participants annotation.

### 11.4 `NlOrgDiffApplierTest`
**File**: `tests/Unit/Support/NlOrg/NlOrgDiffApplierTest.php`
- Mock all Org services (`OrgAgentProfileService`, `OrgReportingEdgeService`, `OrgRitualTemplateService`, `OrgCouncilService`)
- Test `add` operation delegates to service `create()` methods
- Test `update` operation delegates to service `update()` methods
- Test `remove` operation delegates to service `archive()` or `delete()` methods
- Test temp_id resolution: changeset with temp_id in agent add followed by edge referencing that temp_id — verify edge create receives real UUID
- Test transaction rollback: mock one service to throw exception, verify no other entities were persisted (use `assertDatabaseMissing`)

### 11.5 `NlOrgParseAttemptTest`
**File**: `tests/Unit/Models/NlOrgParseAttemptTest.php`
- Test model creation with all columns
- Test `forUser` scope
- Test `unapplied` scope
- Test `forIdempotency` scope
- Test `user()` relationship

### 11.6 `NlOrgParseAttemptPolicyTest`
**File**: `tests/Unit/Policies/NlOrgParseAttemptPolicyTest.php`
- Test `view()` — owner can view, non-owner cannot, admin can
- Test `apply()` — owner can apply, non-owner cannot
- Test `viewAny()` — admin/analytics can, regular user cannot

---

## Section 12: Feature Tests

### 12.1 `NlOrgControllerTest`
**File**: `tests/Feature/NlOrg/NlOrgControllerTest.php`
- **POST /org/nl-parse**:
  - Test successful parse returns 200 with changeset, annotations, confidence, parse_attempt_id
  - Test input exceeding max length returns 422
  - Test unauthenticated returns 401
  - Test creates `NlOrgParseAttempt` record in database
  - Test chat_history is optional and accepted
  - Test org middleware gate (when org feature disabled, returns 403)
- **POST /org/nl-apply**:
  - Test successful apply returns 200, sets `applied_at` on attempt
  - Test applying another user's attempt returns 403
  - Test applying already-applied attempt returns 422
  - Test non-existent parse_attempt_id returns 404/422
  - Test transaction failure returns 422 and attempt remains unapplied
- All tests use `Http::fake()` for LLM calls

---

## Section 13: Dependency Order & Sequencing

The sections must be implemented in this order due to dependencies:

1. **Section 0** (Prerequisite fix) — no dependencies
2. **Section 1** (Configuration) — no dependencies
3. **Section 2** (Migration & Model) — depends on Section 1 for config references in scopes
4. **Section 3** (NlOrgParseResult DTO) — depends on Section 1 for confidence threshold config
5. **Section 4** (NlOrgPromptBuilder) — depends on Section 3 for result type awareness, reads existing org models
6. **Section 5** (NlOrgParserService) — depends on Sections 3, 4; calls PromptBuilder and constructs ParseResult
7. **Section 6** (NlOrgDiffApplier) — depends on Section 3; consumes ParseResult and delegates to existing Org services
8. **Section 7** (Policy, Controller, Routes) — depends on Sections 2, 5, 6; wires everything together
9. **Section 8** (Frontend composable) — depends on Section 7 (API endpoints must exist)
10. **Section 9** (NlOrgChat.vue) — depends on Section 8
11. **Section 10** (OrgLayerBuilder diff integration) — depends on Sections 8, 9
12. **Section 11** (Unit tests) — depends on Sections 2-6 (tests the backend components)
13. **Section 12** (Feature tests) — depends on Section 7 (tests the HTTP layer)

Sections 0, 1 can be done in parallel. Sections 2, 3 can be done in parallel. Sections 5, 6 can be done in parallel after Section 4. Sections 11, 12 can be done in parallel after their respective dependencies.

## Sections

- Section 0: Prerequisite Fix — OrgAgentProfile $fillable
- Section 1: Configuration
- Section 2: Database Migration & Model
- Section 3: NlOrgParseResult DTO
- Section 4: NlOrgPromptBuilder
- Section 5: NlOrgParserService
- Section 6: NlOrgDiffApplier
- Section 7: Authorization — Policy, Controller & Routes
- Section 8: Frontend — API Composable
- Section 9: Frontend — NlOrgChat.vue Sidebar
- Section 10: Frontend — OrgDiffPreview Integration
- Section 11: Backend Unit Tests
- Section 12: Feature Tests
- Section 13: Dependency Order & Sequencing


## Risks

- LLM response format instability: The LLM may not consistently return valid JSON matching the expected changeset schema, requiring robust parsing/fallback logic in NlOrgParserService and potentially multiple prompt iterations to achieve reliable structured output
- Entity resolution false positives: Fuzzy name/role matching against OrgAgentProfile records could resolve to incorrect profiles when multiple agents have similar names, producing silent misconfigurations that the user might not catch in preview
- OrgCostLedger is a runtime recording model (token_count, runtime_ms, estimated_cost_usd per ritual run) — NL commands like 'set a budget for agent X' do not map to OrgCostLedger's schema since it tracks actual costs, not budget constraints. Clarify with requirements whether NL-initiated cost entries are truly in scope or if budget configuration needs a different model
- OrgAgentProfileService.create() requires a valid delegatee_profile_id with ownership validation — NL-created agents must reference an existing DelegateeProfile owned by the user, which the LLM cannot know about without explicit instruction in the prompt. Users may describe agents without specifying delegatee bindings, causing creation failures
- Existing org routes use the /agent/api/v1/org/* prefix (not flat /api/org/*) — the NL endpoints must be registered within the same route group to inherit auth:sanctum, license, and org middleware, matching the actual URL pattern
- The phase_graph and phase_role_mappings structures on OrgRitualTemplate are complex nested JSON — the LLM may produce syntactically valid but semantically incorrect phase graphs that pass validation but cause ritual execution failures
- soul_json column exists in migration but its expected schema/structure is not documented — the LLM prompt needs a clear schema definition for soul_json to generate valid payloads, but the intended structure must be determined first
- Chat history stored only in frontend memory is lost on page refresh — users mid-conversation who refresh the page lose all conversational context with no recovery path


## Assumptions

- The existing Guzzle/HTTP client configuration for LLM calls (endpoint URL, API key, model name) is accessible via config/agent.php or related environment variables already used by the agent execution pipeline
- OrgAgentProfileService, OrgReportingEdgeService, OrgRitualTemplateService, and OrgCouncilService all have create/update/delete (or archive) methods suitable for programmatic use by NlOrgDiffApplier without requiring HTTP request context
- The org middleware referenced in routes/api.php checks the config('agent.org.enabled') feature flag and returns 403 when disabled — NL org routes will inherit this gate automatically
- The GraphCanvas component used in OrgLayerBuilder.vue supports dynamic node/edge styling (border colors, opacity, dashed borders) sufficient to render diff preview overlays without requiring GraphCanvas modifications
- Auto-policy discovery is active (Laravel default) so NlOrgParseAttemptPolicy will be auto-discovered for the NlOrgParseAttempt model without explicit registration in AuthServiceProvider
- The LLM model configured for agent execution supports structured JSON output and the context window is large enough to accommodate typical org states (under ~50 agents) plus the prompt template and few-shot examples
- OrgReportingEdgeService has a create() method accepting subordinate_agent_id, manager_agent_id, and user_id — consistent with the OrgReportingEdge $fillable array
- The existing throttle:agent-mutations rate limiter is appropriate for NL parse and apply endpoints

