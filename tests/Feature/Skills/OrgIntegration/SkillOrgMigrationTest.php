<?php

declare(strict_types=1);

namespace Tests\Feature\Skills\OrgIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkillOrgMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_skill_access_profile_column_exists_on_org_agent_profiles(): void
    {
        $this->assertTrue(
            Schema::hasColumn('org_agent_profiles', 'skill_access_profile'),
            'Column skill_access_profile should exist on org_agent_profiles table'
        );
    }

    public function test_required_skills_column_exists_on_org_ritual_templates(): void
    {
        $this->assertTrue(
            Schema::hasColumn('org_ritual_templates', 'required_skills'),
            'Column required_skills should exist on org_ritual_templates table'
        );
    }
}
