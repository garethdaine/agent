<?php

declare(strict_types=1);

namespace App\Enums\Security;

enum InjectionSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
