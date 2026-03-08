<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Agent\FeatureFlagManager;
use App\Support\Connectors\ConnectorRegistryLoader;
use App\Support\Connectors\ConnectorVaultEncrypter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class ConnectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/connectors.php',
            'connectors'
        );

        $this->app->singleton(ConnectorVaultEncrypter::class, function () {
            return new ConnectorVaultEncrypter;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/connectors.php' => config_path('connectors.php'),
            ], 'connectors-config');
        }

        if ($this->app->make(FeatureFlagManager::class)->enabled(FeatureFlagManager::CONNECTORS_ENABLED)) {
            try {
                $this->app->make(ConnectorRegistryLoader::class)->sync();
            } catch (\Throwable $e) {
                Log::warning('Connector registry sync failed during boot', ['error' => $e->getMessage()]);
            }
        }
    }
}
