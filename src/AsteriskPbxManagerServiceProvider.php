<?php

namespace AsteriskPbxManager;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use PAMI\Client\Impl\ClientImpl;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\EventProcessor;
use AsteriskPbxManager\Services\ActionExecutor;
use AsteriskPbxManager\Services\ConfigurationValidator;
use AsteriskPbxManager\Services\AmiInputSanitizer;
use AsteriskPbxManager\Services\BroadcastAuthService;
use AsteriskPbxManager\Services\AuditLoggingService;
use AsteriskPbxManager\Services\HealthCheckService;
use AsteriskPbxManager\Services\QueueManagerService;
use AsteriskPbxManager\Services\ChannelManagerService;
use AsteriskPbxManager\Services\ConnectionPoolManager;
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

        // Bind Configuration Validator
        $this->app->singleton(ConfigurationValidator::class, function ($app) {
            return new ConfigurationValidator();
        });

        // Bind AMI Input Sanitizer
        $this->app->singleton(AmiInputSanitizer::class, function ($app) {
            return new AmiInputSanitizer();
        });

        // Bind Audit Logging Service
        $this->app->singleton(AuditLoggingService::class, function ($app) {
            return new AuditLoggingService();
        });

        // Bind PAMI Client with comprehensive validation
        $this->app->singleton(ClientImpl::class, function ($app) {
            $config = $app['config']['asterisk-pbx-manager'];
            $validator = $app->make(ConfigurationValidator::class);
            
            // Validate and sanitize complete configuration
            $validatedConfig = $validator->validateConfiguration($config);
            
            return new ClientImpl($validatedConfig['connection']);
        });

        // Bind main service
        $this->app->singleton('asterisk-manager', function ($app) {
            return new AsteriskManagerService(
                $app->make(ClientImpl::class),
                $app->make(AmiInputSanitizer::class),
                $app->make(AuditLoggingService::class)
            );
        });

        // Bind additional services
        $this->app->singleton(EventProcessor::class, function ($app) {
            return new EventProcessor();
        });

        $this->app->singleton(ActionExecutor::class, function ($app) {
            return new ActionExecutor($app->make('asterisk-manager'));
        });

        // Bind Broadcast Authentication Service
        $this->app->singleton(BroadcastAuthService::class, function ($app) {
            return new BroadcastAuthService($app['auth']);
        });

        // Bind Queue Manager Service
        $this->app->singleton(QueueManagerService::class, function ($app) {
            return new QueueManagerService(
                $app->make('asterisk-manager'),
                $app->make(AmiInputSanitizer::class)
            );
        });

        // Bind Channel Manager Service
        $this->app->singleton(ChannelManagerService::class, function ($app) {
            return new ChannelManagerService(
                $app->make('asterisk-manager'),
                $app->make(AmiInputSanitizer::class)
            );
        });

        // Bind Health Check Service
        $this->app->singleton(HealthCheckService::class, function ($app) {
            return new HealthCheckService(
                $app->make('asterisk-manager'),
                $app->make(QueueManagerService::class),
                $app->make(ChannelManagerService::class)
            );
        });

        // Bind Connection Pool Manager
        $this->app->singleton(ConnectionPoolManager::class, function ($app) {
            return new ConnectionPoolManager(
                $app->make(AmiInputSanitizer::class),
                $app->make(AuditLoggingService::class)
            );
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

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

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
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'asterisk-manager',
            AsteriskManagerService::class,
            EventProcessor::class,
            ActionExecutor::class,
            ConfigurationValidator::class,
            AmiInputSanitizer::class,
            BroadcastAuthService::class,
            AuditLoggingService::class,
            HealthCheckService::class,
            QueueManagerService::class,
            ChannelManagerService::class,
            ConnectionPoolManager::class,
            ClientImpl::class,
        ];
    }
}