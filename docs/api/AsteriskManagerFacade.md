# AsteriskManager Facade API Documentation

The `AsteriskManager` facade provides a convenient static interface for accessing Asterisk PBX functionality without needing to inject the service classes directly.

## Class Overview

**Namespace:** `AsteriskPbxManager\Facades`  
**Facade Accessor:** `asterisk-manager`  
**Underlying Service:** `AsteriskManagerService`

## Usage

The facade can be used anywhere in your Laravel application:

```php
use AsteriskPbxManager\Facades\AsteriskManager;

// Use static methods
$result = AsteriskManager::originateCall('SIP/1001', '2002');
```

## Connection Management Methods

### `AsteriskManager::connect(): bool`

Establishes a connection to the Asterisk Manager Interface.

**Returns:** `bool` - True on successful connection, false otherwise

**Throws:** `AsteriskConnectionException` - If connection fails

**Example:**
```php
use AsteriskPbxManager\Facades\AsteriskManager;

try {
    if (AsteriskManager::connect()) {
        echo "Connected to Asterisk successfully";
    }
} catch (AsteriskConnectionException $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

### `AsteriskManager::disconnect(): bool`

Closes the connection to the Asterisk Manager Interface.

**Returns:** `bool` - True on successful disconnection, false otherwise

**Example:**
```php
AsteriskManager::disconnect();
```

### `AsteriskManager::isConnected(): bool`

Checks if the service is currently connected to Asterisk.

**Returns:** `bool` - True if connected, false otherwise

**Example:**
```php
if (AsteriskManager::isConnected()) {
    // Perform AMI operations
    $status = AsteriskManager::getStatus();
} else {
    // Handle disconnected state
    echo "Not connected to Asterisk";
}
```

### `AsteriskManager::reconnect(): bool`

Attempts to reconnect to Asterisk after a connection loss.

**Returns:** `bool` - True on successful reconnection, false otherwise

**Throws:** `AsteriskConnectionException` - If reconnection fails

**Example:**
```php
try {
    if (AsteriskManager::reconnect()) {
        echo "Reconnected successfully";
    }
} catch (AsteriskConnectionException $e) {
    echo "Reconnection failed: " . $e->getMessage();
}
```

### `AsteriskManager::ping(): bool`

Sends a ping command to test AMI responsiveness.

**Returns:** `bool` - True if ping successful, false otherwise

**Example:**
```php
if (AsteriskManager::ping()) {
    echo "Asterisk is responsive";
} else {
    echo "Asterisk is not responding";
}
```

## Call Management Methods

### `AsteriskManager::originateCall(string $channel, string $extension, array $options = []): bool`

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
use AsteriskPbxManager\Facades\AsteriskManager;
use AsteriskPbxManager\Exceptions\ActionExecutionException;

try {
    $result = AsteriskManager::originateCall('SIP/1001', '2002', [
        'Context' => 'internal',
        'CallerID' => 'Sales Department <1001>',
        'Timeout' => 45000,
        'Variables' => [
            'CAMPAIGN' => 'spring2024',
            'PRIORITY' => 'high'
        ]
    ]);
    
    if ($result) {
        echo "Call initiated successfully";
    } else {
        echo "Failed to initiate call";
    }
} catch (ActionExecutionException $e) {
    echo "Call origination failed: " . $e->getMessage();
}
```

### `AsteriskManager::hangupCall(string $channel, string $cause = 'Normal Clearing'): bool`

Terminates an active call on the specified channel.

**Parameters:**
- `$channel` (string) - Channel to hang up
- `$cause` (string, optional) - Hang up cause (default: 'Normal Clearing')

**Returns:** `bool` - True if hangup successful, false otherwise

**Throws:** `ActionExecutionException` - If the hangup action fails

**Example:**
```php
try {
    $result = AsteriskManager::hangupCall('SIP/1001-00000001', 'User Request');
    if ($result) {
        echo "Call terminated successfully";
    }
} catch (ActionExecutionException $e) {
    echo "Failed to hang up call: " . $e->getMessage();
}
```

### `AsteriskManager::transferCall(string $channel, string $extension, string $context = 'default'): bool`

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
    $result = AsteriskManager::transferCall('SIP/1001-00000001', '3001', 'support');
    if ($result) {
        echo "Call transferred to support";
    }
} catch (ActionExecutionException $e) {
    echo "Transfer failed: " . $e->getMessage();
}
```

## System Information Methods

### `AsteriskManager::getStatus(): ?array`

Retrieves current system status information from Asterisk.

**Returns:** `array|null` - System status data or null if failed

**Example:**
```php
$status = AsteriskManager::getStatus();
if ($status) {
    echo "System Name: " . $status['SystemName'];
    echo "Version: " . $status['Version'];
    echo "Uptime: " . $status['SystemUptime'];
    echo "Active Calls: " . $status['ActiveCalls'];
    echo "Active Channels: " . $status['ActiveChannels'];
} else {
    echo "Failed to retrieve system status";
}
```

### `AsteriskManager::getChannels(): array`

Gets a list of all active channels.

**Returns:** `array` - Array of active channel information

**Example:**
```php
$channels = AsteriskManager::getChannels();
foreach ($channels as $channel) {
    echo "Channel: {$channel['channel']}";
    echo "State: {$channel['state']}";
    echo "Caller ID: {$channel['callerid']}";
    echo "Connected Line: {$channel['connectedline']}";
    echo "Duration: {$channel['duration']} seconds";
}
```

### `AsteriskManager::getCoreShowChannels(): array`

Gets detailed channel information using CoreShowChannels command.

**Returns:** `array` - Detailed channel information

**Example:**
```php
$channels = AsteriskManager::getCoreShowChannels();
foreach ($channels as $channel) {
    echo "Channel: {$channel['Channel']}";
    echo "Context: {$channel['Context']}";
    echo "Extension: {$channel['Extension']}";
    echo "Priority: {$channel['Priority']}";
    echo "State: {$channel['State']}";
    echo "Application: {$channel['Application']}";
}
```

### `AsteriskManager::getPeers(string $type = 'sip'): array`

Gets a list of registered peers (SIP, IAX2, etc.).

**Parameters:**
- `$type` (string, optional) - Peer type ('sip', 'iax2') (default: 'sip')

**Returns:** `array` - Array of peer information

**Example:**
```php
// Get SIP peers
$sipPeers = AsteriskManager::getPeers('sip');
foreach ($sipPeers as $peer) {
    echo "Peer: {$peer['ObjectName']}";
    echo "Status: {$peer['Status']}";
    echo "Address: {$peer['Address']}";
    echo "Dynamic: {$peer['Dynamic']}";
}

// Get IAX2 peers
$iaxPeers = AsteriskManager::getPeers('iax2');
```

## Event Management Methods

### `AsteriskManager::registerEventListener(callable $callback, callable $predicate = null): void`

Registers an event listener for Asterisk events.

**Parameters:**
- `$callback` (callable) - Function to call when event occurs
- `$predicate` (callable, optional) - Function to filter events

**Example:**
```php
// Listen for all dial events
AsteriskManager::registerEventListener(
    function ($event) {
        Log::info('Dial event occurred', [
            'channel' => $event->getKey('Channel'),
            'destination' => $event->getKey('Destination'),
            'caller_id' => $event->getKey('CallerIDNum')
        ]);
    },
    function ($event) {
        return $event->getName() === 'Dial';
    }
);

// Listen for hangup events with specific conditions
AsteriskManager::registerEventListener(
    function ($event) {
        Log::warning('Call hangup', [
            'channel' => $event->getKey('Channel'),
            'cause' => $event->getKey('Cause'),
            'cause_txt' => $event->getKey('Cause-txt')
        ]);
    },
    function ($event) {
        return $event->getName() === 'Hangup' && 
               $event->getKey('Cause') !== '16'; // Not normal clearing
    }
);
```

### `AsteriskManager::startEventMonitoring(): void`

Starts monitoring Asterisk events in the background.

**Example:**
```php
// Start event monitoring
AsteriskManager::startEventMonitoring();

// Events will now be processed automatically
// and trigger registered listeners
```

### `AsteriskManager::stopEventMonitoring(): void`

Stops the event monitoring process.

**Example:**
```php
AsteriskManager::stopEventMonitoring();
```

## Generic AMI Methods

### `AsteriskManager::send(string $action, array $parameters = []): mixed`

Sends a generic AMI action with specified parameters.

**Parameters:**
- `$action` (string) - AMI action name
- `$parameters` (array, optional) - Action parameters

**Returns:** `mixed` - Response from Asterisk or false on failure

**Throws:** `ActionExecutionException` - If the action fails

**Example:**
```php
try {
    // Get system information
    $response = AsteriskManager::send('CoreStatus');
    
    // Send SIP show peers command
    $peers = AsteriskManager::send('SIPshowpeer', [
        'Peer' => '1001'
    ]);
    
    // Send custom command
    $result = AsteriskManager::send('Command', [
        'Command' => 'core show version'
    ]);
    
} catch (ActionExecutionException $e) {
    echo "AMI action failed: " . $e->getMessage();
}
```

## Queue Integration Methods

These methods provide direct access to queue operations through the facade:

### `AsteriskManager::addQueueMember(string $queue, string $member, array $options = []): bool`

Adds a member to a queue (delegates to QueueManagerService).

**Parameters:**
- `$queue` (string) - Queue name
- `$member` (string) - Member channel
- `$options` (array, optional) - Member options

**Returns:** `bool` - True if member added successfully

**Example:**
```php
$result = AsteriskManager::addQueueMember('support', 'SIP/1001', [
    'penalty' => 1,
    'membername' => 'Support Agent 1'
]);
```

### `AsteriskManager::removeQueueMember(string $queue, string $member): bool`

Removes a member from a queue.

**Parameters:**
- `$queue` (string) - Queue name
- `$member` (string) - Member channel

**Returns:** `bool` - True if member removed successfully

**Example:**
```php
$result = AsteriskManager::removeQueueMember('support', 'SIP/1001');
```

### `AsteriskManager::pauseQueueMember(string $queue, string $member, bool $paused = true, string $reason = ''): bool`

Pauses or unpauses a queue member.

**Parameters:**
- `$queue` (string) - Queue name
- `$member` (string) - Member channel
- `$paused` (bool, optional) - Pause state
- `$reason` (string, optional) - Reason for action

**Returns:** `bool` - True if operation successful

**Example:**
```php
// Pause member
$result = AsteriskManager::pauseQueueMember('support', 'SIP/1001', true, 'Break time');

// Unpause member
$result = AsteriskManager::pauseQueueMember('support', 'SIP/1001', false);
```

### `AsteriskManager::getQueueStatus(string $queue = ''): ?array`

Gets queue status information.

**Parameters:**
- `$queue` (string, optional) - Queue name, empty for all queues

**Returns:** `array|null` - Queue status data

**Example:**
```php
// Get all queues
$allQueues = AsteriskManager::getQueueStatus();

// Get specific queue
$supportQueue = AsteriskManager::getQueueStatus('support');
```

## Channel Control Methods

### `AsteriskManager::playSound(string $channel, string $filename): bool`

Plays a sound file on the specified channel.

**Parameters:**
- `$channel` (string) - Target channel
- `$filename` (string) - Sound file to play

**Returns:** `bool` - True if playback started successfully

**Example:**
```php
$result = AsteriskManager::playSound('SIP/1001-00000001', 'welcome-message');
```

### `AsteriskManager::recordChannel(string $channel, string $filename, array $options = []): bool`

Starts recording on the specified channel.

**Parameters:**
- `$channel` (string) - Channel to record
- `$filename` (string) - Output filename
- `$options` (array, optional) - Recording options

**Returns:** `bool` - True if recording started successfully

**Example:**
```php
$result = AsteriskManager::recordChannel('SIP/1001-00000001', '/var/recordings/call001', [
    'format' => 'wav',
    'beep' => true,
    'maxDuration' => 1800 // 30 minutes
]);
```

## Practical Usage Examples

### Basic Call Flow Management

```php
use AsteriskPbxManager\Facades\AsteriskManager;
use Illuminate\Support\Facades\Log;

class CallFlowController extends Controller
{
    public function initiateCustomerCall(Request $request)
    {
        // Validate connection
        if (!AsteriskManager::isConnected()) {
            if (!AsteriskManager::connect()) {
                return response()->json(['error' => 'Unable to connect to PBX'], 503);
            }
        }
        
        try {
            // Originate call to agent first
            $agentChannel = 'SIP/' . $request->input('agent_extension');
            $customerNumber = $request->input('customer_number');
            
            $result = AsteriskManager::originateCall(
                $agentChannel,
                $customerNumber,
                [
                    'Context' => 'outbound',
                    'CallerID' => 'Company Name <' . config('pbx.company_number') . '>',
                    'Variables' => [
                        'CUSTOMER_ID' => $request->input('customer_id'),
                        'CAMPAIGN' => $request->input('campaign', 'general'),
                        'AGENT_ID' => $request->input('agent_id')
                    ]
                ]
            );
            
            if ($result) {
                Log::info('Call initiated successfully', [
                    'agent' => $agentChannel,
                    'customer' => $customerNumber,
                    'customer_id' => $request->input('customer_id')
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Call initiated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to initiate call'
                ], 422);
            }
            
        } catch (ActionExecutionException $e) {
            Log::error('Call initiation failed', [
                'error' => $e->getMessage(),
                'agent' => $agentChannel ?? 'unknown',
                'customer' => $customerNumber ?? 'unknown'
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Call initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

### Queue Dashboard Implementation

```php
use AsteriskPbxManager\Facades\AsteriskManager;

class QueueDashboardController extends Controller
{
    public function getDashboardData()
    {
        try {
            $queueStatus = AsteriskManager::getQueueStatus();
            $systemStatus = AsteriskManager::getStatus();
            $activeChannels = AsteriskManager::getChannels();
            
            return response()->json([
                'system' => [
                    'uptime' => $systemStatus['SystemUptime'] ?? 'Unknown',
                    'active_calls' => $systemStatus['ActiveCalls'] ?? 0,
                    'active_channels' => count($activeChannels),
                ],
                'queues' => $this->formatQueueData($queueStatus),
                'channels' => $this->formatChannelData($activeChannels),
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    private function formatQueueData($queueStatus): array
    {
        $formatted = [];
        
        if (isset($queueStatus['queues'])) {
            foreach ($queueStatus['queues'] as $queueName => $queueData) {
                $formatted[] = [
                    'name' => $queueName,
                    'strategy' => $queueData['strategy'] ?? 'unknown',
                    'calls_waiting' => $queueData['calls'] ?? 0,
                    'hold_time' => $queueData['holdtime'] ?? 0,
                    'members_total' => count($queueData['members'] ?? []),
                    'members_available' => $this->countAvailableMembers($queueData['members'] ?? []),
                    'service_level' => $queueData['servicelevel'] ?? 0
                ];
            }
        }
        
        return $formatted;
    }
    
    private function countAvailableMembers(array $members): int
    {
        return count(array_filter($members, fn($member) => !($member['paused'] ?? true)));
    }
}
```

## Error Handling Best Practices

Always implement proper error handling when using the facade:

```php
use AsteriskPbxManager\Facades\AsteriskManager;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;

try {
    // Ensure connection
    if (!AsteriskManager::isConnected()) {
        AsteriskManager::connect();
    }
    
    // Perform operation
    $result = AsteriskManager::originateCall('SIP/1001', '2002');
    
} catch (AsteriskConnectionException $e) {
    // Handle connection issues
    Log::error('AMI Connection failed', [
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    // Try to reconnect
    try {
        AsteriskManager::reconnect();
    } catch (AsteriskConnectionException $reconnectError) {
        // Connection completely failed
        throw new ServiceUnavailableException('PBX system unavailable');
    }
    
} catch (ActionExecutionException $e) {
    // Handle action failures
    Log::warning('AMI Action failed', [
        'action' => 'Originate',
        'error' => $e->getMessage()
    ]);
    
    // Return appropriate error response
    return response()->json([
        'success' => false,
        'error' => 'Operation failed'
    ], 422);
    
} catch (\Exception $e) {
    // Handle unexpected errors
    Log::error('Unexpected AMI error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    throw $e;
}
```

## Performance Tips

- **Connection Reuse**: The facade automatically reuses the underlying service connection
- **Batch Operations**: For multiple operations, consider using the service classes directly
- **Event Filtering**: Use specific event predicates to reduce processing overhead
- **Caching**: Cache system status and queue information when appropriate

## See Also

- [AsteriskManagerService](AsteriskManagerService.md) - Underlying service documentation
- [QueueManagerService](QueueManagerService.md) - Queue management functionality
- [Event System](../events/README.md) - Event handling documentation
- [Configuration](../configuration.md) - Configuration options