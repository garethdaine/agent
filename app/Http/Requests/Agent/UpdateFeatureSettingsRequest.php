<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent;

use App\Support\Agent\FeatureFlagManager;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFeatureSettingsRequest extends FormRequest
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
            'flags' => ['required', 'array', 'min:1'],
            'flags.*' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $flags = $this->input('flags', []);

            if (! is_array($flags)) {
                return;
            }

            $invalidKeys = array_values(array_diff(array_keys($flags), FeatureFlagManager::managedKeys()));
            if ($invalidKeys === []) {
                return;
            }

            $validator->errors()->add(
                'flags',
                'Unsupported feature flag keys: '.implode(', ', $invalidKeys)
            );
        });
    }
}
