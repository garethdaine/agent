<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\Team;
use Illuminate\Encryption\Encrypter;

class ConnectorVaultEncrypter
{
    public function forTeam(Team $team): Encrypter
    {
        $vaultKey = $team->connector_vault_key;
        if (empty($vaultKey)) {
            throw new \RuntimeException("Team {$team->id} has no connector vault key configured.");
        }
        $derivedKey = substr(hash('sha256', $vaultKey.config('app.key')), 0, 32);

        return new Encrypter($derivedKey, 'aes-256-cbc');
    }

    public function encrypt(Team $team, string $plaintext): string
    {
        return $this->forTeam($team)->encryptString($plaintext);
    }

    public function decrypt(Team $team, string $ciphertext): string
    {
        return $this->forTeam($team)->decryptString($ciphertext);
    }
}
