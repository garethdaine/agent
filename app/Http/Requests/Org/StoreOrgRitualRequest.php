<?php

namespace App\Http\Requests\Org;

use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrgRitualRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cron_expression' => ['required', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'nl_source_metadata' => ['nullable', 'string'],
            'phase_graph' => ['required', 'array', 'min:1'],
            'phase_graph.*.id' => ['required', 'string'],
            'phase_graph.*.name' => ['required', 'string'],
            'phase_graph.*.depends_on' => ['sometimes', 'array'],
            'phase_role_mappings' => ['required', 'array'],
            'context_inputs' => ['nullable', 'array'],
            'verification_strategy' => ['nullable', 'array'],
            'delivery_targets' => ['nullable', 'array'],
            'escalation_timeout_seconds' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'notification_level' => ['nullable', 'string', 'in:escalations_only,lifecycle,verbose'],
            'is_paused' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Validate cron expression
            $cronExpression = $this->input('cron_expression');
            if (! CronExpression::isValidExpression($cronExpression)) {
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
