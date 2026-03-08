<?php

declare(strict_types=1);

namespace App\Models\Runtime;

use App\Enums\Runtime\BrowserPersistenceMode;
use App\Enums\Runtime\RuntimeMode;
use App\Enums\Runtime\RuntimeSessionStatus;
use App\Models\ChatSession;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuntimeSession extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'chat_session_id',
        'user_id',
        'team_id',
        'parent_session_id',
        'spawn_depth',
        'status',
        'mode',
        'title',
        'workspace_root',
        'browser_persistence_mode',
        'tool_auto_approvals',
        'started_at',
        'ended_at',
        'total_input_tokens',
        'total_output_tokens',
        'total_cost_usd',
        'security_config_json',
        'file_provenance',
    ];

    protected function casts(): array
    {
        return [
            'status' => RuntimeSessionStatus::class,
            'mode' => RuntimeMode::class,
            'browser_persistence_mode' => BrowserPersistenceMode::class,
            'tool_auto_approvals' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_input_tokens' => 'integer',
            'total_output_tokens' => 'integer',
            'total_cost_usd' => 'decimal:6',
            'spawn_depth' => 'integer',
            'security_config_json' => 'array',
            'file_provenance' => 'array',
        ];
    }

    public function isToolAutoApproved(string $toolName): bool
    {
        return in_array($toolName, $this->tool_auto_approvals ?? [], true);
    }

    public function addToolAutoApproval(string $toolName): void
    {
        $list = $this->tool_auto_approvals ?? [];
        if (! in_array($toolName, $list, true)) {
            $list[] = $toolName;
            $this->update(['tool_auto_approvals' => $list]);
        }
    }

    public function removeToolAutoApproval(string $toolName): void
    {
        $list = array_values(array_filter(
            $this->tool_auto_approvals ?? [],
            fn (string $t) => $t !== $toolName,
        ));
        $this->update(['tool_auto_approvals' => $list]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_session_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_session_id');
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(RuntimeTurn::class)->orderBy('sequence');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(RuntimeArtifact::class);
    }

    public function policySnapshots(): HasMany
    {
        return $this->hasMany(RuntimePolicySnapshot::class)->orderBy('captured_at');
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $teamIds = $user->allTeams()->pluck('id');
        $query->where(function (Builder $q) use ($user, $teamIds): void {
            $q->where('user_id', $user->id)
                ->orWhereIn('team_id', $teamIds);
        });
    }
}
