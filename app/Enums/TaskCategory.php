<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskCategory: string
{
    case FEATURE = 'feature';
    case BUGFIX = 'bugfix';
    case REFACTOR = 'refactor';
    case DOCS = 'docs';
    case TEST = 'test';
    case CUSTOM = 'custom';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value)) ?? self::CUSTOM;
    }
}
