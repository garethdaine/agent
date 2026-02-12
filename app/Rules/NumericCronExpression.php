<?php

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

        if (! preg_match('/^\d+\s+\d+\s+\d+\s+\d+\s+\d+$/', trim($value))) {
            $fail('The :attribute must be a 5-part numeric cron expression.');

            return;
        }

        if (! CronExpression::isValidExpression($value)) {
            $fail('The :attribute is not a valid cron expression.');
        }
    }
}
