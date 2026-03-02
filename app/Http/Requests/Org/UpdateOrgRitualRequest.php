<?php

namespace App\Http\Requests\Org;

use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrgRitualRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cron_expression' => ['sometimes', 'string', 'max:100'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'nl_source_metadata' => ['sometimes', 'nullable', 'string'],
            'phase_graph' => ['sometimes', 'array', 'min:1'],
            'phase_graph.*.id' => ['required_with:phase_graph', 'string'],
            'phase_graph.*.name' => ['required_with:phase_graph', 'string'],
            'phase_graph.*.depends_on' => ['sometimes', 'array'],
            'phase_role_mappings' => ['sometimes', 'array'],
            'context_inputs' => ['sometimes', 'nullable', 'array'],
            'verification_strategy' => ['sometimes', 'nullable', 'array'],
            'delivery_targets' => ['sometimes', 'nullable', 'array'],
            'escalation_timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:60', 'max:86400'],
            'notification_level' => ['sometimes', 'nullable', 'string', 'in:escalations_only,lifecycle,verbose'],
            'is_paused' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Validate cron expression if provided
            $cronExpression = $this->input('cron_expression');
            if ($cronExpression !== null && ! CronExpression::isValidExpression($cronExpression)) {
                $validator->errors()->add(
                    'cron_expression',
                    'Invalid cron expression format.'
                );

                return;
            }

            // Validate timezone if provided
            $timezone = $this->input('timezone');
            if ($timezone !== null) {
                try {
                    new \DateTimeZone($timezone);
                } catch (\Exception) {
                    $validator->errors()->add(
                        'timezone',
                        'Invalid timezone.'
                    );

                    return;
                }
            }
        });
    }
}
