<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compliance;

use App\Enums\TaskCategory;
use App\Support\Compliance\LessonsManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LessonsManagerRunnerTypeTest extends TestCase
{
    private string $testDir;

    private LessonsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir().'/lessons_test_'.uniqid();
        mkdir($this->testDir.'/tasks', 0755, true);
        $this->manager = new LessonsManager;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testDir);
        parent::tearDown();
    }

    public function test_append_lesson_stores_runner_type_in_context(): void
    {
        $this->manager->appendLesson(
            $this->testDir,
            'Test lesson content',
            'manual_add',
            ['task_title' => 'Test Task', 'task_category' => 'feature', 'runner_type' => 'claude']
        );

        $content = File::get($this->testDir.'/tasks/lessons.md');
        $this->assertStringContainsString('Runner: claude', $content);
    }

    public function test_query_lessons_filters_by_runner_type(): void
    {
        $this->manager->appendLesson($this->testDir, 'Claude lesson', 'manual_add', ['runner_type' => 'claude']);
        $this->manager->appendLesson($this->testDir, 'Codex lesson', 'manual_add', ['runner_type' => 'codex']);

        $claudeLessons = $this->manager->queryLessons($this->testDir, null, null, null, 'claude');
        $this->assertCount(1, $claudeLessons);
        $this->assertStringContainsString('Claude lesson', $claudeLessons[0]['content']);
    }

    public function test_query_lessons_runner_type_filter_combined_with_category(): void
    {
        $this->manager->appendLesson($this->testDir, 'Claude feature', 'manual_add', [
            'task_category' => 'feature',
            'runner_type' => 'claude',
        ]);
        $this->manager->appendLesson($this->testDir, 'Claude bugfix', 'manual_add', [
            'task_category' => 'bugfix',
            'runner_type' => 'claude',
        ]);

        $lessons = $this->manager->queryLessons($this->testDir, null, TaskCategory::FEATURE, null, 'claude');
        $this->assertCount(1, $lessons);
        $this->assertStringContainsString('Claude feature', $lessons[0]['content']);
    }
}
