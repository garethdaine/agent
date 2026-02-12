<?php

namespace App\Providers;

use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Policies\AgentJobPolicy;
use App\Policies\AgentJobRunPolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
