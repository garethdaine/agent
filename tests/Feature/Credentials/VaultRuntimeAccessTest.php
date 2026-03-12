<?php

declare(strict_types=1);

use App\Models\CredentialVault;
use App\Models\User;
use App\Services\Credentials\VaultRuntimeTokenService;

// ─── Token Service ───

it('generates and validates a valid token', function () {
    $user = User::factory()->create();
    $service = app(VaultRuntimeTokenService::class);

    $token = $service->generate($user, 'session-123', 3600);

    $payload = $service->validate($token);

    expect($payload)->not->toBeNull();
    expect($payload['user_id'])->toBe($user->id);
    expect($payload['session_id'])->toBe('session-123');
});

it('rejects a token with invalid signature', function () {
    $user = User::factory()->create();
    $service = app(VaultRuntimeTokenService::class);

    $token = $service->generate($user, 'session-123', 3600);

    // Tamper with the signature
    $parts = explode('.', $token);
    $parts[1] = str_repeat('a', strlen($parts[1]));
    $tamperedToken = implode('.', $parts);

    $payload = $service->validate($tamperedToken);

    expect($payload)->toBeNull();
});

it('rejects an expired token', function () {
    $user = User::factory()->create();
    $service = app(VaultRuntimeTokenService::class);

    // Generate a token with 0 TTL (already expired)
    $token = $service->generate($user, 'session-123', -1);

    $payload = $service->validate($token);

    expect($payload)->toBeNull();
});

it('rejects a completely malformed token', function () {
    $service = app(VaultRuntimeTokenService::class);

    expect($service->validate(''))->toBeNull();
    expect($service->validate('not-a-token'))->toBeNull();
    expect($service->validate('abc.def.ghi'))->toBeNull();
});

// ─── Vault Runtime Controller ───

it('returns 401 for missing vault token', function () {
    $response = $this->postJson('/agent/api/v1/runtime/vault/list_credentials');

    $response->assertStatus(401);
    $response->assertJson(['success' => false, 'error' => 'Invalid or expired vault token.']);
});

it('returns 401 for invalid vault token', function () {
    $response = $this->postJson('/agent/api/v1/runtime/vault/list_credentials', [], [
        'Authorization' => 'Bearer invalid-token-here',
    ]);

    $response->assertStatus(401);
});

it('returns 400 for unknown vault operation', function () {
    $user = User::factory()->create();
    $token = app(VaultRuntimeTokenService::class)->generate($user, 'test-session', 3600);

    $response = $this->postJson('/agent/api/v1/runtime/vault/hack_planet', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(400);
    $response->assertJson(['success' => false]);
});

it('lists credentials via runtime vault endpoint', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->login()->for($user)->create(['name' => 'Test Login', 'provider' => 'github']);
    CredentialVault::factory()->apiKey()->for($user)->create(['name' => 'Test Key', 'provider' => 'stripe']);

    $token = app(VaultRuntimeTokenService::class)->generate($user, 'test-session', 3600);

    $response = $this->postJson('/agent/api/v1/runtime/vault/list_credentials', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    expect($response->json('data.credentials'))->toHaveCount(2);
});

it('filters credentials by type via runtime vault endpoint', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->login()->for($user)->create(['name' => 'Login', 'provider' => 'github']);
    CredentialVault::factory()->apiKey()->for($user)->create(['name' => 'Key', 'provider' => 'stripe']);

    $token = app(VaultRuntimeTokenService::class)->generate($user, 'test-session', 3600);

    $response = $this->postJson('/agent/api/v1/runtime/vault/list_credentials', ['type' => 'login'], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);
    expect($response->json('data.credentials'))->toHaveCount(1);
    expect($response->json('data.credentials.0.type'))->toBe('login');
});

it('retrieves login for URL via runtime vault endpoint', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->login()->autonomous()->for($user)->create([
        'name' => 'X Account',
        'provider' => 'x.com',
        'url' => 'https://x.com',
    ]);

    $token = app(VaultRuntimeTokenService::class)->generate($user, 'test-session', 3600);

    $response = $this->postJson('/agent/api/v1/runtime/vault/get_login_for_url', [
        'url' => 'https://x.com/login',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    expect($response->json('data.credential_type'))->toBe('login');
});

it('retrieves api key by provider via runtime vault endpoint', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->apiKey()->autonomous()->for($user)->create([
        'name' => 'Stripe Key',
        'provider' => 'stripe',
    ]);

    $token = app(VaultRuntimeTokenService::class)->generate($user, 'test-session', 3600);

    $response = $this->postJson('/agent/api/v1/runtime/vault/get_api_key', [
        'provider' => 'stripe',
    ], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    expect($response->json('data.credential_type'))->toBe('api_key');
});

it('does not leak credentials across users via runtime vault', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    CredentialVault::factory()->login()->for($user1)->create([
        'name' => 'User1 Secret',
        'provider' => 'github',
    ]);

    // Token for user2 should not see user1's credentials
    $token = app(VaultRuntimeTokenService::class)->generate($user2, 'test-session', 3600);

    $response = $this->postJson('/agent/api/v1/runtime/vault/list_credentials', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);
    expect($response->json('data.credentials'))->toBeEmpty();
});

it('returns empty list when user has no credentials via runtime vault', function () {
    $user = User::factory()->create();
    $token = app(VaultRuntimeTokenService::class)->generate($user, 'test-session', 3600);

    $response = $this->postJson('/agent/api/v1/runtime/vault/list_credentials', [], [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    expect($response->json('data.credentials'))->toBeEmpty();
});

// ─── System Prompt Vault Section ───

it('builds vault section in system prompt when user has credentials', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->login()->for($user)->create([
        'name' => 'X Account',
        'provider' => 'x.com',
        'url' => 'https://x.com',
        'approval_mode' => 'autonomous',
    ]);
    CredentialVault::factory()->apiKey()->for($user)->create([
        'name' => 'Stripe Key',
        'provider' => 'stripe',
        'approval_mode' => 'supervised',
    ]);

    // Create a mock ConnectorAccount with the user
    $account = Mockery::mock(\App\Models\ConnectorAccount::class)->makePartial();
    $account->shouldReceive('getSoul')->andReturn([
        'name' => 'TestBot',
        'personality' => 'Helpful',
    ]);
    $account->shouldReceive('getAttribute')
        ->with('user')
        ->andReturn($user);

    $job = new \App\Jobs\Runtime\ProcessRuntimeTurnJob('session-1', 'test');
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('buildVaultPromptSection');
    $method->setAccessible(true);

    $section = $method->invoke($job, $account);

    expect($section)->not->toBeNull();
    expect($section)->toContain('Persistent Credential Vault');
    expect($section)->toContain('X Account');
    expect($section)->toContain('x.com');
    expect($section)->toContain('Stripe Key');
    expect($section)->toContain('stripe');
    expect($section)->toContain('VAULT_API_BASE');
    expect($section)->toContain('VAULT_TOKEN');
    expect($section)->toContain('[auto-approved]');
    expect($section)->toContain('[may need approval]');

    // Vault-first login flow
    expect($section)->toContain('VAULT-FIRST RULE');
    expect($section)->toContain('Login Workflow');
    expect($section)->toContain('get_login_for_url');
    expect($section)->toContain('don\'t have stored credentials');
});

it('returns null vault section when user has no credentials', function () {
    $user = User::factory()->create();

    $account = Mockery::mock(\App\Models\ConnectorAccount::class)->makePartial();
    $account->shouldReceive('getAttribute')
        ->with('user')
        ->andReturn($user);

    $job = new \App\Jobs\Runtime\ProcessRuntimeTurnJob('session-1', 'test');
    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('buildVaultPromptSection');
    $method->setAccessible(true);

    $section = $method->invoke($job, $account);

    expect($section)->toBeNull();
});
