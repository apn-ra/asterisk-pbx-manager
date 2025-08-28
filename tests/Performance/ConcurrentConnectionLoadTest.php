<?php

namespace AsteriskPbxManager\Tests\Performance;

use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Tests\Unit\UnitTestCase;
use Mockery;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Response\Response;

class ConcurrentConnectionLoadTest extends UnitTestCase
{
    private array $mockClients = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClients = [];
    }

    /**
     * Test concurrent connection establishment performance.
     *
     * @group performance
     */
    public function testConcurrentConnectionEstablishment()
    {
        $connectionCount = 20;
        $maxExecutionTime = 30; // 30 seconds limit
        $maxMemoryUsage = 100 * 1024 * 1024; // 100MB limit

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $services = [];
        $connectionTimes = [];
        $peakMemory = 0;

        // Create multiple service instances with mocked clients
        for ($i = 0; $i < $connectionCount; $i++) {
            $mockClient = $this->createMockClient();
            $this->mockClients[] = $mockClient;

            $connectionStart = microtime(true);

            $service = new AsteriskManagerService($mockClient);
            $result = $service->connect();

            $connectionEnd = microtime(true);
            $connectionTime = $connectionEnd - $connectionStart;
            $connectionTimes[] = $connectionTime;

            $this->assertTrue($result, "Connection {$i} should succeed");
            $services[] = $service;

            $currentMemory = memory_get_usage(true);
            if ($currentMemory > $peakMemory) {
                $peakMemory = $currentMemory;
            }

            // Test connection status
            $this->assertTrue($service->isConnected(), "Service {$i} should report connected");
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $totalExecutionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $avgConnectionTime = array_sum($connectionTimes) / count($connectionTimes);
        $maxConnectionTime = max($connectionTimes);
        $minConnectionTime = min($connectionTimes);

        // Performance assertions
        $this->assertLessThan(
            $maxExecutionTime,
            $totalExecutionTime,
            "Concurrent connection establishment took too long: {$totalExecutionTime}s"
        );

        $this->assertLessThan(
            $maxMemoryUsage,
            $memoryUsed,
            'Memory usage too high: '.number_format($memoryUsed / 1024 / 1024, 2).'MB'
        );

        $this->assertLessThan(
            2.0, // 2 seconds per connection should be reasonable
            $avgConnectionTime,
            "Average connection time too slow: {$avgConnectionTime}s"
        );

        // Log performance metrics
        echo "\n";
        echo "Concurrent Connection Performance:\n";
        echo "- Connections established: {$connectionCount}\n";
        echo '- Total execution time: '.number_format($totalExecutionTime, 3)."s\n";
        echo '- Memory used: '.number_format($memoryUsed / 1024 / 1024, 2)."MB\n";
        echo '- Peak memory: '.number_format($peakMemory / 1024 / 1024, 2)."MB\n";
        echo '- Average connection time: '.number_format($avgConnectionTime * 1000, 2)."ms\n";
        echo '- Min connection time: '.number_format($minConnectionTime * 1000, 2)."ms\n";
        echo '- Max connection time: '.number_format($maxConnectionTime * 1000, 2)."ms\n";
        echo '- Connections per second: '.number_format($connectionCount / $totalExecutionTime, 2)."\n";

        // Clean up connections
        foreach ($services as $service) {
            $service->disconnect();
        }
    }

    /**
     * Test concurrent operations with multiple active connections.
     *
     * @group performance
     */
    public function testConcurrentOperationsLoad()
    {
        $connectionCount = 10;
        $operationsPerConnection = 20;
        $maxExecutionTime = 45; // 45 seconds limit
        $maxMemoryUsage = 150 * 1024 * 1024; // 150MB limit

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $services = [];
        $operationTimes = [];
        $totalOperations = 0;
        $peakMemory = 0;

        // Establish multiple connections
        for ($i = 0; $i < $connectionCount; $i++) {
            $mockClient = $this->createMockClient();
            $this->mockClients[] = $mockClient;

            $service = new AsteriskManagerService($mockClient);
            $service->connect();
            $services[] = $service;
        }

        // Perform concurrent operations
        for ($operation = 0; $operation < $operationsPerConnection; $operation++) {
            foreach ($services as $index => $service) {
                $operationStart = microtime(true);

                // Simulate various AMI operations
                switch ($operation % 4) {
                    case 0:
                        $result = $service->getStatus();
                        break;
                    case 1:
                        $result = $service->originateCall('SIP/1001', '2002');
                        break;
                    case 2:
                        $result = $service->hangupCall('SIP/1001-00000001');
                        break;
                    case 3:
                        $result = $service->isConnected();
                        break;
                }

                $operationEnd = microtime(true);
                $operationTime = $operationEnd - $operationStart;
                $operationTimes[] = $operationTime;
                $totalOperations++;

                $currentMemory = memory_get_usage(true);
                if ($currentMemory > $peakMemory) {
                    $peakMemory = $currentMemory;
                }

                // Periodic memory check
                if ($totalOperations % 50 === 0) {
                    $this->assertLessThan(
                        $maxMemoryUsage,
                        $currentMemory - $startMemory,
                        "Memory usage exceeded limit after {$totalOperations} operations"
                    );
                }
            }

            // Simulate brief pause between operation rounds
            usleep(10000); // 10ms
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $totalExecutionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $avgOperationTime = array_sum($operationTimes) / count($operationTimes);
        $operationsPerSecond = $totalOperations / $totalExecutionTime;

        // Performance assertions
        $this->assertLessThan(
            $maxExecutionTime,
            $totalExecutionTime,
            "Concurrent operations took too long: {$totalExecutionTime}s"
        );

        $this->assertLessThan(
            $maxMemoryUsage,
            $memoryUsed,
            'Memory usage too high: '.number_format($memoryUsed / 1024 / 1024, 2).'MB'
        );

        $this->assertGreaterThan(
            10, // At least 10 operations per second
            $operationsPerSecond,
            "Operations per second too low: {$operationsPerSecond}"
        );

        // Log concurrent operations metrics
        echo "\n";
        echo "Concurrent Operations Performance:\n";
        echo "- Active connections: {$connectionCount}\n";
        echo "- Total operations: {$totalOperations}\n";
        echo '- Total execution time: '.number_format($totalExecutionTime, 3)."s\n";
        echo '- Memory used: '.number_format($memoryUsed / 1024 / 1024, 2)."MB\n";
        echo '- Peak memory: '.number_format($peakMemory / 1024 / 1024, 2)."MB\n";
        echo '- Average operation time: '.number_format($avgOperationTime * 1000, 2)."ms\n";
        echo '- Operations per second: '.number_format($operationsPerSecond, 2)."\n";
        echo "- Operations per connection: {$operationsPerConnection}\n";

        // Clean up connections
        foreach ($services as $service) {
            $service->disconnect();
        }
    }

    /**
     * Test connection pooling behavior under load.
     *
     * @group performance
     */
    public function testConnectionPoolingLoad()
    {
        $poolSize = 15;
        $requestCount = 100;
        $maxExecutionTime = 30; // 30 seconds limit
        $maxMemoryUsage = 80 * 1024 * 1024; // 80MB limit

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $connectionPool = [];
        $requestTimes = [];
        $peakMemory = 0;

        // Initialize connection pool
        for ($i = 0; $i < $poolSize; $i++) {
            $mockClient = $this->createMockClient();
            $this->mockClients[] = $mockClient;

            $service = new AsteriskManagerService($mockClient);
            $service->connect();
            $connectionPool[] = $service;
        }

        // Simulate high-frequency requests using connection pool
        for ($request = 0; $request < $requestCount; $request++) {
            $requestStart = microtime(true);

            // Get connection from pool (round-robin)
            $poolIndex = $request % $poolSize;
            $service = $connectionPool[$poolIndex];

            // Perform operation
            $result = $service->getStatus();
            $this->assertNotNull($result);

            $requestEnd = microtime(true);
            $requestTime = $requestEnd - $requestStart;
            $requestTimes[] = $requestTime;

            $currentMemory = memory_get_usage(true);
            if ($currentMemory > $peakMemory) {
                $peakMemory = $currentMemory;
            }

            // Brief pause to simulate real-world request intervals
            usleep(5000); // 5ms
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $totalExecutionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $avgRequestTime = array_sum($requestTimes) / count($requestTimes);
        $requestsPerSecond = $requestCount / $totalExecutionTime;
        $poolUtilization = $requestCount / $poolSize;

        // Performance assertions
        $this->assertLessThan(
            $maxExecutionTime,
            $totalExecutionTime,
            "Connection pooling test took too long: {$totalExecutionTime}s"
        );

        $this->assertLessThan(
            $maxMemoryUsage,
            $memoryUsed,
            'Memory usage too high with connection pooling: '.number_format($memoryUsed / 1024 / 1024, 2).'MB'
        );

        $this->assertGreaterThan(
            5, // At least 5 requests per second with pooling
            $requestsPerSecond,
            "Requests per second too low with pooling: {$requestsPerSecond}"
        );

        // Log connection pooling metrics
        echo "\n";
        echo "Connection Pooling Performance:\n";
        echo "- Pool size: {$poolSize} connections\n";
        echo "- Total requests: {$requestCount}\n";
        echo '- Pool utilization: '.number_format($poolUtilization, 1)."x per connection\n";
        echo '- Total execution time: '.number_format($totalExecutionTime, 3)."s\n";
        echo '- Memory used: '.number_format($memoryUsed / 1024 / 1024, 2)."MB\n";
        echo '- Peak memory: '.number_format($peakMemory / 1024 / 1024, 2)."MB\n";
        echo '- Average request time: '.number_format($avgRequestTime * 1000, 2)."ms\n";
        echo '- Requests per second: '.number_format($requestsPerSecond, 2)."\n";
        echo '- Memory per connection: '.number_format($memoryUsed / $poolSize, 0)." bytes\n";

        // Clean up connection pool
        foreach ($connectionPool as $service) {
            $service->disconnect();
        }
    }

    /**
     * Test connection failure and recovery under load.
     *
     * @group performance
     */
    public function testConnectionFailureRecoveryLoad()
    {
        $connectionCount = 12;
        $failureRate = 0.3; // 30% of connections will fail
        $maxRecoveryTime = 20; // 20 seconds for recovery
        $maxMemoryUsage = 60 * 1024 * 1024; // 60MB limit

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $services = [];
        $failedConnections = 0;
        $recoveredConnections = 0;
        $recoveryTimes = [];
        $peakMemory = 0;

        // Create connections with some failures
        for ($i = 0; $i < $connectionCount; $i++) {
            $shouldFail = (rand(1, 100) / 100) <= $failureRate;

            $mockClient = $this->createMockClient($shouldFail);
            $this->mockClients[] = $mockClient;

            $service = new AsteriskManagerService($mockClient);

            try {
                $result = $service->connect();
                if ($result) {
                    $services[] = $service;
                }
            } catch (AsteriskConnectionException $e) {
                $failedConnections++;

                // Attempt recovery
                $recoveryStart = microtime(true);

                // Create new working client for recovery
                $recoveryClient = $this->createMockClient(false);
                $recoveryService = new AsteriskManagerService($recoveryClient);

                try {
                    $recoveryResult = $recoveryService->connect();
                    if ($recoveryResult) {
                        $recoveredConnections++;
                        $services[] = $recoveryService;

                        $recoveryEnd = microtime(true);
                        $recoveryTimes[] = $recoveryEnd - $recoveryStart;
                    }
                } catch (AsteriskConnectionException $recoveryException) {
                    // Recovery failed
                }
            }

            $currentMemory = memory_get_usage(true);
            if ($currentMemory > $peakMemory) {
                $peakMemory = $currentMemory;
            }
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $totalExecutionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $successfulConnections = count($services);
        $recoveryRate = $recoveredConnections > 0 ? ($recoveredConnections / $failedConnections) : 0;
        $avgRecoveryTime = count($recoveryTimes) > 0 ? array_sum($recoveryTimes) / count($recoveryTimes) : 0;

        // Performance assertions
        $this->assertLessThan(
            $maxRecoveryTime,
            $totalExecutionTime,
            "Connection failure recovery took too long: {$totalExecutionTime}s"
        );

        $this->assertLessThan(
            $maxMemoryUsage,
            $memoryUsed,
            'Memory usage too high during failure recovery: '.number_format($memoryUsed / 1024 / 1024, 2).'MB'
        );

        $this->assertGreaterThan(
            0,
            $successfulConnections,
            'No successful connections established'
        );

        // Log failure recovery metrics
        echo "\n";
        echo "Connection Failure Recovery Performance:\n";
        echo "- Target connections: {$connectionCount}\n";
        echo "- Successful connections: {$successfulConnections}\n";
        echo "- Failed connections: {$failedConnections}\n";
        echo "- Recovered connections: {$recoveredConnections}\n";
        echo '- Recovery rate: '.number_format($recoveryRate * 100, 1)."%\n";
        echo '- Total execution time: '.number_format($totalExecutionTime, 3)."s\n";
        echo '- Memory used: '.number_format($memoryUsed / 1024 / 1024, 2)."MB\n";
        echo '- Peak memory: '.number_format($peakMemory / 1024 / 1024, 2)."MB\n";
        if ($avgRecoveryTime > 0) {
            echo '- Average recovery time: '.number_format($avgRecoveryTime * 1000, 2)."ms\n";
        }
        echo '- Success rate: '.number_format(($successfulConnections / $connectionCount) * 100, 1)."%\n";

        // Clean up connections
        foreach ($services as $service) {
            $service->disconnect();
        }
    }

    /**
     * Create a mock PAMI client for testing.
     */
    private function createMockClient(bool $shouldFail = false): ClientImpl
    {
        $mockClient = Mockery::mock(ClientImpl::class);

        if ($shouldFail) {
            $mockClient->shouldReceive('open')
                ->once()
                ->andThrow(new \Exception('Connection failed'));
        } else {
            $mockClient->shouldReceive('open')
                ->once()
                ->andReturn(true);

            $mockClient->shouldReceive('close')
                ->zeroOrMoreTimes()
                ->andReturn(true);

            // Mock successful responses for various actions
            $mockResponse = Mockery::mock(Response::class);
            $mockResponse->shouldReceive('isSuccess')->andReturn(true);
            $mockResponse->shouldReceive('getKeys')->andReturn(['Response' => 'Success']);

            $mockClient->shouldReceive('send')
                ->zeroOrMoreTimes()
                ->andReturn($mockResponse);
        }

        return $mockClient;
    }

    protected function tearDown(): void
    {
        // Clean up mock clients
        $this->mockClients = [];
        parent::tearDown();
    }
}
