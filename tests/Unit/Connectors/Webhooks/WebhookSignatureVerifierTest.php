<?php

namespace Tests\Unit\Connectors\Webhooks;

use App\Support\Connectors\Webhooks\WebhookSignatureVerifier;
use Tests\TestCase;

class WebhookSignatureVerifierTest extends TestCase
{
    private WebhookSignatureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new WebhookSignatureVerifier;
    }

    public function test_verifies_hmac_sha256_signature(): void
    {
        $payload = '{"event":"invoice.created","data":{"id":"123"}}';
        $secret = 'whsec_test_secret_key';
        $signature = hash_hmac('sha256', $payload, $secret);

        $result = $this->verifier->verify($payload, $signature, $secret);

        $this->assertTrue($result);
    }

    public function test_rejects_tampered_payload(): void
    {
        $payload = '{"event":"invoice.created","data":{"id":"123"}}';
        $secret = 'whsec_test_secret_key';
        $signature = hash_hmac('sha256', $payload, $secret);

        $tamperedPayload = '{"event":"invoice.created","data":{"id":"456"}}';

        $result = $this->verifier->verify($tamperedPayload, $signature, $secret);

        $this->assertFalse($result);
    }

    public function test_rejects_missing_signature(): void
    {
        $payload = '{"event":"invoice.created"}';
        $secret = 'whsec_test_secret_key';

        $result = $this->verifier->verify($payload, '', $secret);

        $this->assertFalse($result);
    }

    public function test_rejects_wrong_secret(): void
    {
        $payload = '{"event":"invoice.created"}';
        $secret = 'whsec_correct_secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $result = $this->verifier->verify($payload, $signature, 'whsec_wrong_secret');

        $this->assertFalse($result);
    }

    public function test_extracts_signature_from_common_headers(): void
    {
        $payload = '{"event":"test"}';
        $secret = 'test_secret';
        $expected = hash_hmac('sha256', $payload, $secret);

        // sha256= prefix (GitHub/Xero pattern)
        $this->assertTrue($this->verifier->verify($payload, 'sha256='.$expected, $secret));

        // Raw hex (Stripe pattern)
        $this->assertTrue($this->verifier->verify($payload, $expected, $secret));
    }
}
