<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Connectors;

use App\Http\Controllers\Controller;
use App\Support\Connectors\Auth\OAuthFlowManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConnectorOAuthCallbackController extends Controller
{
    public function __construct(
        private readonly OAuthFlowManager $oauthManager,
    ) {}

    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');

        if (! $state || ! $code) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Missing state or code parameter.',
                ],
            ], 400);
        }

        try {
            $this->oauthManager->handleCallback($state, $code);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'OAUTH_CALLBACK_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }

        return redirect(config('app.url') . '/connectors');
    }
}
