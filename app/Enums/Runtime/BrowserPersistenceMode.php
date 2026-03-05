<?php

namespace App\Enums\Runtime;

enum BrowserPersistenceMode: string
{
    case Ephemeral = 'ephemeral';
    case Persistent = 'persistent';
}
