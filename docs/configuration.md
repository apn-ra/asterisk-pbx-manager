# Asterisk PBX Manager - Configuration Guide

This guide provides comprehensive documentation for all configuration options available in the Asterisk PBX Manager Laravel package.

## Table of Contents

- [Installation and Setup](#installation-and-setup)
- [Environment Variables](#environment-variables)
- [Configuration File](#configuration-file)
- [Connection Settings](#connection-settings)
- [Event Configuration](#event-configuration)
- [Logging Configuration](#logging-configuration)
- [Performance Settings](#performance-settings)
- [Security Configuration](#security-configuration)
- [Development Settings](#development-settings)
- [Production Optimization](#production-optimization)
- [Advanced Configuration](#advanced-configuration)
- [Configuration Validation](#configuration-validation)
- [Troubleshooting Configuration](#troubleshooting-configuration)

## Installation and Setup

### 1. Package Installation

Install the package via Composer:

```bash
composer require apn-ra/asterisk-pbx-manager
```

### 2. Configuration Publishing

Publish the configuration file:

```bash
php artisan vendor:publish --provider="AsteriskPbxManager\AsteriskPbxManagerServiceProvider" --tag="config"
```

This creates `config/asterisk-pbx-manager.php` in your Laravel application.

### 3. Migration Publishing (Optional)

If you want to customize the database tables:

```bash
php artisan vendor:publish --provider="AsteriskPbxManager\AsteriskPbxManagerServiceProvider" --tag="migrations"
```

### 4. Run Migrations

Execute the migrations to create required database tables:

```bash
php artisan migrate
```

## Environment Variables

All configuration can be controlled through environment variables in your `.env` file.

### Core Connection Settings

```env
# Asterisk Manager Interface Connection
ASTERISK_AMI_HOST=127.0.0.1
ASTERISK_AMI_PORT=5038
ASTERISK_AMI_USERNAME=admin
ASTERISK_AMI_SECRET=your_ami_secret_key
ASTERISK_AMI_CONNECT_TIMEOUT=10
ASTERISK_AMI_READ_TIMEOUT=10
ASTERISK_AMI_SCHEME=tcp://
```

**Variable Details:**

- `ASTERISK_AMI_HOST` - Asterisk server hostname or IP address
- `ASTERISK_AMI_PORT` - AMI port (default: 5038)
- `ASTERISK_AMI_USERNAME` - AMI username
- `ASTERISK_AMI_SECRET` - AMI password/secret
- `ASTERISK_AMI_CONNECT_TIMEOUT` - Connection timeout in seconds
- `ASTERISK_AMI_READ_TIMEOUT` - Read timeout in seconds
- `ASTERISK_AMI_SCHEME` - Connection scheme (tcp:// or ssl://)

### Event Management Settings

```env
# Event Processing
ASTERISK_EVENTS_ENABLED=true
ASTERISK_EVENTS_BROADCAST=true
ASTERISK_LOG_TO_DATABASE=true
ASTERISK_EVENTS_QUEUE_PROCESSING=false
ASTERISK_EVENT_BUFFER_SIZE=1000
ASTERISK_EVENT_BATCH_SIZE=50
```

**Variable Details:**

- `ASTERISK_EVENTS_ENABLED` - Enable/disable event processing
- `ASTERISK_EVENTS_BROADCAST` - Enable Laravel event broadcasting
- `ASTERISK_LOG_TO_DATABASE` - Store events in database
- `ASTERISK_EVENTS_QUEUE_PROCESSING` - Use queue for event processing
- `ASTERISK_EVENT_BUFFER_SIZE` - Event buffer size for batch processing
- `ASTERISK_EVENT_BATCH_SIZE` - Number of events per batch

### Logging Configuration

```env
# Logging Settings
ASTERISK_LOGGING_ENABLED=true
ASTERISK_LOG_CHANNEL=single
ASTERISK_LOG_LEVEL=info
ASTERISK_LOG_AMI_COMMANDS=false
ASTERISK_LOG_AMI_RESPONSES=false
ASTERISK_LOG_CONNECTION_EVENTS=true
```

**Variable Details:**

- `ASTERISK_LOGGING_ENABLED` - Enable/disable package logging
- `ASTERISK_LOG_CHANNEL` - Laravel log channel to use
- `ASTERISK_LOG_LEVEL` - Log level (debug, info, warning, error)
- `ASTERISK_LOG_AMI_COMMANDS` - Log all AMI commands (debug only)
- `ASTERISK_LOG_AMI_RESPONSES` - Log all AMI responses (debug only)
- `ASTERISK_LOG_CONNECTION_EVENTS` - Log connection events

### Performance Settings

```env
# Performance Optimization
ASTERISK_CONNECTION_POOL_SIZE=5
ASTERISK_RECONNECT_ATTEMPTS=3
ASTERISK_RECONNECT_DELAY=5
ASTERISK_KEEPALIVE_ENABLED=true
ASTERISK_KEEPALIVE_INTERVAL=30
ASTERISK_MAX_CONCURRENT_ACTIONS=10
```

**Variable Details:**

- `ASTERISK_CONNECTION_POOL_SIZE` - Number of pooled connections
- `ASTERISK_RECONNECT_ATTEMPTS` - Auto-reconnection attempts
- `ASTERISK_RECONNECT_DELAY` - Delay between reconnection attempts (seconds)
- `ASTERISK_KEEPALIVE_ENABLED` - Enable connection keepalive
- `ASTERISK_KEEPALIVE_INTERVAL` - Keepalive ping interval (seconds)
- `ASTERISK_MAX_CONCURRENT_ACTIONS` - Maximum concurrent AMI actions

### Security Settings

```env
# Security Configuration
ASTERISK_VERIFY_SSL=true
ASTERISK_SSL_CERT_PATH=
ASTERISK_SSL_KEY_PATH=
ASTERISK_SSL_CA_PATH=
ASTERISK_ALLOWED_IPS=
ASTERISK_RATE_LIMIT_ENABLED=true
ASTERISK_RATE_LIMIT_REQUESTS=100
ASTERISK_RATE_LIMIT_PERIOD=60
```

**Variable Details:**

- `ASTERISK_VERIFY_SSL` - Verify SSL certificates for secure connections
- `ASTERISK_SSL_CERT_PATH` - Path to SSL certificate file
- `ASTERISK_SSL_KEY_PATH` - Path to SSL private key file
- `ASTERISK_SSL_CA_PATH` - Path to SSL CA bundle
- `ASTERISK_ALLOWED_IPS` - Comma-separated list of allowed IP addresses
- `ASTERISK_RATE_LIMIT_ENABLED` - Enable rate limiting
- `ASTERISK_RATE_LIMIT_REQUESTS` - Maximum requests per period
- `ASTERISK_RATE_LIMIT_PERIOD` - Rate limit period in seconds

### Development Settings

```env
# Development & Testing
ASTERISK_MOCK_MODE=false
ASTERISK_MOCK_RESPONSES_PATH=
ASTERISK_DEBUG_MODE=false
ASTERISK_ENABLE_PROFILING=false
ASTERISK_CACHE_RESPONSES=false
ASTERISK_CACHE_TTL=300
```

**Variable Details:**

- `ASTERISK_MOCK_MODE` - Enable mock mode for testing
- `ASTERISK_MOCK_RESPONSES_PATH` - Path to mock response files
- `ASTERISK_DEBUG_MODE` - Enable debug mode with verbose logging
- `ASTERISK_ENABLE_PROFILING` - Enable performance profiling
- `ASTERISK_CACHE_RESPONSES` - Cache AMI responses for performance
- `ASTERISK_CACHE_TTL` - Cache time-to-live in seconds

## Configuration File

The complete configuration file structure at `config/asterisk-pbx-manager.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asterisk Manager Interface Connection
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the Asterisk Manager Interface (AMI).
    | These settings control how the package connects to your Asterisk server.
    |
    */

    'connection' => [
        'host' => env('ASTERISK_AMI_HOST', '127.0.0.1'),
        'port' => env('ASTERISK_AMI_PORT', 5038),
        'username' => env('ASTERISK_AMI_USERNAME', 'admin'),
        'secret' => env('ASTERISK_AMI_SECRET'),
        'connect_timeout' => env('ASTERISK_AMI_CONNECT_TIMEOUT', 10),
        'read_timeout' => env('ASTERISK_AMI_READ_TIMEOUT', 10),
        'scheme' => env('ASTERISK_AMI_SCHEME', 'tcp://'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how Asterisk events are processed, broadcasted, and stored.
    | Event processing can significantly impact performance in high-volume
    | environments.
    |
    */

    'events' => [
        'enabled' => env('ASTERISK_EVENTS_ENABLED', true),
        'broadcast' => env('ASTERISK_EVENTS_BROADCAST', true),
        'log_to_database' => env('ASTERISK_LOG_TO_DATABASE', true),
        'queue_processing' => env('ASTERISK_EVENTS_QUEUE_PROCESSING', false),
        'buffer_size' => env('ASTERISK_EVENT_BUFFER_SIZE', 1000),
        'batch_size' => env('ASTERISK_EVENT_BATCH_SIZE', 50),
        
        // Event filtering
        'filters' => [
            // Include only these event types (empty = all)
            'include' => [],
            
            // Exclude these event types
            'exclude' => [
                'RTCPSent',
                'RTCPReceived',
                'DTMF',
            ],
        ],
        
        // Broadcasting channels
        'broadcasting' => [
            'default_channel' => 'asterisk',
            'private_channels' => true,
            'channel_prefix' => 'asterisk.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for the package. Separate from Laravel's
    | general logging system to allow fine-tuned control.
    |
    */

    'logging' => [
        'enabled' => env('ASTERISK_LOGGING_ENABLED', true),
        'channel' => env('ASTERISK_LOG_CHANNEL', 'single'),
        'level' => env('ASTERISK_LOG_LEVEL', 'info'),
        'ami_commands' => env('ASTERISK_LOG_AMI_COMMANDS', false),
        'ami_responses' => env('ASTERISK_LOG_AMI_RESPONSES', false),
        'connection_events' => env('ASTERISK_LOG_CONNECTION_EVENTS', true),
        
        // Log formatting
        'format' => [
            'include_timestamp' => true,
            'include_memory_usage' => false,
            'include_request_id' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Performance optimization settings for high-volume environments.
    | Adjust these based on your server capacity and usage patterns.
    |
    */

    'performance' => [
        'connection_pool_size' => env('ASTERISK_CONNECTION_POOL_SIZE', 5),
        'reconnect_attempts' => env('ASTERISK_RECONNECT_ATTEMPTS', 3),
        'reconnect_delay' => env('ASTERISK_RECONNECT_DELAY', 5),
        'keepalive_enabled' => env('ASTERISK_KEEPALIVE_ENABLED', true),
        'keepalive_interval' => env('ASTERISK_KEEPALIVE_INTERVAL', 30),
        'max_concurrent_actions' => env('ASTERISK_MAX_CONCURRENT_ACTIONS', 10),
        
        // Caching settings
        'cache' => [
            'enabled' => env('ASTERISK_CACHE_RESPONSES', false),
            'ttl' => env('ASTERISK_CACHE_TTL', 300),
            'store' => 'redis', // Laravel cache store to use
            'prefix' => 'asterisk:',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for protecting AMI connections and rate limiting.
    |
    */

    'security' => [
        // SSL/TLS settings
        'ssl' => [
            'verify' => env('ASTERISK_VERIFY_SSL', true),
            'cert_path' => env('ASTERISK_SSL_CERT_PATH'),
            'key_path' => env('ASTERISK_SSL_KEY_PATH'),
            'ca_path' => env('ASTERISK_SSL_CA_PATH'),
        ],
        
        // Access control
        'access' => [
            'allowed_ips' => array_filter(explode(',', env('ASTERISK_ALLOWED_IPS', ''))),
            'require_authentication' => true,
        ],
        
        // Rate limiting
        'rate_limit' => [
            'enabled' => env('ASTERISK_RATE_LIMIT_ENABLED', true),
            'requests' => env('ASTERISK_RATE_LIMIT_REQUESTS', 100),
            'period' => env('ASTERISK_RATE_LIMIT_PERIOD', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for queue operations and management.
    |
    */

    'queues' => [
        'default_strategy' => 'fewestcalls',
        'default_timeout' => 30,
        'member_defaults' => [
            'penalty' => 0,
            'paused' => false,
        ],
        
        // Queue monitoring
        'monitoring' => [
            'enabled' => true,
            'interval' => 60, // seconds
            'metrics_retention' => 30, // days
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for database storage of call logs and events.
    |
    */

    'database' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
        
        'tables' => [
            'call_logs' => 'asterisk_call_logs',
            'events' => 'asterisk_events',
        ],
        
        // Data retention policies
        'retention' => [
            'call_logs_days' => 365,
            'events_days' => 90,
            'cleanup_enabled' => true,
            'cleanup_schedule' => '0 2 * * *', // Daily at 2 AM
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Testing
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */

    'development' => [
        'mock_mode' => env('ASTERISK_MOCK_MODE', false),
        'mock_responses_path' => env('ASTERISK_MOCK_RESPONSES_PATH', storage_path('asterisk/mocks')),
        'debug_mode' => env('ASTERISK_DEBUG_MODE', false),
        'enable_profiling' => env('ASTERISK_ENABLE_PROFILING', false),
        
        // Testing helpers
        'testing' => [
            'fake_events' => false,
            'mock_ami_responses' => false,
            'simulate_connection_failures' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Configuration
    |--------------------------------------------------------------------------
    |
    | Advanced settings for specialized use cases.
    |
    */

    'advanced' => [
        // Custom event processors
        'event_processors' => [
            // 'custom_processor' => App\Services\CustomEventProcessor::class,
        ],
        
        // Custom action executors
        'action_executors' => [
            // 'custom_action' => App\Services\CustomActionExecutor::class,
        ],
        
        // Middleware
        'middleware' => [
            'connection' => [
                // App\Middleware\AsteriskConnectionMiddleware::class,
            ],
            'action' => [
                // App\Middleware\AsteriskActionMiddleware::class,
            ],
        ],
    ],
];
```

## Connection Settings

### Basic Connection

Minimal configuration for local Asterisk server:

```env
ASTERISK_AMI_HOST=127.0.0.1
ASTERISK_AMI_PORT=5038
ASTERISK_AMI_USERNAME=admin
ASTERISK_AMI_SECRET=your_password
```

### Remote Connection

Configuration for remote Asterisk server:

```env
ASTERISK_AMI_HOST=192.168.1.100
ASTERISK_AMI_PORT=5038
ASTERISK_AMI_USERNAME=remote_admin
ASTERISK_AMI_SECRET=secure_password
ASTERISK_AMI_CONNECT_TIMEOUT=15
ASTERISK_AMI_READ_TIMEOUT=15
```

### Secure Connection (SSL/TLS)

Configuration for encrypted connection:

```env
ASTERISK_AMI_HOST=asterisk.example.com
ASTERISK_AMI_PORT=5039
ASTERISK_AMI_SCHEME=ssl://
ASTERISK_AMI_USERNAME=secure_admin
ASTERISK_AMI_SECRET=very_secure_password
ASTERISK_VERIFY_SSL=true
ASTERISK_SSL_CERT_PATH=/path/to/client.crt
ASTERISK_SSL_KEY_PATH=/path/to/client.key
ASTERISK_SSL_CA_PATH=/path/to/ca-bundle.crt
```

## Event Configuration

### Basic Event Processing

Enable event processing with database logging:

```env
ASTERISK_EVENTS_ENABLED=true
ASTERISK_LOG_TO_DATABASE=true
ASTERISK_EVENTS_BROADCAST=true
```

### High-Volume Event Processing

Configuration for high-volume environments:

```env
ASTERISK_EVENTS_ENABLED=true
ASTERISK_EVENTS_QUEUE_PROCESSING=true
ASTERISK_EVENT_BUFFER_SIZE=5000
ASTERISK_EVENT_BATCH_SIZE=100
ASTERISK_LOG_TO_DATABASE=true
```

### Event Broadcasting Setup

Configuration for real-time event broadcasting:

```env
ASTERISK_EVENTS_BROADCAST=true
BROADCAST_DRIVER=redis
QUEUE_CONNECTION=redis
```

Add to `config/broadcasting.php`:

```php
'channels' => [
    'asterisk' => [
        'driver' => 'pusher',
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

## Logging Configuration

### Development Logging

Verbose logging for development:

```env
ASTERISK_LOGGING_ENABLED=true
ASTERISK_LOG_LEVEL=debug
ASTERISK_LOG_AMI_COMMANDS=true
ASTERISK_LOG_AMI_RESPONSES=true
ASTERISK_DEBUG_MODE=true
```

### Production Logging

Optimized logging for production:

```env
ASTERISK_LOGGING_ENABLED=true
ASTERISK_LOG_LEVEL=warning
ASTERISK_LOG_AMI_COMMANDS=false
ASTERISK_LOG_AMI_RESPONSES=false
ASTERISK_LOG_CONNECTION_EVENTS=true
```

### Custom Log Channel

Create a custom log channel in `config/logging.php`:

```php
'channels' => [
    'asterisk' => [
        'driver' => 'daily',
        'path' => storage_path('logs/asterisk.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

Then use it:

```env
ASTERISK_LOG_CHANNEL=asterisk
```

## Performance Settings

### High-Performance Configuration

For high-volume production environments:

```env
ASTERISK_CONNECTION_POOL_SIZE=10
ASTERISK_MAX_CONCURRENT_ACTIONS=20
ASTERISK_KEEPALIVE_ENABLED=true
ASTERISK_KEEPALIVE_INTERVAL=15
ASTERISK_CACHE_RESPONSES=true
ASTERISK_CACHE_TTL=300
```

### Low-Resource Configuration

For resource-constrained environments:

```env
ASTERISK_CONNECTION_POOL_SIZE=2
ASTERISK_MAX_CONCURRENT_ACTIONS=5
ASTERISK_KEEPALIVE_ENABLED=false
ASTERISK_EVENTS_QUEUE_PROCESSING=false
ASTERISK_EVENT_BUFFER_SIZE=100
```

## Security Configuration

### IP Access Control

Restrict access to specific IP addresses:

```env
ASTERISK_ALLOWED_IPS=192.168.1.0/24,10.0.0.0/8
```

### Rate Limiting

Configure rate limiting to prevent abuse:

```env
ASTERISK_RATE_LIMIT_ENABLED=true
ASTERISK_RATE_LIMIT_REQUESTS=50
ASTERISK_RATE_LIMIT_PERIOD=60
```

### SSL Certificate Configuration

For client certificate authentication:

```env
ASTERISK_AMI_SCHEME=ssl://
ASTERISK_VERIFY_SSL=true
ASTERISK_SSL_CERT_PATH=/etc/ssl/certs/asterisk-client.crt
ASTERISK_SSL_KEY_PATH=/etc/ssl/private/asterisk-client.key
ASTERISK_SSL_CA_PATH=/etc/ssl/certs/ca-certificates.crt
```

## Development Settings

### Mock Mode for Testing

Enable mock mode for unit testing:

```env
ASTERISK_MOCK_MODE=true
ASTERISK_MOCK_RESPONSES_PATH=tests/fixtures/asterisk
```

Create mock response files in the specified directory:

```
tests/fixtures/asterisk/
├── login_success.json
├── originate_success.json
├── queue_status.json
└── events/
    ├── dial_event.json
    └── hangup_event.json
```

### Debug Mode

Enable comprehensive debugging:

```env
ASTERISK_DEBUG_MODE=true
ASTERISK_ENABLE_PROFILING=true
ASTERISK_LOG_LEVEL=debug
ASTERISK_LOG_AMI_COMMANDS=true
ASTERISK_LOG_AMI_RESPONSES=true
```

## Production Optimization

### Optimized Production Configuration

```env
# Connection optimization
ASTERISK_CONNECTION_POOL_SIZE=8
ASTERISK_KEEPALIVE_ENABLED=true
ASTERISK_KEEPALIVE_INTERVAL=30
ASTERISK_MAX_CONCURRENT_ACTIONS=15

# Event processing optimization
ASTERISK_EVENTS_QUEUE_PROCESSING=true
ASTERISK_EVENT_BUFFER_SIZE=2000
ASTERISK_EVENT_BATCH_SIZE=50

# Caching
ASTERISK_CACHE_RESPONSES=true
ASTERISK_CACHE_TTL=600

# Logging optimization
ASTERISK_LOG_LEVEL=error
ASTERISK_LOG_AMI_COMMANDS=false
ASTERISK_LOG_AMI_RESPONSES=false

# Security
ASTERISK_RATE_LIMIT_ENABLED=true
ASTERISK_VERIFY_SSL=true
```

### Database Optimization

Configure database settings for performance:

```php
'database' => [
    'connection' => 'mysql',
    
    'retention' => [
        'call_logs_days' => 180,  // Reduce retention period
        'events_days' => 30,      // Keep events shorter
        'cleanup_enabled' => true,
        'cleanup_schedule' => '0 3 * * *', // 3 AM daily
    ],
],
```

## Advanced Configuration

### Custom Event Processors

Register custom event processors:

```php
'advanced' => [
    'event_processors' => [
        'call_analytics' => App\Services\CallAnalyticsProcessor::class,
        'quality_monitor' => App\Services\QualityMonitorProcessor::class,
    ],
],
```

### Custom Middleware

Add custom middleware for request processing:

```php
'advanced' => [
    'middleware' => [
        'connection' => [
            App\Middleware\AsteriskAuthMiddleware::class,
            App\Middleware\AsteriskLoggingMiddleware::class,
        ],
        'action' => [
            App\Middleware\AsteriskRateLimitMiddleware::class,
        ],
    ],
],
```

### Event Filtering

Configure event filtering to process only relevant events:

```php
'events' => [
    'filters' => [
        'include' => [
            'Dial',
            'Hangup',
            'QueueMemberAdded',
            'QueueMemberRemoved',
            'Bridge',
        ],
        'exclude' => [
            'RTCPSent',
            'RTCPReceived',
            'DTMF',
            'NewExten',
        ],
    ],
],
```

## Configuration Validation

### Validation Command

Create an Artisan command to validate configuration:

```bash
php artisan asterisk:validate-config
```

### Programmatic Validation

Validate configuration in your code:

```php
use AsteriskPbxManager\Services\ConfigurationValidator;

$validator = app(ConfigurationValidator::class);
$result = $validator->validate();

if (!$result->isValid()) {
    foreach ($result->getErrors() as $error) {
        Log::error('Configuration error: ' . $error);
    }
}
```

### Health Check

Use the health check command to verify configuration:

```bash
php artisan asterisk:health-check --config
```

## Troubleshooting Configuration

### Common Configuration Issues

1. **Connection Timeout**
   ```env
   # Increase timeouts for slow networks
   ASTERISK_AMI_CONNECT_TIMEOUT=30
   ASTERISK_AMI_READ_TIMEOUT=30
   ```

2. **Event Processing Lag**
   ```env
   # Enable queue processing for high volume
   ASTERISK_EVENTS_QUEUE_PROCESSING=true
   ASTERISK_EVENT_BUFFER_SIZE=5000
   ```

3. **Memory Issues**
   ```env
   # Reduce buffer sizes
   ASTERISK_EVENT_BUFFER_SIZE=500
   ASTERISK_EVENT_BATCH_SIZE=25
   ASTERISK_CONNECTION_POOL_SIZE=3
   ```

4. **SSL Connection Issues**
   ```env
   # Disable SSL verification for testing
   ASTERISK_VERIFY_SSL=false
   # Or specify correct certificate paths
   ASTERISK_SSL_CA_PATH=/path/to/correct/ca.pem
   ```

### Debug Configuration

Enable comprehensive debugging:

```env
ASTERISK_DEBUG_MODE=true
ASTERISK_LOG_LEVEL=debug
APP_DEBUG=true
```

### Configuration Testing

Test configuration changes:

```bash
# Test connection
php artisan asterisk:status

# Test events
php artisan asterisk:monitor-events --duration=30

# Validate all settings
php artisan asterisk:health-check --verbose
```

## Environment-Specific Configurations

### Development Environment

```env
# .env.local
ASTERISK_AMI_HOST=localhost
ASTERISK_DEBUG_MODE=true
ASTERISK_LOG_LEVEL=debug
ASTERISK_MOCK_MODE=false
ASTERISK_CACHE_RESPONSES=false
```

### Testing Environment

```env
# .env.testing
ASTERISK_MOCK_MODE=true
ASTERISK_EVENTS_ENABLED=false
ASTERISK_LOG_TO_DATABASE=false
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

### Staging Environment

```env
# .env.staging
ASTERISK_AMI_HOST=staging-asterisk.internal
ASTERISK_LOG_LEVEL=info
ASTERISK_EVENTS_QUEUE_PROCESSING=true
ASTERISK_CACHE_RESPONSES=true
```

### Production Environment

```env
# .env.production
ASTERISK_AMI_HOST=prod-asterisk.internal
ASTERISK_AMI_SCHEME=ssl://
ASTERISK_VERIFY_SSL=true
ASTERISK_LOG_LEVEL=error
ASTERISK_EVENTS_QUEUE_PROCESSING=true
ASTERISK_CACHE_RESPONSES=true
ASTERISK_RATE_LIMIT_ENABLED=true
```

## Best Practices

1. **Security**: Always use SSL in production and restrict IP access
2. **Performance**: Enable connection pooling and caching for high-volume environments
3. **Monitoring**: Use appropriate log levels and enable health checks
4. **Testing**: Use mock mode for unit tests and staging for integration tests
5. **Maintenance**: Configure data retention and cleanup policies
6. **Documentation**: Document any custom configuration for your team

## See Also

- [API Documentation](api/README.md)
- [Usage Examples](examples/)
- [Troubleshooting Guide](troubleshooting.md)
- [Performance Tuning](performance.md)
- [Security Guide](security.md)