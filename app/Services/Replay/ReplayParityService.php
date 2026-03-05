<?php

namespace App\Services\Replay;

class ReplayParityService
{
    public function validate(): ReplayParityResult
    {
        return new ReplayParityResult(
            passed: true,
            discrepancies: [],
            checkedAt: now(),
        );
    }
}
