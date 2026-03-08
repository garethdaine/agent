<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\NlParseAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NlParseCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_records_older_than_retention_period(): void
    {
        $user = User::factory()->create();

        // Old record (should be deleted)
        NlParseAttempt::forceCreate([
            'user_id' => $user->id,
            'input_text' => 'old record',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(100),
        ]);

        // Recent record (should be kept)
        NlParseAttempt::forceCreate([
            'user_id' => $user->id,
            'input_text' => 'recent record',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(30),
        ]);

        $this->artisan('nl-parse:cleanup')
            ->assertSuccessful();

        $this->assertDatabaseMissing('nl_parse_attempts', ['input_text' => 'old record']);
        $this->assertDatabaseHas('nl_parse_attempts', ['input_text' => 'recent record']);
    }

    public function test_logs_deletion_count(): void
    {
        $user = User::factory()->create();

        NlParseAttempt::forceCreate([
            'user_id' => $user->id,
            'input_text' => 'old',
            'timezone' => 'UTC',
            'parser_path' => 'rule_based',
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(100),
        ]);

        $this->artisan('nl-parse:cleanup')
            ->expectsOutputToContain('1')
            ->assertSuccessful();
    }

    public function test_handles_empty_table(): void
    {
        $this->artisan('nl-parse:cleanup')
            ->assertSuccessful();
    }
}
