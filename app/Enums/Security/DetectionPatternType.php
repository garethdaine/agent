<?php

declare(strict_types=1);

namespace App\Enums\Security;

enum DetectionPatternType: string
{
    case Regex = 'regex';
    case Keyword = 'keyword';
    case Heuristic = 'heuristic';
}
