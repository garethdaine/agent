<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporterFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('opentelemetry.enabled', true)) {
            return;
        }

        $this->app->singleton(TracerProviderInterface::class, function () {
            $serviceName = config('opentelemetry.service_name', 'agentops');

            $resource = ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => $serviceName,
                ResourceAttributes::SERVICE_VERSION => config('app.version', '1.0.0'),
                ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => app()->environment(),
            ]));

            $exporter = (new ConsoleSpanExporterFactory)->create();
            $processor = new SimpleSpanProcessor($exporter);

            return TracerProvider::builder()
                ->setResource($resource)
                ->addSpanProcessor($processor)
                ->build();
        });

        $this->app->singleton(TracerInterface::class, function () {
            $provider = $this->app->make(TracerProviderInterface::class);

            return $provider->getTracer(
                config('opentelemetry.service_name', 'agentops'),
            );
        });
    }

    public function boot(): void
    {
        if (! config('opentelemetry.enabled', true)) {
            return;
        }

        $this->app->terminating(function () {
            try {
                $provider = $this->app->make(TracerProviderInterface::class);
                if ($provider instanceof TracerProvider) { // @phpstan-ignore instanceof.alwaysTrue
                    $provider->shutdown();
                }
            } catch (\Throwable) {
                // Telemetry shutdown must not break the application
            }
        });
    }
}
