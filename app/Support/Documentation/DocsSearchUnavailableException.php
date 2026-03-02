<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use RuntimeException;
use Throwable;

class DocsSearchUnavailableException extends RuntimeException
{
    public static function fromThrowable(Throwable $throwable): self
    {
        return new self('search temporarily unavailable', previous: $throwable);
    }
}
