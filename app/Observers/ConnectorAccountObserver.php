<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ConnectorAccount;
use Illuminate\Support\Facades\Cache;

class ConnectorAccountObserver
{
    public function updated(ConnectorAccount $connector): void
    {
        Cache::forget("connector:account:{$connector->id}");
    }

    public function deleted(ConnectorAccount $connector): void
    {
        Cache::forget("connector:account:{$connector->id}");
    }
}
