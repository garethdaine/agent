<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
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
    protected $appends = [
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

    public function getNotificationChannel(): string
    {
        return $this->notificationSetting?->channel ?? 'email';
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
