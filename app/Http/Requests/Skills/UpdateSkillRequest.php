<?php

declare(strict_types=1);

namespace App\Http\Requests\Skills;

use App\Models\AgentSkill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                Rule::in([AgentSkill::STATUS_ACTIVE, AgentSkill::STATUS_PAUSED]),
            ],
            'file' => ['sometimes', 'file', 'max:10240'],
        ];
    }
}
