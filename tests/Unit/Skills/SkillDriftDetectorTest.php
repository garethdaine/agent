<?php

declare(strict_types=1);

namespace Tests\Unit\Skills;

use App\Models\AgentSkill;
use App\Services\Skills\DTOs\DriftCheckResult;
use App\Services\Skills\SkillDriftDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SkillDriftDetectorTest extends TestCase
{
    use RefreshDatabase;

    private SkillDriftDetector $detector;

    private string $skillPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new SkillDriftDetector;
        $this->skillPath = sys_get_temp_dir().'/drift-test-'.uniqid();
        mkdir($this->skillPath, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->skillPath);
        parent::tearDown();
    }

    public function test_no_drift_when_hashes_match(): void
    {
        $skillMd = "---\nname: test\n---\n\n# Test Skill";
        file_put_contents($this->skillPath.'/SKILL.md', $skillMd);

        $hashes = $this->detector->computeFileHashes($this->skillPath);

        $skill = AgentSkill::factory()->create([
            'skill_path' => $this->skillPath,
            'file_hashes' => $hashes,
        ]);

        $result = $this->detector->checkOnInvocation($skill);

        $this->assertInstanceOf(DriftCheckResult::class, $result);
        $this->assertFalse($result->hasDrift);
        $this->assertEmpty($result->changedFiles);
        $this->assertEmpty($result->addedFiles);
        $this->assertEmpty($result->missingFiles);
    }

    public function test_detects_modified_file(): void
    {
        file_put_contents($this->skillPath.'/SKILL.md', 'original content');
        $hashes = $this->detector->computeFileHashes($this->skillPath);

        $skill = AgentSkill::factory()->create([
            'skill_path' => $this->skillPath,
            'file_hashes' => $hashes,
        ]);

        // Modify the file
        file_put_contents($this->skillPath.'/SKILL.md', 'modified content');

        $result = $this->detector->checkOnInvocation($skill);

        $this->assertTrue($result->hasDrift);
        $this->assertContains('SKILL.md', $result->changedFiles);
    }

    public function test_detects_added_file(): void
    {
        file_put_contents($this->skillPath.'/SKILL.md', 'test content');
        $hashes = $this->detector->computeFileHashes($this->skillPath);

        $skill = AgentSkill::factory()->create([
            'skill_path' => $this->skillPath,
            'file_hashes' => $hashes,
        ]);

        // Add a new file
        file_put_contents($this->skillPath.'/new-file.md', 'new content');

        $result = $this->detector->checkOnInvocation($skill);

        $this->assertTrue($result->hasDrift);
        $this->assertContains('new-file.md', $result->addedFiles);
    }

    public function test_detects_missing_file(): void
    {
        file_put_contents($this->skillPath.'/SKILL.md', 'test content');
        file_put_contents($this->skillPath.'/extra.md', 'extra content');
        $hashes = $this->detector->computeFileHashes($this->skillPath);

        $skill = AgentSkill::factory()->create([
            'skill_path' => $this->skillPath,
            'file_hashes' => $hashes,
        ]);

        // Delete a file
        unlink($this->skillPath.'/extra.md');

        $result = $this->detector->checkOnInvocation($skill);

        $this->assertTrue($result->hasDrift);
        $this->assertContains('extra.md', $result->missingFiles);
    }

    public function test_check_all_scans_all_skills(): void
    {
        $user = \App\Models\User::factory()->withPersonalTeam()->create();
        $teamId = $user->currentTeam->id;

        $path1 = $this->skillPath.'/skill1';
        $path2 = $this->skillPath.'/skill2';
        mkdir($path1, 0755, true);
        mkdir($path2, 0755, true);

        file_put_contents($path1.'/SKILL.md', 'skill 1');
        file_put_contents($path2.'/SKILL.md', 'skill 2');

        $hashes1 = $this->detector->computeFileHashes($path1);
        $hashes2 = $this->detector->computeFileHashes($path2);

        AgentSkill::factory()->create([
            'team_id' => $teamId,
            'name' => 'skill-one',
            'skill_path' => $path1,
            'file_hashes' => $hashes1,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        AgentSkill::factory()->create([
            'team_id' => $teamId,
            'name' => 'skill-two',
            'skill_path' => $path2,
            'file_hashes' => $hashes2,
            'status' => AgentSkill::STATUS_ACTIVE,
        ]);

        $results = $this->detector->checkAll($teamId);

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(DriftCheckResult::class, $results->all());
    }
}
