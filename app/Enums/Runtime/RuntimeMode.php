<?php

declare(strict_types=1);

namespace App\Enums\Runtime;

enum RuntimeMode: string
{
    case Safe = 'safe';
    case Standard = 'standard';
    case Full = 'full';
}
