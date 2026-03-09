<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\AgentBackupSetting;

class GetBackupSettingsAction
{
    public function execute(): AgentBackupSetting
    {
        return AgentBackupSetting::current();
    }
}
