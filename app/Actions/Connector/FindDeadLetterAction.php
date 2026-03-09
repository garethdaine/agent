<?php

declare(strict_types=1);

namespace App\Actions\Connector;

use App\Models\MessengerDeadLetter;

class FindDeadLetterAction
{
    public function execute(int $id): MessengerDeadLetter
    {
        /** @var MessengerDeadLetter */
        return MessengerDeadLetter::with('connectorAccount')->findOrFail($id);
    }
}
