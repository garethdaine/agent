<?php

declare(strict_types=1);

namespace App\Enums\Security;

enum ContentTrustLevel: string
{
    case Trusted = 'trusted';
    case Contextual = 'contextual';
    case Untrusted = 'untrusted';
}
