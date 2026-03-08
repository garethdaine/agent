<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AgentConnectorCredential;
use App\Models\AgentConnectorCredentialEvent;
use App\Models\Team;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ConnectorRotateKeysCommand extends Command
{
    protected $signature = 'connector:rotate-keys
        {--team= : Team ID to rotate keys for}
        {--force : Skip confirmation}';

    protected $description = 'Rotate the connector vault key for a team and re-encrypt all credentials';

    public function handle(ConnectorVaultEncrypter $vault): int
    {
        $team = Team::find($this->option('team'));

        if (! $team) {
            $this->error('Team not found.');

            return self::FAILURE;
        }

        if (! $team->connector_vault_key) {
            $this->error('Team has no connector vault key to rotate.');

            return self::FAILURE;
        }

        $credentials = AgentConnectorCredential::where('team_id', $team->id)->get();

        if ($credentials->isEmpty()) {
            $this->info('No credentials to rotate for this team.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Rotate vault key for team {$team->id}? This will re-encrypt {$credentials->count()} credential(s).")) {
            return self::SUCCESS;
        }

        // Decrypt all credentials with old key
        $decrypted = [];
        foreach ($credentials as $credential) {
            $encryptedData = $credential->encrypted_data;
            // PostgreSQL BYTEA columns return PHP streams — convert to string
            if (is_resource($encryptedData)) {
                $encryptedData = stream_get_contents($encryptedData);
            }
            $decrypted[$credential->id] = $vault->decrypt($team, $encryptedData);
        }

        // Generate new vault key
        $newKey = Str::random(64);
        $team->update(['connector_vault_key' => $newKey]);
        $team->refresh();

        // Re-encrypt all credentials with new key
        foreach ($credentials as $credential) {
            $credential->update([
                'encrypted_data' => $vault->encrypt($team, $decrypted[$credential->id]),
                'encryption_key_id' => 'v'.($credential->rotation_count + 1),
                'rotation_count' => $credential->rotation_count + 1,
            ]);

            AgentConnectorCredentialEvent::create([
                'credential_id' => $credential->id,
                'event_type' => 'rotated',
                'details' => [
                    'rotation_count' => $credential->rotation_count,
                    'rotated_at' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        }

        $this->info("Rotated vault key and re-encrypted {$credentials->count()} credential(s) for team {$team->id}.");

        return self::SUCCESS;
    }
}
