<?php

declare(strict_types=1);

namespace App\Support\NlSchedule;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class ParseResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $cronExpression,
        public readonly string $timezone,
        public readonly string $explanation,
        public readonly ?array $activeHours,
        public readonly array $nextRuns,
        public readonly float $confidence,
        public readonly string $parserPath,
        public readonly bool $ambiguous,
    ) {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException('Confidence must be between 0.0 and 1.0');
        }
        if (! in_array($parserPath, ['rule_based', 'llm_fallback'], true)) {
            throw new \InvalidArgumentException('Invalid parser path');
        }
    }

    public function toArray(): array
    {
        return [
            'cron_expression' => $this->cronExpression,
            'timezone' => $this->timezone,
            'explanation' => $this->explanation,
            'active_hours' => $this->activeHours,
            'next_runs' => $this->nextRuns,
            'confidence' => $this->confidence,
            'parser_path' => $this->parserPath,
            'ambiguous' => $this->ambiguous,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence >= config('agent.nl_parse.confidence_threshold', 0.75);
    }
}
