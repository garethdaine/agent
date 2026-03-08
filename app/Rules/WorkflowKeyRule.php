<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Agent\WorkflowKey;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WorkflowKeyRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || ! WorkflowKey::isValid($value)) {
            $fail('The :attribute format is invalid.');
        }
    }
}
