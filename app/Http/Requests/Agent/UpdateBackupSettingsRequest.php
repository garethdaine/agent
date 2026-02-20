<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'timezone' => ['required', 'string', 'max:64', 'timezone'],
            'run_hour' => ['required', 'integer', 'between:0,23'],
            'run_minute' => ['required', 'integer', 'between:0,59'],
            'retention_days' => ['required', 'integer', 'between:1,365'],
        ];
    }
}
