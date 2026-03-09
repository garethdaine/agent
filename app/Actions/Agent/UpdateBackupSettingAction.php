<?php

declare(strict_types=1);

namespace App\Actions\Agent;

use App\Models\AgentBackupSetting;

class UpdateBackupSettingAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(AgentBackupSetting $setting, array $attributes, ?int $updatedByUserId = null): AgentBackupSetting
    {
        $setting->fill($attributes);
        $setting->updated_by_user_id = $updatedByUserId;
        $setting->save();

        return $setting;
    }
}
