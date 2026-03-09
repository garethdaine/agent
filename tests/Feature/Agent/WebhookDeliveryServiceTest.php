<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use App\Services\Agent\WebhookDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated(): void
    {
        $service = app(WebhookDeliveryService::class);
        $this->assertInstanceOf(WebhookDeliveryService::class, $service);
    }

    public function test_verify_signature_validates_correctly(): void
    {
        $payload = '{"event":"test"}';
        $secret = 'test-secret';
        $validSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(WebhookDeliveryService::verifySignature($payload, $validSignature, $secret));
        $this->assertFalse(WebhookDeliveryService::verifySignature($payload, 'sha256=invalid', $secret));
    }
}
