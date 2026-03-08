<?php

declare(strict_types=1);

namespace App\Enums\Security;

enum MessengerGroupPolicy: string
{
    case Ignore = 'ignore';
    case LowTrust = 'low_trust';
}
