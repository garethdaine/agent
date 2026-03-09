<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messenger;

use App\Actions\Connector\FindConnectorAccountAction;
use App\Actions\Connector\FindConnectorAccountByProviderAction;
use App\Actions\Connector\SaveIdentityLinkAction;
use App\Http\Controllers\Controller;
use App\Models\ConnectorAccount;
use App\Services\Messenger\AccountLinkTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AccountLinkController extends Controller
{
    public function __construct(
        private readonly AccountLinkTokenService $tokenService,
        private readonly FindConnectorAccountAction $findConnectorAccount,
        private readonly FindConnectorAccountByProviderAction $findConnectorAccountByProvider,
        private readonly SaveIdentityLinkAction $saveIdentityLink,
    ) {}

    /**
     * Show the account link page for a valid token.
     *
     * GET /messenger/link/{token}
     *
     * No authentication required - just validates the token and shows info.
     */
    public function show(string $token): InertiaResponse
    {
        $payload = $this->tokenService->validate($token);

        if ($payload === null) {
            return Inertia::render('Messenger/LinkAccount', [
                'error' => 'This link has expired or is invalid. Please request a new link.',
                'token' => null,
                'provider' => null,
                'providerUserId' => null,
            ]);
        }

        return Inertia::render('Messenger/LinkAccount', [
            'token' => $token,
            'provider' => $payload->provider,
            'providerUserId' => $payload->providerUserId,
            'error' => null,
        ]);
    }

    /**
     * Complete the account link after authentication.
     *
     * POST /messenger/link/{token}
     *
     * Requires authentication via auth:sanctum middleware.
     */
    public function store(Request $request, string $token): InertiaResponse|RedirectResponse
    {
        // Atomically consume the token
        $payload = $this->tokenService->consume($token);

        if ($payload === null) {
            return Inertia::render('Messenger/LinkAccount', [
                'error' => 'This link has expired or was already used. Please request a new link.',
                'token' => null,
                'provider' => null,
                'providerUserId' => null,
            ]);
        }

        // Find the connector account
        $connectorAccount = null;
        if ($payload->connectorAccountId) {
            try {
                $connectorAccount = $this->findConnectorAccount->execute($payload->connectorAccountId);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $connectorAccount = null;
            }
        } else {
            // Find by provider (fallback when connectorAccountId not stored)
            $connectorAccount = $this->findConnectorAccountByProvider->execute(
                $payload->provider,
                ConnectorAccount::STATUS_CONNECTED,
            );
        }

        if ($connectorAccount === null) {
            return Inertia::render('Messenger/LinkAccount', [
                'error' => 'The messenger connection is no longer available.',
                'token' => null,
                'provider' => $payload->provider,
                'providerUserId' => null,
            ]);
        }

        $this->saveIdentityLink->execute(
            $request->user()->id,
            $connectorAccount->id,
            $payload->providerUserId,
            $this->calculateExpiry($connectorAccount),
        );

        Log::info('Messenger account linked', [
            'user_id' => $request->user()->id,
            'connector_account_id' => $connectorAccount->id,
            'provider' => $payload->provider,
            'provider_user_id' => $payload->providerUserId,
        ]);

        // Redirect to dashboard on success
        return redirect()->route('dashboard')->with('success', 'Your messenger account has been linked successfully.');
    }

    private function calculateExpiry(ConnectorAccount $connectorAccount): ?\Carbon\Carbon
    {
        $expirationDays = $connectorAccount->config['link_expiration_days'] ?? null;

        if ($expirationDays === null) {
            return null; // Permanent link
        }

        return now()->addDays((int) $expirationDays);
    }
}
