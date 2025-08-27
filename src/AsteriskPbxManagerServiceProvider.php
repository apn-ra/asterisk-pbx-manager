<?php

namespace AsteriskPbxManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use PAMI\Client\Impl\ClientImpl;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\EventProcessor;
use AsteriskPbxManager\Services\ActionExecutor;
use AsteriskPbxManager\Commands\AsteriskStatus;
use AsteriskPbxManager\Commands\MonitorEvents;
use AsteriskPbxManager\Listeners\LogCallEvent;
use AsteriskPbxManager\Listeners\BroadcastCallStatus;
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use AsteriskPbxManager\Events\QueueMemberAdded;

class AsteriskPbxManagerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/Config/asterisk-pbx-manager.php',
            'asterisk-pbx-manager'
        );

        // Bind PAMI Client
        $this->app->singleton(ClientImpl::class, function ($app) {
            $config = $app['config']['asterisk-pbx-manager.connection'];
            
            // Validate required configuration
            $this->validateConfiguration($config);
            
            return new ClientImpl($config);
        });

        // Bind main service
        $this->app->singleton('asterisk-manager', function ($app) {
            return new AsteriskManagerService($app->make(ClientImpl::class));
        });

        // Bind additional services
        $this->app->singleton(EventProcessor::class, function ($app) {
            return new EventProcessor();
        });

        $this->app->singleton(ActionExecutor::class, function ($app) {
            return new ActionExecutor($app->make('asterisk-manager'));
        });

        // Register facade
        $this->app->alias('asterisk-manager', AsteriskManagerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/Config/asterisk-pbx-manager.php' => config_path('asterisk-pbx-manager.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/Migrations/' => database_path('migrations'),
        ], 'migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/Migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                AsteriskStatus::class,
                MonitorEvents::class,
            ]);
        }

        // Register event listeners
        $this->registerEventListeners();
    }

    /**
     * Register event listeners for Asterisk events.
     */
    protected function registerEventListeners(): void
    {
        if (config('asterisk-pbx-manager.events.enabled', true)) {
            $events = $this->app['events'];

            // Register call event listeners
            if (config('asterisk-pbx-manager.events.log_to_database', true)) {
                $events->listen(CallConnected::class, LogCallEvent::class);
                $events->listen(CallEnded::class, LogCallEvent::class);
                $events->listen(QueueMemberAdded::class, LogCallEvent::class);
            }

            // Register broadcasting listeners
            if (config('asterisk-pbx-manager.events.broadcast', true)) {
                $events->listen(CallConnected::class, BroadcastCallStatus::class);
                $events->listen(CallEnded::class, BroadcastCallStatus::class);
                $events->listen(QueueMemberAdded::class, BroadcastCallStatus::class);
            }
        }
    }

    /**
     * Validate the AMI configuration.
     */
    protected function validateConfiguration(array $config): void
    {
        $requiredKeys = ['host', 'port', 'username', 'secret'];
        
        foreach ($requiredKeys as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException(
                    "Missing required Asterisk AMI configuration: {$key}"
                );
            }
        }

        // Log configuration validation
        if (config('asterisk-pbx-manager.logging.enabled', true)) {
            Log::info('Asterisk PBX Manager configuration validated successfully', [
                'host' => $config['host'],
                'port' => $config['port'],
                'username' => $config['username'],
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'asterisk-manager',
            AsteriskManagerService::class,
            EventProcessor::class,
            ActionExecutor::class,
            ClientImpl::class,
        ];
    }
}