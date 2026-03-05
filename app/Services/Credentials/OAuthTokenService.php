<?php

namespace App\Services\Credentials;

use App\Models\ConnectedProvider;
use Illuminate\Support\Facades\Log;

class OAuthTokenService
{
    public function revoke(ConnectedProvider $provider): void
    {
        $provider->access_token = null;
        $provider->refresh_token = null;
        $provider->expires_at = null;
        $provider->save();

        Log::info('OAuthTokenService: tokens revoked', [
            'provider_id' => $provider->id,
            'driver' => $provider->driver ?? null,
            'providerable_type' => $provider->providerable_type,
        ]);
    }

    public function needsRefresh(ConnectedProvider $provider, int $bufferSeconds = 300): bool
    {
        if (empty($provider->refresh_token)) {
            return false;
        }

        $expiresAt = $provider->expires_at;
        if ($expiresAt === null) {
            return false;
        }

        return $expiresAt->subSeconds($bufferSeconds)->isPast();
    }
}
