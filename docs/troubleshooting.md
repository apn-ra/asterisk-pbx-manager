# Asterisk PBX Manager - Troubleshooting Guide

This guide provides comprehensive troubleshooting information for the Asterisk PBX Manager Laravel package, covering common issues, solutions, and debugging techniques.

## Table of Contents

- [General Troubleshooting](#general-troubleshooting)
- [Connection Issues](#connection-issues)
- [Authentication Problems](#authentication-problems)
- [Event Processing Issues](#event-processing-issues)
- [Performance Problems](#performance-problems)
- [Queue Management Issues](#queue-management-issues)
- [Database Problems](#database-problems)
- [Configuration Issues](#configuration-issues)
- [Laravel Integration Problems](#laravel-integration-problems)
- [Development and Testing Issues](#development-and-testing-issues)
- [Debugging Tools and Techniques](#debugging-tools-and-techniques)
- [Log Analysis](#log-analysis)
- [Performance Debugging](#performance-debugging)
- [Common Error Messages](#common-error-messages)
- [FAQ](#faq)

## General Troubleshooting

### Initial Diagnostic Steps

Before diving into specific issues, perform these general diagnostic steps:

1. **Check Package Installation**
   ```bash
   composer show apn-ra/asterisk-pbx-manager
   ```

2. **Verify Configuration**
   ```bash
   php artisan asterisk:health-check --verbose
   ```

3. **Check Asterisk Server Status**
   ```bash
   # On Asterisk server
   asterisk -rvvv
   ```

4. **Verify AMI Configuration**
   ```bash
   # Check manager.conf on Asterisk server
   cat /etc/asterisk/manager.conf
   ```

5. **Test Network Connectivity**
   ```bash
   telnet your-asterisk-host 5038
   ```

### Health Check Command

Use the built-in health check command for quick diagnostics:

```bash
# Basic health check
php artisan asterisk:health-check

# Verbose output with detailed information
php artisan asterisk:health-check --verbose

# Check specific components
php artisan asterisk:health-check --config --connection --events
```

## Connection Issues

### Issue: Unable to Connect to Asterisk

**Symptoms:**
- `AsteriskConnectionException: Failed to connect to AMI`
- Connection timeout errors
- "Connection refused" messages

**Common Causes and Solutions:**

1. **Incorrect Host/Port Configuration**
   ```env
   # Check these settings in .env
   ASTERISK_AMI_HOST=127.0.0.1  # Correct IP/hostname
   ASTERISK_AMI_PORT=5038       # Correct port
   ```

2. **Asterisk Service Not Running**
   ```bash
   # Check Asterisk status
   systemctl status asterisk
   
   # Start Asterisk if stopped
   systemctl start asterisk
   ```

3. **AMI Not Enabled in Asterisk**
   ```ini
   # In /etc/asterisk/manager.conf
   [general]
   enabled = yes
   port = 5038
   bindaddr = 0.0.0.0
   ```

4. **Firewall Blocking Connection**
   ```bash
   # Check firewall rules
   iptables -L | grep 5038
   
   # Allow AMI port (Ubuntu/Debian)
   ufw allow 5038
   
   # Allow AMI port (CentOS/RHEL)
   firewall-cmd --permanent --add-port=5038/tcp
   firewall-cmd --reload
   ```

5. **Network Connectivity Issues**
   ```bash
   # Test direct connection
   telnet asterisk-server 5038
   
   # Check routing
   traceroute asterisk-server
   
   # Test with netcat
   nc -zv asterisk-server 5038
   ```

### Issue: Connection Drops Frequently

**Symptoms:**
- Intermittent connection failures
- "Connection lost" messages
- Events stop being received

**Solutions:**

1. **Enable Keepalive**
   ```env
   ASTERISK_KEEPALIVE_ENABLED=true
   ASTERISK_KEEPALIVE_INTERVAL=30
   ```

2. **Adjust Timeouts**
   ```env
   ASTERISK_AMI_CONNECT_TIMEOUT=20
   ASTERISK_AMI_READ_TIMEOUT=30
   ```

3. **Enable Automatic Reconnection**
   ```env
   ASTERISK_RECONNECT_ATTEMPTS=5
   ASTERISK_RECONNECT_DELAY=10
   ```

4. **Check Network Stability**
   ```bash
   # Monitor connection stability
   ping -c 100 asterisk-server
   
   # Check for packet loss
   mtr asterisk-server
   ```

### Issue: SSL/TLS Connection Problems

**Symptoms:**
- SSL certificate verification errors
- Handshake failures
- "unable to verify certificate" errors

**Solutions:**

1. **Disable SSL Verification (Testing Only)**
   ```env
   ASTERISK_VERIFY_SSL=false
   ```

2. **Configure Proper SSL Certificates**
   ```env
   ASTERISK_SSL_CERT_PATH=/path/to/client.crt
   ASTERISK_SSL_KEY_PATH=/path/to/client.key
   ASTERISK_SSL_CA_PATH=/path/to/ca-bundle.crt
   ```

3. **Check Certificate Validity**
   ```bash
   # Verify certificate
   openssl x509 -in /path/to/client.crt -text -noout
   
   # Test SSL connection
   openssl s_client -connect asterisk-server:5039
   ```

## Authentication Problems

### Issue: Authentication Failed

**Symptoms:**
- "Authentication failed" errors
- "Invalid username/password" messages
- Connection succeeds but commands fail

**Solutions:**

1. **Verify Credentials**
   ```env
   # Check username and secret in .env
   ASTERISK_AMI_USERNAME=your_username
   ASTERISK_AMI_SECRET=your_password
   ```

2. **Check AMI User Configuration**
   ```ini
   # In /etc/asterisk/manager.conf
   [your_username]
   secret = your_password
   permit = 0.0.0.0/0.0.0.0
   read = all
   write = all
   ```

3. **Test Manual Authentication**
   ```bash
   # Telnet to AMI and test login
   telnet asterisk-server 5038
   # Then send:
   # Action: Login
   # Username: your_username
   # Secret: your_password
   ```

### Issue: Permission Denied for Actions

**Symptoms:**
- "Permission denied" for specific AMI actions
- Some commands work, others don't
- "Not authorized" messages

**Solutions:**

1. **Grant Proper Permissions**
   ```ini
   # In /etc/asterisk/manager.conf
   [your_username]
   secret = your_password
   permit = 0.0.0.0/0.0.0.0
   read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan
   write = system,call,agent,user,config,command,reporting,originate
   ```

2. **Check Required Permissions**
   ```php
   // Different operations require different permissions:
   // Originate calls: originate, call
   // Queue operations: agent, call
   // System status: system, call
   // Events: Multiple permissions depending on event type
   ```

## Event Processing Issues

### Issue: Events Not Being Received

**Symptoms:**
- No events in logs or database
- Event listeners not triggered
- Real-time updates not working

**Diagnostic Steps:**

1. **Check Event Configuration**
   ```env
   ASTERISK_EVENTS_ENABLED=true
   ASTERISK_LOG_TO_DATABASE=true
   ```

2. **Verify Event Listeners Registration**
   ```bash
   php artisan asterisk:monitor-events --duration=30
   ```

3. **Check Asterisk Event Generation**
   ```bash
   # In Asterisk CLI
   asterisk -rvvv
   manager show eventq
   manager show events
   ```

**Solutions:**

1. **Enable Event Processing**
   ```env
   ASTERISK_EVENTS_ENABLED=true
   ASTERISK_EVENTS_BROADCAST=true
   ```

2. **Check AMI User Permissions**
   ```ini
   [your_username]
   read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan
   ```

3. **Restart Event Monitoring**
   ```php
   // In your application
   AsteriskManager::stopEventMonitoring();
   AsteriskManager::startEventMonitoring();
   ```

### Issue: Event Processing Lag

**Symptoms:**
- Events processed with significant delay
- High memory usage during event processing
- Application becomes unresponsive

**Solutions:**

1. **Enable Queue Processing**
   ```env
   ASTERISK_EVENTS_QUEUE_PROCESSING=true
   ASTERISK_EVENT_BUFFER_SIZE=5000
   ASTERISK_EVENT_BATCH_SIZE=100
   ```

2. **Optimize Event Filtering**
   ```php
   // In config/asterisk-pbx-manager.php
   'events' => [
       'filters' => [
           'include' => ['Dial', 'Hangup', 'QueueMemberAdded'],
           'exclude' => ['RTCPSent', 'RTCPReceived', 'DTMF'],
       ],
   ],
   ```

3. **Use Redis for Event Processing**
   ```env
   QUEUE_CONNECTION=redis
   CACHE_DRIVER=redis
   ```

## Performance Problems

### Issue: Slow Response Times

**Symptoms:**
- AMI commands take long time to execute
- High latency for operations
- Timeouts on simple operations

**Diagnostic Tools:**

1. **Enable Performance Profiling**
   ```env
   ASTERISK_ENABLE_PROFILING=true
   ASTERISK_LOG_LEVEL=debug
   ```

2. **Monitor Memory Usage**
   ```bash
   # Monitor PHP memory usage
   watch -n 1 'ps aux | grep php | head -10'
   ```

3. **Check Database Performance**
   ```bash
   # MySQL query log
   tail -f /var/log/mysql/query.log
   ```

**Solutions:**

1. **Enable Connection Pooling**
   ```env
   ASTERISK_CONNECTION_POOL_SIZE=10
   ASTERISK_MAX_CONCURRENT_ACTIONS=20
   ```

2. **Enable Response Caching**
   ```env
   ASTERISK_CACHE_RESPONSES=true
   ASTERISK_CACHE_TTL=300
   ```

3. **Optimize Database Queries**
   ```bash
   php artisan optimize
   php artisan config:cache
   php artisan route:cache
   ```

### Issue: High Memory Usage

**Symptoms:**
- PHP processes consuming excessive memory
- "Out of memory" errors
- Server becomes unresponsive

**Solutions:**

1. **Reduce Buffer Sizes**
   ```env
   ASTERISK_EVENT_BUFFER_SIZE=1000
   ASTERISK_EVENT_BATCH_SIZE=50
   ASTERISK_CONNECTION_POOL_SIZE=5
   ```

2. **Implement Memory Limits**
   ```ini
   ; In php.ini
   memory_limit = 512M
   ```

3. **Monitor Memory Leaks**
   ```bash
   # Use memory profiler
   php -d memory_limit=128M your-script.php
   ```

## Queue Management Issues

### Issue: Queue Members Not Added

**Symptoms:**
- AddQueueMember action appears successful but member not in queue
- Queue status doesn't show added members
- Calls not distributed to added members

**Diagnostic Steps:**

1. **Check Queue Existence**
   ```bash
   # In Asterisk CLI
   queue show support
   ```

2. **Verify Member Format**
   ```php
   // Correct format
   AsteriskManager::addQueueMember('support', 'SIP/1001');
   
   // Incorrect format
   AsteriskManager::addQueueMember('support', '1001');
   ```

**Solutions:**

1. **Ensure Queue Exists in Asterisk**
   ```ini
   ; In /etc/asterisk/queues.conf
   [support]
   strategy = fewestcalls
   timeout = 30
   ```

2. **Check Member Channel Format**
   ```php
   // Valid channel formats
   'SIP/1001'      // SIP channel
   'IAX2/1001'     // IAX2 channel
   'DAHDI/1'       // DAHDI channel
   'Local/1001@internal' // Local channel
   ```

3. **Verify AMI Permissions**
   ```ini
   [your_username]
   write = agent,call,system
   ```

### Issue: Queue Events Not Triggered

**Symptoms:**
- QueueMemberAdded events not fired
- Queue status changes not detected
- Real-time queue updates not working

**Solutions:**

1. **Enable Queue Event Logging**
   ```ini
   ; In /etc/asterisk/logger.conf
   queue_log => queue_log
   ```

2. **Check Event Permissions**
   ```ini
   [your_username]
   read = system,call,agent,user
   ```

3. **Monitor Queue Events**
   ```bash
   php artisan asterisk:monitor-events | grep -i queue
   ```

## Database Problems

### Issue: Database Connection Errors

**Symptoms:**
- "SQLSTATE" errors
- Database connection timeout
- Migration failures

**Solutions:**

1. **Check Database Configuration**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

2. **Test Database Connection**
   ```bash
   php artisan migrate:status
   php artisan db:show
   ```

3. **Run Migrations**
   ```bash
   php artisan migrate
   ```

### Issue: Call Logs Not Stored

**Symptoms:**
- Database tables empty despite active calls
- Event data not being saved
- No call history available

**Solutions:**

1. **Enable Database Logging**
   ```env
   ASTERISK_LOG_TO_DATABASE=true
   ```

2. **Check Table Structure**
   ```bash
   php artisan migrate:status
   php artisan migrate --force
   ```

3. **Verify Event Processing**
   ```php
   // Check if events are being processed
   use AsteriskPbxManager\Models\AsteriskEvent;
   
   $recentEvents = AsteriskEvent::latest()->take(10)->get();
   dd($recentEvents);
   ```

## Configuration Issues

### Issue: Configuration Not Loading

**Symptoms:**
- Default values used instead of .env values
- Configuration changes not taking effect
- "Config key not found" errors

**Solutions:**

1. **Clear Configuration Cache**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Publish Configuration**
   ```bash
   php artisan vendor:publish --provider="AsteriskPbxManager\AsteriskPbxManagerServiceProvider" --tag="config"
   ```

3. **Check Configuration Loading**
   ```php
   // Test configuration loading
   dd(config('asterisk-pbx-manager'));
   ```

### Issue: Environment Variables Not Working

**Symptoms:**
- .env changes not applied
- Environment-specific settings not working
- Default values always used

**Solutions:**

1. **Check .env File Location**
   ```bash
   # Ensure .env is in Laravel root
   ls -la .env
   ```

2. **Verify Variable Names**
   ```env
   # Correct format
   ASTERISK_AMI_HOST=127.0.0.1
   
   # Incorrect format (no spaces)
   ASTERISK_AMI_HOST = 127.0.0.1
   ```

3. **Clear All Caches**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan optimize:clear
   ```

## Laravel Integration Problems

### Issue: Service Provider Not Loading

**Symptoms:**
- Package services not available
- Facade not working
- Artisan commands not registered

**Solutions:**

1. **Check Package Discovery**
   ```json
   {
       "extra": {
           "laravel": {
               "dont-discover": []
           }
       }
   }
   ```

2. **Manual Service Provider Registration**
   ```php
   // In config/app.php
   'providers' => [
       AsteriskPbxManager\AsteriskPbxManagerServiceProvider::class,
   ],
   ```

3. **Clear Discovery Cache**
   ```bash
   composer dump-autoload
   php artisan package:discover
   ```

### Issue: Facade Not Working

**Symptoms:**
- "Class 'AsteriskManager' not found"
- Facade methods not available
- IDE not recognizing facade

**Solutions:**

1. **Check Facade Registration**
   ```php
   // In config/app.php
   'aliases' => [
       'AsteriskManager' => AsteriskPbxManager\Facades\AsteriskManager::class,
   ],
   ```

2. **Clear Application Cache**
   ```bash
   php artisan optimize:clear
   ```

3. **Use Full Namespace**
   ```php
   use AsteriskPbxManager\Facades\AsteriskManager;
   
   AsteriskManager::isConnected();
   ```

## Development and Testing Issues

### Issue: Mock Mode Not Working

**Symptoms:**
- Real AMI connections attempted during testing
- Tests failing due to network issues
- Mock responses not used

**Solutions:**

1. **Enable Mock Mode Correctly**
   ```env
   # In .env.testing
   ASTERISK_MOCK_MODE=true
   ASTERISK_EVENTS_ENABLED=false
   ```

2. **Create Mock Response Files**
   ```bash
   mkdir -p tests/fixtures/asterisk
   echo '{"Response": "Success"}' > tests/fixtures/asterisk/login.json
   ```

3. **Configure Mock Path**
   ```env
   ASTERISK_MOCK_RESPONSES_PATH=tests/fixtures/asterisk
   ```

### Issue: Tests Failing Intermittently

**Symptoms:**
- Tests pass sometimes, fail others
- Network-related test failures
- Race conditions in event testing

**Solutions:**

1. **Use Database Transactions in Tests**
   ```php
   use Illuminate\Foundation\Testing\RefreshDatabase;
   
   class AsteriskTest extends TestCase
   {
       use RefreshDatabase;
   }
   ```

2. **Mock External Dependencies**
   ```php
   public function testOriginateCall()
   {
       AsteriskManager::fake();
       
       // Your test code here
       
       AsteriskManager::assertCallOriginated('SIP/1001', '2002');
   }
   ```

3. **Use Proper Test Environment**
   ```bash
   cp .env .env.testing
   # Modify .env.testing for testing
   php artisan test --env=testing
   ```

## Debugging Tools and Techniques

### Debug Mode

Enable comprehensive debugging:

```env
ASTERISK_DEBUG_MODE=true
ASTERISK_LOG_LEVEL=debug
ASTERISK_LOG_AMI_COMMANDS=true
ASTERISK_LOG_AMI_RESPONSES=true
APP_DEBUG=true
```

### Logging Configuration

Set up detailed logging:

```php
// In config/logging.php
'channels' => [
    'asterisk' => [
        'driver' => 'daily',
        'path' => storage_path('logs/asterisk.log'),
        'level' => 'debug',
        'days' => 7,
    ],
],
```

### Connection Testing

Test AMI connection manually:

```php
// Create a test script
use AsteriskPbxManager\Services\AsteriskManagerService;

$service = app(AsteriskManagerService::class);

try {
    $connected = $service->connect();
    echo $connected ? "Connected!" : "Connection failed!";
    
    if ($connected) {
        $status = $service->getStatus();
        print_r($status);
        $service->disconnect();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Event Monitoring

Monitor events in real-time:

```bash
# Monitor all events
php artisan asterisk:monitor-events

# Monitor specific duration
php artisan asterisk:monitor-events --duration=60

# Filter events by type
php artisan asterisk:monitor-events | grep -i "dial\|hangup"
```

## Log Analysis

### Finding Connection Issues

```bash
# Search for connection errors
grep -i "connection" storage/logs/laravel.log

# Look for authentication failures
grep -i "auth" storage/logs/laravel.log

# Find timeout issues
grep -i "timeout" storage/logs/laravel.log
```

### Analyzing Performance Issues

```bash
# Find slow operations
grep -i "slow\|performance" storage/logs/laravel.log

# Check memory usage
grep -i "memory" storage/logs/laravel.log

# Look for database issues
grep -i "database\|sql" storage/logs/laravel.log
```

### Event Processing Analysis

```bash
# Check event processing
grep -i "event" storage/logs/laravel.log

# Monitor queue processing
grep -i "queue" storage/logs/laravel.log

# Check for errors
grep -i "error\|exception\|failed" storage/logs/laravel.log
```

## Performance Debugging

### Enable Performance Monitoring

```env
ASTERISK_ENABLE_PROFILING=true
```

### Monitor Database Queries

```bash
# Enable query logging in MySQL
SET global general_log = 1;
SET global general_log_file='/tmp/mysql_queries.log';

# Monitor queries
tail -f /tmp/mysql_queries.log
```

### Check Memory Usage

```php
// Add to your code for debugging
echo "Memory usage: " . memory_get_usage(true) / 1024 / 1024 . " MB\n";
echo "Peak memory: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB\n";
```

### Profile AMI Operations

```php
$startTime = microtime(true);
$result = AsteriskManager::originateCall('SIP/1001', '2002');
$endTime = microtime(true);

echo "Operation took: " . ($endTime - $startTime) . " seconds\n";
```

## Common Error Messages

### "Failed to connect to AMI"

**Cause:** Network connectivity or Asterisk configuration issue  
**Solution:** Check network, Asterisk service, and AMI configuration

### "Authentication failed"

**Cause:** Incorrect username/password or AMI user not configured  
**Solution:** Verify credentials in .env and manager.conf

### "Permission denied"

**Cause:** AMI user lacks required permissions  
**Solution:** Grant appropriate read/write permissions in manager.conf

### "Class 'AsteriskManager' not found"

**Cause:** Facade not registered or autoloader issues  
**Solution:** Clear caches and check service provider registration

### "SQLSTATE[HY000] [2002]"

**Cause:** Database connection issues  
**Solution:** Check database configuration and connectivity

### "Call to undefined method"

**Cause:** Method doesn't exist or package not properly installed  
**Solution:** Check API documentation and package installation

## FAQ

### Q: How do I test if the package is working correctly?

A: Use the health check command:
```bash
php artisan asterisk:health-check --verbose
```

### Q: Why are my events not being received?

A: Check these common issues:
1. Events enabled: `ASTERISK_EVENTS_ENABLED=true`
2. AMI permissions include event reading
3. Asterisk is generating events
4. Network connectivity is stable

### Q: How can I improve performance?

A: Consider these optimizations:
1. Enable connection pooling
2. Use queue processing for events
3. Enable response caching
4. Optimize database queries
5. Filter unnecessary events

### Q: What's the difference between mock mode and normal operation?

A: Mock mode simulates AMI responses for testing without connecting to a real Asterisk server. Enable with `ASTERISK_MOCK_MODE=true`.

### Q: How do I enable SSL/TLS connections?

A: Set the scheme and certificate paths:
```env
ASTERISK_AMI_SCHEME=ssl://
ASTERISK_SSL_CERT_PATH=/path/to/cert.pem
ASTERISK_SSL_KEY_PATH=/path/to/key.pem
```

### Q: Can I use multiple Asterisk servers?

A: The package supports one AMI connection at a time. For multiple servers, create separate service instances with different configurations.

### Q: How do I handle connection drops gracefully?

A: Enable automatic reconnection:
```env
ASTERISK_RECONNECT_ATTEMPTS=5
ASTERISK_RECONNECT_DELAY=10
ASTERISK_KEEPALIVE_ENABLED=true
```

### Q: What Laravel versions are supported?

A: The package requires Laravel 12.0+ and PHP 8.4+.

### Q: How do I contribute bug fixes?

A: See the [Contributing Guide](CONTRIBUTING.md) for detailed instructions on submitting bug fixes and improvements.

## Getting Help

If you're still experiencing issues after following this guide:

1. Check the [API Documentation](api/README.md) for method details
2. Review [Configuration Guide](configuration.md) for setup options
3. Search existing [GitHub Issues](https://github.com/apn-ra/asterisk-pbx-manager/issues)
4. Create a new issue with:
   - Laravel version
   - PHP version
   - Package version
   - Configuration (sanitized)
   - Error messages
   - Steps to reproduce

## See Also

- [Configuration Guide](configuration.md)
- [API Documentation](api/README.md)
- [Usage Examples](examples/)
- [Performance Tuning](performance.md)
- [Contributing Guide](CONTRIBUTING.md)