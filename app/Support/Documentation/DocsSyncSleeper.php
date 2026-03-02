<?php

declare(strict_types=1);

namespace App\Support\Documentation;

interface DocsSyncSleeper
{
    public function sleep(int $seconds): void;
}

