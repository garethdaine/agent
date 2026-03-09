<?php

declare(strict_types=1);

namespace App\Actions\Delegation;

use App\Models\DelegationVerificationResult;

class ResolveVerificationResultAction
{
    public function find(int $taskId, int $verificationResultId): ?DelegationVerificationResult
    {
        /** @var DelegationVerificationResult|null */
        return DelegationVerificationResult::query()
            ->where('delegation_task_id', $taskId)
            ->where('id', $verificationResultId)
            ->first();
    }

    public function resolve(DelegationVerificationResult $verificationResult, string $verdict, ?array $evidence): DelegationVerificationResult
    {
        $verificationResult->update([
            'verdict' => $verdict,
            'evidence_json' => $evidence ?? $verificationResult->evidence_json,
            'finished_at' => now('UTC'),
        ]);

        return $verificationResult;
    }
}
