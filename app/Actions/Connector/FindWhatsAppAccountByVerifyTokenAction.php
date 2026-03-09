<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectorAccount;

class FindWhatsAppAccountByVerifyTokenAction
{
    public function execute(string $verifyToken): ?ConnectorAccount
    {
        return ConnectorAccount::where('provider', ConnectorAccount::PROVIDER_WHATSAPP)
            ->get()
            ->first(function (ConnectorAccount $account) use ($verifyToken): bool {
                $credentials = $account->credentials;
                $expectedToken = $credentials['verify_token'] ?? null;

                return $expectedToken !== null && $verifyToken === $expectedToken;
            });
    }
}
