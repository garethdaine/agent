<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\OrchestrationPolicyServiceContract;
use App\Events\AgentJobRunFinished;
use App\Events\DelegationGraphCompleted;
use App\Events\DelegationTaskVerified;
use App\Events\InterrogationPhaseChanged;
use App\Events\Org\OrgRitualEscalationTimedOut;
use App\Events\RepoAnalysisSessionUpdated;
use App\Events\RuntimeApprovalRequested;
use App\Listeners\DelegationBroadcastSubscriber;
use App\Listeners\DelegationCoordinator;
use App\Listeners\DelegationEventPersistenceSubscriber;
use App\Listeners\DelegationRecoveryHandler;
use App\Listeners\Documentation\DocumentationTelemetrySubscriber;
use App\Listeners\Messenger\SendAgentJobFinishedNotification;
use App\Listeners\Messenger\SendApprovalRequestedNotification;
use App\Listeners\Messenger\SendDelegationTaskFailedNotification;
use App\Listeners\Messenger\SendEscalationTimedOutNotification;
use App\Listeners\Messenger\SendInterrogationPhaseNotification;
use App\Listeners\Messenger\SendRepoAnalysisCompletedNotification;
use App\Listeners\Messenger\SendRitualRunCompletedNotification;
use App\Listeners\Org\RitualCouncilDeliberationListener;
use App\Listeners\Org\RitualPhaseOutputCaptureListener;
use App\Listeners\Org\RitualRunCompletionListener;
use App\Models\AgentAuditLog;
use App\Models\AgentJob;
use App\Models\AgentJobRun;
use App\Models\InterrogationSession;
use App\Models\NlOrgParseAttempt;
use App\Models\NlParseAttempt;
use App\Models\OrgAgentProfile;
use App\Models\OrgRitualRun;
use App\Models\OrgRitualTemplate;
use App\Models\RepoAnalysisSession;
use App\Observers\DatabaseNotificationObserver;
use App\Policies\AgentAuditLogPolicy;
use App\Policies\AgentJobPolicy;
use App\Policies\AgentJobRunPolicy;
use App\Policies\ConnectorPolicy;
use App\Policies\InterrogationSessionPolicy;
use App\Policies\NlOrgParseAttemptPolicy;
use App\Policies\NlParseAttemptPolicy;
use App\Policies\OrgAgentProfilePolicy;
use App\Policies\OrgRitualRunPolicy;
use App\Policies\OrgRitualTemplatePolicy;
use App\Policies\RepoAnalysisSessionPolicy;
use App\Policies\WorkflowGovernancePolicy;
use App\Repositories\TunnelSettingsRepository;
use App\Services\Agent\LicenseService;
use App\Services\Credentials\CredentialsManager;
use App\Services\Credentials\OAuthTokenService;
use App\Services\Runtime\Adapters\AgentApiToolAdapter;
use App\Services\Runtime\Adapters\BrowserToolAdapter;
use App\Services\Runtime\Adapters\DiscoveryToolAdapter;
use App\Services\Runtime\Adapters\FsToolAdapter;
use App\Services\Runtime\Adapters\McpToolAdapter;
use App\Services\Runtime\Adapters\RuntimeToolAdapter;
use App\Services\Runtime\Adapters\WebToolAdapter;
use App\Services\Runtime\ToolGateway;
use App\Support\Agent\DatabaseDestructionGuard;
use App\Support\Agent\ErrorEnvelope;
use App\Support\Agent\InstanceFingerprint;
use App\Support\Agent\WorkflowKey;
use App\Support\Compliance\ComplexityClassifier;
use App\Support\Compliance\ComplianceFlagResolver;
use App\Support\Compliance\LessonsManager;
use App\Support\Compliance\OrchestrationPolicyService;
use App\Support\Compliance\VerificationEvidenceEvaluator;
use App\Support\Documentation\AgentSystemDocsTelemetryStore;
use App\Support\Documentation\DocsReindexExecutor;
use App\Support\Documentation\DocsSearchIndexClient;
use App\Support\Documentation\DocsSyncSleeper;
use App\Support\Documentation\DocsTelemetryStore;
use App\Support\Documentation\ScoutDocsReindexExecutor;
use App\Support\Documentation\ScoutDocsSearchIndexClient;
use App\Support\Documentation\SystemDocsSyncSleeper;
use App\Support\Interrogation\Adapters\ClaudeAdapter;
use App\Support\Interrogation\AdversarialReviewerService;
use App\Support\Interrogation\InterrogationBuildCommandGuard;
use App\Support\Interrogation\ReviewerContextBuilder;
use App\Support\Interrogation\ReviewerPayloadGuard;
use App\Support\Interrogation\ReviewerPayloadNormalizer;
use App\Support\Observability\LlmTelemetry;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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
        $this->app->singleton(DocsSearchIndexClient::class, ScoutDocsSearchIndexClient::class);
        $this->app->singleton(DocsReindexExecutor::class, ScoutDocsReindexExecutor::class);
        $this->app->singleton(DocsSyncSleeper::class, SystemDocsSyncSleeper::class);
        $this->app->singleton(DocsTelemetryStore::class, AgentSystemDocsTelemetryStore::class);

        $this->app->singleton(ComplianceFlagResolver::class);

        $this->app->singleton(LessonsManager::class, fn () => LessonsManager::fromConfig());

        $this->app->singleton(CredentialsManager::class);
        $this->app->singleton(OAuthTokenService::class);
        $this->app->singleton(ToolGateway::class);

        $this->app->singleton(InstanceFingerprint::class);
        $this->app->singleton(LicenseService::class);

        $this->app->singleton(\App\Services\Skills\SkillLibrary::class, fn ($app) => new \App\Services\Skills\SkillLibrary(
            config('agent.skills.library_manifest_path'),
        ));

        $this->app->singleton(LlmTelemetry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Dispatcher $events): void
    {
        $gateway = $this->app->make(ToolGateway::class);
        $gateway->register($this->app->make(FsToolAdapter::class));
        $gateway->register($this->app->make(RuntimeToolAdapter::class));
        $gateway->register($this->app->make(WebToolAdapter::class));
        $gateway->register($this->app->make(BrowserToolAdapter::class));
        $gateway->register($this->app->make(DiscoveryToolAdapter::class));
        $gateway->register($this->app->make(AgentApiToolAdapter::class));
        $gateway->register($this->app->make(McpToolAdapter::class));

        Route::pattern('workflowKey', WorkflowKey::routePattern());
        Route::pattern('workflow_key', WorkflowKey::routePattern());

        if ($this->app->runningInConsole()) {
            app(InterrogationBuildCommandGuard::class)->enforceFromGlobals();
            app(DatabaseDestructionGuard::class)->enforceFromGlobals();
        }

        DatabaseNotification::observe(DatabaseNotificationObserver::class);

        $events->subscribe(DelegationCoordinator::class);
        $events->subscribe(DelegationRecoveryHandler::class);
        $events->subscribe(DelegationBroadcastSubscriber::class);
        $events->subscribe(DelegationEventPersistenceSubscriber::class);
        $events->subscribe(DocumentationTelemetrySubscriber::class);

        $events->listen(DelegationGraphCompleted::class, RitualRunCompletionListener::class);
        $events->listen(DelegationTaskVerified::class, RitualCouncilDeliberationListener::class);
        $events->listen(DelegationTaskVerified::class, RitualPhaseOutputCaptureListener::class);

        // System event messenger notifications
        $events->listen(DelegationGraphCompleted::class, SendRitualRunCompletedNotification::class);
        $events->listen(DelegationTaskVerified::class, SendDelegationTaskFailedNotification::class);
        $events->listen(AgentJobRunFinished::class, SendAgentJobFinishedNotification::class);
        $events->listen(AgentJobRunFinished::class, \App\Listeners\Compliance\LessonExtractionListener::class);
        $events->listen(AgentJobRunFinished::class, \App\Listeners\DispatchBuildTickOnRunFinished::class);
        $events->listen(OrgRitualEscalationTimedOut::class, SendEscalationTimedOutNotification::class);
        $events->listen(RuntimeApprovalRequested::class, SendApprovalRequestedNotification::class);
        $events->listen(RepoAnalysisSessionUpdated::class, SendRepoAnalysisCompletedNotification::class);
        $events->listen(InterrogationPhaseChanged::class, SendInterrogationPhaseNotification::class);

        Gate::policy(AgentJob::class, AgentJobPolicy::class);
        Gate::policy(AgentJobRun::class, AgentJobRunPolicy::class);
        Gate::policy(AgentAuditLog::class, AgentAuditLogPolicy::class);
        Gate::policy(InterrogationSession::class, InterrogationSessionPolicy::class);
        Gate::policy(NlOrgParseAttempt::class, NlOrgParseAttemptPolicy::class);
        Gate::policy(NlParseAttempt::class, NlParseAttemptPolicy::class);
        Gate::policy(OrgAgentProfile::class, OrgAgentProfilePolicy::class);
        Gate::policy(OrgRitualTemplate::class, OrgRitualTemplatePolicy::class);
        Gate::policy(OrgRitualRun::class, OrgRitualRunPolicy::class);
        Gate::policy(RepoAnalysisSession::class, RepoAnalysisSessionPolicy::class);
        Gate::policy(\App\Models\AgentConnectorConnection::class, ConnectorPolicy::class);

        Gate::define('view-nl-parse-telemetry', function ($user) {
            return $user->hasRole(['admin', 'analytics']);
        });

        Gate::define('view-docs-coverage', function ($user) {
            return $user->hasRole(['admin', 'analytics']);
        });

        Gate::define('view-docs-diagnostics', function ($user) {
            return $user->hasRole(['admin', 'analytics']);
        });

        Gate::define('viewAgent', fn ($user) => true);

        Gate::define('viewPulse', fn ($user) => $user->hasRole('admin'));

        Gate::define('workflow.pause', [WorkflowGovernancePolicy::class, 'pause']);
        Gate::define('workflow.resume', [WorkflowGovernancePolicy::class, 'resume']);
        Gate::define('workflow.override', [WorkflowGovernancePolicy::class, 'override']);

        RateLimiter::for('agent-mutations', function (Request $request) {
            return [
                Limit::perMinute(30)
                    ->by((string) ($request->user()->id ?? $request->ip()))
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
                    ->by((string) ($request->user()->id ?? $request->ip()))
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
                ->by((string) ($request->user()->id ?? $request->ip()))
                ->response(function () {
                    return ErrorEnvelope::make(
                        'RATE_LIMITED',
                        'Too many memory read requests. Please retry shortly.',
                        429
                    );
                });
        });

        Scramble::configure()
            ->routes(fn (RoutingRoute $route) => str_starts_with($route->uri, 'agent/api/v1'))
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(SecurityScheme::http('bearer'));
            });

        RateLimiter::for('memory-writes', function (Request $request) {
            return Limit::perMinute(30)
                ->by((string) ($request->user()->id ?? $request->ip()))
                ->response(function () {
                    return ErrorEnvelope::make(
                        'RATE_LIMITED',
                        'Too many memory write requests. Please retry shortly.',
                        429
                    );
                });
        });

        $this->mergeTunnelHostnameIntoConfig();
    }

    private function mergeTunnelHostnameIntoConfig(): void
    {
        if (! config('tunnel.enabled', false)) {
            return;
        }

        try {
            $settings = $this->app->make(TunnelSettingsRepository::class)->getSettings();
            $hostname = trim((string) ($settings['hostname'] ?? ''));
            if ($hostname === '') {
                return;
            }

            $origin = str_starts_with($hostname, 'http') ? $hostname : 'https://'.$hostname;
            $origin = rtrim($origin, '/');

            $allowed = config('cors.allowed_origins', []);
            if (! in_array($origin, $allowed, true)) {
                config(['cors.allowed_origins' => array_merge($allowed, [$origin])]);
            }

            $host = parse_url($origin, PHP_URL_HOST) ?: $hostname;
            $stateful = config('sanctum.stateful', []);
            if (! in_array($host, $stateful, true)) {
                config(['sanctum.stateful' => array_merge($stateful, [$host])]);
            }
        } catch (\Throwable) {
        }
    }
}
