<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\NlOrgParseAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NlOrgParseAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_with_all_attributes(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'Create a team with a lead and two developers',
            'parsed_result' => ['changeset' => [], 'annotations' => []],
            'confidence' => 0.85,
            'applied_at' => now(),
        ]);

        $this->assertNotNull($attempt->id);
        $this->assertEquals($user->id, $attempt->user_id);
        $this->assertEquals('Create a team with a lead and two developers', $attempt->raw_input);
        $this->assertIsArray($attempt->parsed_result);
        $this->assertIsFloat($attempt->confidence);
        $this->assertEquals(0.85, $attempt->confidence);
        $this->assertNotNull($attempt->applied_at);
    }

    public function test_can_create_with_nullable_fields(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'Some input',
        ]);

        $this->assertNull($attempt->parsed_result);
        $this->assertNull($attempt->confidence);
        $this->assertNull($attempt->applied_at);
    }

    public function test_user_relationship_returns_user(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'test input',
        ]);

        $this->assertInstanceOf(User::class, $attempt->user);
        $this->assertEquals($user->id, $attempt->user->id);
    }

    public function test_scope_for_user_filters_by_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        NlOrgParseAttempt::create(['user_id' => $user1->id, 'raw_input' => 'input 1']);
        NlOrgParseAttempt::create(['user_id' => $user1->id, 'raw_input' => 'input 2']);
        NlOrgParseAttempt::create(['user_id' => $user2->id, 'raw_input' => 'input 3']);

        $results = NlOrgParseAttempt::forUser($user1->id)->get();

        $this->assertCount(2, $results);
        $results->each(fn ($attempt) => $this->assertEquals($user1->id, $attempt->user_id));
    }

    public function test_scope_unapplied_returns_only_unapplied(): void
    {
        $user = User::factory()->create();

        NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'applied input',
            'applied_at' => now(),
        ]);
        NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'unapplied input 1',
        ]);
        NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'unapplied input 2',
        ]);

        $results = NlOrgParseAttempt::unapplied()->get();

        $this->assertCount(2, $results);
        $results->each(fn ($attempt) => $this->assertNull($attempt->applied_at));
    }

    public function test_parsed_result_is_cast_to_array(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'test',
            'parsed_result' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $fresh = $attempt->fresh();
        $this->assertIsArray($fresh->parsed_result);
        $this->assertEquals('value', $fresh->parsed_result['key']);
        $this->assertEquals(1, $fresh->parsed_result['nested']['a']);
    }

    public function test_confidence_is_cast_to_float(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'test',
            'confidence' => '0.75',
        ]);

        $fresh = $attempt->fresh();
        $this->assertIsFloat($fresh->confidence);
        $this->assertEquals(0.75, $fresh->confidence);
    }

    public function test_applied_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $now = now();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'test',
            'applied_at' => $now,
        ]);

        $fresh = $attempt->fresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->applied_at);
    }

    public function test_uses_uuid_primary_key(): void
    {
        $user = User::factory()->create();

        $attempt = NlOrgParseAttempt::create([
            'user_id' => $user->id,
            'raw_input' => 'test',
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $attempt->id
        );
    }
}
