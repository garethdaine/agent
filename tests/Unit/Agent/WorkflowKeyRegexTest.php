<?php

declare(strict_types=1);

namespace Tests\Unit\Agent;

use App\Support\Agent\WorkflowKey;
use Tests\TestCase;

class WorkflowKeyRegexTest extends TestCase
{
    public function test_valid_keys_match_canonical_regex(): void
    {
        $validKeys = [
            'eng.repo-analysis.v1',
            'eng.code-implementation.v2',
            'eng.pr_quality-gate.v10',
            'a.b-c_d.v999',
            'workflow.v1',
        ];

        foreach ($validKeys as $key) {
            $this->assertTrue(WorkflowKey::isValid($key), sprintf('Expected key to be valid: %s', $key));
        }
    }

    public function test_invalid_keys_fail_canonical_regex(): void
    {
        $invalidKeys = [
            'Eng.repo-analysis.v1',
            'eng.repo-analysis',
            '',
            'eng..repo.v1',
            'eng_repo_v1',
            'eng/repo-analysis.v1',
            'eng.repo-analysis.v0',
            'eng.repo-analysis.v01',
        ];

        foreach ($invalidKeys as $key) {
            $this->assertFalse(WorkflowKey::isValid($key), sprintf('Expected key to be invalid: %s', $key));
        }
    }
}
