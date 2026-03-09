<?php

declare(strict_types=1);

namespace Tests\Feature\Credentials;

use App\Services\Credentials\CredentialsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialsManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(CredentialsManager::class);
        $this->assertInstanceOf(CredentialsManager::class, $service);
    }

    public function test_redact_for_audit_returns_redacted_string(): void
    {
        $service = app(CredentialsManager::class);
        $result = $service->redactForAudit('my-secret-value');

        $this->assertIsString($result);
        $this->assertStringNotContainsString('my-secret-value', $result);
    }
}
