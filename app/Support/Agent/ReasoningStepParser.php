<?php

declare(strict_types=1);

namespace App\Support\Agent;

class ReasoningStepParser
{
    private const MAX_CONTENT_LENGTH = 2000;

    private const STEP_PATTERN = '/^###\s+(SITUATION|TASK|ACTION|RESULT)\s*$/im';

    private ?string $currentStep = null;

    private array $stepContents = [
        'situation' => '',
        'task' => '',
        'action' => '',
        'result' => '',
    ];

    private array $stepTimestamps = [];

    public function parse(string $chunk): ?string
    {
        // Check for STAR section headers
        if (preg_match(self::STEP_PATTERN, $chunk, $matches)) {
            $newStep = strtolower($matches[1]);
            $this->currentStep = $newStep;
            $this->stepTimestamps[$newStep] = now()->toIso8601String();

            return $this->currentStep;
        }

        // Check for delimiter marking end of STAR preamble
        if (str_contains($chunk, '---') && $this->currentStep === 'result') {
            $this->currentStep = null;

            return null;
        }

        // Append content to current step if active
        if ($this->currentStep !== null) {
            $this->appendContent($this->currentStep, $chunk);

            return $this->currentStep;
        }

        return null;
    }

    public function getSummary(): array
    {
        $steps = [];
        foreach (['situation', 'task', 'action', 'result'] as $step) {
            $content = $this->stepContents[$step];
            $steps[$step] = [
                'completed' => isset($this->stepTimestamps[$step]),
                'content' => substr($content, 0, self::MAX_CONTENT_LENGTH),
                'timestamp' => $this->stepTimestamps[$step] ?? null,
            ];
        }

        return [
            'steps' => $steps,
            'all_completed' => $this->allStepsCompleted(),
        ];
    }

    public function reset(): void
    {
        $this->currentStep = null;
        $this->stepContents = [
            'situation' => '',
            'task' => '',
            'action' => '',
            'result' => '',
        ];
        $this->stepTimestamps = [];
    }

    private function appendContent(string $step, string $content): void
    {
        if (strlen($this->stepContents[$step]) < self::MAX_CONTENT_LENGTH) {
            $this->stepContents[$step] .= $content;
        }
    }

    private function allStepsCompleted(): bool
    {
        foreach (['situation', 'task', 'action', 'result'] as $step) {
            if (! isset($this->stepTimestamps[$step])) {
                return false;
            }
        }

        return true;
    }
}
