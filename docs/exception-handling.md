# Exception Handling

This document provides comprehensive guidance on exception handling in the Asterisk PBX Manager Laravel package. The package implements secure and robust error handling patterns using custom exceptions.

## Table of Contents

1. [Overview](#overview)
2. [Custom Exceptions](#custom-exceptions)
3. [Exception Hierarchy](#exception-hierarchy)
4. [Usage Patterns](#usage-patterns)
5. [Error Handling Best Practices](#error-handling-best-practices)
6. [Security Considerations](#security-considerations)
7. [Logging and Monitoring](#logging-and-monitoring)
8. [Testing Exception Scenarios](#testing-exception-scenarios)

## Overview

The Asterisk PBX Manager package uses a structured exception handling approach that provides:

- **Secure Error Messages**: Prevents information disclosure while maintaining diagnostic value
- **Error Reference Tracking**: Each exception includes a unique reference ID for tracking
- **Context Preservation**: Important context is preserved for debugging and monitoring
- **Type-Specific Exceptions**: Different exception types for different error categories

## Custom Exceptions

### AsteriskConnectionException

Handles all connection-related errors when communicating with the Asterisk Manager Interface.

#### Factory Methods

```php
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;

// Connection timeout
$exception = AsteriskConnectionException::timeout(30);

// Authentication failure
$exception = AsteriskConnectionException::authenticationFailed('admin');

// Network error
$exception = AsteriskConnectionException::networkError('192.168.1.100', 5038, 'Connection refused');

// Invalid configuration
$exception = AsteriskConnectionException::invalidConfiguration('ami_secret');
```

#### Usage Example

```php
use AsteriskPbxManager\Facades\AsteriskManager;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;

try {
    AsteriskManager::connect();
} catch (AsteriskConnectionException $e) {
    // Log the error with reference ID
    Log::error('AMI connection failed', [
        'error_reference' => $e->getErrorReference(),
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
    // Handle the error appropriately
    return response()->json([
        'error' => 'Connection failed',
        'reference' => $e->getErrorReference()
    ], 503);
}
```

### ActionExecutionException

Handles errors that occur during AMI action execution.

#### Factory Methods

```php
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Response\ResponseMessage;

// Action failed
$action = new OriginateAction('SIP/1001', '2002');
$response = new ResponseMessage(); // Failed response
$exception = ActionExecutionException::actionFailed($action, $response);

// Action timeout
$exception = ActionExecutionException::timeout($action, 30);

// Invalid parameter
$exception = ActionExecutionException::invalidParameter('Originate', 'channel', 'invalid-channel');

// Missing parameter
$exception = ActionExecutionException::missingParameter('Originate', 'extension');

// Permission denied
$exception = ActionExecutionException::permissionDenied('Originate', 'readonly_user');
```

#### Usage Example

```php
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Exceptions\ActionExecutionException;

try {
    $service = app(AsteriskManagerService::class);
    $result = $service->originateCall('SIP/1001', '2002');
} catch (ActionExecutionException $e) {
    // Access additional context
    $context = $e->getContext();
    $action = $e->getAction();
    $response = $e->getResponse();
    
    Log::warning('AMI action failed', [
        'error_reference' => $e->getErrorReference(),
        'action_name' => $action ? $action->getActionName() : 'unknown',
        'context' => $context
    ]);
    
    // Return appropriate error response
    return response()->json([
        'error' => 'Action execution failed',
        'reference' => $e->getErrorReference()
    ], 422);
}
```

## Exception Hierarchy

```
Exception (PHP Core)
├── AsteriskConnectionException
│   ├── Connection timeout errors
│   ├── Authentication failures
│   ├── Network connectivity issues
│   └── Configuration validation errors
└── ActionExecutionException
    ├── AMI action failures
    ├── Parameter validation errors
    ├── Permission denied errors
    └── Action timeout errors
```

## Usage Patterns

### Basic Error Handling

```php
use AsteriskPbxManager\Facades\AsteriskManager;
use AsteriskPbxManager\Exceptions\{AsteriskConnectionException, ActionExecutionException};

try {
    // Connect to AMI
    AsteriskManager::connect();
    
    // Execute actions
    $result = AsteriskManager::originateCall('SIP/1001', '2002');
    
} catch (AsteriskConnectionException $e) {
    // Handle connection-specific errors
    $this->handleConnectionError($e);
    
} catch (ActionExecutionException $e) {
    // Handle action execution errors
    $this->handleActionError($e);
    
} catch (\Exception $e) {
    // Handle unexpected errors
    $this->handleUnexpectedError($e);
}
```

### Laravel Controller Integration

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use AsteriskPbxManager\Facades\AsteriskManager;
use AsteriskPbxManager\Exceptions\{AsteriskConnectionException, ActionExecutionException};

class CallController extends Controller
{
    public function initiateCall(Request $request): JsonResponse
    {
        try {
            $result = AsteriskManager::originateCall(
                $request->input('from'),
                $request->input('to')
            );
            
            return response()->json([
                'success' => true,
                'call_id' => $result['ActionID']
            ]);
            
        } catch (AsteriskConnectionException $e) {
            return $this->handleConnectionError($e);
            
        } catch (ActionExecutionException $e) {
            return $this->handleActionError($e);
        }
    }
    
    private function handleConnectionError(AsteriskConnectionException $e): JsonResponse
    {
        \Log::error('AMI connection failed', [
            'reference' => $e->getErrorReference(),
            'message' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Service temporarily unavailable',
            'reference' => $e->getErrorReference()
        ], 503);
    }
    
    private function handleActionError(ActionExecutionException $e): JsonResponse
    {
        \Log::warning('AMI action failed', [
            'reference' => $e->getErrorReference(),
            'context' => $e->getContext()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Call initiation failed',
            'reference' => $e->getErrorReference()
        ], 422);
    }
}
```

### Service Layer Error Handling

```php
<?php

namespace App\Services;

use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Exceptions\{AsteriskConnectionException, ActionExecutionException};

class PhoneService
{
    public function __construct(
        private AsteriskManagerService $asteriskService
    ) {}
    
    public function makeCall(string $from, string $to): array
    {
        try {
            // Ensure connection
            if (!$this->asteriskService->isConnected()) {
                $this->asteriskService->connect();
            }
            
            // Originate the call
            $result = $this->asteriskService->originateCall($from, $to);
            
            return [
                'success' => true,
                'call_id' => $result['ActionID']
            ];
            
        } catch (AsteriskConnectionException $e) {
            // Attempt reconnection for connection errors
            return $this->handleConnectionErrorWithRetry($from, $to, $e);
            
        } catch (ActionExecutionException $e) {
            return [
                'success' => false,
                'error' => 'Call failed',
                'reference' => $e->getErrorReference(),
                'details' => $this->extractActionErrorDetails($e)
            ];
        }
    }
    
    private function handleConnectionErrorWithRetry(
        string $from, 
        string $to, 
        AsteriskConnectionException $e
    ): array {
        try {
            // Attempt one reconnection
            $this->asteriskService->reconnect();
            $result = $this->asteriskService->originateCall($from, $to);
            
            return [
                'success' => true,
                'call_id' => $result['ActionID'],
                'recovered' => true
            ];
            
        } catch (\Exception $retryException) {
            \Log::error('Connection recovery failed', [
                'original_reference' => $e->getErrorReference(),
                'retry_error' => $retryException->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Service unavailable',
                'reference' => $e->getErrorReference()
            ];
        }
    }
    
    private function extractActionErrorDetails(ActionExecutionException $e): array
    {
        $context = $e->getContext();
        $action = $e->getAction();
        
        return [
            'action_name' => $action ? $action->getActionName() : 'unknown',
            'error_type' => $context['error_type'] ?? 'unknown',
            'parameters' => $context['action_parameters'] ?? []
        ];
    }
}
```

## Error Handling Best Practices

### 1. Always Use Specific Exception Types

```php
// Good
try {
    $service->connect();
} catch (AsteriskConnectionException $e) {
    // Handle connection errors
} catch (ActionExecutionException $e) {
    // Handle action errors
}

// Avoid
try {
    $service->connect();
} catch (\Exception $e) {
    // Too generic - doesn't allow proper error handling
}
```

### 2. Log with Context

```php
// Good
catch (AsteriskConnectionException $e) {
    Log::error('AMI connection failed', [
        'reference' => $e->getErrorReference(),
        'host' => config('asterisk-pbx-manager.connection.host'),
        'port' => config('asterisk-pbx-manager.connection.port'),
        'user_id' => auth()->id()
    ]);
}

// Avoid
catch (AsteriskConnectionException $e) {
    Log::error($e->getMessage()); // Not enough context
}
```

### 3. Implement Graceful Degradation

```php
public function getChannelStatus(): array
{
    try {
        return $this->asteriskService->getChannels();
    } catch (AsteriskConnectionException $e) {
        // Return cached data or empty state
        return $this->getCachedChannelStatus();
    } catch (ActionExecutionException $e) {
        // Return partial data or safe defaults
        return ['channels' => [], 'error' => 'Partial data unavailable'];
    }
}
```

### 4. Use Circuit Breaker Pattern for Resilience

```php
public function makeCallWithCircuitBreaker(string $from, string $to): array
{
    if ($this->circuitBreaker->isOpen()) {
        return [
            'success' => false,
            'error' => 'Service temporarily unavailable',
            'retry_after' => $this->circuitBreaker->getRetryAfter()
        ];
    }
    
    try {
        $result = $this->asteriskService->originateCall($from, $to);
        $this->circuitBreaker->recordSuccess();
        return $result;
        
    } catch (AsteriskConnectionException $e) {
        $this->circuitBreaker->recordFailure();
        throw $e;
    }
}
```

## Security Considerations

### 1. Information Disclosure Prevention

The custom exceptions implement secure error handling to prevent information disclosure:

```php
// The package automatically sanitizes error messages
try {
    $service->connect();
} catch (AsteriskConnectionException $e) {
    // $e->getMessage() contains sanitized message safe for client consumption
    // Detailed information is logged securely server-side
}
```

### 2. Error Reference Tracking

Use error references for secure error tracking:

```php
// Client receives only reference ID
return response()->json([
    'error' => 'Operation failed',
    'reference' => $e->getErrorReference() // e.g., "ERR-2023-1234567890"
]);

// Server logs contain full details tied to reference
Log::error('Detailed error information', [
    'reference' => $e->getErrorReference(),
    'sensitive_details' => $sensitiveData
]);
```

### 3. Input Validation Errors

Handle validation errors securely:

```php
try {
    $service->originateCall($from, $to);
} catch (ActionExecutionException $e) {
    // Don't expose internal validation logic
    return response()->json([
        'error' => 'Invalid request parameters',
        'reference' => $e->getErrorReference()
    ], 422);
}
```

## Logging and Monitoring

### 1. Structured Logging

```php
// Use consistent log structure
Log::error('AMI operation failed', [
    'component' => 'asterisk-pbx-manager',
    'operation' => 'originate_call',
    'reference' => $e->getErrorReference(),
    'user_id' => auth()->id(),
    'timestamp' => now()->toISOString(),
    'context' => $e->getContext()
]);
```

### 2. Metrics Collection

```php
// Track error rates and types
public function handleException(\Exception $e): void
{
    // Increment error counters
    if ($e instanceof AsteriskConnectionException) {
        Metrics::increment('asterisk.connection_errors');
    } elseif ($e instanceof ActionExecutionException) {
        Metrics::increment('asterisk.action_errors');
        Metrics::increment('asterisk.action_errors.by_type', [
            'action' => $e->getAction()?->getActionName() ?? 'unknown'
        ]);
    }
    
    // Record error reference for tracking
    Metrics::histogram('asterisk.error_references', [
        'reference' => $e->getErrorReference()
    ]);
}
```

### 3. Health Check Integration

```php
public function healthCheck(): array
{
    try {
        $this->asteriskService->getStatus();
        return ['status' => 'healthy'];
    } catch (AsteriskConnectionException $e) {
        return [
            'status' => 'unhealthy',
            'error' => 'Connection failed',
            'reference' => $e->getErrorReference()
        ];
    }
}
```

## Testing Exception Scenarios

### 1. Unit Testing Exceptions

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;

class ExceptionHandlingTest extends TestCase
{
    public function testConnectionTimeoutThrowsException(): void
    {
        $service = $this->createServiceWithTimeoutMock();
        
        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Connection timeout');
        
        $service->connect();
    }
    
    public function testExceptionContainsErrorReference(): void
    {
        $exception = AsteriskConnectionException::timeout(30);
        
        $this->assertNotNull($exception->getErrorReference());
        $this->assertStringStartsWith('ERR-', $exception->getErrorReference());
    }
}
```

### 2. Integration Testing

```php
<?php

namespace Tests\Integration;

use Orchestra\Testbench\TestCase;
use AsteriskPbxManager\Exceptions\ActionExecutionException;

class ErrorRecoveryTest extends TestCase
{
    public function testServiceRecoveryAfterConnectionError(): void
    {
        // Simulate connection failure followed by recovery
        $service = $this->createServiceWithRecoveryMock();
        
        // First call should fail
        $this->expectException(AsteriskConnectionException::class);
        $service->originateCall('SIP/1001', '2002');
        
        // Recovery should work
        $result = $service->reconnectAndRetry('SIP/1001', '2002');
        $this->assertTrue($result['success']);
    }
}
```

### 3. Error Scenario Simulation

```php
// Create mock conditions for testing
public function simulateNetworkError(): void
{
    // Mock network connectivity issues
    $this->mockPamiClient
        ->shouldReceive('open')
        ->andThrow(new \Exception('Network unreachable'));
}

public function simulateAuthenticationFailure(): void
{
    // Mock authentication problems
    $this->mockPamiClient
        ->shouldReceive('open')
        ->andThrow(AsteriskConnectionException::authenticationFailed('test_user'));
}
```

## Conclusion

The Asterisk PBX Manager package provides robust exception handling that balances security, usability, and maintainability. By following these patterns and best practices, you can build resilient applications that handle errors gracefully while maintaining security and providing valuable diagnostic information.

Key takeaways:

- Use specific exception types for targeted error handling
- Always log errors with proper context and reference IDs
- Implement graceful degradation and recovery mechanisms
- Follow security best practices to prevent information disclosure
- Test exception scenarios thoroughly
- Monitor error rates and patterns for system health

For additional information, refer to:
- [Security Guidelines](security-guidelines.md)
- [API Documentation](api/)
- [Troubleshooting Guide](troubleshooting.md)