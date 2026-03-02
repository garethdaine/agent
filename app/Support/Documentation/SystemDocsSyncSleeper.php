<?php

declare(strict_types=1);

namespace App\Support\Documentation;

class SystemDocsSyncSleeper implements DocsSyncSleeper
{
    public function sleep(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        sleep($seconds);
    }
}

