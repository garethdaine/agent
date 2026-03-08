<?php

declare(strict_types=1);

namespace App\Enums\Runtime;

enum BrowserPersistenceMode: string
{
    case Ephemeral = 'ephemeral';
    case Persistent = 'persistent';
}
