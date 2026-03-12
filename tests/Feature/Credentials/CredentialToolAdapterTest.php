<?php

declare(strict_types=1);

use App\DTOs\Runtime\RuntimeContext;
use App\Enums\Runtime\RuntimeMode;
use App\Models\CredentialVault;
use App\Models\Runtime\RuntimeSession;
use App\Models\Runtime\RuntimeTurn;
use App\Models\User;
use App\Services\Runtime\Adapters\CredentialToolAdapter;

// ─── Adapter contract ───

it('has name "vault"', function () {
    $adapter = app(CredentialToolAdapter::class);
    expect($adapter->name())->toBe('vault');
});

it('returns schema with all four operations', function () {
    $adapter = app(CredentialToolAdapter::class);
    $schema = $adapter->schema();

    expect($schema['operations'])->toBe([
        'list_credentials',
        'get_credential',
        'get_login_for_url',
        'get_api_key',
    ]);
    expect($schema['parameters'])->toHaveKeys(['operation', 'provider', 'name', 'url', 'type', 'search']);
    expect($schema['description'])->toContain('Credential vault');
});

it('requires vault_read capability for list_credentials', function () {
    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Safe);

    // Safe mode includes vault_read, so list should be authorized
    expect($adapter->authorize($context, ['operation' => 'list_credentials']))->toBeTrue();
});

it('requires vault_retrieve capability for get_credential', function () {
    $adapter = app(CredentialToolAdapter::class);

    // Safe mode does NOT have vault_retrieve
    $safeContext = createVaultContext(RuntimeMode::Safe);
    expect($adapter->authorize($safeContext, ['operation' => 'get_credential']))->toBeFalse();
    expect($adapter->authorize($safeContext, ['operation' => 'get_login_for_url']))->toBeFalse();
    expect($adapter->authorize($safeContext, ['operation' => 'get_api_key']))->toBeFalse();

    // Standard mode HAS vault_retrieve
    $standardContext = createVaultContext(RuntimeMode::Standard);
    expect($adapter->authorize($standardContext, ['operation' => 'get_credential']))->toBeTrue();
    expect($adapter->authorize($standardContext, ['operation' => 'get_login_for_url']))->toBeTrue();
    expect($adapter->authorize($standardContext, ['operation' => 'get_api_key']))->toBeTrue();
});

it('allows all vault operations in full mode', function () {
    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Full);

    expect($adapter->authorize($context, ['operation' => 'list_credentials']))->toBeTrue();
    expect($adapter->authorize($context, ['operation' => 'get_credential']))->toBeTrue();
    expect($adapter->authorize($context, ['operation' => 'get_login_for_url']))->toBeTrue();
    expect($adapter->authorize($context, ['operation' => 'get_api_key']))->toBeTrue();
});

// ─── Execution: list_credentials ───

it('lists credentials from vault', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->login()->for($user)->create(['name' => 'GitHub Login', 'provider' => 'github']);
    CredentialVault::factory()->apiKey()->for($user)->create(['name' => 'Stripe Key', 'provider' => 'stripe']);

    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, ['operation' => 'list_credentials']);

    expect($result->success)->toBeTrue();
    expect($result->data['credentials'])->toHaveCount(2);
});

it('filters listed credentials by type', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->login()->for($user)->create(['name' => 'Login Cred', 'provider' => 'github']);
    CredentialVault::factory()->apiKey()->for($user)->create(['name' => 'API Cred', 'provider' => 'stripe']);

    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, ['operation' => 'list_credentials', 'type' => 'login']);

    expect($result->success)->toBeTrue();
    expect($result->data['credentials'])->toHaveCount(1);
    expect($result->data['credentials'][0]['type'])->toBe('login');
});

it('returns empty list when user has no credentials', function () {
    $user = User::factory()->create();
    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, ['operation' => 'list_credentials']);

    expect($result->success)->toBeTrue();
    expect($result->data['credentials'])->toBeEmpty();
});

// ─── Execution: get_login_for_url ───

it('retrieves login for matching URL', function () {
    $user = User::factory()->create();
    $cred = CredentialVault::factory()->login()->autonomous()->for($user)->create([
        'name' => 'X Account',
        'provider' => 'x.com',
        'url' => 'https://x.com',
    ]);

    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, [
        'operation' => 'get_login_for_url',
        'url' => 'https://x.com/login',
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data)->toHaveKey('credentials');
    expect($result->data['credential_type'])->toBe('login');
});

it('fails gracefully for unmatched URL', function () {
    $user = User::factory()->create();
    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, [
        'operation' => 'get_login_for_url',
        'url' => 'https://no-match.example.com',
    ]);

    expect($result->success)->toBeFalse();
});

// ─── Execution: get_api_key ───

it('retrieves api key by provider', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->apiKey()->autonomous()->for($user)->create([
        'name' => 'Stripe Production',
        'provider' => 'stripe',
    ]);

    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, [
        'operation' => 'get_api_key',
        'provider' => 'stripe',
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data)->toHaveKey('credentials');
    expect($result->data['credential_type'])->toBe('api_key');
});

// ─── Execution: get_credential ───

it('retrieves credential by provider name', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->sshKey()->autonomous()->for($user)->create([
        'name' => 'Production SSH',
        'provider' => 'aws',
    ]);

    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, [
        'operation' => 'get_credential',
        'provider' => 'aws',
        'type' => 'ssh_key',
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data['credential_type'])->toBe('ssh_key');
});

// ─── Error handling ───

it('returns failure for unknown operation', function () {
    $user = User::factory()->create();
    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, ['operation' => 'hack_the_planet']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('Unknown vault operation');
});

it('returns failure when url is missing for get_login_for_url', function () {
    $user = User::factory()->create();
    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, ['operation' => 'get_login_for_url']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('URL is required');
});

it('returns failure when provider is missing for get_api_key', function () {
    $user = User::factory()->create();
    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, ['operation' => 'get_api_key']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('Provider is required');
});

// ─── Approval policy enforcement ───

it('denies access to restricted credentials regardless of trust score', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->login()->restricted()->for($user)->create([
        'name' => 'Restricted Login',
        'provider' => 'bank',
        'url' => 'https://bank.example.com',
    ]);

    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, [
        'operation' => 'get_login_for_url',
        'url' => 'https://bank.example.com/login',
    ]);

    // Restricted always needs confirmation or denies — not a success
    expect($result->success)->toBeFalse();
});

it('excludes expired credentials from retrieval', function () {
    $user = User::factory()->create();
    CredentialVault::factory()->apiKey()->autonomous()->expired()->for($user)->create([
        'name' => 'Expired Key',
        'provider' => 'old-service',
    ]);

    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user);

    $result = $adapter->execute($context, [
        'operation' => 'get_api_key',
        'provider' => 'old-service',
    ]);

    // Expired credentials should not be returned
    expect($result->success)->toBeFalse();
});

// ─── User isolation ───

it('does not leak credentials across users', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    CredentialVault::factory()->login()->for($user1)->create([
        'name' => 'User 1 Secret',
        'provider' => 'github',
    ]);

    $adapter = app(CredentialToolAdapter::class);
    $context = createVaultContext(RuntimeMode::Standard, $user2);

    $result = $adapter->execute($context, ['operation' => 'list_credentials']);

    expect($result->success)->toBeTrue();
    expect($result->data['credentials'])->toBeEmpty();
});

// ─── Helpers ───

function createVaultContext(RuntimeMode $mode, ?User $user = null): RuntimeContext
{
    $user = $user ?? Mockery::mock(User::class)->makePartial();

    return new RuntimeContext(
        session: Mockery::mock(RuntimeSession::class)->makePartial(),
        turn: Mockery::mock(RuntimeTurn::class)->makePartial(),
        user: $user,
        mode: $mode,
        policy: [],
        workspaceRoot: null,
    );
}
