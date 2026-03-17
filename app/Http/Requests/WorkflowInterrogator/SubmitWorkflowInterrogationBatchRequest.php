<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkflowInterrogator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitWorkflowInterrogationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1', 'max:20'],
            'answers.*.question_id' => ['required', 'string', 'max:120'],
            'answers.*.answer_type' => ['required', Rule::in(['choice', 'multi_choice', 'freetext'])],
            'answers.*.answer_text' => ['nullable', 'string', 'max:10000'],
            'answers.*.selected_option' => ['nullable', 'string', 'max:1000'],
            'answers.*.selected_options' => ['nullable', 'array', 'max:20'],
            'answers.*.selected_options.*' => ['string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $answers = (array) $this->input('answers', []);

            foreach ($answers as $index => $answer) {
                $answerType = (string) ($answer['answer_type'] ?? '');
                $text = trim((string) ($answer['answer_text'] ?? ''));
                $single = trim((string) ($answer['selected_option'] ?? ''));
                $many = array_values(array_filter(
                    (array) ($answer['selected_options'] ?? []),
                    static fn ($value): bool => is_string($value) && trim($value) !== ''
                ));

                if ($answerType === 'freetext' && $text === '') {
                    $validator->errors()->add("answers.{$index}.answer_text", 'Please provide an answer.');
                }

                if ($answerType === 'choice' && $single === '') {
                    $validator->errors()->add("answers.{$index}.selected_option", 'Please select an option.');
                }

                if ($answerType === 'multi_choice' && $many === []) {
                    $validator->errors()->add("answers.{$index}.selected_options", 'Please select at least one option.');
                }
            }
        });
    }
}
