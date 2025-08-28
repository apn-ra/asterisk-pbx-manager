# Asterisk PBX Manager Laravel Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/apn-ra/asterisk-pbx-manager.svg?style=flat-square)](https://packagist.org/packages/apn-ra/asterisk-pbx-manager)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/apn-ra/asterisk-pbx-manager/run-tests?label=tests)](https://github.com/apn-ra/asterisk-pbx-manager/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/apn-ra/asterisk-pbx-manager/Check%20&%20fix%20styling?label=code%20style)](https://github.com/apn-ra/asterisk-pbx-manager/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/apn-ra/asterisk-pbx-manager.svg?style=flat-square)](https://packagist.org/packages/apn-ra/asterisk-pbx-manager)

A comprehensive Laravel package for Asterisk PBX management via the Asterisk Manager Interface (AMI). This package provides a modern, Laravel-native interface for integrating telephony functionality into your Laravel applications.

## Features

- **🔌 AMI Connection Management**: Robust connection handling with automatic reconnection
- **📡 Real-time Event Processing**: Laravel event system integration for Asterisk events
- **📞 Complete Action Support**: Originate calls, manage queues, control channels
- **🚀 Laravel Integration**: Native service providers, facades, and configuration
- **📺 Event Broadcasting**: Real-time updates via Laravel Broadcasting
- **💾 Database Logging**: Optional call logging and metrics storage
- **🔧 Artisan Commands**: CLI tools for AMI management and monitoring
- **🛡️ Type Safety**: Full PHP 8.4+ type hints and return types
- **🧪 Comprehensive Testing**: Unit and integration tests included

## Requirements

- PHP 8.4 or higher
- Laravel 12.0 or higher
- Asterisk PBX server with AMI enabled

## Installation

You can install the package via composer:

```bash
composer require apn-ra/asterisk-pbx-manager
```

### Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="AsteriskPbxManager\AsteriskPbxManagerServiceProvider" --tag="config"
```

### Publish Migrations

Publish and run the migrations (optional, for database logging):

```bash
php artisan vendor:publish --provider="AsteriskPbxManager\AsteriskPbxManagerServiceProvider" --tag="migrations"
php artisan migrate
```

## Configuration

### Environment Variables

Add the following environment variables to your `.env` file:

```env
# Asterisk AMI Configuration
ASTERISK_AMI_HOST=127.0.0.1
ASTERISK_AMI_PORT=5038
ASTERISK_AMI_USERNAME=admin
ASTERISK_AMI_SECRET=your_ami_secret
ASTERISK_AMI_CONNECT_TIMEOUT=10
ASTERISK_AMI_READ_TIMEOUT=10
ASTERISK_AMI_SCHEME=tcp://

# Event Configuration
ASTERISK_EVENTS_ENABLED=true
ASTERISK_EVENTS_BROADCAST=true
ASTERISK_LOG_TO_DATABASE=true
```

### Configuration File

The package configuration file (`config/asterisk-pbx-manager.php`) provides comprehensive options:

```php
return [
    'connection' => [
        'host' => env('ASTERISK_AMI_HOST', '127.0.0.1'),
        'port' => env('ASTERISK_AMI_PORT', 5038),
        'username' => env('ASTERISK_AMI_USERNAME', 'admin'),
        'secret' => env('ASTERISK_AMI_SECRET'),
        'connect_timeout' => env('ASTERISK_AMI_CONNECT_TIMEOUT', 10),
        'read_timeout' => env('ASTERISK_AMI_READ_TIMEOUT', 10),
        'scheme' => env('ASTERISK_AMI_SCHEME', 'tcp://'),
    ],
    
    'events' => [
        'enabled' => env('ASTERISK_EVENTS_ENABLED', true),
        'broadcast' => env('ASTERISK_EVENTS_BROADCAST', true),
        'log_to_database' => env('ASTERISK_LOG_TO_DATABASE', true),
    ],
    
    'logging' => [
        'enabled' => env('ASTERISK_LOGGING_ENABLED', true),
        'channel' => env('ASTERISK_LOG_CHANNEL', 'single'),
        'level' => env('ASTERISK_LOG_LEVEL', 'info'),
    ],
];
```

## Basic Usage

### Using the Facade

```php
use AsteriskPbxManager\Facades\AsteriskManager;

// Check connection status
if (AsteriskManager::isConnected()) {
    echo "Connected to Asterisk";
}

// Originate a call
$result = AsteriskManager::originateCall('SIP/1001', '2002');

if ($result) {
    echo "Call originated successfully";
}

// Get system status
$status = AsteriskManager::getStatus();
```

### Using Dependency Injection

```php
use AsteriskPbxManager\Services\AsteriskManagerService;

class CallController extends Controller
{
    public function __construct(
        private AsteriskManagerService $asteriskManager
    ) {}

    public function makeCall(Request $request)
    {
        $channel = $request->input('channel');
        $extension = $request->input('extension');
        
        try {
            $result = $this->asteriskManager->originateCall($channel, $extension);
            
            return response()->json([
                'success' => $result,
                'message' => $result ? 'Call initiated' : 'Call failed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
```

## Advanced Usage

### Queue Management

```php
use AsteriskPbxManager\Services\QueueManagerService;

class QueueController extends Controller
{
    public function __construct(
        private QueueManagerService $queueManager
    ) {}

    public function addAgent(Request $request)
    {
        $queue = $request->input('queue');
        $member = $request->input('member');
        
        $result = $this->queueManager->addMember($queue, $member);
        
        return response()->json(['success' => $result]);
    }

    public function getQueueStatus(string $queue)
    {
        $status = $this->queueManager->getQueueStatus($queue);
        
        return response()->json($status);
    }
}
```

### Event Handling

The package automatically fires Laravel events for Asterisk events:

```php
// Listen for call events
Event::listen(\AsteriskPbxManager\Events\CallConnected::class, function ($event) {
    Log::info('Call connected', [
        'unique_id' => $event->uniqueId,
        'channel' => $event->channel,
        'caller_id' => $event->callerId,
    ]);
});

Event::listen(\AsteriskPbxManager\Events\CallEnded::class, function ($event) {
    Log::info('Call ended', [
        'unique_id' => $event->uniqueId,
        'duration' => $event->duration,
        'cause' => $event->cause,
    ]);
});
```

### Custom Event Listeners

Create custom event listeners:

```php
use AsteriskPbxManager\Events\CallConnected;

class LogCallActivity
{
    public function handle(CallConnected $event)
    {
        // Custom logging logic
        CallLog::create([
            'unique_id' => $event->uniqueId,
            'channel' => $event->channel,
            'caller_id' => $event->callerId,
            'connected_at' => now(),
        ]);
    }
}
```

Register in your `EventServiceProvider`:

```php
protected $listen = [
    \AsteriskPbxManager\Events\CallConnected::class => [
        \App\Listeners\LogCallActivity::class,
    ],
];
```

### Broadcasting Events

Enable real-time updates by configuring broadcasting:

```php
// In your broadcasting configuration
'channels' => [
    'asterisk' => [
        'driver' => 'pusher', // or your preferred driver
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
        ],
    ],
],
```

Listen for events in JavaScript:

```javascript
Echo.channel('asterisk.calls')
    .listen('CallConnected', (e) => {
        console.log('Call connected:', e);
    })
    .listen('CallEnded', (e) => {
        console.log('Call ended:', e);
    });
```

## Artisan Commands

### Monitor Asterisk Status

```bash
php artisan asterisk:status
```

### Monitor Events in Real-time

```bash
php artisan asterisk:monitor-events
```

### Queue Management

```bash
# Add queue member
php artisan asterisk:queue:add support SIP/1001

# Remove queue member  
php artisan asterisk:queue:remove support SIP/1001

# Pause queue member
php artisan asterisk:queue:pause support SIP/1001

# Get queue status
php artisan asterisk:queue:status support
```

### Health Check

```bash
php artisan asterisk:health-check
```

## Database Models

### CallLog Model

Track call history and analytics:

```php
use AsteriskPbxManager\Models\CallLog;

// Get recent calls
$recentCalls = CallLog::recent()->get();

// Get calls by date range
$calls = CallLog::whereBetween('created_at', [$startDate, $endDate])->get();

// Get calls by caller ID
$calls = CallLog::whereCallerId('1001')->get();
```

### AsteriskEvent Model

Store and query Asterisk events:

```php
use AsteriskPbxManager\Models\AsteriskEvent;

// Get recent events
$events = AsteriskEvent::recent()->get();

// Get events by type
$dialEvents = AsteriskEvent::whereEventName('Dial')->get();

// Get events by unique ID
$callEvents = AsteriskEvent::whereUniqueId($uniqueId)->get();
```

## Error Handling

The package provides custom exceptions for different error scenarios:

```php
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;

try {
    $result = AsteriskManager::originateCall('SIP/1001', '2002');
} catch (AsteriskConnectionException $e) {
    Log::error('AMI connection failed: ' . $e->getMessage());
} catch (ActionExecutionException $e) {
    Log::error('Action execution failed: ' . $e->getMessage());
} catch (\Exception $e) {
    Log::error('Unexpected error: ' . $e->getMessage());
}
```

## Testing

The package includes comprehensive tests. Run the test suite:

```bash
composer test
```

Run specific test suites:

```bash
# Unit tests only
./vendor/bin/phpunit --testsuite=Unit

# Integration tests only
./vendor/bin/phpunit --testsuite=Integration

# Performance tests only
./vendor/bin/phpunit --testsuite=Performance
```

Generate code coverage:

```bash
./vendor/bin/phpunit --coverage-html coverage
```

## Security

### Configuration Security

- Always use environment variables for sensitive configuration
- Never commit AMI credentials to version control
- Use strong, unique AMI passwords
- Restrict AMI access to specific IP addresses when possible

### Input Validation

The package automatically validates all inputs to AMI commands to prevent injection attacks.

### Error Handling

Errors are logged without exposing sensitive information in responses.

## Performance Considerations

### Connection Management

- The package uses connection pooling for optimal performance
- Connections are automatically reestablished if lost
- Configure appropriate timeouts for your environment

### Event Processing

- Use Laravel queues for heavy event processing
- Configure event filtering to reduce processing overhead
- Consider using Redis for event caching in high-load scenarios

### Database Optimization

- Use database indexes for frequently queried fields
- Consider partitioning large event tables
- Regularly archive old call logs to maintain performance

## Troubleshooting

### Common Issues

#### Connection Failed

```
AsteriskConnectionException: Failed to connect to AMI
```

**Solutions:**
- Verify Asterisk is running and AMI is enabled
- Check AMI configuration in `/etc/asterisk/manager.conf`
- Verify network connectivity and firewall rules
- Confirm AMI credentials are correct

#### Permission Denied

```
ActionExecutionException: Action not permitted
```

**Solutions:**
- Check AMI user permissions in `manager.conf`
- Verify the user has necessary action permissions
- Confirm authentication is successful

#### Event Not Received

**Solutions:**
- Verify event logging is enabled in Asterisk
- Check Laravel event listeners are registered
- Confirm broadcasting configuration if using real-time updates

### Debug Mode

Enable debug logging for detailed troubleshooting:

```env
ASTERISK_LOG_LEVEL=debug
```

### Health Checks

Use the health check command to verify system status:

```bash
php artisan asterisk:health-check --verbose
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details on how to contribute to this project.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [APN RA](https://github.com/apn-ra)
- [Jetbrains Junie](https://www.jetbrains.com/junie/)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

If you discover any issues or have questions, please:

1. Check the [troubleshooting section](#troubleshooting)
2. Search existing [issues](https://github.com/apn-ra/asterisk-pbx-manager/issues)
3. Create a new issue with detailed information

For commercial support and consulting, please contact [support@apntelecom.com](mailto:support@apntelecom.com).