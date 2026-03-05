<?php

declare(strict_types=1);

namespace App\Services\Messenger;

use App\Models\ConnectorAccount;
use App\Models\MessengerIdentityLink;

final class ChannelPolicyGuard
{
    /**
     * Check if a message from a given channel/user is allowed by the connector's policies.
     *
     * @param  bool  $isDm  Whether this is a direct message (not a group/channel)
     * @param  string  $channelId  The provider channel/group ID
     * @param  string  $providerUserId  The provider user who sent the message
     * @param  bool  $mentionsBot  Whether the bot was explicitly @mentioned
     */
    public function isAllowed(
        ConnectorAccount $connector,
        bool $isDm,
        string $channelId,
        string $providerUserId,
        bool $mentionsBot = false,
    ): ChannelPolicyResult {
        if ($isDm) {
            return $this->evaluateDmPolicy($connector, $providerUserId);
        }

        return $this->evaluateGroupPolicy($connector, $channelId, $mentionsBot);
    }

    private function evaluateDmPolicy(ConnectorAccount $connector, string $providerUserId): ChannelPolicyResult
    {
        $policy = $connector->getDmPolicy();

        return match ($policy) {
            'disabled' => ChannelPolicyResult::denied('DMs are disabled for this connector.'),
            'open' => ChannelPolicyResult::allowed(),
            'pairing' => $this->checkPairing($connector, $providerUserId),
            'allowlist' => $this->checkPairing($connector, $providerUserId),
            default => ChannelPolicyResult::denied("Unknown DM policy: {$policy}"),
        };
    }

    private function checkPairing(ConnectorAccount $connector, string $providerUserId): ChannelPolicyResult
    {
        $link = MessengerIdentityLink::query()
            ->forConnector($connector->id)
            ->forProviderUser($providerUserId)
            ->first();

        if (! $link) {
            return ChannelPolicyResult::denied('No identity link found. Send /whoami to request pairing.');
        }

        if ($link->isRevoked()) {
            return ChannelPolicyResult::denied('Your access has been revoked.');
        }

        if ($link->isPending()) {
            return ChannelPolicyResult::denied('Your pairing is pending approval.');
        }

        if (! $link->isValid()) {
            return ChannelPolicyResult::denied('Your identity link has expired.');
        }

        return ChannelPolicyResult::allowed();
    }

    private function evaluateGroupPolicy(ConnectorAccount $connector, string $channelId, bool $mentionsBot): ChannelPolicyResult
    {
        $policy = $connector->getGroupPolicy();

        if ($policy['require_mention'] && ! $mentionsBot) {
            return ChannelPolicyResult::ignored();
        }

        $allowedGroups = $policy['allowed_groups'];
        if ($allowedGroups !== [] && ! in_array($channelId, $allowedGroups, true)) {
            return ChannelPolicyResult::denied('This group is not in the allowed list.');
        }

        return ChannelPolicyResult::allowed();
    }
}
