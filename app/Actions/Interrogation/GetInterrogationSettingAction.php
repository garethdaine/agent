<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSetting;

class GetInterrogationSettingAction
{
    public function execute(int $userId, string $key): ?InterrogationSetting
    {
        return InterrogationSetting::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();
    }
}
