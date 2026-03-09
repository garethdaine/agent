<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security()
    ->ignoring('App\Services\Skills\SkillInstaller')
    ->ignoring('App\Support\Agent\ReconcileActiveRunsService')
    ->ignoring('App\Services\Tunnel\TunnelSyncService');

arch()->preset()->laravel()
    // Console commands legitimately read env() for install/setup wizards
    ->ignoring('App\Console\Commands')
    // Many API controllers use domain-specific action methods beyond CRUD
    ->ignoring('App\Http\Controllers\Api\V1')
    ->ignoring('App\Http\Controllers\Settings\TunnelController')
    ->ignoring('App\Http\Controllers\Agent\OperatorPageController')
    ->ignoring('App\Http\Controllers\Messenger\DeadLetterController')
    ->ignoring('App\Http\Controllers\Messenger\MessengerHealthController')
    ->ignoring('App\Http\Controllers\Onboarding\OnboardingController')
    // SecurityConfigProvider dynamically reads SECURITY_* env vars by design
    ->ignoring('App\Services\Security\SecurityConfigProvider')
    // Event subscribers use subscribe() instead of handle()
    ->ignoring('App\Listeners\Documentation\DocumentationTelemetrySubscriber')
    ->ignoring('App\Listeners\DelegationEventPersistenceSubscriber')
    ->ignoring('App\Listeners\DelegationBroadcastSubscriber')
    ->ignoring('App\Listeners\DelegationCoordinator')
    // Exceptions extending RuntimeException/Exception legitimately implement Throwable
    ->ignoring('App\Exceptions')
    ->ignoring('App\Messenger\Exceptions')
    ->ignoring('App\Support\Connectors\Exceptions')
    ->ignoring('App\Support\Documentation\DocsSearchUnavailableException')
    ->ignoring('App\Support\Memory\Exceptions')
    ->ignoring('App\Support\NlOrg\Exceptions')
    ->ignoring('App\Support\Org\Exceptions')
    ->ignoring('App\Support\RepoAnalysis\Exceptions')
    ->ignoring('App\Support\Documentation\DocsSearchUnavailableException')
    ->ignoring('App\Support\Documentation\Schemas\DocumentationValidationException')
    ->ignoring('App\Support\System\DirectoryPickerException')
    // Enums and DTOs used throughout the domain
    ->ignoring('App\DTOs')
    ->ignoring('App\Enums')
    ->ignoring('App\Messenger\Gateway\Enums');
