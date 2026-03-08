<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Runtime;

use App\Enums\Runtime\RuntimeToolCallStatus;
use App\Models\Runtime\RuntimeApproval;
use App\Models\Runtime\RuntimeToolCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeToolCallTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_call_casts_arguments_to_array(): void
    {
        $toolCall = RuntimeToolCall::factory()->create([
            'arguments_json' => ['path' => '/tmp/test.txt', 'content' => 'hello'],
        ]);

        $this->assertIsArray($toolCall->arguments_json);
        $this->assertEquals('/tmp/test.txt', $toolCall->arguments_json['path']);
    }

    public function test_tool_call_has_one_approval(): void
    {
        $toolCall = RuntimeToolCall::factory()->create(['requires_approval' => true]);
        RuntimeApproval::factory()->create(['runtime_tool_call_id' => $toolCall->id]);

        $this->assertInstanceOf(RuntimeApproval::class, $toolCall->approval);
    }

    public function test_tool_call_status_cast(): void
    {
        $toolCall = RuntimeToolCall::factory()->create(['status' => 'pending_approval']);
        $this->assertEquals(RuntimeToolCallStatus::PendingApproval, $toolCall->status);
    }
}
