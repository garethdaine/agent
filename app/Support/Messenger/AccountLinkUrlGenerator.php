<?php

declare(strict_types=1);

namespace App\Support\Messenger;

use App\Services\Messenger\AccountLinkTokenService;

class AccountLinkUrlGenerator
{
    public function __construct(
        private readonly AccountLinkTokenService $tokenService
    ) {}

    /**
     * Generate a full URL for account linking.
     *
     * This URL is sent to users in chat to complete the account link flow.
     */
    public function generate(string $provider, string $providerUserId): string
    {
        $token = $this->tokenService->generate($provider, $providerUserId);

        return $this->buildUrl($token);
    }

    /**
     * Build the full URL for a given token.
     *
     * Handles both local and public URL modes.
     */
    public function buildUrl(string $token): string
    {
        return route('messenger.link.show', ['token' => $token]);
    }

    /**
     * Get the public-facing URL for account linking.
     *
     * Uses APP_URL configuration to generate the public URL.
     */
    public function getPublicUrl(string $token): string
    {
        $baseUrl = config('app.url');

        return rtrim($baseUrl, '/').'/messenger/link/'.$token;
    }
}
