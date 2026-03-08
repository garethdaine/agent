<?php

declare(strict_types=1);

namespace App\Support\Compliance;

use App\Support\Compliance\DTOs\ComplexityResult;

/**
 * Classifies task complexity as 'simple' or 'non_trivial' based on heuristics and explicit overrides.
 *
 * Heuristics evaluate file count, LOC count, and directory count against configurable thresholds.
 * Explicit overrides (force_simple, force_non_trivial) take precedence over heuristics.
 */
class ComplexityClassifier
{
    /**
     * @param  array{file_count: int, loc_count: int, directory_count: int}  $thresholds
     */
    public function __construct(
        private readonly array $thresholds
    ) {}

    /**
     * Create a classifier instance using configuration thresholds.
     */
    public static function fromConfig(): self
    {
        /** @var array{file_count: int, loc_count: int, directory_count: int} $thresholds */
        $thresholds = config('agent.compliance.complexity_thresholds', [
            'file_count' => 3,
            'loc_count' => 50,
            'directory_count' => 2,
        ]);

        return new self($thresholds);
    }

    /**
     * Classify task complexity based on context metrics and overrides.
     *
     * @param  array<string, mixed>  $context  Context containing file_count, loc_count, directory_count, and optional complexity_override
     */
    public function classify(array $context): ComplexityResult
    {
        // Check for explicit override first - overrides take precedence
        $override = $context['complexity_override'] ?? null;

        if ($override === 'force_simple') {
            return new ComplexityResult('simple', [], true);
        }

        if ($override === 'force_non_trivial') {
            return new ComplexityResult('non_trivial', ['override'], true);
        }

        // Apply heuristics - treat missing values as zero (safe default)
        $evidence = [];

        $fileCount = (int) ($context['file_count'] ?? 0);
        $locCount = (int) ($context['loc_count'] ?? 0);
        $directoryCount = (int) ($context['directory_count'] ?? 0);

        $fileThreshold = $this->thresholds['file_count'];
        $locThreshold = $this->thresholds['loc_count'];
        $directoryThreshold = $this->thresholds['directory_count'];

        // Only exceed threshold triggers non_trivial (at-threshold is simple)
        if ($fileCount > $fileThreshold) {
            $evidence[] = "file_count:{$fileCount}>{$fileThreshold}";
        }

        if ($locCount > $locThreshold) {
            $evidence[] = "loc_count:{$locCount}>{$locThreshold}";
        }

        if ($directoryCount > $directoryThreshold) {
            $evidence[] = "directory_count:{$directoryCount}>{$directoryThreshold}";
        }

        $classification = count($evidence) > 0 ? 'non_trivial' : 'simple';

        return new ComplexityResult($classification, $evidence);
    }
}
