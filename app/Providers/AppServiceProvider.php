<?php

namespace App\Providers;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Policies\AgentJobPolicy;
use App\Policies\AgentJobRunPolicy;
use App\Support\Agent\ErrorEnvelope;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AgentJob::class, AgentJobPolicy::class);
        Gate::policy(AgentJobRun::class, AgentJobRunPolicy::class);

        RateLimiter::for('agent-mutations', function (Request $request) {
            return [
                Limit::perMinute(30)
                    ->by((string) ($request->user()?->id ?? $request->ip()))
                    ->response(function () {
                        return ErrorEnvelope::make(
                            'RATE_LIMITED',
                            'Too many requests. Please retry shortly.',
                            429
                        );
                    }),
            ];
        });
    }
}
