<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Jobs\Skills\ValidateSkillJob;
use Tests\TestCase;

class ValidateSkillJobTest extends TestCase
{
    public function test_job_dispatches_on_skill_validation_queue(): void
    {
        $job = new ValidateSkillJob(
            filePath: '/tmp/test.skill',
            teamId: 1,
            userId: 1,
            source: 'upload',
        );

        $this->assertEquals('skill-validation', $job->queue);
    }

    public function test_job_retries_configuration(): void
    {
        $job = new ValidateSkillJob(
            filePath: '/tmp/test.skill',
            teamId: 1,
            userId: 1,
            source: 'upload',
        );

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([10, 30, 60], $job->backoff);
    }
}
