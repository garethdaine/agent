<?php

namespace App\Enums\Runtime;

enum RuntimeMode: string
{
    case Safe = 'safe';
    case Standard = 'standard';
    case Full = 'full';
}
