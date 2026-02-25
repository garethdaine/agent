<?php

namespace App\Http\Requests\Interrogation;

use App\Models\InterrogationSession;
use App\Models\InterrogationSetting;
use App\Support\Agent\PathPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInterrogationSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InterrogationSession::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $projectDirectory = $this->input('project_directory');

        $this->merge([
            'project_directory' => is_string($projectDirectory) ? trim($projectDirectory) : $projectDirectory,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxTextLength = max(1, (int) config('agent.interrogation.max_text_length', 60000));

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'runner_type' => ['required', Rule::in(['claude', 'codex'])],
            'project_directory' => ['required', 'string', 'max:1024'],
            'interrogation_type' => ['required', Rule::in([InterrogationSession::TYPE_FEATURE, InterrogationSession::TYPE_GENERAL])],
            'feature_brief' => ['nullable', 'string', 'max:'.$maxTextLength, 'required_if:interrogation_type,'.InterrogationSession::TYPE_FEATURE],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pathPolicy = app(PathPolicy::class);

            $projectDirectoryError = $pathPolicy->validateWorkingDirectory((string) $this->input('project_directory'));

            if ($projectDirectoryError !== null) {
                $validator->errors()->add('project_directory', $projectDirectoryError);
            }

            if ($this->user() !== null) {
                $maxActiveSessions = $this->resolveMaxActiveSessions((int) $this->user()->id);
                $activeCount = InterrogationSession::query()
                    ->forUser((int) $this->user()->id)
                    ->active()
                    ->count();

                if ($activeCount >= $maxActiveSessions) {
                    $validator->errors()->add('runner_type', sprintf('You already have %d active interrogation sessions.', $maxActiveSessions));
                }
            }
        });
    }

    private function resolveMaxActiveSessions(int $userId): int
    {
        $default = max(1, (int) config('agent.interrogation.max_active_sessions', 3));
        $setting = InterrogationSetting::getForUser($userId, 'interrogation.max_active_sessions', $default);

        if (is_numeric($setting)) {
            return max(1, min(50, (int) $setting));
        }

        return $default;
    }
}
