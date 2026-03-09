<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use Illuminate\Support\Str;

class EnsureTeamVaultKeyAction
{
    public function execute(object $team): void
    {
        if (! $team->connector_vault_key) {
            $team->update(['connector_vault_key' => Str::random(64)]);
            $team->refresh();
        }
    }
}
