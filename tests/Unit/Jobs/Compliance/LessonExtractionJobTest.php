<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Compliance;

use App\Jobs\Compliance\LessonExtractionJob;
use App\Support\Compliance\LessonsManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class LessonExtractionJobTest extends TestCase
{
    public function test_implements_should_queue(): void
    {
        $job = new LessonExtractionJob('content', 'source', '/tmp');
        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    public function test_configuration_values(): void
    {
        $job = new LessonExtractionJob('content', 'source', '/tmp');

        $this->assertSame(2, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame('default', $job->queue);
        $this->assertSame([5, 15], $job->backoff());
    }

    public function test_tags_include_source(): void
    {
        $job = new LessonExtractionJob('content', 'auto_failure_extraction', '/tmp');

        $this->assertSame([
            'compliance',
            'lesson',
            'source:auto_failure_extraction',
        ], $job->tags());
    }

    public function test_should_queue_returns_false_when_flag_disabled(): void
    {
        config()->set('compliance.lessons_enabled', false);

        $job = new LessonExtractionJob('content', 'source', '/tmp');

        $this->assertFalse($job->shouldQueue());
    }

    public function test_should_queue_returns_true_when_flag_enabled(): void
    {
        config()->set('compliance.lessons_enabled', true);

        $job = new LessonExtractionJob('content', 'source', '/tmp');

        $this->assertTrue($job->shouldQueue());
    }

    public function test_handle_skips_when_flag_disabled(): void
    {
        config()->set('compliance.lessons_enabled', false);

        $manager = Mockery::mock(LessonsManager::class);
        $manager->shouldNotReceive('appendLesson');

        Log::shouldReceive('debug')
            ->once()
            ->with('LessonExtractionJob: Lessons disabled, skipping');

        $job = new LessonExtractionJob('lesson content', 'source', '/tmp');
        $job->handle($manager);
    }

    public function test_handle_skips_empty_content(): void
    {
        config()->set('compliance.lessons_enabled', true);

        $manager = Mockery::mock(LessonsManager::class);
        $manager->shouldNotReceive('appendLesson');

        Log::shouldReceive('debug')
            ->once()
            ->with('LessonExtractionJob: Empty lesson content, skipping');

        $job = new LessonExtractionJob('   ', 'source', '/tmp');
        $job->handle($manager);
    }

    public function test_handle_appends_lesson_when_enabled(): void
    {
        config()->set('compliance.lessons_enabled', true);

        $context = [
            'task_title' => 'Test Job',
            'task_category' => 'testing',
            'runner_type' => 'claude',
            'run_id' => 42,
        ];

        $manager = Mockery::mock(LessonsManager::class);
        $manager->shouldReceive('appendLesson')
            ->once()
            ->with('/project', 'Something failed badly', 'auto_failure_extraction', $context);

        Log::shouldReceive('info')
            ->once()
            ->with('LessonExtractionJob: Lesson persisted', Mockery::on(function (array $log) {
                return $log['source'] === 'auto_failure_extraction'
                    && isset($log['context']['task_title'])
                    && isset($log['context']['run_id'])
                    && ! isset($log['context']['runner_type']); // filtered by array_flip
            }));

        $job = new LessonExtractionJob('Something failed badly', 'auto_failure_extraction', '/project', $context);
        $job->handle($manager);
    }

    public function test_failed_logs_warning_without_throwing(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('LessonExtractionJob: Failed permanently', Mockery::on(function (array $log) {
                return $log['source'] === 'test_source'
                    && $log['error'] === 'Something went wrong';
            }));

        $job = new LessonExtractionJob('content', 'test_source', '/tmp');
        $job->failed(new \RuntimeException('Something went wrong'));
    }
}
