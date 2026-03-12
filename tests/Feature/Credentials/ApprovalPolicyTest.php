<?php

declare(strict_types=1);

use App\Models\CredentialVault;
use App\Services\Credentials\CredentialsManager;
use App\Support\Credentials\ApprovalDecision;

describe('CredentialVault approval policy', function () {
    it('autonomous mode does not require approval at any trust score', function () {
        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_AUTONOMOUS;

        expect($credential->requiresApproval(0.0))->toBeFalse()
            ->and($credential->requiresApproval(0.5))->toBeFalse()
            ->and($credential->requiresApproval(1.0))->toBeFalse();
    });

    it('supervised mode requires approval when trust is below threshold', function () {
        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_SUPERVISED;

        expect($credential->requiresApproval(0.3))->toBeTrue()
            ->and($credential->requiresApproval(0.69))->toBeTrue();
    });

    it('supervised mode allows access when trust meets threshold', function () {
        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_SUPERVISED;

        expect($credential->requiresApproval(0.7))->toBeFalse()
            ->and($credential->requiresApproval(0.9))->toBeFalse()
            ->and($credential->requiresApproval(1.0))->toBeFalse();
    });

    it('restricted mode always requires approval', function () {
        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_RESTRICTED;

        expect($credential->requiresApproval(0.0))->toBeTrue()
            ->and($credential->requiresApproval(0.5))->toBeTrue()
            ->and($credential->requiresApproval(1.0))->toBeTrue();
    });
});

describe('CredentialVault expiry', function () {
    it('is not expired when expires_at is null', function () {
        $credential = new CredentialVault;
        $credential->expires_at = null;

        expect($credential->isExpired())->toBeFalse();
    });

    it('is not expired when expires_at is in the future', function () {
        $credential = new CredentialVault;
        $credential->expires_at = now()->addHour();

        expect($credential->isExpired())->toBeFalse();
    });

    it('is expired when expires_at is in the past', function () {
        $credential = new CredentialVault;
        $credential->expires_at = now()->subHour();

        expect($credential->isExpired())->toBeTrue();
    });
});

describe('CredentialsManager checkApproval', function () {
    it('returns Denied for expired credentials', function () {
        $manager = app(CredentialsManager::class);

        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_AUTONOMOUS;
        $credential->expires_at = now()->subHour();

        expect($manager->checkApproval($credential, 1.0))->toBe(ApprovalDecision::Denied);
    });

    it('returns NeedsConfirmation for restricted credentials', function () {
        $manager = app(CredentialsManager::class);

        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_RESTRICTED;
        $credential->expires_at = null;

        expect($manager->checkApproval($credential, 1.0))->toBe(ApprovalDecision::NeedsConfirmation);
    });

    it('returns Allowed for autonomous credentials', function () {
        $manager = app(CredentialsManager::class);

        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_AUTONOMOUS;
        $credential->expires_at = null;

        expect($manager->checkApproval($credential, 0.0))->toBe(ApprovalDecision::Allowed);
    });

    it('returns Allowed for supervised credentials with high trust', function () {
        $manager = app(CredentialsManager::class);

        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_SUPERVISED;
        $credential->expires_at = null;

        expect($manager->checkApproval($credential, 0.8))->toBe(ApprovalDecision::Allowed);
    });

    it('returns NeedsConfirmation for supervised credentials with low trust', function () {
        $manager = app(CredentialsManager::class);

        $credential = new CredentialVault;
        $credential->approval_mode = CredentialVault::APPROVAL_SUPERVISED;
        $credential->expires_at = null;

        expect($manager->checkApproval($credential, 0.5))->toBe(ApprovalDecision::NeedsConfirmation);
    });
});

describe('CredentialVault type constants', function () {
    it('has all expected types', function () {
        expect(CredentialVault::VALID_TYPES)->toBe([
            'login',
            'api_key',
            'ssh_key',
            'oauth_token',
        ]);
    });

    it('has all expected approval modes', function () {
        expect(CredentialVault::VALID_APPROVAL_MODES)->toBe([
            'autonomous',
            'supervised',
            'restricted',
        ]);
    });
});

describe('CredentialVault redaction', function () {
    it('redacts short values completely', function () {
        expect(CredentialVault::redactForAudit('short'))->toBe('***');
    });

    it('shows first and last 4 chars for longer values', function () {
        expect(CredentialVault::redactForAudit('sk_test_1234567890'))->toBe('sk_t…7890');
    });

    it('handles empty and null values', function () {
        expect(CredentialVault::redactForAudit(null))->toBe('[empty]')
            ->and(CredentialVault::redactForAudit(''))->toBe('[empty]');
    });
});
