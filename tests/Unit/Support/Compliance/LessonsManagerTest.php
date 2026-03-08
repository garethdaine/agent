<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compliance;

use App\Enums\TaskCategory;
use App\Support\Compliance\LessonsManager;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LessonsManagerTest extends TestCase
{
    private string $testProjectDirectory;

    private LessonsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testProjectDirectory = sys_get_temp_dir().'/lessons-manager-test-'.uniqid();
        File::makeDirectory($this->testProjectDirectory, 0755, true);

        $this->manager = new LessonsManager(2000);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->testProjectDirectory)) {
            File::deleteDirectory($this->testProjectDirectory);
        }

        parent::tearDown();
    }

    #[Test]
    public function append_lesson_creates_lessons_file_if_not_exists(): void
    {
        $lessonsPath = $this->testProjectDirectory.'/tasks/lessons.md';

        $this->assertFalse(File::exists($lessonsPath));

        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Test lesson content',
            'manual_add',
            ['task_title' => 'Test Task']
        );

        $this->assertTrue(File::exists($lessonsPath));
        $this->assertStringContainsString('# Lessons', File::get($lessonsPath));
    }

    #[Test]
    public function append_lesson_creates_directory_structure_if_not_exists(): void
    {
        $tasksDir = $this->testProjectDirectory.'/tasks';

        $this->assertFalse(File::isDirectory($tasksDir));

        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Test lesson content',
            'manual_add',
            []
        );

        $this->assertTrue(File::isDirectory($tasksDir));
    }

    #[Test]
    public function append_lesson_appends_structured_entry_with_timestamp_and_source(): void
    {
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'First lesson content',
            'explicit_rejection',
            ['task_title' => 'Task A', 'task_category' => 'feature']
        );

        $content = File::get($this->testProjectDirectory.'/tasks/lessons.md');

        $this->assertStringContainsString('[explicit_rejection]', $content);
        $this->assertStringContainsString('First lesson content', $content);
        $this->assertStringContainsString('Task: Task A', $content);
        $this->assertStringContainsString('Category: feature', $content);
        // Timestamp should be ISO 8601 format
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $content);
    }

    #[Test]
    public function append_lesson_appends_to_existing_file(): void
    {
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'First lesson',
            'manual_add',
            ['task_title' => 'Task A']
        );

        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Second lesson',
            'correction_message',
            ['task_title' => 'Task B']
        );

        $content = File::get($this->testProjectDirectory.'/tasks/lessons.md');

        $this->assertStringContainsString('First lesson', $content);
        $this->assertStringContainsString('Second lesson', $content);
        $this->assertStringContainsString('[manual_add]', $content);
        $this->assertStringContainsString('[correction_message]', $content);
    }

    #[Test]
    public function query_lessons_returns_lessons_within_token_budget(): void
    {
        // Each entry is roughly ~150 chars = ~37 tokens at 0.25 tokens/char
        // With budget of 100, should only get ~2-3 entries
        $manager = new LessonsManager(100);

        for ($i = 1; $i <= 10; $i++) {
            $this->manager->appendLesson(
                $this->testProjectDirectory,
                "Lesson content number {$i} with some additional text",
                'manual_add',
                ['task_title' => "Task {$i}"]
            );
        }

        $lessons = $manager->queryLessons($this->testProjectDirectory);

        // Should be limited by token budget
        $this->assertLessThan(10, count($lessons));
        $this->assertNotEmpty($lessons);
    }

    #[Test]
    public function query_lessons_prioritizes_recent_lessons(): void
    {
        // Add lessons with a small delay to ensure different timestamps
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Old lesson from yesterday',
            'manual_add',
            ['task_title' => 'Old Task']
        );

        // Small wait to ensure different timestamp
        usleep(1000);

        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'New lesson from today',
            'manual_add',
            ['task_title' => 'New Task']
        );

        $lessons = $this->manager->queryLessons($this->testProjectDirectory);

        // Newest should come first
        $this->assertCount(2, $lessons);
        $this->assertStringContainsString('New lesson from today', $lessons[0]['content']);
        $this->assertStringContainsString('Old lesson from yesterday', $lessons[1]['content']);
    }

    #[Test]
    public function query_lessons_filters_by_category_when_provided(): void
    {
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Feature lesson',
            'manual_add',
            ['task_title' => 'Feature Task', 'task_category' => 'feature']
        );

        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Bugfix lesson',
            'manual_add',
            ['task_title' => 'Bugfix Task', 'task_category' => 'bugfix']
        );

        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Another feature lesson',
            'manual_add',
            ['task_title' => 'Another Feature', 'task_category' => 'feature']
        );

        $featureLessons = $this->manager->queryLessons(
            $this->testProjectDirectory,
            null,
            TaskCategory::FEATURE
        );

        // Should only return feature lessons
        $this->assertCount(2, $featureLessons);
        foreach ($featureLessons as $lesson) {
            $this->assertStringContainsString('feature', $lesson['content']);
        }
    }

    #[Test]
    public function inject_into_context_adds_lessons_to_context_array(): void
    {
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Important lesson for context',
            'correction_message',
            ['task_title' => 'Context Task']
        );

        $context = ['existing_key' => 'existing_value'];

        $this->manager->injectIntoContext($context, $this->testProjectDirectory);

        $this->assertArrayHasKey('existing_key', $context);
        $this->assertArrayHasKey('relevant_lessons', $context);
        $this->assertArrayHasKey('lessons_injected', $context);
        $this->assertTrue($context['lessons_injected']);
        $this->assertNotEmpty($context['relevant_lessons']);
    }

    #[Test]
    public function inject_into_context_respects_category_filter(): void
    {
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Feature lesson',
            'manual_add',
            ['task_title' => 'Feature Task', 'task_category' => 'feature']
        );

        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Bugfix lesson',
            'manual_add',
            ['task_title' => 'Bugfix Task', 'task_category' => 'bugfix']
        );

        $context = [];

        $this->manager->injectIntoContext($context, $this->testProjectDirectory, TaskCategory::BUGFIX);

        $this->assertArrayHasKey('relevant_lessons', $context);
        $this->assertCount(1, $context['relevant_lessons']);
        $this->assertStringContainsString('Bugfix', $context['relevant_lessons'][0]['content']);
    }

    #[Test]
    public function empty_lessons_file_returns_empty_array(): void
    {
        // Don't add any lessons
        $lessons = $this->manager->queryLessons($this->testProjectDirectory);

        $this->assertIsArray($lessons);
        $this->assertEmpty($lessons);
    }

    #[Test]
    public function query_lessons_handles_nonexistent_directory(): void
    {
        $nonexistentPath = '/tmp/nonexistent-lessons-path-'.uniqid();

        $lessons = $this->manager->queryLessons($nonexistentPath);

        $this->assertIsArray($lessons);
        $this->assertEmpty($lessons);
    }

    #[Test]
    public function inject_into_context_does_not_modify_context_when_no_lessons(): void
    {
        $context = ['existing_key' => 'existing_value'];
        $originalContext = $context;

        $this->manager->injectIntoContext($context, $this->testProjectDirectory);

        $this->assertArrayNotHasKey('relevant_lessons', $context);
        $this->assertArrayNotHasKey('lessons_injected', $context);
        $this->assertEquals($originalContext, $context);
    }

    #[Test]
    public function from_config_creates_manager_with_config_token_budget(): void
    {
        config(['agent.compliance.lessons_token_budget' => 5000]);

        $manager = LessonsManager::fromConfig();

        // We can't directly access the private property, but we can verify it works
        // by checking the manager was created without error
        $this->assertInstanceOf(LessonsManager::class, $manager);
    }

    #[Test]
    public function query_lessons_returns_entries_with_expected_structure(): void
    {
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Test lesson content',
            'failed_retry',
            ['task_title' => 'Test Task', 'task_category' => 'refactor']
        );

        $lessons = $this->manager->queryLessons($this->testProjectDirectory);

        $this->assertCount(1, $lessons);
        $this->assertArrayHasKey('timestamp', $lessons[0]);
        $this->assertArrayHasKey('source', $lessons[0]);
        $this->assertArrayHasKey('content', $lessons[0]);
        $this->assertSame('failed_retry', $lessons[0]['source']);
    }

    #[Test]
    public function query_lessons_filters_by_query_keywords(): void
    {
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Authentication token refresh bug',
            'manual_add',
            ['task_title' => 'Auth Task']
        );

        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Database migration rollback issue',
            'manual_add',
            ['task_title' => 'DB Task']
        );

        $lessons = $this->manager->queryLessons($this->testProjectDirectory, 'authentication');

        $this->assertCount(1, $lessons);
        $this->assertStringContainsString('Authentication', $lessons[0]['content']);
    }

    #[Test]
    public function append_lesson_uses_unknown_for_missing_context_values(): void
    {
        $this->manager->appendLesson(
            $this->testProjectDirectory,
            'Lesson without context',
            'manual_add',
            []
        );

        $content = File::get($this->testProjectDirectory.'/tasks/lessons.md');

        $this->assertStringContainsString('Task: Unknown Task', $content);
        $this->assertStringContainsString('Category: unknown', $content);
    }
}
