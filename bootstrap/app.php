<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->api(prepend: [\App\Http\Middleware\TunnelSessionDomainMiddleware::class]);
        $middleware->web(prepend: [\App\Http\Middleware\TunnelSessionDomainMiddleware::class], append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'delegation' => \App\Http\Middleware\DelegationFeatureGate::class,
            'delegation.ui' => \App\Http\Middleware\DelegationUiFeatureGate::class,
            'license' => \App\Http\Middleware\EnsureLicenseValid::class,
            'onboarding' => \App\Http\Middleware\EnsureOnboardingCompleted::class,
            'org' => \App\Http\Middleware\OrgFeatureGate::class,
            'org.ui' => \App\Http\Middleware\OrgUiFeatureGate::class,
            'office.ui' => \App\Http\Middleware\OfficeUiFeatureGate::class,
            'outage.protect' => \App\Http\Middleware\OutageAutoProtect::class,
            'skills.ui' => \App\Http\Middleware\EnsureSkillsUiEnabled::class,
            'tunnel.feature' => \App\Http\Middleware\TunnelFeatureGate::class,
            'tunnel.ip' => \App\Http\Middleware\TunnelIpAllowlistMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('agent/api/v1/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $exception->errors(),
                ],
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('agent/api/v1/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                    'details' => (object) [],
                ],
            ], 401);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('agent/api/v1/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Resource not found.',
                    'details' => (object) [],
                ],
            ], 404);
        });
    })->create();
