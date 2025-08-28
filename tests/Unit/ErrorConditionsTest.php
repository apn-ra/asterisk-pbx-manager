<?php

namespace AsteriskPbxManager\Tests\Unit;

use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Tests\Unit\Mocks\PamiMockFactory;
use AsteriskPbxManager\Tests\Unit\Mocks\ResponseMockFactory;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use Illuminate\Support\Facades\Log;
use Exception;

class ErrorConditionsTest extends UnitTestCase
{
    public function test_connection_failure_throws_exception()
    {
        $mockClient = PamiMockFactory::createDisconnectedClient();
        $service = new AsteriskManagerService($mockClient);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Connection failed');

        $service->connect();
    }

    public function test_connection_timeout_throws_exception()
    {
        $mockClient = PamiMockFactory::createTimeoutClient(5);
        $service = new AsteriskManagerService($mockClient);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Connection timeout');

        $service->connect();
    }

    public function test_network_connection_error()
    {
        $mockClient = PamiMockFactory::createClient([
            'open' => ['exception' => new Exception('Network unreachable')],
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Network unreachable');

        $service->connect();
    }

    public function test_authentication_failure()
    {
        $mockClient = PamiMockFactory::createClient([
            'open' => ['exception' => new Exception('Authentication failed')],
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Authentication failed');

        $service->connect();
    }

    public function test_originate_call_failure_invalid_extension()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => ResponseMockFactory::createOriginateErrorResponse('No such extension/context'),
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('No such extension/context');

        $service->originateCall('SIP/1001', 'invalid-extension');
    }

    public function test_originate_call_failure_channel_busy()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => ResponseMockFactory::createOriginateErrorResponse('Channel is busy'),
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Channel is busy');

        $service->originateCall('SIP/1001', '1002');
    }

    public function test_hangup_call_failure_no_such_channel()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => ResponseMockFactory::createHangupErrorResponse('No such channel'),
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('No such channel');

        $service->hangupCall('SIP/invalid-channel');
    }

    public function test_queue_add_failure_no_such_queue()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => ResponseMockFactory::createQueueAddErrorResponse('No such queue'),
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('No such queue');

        $service->send(PamiMockFactory::createAction('QueueAdd', [
            'Queue' => 'invalid-queue',
            'Interface' => 'SIP/1001',
        ]));
    }

    public function test_queue_remove_failure_interface_not_in_queue()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => ResponseMockFactory::createQueueRemoveErrorResponse('Interface not in queue'),
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Interface not in queue');

        $service->send(PamiMockFactory::createAction('QueueRemove', [
            'Queue' => 'support',
            'Interface' => 'SIP/9999',
        ]));
    }

    public function test_permission_denied_error()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => ResponseMockFactory::createPermissionDeniedResponse('Originate'),
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Permission denied for action: Originate');

        $service->originateCall('SIP/1001', '1002');
    }

    public function test_action_timeout_error()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => ResponseMockFactory::createTimeoutResponse('Originate'),
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Timeout executing action: Originate');

        $service->originateCall('SIP/1001', '1002');
    }

    public function test_connection_lost_during_operation()
    {
        $mockClient = PamiMockFactory::createUnstableClient(2); // Fails after 2 operations
        $service = new AsteriskManagerService($mockClient);

        // First two operations should succeed
        $response1 = $service->send(PamiMockFactory::createOriginateAction());
        $response2 = $service->send(PamiMockFactory::createOriginateAction());

        $this->assertTrue($response1->isSuccess());
        $this->assertTrue($response2->isSuccess());

        // Third operation should fail
        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Connection lost');

        $service->send(PamiMockFactory::createOriginateAction());
    }

    public function test_malformed_response_handling()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => function () {
                throw new Exception('Malformed response received');
            },
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Malformed response received');

        $service->originateCall('SIP/1001', '1002');
    }

    public function test_invalid_parameters_handling()
    {
        $mockClient = PamiMockFactory::createConnectedClient();
        $service = new AsteriskManagerService($mockClient);

        // Test empty channel
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Channel cannot be empty');

        $service->originateCall('', '1002');
    }

    public function test_invalid_extension_handling()
    {
        $mockClient = PamiMockFactory::createConnectedClient();
        $service = new AsteriskManagerService($mockClient);

        // Test empty extension
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension cannot be empty');

        $service->originateCall('SIP/1001', '');
    }

    public function test_reconnection_attempts_exceeded()
    {
        $connectionAttempts = 0;
        $mockClient = PamiMockFactory::createClient([
            'open' => function () use (&$connectionAttempts) {
                $connectionAttempts++;
                throw new Exception('Connection failed');
            },
        ]);

        $service = new AsteriskManagerService($mockClient);

        // Should try multiple times before giving up
        try {
            $service->connectWithRetry();
        } catch (AsteriskConnectionException $e) {
            // Verify multiple attempts were made
            $this->assertGreaterThan(1, $connectionAttempts);
            $this->assertStringContains('max attempts', $e->getMessage());
        }
    }

    public function test_event_processing_error_handling()
    {
        $mockClient = PamiMockFactory::createEventSimulatingClient();
        $service = new AsteriskManagerService($mockClient);

        // Register event listener that throws exception
        $errorOccurred = false;
        $service->registerEventListener(function ($event) {
            throw new Exception('Event processing failed');
        });

        // Simulate event - should catch exception and log error
        Log::shouldReceive('error')
            ->once()
            ->with('Event processing failed', \Mockery::type('array'));

        try {
            $mockClient->simulateEvent(
                PamiMockFactory::createDialEvent(['Channel' => 'SIP/1001-00000001'])
            );
        } catch (Exception $e) {
            $errorOccurred = true;
        }

        // Event processing errors should be caught and logged, not propagated
        $this->assertFalse($errorOccurred);
    }

    public function test_concurrent_connection_attempts()
    {
        $mockClient = PamiMockFactory::createClient([
            'open' => function () {
                // Simulate delay
                usleep(100000); // 100ms
                throw new Exception('Another connection in progress');
            },
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Another connection in progress');

        $service->connect();
    }

    public function test_memory_exhaustion_protection()
    {
        // Create service with limited memory
        $mockClient = PamiMockFactory::createClient([
            'send' => function () {
                // Simulate memory-intensive response
                return ResponseMockFactory::createCoreStatusResponse([
                    'LargeData' => str_repeat('x', 1024 * 1024), // 1MB of data
                ]);
            },
        ]);

        $service = new AsteriskManagerService($mockClient);

        // Should handle large responses gracefully
        $response = $service->send(PamiMockFactory::createAction('CoreStatus'));
        $this->assertTrue($response->isSuccess());
    }

    public function test_rate_limiting_error()
    {
        $requestCount = 0;
        $mockClient = PamiMockFactory::createClient([
            'send' => function () use (&$requestCount) {
                $requestCount++;
                if ($requestCount > 10) {
                    return ResponseMockFactory::createErrorResponse('Rate limit exceeded', [
                        'Response' => 'Error',
                        'ActionID' => 'test_' . uniqid(),
                    ]);
                }
                return ResponseMockFactory::createOriginateSuccessResponse();
            },
        ]);

        $service = new AsteriskManagerService($mockClient);

        // First 10 requests should succeed
        for ($i = 0; $i < 10; $i++) {
            $response = $service->send(PamiMockFactory::createOriginateAction());
            $this->assertTrue($response->isSuccess());
        }

        // 11th request should fail with rate limit
        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $service->send(PamiMockFactory::createOriginateAction());
    }

    public function test_invalid_configuration_handling()
    {
        // Test with invalid configuration
        config(['asterisk-pbx-manager.connection.host' => '']);

        $mockClient = PamiMockFactory::createClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration: host cannot be empty');

        new AsteriskManagerService($mockClient);
    }

    public function test_ssl_connection_error()
    {
        $mockClient = PamiMockFactory::createClient([
            'open' => ['exception' => new Exception('SSL certificate verification failed')],
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('SSL certificate verification failed');

        $service->connect();
    }

    public function test_dns_resolution_error()
    {
        $mockClient = PamiMockFactory::createClient([
            'open' => ['exception' => new Exception('Could not resolve hostname')],
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Could not resolve hostname');

        $service->connect();
    }

    public function test_port_unavailable_error()
    {
        $mockClient = PamiMockFactory::createClient([
            'open' => ['exception' => new Exception('Connection refused on port 5038')],
        ]);

        $service = new AsteriskManagerService($mockClient);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Connection refused on port 5038');

        $service->connect();
    }

    public function test_graceful_degradation_on_partial_failure()
    {
        $operationCount = 0;
        $mockClient = PamiMockFactory::createClient([
            'send' => function () use (&$operationCount) {
                $operationCount++;
                
                // Every 3rd operation fails
                if ($operationCount % 3 === 0) {
                    return ResponseMockFactory::createTimeoutResponse('Test');
                }
                
                return ResponseMockFactory::createOriginateSuccessResponse();
            },
        ]);

        $service = new AsteriskManagerService($mockClient);
        
        $successCount = 0;
        $failureCount = 0;

        // Try multiple operations
        for ($i = 0; $i < 9; $i++) {
            try {
                $response = $service->send(PamiMockFactory::createOriginateAction());
                if ($response->isSuccess()) {
                    $successCount++;
                }
            } catch (ActionExecutionException $e) {
                $failureCount++;
            }
        }

        // Should have some successes and some failures
        $this->assertEquals(6, $successCount);
        $this->assertEquals(3, $failureCount);
    }

    public function test_error_context_preservation()
    {
        $mockClient = PamiMockFactory::createClient([
            'send' => ResponseMockFactory::createOriginateErrorResponse('Channel not found', [
                'Channel' => 'SIP/invalid-channel',
                'Context' => 'internal',
                'Extension' => '1002',
            ]),
        ]);

        $service = new AsteriskManagerService($mockClient);

        try {
            $service->originateCall('SIP/invalid-channel', '1002', 'internal');
        } catch (ActionExecutionException $e) {
            // Error should include context information
            $context = $e->getContext();
            $this->assertEquals('SIP/invalid-channel', $context['channel']);
            $this->assertEquals('internal', $context['context']);
            $this->assertEquals('1002', $context['extension']);
        }
    }
}