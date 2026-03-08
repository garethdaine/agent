<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Cron\CronExpression;
use Illuminate\Contracts\Validation\ValidationRule;

class NumericCronExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (! preg_match('/^[\x20-\x7E]+$/', $value)) {
            $fail('The :attribute must use printable ASCII characters only.');

            return;
        }

        $parts = preg_split('/\s+/', trim($value)) ?: [];

        if (count($parts) !== 5) {
            $fail('The :attribute must be a 5-part numeric cron expression.');

            return;
        }

        foreach ($parts as $part) {
            if (! preg_match('/^[0-9*\/,\-]+$/', $part)) {
                $fail('The :attribute must use numeric cron tokens only (numbers, *, ranges, lists, and steps).');

                return;
            }
        }

        if (preg_match('/[A-Za-z]/', $value)) {
            $fail('The :attribute must not use month/day names.');

            return;
        }

        if (! CronExpression::isValidExpression($value)) {
            $fail('The :attribute is not a valid cron expression.');
        }
    }
}
