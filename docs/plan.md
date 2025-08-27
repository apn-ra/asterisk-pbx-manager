# Asterisk PBX Manager Laravel Package Development Plan

**Document Version:** 2.0  
**Date:** August 27, 2025  
**Author:** System Analysis  

## Executive Summary

This document outlines a comprehensive plan for developing a Laravel 12 package that integrates PHP Asterisk Manager Interface (PAMI) for robust Asterisk PBX management. The package will provide a modern, Laravel-native interface for Asterisk Manager Interface operations, enabling developers to easily integrate telephony functionality into their Laravel applications.

## Package Overview

### Package Purpose
The **Asterisk PBX Manager** Laravel package provides a seamless integration between Laravel applications and Asterisk PBX systems through the Asterisk Manager Interface (AMI). This package leverages the proven PAMI library while adding Laravel-specific features like service providers, facades, configuration management, and event handling.

### Core Features
- **AMI Connection Management**: Robust connection handling with automatic reconnection
- **Event Listening**: Real-time Asterisk event processing with Laravel event system integration
- **Action Execution**: Complete AMI action support (Originate, Queue operations, etc.)
- **Laravel Integration**: Native Laravel patterns (Service Providers, Facades, Config)
- **Event Broadcasting**: Integration with Laravel's broadcasting system
- **Database Logging**: Optional call logging and metrics storage
- **Artisan Commands**: CLI tools for AMI management and monitoring

### Technology Stack
- **Backend**: Laravel 12.25.0, PHP 8.4+
- **AMI Library**: PAMI (PHP Asterisk Manager Interface)
- **Database**: PostgreSQL (configurable)
- **Events**: Laravel Event System, Broadcasting
- **Logging**: PSR-3 compatible logging
- **Testing**: PHPUnit for package testing

## Laravel Package Architecture

### Package Structure

The Asterisk PBX Manager package follows Laravel package development best practices with a clean, modular architecture:

```
src/
├── AsteriskPbxManagerServiceProvider.php
├── Facades/
│   └── AsteriskManager.php
├── Services/
│   ├── AsteriskManagerService.php
│   ├── EventProcessor.php
│   └── ActionExecutor.php
├── Events/
│   ├── CallConnected.php
│   ├── CallEnded.php
│   ├── QueueMemberAdded.php
│   └── AsteriskEvent.php
├── Listeners/
│   ├── LogCallEvent.php
│   └── BroadcastCallStatus.php
├── Commands/
│   ├── AsteriskStatus.php
│   └── MonitorEvents.php
├── Models/
│   ├── CallLog.php
│   └── AsteriskEvent.php
├── Migrations/
│   ├── create_call_logs_table.php
│   └── create_asterisk_events_table.php
├── Config/
│   └── asterisk-pbx-manager.php
└── Exceptions/
    ├── AsteriskConnectionException.php
    └── ActionExecutionException.php
```

### Core PAMI Integration

#### 1. Service Provider Implementation
**Rationale**: Laravel service provider ensures proper dependency injection and configuration binding.

```php
<?php

namespace AsteriskPbxManager;

use Illuminate\Support\ServiceProvider;
use PAMI\Client\Impl\ClientImpl;
use AsteriskPbxManager\Services\AsteriskManagerService;

class AsteriskPbxManagerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/Config/asterisk-pbx-manager.php', 
            'asterisk-pbx-manager'
        );

        $this->app->singleton('asterisk-manager', function ($app) {
            return new AsteriskManagerService(
                new ClientImpl($app['config']['asterisk-pbx-manager'])
            );
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/Config/asterisk-pbx-manager.php' => config_path('asterisk-pbx-manager.php'),
        ], 'config');

        $this->loadMigrationsFrom(__DIR__.'/Migrations');
        
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\AsteriskStatus::class,
                Commands\MonitorEvents::class,
            ]);
        }
    }
}
```

#### 2. Asterisk Manager Service
**Rationale**: Centralized service manages PAMI client lifecycle and provides Laravel-native interface.

```php
<?php

namespace AsteriskPbxManager\Services;

use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\QueueAddAction;
use PAMI\Message\Action\HangupAction;
use Illuminate\Support\Facades\Log;
use AsteriskPbxManager\Events\AsteriskEvent;

class AsteriskManagerService
{
    protected ClientImpl $client;
    protected bool $connected = false;

    public function __construct(ClientImpl $client)
    {
        $this->client = $client;
        $this->setupEventListeners();
    }

    public function connect(): bool
    {
        try {
            $this->client->open();
            $this->connected = true;
            Log::info('Connected to Asterisk Manager Interface');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to connect to Asterisk: ' . $e->getMessage());
            return false;
        }
    }

    public function originateCall(string $channel, string $extension, string $context = 'default'): bool
    {
        $action = new OriginateAction($channel);
        $action->setExtension($extension);
        $action->setContext($context);
        $action->setPriority(1);

        try {
            $response = $this->client->send($action);
            return $response->isSuccess();
        } catch (\Exception $e) {
            Log::error('Failed to originate call: ' . $e->getMessage());
            return false;
        }
    }

    protected function setupEventListeners(): void
    {
        $this->client->registerEventListener(function ($event) {
            event(new AsteriskEvent($event));
        });
    }
}
```

### Package Configuration

#### Configuration File
**Rationale**: Comprehensive configuration supports various Asterisk setups and Laravel integration patterns.

```php
<?php
// config/asterisk-pbx-manager.php

return [
    'connection' => [
        'host' => env('ASTERISK_AMI_HOST', '127.0.0.1'),
        'port' => env('ASTERISK_AMI_PORT', 5038),
        'username' => env('ASTERISK_AMI_USERNAME', 'admin'),
        'secret' => env('ASTERISK_AMI_SECRET', 'amp111'),
        'connect_timeout' => env('ASTERISK_AMI_CONNECT_TIMEOUT', 10),
        'read_timeout' => env('ASTERISK_AMI_READ_TIMEOUT', 10),
        'scheme' => env('ASTERISK_AMI_SCHEME', 'tcp://'),
    ],
    'events' => [
        'enabled' => env('ASTERISK_EVENTS_ENABLED', true),
        'broadcast' => env('ASTERISK_EVENTS_BROADCAST', true),
        'log_to_database' => env('ASTERISK_LOG_TO_DATABASE', true),
    ],
];
### Package Installation and Usage

#### 1. Composer Configuration
**Rationale**: Standard Composer package structure enables easy distribution and version management.

```json
{
    "name": "apn-ra/asterisk-pbx-manager",
    "description": "Laravel package for Asterisk PBX Manager Interface integration using PAMI",
    "type": "library",
    "keywords": ["laravel", "asterisk", "pbx", "ami", "pami", "voip", "telephony"],
    "license": "MIT",
    "require": {
        "php": "^8.1",
        "laravel/framework": "^10.0|^11.0|^12.0",
        "marcelog/pami": "^2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "orchestra/testbench": "^8.0"
    },
    "autoload": {
        "psr-4": {
            "AsteriskPbxManager\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "AsteriskPbxManager\\AsteriskPbxManagerServiceProvider"
            ],
            "aliases": {
                "AsteriskManager": "AsteriskPbxManager\\Facades\\AsteriskManager"
            }
        }
    }
}
```

#### 2. Installation Guide
**Rationale**: Clear installation instructions ensure proper package setup and configuration.

```bash
# Install the package via Composer
composer require apn-ra/asterisk-pbx-manager

# Publish the configuration file
php artisan vendor:publish --provider="AsteriskPbxManager\AsteriskPbxManagerServiceProvider" --tag="config"

# Run the database migrations
php artisan migrate

# (Optional) Publish and customize migrations
php artisan vendor:publish --provider="AsteriskPbxManager\AsteriskPbxManagerServiceProvider" --tag="migrations"
```

## Package Usage Examples

### Basic Usage
**Rationale**: Simple, intuitive API makes common telephony tasks easy to implement.

```php
<?php

use AsteriskPbxManager\Facades\AsteriskManager;

// Connect to Asterisk
if (AsteriskManager::connect()) {
    // Make a call
    $result = AsteriskManager::originateCall(
        channel: 'SIP/1001',
        extension: '1002',
        context: 'internal'
    );

    if ($result) {
        Log::info('Call initiated successfully');
    }
}

// Check connection status
if (AsteriskManager::isConnected()) {
    $status = AsteriskManager::getStatus();
    return response()->json($status);
}
```

### Database Schema

#### Package Migrations
**Rationale**: Database tables support call logging and event tracking with proper indexing for performance.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAsteriskCallLogsTable extends Migration
{
    public function up()
    {
        Schema::create('asterisk_call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->index();
            $table->string('caller_id')->nullable();
            $table->string('connected_to')->nullable();
            $table->string('context')->nullable();
            $table->enum('direction', ['inbound', 'outbound'])->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable();
            $table->string('hangup_cause')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asterisk_call_logs');
    }
}
```

### Event System Integration

#### 1. Laravel Event Classes
**Rationale**: Native Laravel events enable seamless integration with existing application event handling.

```php
<?php

namespace AsteriskPbxManager\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallConnected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $channel;
    public $extension;
    public $callerIdNum;
    public $connectedLineNum;

    public function __construct($eventData)
    {
        $this->channel = $eventData->getKey('Channel');
        $this->extension = $eventData->getKey('Extension');
        $this->callerIdNum = $eventData->getKey('CallerIDNum');
        $this->connectedLineNum = $eventData->getKey('ConnectedLineNum');
    }

    public function broadcastOn()
    {
        return new Channel('asterisk-events');
    }

    public function broadcastAs()
    {
        return 'call.connected';
    }
}
```

#### 2. Event Processing
**Rationale**: Centralized event processor handles all Asterisk events with proper filtering and routing.

```php
<?php

namespace AsteriskPbxManager\Services;

use PAMI\Message\Event\DialEvent;
use PAMI\Message\Event\HangupEvent;
use PAMI\Message\Event\BridgeEvent;
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use Illuminate\Support\Facades\Log;

class EventProcessor
{
    public function processEvent($event)
    {
        $eventName = $event->getEventName();
        
        Log::info("Processing Asterisk event: {$eventName}");

        switch ($eventName) {
            case 'Dial':
                $this->handleDialEvent($event);
                break;
            case 'Hangup':
                $this->handleHangupEvent($event);
                break;
            case 'Bridge':
                $this->handleBridgeEvent($event);
                break;
            case 'QueueMemberAdded':
                $this->handleQueueMemberAdded($event);
                break;
            default:
                $this->handleUnknownEvent($event);
        }
    }

    protected function handleDialEvent(DialEvent $event)
    {
        if ($event->getSubEvent() === 'Begin') {
            Log::info('Call initiated', [
                'channel' => $event->getChannel(),
                'destination' => $event->getDestination()
            ]);
        } elseif ($event->getSubEvent() === 'End') {
            event(new CallConnected($event));
        }
    }

    protected function handleHangupEvent(HangupEvent $event)
    {
        event(new CallEnded($event));
        
        Log::info('Call ended', [
            'channel' => $event->getChannel(),
            'cause' => $event->getCause()
        ]);
    }
}
```

### Advanced Features

#### 1. Queue Management
**Rationale**: Call queue management is essential for call center operations.

```php
<?php

namespace AsteriskPbxManager\Services;

use PAMI\Message\Action\QueueAddAction;
use PAMI\Message\Action\QueueRemoveAction;
use PAMI\Message\Action\QueuePauseAction;
use PAMI\Message\Action\QueuesAction;

class QueueManagerService
{
    protected AsteriskManagerService $asteriskManager;

    public function __construct(AsteriskManagerService $asteriskManager)
    {
        $this->asteriskManager = $asteriskManager;
    }

    public function addMember(string $queue, string $interface, string $memberName = null): bool
    {
        $action = new QueueAddAction($queue, $interface);
        if ($memberName) {
            $action->setMemberName($memberName);
        }

        try {
            $response = $this->asteriskManager->send($action);
            return $response->isSuccess();
        } catch (\Exception $e) {
            Log::error("Failed to add queue member: {$e->getMessage()}");
            return false;
        }
    }

    public function removeMember(string $queue, string $interface): bool
    {
        $action = new QueueRemoveAction($queue, $interface);
        
        try {
            $response = $this->asteriskManager->send($action);
            return $response->isSuccess();
        } catch (\Exception $e) {
            Log::error("Failed to remove queue member: {$e->getMessage()}");
            return false;
        }
    }
}
```

## Implementation Roadmap

### Phase 1: Core Package Development (Week 1-2)
**Rationale**: Establish solid foundation with essential PAMI integration.

#### Package Structure Setup
- [ ] Create Laravel package skeleton with proper directory structure
- [ ] Implement service provider with dependency injection
- [ ] Create PAMI client wrapper service
- [ ] Add basic configuration management
- [ ] Implement connection management and error handling

#### Basic AMI Operations
- [ ] Implement originate call functionality
- [ ] Add hangup and basic call control
- [ ] Create event listening infrastructure
- [ ] Add logging integration with PSR-3

### Phase 2: Event System Integration (Week 3)
**Rationale**: Real-time event processing is crucial for telephony applications.

#### Laravel Event Integration
- [ ] Create Laravel event classes for common Asterisk events
- [ ] Implement event processor service
- [ ] Add event broadcasting capability
- [ ] Create database event logging

#### Queue and Channel Management
- [ ] Implement queue management service
- [ ] Add channel control operations
- [ ] Create status monitoring commands
- [ ] Add call transfer functionality

### Phase 3: Advanced Features (Week 4)
**Rationale**: Enhanced features provide comprehensive telephony management.

#### Database Integration
- [ ] Create migration files for call logs and events
- [ ] Implement Eloquent models with proper relationships
- [ ] Add query scopes and accessors
- [ ] Create database seeders for testing

#### CLI Commands
- [ ] Implement Artisan commands for system monitoring
- [ ] Add queue management commands
- [ ] Create event monitoring tools
- [ ] Add system health checks

### Phase 4: Testing and Documentation (Week 5)
**Rationale**: Comprehensive testing ensures package reliability.

#### Testing Suite
- [ ] Create PHPUnit tests for all services
- [ ] Add integration tests with mock PAMI client
- [ ] Implement event testing scenarios
- [ ] Add performance and load testing

#### Documentation
- [ ] Write comprehensive README with usage examples
- [ ] Create API documentation
- [ ] Add configuration guide
- [ ] Create troubleshooting guide

## Testing Strategy

### Unit Testing
**Rationale**: Comprehensive testing ensures package reliability and maintainability.

```php
<?php

namespace AsteriskPbxManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AsteriskPbxManager\Services\AsteriskManagerService;
use PAMI\Client\Impl\ClientImpl;
use Mockery;

class AsteriskManagerServiceTest extends TestCase
{
    public function testConnectionSuccess()
    {
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('open')->once()->andReturn(true);
        
        $service = new AsteriskManagerService($mockClient);
        $result = $service->connect();
        
        $this->assertTrue($result);
    }

    public function testOriginateCallSuccess()
    {
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock('PAMI\Message\Response\ResponseMessage');
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        
        $mockClient->shouldReceive('send')->once()->andReturn($mockResponse);
        
        $service = new AsteriskManagerService($mockClient);
        $result = $service->originateCall('SIP/1001', '1002');
        
        $this->assertTrue($result);
    }
}
```

### Integration Testing
**Rationale**: Integration tests validate package behavior in Laravel environment.

```php
<?php

namespace AsteriskPbxManager\Tests\Integration;

use Orchestra\Testbench\TestCase;
use AsteriskPbxManager\AsteriskPbxManagerServiceProvider;

class PackageIntegrationTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [AsteriskPbxManagerServiceProvider::class];
    }

    public function testServiceProviderRegistration()
    {
        $this->assertTrue($this->app->bound('asterisk-manager'));
    }

    public function testConfigurationPublishing()
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'AsteriskPbxManager\AsteriskPbxManagerServiceProvider',
            '--tag' => 'config'
        ]);

        $this->assertFileExists(config_path('asterisk-pbx-manager.php'));
    }
}
```

## Deployment and Distribution

### Package Publishing
**Rationale**: Proper distribution enables easy adoption by Laravel developers.

#### Packagist Registration
- [ ] Create Packagist account and register package
- [ ] Set up automated version tagging
- [ ] Configure semantic versioning strategy
- [ ] Add package statistics monitoring

#### GitHub Repository Setup
- [ ] Create public GitHub repository with proper README
- [ ] Set up GitHub Actions for automated testing
- [ ] Add issue and pull request templates
- [ ] Configure automated security scanning

## Security Considerations

### AMI Security
**Rationale**: Asterisk Manager Interface requires careful security configuration.

- **Connection Security**: Use secure credentials and limit IP access
- **Action Permissions**: Implement granular AMI user permissions
- **Encryption**: Use TLS for AMI connections when possible
- **Audit Logging**: Log all AMI actions for security monitoring

### Laravel Integration Security
**Rationale**: Package must follow Laravel security best practices.

- **Configuration Validation**: Validate all configuration parameters
- **Input Sanitization**: Sanitize all user inputs to AMI commands
- **Error Handling**: Prevent information disclosure in error messages
- **Event Broadcasting**: Secure event channels and authentication

## Conclusion

This Laravel package will provide a robust, Laravel-native interface to Asterisk PBX systems using the proven PAMI library. By following Laravel conventions and best practices, the package will enable developers to easily integrate comprehensive telephony functionality into their applications.

The phased development approach ensures a solid foundation while building towards advanced features. Comprehensive testing and documentation will ensure reliability and ease of adoption by the Laravel community.

### Key Benefits

- **Native Laravel Integration**: Service providers, facades, and Artisan commands
- **Real-time Event Processing**: Laravel event system integration with broadcasting
- **Comprehensive AMI Support**: Full range of Asterisk Manager Interface operations
- **Database Integration**: Call logging and metrics with Eloquent models
- **Production Ready**: Robust error handling, logging, and monitoring tools

This package will serve as the foundation for building sophisticated telephony applications within the Laravel ecosystem while maintaining clean, maintainable, and testable code.