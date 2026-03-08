<?php

namespace Tests\Unit\Connectors;

use App\Support\Connectors\ConnectorManifestValidator;
use Tests\TestCase;

class ConnectorManifestValidatorTest extends TestCase
{
    private ConnectorManifestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConnectorManifestValidator;
    }

    public function test_validates_valid_manifest(): void
    {
        $result = $this->validator->validate($this->validManifest());

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
    }

    public function test_rejects_missing_required_fields(): void
    {
        $manifest = $this->validManifest();
        unset($manifest['name']);

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result->valid);
        $this->assertContains('Missing required field: name', $result->errors);
    }

    public function test_rejects_invalid_auth_type(): void
    {
        $manifest = $this->validManifest();
        $manifest['auth_type'] = 'invalid';

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result->valid);
        $this->assertTrue(
            collect($result->errors)->contains(fn ($e) => str_contains($e, 'Invalid auth_type: invalid'))
        );
    }

    public function test_rejects_invalid_action_methods(): void
    {
        $manifest = $this->validManifest();
        $manifest['actions'] = [
            ['name' => 'test-action', 'method' => 'PATCH', 'path' => '/test'],
        ];

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result->valid);
        $this->assertTrue(
            collect($result->errors)->contains(fn ($e) => str_contains($e, 'invalid method: PATCH'))
        );
    }

    public function test_rejects_duplicate_action_names(): void
    {
        $manifest = $this->validManifest();
        $manifest['actions'] = [
            ['name' => 'list-items', 'method' => 'GET', 'path' => '/items'],
            ['name' => 'list-items', 'method' => 'POST', 'path' => '/items'],
        ];

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result->valid);
        $this->assertTrue(
            collect($result->errors)->contains(fn ($e) => str_contains($e, 'Duplicate action name: list-items'))
        );
    }

    public function test_validates_oauth2_requires_authorization_url(): void
    {
        $manifest = $this->validManifest();
        $manifest['auth_type'] = 'oauth2_authorization_code';
        $manifest['auth_config'] = ['token_url' => 'https://example.com/token'];

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result->valid);
        $this->assertTrue(
            collect($result->errors)->contains(fn ($e) => str_contains($e, 'authorization_url'))
        );
    }

    public function test_validates_rate_limits_structure(): void
    {
        $manifest = $this->validManifest();
        $manifest['rate_limits'] = ['requests_per_minute' => 60];

        $result = $this->validator->validate($manifest);

        $this->assertFalse($result->valid);
        $this->assertTrue(
            collect($result->errors)->contains(fn ($e) => str_contains($e, 'daily_limit'))
        );
        $this->assertTrue(
            collect($result->errors)->contains(fn ($e) => str_contains($e, 'concurrent_limit'))
        );
    }

    public function test_accepts_all_valid_auth_types(): void
    {
        $authTypes = [
            'oauth2_authorization_code',
            'oauth2_client_credentials',
            'api_key',
            'api_key_secret',
            'basic',
            'custom_header',
        ];

        foreach ($authTypes as $authType) {
            $manifest = $this->validManifest();
            $manifest['auth_type'] = $authType;

            if (in_array($authType, ['oauth2_authorization_code', 'oauth2_client_credentials'])) {
                $manifest['auth_config'] = [
                    'authorization_url' => 'https://example.com/authorize',
                    'token_url' => 'https://example.com/token',
                ];
            }

            $result = $this->validator->validate($manifest);

            $this->assertTrue($result->valid, "Auth type '{$authType}' should be valid but got errors: ".implode(', ', $result->errors));
        }
    }

    private function validManifest(): array
    {
        return [
            'name' => 'test-connector',
            'display_name' => 'Test Connector',
            'description' => 'A test connector for unit tests.',
            'version' => '1.0.0',
            'auth_type' => 'api_key',
            'auth_config' => ['header' => 'X-API-Key'],
            'base_url' => 'https://api.example.com',
            'rate_limits' => [
                'requests_per_minute' => 60,
                'daily_limit' => 10000,
                'concurrent_limit' => 5,
            ],
            'actions' => [
                ['name' => 'list-items', 'method' => 'GET', 'path' => '/items'],
                ['name' => 'create-item', 'method' => 'POST', 'path' => '/items'],
            ],
        ];
    }
}
