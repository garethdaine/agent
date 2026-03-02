<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\RepoAnalysis;

use Illuminate\Foundation\Http\FormRequest;

class RepoAnalysisEventsRequest extends FormRequest
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
            'since_sequence' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
