<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Memory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memory\TestConnectionRequest;
use App\Http\Requests\Memory\UpdateMemorySettingsRequest;
use App\Support\Memory\MemoryCapabilityResolver;
use App\Support\Memory\MemorySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Memory settings API controller.
 *
 * Endpoints:
 * - GET /memory/settings - Read all settings (keys masked)
 * - PUT /memory/settings - Batch update settings
 * - POST /memory/settings/test-connection - Test provider connectivity
 * - GET /memory/settings/capabilities - Return operating mode and features
 */
class MemorySettingsController extends Controller
{
    public function __construct(
        private readonly MemorySettingsService $settingsService,
        private readonly MemoryCapabilityResolver $capabilityResolver
    ) {}

    /**
     * GET /memory/settings
     *
     * Returns all settings (non-credential) plus credential status per provider.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $settings = $this->settingsService->getAllMasked($userId);

        return response()->json([
            'data' => $settings,
            'credentials' => [
                'openai' => $this->settingsService->isProviderKeyConfigured($userId, 'openai'),
                'anthropic' => $this->settingsService->isProviderKeyConfigured($userId, 'anthropic'),
            ],
        ]);
    }

    /**
     * PUT /memory/settings
     *
     * Batch update settings.
     */
    public function update(UpdateMemorySettingsRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $settings = $request->getSettings();
        $updated = 0;

        foreach ($settings as $key => $value) {
            $this->settingsService->set($userId, $key, $value);
            $updated++;
        }

        return response()->json([
            'data' => [
                'updated' => $updated,
            ],
        ]);
    }

    /**
     * POST /memory/settings/test-connection
     *
     * Test connectivity to a provider.
     */
    public function testConnection(TestConnectionRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $provider = $request->input('provider');

        try {
            $result = $this->settingsService->testConnection($userId, $provider);

            return response()->json([
                'data' => [
                    'provider' => $provider,
                    'success' => $result['success'] ?? false, // @phpstan-ignore nullCoalesce.offset
                    'message' => $result['message'] ?? null,
                    'latency_ms' => $result['latency_ms'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [
                    'provider' => $provider,
                    'success' => false,
                    'message' => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * GET /memory/settings/capabilities
     *
     * Returns the operating mode and available capabilities.
     */
    public function capabilities(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $capabilities = $this->capabilityResolver->getCapabilities($userId);

        return response()->json([
            'data' => $capabilities,
        ]);
    }
}
