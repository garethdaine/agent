<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger\SlashCommands;

use App\Messenger\SlashCommands\ContextCommandHandler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ContextCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function context_list_returns_success_without_session(): void
    {
        $user = User::factory()->create();
        $handler = app(ContextCommandHandler::class);
        $result = $handler->handle($user, ['list'], null, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Active runtime sessions', $result->message);
    }

    #[Test]
    public function context_json_returns_machine_readable_output(): void
    {
        $user = User::factory()->create();
        $handler = app(ContextCommandHandler::class);
        $result = $handler->handle($user, ['json'], null, null);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('user_active_sessions', $result->data);
        $this->assertArrayHasKey('user_total_input_tokens', $result->data);
    }
}
