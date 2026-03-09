<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\MessengerDeadLetter;

class CountDeadLettersAction
{
    public function execute(): int
    {
        return MessengerDeadLetter::count();
    }
}
