<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DelegateeProfile;
use App\Models\DelegationCapability;
use App\Support\Agent\AuditLogger;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Delegation\TrustScoreCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DelegateeProfileController extends Controller
{
    public function capabilities(Request $request): JsonResponse
    {
        $capabilities = DelegationCapability::query()
            ->active()
            ->orderBy('slug')
            ->get(['id', 'slug', 'name', 'description']);

        return response()->json([
            'data' => $capabilities->map(fn (DelegationCapability $c): array => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'description' => $c->description,
            ])->values()->all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->delegateeProfiles()->newQuery();

        $deleted = $request->string('deleted')->toString();
        if ($deleted === '1' || $deleted === 'true') {
            $query->onlyTrashed(); // @phpstan-ignore method.notFound
        } elseif ($deleted === 'all') {
            $query->withTrashed(); // @phpstan-ignore method.notFound
        }

        $q = trim($request->string('q')->toString());
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('runner_type', 'like', "%{$q}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL));
        }

        if ($request->filled('runner_type')) {
            $query->where('runner_type', $request->string('runner_type')->toString());
        }

        $sort = $request->string('sort', 'name')->toString();
        $dir = strtolower($request->string('dir', 'asc')->toString()) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'updated_at', 'created_at', 'is_active', 'runner_type'], true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $dir);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));
        $profiles = $query->with(['capabilities', 'metric'])->paginate($perPage)->withQueryString();

        /** @var \Illuminate\Support\Collection<int, DelegateeProfile> $profileItems */
        $profileItems = collect($profiles->items());
        $data = $profileItems->map(fn (DelegateeProfile $profile): array => $this->transformProfile($profile))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $profiles->currentPage(),
                'per_page' => $profiles->perPage(),
                'total' => $profiles->total(),
                'last_page' => $profiles->lastPage(),
            ],
            'links' => [
                'first' => $profiles->url(1),
                'last' => $profiles->url($profiles->lastPage()),
                'prev' => $profiles->previousPageUrl(),
                'next' => $profiles->nextPageUrl(),
            ],
            'filters' => [
                'q' => $request->input('q'),
                'is_active' => $request->input('is_active'),
                'runner_type' => $request->input('runner_type'),
                'deleted' => $request->input('deleted'),
            ],
            'sort' => [
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var DelegateeProfile|null $profile */
        $profile = $request->user()->delegateeProfiles()->withTrashed()->with(['capabilities', 'metric'])->find($id); // @phpstan-ignore method.notFound

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        return response()->json([
            'data' => $this->transformProfile($profile, true),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'runner_type' => ['required', 'string', 'max:32'],
            'command_template' => ['required', 'string', 'max:2000'],
            'working_directory' => ['required', 'string', 'max:1024'],
            'env_json' => ['nullable', 'array'],
            'config_json' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'capability_ids' => ['sometimes', 'array'],
            'capability_ids.*' => ['integer', 'exists:delegation_capabilities,id'],
            'soul' => ['nullable', 'array'],
            'soul.personality' => ['nullable', 'string', 'max:2000'],
            'soul.system_prompt' => ['nullable', 'string', 'max:5000'],
            'soul.user_context' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var DelegateeProfile $profile */
        $profile = $request->user()->delegateeProfiles()->create([
            'name' => $validated['name'],
            'runner_type' => $validated['runner_type'],
            'command_template' => $validated['command_template'],
            'working_directory' => $validated['working_directory'],
            'env_json' => $validated['env_json'] ?? null,
            'config_json' => $validated['config_json'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Sync capabilities if provided
        if (isset($validated['capability_ids'])) {
            $profile->capabilities()->sync($validated['capability_ids']);
        }

        if (isset($validated['soul'])) {
            $profile->setSoul($validated['soul']);
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegatee_profile.create',
            targetType: 'delegatee_profile',
            targetId: (int) $profile->id,
            ownerUserId: (int) $profile->user_id,
            changedFields: array_keys($profile->getAttributes()),
            before: null,
            after: $profile->only(['id', 'name', 'runner_type', 'is_active']),
        );

        return response()->json([
            'data' => $this->transformProfile($profile->load(['capabilities', 'metric'])),
        ], 201);
    }

    public function update(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        /** @var DelegateeProfile|null $profile */
        $profile = $request->user()->delegateeProfiles()->withTrashed()->find($id); // @phpstan-ignore method.notFound

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'runner_type' => ['sometimes', 'string', 'max:32'],
            'command_template' => ['sometimes', 'string', 'max:2000'],
            'working_directory' => ['sometimes', 'string', 'max:1024'],
            'env_json' => ['nullable', 'array'],
            'config_json' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'capability_ids' => ['sometimes', 'array'],
            'capability_ids.*' => ['integer', 'exists:delegation_capabilities,id'],
            'soul' => ['sometimes', 'array'],
            'soul.personality' => ['nullable', 'string', 'max:2000'],
            'soul.system_prompt' => ['nullable', 'string', 'max:5000'],
            'soul.user_context' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = $profile->only(['name', 'runner_type', 'command_template', 'working_directory', 'is_active']);

        // Only update fields that are present
        $updateFields = array_intersect_key($validated, array_flip([
            'name', 'runner_type', 'command_template', 'working_directory', 'env_json', 'config_json', 'is_active',
        ]));

        $profile->fill($updateFields);
        $profile->save();

        // Sync capabilities if provided
        if (isset($validated['capability_ids'])) {
            $profile->capabilities()->sync($validated['capability_ids']);
        }

        if (isset($validated['soul'])) {
            $profile->setSoul($validated['soul']);
        }

        $after = $profile->only(array_keys($before));
        $changedFields = [];
        foreach ($after as $field => $value) {
            if ($before[$field] !== $value) {
                $changedFields[] = $field;
            }
        }

        if ($changedFields !== []) {
            $auditLogger->recordUserAction(
                request: $request,
                action: 'delegatee_profile.update',
                targetType: 'delegatee_profile',
                targetId: (int) $profile->id,
                ownerUserId: (int) $profile->user_id,
                changedFields: $changedFields,
                before: $before,
                after: $after,
            );
        }

        return response()->json([
            'data' => $this->transformProfile($profile->load(['capabilities', 'metric'])),
        ]);
    }

    public function destroy(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        /** @var DelegateeProfile|null $profile */
        $profile = $request->user()->delegateeProfiles()->find($id);

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        $before = ['deleted_at' => optional($profile->deleted_at)?->toIso8601String()];
        $profile->delete();

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegatee_profile.delete',
            targetType: 'delegatee_profile',
            targetId: (int) $profile->id,
            ownerUserId: (int) $profile->user_id,
            changedFields: ['deleted_at'],
            before: $before,
            after: ['deleted_at' => optional($profile->deleted_at)?->toIso8601String()],
        );

        return response()->json([
            'data' => [
                'deleted' => true,
                'id' => $profile->id,
            ],
        ]);
    }

    public function restore(Request $request, int $id, AuditLogger $auditLogger): JsonResponse
    {
        /** @var DelegateeProfile|null $profile */
        $profile = $request->user()->delegateeProfiles()->withTrashed()->find($id); // @phpstan-ignore method.notFound

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        $before = ['deleted_at' => optional($profile->deleted_at)?->toIso8601String()];

        if ($profile->trashed()) {
            $profile->restore();
        }

        $auditLogger->recordUserAction(
            request: $request,
            action: 'delegatee_profile.restore',
            targetType: 'delegatee_profile',
            targetId: (int) $profile->id,
            ownerUserId: (int) $profile->user_id,
            changedFields: ['deleted_at'],
            before: $before,
            after: ['deleted_at' => optional($profile->deleted_at)?->toIso8601String()],
        );

        return response()->json([
            'data' => $this->transformProfile($profile->fresh()->load(['capabilities', 'metric'])),
        ]);
    }

    public function trust(Request $request, int $id, TrustScoreCalculator $calculator): JsonResponse
    {
        $profile = DelegateeProfile::find($id);

        if ($profile === null) {
            return ErrorEnvelope::make('NOT_FOUND', 'Profile not found.', 404);
        }

        if ($profile->user_id !== $request->user()->id) {
            return ErrorEnvelope::make('FORBIDDEN', 'Access denied.', 403);
        }

        $trustScore = $calculator->calculate($profile->runner_type);

        return response()->json([
            'data' => $trustScore->toArray(),
        ]);
    }

    private function transformProfile(DelegateeProfile $profile, bool $includeDetails = false): array
    {
        $payload = [
            'id' => $profile->id,
            'name' => $profile->name,
            'runner_type' => $profile->runner_type,
            'command_template' => $profile->command_template,
            'working_directory' => $profile->working_directory,
            'is_active' => $profile->is_active,
            'trust_score' => $profile->trust_score !== null ? (float) $profile->trust_score : null,
            'trust_updated_at' => optional($profile->trust_updated_at)?->toIso8601String(),
            'deleted_at' => optional($profile->deleted_at)?->toIso8601String(),
            'created_at' => optional($profile->created_at)?->toIso8601String(),
            'updated_at' => optional($profile->updated_at)?->toIso8601String(),
        ];

        if ($profile->relationLoaded('capabilities')) {
            $payload['capabilities'] = $profile->capabilities->map(fn ($cap) => [
                'id' => $cap->id,
                'slug' => $cap->slug,
                'name' => $cap->name,
            ])->values();
        }

        if ($profile->relationLoaded('metric') && $profile->metric !== null) {
            $payload['metrics'] = [
                'window_24h' => $profile->metric->window_24h_json,
                'window_7d' => $profile->metric->window_7d_json,
                'last_recomputed_at' => optional($profile->metric->last_recomputed_at)?->toIso8601String(),
            ];
        }

        if ($includeDetails) {
            $payload['env_json'] = $profile->env_json;
            $payload['config_json'] = $profile->config_json;
        }

        return $payload;
    }
}
