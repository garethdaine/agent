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
            'selected_option' => ['nullable', 'string', 'max:1000'],
            'selected_options' => ['nullable', 'array', 'max:20'],
            'selected_options.*' => ['string', 'max:1000'],
            'skip_reason' => ['nullable', 'string', 'max:2000', 'required_if:answer_type,skip'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $answerType = (string) $this->input('answer_type', '');

            if ($answerType !== 'choice') {
                return;
            }

            $single = trim((string) $this->input('selected_option', ''));
            $many = array_filter((array) $this->input('selected_options', []), static fn ($value): bool => is_string($value) && trim($value) !== '');

            if ($single === '' && $many === []) {
                $validator->errors()->add('selected_option', 'Please select at least one option.');
            }
        });
    }
}
