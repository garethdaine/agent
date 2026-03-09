<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\ConnectedProvider;
use Carbon\CarbonImmutable;

class SaveTaskProviderSettingsAction
{
    /**
     * @param  array<string, mixed>  $teamData  ['team_id' => string, 'team_name' => ?string, 'team_key' => ?string] or empty
     * @param  array<string, mixed>  $projectSyncData  ['mode' => string, 'selected_project_id' => ?string, 'selected_project_name' => ?string, 'selected_project_url' => ?string]
     */
    public function execute(
        ConnectedProvider $provider,
        array $teamData,
        array $projectSyncData,
    ): ConnectedProvider {
        $metadata = is_array($provider->metadata_json) ? $provider->metadata_json : [];
        $identity = is_array($metadata['identity'] ?? null) ? $metadata['identity'] : [];

        if ($teamData !== []) {
            $metadata['team_id'] = $teamData['team_id'];
            $metadata['team_name'] = $teamData['team_name'] ?? null;
            $metadata['team_key'] = $teamData['team_key'] ?? null;
            $identity['team_id'] = $metadata['team_id'];
            $identity['team_name'] = $metadata['team_name'];
            $identity['team_key'] = $metadata['team_key'];
            $metadata['identity'] = $identity;
        }

        $metadata['project_sync'] = [
            'mode' => $projectSyncData['mode'],
            'selected_project_id' => $projectSyncData['selected_project_id'] ?? null,
            'selected_project_name' => $projectSyncData['selected_project_name'] ?? null,
            'selected_project_url' => $projectSyncData['selected_project_url'] ?? null,
            'updated_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        $provider->metadata_json = $metadata;
        $provider->save();

        return $provider;
    }
}
