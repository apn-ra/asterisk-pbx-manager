# AsteriskManagerService API Documentation

The `AsteriskManagerService` is the core service class that provides the main interface for interacting with Asterisk PBX systems through the Asterisk Manager Interface (AMI).

## Class Overview

**Namespace:** `AsteriskPbxManager\Services`  
**Implements:** None  
**Dependencies:** `PAMI\Client\Impl\ClientImpl`

## Constructor

### `__construct(ClientImpl $client, array $config = [])`

Creates a new AsteriskManagerService instance.

**Parameters:**
- `$client` (ClientImpl) - The PAMI client instance
- `$config` (array, optional) - Configuration options

**Example:**
```php
$client = new ClientImpl($configuration);
$service = new AsteriskManagerService($client);
```

## Connection Management Methods

### `connect(): bool`

Establishes a connection to the Asterisk Manager Interface.

**Returns:** `bool` - True on successful connection, false otherwise

**Throws:** `AsteriskConnectionException` - If connection fails

**Example:**
```php
try {
    if ($service->connect()) {
        echo "Connected successfully";
    }
} catch (AsteriskConnectionException $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

### `disconnect(): bool`

Closes the connection to the Asterisk Manager Interface.

**Returns:** `bool` - True on successful disconnection, false otherwise

**Example:**
```php
$service->disconnect();
```

### `isConnected(): bool`

Checks if the service is currently connected to Asterisk.

**Returns:** `bool` - True if connected, false otherwise

**Example:**
```php
if ($service->isConnected()) {
    // Perform AMI operations
}
```

### `reconnect(): bool`

Attempts to reconnect to Asterisk after a connection loss.

**Returns:** `bool` - True on successful reconnection, false otherwise

**Throws:** `AsteriskConnectionException` - If reconnection fails

**Example:**
```php
try {
    if ($service->reconnect()) {
        echo "Reconnected successfully";
    }
} catch (AsteriskConnectionException $e) {
    echo "Reconnection failed: " . $e->getMessage();
}
```

### `ping(): bool`

Sends a ping command to test AMI responsiveness.

**Returns:** `bool` - True if ping successful, false otherwise

**Example:**
```php
if ($service->ping()) {
    echo "AMI is responsive";
} else {
    echo "AMI is not responding";
}
```

## Call Management Methods

### `originateCall(string $channel, string $extension, array $options = []): bool`

Initiates a new call from a channel to an extension.

**Parameters:**
- `$channel` (string) - Source channel (e.g., 'SIP/1001')
- `$extension` (string) - Destination extension
- `$options` (array, optional) - Additional call options

**Available Options:**
- `Context` (string) - Dialplan context (default: 'default')
- `Priority` (string) - Dialplan priority (default: '1')
- `Timeout` (int) - Call timeout in milliseconds (default: 30000)
- `CallerID` (string) - Caller ID to use
- `Variables` (array) - Channel variables to set

**Returns:** `bool` - True if call initiated successfully, false otherwise

**Throws:** `ActionExecutionException` - If the originate action fails

**Example:**
```php
try {
    $result = $service->originateCall('SIP/1001', '2002', [
        'Context' => 'internal',
        'CallerID' => 'John Doe <1001>',
        'Timeout' => 45000,
        'Variables' => [
            'PRIORITY' => 'high',
            'DEPARTMENT' => 'sales'
        ]
    ]);
    
    if ($result) {
        echo "Call initiated successfully";
    }
} catch (ActionExecutionException $e) {
    echo "Failed to originate call: " . $e->getMessage();
}
```

### `hangupCall(string $channel, string $cause = 'Normal Clearing'): bool`

Terminates an active call on the specified channel.

**Parameters:**
- `$channel` (string) - Channel to hang up
- `$cause` (string, optional) - Hang up cause (default: 'Normal Clearing')

**Returns:** `bool` - True if hangup successful, false otherwise

**Throws:** `ActionExecutionException` - If the hangup action fails

**Example:**
```php
try {
    $result = $service->hangupCall('SIP/1001-00000001', 'User Request');
    if ($result) {
        echo "Call hung up successfully";
    }
} catch (ActionExecutionException $e) {
    echo "Failed to hang up call: " . $e->getMessage();
}
```

### `transferCall(string $channel, string $extension, string $context = 'default'): bool`

Transfers an active call to another extension.

**Parameters:**
- `$channel` (string) - Channel to transfer
- `$extension` (string) - Destination extension
- `$context` (string, optional) - Destination context (default: 'default')

**Returns:** `bool` - True if transfer initiated successfully, false otherwise

**Throws:** `ActionExecutionException` - If the transfer action fails

**Example:**
```php
try {
    $result = $service->transferCall('SIP/1001-00000001', '2002', 'internal');
    if ($result) {
        echo "Call transferred successfully";
    }
} catch (ActionExecutionException $e) {
    echo "Failed to transfer call: " . $e->getMessage();
}
```

## System Information Methods

### `getStatus(): ?array`

Retrieves current system status information from Asterisk.

**Returns:** `array|null` - System status data or null if failed

**Example:**
```php
$status = $service->getStatus();
if ($status) {
    echo "System uptime: " . $status['SystemUptime'];
    echo "Active calls: " . $status['ActiveCalls'];
}
```

### `getChannels(): array`

Gets a list of all active channels.

**Returns:** `array` - Array of active channel information

**Example:**
```php
$channels = $service->getChannels();
foreach ($channels as $channel) {
    echo "Channel: " . $channel['channel'];
    echo "State: " . $channel['state'];
    echo "CallerID: " . $channel['callerid'];
}
```

### `getCoreShowChannels(): array`

Gets detailed channel information using CoreShowChannels command.

**Returns:** `array` - Detailed channel information

**Example:**
```php
$channels = $service->getCoreShowChannels();
foreach ($channels as $channel) {
    echo "Channel: " . $channel['Channel'];
    echo "Context: " . $channel['Context'];
    echo "Extension: " . $channel['Extension'];
    echo "Duration: " . $channel['Duration'];
}
```

### `getPeers(string $type = 'sip'): array`

Gets a list of registered peers (SIP, IAX2, etc.).

**Parameters:**
- `$type` (string, optional) - Peer type ('sip', 'iax2') (default: 'sip')

**Returns:** `array` - Array of peer information

**Example:**
```php
$sipPeers = $service->getPeers('sip');
foreach ($sipPeers as $peer) {
    echo "Peer: " . $peer['ObjectName'];
    echo "Status: " . $peer['Status'];
    echo "Address: " . $peer['Address'];
}
```

## Event Management Methods

### `registerEventListener(callable $callback, callable $predicate = null): void`

Registers an event listener for Asterisk events.

**Parameters:**
- `$callback` (callable) - Function to call when event occurs
- `$predicate` (callable, optional) - Function to filter events

**Example:**
```php
$service->registerEventListener(
    function ($event) {
        echo "Event: " . $event->getName();
    },
    function ($event) {
        return $event->getName() === 'Dial';
    }
);
```

### `startEventMonitoring(): void`

Starts monitoring Asterisk events in the background.

**Example:**
```php
$service->startEventMonitoring();
```

### `stopEventMonitoring(): void`

Stops the event monitoring process.

**Example:**
```php
$service->stopEventMonitoring();
```

## Generic AMI Methods

### `send(string $action, array $parameters = []): mixed`

Sends a generic AMI action with specified parameters.

**Parameters:**
- `$action` (string) - AMI action name
- `$parameters` (array, optional) - Action parameters

**Returns:** `mixed` - Response from Asterisk or false on failure

**Throws:** `ActionExecutionException` - If the action fails

**Example:**
```php
try {
    // Send a custom AMI command
    $response = $service->send('CoreStatus', []);
    
    // Send with parameters
    $response = $service->send('Originate', [
        'Channel' => 'SIP/1001',
        'Extension' => '2002',
        'Context' => 'default',
        'Priority' => '1'
    ]);
} catch (ActionExecutionException $e) {
    echo "Action failed: " . $e->getMessage();
}
```

### `sendAction(ActionMessage $action): ResponseMessage`

Sends a prepared AMI action message.

**Parameters:**
- `$action` (ActionMessage) - Pre-built PAMI action message

**Returns:** `ResponseMessage` - Response from Asterisk

**Throws:** `ActionExecutionException` - If the action fails

**Example:**
```php
use PAMI\Message\Action\PingAction;

try {
    $action = new PingAction();
    $response = $service->sendAction($action);
    
    if ($response->isSuccess()) {
        echo "Ping successful";
    }
} catch (ActionExecutionException $e) {
    echo "Action failed: " . $e->getMessage();
}
```

## Channel Control Methods

### `playSound(string $channel, string $filename): bool`

Plays a sound file on the specified channel.

**Parameters:**
- `$channel` (string) - Target channel
- `$filename` (string) - Sound file to play

**Returns:** `bool` - True if playback started successfully

**Example:**
```php
$result = $service->playSound('SIP/1001-00000001', 'welcome');
```

### `recordChannel(string $channel, string $filename, array $options = []): bool`

Starts recording on the specified channel.

**Parameters:**
- `$channel` (string) - Channel to record
- `$filename` (string) - Output filename
- `$options` (array, optional) - Recording options

**Returns:** `bool` - True if recording started successfully

**Example:**
```php
$result = $service->recordChannel('SIP/1001-00000001', 'recording001', [
    'format' => 'wav',
    'beep' => true,
    'maxDuration' => 300
]);
```

### `stopRecording(string $channel): bool`

Stops recording on the specified channel.

**Parameters:**
- `$channel` (string) - Channel to stop recording

**Returns:** `bool` - True if recording stopped successfully

**Example:**
```php
$result = $service->stopRecording('SIP/1001-00000001');
```

## Configuration Methods

### `getConfiguration(): array`

Gets the current service configuration.

**Returns:** `array` - Current configuration settings

**Example:**
```php
$config = $service->getConfiguration();
echo "AMI Host: " . $config['host'];
echo "AMI Port: " . $config['port'];
```

### `updateConfiguration(array $config): void`

Updates the service configuration.

**Parameters:**
- `$config` (array) - New configuration settings

**Example:**
```php
$service->updateConfiguration([
    'connect_timeout' => 20,
    'read_timeout' => 15
]);
```

## Error Handling

The service throws specific exceptions for different error conditions:

- `AsteriskConnectionException` - Connection-related errors
- `ActionExecutionException` - AMI action execution errors

Always wrap service calls in try-catch blocks for proper error handling:

```php
try {
    $result = $service->originateCall('SIP/1001', '2002');
} catch (AsteriskConnectionException $e) {
    // Handle connection errors
    Log::error('AMI Connection failed: ' . $e->getMessage());
} catch (ActionExecutionException $e) {
    // Handle action execution errors
    Log::error('Action failed: ' . $e->getMessage());
} catch (\Exception $e) {
    // Handle other unexpected errors
    Log::error('Unexpected error: ' . $e->getMessage());
}
```

## Thread Safety

The AsteriskManagerService is **not thread-safe**. If using in a multi-threaded environment, ensure proper synchronization or use separate service instances per thread.

## Performance Considerations

- Connection pooling: Reuse service instances when possible
- Event filtering: Use predicates to filter events and reduce processing overhead
- Timeout configuration: Set appropriate timeouts for your network environment
- Error handling: Implement retry logic for transient failures

## See Also

- [QueueManagerService](QueueManagerService.md) - Queue management operations
- [EventProcessor](EventProcessor.md) - Event processing functionality
- [Configuration Guide](../configuration.md) - Detailed configuration options