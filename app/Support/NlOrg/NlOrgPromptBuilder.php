<?php

declare(strict_types=1);

namespace App\Support\NlOrg;

use App\Support\NlOrg\Exceptions\NlOrgContextOverflowException;

class NlOrgPromptBuilder
{
    /**
     * Build the complete LLM prompt for NL org parsing.
     *
     * @param  string  $nlInput  The user's natural language input
     * @param  array  $currentOrgState  Structured array of current org entities
     * @param  array  $chatHistory  Array of ['role' => 'user'|'assistant', 'content' => string]
     *
     * @throws NlOrgContextOverflowException
     */
    public function build(string $nlInput, array $currentOrgState, array $chatHistory = []): string
    {
        $sections = [];

        $sections[] = $this->buildSystemInstruction();
        $sections[] = $this->buildOutputSchema();
        $sections[] = $this->buildOrgStateSection($currentOrgState);
        $sections[] = $this->buildFewShotExamples();

        $historySection = $this->buildChatHistorySection($chatHistory);
        if ($historySection !== null) {
            $sections[] = $historySection;
        }

        $sections[] = $this->buildUserInputSection($nlInput);

        $prompt = implode("\n\n", $sections);

        $estimatedTokens = $this->estimateTokenCount($prompt);
        $budget = $this->getContextWindowBudget();

        if ($estimatedTokens > $budget) {
            throw new NlOrgContextOverflowException($estimatedTokens, $budget);
        }

        return $prompt;
    }

    public function estimateTokenCount(string $prompt): int
    {
        return (int) ceil(strlen($prompt) / 4);
    }

    public function getContextWindowBudget(): int
    {
        $contextWindow = (int) config('memory.context_injection.default_context_window', 200000);

        return (int) ($contextWindow * 0.8);
    }

    private function buildSystemInstruction(): string
    {
        return <<<'PROMPT'
## System Instruction

You are an org layer configuration parser. Your role is to interpret natural language descriptions of agent organizational structures and convert them into structured changeset payloads.

You receive the current org state as context and must produce a diff (additions, modifications, removals) — never wholesale-replace the org layer. Each entity in your output must be tagged with a diff operation: `add`, `update`, or `remove`.

When referencing existing agents, resolve them by name or role against the provided org state. If a reference cannot be resolved, flag it as ambiguous with an annotation.

If a ritual or council is described without specifying which agent profiles participate, you MUST flag it as ambiguous with a `missing_participants` annotation type. Do not infer participants or create the entity with an empty participant list. Other fully-specified entities in the same prompt should still be included in the changeset.
PROMPT;
    }

    private function buildOutputSchema(): string
    {
        return <<<'PROMPT'
## Output JSON Schema

Respond with a JSON object matching this schema:

```json
{
  "confidence": 0.0-1.0,
  "changeset": [
    {
      "entity_type": "org_agent_profile | org_reporting_edge | org_ritual_template | org_council_template | org_cost_ledger",
      "operation": "add | update | remove",
      "payload": { ... },
      "temp_id": "optional temporary ID for cross-referencing new entities"
    }
  ],
  "annotations": [
    {
      "message": "description of issue",
      "type": "error | warning | ambiguity | missing_participants",
      "char_offset_start": null,
      "char_offset_end": null,
      "suggestion": "optional suggested fix"
    }
  ],
  "resolved_references": {
    "agent name": "resolved-uuid"
  }
}
```

### Entity Type Schemas

**`org_agent_profile`** — An agent in the org hierarchy.
Payload fields: `name` (string), `role_slug` (string), `role_description` (string), `soul_json` (object, optional), `capability_bindings` (array, optional).

**`org_reporting_edge`** — A parent-child reporting relationship between two agents.
Payload fields: `subordinate_agent_id` (UUID or temp_id), `manager_agent_id` (UUID or temp_id).

**`org_ritual_template`** — A recurring ritual with an embedded `phase_graph` defining its execution phases. Rituals are modeled as templates with phase graphs, not separate rule entities.
Payload fields: `name` (string), `phase_graph` (object — defines phases and transitions), `phase_role_mappings` (array — maps phases to participating agent IDs; REQUIRED, must not be empty).

**`org_council_template`** — A decision-making group configuration.
Payload fields: `name` (string), `member_list` (array of `{ "agent_id": UUID or temp_id }` — REQUIRED, must not be empty).

**`org_cost_ledger`** — A cost/budget constraint for an agent.
Payload fields: `org_agent_id` (UUID or temp_id), `estimated_cost_usd` (float), `description` (string, optional).
PROMPT;
    }

    private function buildOrgStateSection(array $currentOrgState): string
    {
        if (empty($currentOrgState)) {
            return "## Current Org State\n\nNo existing org entities. This is a fresh org layer.";
        }

        $stateJson = json_encode($currentOrgState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return "## Current Org State\n\n```json\n{$stateJson}\n```";
    }

    private function buildFewShotExamples(): string
    {
        return <<<'PROMPT'
## Examples

### Example 1: Holistic Creation
**User input**: "Create a 3-tier team with a lead, two developers, and a QA agent. The QA agent reports to the lead."
**Expected output**:
```json
{
  "confidence": 0.95,
  "changeset": [
    {"entity_type": "org_agent_profile", "operation": "add", "payload": {"name": "Lead", "role_slug": "lead", "role_description": "Team lead"}, "temp_id": "new_lead"},
    {"entity_type": "org_agent_profile", "operation": "add", "payload": {"name": "Developer 1", "role_slug": "developer", "role_description": "Software developer"}, "temp_id": "new_dev_1"},
    {"entity_type": "org_agent_profile", "operation": "add", "payload": {"name": "Developer 2", "role_slug": "developer", "role_description": "Software developer"}, "temp_id": "new_dev_2"},
    {"entity_type": "org_agent_profile", "operation": "add", "payload": {"name": "QA Agent", "role_slug": "qa", "role_description": "Quality assurance"}, "temp_id": "new_qa"},
    {"entity_type": "org_reporting_edge", "operation": "add", "payload": {"subordinate_agent_id": "new_qa", "manager_agent_id": "new_lead"}}
  ],
  "annotations": [],
  "resolved_references": {}
}
```

### Example 2: Incremental Add
**User input**: "Add a security reviewer reporting to Agent Alpha."
**Current state**: Contains agent "Agent Alpha" with id "uuid-alpha".
**Expected output**:
```json
{
  "confidence": 0.9,
  "changeset": [
    {"entity_type": "org_agent_profile", "operation": "add", "payload": {"name": "Security Reviewer", "role_slug": "security-reviewer", "role_description": "Reviews security"}, "temp_id": "new_sec"},
    {"entity_type": "org_reporting_edge", "operation": "add", "payload": {"subordinate_agent_id": "new_sec", "manager_agent_id": "uuid-alpha"}}
  ],
  "annotations": [],
  "resolved_references": {"Agent Alpha": "uuid-alpha"}
}
```

### Example 3: Incremental Remove
**User input**: "Remove the cost ledger from Agent Beta."
**Current state**: Contains agent "Agent Beta" with id "uuid-beta" and a cost ledger entry.
**Expected output**:
```json
{
  "confidence": 0.85,
  "changeset": [
    {"entity_type": "org_cost_ledger", "operation": "remove", "payload": {"org_agent_id": "uuid-beta"}}
  ],
  "annotations": [],
  "resolved_references": {"Agent Beta": "uuid-beta"}
}
```

### Example 4: Entity with Missing Participants
**User input**: "Add a daily standup ritual."
**Expected output**:
```json
{
  "confidence": 0.7,
  "changeset": [],
  "annotations": [
    {"message": "Ritual 'daily standup' requires explicit agent participants. Please specify which agents should participate.", "type": "missing_participants", "char_offset_start": null, "char_offset_end": null, "suggestion": "Try: 'Add a daily standup ritual with Agent Alpha and Agent Beta participating.'"}
  ],
  "resolved_references": {}
}
```
PROMPT;
    }

    private function buildChatHistorySection(array $chatHistory): ?string
    {
        if (empty($chatHistory)) {
            return null;
        }

        $maxTurns = (int) config('agent.nl_org.max_chat_history_turns', 10);

        if (count($chatHistory) > $maxTurns) {
            $chatHistory = array_slice($chatHistory, -$maxTurns);
        }

        $lines = ["## Chat History\n"];

        foreach ($chatHistory as $turn) {
            $role = $turn['role'] === 'user' ? 'User' : 'Assistant';
            $lines[] = "{$role}: {$turn['content']}";
        }

        return implode("\n", $lines);
    }

    private function buildUserInputSection(string $nlInput): string
    {
        return "## User Input\n\n{$nlInput}";
    }
}
