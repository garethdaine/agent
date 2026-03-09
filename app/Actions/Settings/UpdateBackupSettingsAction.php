<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\AgentBackupSetting;

class UpdateBackupSettingsAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{setting: AgentBackupSetting, before: array<string, mixed>}
     */
    public function execute(array $data, ?int $userId = null): array
    {
        $setting = AgentBackupSetting::current();

        $before = $setting->only([
            'is_enabled',
            'timezone',
            'run_hour',
            'run_minute',
            'retention_days',
        ]);

        $setting->fill($data);
        $setting->updated_by_user_id = $userId;
        $setting->save();

        return [
            'setting' => $setting,
            'before' => $before,
        ];
    }
}
