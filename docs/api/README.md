# Asterisk PBX Manager - API Documentation

This directory contains comprehensive API documentation for all public methods and classes in the Asterisk PBX Manager Laravel package.

## Overview

The Asterisk PBX Manager package provides a Laravel-native interface for Asterisk PBX systems through the Asterisk Manager Interface (AMI). The package is organized into several main components:

- **Services** - Core business logic and AMI operations
- **Facades** - Convenient static interfaces for common operations
- **Events** - Laravel event classes for Asterisk events
- **Models** - Eloquent models for data persistence
- **Commands** - Artisan commands for CLI operations

## Core API Documentation

### Services

#### [AsteriskManagerService](AsteriskManagerService.md)
The primary service class providing core AMI functionality including:
- Connection management (connect, disconnect, reconnect, ping)
- Call operations (originate, hangup, transfer)
- System information (status, channels, peers)
- Event management (listeners, monitoring)
- Generic AMI commands

#### [QueueManagerService](QueueManagerService.md)
Specialized service for queue management operations:
- Queue member management (add, remove, pause)
- Queue status monitoring and analytics
- Bulk operations for multiple queues/members
- Performance metrics and reporting

### Facades

#### [AsteriskManager Facade](AsteriskManagerFacade.md)
Primary facade providing static access to AMI functionality:
- All AsteriskManagerService methods via static interface
- Queue operation shortcuts
- Practical usage examples and best practices

## Quick Start Guide

### Basic Usage with Facade

```php
use AsteriskPbxManager\Facades\AsteriskManager;

// Check connection
if (AsteriskManager::isConnected()) {
    // Originate a call
    $result = AsteriskManager::originateCall('SIP/1001', '2002');
    
    // Get system status
    $status = AsteriskManager::getStatus();
    
    // Add queue member
    AsteriskManager::addQueueMember('support', 'SIP/1001');
}
```

### Service Injection

```php
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\QueueManagerService;

class CallController extends Controller
{
    public function __construct(
        private AsteriskManagerService $asteriskManager,
        private QueueManagerService $queueManager
    ) {}
    
    public function makeCall()
    {
        return $this->asteriskManager->originateCall('SIP/1001', '2002');
    }
}
```

## Event System

### Available Events

The package fires several Laravel events for different Asterisk events:

- **CallConnected** - When a call is established
- **CallEnded** - When a call terminates
- **QueueMemberAdded** - When a member is added to a queue
- **AsteriskEvent** - Generic event for all Asterisk events

### Event Listening

```php
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;

// Listen for call events
Event::listen(CallConnected::class, function (CallConnected $event) {
    Log::info('Call connected', [
        'unique_id' => $event->uniqueId,
        'channel' => $event->channel,
        'destination' => $event->destination
    ]);
});

Event::listen(CallEnded::class, function (CallEnded $event) {
    Log::info('Call ended', [
        'unique_id' => $event->uniqueId,
        'duration' => $event->duration,
        'cause' => $event->cause
    ]);
});
```

## Database Integration

### Models

#### CallLog Model
Stores call history and analytics:

```php
use AsteriskPbxManager\Models\CallLog;

// Get recent calls
$recentCalls = CallLog::recent()->get();

// Get calls by date range
$calls = CallLog::whereBetween('connected_at', [$start, $end])->get();

// Get calls by status
$completedCalls = CallLog::where('status', 'completed')->get();
```

#### AsteriskEvent Model
Stores Asterisk event data:

```php
use AsteriskPbxManager\Models\AsteriskEvent;

// Get recent events
$events = AsteriskEvent::recent()->get();

// Get events by type
$dialEvents = AsteriskEvent::where('event_name', 'Dial')->get();
```

### Migrations

The package includes migrations for:
- `asterisk_call_logs` - Call history and metadata
- `asterisk_events` - Event logging and analytics

## Artisan Commands

### Available Commands

- `asterisk:status` - Show Asterisk system status
- `asterisk:monitor-events` - Monitor events in real-time
- `asterisk:queue:add` - Add member to queue
- `asterisk:queue:remove` - Remove member from queue
- `asterisk:queue:pause` - Pause/unpause queue member
- `asterisk:queue:status` - Show queue status
- `asterisk:health-check` - Perform system health check

### Usage Examples

```bash
# Check system status
php artisan asterisk:status

# Monitor events in real-time
php artisan asterisk:monitor-events

# Add queue member
php artisan asterisk:queue:add support SIP/1001

# Show queue status
php artisan asterisk:queue:status support

# Health check with verbose output
php artisan asterisk:health-check --verbose
```

## Configuration

### Environment Variables

```env
# AMI Connection
ASTERISK_AMI_HOST=127.0.0.1
ASTERISK_AMI_PORT=5038
ASTERISK_AMI_USERNAME=admin
ASTERISK_AMI_SECRET=your_secret

# Connection Settings
ASTERISK_AMI_CONNECT_TIMEOUT=10
ASTERISK_AMI_READ_TIMEOUT=10
ASTERISK_AMI_SCHEME=tcp://

# Feature Settings
ASTERISK_EVENTS_ENABLED=true
ASTERISK_EVENTS_BROADCAST=true
ASTERISK_LOG_TO_DATABASE=true
```

### Configuration File

Publish the configuration file:

```bash
php artisan vendor:publish --provider="AsteriskPbxManager\AsteriskPbxManagerServiceProvider" --tag="config"
```

## Error Handling

### Exception Types

The package defines custom exceptions for different error scenarios:

- **AsteriskConnectionException** - Connection-related errors
- **ActionExecutionException** - AMI action execution failures

### Best Practices

```php
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;

try {
    $result = AsteriskManager::originateCall('SIP/1001', '2002');
} catch (AsteriskConnectionException $e) {
    // Handle connection issues
    Log::error('AMI connection failed: ' . $e->getMessage());
} catch (ActionExecutionException $e) {
    // Handle action failures
    Log::error('AMI action failed: ' . $e->getMessage());
} catch (\Exception $e) {
    // Handle unexpected errors
    Log::error('Unexpected error: ' . $e->getMessage());
}
```

## Performance Considerations

### Connection Management
- Reuse service instances when possible
- Implement connection pooling for high-load scenarios
- Configure appropriate timeouts for your environment

### Event Processing
- Use event filtering to reduce processing overhead
- Consider using queues for heavy event processing
- Implement caching for frequently accessed data

### Database Optimization
- Use proper indexes on frequently queried fields
- Consider partitioning large event tables
- Implement archiving for old call logs

## Testing

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Integration
./vendor/bin/phpunit --testsuite=Performance

# Generate coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Test Categories

- **Unit Tests** - Test individual classes and methods
- **Integration Tests** - Test component interactions
- **Performance Tests** - Test under load conditions

## Advanced Usage Patterns

### Middleware Integration

```php
use AsteriskPbxManager\Services\AsteriskManagerService;

class AsteriskConnectionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $asteriskManager = app(AsteriskManagerService::class);
        
        if (!$asteriskManager->isConnected()) {
            return response()->json(['error' => 'PBX unavailable'], 503);
        }
        
        return $next($request);
    }
}
```

### Job Queue Integration

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessCallAnalytics implements ShouldQueue
{
    public function handle(AsteriskManagerService $asterisk)
    {
        // Process call analytics in background
        $calls = $asterisk->getRecentCalls();
        $this->analyzeCallPatterns($calls);
    }
}
```

### Broadcasting Integration

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class CallStatusUpdate implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return new Channel('asterisk.calls');
    }
    
    public function broadcastWith()
    {
        return [
            'call_id' => $this->callId,
            'status' => $this->status,
            'timestamp' => now()
        ];
    }
}
```

## Troubleshooting

### Common Issues

1. **Connection Failed**
   - Check Asterisk service status
   - Verify AMI configuration in manager.conf
   - Confirm network connectivity and firewall rules

2. **Permission Denied**
   - Check AMI user permissions
   - Verify authentication credentials

3. **Events Not Received**
   - Ensure event logging is enabled in Asterisk
   - Check Laravel event listeners are registered

### Debug Mode

Enable debug logging:

```env
ASTERISK_LOG_LEVEL=debug
```

### Health Checks

Use the health check command:

```bash
php artisan asterisk:health-check --verbose
```

## Version Compatibility

- **PHP**: 8.4+
- **Laravel**: 12.0+
- **Asterisk**: 16.0+ (recommended 18.0+)
- **PAMI**: 2.0+

## Contributing

When contributing to the API:

1. Document all public methods with PHPDoc comments
2. Include practical examples in documentation
3. Add appropriate type hints and return types
4. Write comprehensive tests for new functionality
5. Update this documentation index when adding new components

## Support

For API-related questions:

1. Check the specific component documentation
2. Review the examples directory
3. Search existing GitHub issues
4. Create a new issue with detailed information

## See Also

- [Installation Guide](../README.md#installation)
- [Configuration Guide](../configuration.md)
- [Usage Examples](../examples/)
- [Troubleshooting Guide](../troubleshooting.md)
- [Contributing Guidelines](../CONTRIBUTING.md)