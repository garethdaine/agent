<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Credentials\VaultRuntimeTokenService;
use App\Support\Credentials\CredentialMcpTools;
use App\Support\Credentials\RuntimeCredentialResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight vault API for CLI runtime access.
 *
 * The CLI agent subprocess cannot use Sanctum/session auth, so this endpoint
 * authenticates via a short-lived HMAC token injected as an environment variable.
 * It proxies requests to CredentialMcpTools, applying the same approval policies
 * as the in-app LLM path.
 */
class VaultRuntimeController extends Controller
{
    public function __construct(
        private readonly VaultRuntimeTokenService $tokenService,
        private readonly CredentialMcpTools $mcpTools,
        private readonly RuntimeCredentialResolver $resolver,
    ) {}

    /**
     * Handle a vault runtime operation.
     *
     * POST /api/v1/runtime/vault/{operation}
     *
     * Operations: list_credentials, get_credential, get_login_for_url, get_api_key
     */
    public function __invoke(Request $request, string $operation): JsonResponse
    {
        $user = $this->authenticateRuntime($request);
        if ($user === null) {
            return response()->json(['success' => false, 'error' => 'Invalid or expired vault token.'], 401);
        }

        $validOperations = ['list_credentials', 'get_credential', 'get_login_for_url', 'get_api_key'];
        if (! in_array($operation, $validOperations, true)) {
            return response()->json([
                'success' => false,
                'error' => "Unknown vault operation: {$operation}. Valid: ".implode(', ', $validOperations),
            ], 400);
        }

        $toolName = 'vault.'.$operation;
        $arguments = $request->only(['provider', 'name', 'url', 'type', 'search']);

        // Trust score from the request (injected by CLI executor) or default to 0.0
        $trustScore = (float) $request->input('trust_score', 0.0);

        $result = $this->mcpTools->executeTool(
            $toolName,
            $arguments,
            $user,
            $this->resolver,
            $trustScore,
        );

        $statusCode = ($result['success'] ?? false) ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    private function authenticateRuntime(Request $request): ?User
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return null;
        }

        $payload = $this->tokenService->validate($token);
        if ($payload === null) {
            return null;
        }

        return User::find($payload['user_id']);
    }
}
