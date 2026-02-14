<?php

namespace App\Http\Requests\Interrogation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitAnswerRequest extends FormRequest
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
            'question_id' => ['nullable', 'string', 'max:120'],
            'answer_type' => ['required', Rule::in(['choice', 'freetext', 'skip'])],
            'answer_text' => ['nullable', 'string', 'max:10000', 'required_if:answer_type,freetext'],
            'selected_option' => ['nullable', 'string', 'max:1000', 'required_if:answer_type,choice'],
            'skip_reason' => ['nullable', 'string', 'max:2000', 'required_if:answer_type,skip'],
        ];
    }
}
