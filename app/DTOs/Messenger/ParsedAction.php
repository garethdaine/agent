<?php

namespace App\DTOs\Messenger;

use App\Enums\Messenger\ChatActionType;

final readonly class ParsedAction
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public ChatActionType $type,
        public array $parameters,
        public float $confidence,
        public bool $requiresConfirmation,
        public string $rawIntent,
        public ?string $clarificationNeeded = null,
    ) {}

    /**
     * Create a ParsedAction that needs clarification.
     */
    public static function needsClarification(string $rawIntent, string $clarification): self
    {
        return new self(
            type: ChatActionType::JOBS_LIST, // Placeholder - won't be used
            parameters: [],
            confidence: 0.0,
            requiresConfirmation: false,
            rawIntent: $rawIntent,
            clarificationNeeded: $clarification,
        );
    }

    /**
     * Check if this parsed action needs clarification before execution.
     */
    public function needsClarificationResponse(): bool
    {
        return $this->clarificationNeeded !== null;
    }

    /**
     * Check if the confidence level is high enough for execution.
     */
    public function hasHighConfidence(float $threshold = 0.7): bool
    {
        return $this->confidence >= $threshold;
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->type->value,
            'parameters' => $this->parameters,
            'confidence' => $this->confidence,
            'requires_confirmation' => $this->requiresConfirmation,
            'raw_intent' => $this->rawIntent,
            'clarification_needed' => $this->clarificationNeeded,
        ];
    }

    /**
     * Create from AI response array.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config  Connector account config
     */
    public static function fromArray(array $data, array $config = []): self
    {
        $type = ChatActionType::from($data['action']);

        return new self(
            type: $type,
            parameters: $data['parameters'] ?? [],
            confidence: (float) ($data['confidence'] ?? 0.0),
            requiresConfirmation: $type->requiresConfirmation($config),
            rawIntent: $data['raw_intent'] ?? '',
            clarificationNeeded: $data['clarification_needed'] ?? null,
        );
    }
}
