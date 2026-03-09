<?php

declare(strict_types=1);

namespace App\Actions\Interrogation;

use App\Models\InterrogationSetting;
use Illuminate\Database\Eloquent\Collection;

class ListInterrogationSettingsAction
{
    /**
     * @return Collection<int, InterrogationSetting>
     */
    public function execute(int $userId): Collection
    {
        return InterrogationSetting::query()
            ->where('user_id', $userId)
            ->orderBy('key')
            ->get(['key', 'value', 'updated_at']);
    }
}
