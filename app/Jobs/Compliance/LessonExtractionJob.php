<?php

declare(strict_types=1);

namespace App\Jobs\Compliance;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Compliance\LessonsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class LessonExtractionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $lessonContent,
        public readonly string $source,
        public readonly string $projectDirectory,
        public readonly array $context = [],
    ) {
        $this->onQueue('default');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15];
    }

    public function shouldQueue(): bool
    {
        return app(FeatureFlagManager::class)->enabled(FeatureFlagManager::COMPLIANCE_LESSONS);
    }

    public function handle(LessonsManager $lessons): void
    {
        if (! app(FeatureFlagManager::class)->enabled(FeatureFlagManager::COMPLIANCE_LESSONS)) {
            Log::debug('LessonExtractionJob: Lessons disabled, skipping');

            return;
        }

        if (trim($this->lessonContent) === '') {
            Log::debug('LessonExtractionJob: Empty lesson content, skipping');

            return;
        }

        $lessons->appendLesson(
            $this->projectDirectory,
            $this->lessonContent,
            $this->source,
            $this->context,
        );

        Log::info('LessonExtractionJob: Lesson persisted', [
            'source' => $this->source,
            'context' => array_intersect_key(
                $this->context,
                array_flip(['task_title', 'run_id', 'session_id'])
            ),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('LessonExtractionJob: Failed permanently', [
            'source' => $this->source,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'compliance',
            'lesson',
            'source:'.$this->source,
        ];
    }
}
