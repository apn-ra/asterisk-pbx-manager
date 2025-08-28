# QueueManagerService API Documentation

The `QueueManagerService` provides comprehensive queue management functionality for Asterisk PBX systems, including member management, status monitoring, and queue operations.

## Class Overview

**Namespace:** `AsteriskPbxManager\Services`  
**Implements:** None  
**Dependencies:** `AsteriskManagerService`, `PAMI\Client\Impl\ClientImpl`

## Constructor

### `__construct(AsteriskManagerService $asteriskManager)`

Creates a new QueueManagerService instance.

**Parameters:**
- `$asteriskManager` (AsteriskManagerService) - The main Asterisk manager service

**Example:**
```php
$queueManager = new QueueManagerService($asteriskManager);
```

## Queue Member Management Methods

### `addMember(string $queue, string $member, array $options = []): bool`

Adds a member (agent) to a specified queue.

**Parameters:**
- `$queue` (string) - Queue name
- `$member` (string) - Member channel (e.g., 'SIP/1001')
- `$options` (array, optional) - Additional options

**Available Options:**
- `penalty` (int) - Member penalty (0-999, lower = higher priority) (default: 0)
- `paused` (bool) - Whether to add member in paused state (default: false)
- `membername` (string) - Display name for the member (default: '')
- `interface` (string) - Member interface (default: same as member)

**Returns:** `bool` - True if member added successfully, false otherwise

**Throws:** `ActionExecutionException` - If the action fails

**Example:**
```php
try {
    $result = $queueManager->addMember('support', 'SIP/1001', [
        'penalty' => 1,
        'paused' => false,
        'membername' => 'John Doe'
    ]);
    
    if ($result) {
        echo "Member added successfully";
    }
} catch (ActionExecutionException $e) {
    echo "Failed to add member: " . $e->getMessage();
}
```

### `removeMember(string $queue, string $member): bool`

Removes a member from a specified queue.

**Parameters:**
- `$queue` (string) - Queue name
- `$member` (string) - Member channel to remove

**Returns:** `bool` - True if member removed successfully, false otherwise

**Throws:** `ActionExecutionException` - If the action fails

**Example:**
```php
try {
    $result = $queueManager->removeMember('support', 'SIP/1001');
    if ($result) {
        echo "Member removed successfully";
    }
} catch (ActionExecutionException $e) {
    echo "Failed to remove member: " . $e->getMessage();
}
```

### `pauseMember(string $queue, string $member, bool $paused = true, string $reason = ''): bool`

Pauses or unpauses a queue member.

**Parameters:**
- `$queue` (string) - Queue name
- `$member` (string) - Member channel
- `$paused` (bool, optional) - True to pause, false to unpause (default: true)
- `$reason` (string, optional) - Reason for pause/unpause (default: '')

**Returns:** `bool` - True if operation successful, false otherwise

**Throws:** `ActionExecutionException` - If the action fails

**Example:**
```php
try {
    // Pause member with reason
    $result = $queueManager->pauseMember('support', 'SIP/1001', true, 'Lunch break');
    
    // Unpause member
    $result = $queueManager->pauseMember('support', 'SIP/1001', false);
    
    if ($result) {
        echo "Member status updated successfully";
    }
} catch (ActionExecutionException $e) {
    echo "Failed to update member status: " . $e->getMessage();
}
```

### `setPenalty(string $queue, string $member, int $penalty): bool`

Sets the penalty for a queue member.

**Parameters:**
- `$queue` (string) - Queue name
- `$member` (string) - Member channel
- `$penalty` (int) - New penalty value (0-999)

**Returns:** `bool` - True if penalty set successfully, false otherwise

**Throws:** `ActionExecutionException` - If the action fails

**Example:**
```php
try {
    $result = $queueManager->setPenalty('support', 'SIP/1001', 5);
    if ($result) {
        echo "Penalty updated successfully";
    }
} catch (ActionExecutionException $e) {
    echo "Failed to set penalty: " . $e->getMessage();
}
```

## Queue Status Methods

### `getQueueStatus(string $queue = ''): ?array`

Retrieves status information for one or all queues.

**Parameters:**
- `$queue` (string, optional) - Specific queue name, empty for all queues (default: '')

**Returns:** `array|null` - Queue status data or null if failed

**Example:**
```php
// Get status for all queues
$allQueues = $queueManager->getQueueStatus();

// Get status for specific queue
$supportQueue = $queueManager->getQueueStatus('support');

if ($supportQueue) {
    echo "Queue: " . $supportQueue['name'];
    echo "Strategy: " . $supportQueue['strategy'];
    echo "Calls waiting: " . $supportQueue['calls'];
    echo "Hold time: " . $supportQueue['holdtime'];
    echo "Talk time: " . $supportQueue['talktime'];
}
```

### `getQueueSummary(string $queue): ?array`

Gets a summarized view of queue statistics.

**Parameters:**
- `$queue` (string) - Queue name

**Returns:** `array|null` - Queue summary data or null if failed

**Example:**
```php
$summary = $queueManager->getQueueSummary('support');
if ($summary) {
    echo "Available members: " . $summary['available'];
    echo "Busy members: " . $summary['busy'];
    echo "Unavailable members: " . $summary['unavailable'];
    echo "Calls waiting: " . $summary['calls'];
    echo "Longest hold time: " . $summary['holdtime'];
}
```

### `getQueueMembers(string $queue): array`

Gets detailed information about all members in a queue.

**Parameters:**
- `$queue` (string) - Queue name

**Returns:** `array` - Array of member information

**Example:**
```php
$members = $queueManager->getQueueMembers('support');
foreach ($members as $member) {
    echo "Member: " . $member['location'];
    echo "Name: " . $member['membername'];
    echo "Penalty: " . $member['penalty'];
    echo "Calls taken: " . $member['callstaken'];
    echo "Status: " . ($member['paused'] ? 'Paused' : 'Available');
    echo "Last call: " . $member['lastcall'];
}
```

### `isQueueMember(string $queue, string $member): bool`

Checks if a member belongs to a specific queue.

**Parameters:**
- `$queue` (string) - Queue name
- `$member` (string) - Member channel

**Returns:** `bool` - True if member is in queue, false otherwise

**Example:**
```php
if ($queueManager->isQueueMember('support', 'SIP/1001')) {
    echo "SIP/1001 is a member of support queue";
} else {
    echo "SIP/1001 is not a member of support queue";
}
```

## Queue Configuration Methods

### `setQueueStrategy(string $queue, string $strategy): bool`

Sets the strategy for call distribution in a queue.

**Parameters:**
- `$queue` (string) - Queue name
- `$strategy` (string) - Strategy name ('ringall', 'leastrecent', 'fewestcalls', etc.)

**Returns:** `bool` - True if strategy set successfully, false otherwise

**Available Strategies:**
- `ringall` - Ring all available members
- `leastrecent` - Ring member who has been idle longest
- `fewestcalls` - Ring member with fewest completed calls
- `random` - Ring random member
- `rrmemory` - Round-robin with memory
- `linear` - Ring members in order specified
- `wrandom` - Weighted random based on penalty

**Example:**
```php
$result = $queueManager->setQueueStrategy('support', 'fewestcalls');
if ($result) {
    echo "Queue strategy updated";
}
```

### `setQueueTimeout(string $queue, int $timeout): bool`

Sets the timeout for queue operations.

**Parameters:**
- `$queue` (string) - Queue name
- `$timeout` (int) - Timeout in seconds

**Returns:** `bool` - True if timeout set successfully, false otherwise

**Example:**
```php
$result = $queueManager->setQueueTimeout('support', 60);
if ($result) {
    echo "Queue timeout updated to 60 seconds";
}
```

## Bulk Operations Methods

### `addMembersToQueues(array $queues, array $members, array $options = []): array`

Adds multiple members to multiple queues in bulk.

**Parameters:**
- `$queues` (array) - Array of queue names
- `$members` (array) - Array of member channels
- `$options` (array, optional) - Default options for all members

**Returns:** `array` - Results array with success/failure for each operation

**Example:**
```php
$results = $queueManager->addMembersToQueues(
    ['support', 'sales'],
    ['SIP/1001', 'SIP/1002'],
    ['penalty' => 1, 'membername' => 'Agent']
);

foreach ($results as $result) {
    echo "Queue: {$result['queue']}, Member: {$result['member']}, Success: " . 
         ($result['success'] ? 'Yes' : 'No');
}
```

### `removeMembersFromQueues(array $queues, array $members): array`

Removes multiple members from multiple queues in bulk.

**Parameters:**
- `$queues` (array) - Array of queue names
- `$members` (array) - Array of member channels

**Returns:** `array` - Results array with success/failure for each operation

**Example:**
```php
$results = $queueManager->removeMembersFromQueues(
    ['support', 'sales'],
    ['SIP/1001', 'SIP/1002']
);
```

### `pauseAllMembers(string $queue, bool $paused = true, string $reason = ''): array`

Pauses or unpauses all members in a queue.

**Parameters:**
- `$queue` (string) - Queue name
- `$paused` (bool, optional) - True to pause, false to unpause (default: true)
- `$reason` (string, optional) - Reason for pause/unpause

**Returns:** `array` - Results for each member

**Example:**
```php
// Pause all members for maintenance
$results = $queueManager->pauseAllMembers('support', true, 'System maintenance');

// Resume all members
$results = $queueManager->pauseAllMembers('support', false);
```

## Queue Metrics and Analytics Methods

### `getQueueMetrics(string $queue, array $metrics = []): array`

Gets comprehensive metrics for a queue.

**Parameters:**
- `$queue` (string) - Queue name
- `$metrics` (array, optional) - Specific metrics to retrieve

**Available Metrics:**
- `calls` - Call statistics
- `members` - Member statistics
- `performance` - Performance metrics
- `efficiency` - Efficiency calculations

**Returns:** `array` - Requested metrics data

**Example:**
```php
$metrics = $queueManager->getQueueMetrics('support', ['calls', 'members', 'performance']);

echo "Total calls: " . $metrics['calls']['total'];
echo "Abandoned calls: " . $metrics['calls']['abandoned'];
echo "Average hold time: " . $metrics['performance']['avg_hold_time'];
echo "Service level: " . $metrics['performance']['service_level'];
```

### `calculateEfficiency(string $queue): array`

Calculates efficiency metrics for a queue.

**Parameters:**
- `$queue` (string) - Queue name

**Returns:** `array` - Efficiency metrics

**Example:**
```php
$efficiency = $queueManager->calculateEfficiency('support');
echo "Utilization rate: " . $efficiency['utilization_rate'] . "%";
echo "First call resolution: " . $efficiency['fcr_rate'] . "%";
echo "Average speed of answer: " . $efficiency['asa'] . " seconds";
```

### `getPerformanceReport(array $queues = [], string $period = 'today'): array`

Generates a performance report for specified queues.

**Parameters:**
- `$queues` (array, optional) - Queue names, empty for all queues
- `$period` (string, optional) - Time period ('today', 'week', 'month')

**Returns:** `array` - Performance report data

**Example:**
```php
$report = $queueManager->getPerformanceReport(['support', 'sales'], 'today');
foreach ($report as $queueName => $queueReport) {
    echo "Queue: {$queueName}";
    echo "Calls handled: " . $queueReport['calls_handled'];
    echo "Average wait time: " . $queueReport['avg_wait_time'];
    echo "Abandonment rate: " . $queueReport['abandonment_rate'] . "%";
}
```

## Queue Event Methods

### `logQueueEvent(string $queue, string $event, array $data = []): bool`

Logs a queue-related event for tracking and analytics.

**Parameters:**
- `$queue` (string) - Queue name
- `$event` (string) - Event type
- `$data` (array, optional) - Additional event data

**Returns:** `bool` - True if logged successfully

**Example:**
```php
$queueManager->logQueueEvent('support', 'member_added', [
    'member' => 'SIP/1001',
    'penalty' => 1,
    'timestamp' => time()
]);
```

### `getQueueEvents(string $queue, int $limit = 100): array`

Retrieves recent queue events for analysis.

**Parameters:**
- `$queue` (string) - Queue name
- `$limit` (int, optional) - Maximum number of events to retrieve

**Returns:** `array` - Array of queue events

**Example:**
```php
$events = $queueManager->getQueueEvents('support', 50);
foreach ($events as $event) {
    echo "Event: {$event['type']} at {$event['timestamp']}";
}
```

## Advanced Queue Management

### `reloadQueueConfiguration(string $queue = ''): bool`

Reloads queue configuration from Asterisk configuration files.

**Parameters:**
- `$queue` (string, optional) - Specific queue name, empty for all

**Returns:** `bool` - True if reload successful

**Example:**
```php
// Reload all queues
$result = $queueManager->reloadQueueConfiguration();

// Reload specific queue
$result = $queueManager->reloadQueueConfiguration('support');
```

### `resetQueueStatistics(string $queue): bool`

Resets statistics for a queue.

**Parameters:**
- `$queue` (string) - Queue name

**Returns:** `bool` - True if reset successful

**Example:**
```php
$result = $queueManager->resetQueueStatistics('support');
if ($result) {
    echo "Queue statistics reset successfully";
}
```

### `setQueueMusicOnHold(string $queue, string $mohClass): bool`

Sets the music on hold class for a queue.

**Parameters:**
- `$queue` (string) - Queue name
- `$mohClass` (string) - Music on hold class name

**Returns:** `bool` - True if set successfully

**Example:**
```php
$result = $queueManager->setQueueMusicOnHold('support', 'default');
```

## Error Handling

The service throws `ActionExecutionException` for AMI-related failures. Always implement proper error handling:

```php
try {
    $result = $queueManager->addMember('support', 'SIP/1001');
} catch (ActionExecutionException $e) {
    Log::error('Queue operation failed: ' . $e->getMessage());
    
    // Implement retry logic or fallback behavior
    if (str_contains($e->getMessage(), 'timeout')) {
        // Handle timeout scenarios
    } elseif (str_contains($e->getMessage(), 'not found')) {
        // Handle queue/member not found
    }
}
```

## Performance Considerations

- **Bulk Operations**: Use bulk methods for multiple operations to reduce AMI overhead
- **Status Caching**: Cache queue status data when possible, as status queries can be expensive
- **Event Filtering**: Filter queue events to process only relevant events
- **Connection Pooling**: Reuse the underlying AsteriskManagerService connection

## Thread Safety

The QueueManagerService inherits thread safety characteristics from the underlying AsteriskManagerService. Use separate instances for multi-threaded environments.

## See Also

- [AsteriskManagerService](AsteriskManagerService.md) - Core AMI functionality
- [EventProcessor](EventProcessor.md) - Event processing functionality
- [Queue Events Documentation](../events/queue-events.md) - Queue-specific events