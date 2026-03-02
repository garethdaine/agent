<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\RepoAnalysis;

use App\Models\RepoAnalysisTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RetryRepoAnalysisTaskRequest extends FormRequest
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
            'task_id' => ['required', 'integer', 'exists:repo_analysis_tasks,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sessionId = (int) $this->route('id');
            $taskId = (int) $this->input('task_id');

            if ($sessionId <= 0 || $taskId <= 0) {
                return;
            }

            $belongsToSession = RepoAnalysisTask::query()
                ->where('id', $taskId)
                ->where('repo_analysis_session_id', $sessionId)
                ->exists();

            if (! $belongsToSession) {
                $validator->errors()->add('task_id', 'The selected task_id is invalid for this session.');
            }
        });
    }
}
