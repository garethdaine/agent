<?php

declare(strict_types=1);

namespace App\Enums\Security;

enum InjectionAction: string
{
    case Strip = 'strip';
    case Warn = 'warn';
    case Block = 'block';
    case Log = 'log';
}
