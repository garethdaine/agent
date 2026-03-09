<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Org\OrgExecuteRitualJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgExecuteRitualJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(OrgExecuteRitualJob::class));
    }

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            is_subclass_of(OrgExecuteRitualJob::class, ShouldQueue::class)
            || in_array(ShouldQueue::class, class_implements(OrgExecuteRitualJob::class) ?: [])
        );
    }
}
