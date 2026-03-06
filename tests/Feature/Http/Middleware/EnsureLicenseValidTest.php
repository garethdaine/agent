<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\EnsureLicenseValid;
use App\Services\Agent\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureLicenseValidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(EnsureLicenseValid::class)
            ->get('/_test/license-web', fn () => response('OK'));

        Route::middleware(EnsureLicenseValid::class)
            ->prefix('agent/api/v1')
            ->get('/_test/license-api', fn () => response()->json(['ok' => true]));
    }

    public function test_allows_request_when_license_bypassed(): void
    {
        config(['app.url' => 'https://myapp.test']);

        $response = $this->get('/_test/license-web');

        $response->assertOk();
    }

    public function test_allows_api_request_when_license_is_valid(): void
    {
        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('shouldBypass')->andReturn(false);
        $mock->shouldReceive('isValid')->andReturn(true);

        $response = $this->getJson('/agent/api/v1/_test/license-api');

        $response->assertOk();
    }

    public function test_returns_403_for_api_when_license_invalid(): void
    {
        config(['app.url' => 'https://production.example.com']);

        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('shouldBypass')->andReturn(false);
        $mock->shouldReceive('isValid')->andReturn(false);

        $response = $this->getJson('/agent/api/v1/_test/license-api');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'LICENSE_INVALID',
            ],
        ]);
    }

    public function test_redirects_web_request_when_license_invalid(): void
    {
        config(['app.url' => 'https://production.example.com']);

        $mock = $this->mock(LicenseService::class);
        $mock->shouldReceive('shouldBypass')->andReturn(false);
        $mock->shouldReceive('isValid')->andReturn(false);

        $response = $this->get('/_test/license-web');

        $response->assertRedirect(route('license.required'));
    }
}
