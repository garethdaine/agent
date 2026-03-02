<?php

declare(strict_types=1);

namespace App\Support\Documentation\Schemas;

use RuntimeException;

class DocumentationValidationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    private function __construct(
        private readonly array $errors,
        string $context
    ) {
        parent::__construct(self::buildMessage($context, $errors));
    }

    /**
     * @param  array<int, string>  $errors
     */
    public static function fromErrors(array $errors, string $context): self
    {
        return new self(array_values($errors), $context);
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private static function buildMessage(string $context, array $errors): string
    {
        if ($errors === []) {
            return $context;
        }

        return $context.': '.implode(' | ', $errors);
    }
}
