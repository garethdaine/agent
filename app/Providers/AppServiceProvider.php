<?php

namespace App\Providers;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Listeners\DelegationBroadcastSubscriber;
use App\Listeners\DelegationCoordinator;
use App\Listeners\DelegationRecoveryHandler;
use App\Models\AgentAuditLog;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationSession;
use App\Models\NlParseAttempt;
use App\Models\OrgAgentProfile;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Policies\AgentAuditLogPolicy;
use App\Policies\AgentJobPolicy;
use App\Policies\AgentJobRunPolicy;
use App\Policies\InterrogationSessionPolicy;
use App\Policies\NlParseAttemptPolicy;
use App\Policies\OrgAgentProfilePolicy;
use App\Policies\OrgRitualRunPolicy;
use App\Policies\OrgRitualTemplatePolicy;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Compliance\ComplexityClassifier;
use App\Support\Compliance\ComplianceFlagResolver;
use App\Support\Compliance\LessonsManager;
use App\Support\Compliance\OrchestrationPolicyService;
use App\Support\Compliance\VerificationEvidenceEvaluator;
use App\Support\Interrogation\Adapters\ClaudeAdapter;
use App\Support\Interrogation\AdversarialReviewerService;
use App\Support\Interrogation\InterrogationBuildCommandGuard;
use App\Support\Interrogation\ReviewerContextBuilder;
use App\Support\Interrogation\ReviewerPayloadGuard;
use App\Support\Interrogation\ReviewerPayloadNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Events\Dispatcher;
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
        $this->app->singleton(AdversarialReviewerService::class, function ($app) {
            return new AdversarialReviewerService(
                $app->make(ClaudeAdapter::class),
                new ReviewerPayloadGuard,
                new ReviewerPayloadNormalizer,
                new ReviewerContextBuilder
            );
        });

        $this->app->singleton(ComplexityClassifier::class, fn () => ComplexityClassifier::fromConfig());

        $this->app->singleton(VerificationEvidenceEvaluator::class);

        $this->app->singleton(OrchestrationPolicyServiceContract::class, OrchestrationPolicyService::class);

        $this->app->singleton(ComplianceFlagResolver::class);

        $this->app->singleton(LessonsManager::class, fn () => LessonsManager::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $events): void
    {
        if ($this->app->runningInConsole()) {
            app(InterrogationBuildCommandGuard::class)->enforceFromGlobals();
        }

        $events->subscribe(DelegationCoordinator::class);
        $events->subscribe(DelegationRecoveryHandler::class);
        $events->subscribe(DelegationBroadcastSubscriber::class);

        Gate::policy(AgentJob::class, AgentJobPolicy::class);
        Gate::policy(AgentJobRun::class, AgentJobRunPolicy::class);
        Gate::policy(AgentAuditLog::class, AgentAuditLogPolicy::class);
        Gate::policy(InterrogationSession::class, InterrogationSessionPolicy::class);
        Gate::policy(NlParseAttempt::class, NlParseAttemptPolicy::class);
        Gate::policy(OrgAgentProfile::class, OrgAgentProfilePolicy::class);
        Gate::policy(OrgRitualTemplate::class, OrgRitualTemplatePolicy::class);
        Gate::policy(OrgRitualRun::class, OrgRitualRunPolicy::class);

        Gate::define('view-nl-parse-telemetry', function ($user) {
            return $user->hasRole(['admin', 'analytics']);
        });

        Gate::define('view-docs-coverage', function ($user) {
            return $user->hasRole(['admin', 'analytics']);
        });

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

        RateLimiter::for('interrogation', function (Request $request) {
            return [
                Limit::perMinute(120)
                    ->by((string) ($request->user()?->id ?? $request->ip()))
                    ->response(function () {
                        return ErrorEnvelope::make(
                            'RATE_LIMITED',
                            'Too many interrogation requests. Please retry shortly.',
                            429
                        );
                    }),
            ];
        });

        // Memory API rate limiters
        RateLimiter::for('memory-reads', function (Request $request) {
            return Limit::perMinute(120)
                ->by((string) ($request->user()?->id ?? $request->ip()))
                ->response(function () {
                    return ErrorEnvelope::make(
                        'RATE_LIMITED',
                        'Too many memory read requests. Please retry shortly.',
                        429
                    );
                });
        });

        RateLimiter::for('memory-writes', function (Request $request) {
            return Limit::perMinute(30)
                ->by((string) ($request->user()?->id ?? $request->ip()))
                ->response(function () {
                    return ErrorEnvelope::make(
                        'RATE_LIMITED',
                        'Too many memory write requests. Please retry shortly.',
                        429
                    );
                });
        });
    }
}
