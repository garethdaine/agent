<?php

declare(strict_types=1);

namespace App\Support\NlOrg;

use App\Models\OrgAgentProfile;
use App\Models\User;
use App\Services\Credentials\CredentialsManager;
use App\Support\NlOrg\Exceptions\NlOrgContextOverflowException;
use App\Support\Org\OrgAgentProfileService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class NlOrgParserService
{
    public function __construct(
        private readonly NlOrgPromptBuilder $promptBuilder,
        private readonly OrgAgentProfileService $profileService,
        private readonly CredentialsManager $credentialsManager,
    ) {}

    /**
     * Parse natural language input into a structured org changeset.
     */
    public function parse(User $user, string $input, array $chatHistory = []): NlOrgParseResult
    {
        $input = trim($input);

        // 1. Validate input length
        $maxLength = (int) config('agent.nl_org.max_input_length', 2000);
        if (strlen($input) > $maxLength) {
            throw new InvalidArgumentException(
                "Input exceeds maximum length of {$maxLength} characters."
            );
        }

        $this->logRedacted('Parse started', $input, ['user_id' => $user->id]);

        // 2. Build the org state for prompt context
        $orgState = $this->serializeOrgState($user);

        // 3. Build the prompt (may throw NlOrgContextOverflowException)
        try {
            $prompt = $this->promptBuilder->build($input, $orgState, $chatHistory);
        } catch (NlOrgContextOverflowException $e) {
            $this->logRedacted('Context overflow detected', $input, [
                'estimated_tokens' => $e->estimatedTokens,
                'budget_tokens' => $e->budgetTokens,
            ]);

            return new NlOrgParseResult(
                confidence: 0.0,
                changeset: [],
                annotations: [],
                resolvedReferences: [],
                error: $e->getMessage(),
            );
        }

        // 4. Call LLM
        $this->logRedacted('Calling LLM', $input);
        $this->lastLlmError = null;
        $llmData = $this->callLlm($user, $prompt);

        if ($llmData === null) {
            $errorMessage = $this->lastLlmError ?? 'LLM response could not be parsed.';
            $this->logRedacted('LLM call failed or returned malformed response', $input, [
                'error_detail' => $errorMessage,
            ]);

            return new NlOrgParseResult(
                confidence: 0.0,
                changeset: [],
                annotations: [
                    [
                        'message' => $errorMessage,
                        'type' => 'error',
                        'char_offset_start' => null,
                        'char_offset_end' => null,
                        'suggestion' => 'Try rephrasing your input or check service configuration.',
                    ],
                ],
                resolvedReferences: [],
                error: $errorMessage,
            );
        }

        // 5. Extract fields from LLM response
        $confidence = (float) ($llmData['confidence'] ?? 0.0);
        $changeset = $llmData['changeset'] ?? [];
        $annotations = $llmData['annotations'] ?? [];
        $resolvedReferences = $llmData['resolved_references'] ?? [];

        // Clamp confidence to valid range
        $confidence = max(0.0, min(1.0, $confidence));

        // 6. Resolve entity references against OrgAgentProfile
        $resolvedReferences = $this->resolveEntityReferences($user, $resolvedReferences, $annotations);

        // 7. Validate ritual/council participant completeness
        [$changeset, $annotations] = $this->validateParticipantCompleteness($changeset, $annotations);

        $this->logRedacted('Parse completed', $input, [
            'confidence' => $confidence,
            'changeset_count' => count($changeset),
            'annotation_count' => count($annotations),
        ]);

        return new NlOrgParseResult(
            confidence: $confidence,
            changeset: $changeset,
            annotations: $annotations,
            resolvedReferences: $resolvedReferences,
        );
    }

    private ?string $lastLlmError = null;

    /**
     * Call the LLM API via Http facade.
     *
     * @return array|null Parsed JSON response, or null on failure.
     */
    private function callLlm(User $user, string $prompt): ?array
    {
        $baseUrl = rtrim((string) config('memory.providers.anthropic.base_url', 'https://api.anthropic.com/v1'), '/');
        $apiKey = $this->credentialsManager->get($user, 'anthropic', 'api_key')
            ?? config('runtime.llm.anthropic.api_key')
            ?? '';
        $model = config('runtime.llm.anthropic.model', 'claude-sonnet-4-20250514');

        if (trim((string) $apiKey) === '') {
            Log::error('[NlOrgParser] Anthropic API key is not configured');
            $this->lastLlmError = 'LLM service is not configured. Please set an Anthropic API key.';

            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/messages", [
                    'model' => $model,
                    'max_tokens' => 8192,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                $status = $response->status();

                if ($status === 401) {
                    Log::error('[NlOrgParser] Anthropic API authentication failed (401)');
                    $this->lastLlmError = 'LLM authentication failed. Please verify your Anthropic API key.';
                } elseif ($status === 429) {
                    Log::warning('[NlOrgParser] Anthropic API rate limited (429)');
                    $this->lastLlmError = 'LLM rate limit exceeded. Please wait a moment and try again.';
                } elseif ($status >= 500) {
                    Log::warning('[NlOrgParser] Anthropic API server error', ['status' => $status]);
                    $this->lastLlmError = 'LLM service is temporarily unavailable. Please try again later.';
                } else {
                    Log::warning('[NlOrgParser] LLM HTTP error', ['status' => $status]);
                    $this->lastLlmError = 'LLM request failed with status '.$status.'.';
                }

                return null;
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? null;

            if ($text === null) {
                Log::warning('[NlOrgParser] LLM returned no text content');
                $this->lastLlmError = 'LLM returned an empty response.';

                return null;
            }

            // Extract JSON from the text — may be wrapped in markdown code blocks
            $jsonText = $this->extractJson($text);
            $decoded = json_decode($jsonText, true);

            if (! is_array($decoded)) {
                Log::warning('[NlOrgParser] Failed to decode LLM JSON response', [
                    'json_error' => json_last_error_msg(),
                ]);
                $this->lastLlmError = 'LLM response was not valid JSON. Try rephrasing your input.';

                return null;
            }

            return $decoded;
        } catch (\Throwable $e) {
            Log::warning('[NlOrgParser] LLM call exception', [
                'message' => $e->getMessage(),
            ]);
            $this->lastLlmError = 'LLM service connection failed. Please try again.';

            return null;
        }
    }

    /**
     * Extract JSON from text that may be wrapped in markdown code blocks.
     */
    private function extractJson(string $text): string
    {
        // Try to extract from ```json ... ``` block
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $text, $matches)) {
            return $matches[1];
        }

        return $text;
    }

    /**
     * Serialize current org state for the user into a structured array.
     */
    private function serializeOrgState(User $user): array
    {
        $agents = OrgAgentProfile::forUser($user->id)
            ->active()
            ->get(['id', 'name', 'role_slug', 'role_description', 'capability_bindings', 'soul_json'])
            ->toArray();

        if (empty($agents)) {
            return [];
        }

        return [
            'agents' => $agents,
        ];
    }

    /**
     * Resolve entity references from the LLM response against real OrgAgentProfile records.
     *
     * The LLM may return placeholder IDs for references — we replace them with real UUIDs.
     */
    private function resolveEntityReferences(User $user, array $llmReferences, array &$annotations): array
    {
        if (empty($llmReferences)) {
            return [];
        }

        $names = array_keys($llmReferences);

        $profiles = OrgAgentProfile::forUser($user->id)
            ->active()
            ->whereIn('name', $names)
            ->get(['id', 'name']);

        $resolved = [];
        $profilesByName = [];

        foreach ($profiles as $profile) {
            $profilesByName[$profile->name][] = $profile;
        }

        foreach ($names as $name) {
            $matching = $profilesByName[$name] ?? [];

            if (count($matching) === 1) {
                $resolved[$name] = $matching[0]->id;
            } elseif (count($matching) > 1) {
                // Ambiguous — multiple profiles with same name
                $resolved[$name] = $matching[0]->id;
                $annotations[] = [
                    'message' => "Multiple agent profiles found with name '{$name}'. Using the first match.",
                    'type' => 'ambiguity',
                    'char_offset_start' => null,
                    'char_offset_end' => null,
                    'suggestion' => 'Use a unique agent name or role_slug to disambiguate.',
                ];
            }
            // If no match found, the LLM's placeholder stays as-is (unresolved)
        }

        return $resolved;
    }

    /**
     * Validate that rituals and councils have explicit participant references.
     *
     * Withholds entities with missing participants from the changeset and adds annotations.
     *
     * @return array{0: array, 1: array} [filtered changeset, updated annotations]
     */
    private function validateParticipantCompleteness(array $changeset, array $annotations): array
    {
        $filtered = [];

        foreach ($changeset as $entry) {
            $entityType = $entry['entity_type'] ?? '';
            $operation = $entry['operation'] ?? '';
            $payload = $entry['payload'] ?? [];

            // Only validate add/update operations for rituals and councils
            if ($operation === NlOrgParseResult::OP_REMOVE) {
                $filtered[] = $entry;

                continue;
            }

            if ($entityType === NlOrgParseResult::TYPE_RITUAL_TEMPLATE) {
                $mappings = $payload['phase_role_mappings'] ?? [];
                if (empty($mappings)) {
                    $name = $payload['name'] ?? 'unnamed ritual';
                    $annotations[] = [
                        'message' => "Ritual '{$name}' requires explicit agent participants. Please specify which agents should participate.",
                        'type' => 'missing_participants',
                        'char_offset_start' => null,
                        'char_offset_end' => null,
                        'suggestion' => "Try: 'Add a {$name} ritual with [Agent A] and [Agent B] participating.'",
                    ];

                    continue; // Withhold from changeset
                }
            }

            if ($entityType === NlOrgParseResult::TYPE_COUNCIL_TEMPLATE) {
                $members = $payload['member_list'] ?? [];
                if (empty($members)) {
                    $name = $payload['name'] ?? 'unnamed council';
                    $annotations[] = [
                        'message' => "Council '{$name}' requires explicit agent members. Please specify which agents should participate.",
                        'type' => 'missing_participants',
                        'char_offset_start' => null,
                        'char_offset_end' => null,
                        'suggestion' => "Try: 'Add a {$name} council with [Agent A] and [Agent B] as members.'",
                    ];

                    continue; // Withhold from changeset
                }
            }

            $filtered[] = $entry;
        }

        return [$filtered, $annotations];
    }

    /**
     * Log with redacted input: first 80 chars + SHA-256 hash.
     * Follows NlScheduleParserService pattern.
     */
    private function logRedacted(string $message, string $input, array $context = []): void
    {
        $redacted = strlen($input) > 80
            ? substr($input, 0, 80).'...'
            : $input;

        $context['input_preview'] = $redacted;
        $context['input_hash'] = hash('sha256', $input);

        Log::info("[NlOrgParser] {$message}", $context);
    }
}
