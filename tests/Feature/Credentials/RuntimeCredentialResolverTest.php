<?php

declare(strict_types=1);

use App\Models\CredentialUsageLog;
use App\Models\CredentialVault;
use App\Models\User;
use App\Support\Credentials\ApprovalDecision;
use App\Support\Credentials\CredentialQuery;
use App\Support\Credentials\RuntimeCredentialResolver;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->resolver = app(RuntimeCredentialResolver::class);
});

describe('resolve by query', function () {
    it('resolves a credential by provider name', function () {
        CredentialVault::factory()->apiKey()->autonomous()->create([
            'user_id' => $this->user->id,
            'provider' => 'stripe',
        ]);

        $result = $this->resolver->resolve(
            $this->user,
            new CredentialQuery(provider: 'stripe'),
            trustScore: 0.5,
        );

        expect($result->isAllowed())->toBeTrue()
            ->and($result->credentials)->toBeArray()
            ->and($result->credentialType)->toBe(CredentialVault::TYPE_API_KEY);
    });

    it('resolves a credential by URL', function () {
        CredentialVault::factory()->login()->autonomous()->create([
            'user_id' => $this->user->id,
            'provider' => 'github',
            'url' => 'https://github.com/login',
        ]);

        $result = $this->resolver->resolve(
            $this->user,
            new CredentialQuery(url: 'https://github.com'),
            trustScore: 0.5,
        );

        expect($result->isAllowed())->toBeTrue()
            ->and($result->credentialType)->toBe(CredentialVault::TYPE_LOGIN);
    });

    it('resolves a credential by name search', function () {
        CredentialVault::factory()->apiKey()->autonomous()->create([
            'user_id' => $this->user->id,
            'name' => 'My AWS Production Key',
            'provider' => 'aws',
        ]);

        $result = $this->resolver->resolve(
            $this->user,
            new CredentialQuery(name: 'AWS Production'),
            trustScore: 0.5,
        );

        expect($result->isAllowed())->toBeTrue();
    });

    it('returns denied when no credential matches', function () {
        $result = $this->resolver->resolve(
            $this->user,
            new CredentialQuery(provider: 'nonexistent'),
            trustScore: 1.0,
        );

        expect($result->decision)->toBe(ApprovalDecision::Denied)
            ->and($result->reason)->toContain('No matching credential found');
    });
});

describe('resolve for browser', function () {
    it('resolves login credentials for a URL', function () {
        CredentialVault::factory()->login()->autonomous()->create([
            'user_id' => $this->user->id,
            'provider' => 'x.com',
            'url' => 'https://x.com',
        ]);

        $result = $this->resolver->resolveForBrowser($this->user, 'https://x.com/home', 0.5);

        expect($result->isAllowed())->toBeTrue()
            ->and($result->credentialType)->toBe(CredentialVault::TYPE_LOGIN)
            ->and($result->credentials)->toBeArray();
    });

    it('returns denied for unmatched URL', function () {
        $result = $this->resolver->resolveForBrowser($this->user, 'https://unknown-site.com', 1.0);

        expect($result->decision)->toBe(ApprovalDecision::Denied)
            ->and($result->reason)->toContain('No login credentials found');
    });
});

describe('resolve API key', function () {
    it('resolves API key by provider', function () {
        CredentialVault::factory()->apiKey()->autonomous()->create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
        ]);

        $result = $this->resolver->resolveApiKey($this->user, 'openai', 0.5);

        expect($result->isAllowed())->toBeTrue()
            ->and($result->credentialType)->toBe(CredentialVault::TYPE_API_KEY);
    });

    it('returns denied for missing provider', function () {
        $result = $this->resolver->resolveApiKey($this->user, 'nonexistent', 1.0);

        expect($result->decision)->toBe(ApprovalDecision::Denied)
            ->and($result->reason)->toContain('No API key found');
    });
});

describe('approval policy enforcement', function () {
    it('allows autonomous credentials regardless of trust score', function () {
        CredentialVault::factory()->apiKey()->autonomous()->create([
            'user_id' => $this->user->id,
            'provider' => 'test-auto',
        ]);

        $result = $this->resolver->resolveApiKey($this->user, 'test-auto', 0.0);

        expect($result->isAllowed())->toBeTrue();
    });

    it('allows supervised credentials when trust score is high enough', function () {
        CredentialVault::factory()->apiKey()->supervised()->create([
            'user_id' => $this->user->id,
            'provider' => 'test-supervised',
        ]);

        $result = $this->resolver->resolveApiKey($this->user, 'test-supervised', 0.8);

        expect($result->isAllowed())->toBeTrue();
    });

    it('requires confirmation for supervised credentials with low trust', function () {
        CredentialVault::factory()->apiKey()->supervised()->create([
            'user_id' => $this->user->id,
            'provider' => 'test-supervised-low',
        ]);

        $result = $this->resolver->resolveApiKey($this->user, 'test-supervised-low', 0.3);

        expect($result->needsConfirmation())->toBeTrue()
            ->and($result->reason)->toContain('requires user confirmation');
    });

    it('always requires confirmation for restricted credentials', function () {
        CredentialVault::factory()->apiKey()->restricted()->create([
            'user_id' => $this->user->id,
            'provider' => 'test-restricted',
        ]);

        $result = $this->resolver->resolveApiKey($this->user, 'test-restricted', 1.0);

        expect($result->needsConfirmation())->toBeTrue();
    });

    it('denies expired credentials', function () {
        CredentialVault::factory()->apiKey()->autonomous()->expired()->create([
            'user_id' => $this->user->id,
            'provider' => 'test-expired',
        ]);

        $result = $this->resolver->resolveApiKey($this->user, 'test-expired', 1.0);

        expect($result->decision)->toBe(ApprovalDecision::Denied);
    });
});

describe('audit logging', function () {
    it('logs every credential resolution attempt', function () {
        CredentialVault::factory()->apiKey()->autonomous()->create([
            'user_id' => $this->user->id,
            'provider' => 'audit-test',
        ]);

        $this->resolver->resolveApiKey($this->user, 'audit-test', 0.5);

        $log = CredentialUsageLog::where('user_id', $this->user->id)
            ->where('action', CredentialUsageLog::ACTION_RETRIEVED)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->outcome)->toBe('allowed')
            ->and($log->context)->toHaveKey('provider');
    });

    it('logs denied attempts', function () {
        CredentialVault::factory()->apiKey()->restricted()->create([
            'user_id' => $this->user->id,
            'provider' => 'denied-test',
        ]);

        $this->resolver->resolveApiKey($this->user, 'denied-test', 0.5);

        $log = CredentialUsageLog::where('user_id', $this->user->id)
            ->where('action', CredentialUsageLog::ACTION_RETRIEVED)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->outcome)->toBe('needs_confirmation');
    });
});

describe('usage tracking', function () {
    it('updates last_used_at when credential is allowed', function () {
        $credential = CredentialVault::factory()->apiKey()->autonomous()->create([
            'user_id' => $this->user->id,
            'provider' => 'usage-test',
            'last_used_at' => null,
        ]);

        $this->resolver->resolveApiKey($this->user, 'usage-test', 0.5);

        $credential->refresh();
        expect($credential->last_used_at)->not->toBeNull();
    });

    it('does not update last_used_at when access is denied', function () {
        $credential = CredentialVault::factory()->apiKey()->restricted()->create([
            'user_id' => $this->user->id,
            'provider' => 'no-usage-test',
            'last_used_at' => null,
        ]);

        $this->resolver->resolveApiKey($this->user, 'no-usage-test', 0.5);

        $credential->refresh();
        expect($credential->last_used_at)->toBeNull();
    });
});
