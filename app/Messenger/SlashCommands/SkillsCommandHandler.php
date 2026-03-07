<?php

declare(strict_types=1);

namespace App\Messenger\SlashCommands;

use App\Contracts\Messenger\SlashCommandHandlerInterface;
use App\DTOs\Messenger\CommandResult;
use App\Models\AgentSkill;
use App\Models\User;
use App\Services\Skills\SkillInstaller;
use App\Services\Skills\SkillLibrary;
use App\Support\Agent\FeatureFlagManager;

final class SkillsCommandHandler implements SlashCommandHandlerInterface
{
    private const VALID_SUBCOMMANDS = ['list', 'install', 'pause', 'resume', 'info'];

    public function __construct(
        private readonly FeatureFlagManager $flags,
        private readonly SkillInstaller $installer,
        private readonly SkillLibrary $library,
    ) {}

    public function handle(User $user, array $args, ?string $chatSessionId = null, ?string $connectorAccountId = null): CommandResult
    {
        if (! $this->flags->isEnabled(FeatureFlagManager::SKILLS_ENABLED)) {
            return CommandResult::failure('Skills feature is disabled.');
        }

        if (empty($args)) {
            return $this->showUsage();
        }

        $subcommand = strtolower($args[0]);
        $subArgs = array_slice($args, 1);

        if (! in_array($subcommand, self::VALID_SUBCOMMANDS, true)) {
            return $this->showUsage();
        }

        return match ($subcommand) {
            'list' => $this->listSkills($user),
            'install' => $this->installSkill($user, $subArgs),
            'pause' => $this->pauseSkill($user, $subArgs),
            'resume' => $this->resumeSkill($user, $subArgs),
            'info' => $this->skillInfo($user, $subArgs),
            default => $this->showUsage(),
        };
    }

    private function listSkills(User $user): CommandResult
    {
        $team = $user->currentTeam;
        if ($team === null) {
            return CommandResult::failure('No team selected.');
        }

        $skills = AgentSkill::query()
            ->where(function ($q) use ($team) {
                $q->where('team_id', $team->id)->orWhere('is_global', true);
            })
            ->orderBy('name')
            ->get(['name', 'slug', 'version', 'risk_level', 'status', 'invocation_count']);

        if ($skills->isEmpty()) {
            return CommandResult::success('No skills installed.', ['skills' => []]);
        }

        $lines = ["**Installed Skills** ({$skills->count()})\n"];
        $lines[] = '| Name | Version | Risk | Status | Invocations |';
        $lines[] = '|------|---------|------|--------|-------------|';

        foreach ($skills as $skill) {
            $lines[] = "| {$skill->name} | {$skill->version} | {$skill->risk_level} | {$skill->status} | {$skill->invocation_count} |";
        }

        return CommandResult::success(
            implode("\n", $lines),
            ['skills' => $skills->toArray()]
        );
    }

    private function installSkill(User $user, array $args): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure('Usage: /skills install <skill-name>');
        }

        $skillSlug = $args[0];
        $isConfirmed = in_array('--confirmed', $args, true);

        if (! $this->flags->isEnabled(FeatureFlagManager::SKILLS_LIBRARY_ENABLED)) {
            return CommandResult::failure('Skills library is disabled.');
        }

        $team = $user->currentTeam;
        if ($team === null) {
            return CommandResult::failure('No team selected.');
        }

        if (! $this->isTeamAdmin($user)) {
            return CommandResult::failure('Only team owners and admins can install skills.');
        }

        $entry = $this->library->find($skillSlug);
        if ($entry === null) {
            return CommandResult::failure("Skill '{$skillSlug}' not found in library.");
        }

        if (! $isConfirmed) {
            return CommandResult::success(
                "**Install Skill: {$entry->name}**\n\n".
                "Description: {$entry->description}\n".
                "Version: {$entry->version}\n".
                "Risk Level: {$entry->riskLevel}\n\n".
                'Please confirm installation by running `/skills install '.$skillSlug.' --confirmed`',
                [
                    'requires_confirmation' => true,
                    'skill_slug' => $skillSlug,
                    'skill_name' => $entry->name,
                ]
            );
        }

        $result = $this->installer->installFromLibrary(
            $skillSlug,
            $entry->filePath,
            $team->id,
            $user->id,
        );

        if (! $result->success) {
            return CommandResult::failure(
                "Failed to install '{$skillSlug}': ".implode(', ', $result->errors)
            );
        }

        return CommandResult::success(
            "Skill '{$entry->name}' installed successfully.",
            ['skill_id' => $result->skillId]
        );
    }

    private function pauseSkill(User $user, array $args): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure('Usage: /skills pause <skill-name>');
        }

        if (! $this->isTeamAdmin($user)) {
            return CommandResult::failure('Only team owners and admins can pause skills.');
        }

        $skill = $this->findSkillByName($user, $args[0]);
        if ($skill === null) {
            return CommandResult::failure("Skill '{$args[0]}' not found.");
        }

        if ($skill->status === AgentSkill::STATUS_PAUSED) {
            return CommandResult::failure("Skill '{$skill->name}' is already paused.");
        }

        $skill->update([
            'status' => AgentSkill::STATUS_PAUSED,
            'paused_by' => $user->id,
            'paused_at' => now(),
        ]);

        return CommandResult::success("Skill '{$skill->name}' has been paused.");
    }

    private function resumeSkill(User $user, array $args): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure('Usage: /skills resume <skill-name>');
        }

        if (! $this->isTeamAdmin($user)) {
            return CommandResult::failure('Only team owners and admins can resume skills.');
        }

        $skill = $this->findSkillByName($user, $args[0]);
        if ($skill === null) {
            return CommandResult::failure("Skill '{$args[0]}' not found.");
        }

        if ($skill->status === AgentSkill::STATUS_ACTIVE) {
            return CommandResult::failure("Skill '{$skill->name}' is already active.");
        }

        $skill->update([
            'status' => AgentSkill::STATUS_ACTIVE,
            'paused_by' => null,
            'paused_at' => null,
        ]);

        return CommandResult::success("Skill '{$skill->name}' has been resumed.");
    }

    private function skillInfo(User $user, array $args): CommandResult
    {
        if (empty($args)) {
            return CommandResult::failure('Usage: /skills info <skill-name>');
        }

        $skill = $this->findSkillByName($user, $args[0]);
        if ($skill === null) {
            return CommandResult::failure("Skill '{$args[0]}' not found.");
        }

        $lastInvoked = $skill->last_invoked_at
            ? $skill->last_invoked_at->diffForHumans()
            : 'Never';

        $message = "**Skill: {$skill->name}**\n\n".
            "Description: {$skill->description}\n".
            "Version: {$skill->version}\n".
            "Author: {$skill->author}\n".
            "Risk Level: {$skill->risk_level}\n".
            "Status: {$skill->status}\n".
            "Invocations: {$skill->invocation_count}\n".
            "Last Invoked: {$lastInvoked}";

        return CommandResult::success($message, [
            'skill' => $skill->toArray(),
        ]);
    }

    private function showUsage(): CommandResult
    {
        return CommandResult::failure(
            "Usage: /skills <subcommand>\n".
            "  list              - List installed skills\n".
            "  install <name>    - Install a skill from the library\n".
            "  pause <name>      - Pause a skill\n".
            "  resume <name>     - Resume a paused skill\n".
            '  info <name>       - Show skill details'
        );
    }

    private function findSkillByName(User $user, string $name): ?AgentSkill
    {
        $team = $user->currentTeam;
        if ($team === null) {
            return null;
        }

        return AgentSkill::query()
            ->where(function ($q) use ($team) {
                $q->where('team_id', $team->id)->orWhere('is_global', true);
            })
            ->where(function ($q) use ($name) {
                $q->where('slug', $name)->orWhere('name', $name);
            })
            ->first();
    }

    private function isTeamAdmin(User $user): bool
    {
        $team = $user->currentTeam;
        if ($team === null) {
            return false;
        }

        return $user->ownsTeam($team) || $user->hasTeamRole($team, 'admin');
    }
}
