<?php

declare(strict_types=1);

namespace App\Support\NlOrg;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

final class NlOrgParseResult implements Arrayable, JsonSerializable
{
    public const TYPE_AGENT_PROFILE = 'org_agent_profile';

    public const TYPE_REPORTING_EDGE = 'org_reporting_edge';

    public const TYPE_RITUAL_TEMPLATE = 'org_ritual_template';

    public const TYPE_COUNCIL_TEMPLATE = 'org_council_template';

    public const TYPE_COST_LEDGER = 'org_cost_ledger';

    public const OP_ADD = 'add';

    public const OP_UPDATE = 'update';

    public const OP_REMOVE = 'remove';

    public function __construct(
        public readonly float $confidence,
        public readonly array $changeset,
        public readonly array $annotations,
        public readonly array $resolvedReferences,
        public readonly ?string $error = null,
    ) {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException('Confidence must be between 0.0 and 1.0');
        }
    }

    public function toArray(): array
    {
        return [
            'confidence' => $this->confidence,
            'changeset' => $this->changeset,
            'annotations' => $this->annotations,
            'resolved_references' => $this->resolvedReferences,
            'error' => $this->error,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence >= config('agent.nl_org.confidence_threshold', 0.7);
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getChangesetByEntityType(string $type): array
    {
        return array_values(array_filter(
            $this->changeset,
            static fn (array $entry): bool => $entry['entity_type'] === $type,
        ));
    }
}
