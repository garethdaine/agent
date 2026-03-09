<?php

declare(strict_types=1);

namespace App\Actions\Pairing;

use App\Models\MessengerIdentityLink;

class FindMessengerIdentityLinkAction
{
    public function execute(string $id): MessengerIdentityLink
    {
        return MessengerIdentityLink::findOrFail($id);
    }
}
