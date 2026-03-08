<?php

namespace App\Http\Requests\NlSchedule;

use Illuminate\Foundation\Http\FormRequest;

class ParseScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input' => ['required', 'string', 'max:'.config('agent.nl_parse.max_input_length', 200)],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
