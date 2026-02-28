<?php

namespace App\Providers;

use App\Messenger\Gateway\MessengerGatewayManager;
use App\Support\Messenger\ConnectorManager;
use App\Support\Messenger\IdempotencyKeyGenerator;
use Illuminate\Support\ServiceProvider;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class MessengerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/messenger.php',
            'messenger'
        );

        $this->app->singleton(ConnectorManager::class, function ($app) {
            $manager = new ConnectorManager;

            // Register configured adapters
            $adapters = config('messenger.adapters', []);
            foreach ($adapters as $provider => $adapterClass) {
                $manager->register($provider, $adapterClass);
            }

            return $manager;
        });

        $this->app->singleton(IdempotencyKeyGenerator::class);

        // Register ReactPHP EventLoop for gateway command
        $this->app->singleton(LoopInterface::class, function () {
            return Loop::get();
        });

        // Register MessengerGatewayManager for gateway command
        $this->app->singleton(MessengerGatewayManager::class, function ($app) {
            return new MessengerGatewayManager(
                $app->make(LoopInterface::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/messenger.php' => config_path('messenger.php'),
            ], 'messenger-config');
        }
    }
}
