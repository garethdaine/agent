<?php

namespace App\Support\Agent;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Support\Compliance\LessonsManager;

class StarPreambleGenerator
{
    private const PREAMBLE_TEMPLATE = <<<'MD'
## Pre-Execution Goal Articulation (Required)

Before beginning any work, complete each section below. Do not skip or abbreviate.

### SITUATION
What is the current state? What exists, what doesn't? What constraints apply?

### TASK
What specifically must be true when this work is complete? State the end-state, not the process.

### ACTION
What steps will achieve that end state? List them in order.

### RESULT
How will completion be verified? What evidence proves success?

---

Now proceed with the work described below.
MD;

    public function __construct(
        private readonly LessonsManager $lessonsManager
    ) {}

    public function generate(AgentJob $job, AgentJobRun $run): string
    {
        $preamble = self::PREAMBLE_TEMPLATE;

        // Enrich with lessons if available
        $lessons = $this->getRelevantLessons($job);
        if (! empty($lessons)) {
            $lessonsText = $this->formatLessons($lessons);
            $preamble = $this->injectLessonsIntoTemplate($preamble, $lessonsText);
        }

        return $preamble;
    }

    public function isEnabled(AgentJob $job): bool
    {
        // Per-job override takes precedence
        if ($job->star_preamble_enabled !== null) {
            return $job->star_preamble_enabled;
        }

        // Fall back to global config
        return config('agent.star_preamble.enabled', true);
    }

    public function assignAbGroup(AgentJob $job): ?string
    {
        if (! config('agent.star_preamble.ab_test_enabled', false)) {
            return null;
        }

        $treatmentPercent = config('agent.star_preamble.ab_test_treatment_percent', 50);
        $random = mt_rand(1, 100);

        return $random <= $treatmentPercent ? 'treatment' : 'control';
    }

    private function getRelevantLessons(AgentJob $job): array
    {
        $projectDir = base_path();
        $category = $job->task_category ?? null;
        $runnerType = $job->runner_type;

        return $this->lessonsManager->queryLessons(
            $projectDir,
            null,
            $category,
            500, // Token budget for lessons in preamble
            $runnerType
        );
    }

    private function formatLessons(array $lessons): string
    {
        $lines = ['**Learned Guardrails:**'];
        foreach ($lessons as $lesson) {
            $content = trim($lesson['content']);
            // Extract just the main lesson text, not the metadata
            $lines[] = '- '.$this->extractLessonCore($content);
        }

        return implode("\n", $lines);
    }

    private function extractLessonCore(string $content): string
    {
        // Remove metadata lines (starting with **Context:**)
        $parts = explode('**Context:**', $content);

        return trim($parts[0]);
    }

    private function injectLessonsIntoTemplate(string $preamble, string $lessonsText): string
    {
        // Insert lessons after ACTION section
        $marker = '### RESULT';

        return str_replace(
            $marker,
            $lessonsText."\n\n".$marker,
            $preamble
        );
    }
}
