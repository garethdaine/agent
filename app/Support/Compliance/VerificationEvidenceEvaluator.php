<?php

declare(strict_types=1);

namespace App\Support\Compliance;

use App\Enums\TaskCategory;
use App\Support\Compliance\DTOs\VerificationResult;

class VerificationEvidenceEvaluator
{
    /**
     * Evidence requirements by task category.
     *
     * - feature: automated_check + ai_critic
     * - bugfix: automated_check + ai_critic + human_approval
     * - refactor: automated_check only
     * - docs: no verification required
     * - test: no verification required
     * - custom: inherits feature requirements
     *
     * @var array<string, array<int, string>>
     */
    private const REQUIREMENTS = [
        'feature' => ['automated_check', 'ai_critic'],
        'bugfix' => ['automated_check', 'ai_critic', 'human_approval'],
        'refactor' => ['automated_check'],
        'docs' => [],
        'test' => [],
        'custom' => ['automated_check', 'ai_critic'],
    ];

    /**
     * Evaluate whether verification evidence satisfies category requirements.
     *
     * @param  TaskCategory  $category  The task category
     * @param  array<string, mixed>  $evidence  Evidence array from metadata_json.verification_evidence
     */
    public function evaluate(TaskCategory $category, array $evidence): VerificationResult
    {
        $requirements = $this->getRequirements($category);

        if (empty($requirements)) {
            return new VerificationResult(true, [], []);
        }

        $present = [];
        $missing = [];

        foreach ($requirements as $requirement) {
            if ($this->hasEvidence($evidence, $requirement)) {
                $present[] = $requirement;
            } else {
                $missing[] = $requirement;
            }
        }

        return new VerificationResult(
            satisfied: empty($missing),
            missing: $missing,
            present: $present
        );
    }

    /**
     * Get the evidence requirements for a given task category.
     *
     * @return array<int, string>
     */
    public function getRequirements(TaskCategory $category): array
    {
        return self::REQUIREMENTS[$category->value] ?? self::REQUIREMENTS['custom'];
    }

    /**
     * Check if evidence is present and truthy for a given requirement.
     *
     * @param  array<string, mixed>  $evidence
     */
    private function hasEvidence(array $evidence, string $requirement): bool
    {
        if (! array_key_exists($requirement, $evidence)) {
            return false;
        }

        $value = $evidence[$requirement];

        // Treat falsy values as missing evidence
        return (bool) $value;
    }
}
