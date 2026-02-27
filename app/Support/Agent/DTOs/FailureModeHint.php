<?php

namespace App\Support\Agent\DTOs;

readonly class FailureModeHint
{
    public function __construct(
        public int $type,
        public string $confidence,
        public string $description,
        public ?string $suggestedLesson = null,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'confidence' => $this->confidence,
            'description' => $this->description,
            'suggested_lesson' => $this->suggestedLesson,
        ];
    }
}
