<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonInterface|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Carbon\CarbonInterface|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property string|null $profile_photo_path
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property \Carbon\CarbonInterface|null $trial_ends_at
 * @property \Carbon\CarbonInterface|null $onboarding_completed_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AgentJob> $agentJobs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AgentJobRun> $agentJobRuns
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InterrogationSession> $interrogationSessions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InterrogationSetting> $interrogationSettings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ConnectedProvider> $connectedProviders
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DelegationGraph> $delegationGraphs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DelegateeProfile> $delegateeProfiles
 * @property-read \App\Models\UserNotificationSetting|null $notificationSetting
 * @property-read \App\Models\UserChatPreference|null $chatPreference
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $ownedTeams
 * @property-read \App\Models\Team|null $currentTeam
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class User extends Authenticatable
{
    use Billable;
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [ // @phpstan-ignore property.phpDocType
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [ // @phpstan-ignore property.phpDocType
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [ // @phpstan-ignore property.phpDocType
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function agentJobs(): HasMany
    {
        return $this->hasMany(AgentJob::class);
    }

    public function agentJobRuns(): HasMany
    {
        return $this->hasMany(AgentJobRun::class);
    }

    public function interrogationSessions(): HasMany
    {
        return $this->hasMany(InterrogationSession::class);
    }

    public function interrogationSettings(): HasMany
    {
        return $this->hasMany(InterrogationSetting::class);
    }

    public function connectedProviders(): HasMany
    {
        return $this->hasMany(ConnectedProvider::class);
    }

    public function delegationGraphs(): HasMany
    {
        return $this->hasMany(DelegationGraph::class);
    }

    public function delegateeProfiles(): HasMany
    {
        return $this->hasMany(DelegateeProfile::class);
    }

    public function notificationSetting(): HasOne
    {
        return $this->hasOne(UserNotificationSetting::class);
    }

    public function chatPreference(): HasOne
    {
        return $this->hasOne(UserChatPreference::class);
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function getNotificationChannel(): string
    {
        return $this->notificationSetting->channel ?? 'email';
    }

    public function requiresConfirmationFor(string $action): bool
    {
        return $this->chatPreference?->requiresConfirmationFor($action) ?? true;
    }

    /**
     * Check if the user has any of the given roles.
     *
     * Uses config-based allowlist for role assignment.
     *
     * @param  string|array<string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $userRoles = $this->getRoles();

        return count(array_intersect($userRoles, $roles)) > 0;
    }

    /**
     * Get the user's assigned roles from config.
     *
     * @return array<string>
     */
    protected function getRoles(): array
    {
        $adminIds = config('agent.roles.admin_user_ids', []);
        $analyticsIds = config('agent.roles.analytics_user_ids', []);

        $roles = [];

        if (in_array($this->id, $adminIds, true)) {
            $roles[] = 'admin';
        }

        if (in_array($this->id, $analyticsIds, true)) {
            $roles[] = 'analytics';
        }

        return $roles;
    }
}
