<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Skills;

use App\Http\Controllers\Controller;
use App\Http\Requests\Skills\InstallSkillRequest;
use App\Http\Requests\Skills\UpdateSkillRequest;
use App\Http\Resources\Skills\SkillLibraryResource;
use App\Http\Resources\Skills\SkillResource;
use App\Models\AgentSkill;
use App\Services\Skills\SkillInstaller;
use App\Services\Skills\SkillLibrary;
use App\Services\Skills\SkillParser;
use App\Services\Skills\SkillValidator;
use App\Support\Agent\FeatureFlagManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\File;

class SkillController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $flags,
        private readonly SkillInstaller $installer,
        private readonly SkillLibrary $library,
        private readonly SkillParser $parser,
        private readonly SkillValidator $validator,
    ) {}

    public function install(InstallSkillRequest $request): JsonResponse|SkillResource
    {
        $this->ensureSkillsEnabled();
        $this->authorizeTeamAdmin($request);

        $file = $request->file('file');
        $result = $this->installer->installFromFile(
            filePath: $file->getRealPath(),
            teamId: $this->requireCurrentTeamId($request),
            userId: $request->user()->id,
        );

        if (! $result->success) {
            return response()->json([
                'error' => [
                    'code' => 'SKILL_VALIDATION_FAILED',
                    'message' => 'Skill validation failed.',
                    'details' => [
                        'errors' => $result->errors,
                        'warnings' => $result->warnings,
                        'requires_confirmation' => $result->requiresAdminConfirmation,
                        'blocked' => $result->blocked,
                    ],
                ],
            ], 422);
        }

        $skill = AgentSkill::findOrFail($result->skillId);

        return (new SkillResource($skill))
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureSkillsEnabled();

        $teamId = $this->requireCurrentTeamId($request);
        $perPage = min((int) $request->query('per_page', 25), 100);

        $query = AgentSkill::query()
            ->where(function ($q) use ($teamId) {
                $q->where('team_id', $teamId)
                    ->orWhere('is_global', true);
            });

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->query('risk_level'));
        }

        if ($request->filled('industry')) {
            $query->whereJsonContains('industries', $request->query('industry'));
        }

        $skills = $query->orderBy('name')->paginate($perPage);

        return SkillResource::collection($skills);
    }

    public function show(Request $request, string $id): SkillResource|JsonResponse
    {
        $this->ensureSkillsEnabled();

        $teamId = $this->requireCurrentTeamId($request);

        $skill = AgentSkill::where(function ($q) use ($teamId) {
            $q->where('team_id', $teamId)
                ->orWhere('is_global', true);
        })->find($id);

        if (! $skill) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Skill not found.',
                ],
            ], 404);
        }

        return new SkillResource($skill);
    }

    public function update(UpdateSkillRequest $request, string $id): SkillResource|JsonResponse
    {
        $this->ensureSkillsEnabled();
        $this->authorizeTeamAdmin($request);

        $skill = AgentSkill::where('team_id', $this->requireCurrentTeamId($request))->find($id);

        if (! $skill) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Skill not found.',
                ],
            ], 404);
        }

        if ($request->has('status')) {
            $updates = ['status' => $request->input('status'), 'updated_by' => $request->user()->id, 'updated_at' => now()];

            if ($request->input('status') === AgentSkill::STATUS_PAUSED) {
                $updates['paused_by'] = $request->user()->id;
                $updates['paused_at'] = now();
            } elseif ($request->input('status') === AgentSkill::STATUS_ACTIVE) {
                $updates['paused_by'] = null;
                $updates['paused_at'] = null;
            }

            $skill->update($updates);
        }

        if ($request->hasFile('file')) {
            $result = $this->installer->update(
                skillId: $skill->id,
                filePath: $request->file('file')->getRealPath(),
                userId: $request->user()->id,
            );

            if (! $result->success) {
                return response()->json([
                    'error' => [
                        'code' => 'SKILL_VALIDATION_FAILED',
                        'message' => 'Skill update validation failed.',
                        'details' => [
                            'errors' => $result->errors,
                            'warnings' => $result->warnings,
                        ],
                    ],
                ], 422);
            }

            $skill->refresh();
        }

        return new SkillResource($skill);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->ensureSkillsEnabled();
        $this->authorizeTeamAdmin($request);

        $skill = AgentSkill::where('team_id', $this->requireCurrentTeamId($request))->find($id);

        if (! $skill) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Skill not found.',
                ],
            ], 404);
        }

        $this->installer->remove($skill->id, $request->user()->id);

        return response()->json(null, 204);
    }

    public function revalidate(Request $request, string $id): SkillResource|JsonResponse
    {
        $this->ensureSkillsEnabled();
        $this->authorizeTeamAdmin($request);

        $skill = AgentSkill::where('team_id', $this->requireCurrentTeamId($request))->find($id);

        if (! $skill) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Skill not found.',
                ],
            ], 404);
        }

        if (! File::isDirectory($skill->skill_path)) {
            return response()->json([
                'error' => [
                    'code' => 'SKILL_PATH_MISSING',
                    'message' => 'Skill files not found on disk.',
                ],
            ], 422);
        }

        $skillMdPath = $skill->skill_path.'/SKILL.md';
        if (! File::exists($skillMdPath)) {
            return response()->json([
                'error' => [
                    'code' => 'SKILL_MD_MISSING',
                    'message' => 'SKILL.md not found in skill directory.',
                ],
            ], 422);
        }

        $parsed = $this->parser->parse(File::get($skillMdPath));
        $validation = $this->validator->validate(
            $skill->skill_path,
            $parsed,
            'revalidation',
            $skill->team_id,
        );

        $newStatus = $validation->overallPass ? AgentSkill::STATUS_ACTIVE : AgentSkill::STATUS_FAILED_VALIDATION;

        $skill->update([
            'validation_result' => $validation->toArray(),
            'status' => $newStatus,
            'updated_by' => $request->user()->id,
            'updated_at' => now(),
        ]);

        return new SkillResource($skill);
    }

    public function library(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $this->ensureSkillsEnabled();

        if (! $this->flags->enabled(FeatureFlagManager::SKILLS_LIBRARY_ENABLED)) {
            return response()->json([
                'error' => [
                    'code' => 'FEATURE_DISABLED',
                    'message' => 'Skills library is not enabled.',
                ],
            ], 403);
        }

        $filters = array_filter([
            'category' => $request->query('category'),
            'industry' => $request->query('industry'),
            'search' => $request->query('search'),
        ]);

        $entries = $this->library->browse($filters);

        return SkillLibraryResource::collection($entries);
    }

    public function installFromLibrary(Request $request, string $slug): JsonResponse|SkillResource
    {
        $this->ensureSkillsEnabled();
        $this->authorizeTeamAdmin($request);

        if (! $this->flags->enabled(FeatureFlagManager::SKILLS_LIBRARY_ENABLED)) {
            return response()->json([
                'error' => [
                    'code' => 'FEATURE_DISABLED',
                    'message' => 'Skills library is not enabled.',
                ],
            ], 403);
        }

        $entry = $this->library->find($slug);

        if (! $entry) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => "Library skill '{$slug}' not found.",
                ],
            ], 404);
        }

        $filePath = base_path($entry->filePath);

        $result = $this->installer->installFromLibrary(
            slug: $slug,
            filePath: $filePath,
            teamId: $this->requireCurrentTeamId($request),
            userId: $request->user()->id,
        );

        if (! $result->success) {
            return response()->json([
                'error' => [
                    'code' => 'SKILL_VALIDATION_FAILED',
                    'message' => 'Library skill installation failed.',
                    'details' => [
                        'errors' => $result->errors,
                        'warnings' => $result->warnings,
                    ],
                ],
            ], 422);
        }

        $skill = AgentSkill::findOrFail($result->skillId);

        return (new SkillResource($skill))
            ->response()
            ->setStatusCode(201);
    }

    private function ensureSkillsEnabled(): void
    {
        if (! $this->flags->enabled(FeatureFlagManager::SKILLS_ENABLED)) {
            abort(403, 'Skills feature is not enabled.');
        }
    }

    private function requireCurrentTeamId(Request $request): int
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        return (int) $team->id;
    }

    private function authorizeTeamAdmin(Request $request): void
    {
        $team = $request->user()->currentTeam;

        if (! $team) {
            abort(403, 'No current team selected.');
        }

        if (! $request->user()->ownsTeam($team) && ! $request->user()->hasTeamRole($team, 'admin')) {
            abort(403, 'Team owner or admin role required.');
        }
    }
}
