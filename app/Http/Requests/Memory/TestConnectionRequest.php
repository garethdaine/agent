<?php

declare(strict_types=1);

namespace App\Http\Requests\Memory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for testing provider connection.
 */
class TestConnectionRequest extends FormRequest
{
    /**
     * Valid providers for connection testing.
     *
     * @var array<string>
     */
    public const VALID_PROVIDERS = ['openai', 'anthropic', 'neo4j'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(self::VALID_PROVIDERS)],
        ];
    }
}
