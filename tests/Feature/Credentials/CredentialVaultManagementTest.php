<?php

declare(strict_types=1);

use App\Models\CredentialUsageLog;
use App\Models\CredentialVault;
use App\Models\User;
use App\Services\Credentials\CredentialsManager;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->manager = app(CredentialsManager::class);
});

describe('store credential', function () {
    it('stores a login credential with encrypted secrets', function () {
        $credential = $this->manager->storeCredential($this->user, CredentialVault::TYPE_LOGIN, [
            'name' => 'My X.com Account',
            'provider' => 'x.com',
            'url' => 'https://x.com',
            'secrets' => ['username' => 'testuser', 'password' => 's3cret'],
            'approval_mode' => CredentialVault::APPROVAL_SUPERVISED,
        ]);

        expect($credential)->toBeInstanceOf(CredentialVault::class)
            ->and($credential->credential_type)->toBe(CredentialVault::TYPE_LOGIN)
            ->and($credential->name)->toBe('My X.com Account')
            ->and($credential->provider)->toBe('x.com')
            ->and($credential->url)->toBe('https://x.com')
            ->and($credential->approval_mode)->toBe(CredentialVault::APPROVAL_SUPERVISED);

        $payload = $credential->getDecryptedPayload();
        expect($payload)->toBe(['username' => 'testuser', 'password' => 's3cret']);
    });

    it('stores an API key credential', function () {
        $credential = $this->manager->storeCredential($this->user, CredentialVault::TYPE_API_KEY, [
            'name' => 'Stripe API',
            'provider' => 'stripe',
            'secrets' => ['api_key' => 'sk_test_123', 'api_secret' => 'whsec_456'],
            'metadata' => ['base_url' => 'https://api.stripe.com', 'auth_method' => 'bearer'],
        ]);

        expect($credential->credential_type)->toBe(CredentialVault::TYPE_API_KEY)
            ->and($credential->metadata)->toBe(['base_url' => 'https://api.stripe.com', 'auth_method' => 'bearer']);

        $payload = $credential->getDecryptedPayload();
        expect($payload['api_key'])->toBe('sk_test_123')
            ->and($payload['api_secret'])->toBe('whsec_456');
    });

    it('stores an SSH key credential', function () {
        $credential = $this->manager->storeCredential($this->user, CredentialVault::TYPE_SSH_KEY, [
            'name' => 'Production Server',
            'provider' => 'aws',
            'url' => 'ssh://prod.example.com:22',
            'secrets' => ['private_key' => 'ssh-key-data', 'passphrase' => 'mypass'],
            'metadata' => ['key_type' => 'ed25519', 'host' => 'prod.example.com', 'port' => 22],
        ]);

        expect($credential->credential_type)->toBe(CredentialVault::TYPE_SSH_KEY);
        $payload = $credential->getDecryptedPayload();
        expect($payload['private_key'])->toBe('ssh-key-data');
    });

    it('stores an OAuth token credential with expiry', function () {
        $expiresAt = now()->addHour();

        $credential = $this->manager->storeCredential($this->user, CredentialVault::TYPE_OAUTH_TOKEN, [
            'name' => 'Google OAuth',
            'provider' => 'google',
            'secrets' => ['access_token' => 'ya29.abc', 'refresh_token' => '1//def'],
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        expect($credential->credential_type)->toBe(CredentialVault::TYPE_OAUTH_TOKEN)
            ->and($credential->expires_at)->not->toBeNull();
    });

    it('creates an audit log entry on store', function () {
        $this->manager->storeCredential($this->user, CredentialVault::TYPE_API_KEY, [
            'name' => 'Test',
            'provider' => 'test',
            'secrets' => ['api_key' => 'key-123'],
        ]);

        expect(CredentialUsageLog::where('user_id', $this->user->id)->where('action', 'created')->count())->toBe(1);
    });

    it('encrypts notes when provided', function () {
        $credential = $this->manager->storeCredential($this->user, CredentialVault::TYPE_LOGIN, [
            'name' => 'Test Login',
            'provider' => 'example.com',
            'secrets' => ['username' => 'user', 'password' => 'pass'],
            'notes' => 'Personal account, MFA enabled',
        ]);

        expect($credential->notes)->not->toBe('Personal account, MFA enabled')
            ->and($credential->getDecryptedNotes())->toBe('Personal account, MFA enabled');
    });
});

describe('update credential', function () {
    it('updates credential name and metadata', function () {
        $credential = CredentialVault::factory()->apiKey()->create(['user_id' => $this->user->id]);

        $this->manager->updateCredential($credential, [
            'name' => 'Updated Name',
            'metadata' => ['base_url' => 'https://new-api.example.com'],
        ]);

        $credential->refresh();
        expect($credential->name)->toBe('Updated Name')
            ->and($credential->metadata['base_url'])->toBe('https://new-api.example.com');
    });

    it('updates secrets when provided', function () {
        $credential = CredentialVault::factory()->apiKey()->create(['user_id' => $this->user->id]);

        $this->manager->updateCredential($credential, [
            'secrets' => ['api_key' => 'new-key-456'],
        ]);

        $credential->refresh();
        $payload = $credential->getDecryptedPayload();
        expect($payload['api_key'])->toBe('new-key-456');
    });
});

describe('rotate credential', function () {
    it('rotates secrets and updates last_rotated_at', function () {
        $credential = CredentialVault::factory()->apiKey()->create([
            'user_id' => $this->user->id,
            'last_rotated_at' => null,
        ]);

        $this->manager->rotateCredential($credential, ['api_key' => 'rotated-key']);

        $credential->refresh();
        $payload = $credential->getDecryptedPayload();
        expect($payload['api_key'])->toBe('rotated-key')
            ->and($credential->last_rotated_at)->not->toBeNull();

        expect(CredentialUsageLog::where('credential_id', $credential->id)->where('action', 'rotated')->count())->toBe(1);
    });
});

describe('list credentials', function () {
    it('lists all credentials for a user', function () {
        CredentialVault::factory()->login()->create(['user_id' => $this->user->id]);
        CredentialVault::factory()->apiKey()->create(['user_id' => $this->user->id]);
        CredentialVault::factory()->sshKey()->create(['user_id' => $this->user->id]);

        $credentials = $this->manager->listCredentials($this->user);

        expect($credentials)->toHaveCount(3);
    });

    it('filters by type', function () {
        CredentialVault::factory()->login()->create(['user_id' => $this->user->id]);
        CredentialVault::factory()->apiKey()->create(['user_id' => $this->user->id]);

        $logins = $this->manager->listCredentials($this->user, CredentialVault::TYPE_LOGIN);

        expect($logins)->toHaveCount(1)
            ->and($logins->first()->credential_type)->toBe(CredentialVault::TYPE_LOGIN);
    });

    it('searches by name', function () {
        CredentialVault::factory()->login()->create([
            'user_id' => $this->user->id,
            'name' => 'My GitHub Login',
            'provider' => 'gitlab',
            'url' => 'https://gitlab.com/login',
        ]);
        CredentialVault::factory()->login()->create([
            'user_id' => $this->user->id,
            'name' => 'My Stripe Login',
            'provider' => 'stripe',
            'url' => 'https://dashboard.stripe.com/login',
        ]);

        $results = $this->manager->listCredentials($this->user, search: 'github');

        expect($results)->toHaveCount(1)
            ->and($results->first()->name)->toBe('My GitHub Login');
    });

    it('does not return other users credentials', function () {
        $otherUser = User::factory()->create();
        CredentialVault::factory()->login()->create(['user_id' => $otherUser->id]);
        CredentialVault::factory()->login()->create(['user_id' => $this->user->id]);

        $credentials = $this->manager->listCredentials($this->user);
        expect($credentials)->toHaveCount(1);
    });
});

describe('delete credential', function () {
    it('deletes a credential', function () {
        $credential = CredentialVault::factory()->apiKey()->create(['user_id' => $this->user->id]);
        $credentialId = $credential->id;

        $result = $this->manager->deleteCredential($credential);

        expect($result)->toBeTrue()
            ->and(CredentialVault::find($credentialId))->toBeNull();
    });
});

describe('resolve for URL', function () {
    it('finds a login credential by matching URL domain', function () {
        CredentialVault::factory()->login()->create([
            'user_id' => $this->user->id,
            'url' => 'https://github.com/login',
            'provider' => 'github',
        ]);

        $result = $this->manager->resolveForUrl($this->user, 'https://github.com/settings');

        expect($result)->not->toBeNull()
            ->and($result->provider)->toBe('github');
    });

    it('returns null for unmatched URL', function () {
        $result = $this->manager->resolveForUrl($this->user, 'https://unknown-site.com');

        expect($result)->toBeNull();
    });

    it('strips www prefix for matching', function () {
        CredentialVault::factory()->login()->create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com',
            'provider' => 'example',
        ]);

        $result = $this->manager->resolveForUrl($this->user, 'https://www.example.com/page');

        expect($result)->not->toBeNull();
    });
});

describe('expiry', function () {
    it('detects expired credentials', function () {
        $credential = CredentialVault::factory()->expired()->create(['user_id' => $this->user->id]);

        expect($credential->isExpired())->toBeTrue();
    });

    it('excludes expired credentials from runtime queries', function () {
        CredentialVault::factory()->expired()->create([
            'user_id' => $this->user->id,
            'provider' => 'expired-service',
        ]);

        $result = $this->manager->getForRuntime($this->user, 'expired-service');

        expect($result)->toBeNull();
    });
});
