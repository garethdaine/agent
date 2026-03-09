<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\MessengerIdentityLink;

class SaveIdentityLinkAction
{
    /**
     * Create or update a messenger identity link.
     */
    public function execute(
        int $userId,
        string $connectorAccountId,
        string $providerUserId,
        ?\Carbon\Carbon $expiresAt,
    ): MessengerIdentityLink {
        $existing = MessengerIdentityLink::where('connector_account_id', $connectorAccountId)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'user_id' => $userId,
                'expires_at' => $expiresAt,
            ]);

            return $existing;
        }

        return MessengerIdentityLink::create([
            'user_id' => $userId,
            'connector_account_id' => $connectorAccountId,
            'provider_user_id' => $providerUserId,
            'expires_at' => $expiresAt,
        ]);
    }
}
