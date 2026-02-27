<?php

namespace App\Http\Requests\Agent;

use App\Rules\NumericCronExpression;
use App\Support\Agent\CommandPolicy;
use App\Support\Agent\EnvPolicy;
use App\Support\Agent\PathPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAgentJobRequest extends FormRequest
{
    private ?string $normalizedCommandTemplate = null;

    private ?string $resolvedExecutablePath = null;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $workingDirectory = $this->input('working_directory');
        $taskMarkdownPath = $this->input('task_markdown_path');
        $commandTemplate = $this->input('command_template');

        $this->merge([
            'working_directory' => is_string($workingDirectory) ? trim($workingDirectory) : $workingDirectory,
            'task_markdown_path' => is_string($taskMarkdownPath) ? trim($taskMarkdownPath) : $taskMarkdownPath,
            'command_template' => is_string($commandTemplate) ? trim($commandTemplate) : $commandTemplate,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[^\p{C}]+$/u'],
            'description' => ['nullable', 'string', 'max:4000'],
            'cron_expression' => ['required', 'string', 'max:100', new NumericCronExpression],
            'timezone' => ['required', 'string', 'max:64', 'timezone'],
            'is_enabled' => ['sometimes', 'boolean'],
            'max_runtime_seconds' => ['required', 'integer', 'between:10,86400'],
            'cooldown_seconds' => ['required', 'integer', 'between:0,86400'],
            'runner_type' => ['required', Rule::in(['claude', 'codex', 'custom'])],
            'command_template' => ['nullable', 'string', 'max:2000'],
            'task_markdown_path' => ['nullable', 'string', 'max:1024', 'required_without:task_markdown_content'],
            'task_markdown_content' => ['nullable', 'string', 'max:200000', 'required_without:task_markdown_path'],
            'working_directory' => ['required', 'string', 'max:1024'],
            'env_json' => ['nullable', 'array'],
            'active_hours_config' => ['nullable', 'array'],
            'active_hours_config.start' => ['required_with:active_hours_config', 'date_format:H:i'],
            'active_hours_config.end' => ['required_with:active_hours_config', 'date_format:H:i'],
            'active_hours_config.days' => ['required_with:active_hours_config', 'array', 'min:1'],
            'active_hours_config.days.*' => ['integer', 'between:1,7'],
            'star_preamble_enabled' => ['nullable', 'boolean'],
            'targeted_retry_enabled' => ['nullable', 'boolean'],
            'max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pathPolicy = app(PathPolicy::class);
            $envPolicy = app(EnvPolicy::class);
            $commandPolicy = app(CommandPolicy::class);

            if ($this->description !== null && preg_match('/\p{C}/u', (string) $this->description)) {
                $validator->errors()->add('description', 'The description may not contain control characters.');
            }

            $taskMarkdownPath = trim((string) ($this->input('task_markdown_path') ?? ''));
            $taskMarkdownContent = (string) ($this->input('task_markdown_content') ?? '');

            if ($taskMarkdownPath !== '') {
                $taskError = $pathPolicy->validateTaskMarkdownPath($taskMarkdownPath);
                if ($taskError !== null) {
                    $validator->errors()->add('task_markdown_path', $taskError);
                }
            }

            if ($taskMarkdownPath === '' && trim($taskMarkdownContent) === '') {
                $validator->errors()->add('task_markdown_content', 'Either task_markdown_path or task_markdown_content is required.');
            }

            $workingDirError = $pathPolicy->validateWorkingDirectory($this->input('working_directory'));
            if ($workingDirError !== null) {
                $validator->errors()->add('working_directory', $workingDirError);
            }

            foreach ($envPolicy->validate($this->input('env_json')) as $field => $message) {
                $validator->errors()->add($field, $message);
            }

            $runnerType = (string) $this->input('runner_type');
            if ($runnerType !== '') {
                $commandResult = $commandPolicy->validateForSave($runnerType, $this->input('command_template'));

                foreach ($commandResult['errors'] as $field => $message) {
                    $validator->errors()->add($field, $message);
                }

                if ($validator->errors()->isEmpty()) {
                    $this->normalizedCommandTemplate = $commandResult['normalized_template'];
                    $this->resolvedExecutablePath = $commandResult['resolved_executable_path'];
                }
            }
        });
    }

    public function normalizedCommandTemplate(): ?string
    {
        return $this->normalizedCommandTemplate;
    }

    public function resolvedExecutablePath(): ?string
    {
        return $this->resolvedExecutablePath;
    }
}
