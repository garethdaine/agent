<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSetting;

class SaveInterrogationSettingAction
{
    public function execute(int $userId, string $key, mixed $value): InterrogationSetting
    {
        return InterrogationSetting::setForUser($userId, $key, $value);
    }
}
