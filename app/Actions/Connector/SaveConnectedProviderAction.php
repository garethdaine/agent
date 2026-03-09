<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\ConnectedProvider;
use App\Models\InterrogationSession;
use Carbon\CarbonImmutable;

class SaveConnectedProviderAction
{
    /**
     * @param  array<string, mixed>  $token
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $existingMetadata
     */
    public function execute(
        int $sessionId,
        int $userId,
        string $driverKey,
        array $token,
        array $identity,
        array $existingMetadata = [],
    ): ConnectedProvider {
        $projectSync = is_array($existingMetadata['project_sync'] ?? null) ? $existingMetadata['project_sync'] : [];
        $projectMode = in_array(($projectSync['mode'] ?? null), ['create_new', 'existing'], true)
            ? (string) $projectSync['mode']
            : 'create_new';

        /** @var ConnectedProvider */
        return ConnectedProvider::query()->updateOrCreate(
            [
                'providerable_type' => InterrogationSession::class,
                'providerable_id' => $sessionId,
                'category' => 'task_management',
                'driver' => $driverKey,
            ],
            [
                'user_id' => $userId,
                'provider_user_id' => $identity['provider_user_id'] ?? null,
                'provider_workspace_id' => $identity['workspace_id'] ?? null,
                'provider_workspace_name' => $identity['workspace_name'] ?? null,
                'access_token' => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_type' => $token['token_type'] ?? null,
                'expires_at' => $token['expires_at'] ?? null,
                'scopes_json' => $token['scopes'] ?? [],
                'metadata_json' => [
                    'provider' => $driverKey,
                    'connected_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    'team_id' => $identity['team_id'] ?? null,
                    'team_name' => $identity['team_name'] ?? null,
                    'team_key' => $identity['team_key'] ?? null,
                    'identity' => $identity,
                    'project_sync' => [
                        'mode' => $projectMode,
                        'selected_project_id' => $projectMode === 'existing'
                            ? (is_string($projectSync['selected_project_id'] ?? null) ? trim((string) $projectSync['selected_project_id']) : null)
                            : null,
                        'selected_project_name' => $projectMode === 'existing'
                            ? ($projectSync['selected_project_name'] ?? null)
                            : null,
                        'selected_project_url' => $projectMode === 'existing'
                            ? ($projectSync['selected_project_url'] ?? null)
                            : null,
                        'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    ],
                ],
            ],
        );
    }
}
