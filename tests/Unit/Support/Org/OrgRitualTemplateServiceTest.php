<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Org;

use App\Models\OrgAgentProfile;
use App\Models\OrgRitualTemplate;
use App\Models\User;
use App\Support\Org\Exceptions\InvalidCronExpressionException;
use App\Support\Org\Exceptions\InvalidPhaseRoleMappingException;
use App\Support\Org\OrgRitualTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgRitualTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrgRitualTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrgRitualTemplateService::class);
    }

    public function test_creates_template_with_valid_data(): void
    {
        $user = User::factory()->create();
        $agent = OrgAgentProfile::factory()->forUser($user)->create(['role_slug' => 'developer']);

        $template = $this->service->create($user, [
            'name' => 'Daily Standup',
            'cron_expression' => '0 9 * * 1-5',
            'timezone' => 'America/New_York',
            'phase_graph' => [
                ['id' => 'gather', 'name' => 'Gather Updates', 'depends_on' => []],
            ],
            'phase_role_mappings' => [
                'gather' => 'developer',
            ],
        ]);

        $this->assertInstanceOf(OrgRitualTemplate::class, $template);
    }

    public function test_rejects_invalid_cron_expression(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidCronExpressionException::class);

        $this->service->create($user, [
            'name' => 'Test',
            'cron_expression' => 'invalid cron',
            'phase_graph' => [],
            'phase_role_mappings' => [],
        ]);
    }

    public function test_rejects_mapping_to_nonexistent_role(): void
    {
        $user = User::factory()->create();
        OrgAgentProfile::factory()->forUser($user)->create(['role_slug' => 'developer']);

        $this->expectException(InvalidPhaseRoleMappingException::class);

        $this->service->create($user, [
            'name' => 'Test',
            'cron_expression' => '0 9 * * *',
            'phase_graph' => [
                ['id' => 'phase1', 'depends_on' => []],
            ],
            'phase_role_mappings' => [
                'phase1' => 'nonexistent_role',
            ],
        ]);
    }

    public function test_pause_sets_is_paused(): void
    {
        $template = OrgRitualTemplate::factory()->create(['is_paused' => false]);

        $this->service->pause($template);

        $this->assertTrue($template->fresh()->is_paused);
    }

    public function test_resume_clears_is_paused(): void
    {
        $template = OrgRitualTemplate::factory()->create(['is_paused' => true]);

        $this->service->resume($template);

        $this->assertFalse($template->fresh()->is_paused);
    }
}
