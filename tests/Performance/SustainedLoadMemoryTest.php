<?php

namespace AsteriskPbxManager\Tests\Performance;

use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\EventProcessor;
use AsteriskPbxManager\Tests\Unit\Generators\EventGenerator;
use AsteriskPbxManager\Tests\Unit\UnitTestCase;
use Mockery;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Response\Response;

class SustainedLoadMemoryTest extends UnitTestCase
{
    private AsteriskManagerService $asteriskService;
    private EventProcessor $eventProcessor;
    private EventGenerator $eventGenerator;
    private array $mockClients = [];

    protected function setUp(): void
    {
        parent::setUp();

        $mockClient = $this->createMockClient();
        $this->mockClients[] = $mockClient;
        $this->asteriskService = new AsteriskManagerService($mockClient);
        $this->eventProcessor = new EventProcessor();
        $this->eventGenerator = new EventGenerator();
    }

    /**
     * Test memory usage during sustained operations.
     *
     * @group performance
     */
    public function testSustainedOperationMemoryUsage()
    {
        $operationCount = 2000;
        $maxMemoryGrowth = 20 * 1024 * 1024; // 20MB max growth
        $memoryCheckInterval = 100; // Check every 100 operations
        $maxExecutionTime = 120; // 2 minutes limit

        $this->asteriskService->connect();

        $startTime = microtime(true);
        $baselineMemory = memory_get_usage(true);
        $peakMemory = $baselineMemory;
        $memoryReadings = [];
        $operationTimes = [];

        echo "\n";
        echo "Sustained Operation Memory Test:\n";
        echo "- Target operations: {$operationCount}\n";
        echo '- Baseline memory: '.number_format($baselineMemory / 1024 / 1024, 2)."MB\n";

        for ($i = 0; $i < $operationCount; $i++) {
            $opStart = microtime(true);

            // Rotate between different operations to simulate real usage
            switch ($i % 6) {
                case 0:
                    $result = $this->asteriskService->getStatus();
                    break;
                case 1:
                    $result = $this->asteriskService->originateCall('SIP/1001', '2002');
                    break;
                case 2:
                    $result = $this->asteriskService->hangupCall('SIP/1001-00000001');
                    break;
                case 3:
                    $result = $this->asteriskService->isConnected();
                    break;
                case 4:
                    // Simulate event processing
                    $event = $this->eventGenerator->createDialEvent();
                    $this->eventProcessor->processEvent($event);
                    break;
                case 5:
                    // Simulate more event processing
                    $event = $this->eventGenerator->createHangupEvent();
                    $this->eventProcessor->processEvent($event);
                    break;
            }

            $opEnd = microtime(true);
            $operationTimes[] = $opEnd - $opStart;

            // Memory monitoring
            if ($i % $memoryCheckInterval === 0) {
                $currentMemory = memory_get_usage(true);
                $memoryGrowth = $currentMemory - $baselineMemory;

                $memoryReadings[] = [
                    'operation' => $i,
                    'memory'    => $currentMemory,
                    'growth'    => $memoryGrowth,
                    'time'      => microtime(true) - $startTime,
                ];

                if ($currentMemory > $peakMemory) {
                    $peakMemory = $currentMemory;
                }

                // Assert memory growth is within limits
                $this->assertLessThan(
                    $maxMemoryGrowth,
                    $memoryGrowth,
                    "Memory growth exceeded limit at operation {$i}: ".
                    number_format($memoryGrowth / 1024 / 1024, 2).'MB'
                );

                // Progress indicator
                if ($i % 500 === 0) {
                    echo "- Progress: {$i}/{$operationCount} operations, ".
                         'Memory: '.number_format($currentMemory / 1024 / 1024, 2).'MB, '.
                         'Growth: '.number_format($memoryGrowth / 1024 / 1024, 2)."MB\n";
                }
            }

            // Force garbage collection periodically
            if ($i % 250 === 0 && $i > 0) {
                gc_collect_cycles();
            }
        }

        $endTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        $totalTime = $endTime - $startTime;
        $totalGrowth = $finalMemory - $baselineMemory;
        $avgOperationTime = array_sum($operationTimes) / count($operationTimes);
        $operationsPerSecond = $operationCount / $totalTime;

        // Performance assertions
        $this->assertLessThan(
            $maxExecutionTime,
            $totalTime,
            "Sustained operations took too long: {$totalTime}s"
        );

        $this->assertLessThan(
            $maxMemoryGrowth,
            $totalGrowth,
            'Total memory growth too high: '.number_format($totalGrowth / 1024 / 1024, 2).'MB'
        );

        // Log final metrics
        echo "\n";
        echo "Sustained Operation Results:\n";
        echo "- Operations completed: {$operationCount}\n";
        echo '- Total execution time: '.number_format($totalTime, 3)."s\n";
        echo '- Baseline memory: '.number_format($baselineMemory / 1024 / 1024, 2)."MB\n";
        echo '- Final memory: '.number_format($finalMemory / 1024 / 1024, 2)."MB\n";
        echo '- Peak memory: '.number_format($peakMemory / 1024 / 1024, 2)."MB\n";
        echo '- Total growth: '.number_format($totalGrowth / 1024 / 1024, 2)."MB\n";
        echo '- Average operation time: '.number_format($avgOperationTime * 1000, 2)."ms\n";
        echo '- Operations per second: '.number_format($operationsPerSecond, 2)."\n";
        echo '- Memory per operation: '.number_format($totalGrowth / $operationCount, 0)." bytes\n";

        $this->asteriskService->disconnect();
    }

    /**
     * Test long-running memory stability.
     *
     * @group performance
     */
    public function testLongRunningMemoryStability()
    {
        $durationMinutes = 5; // 5 minutes of continuous operation
        $operationInterval = 50000; // 50ms between operations (microseconds)
        $memoryCheckInterval = 30; // Check memory every 30 operations
        $maxMemoryGrowth = 15 * 1024 * 1024; // 15MB max growth

        $this->asteriskService->connect();

        $startTime = microtime(true);
        $endTime = $startTime + ($durationMinutes * 60);
        $baselineMemory = memory_get_usage(true);
        $peakMemory = $baselineMemory;
        $operationCount = 0;
        $memoryReadings = [];
        $stabilityViolations = 0;

        echo "\n";
        echo "Long-Running Memory Stability Test:\n";
        echo "- Duration: {$durationMinutes} minutes\n";
        echo '- Operation interval: '.number_format($operationInterval / 1000, 1)."ms\n";
        echo '- Baseline memory: '.number_format($baselineMemory / 1024 / 1024, 2)."MB\n";

        while (microtime(true) < $endTime) {
            $opStart = microtime(true);

            // Perform various operations
            switch ($operationCount % 8) {
                case 0:
                    $this->asteriskService->getStatus();
                    break;
                case 1:
                    $this->asteriskService->originateCall('SIP/1001', '2002');
                    break;
                case 2:
                    $this->asteriskService->hangupCall('SIP/1001-00000001');
                    break;
                case 3:
                    $this->asteriskService->isConnected();
                    break;
                case 4:
                case 5:
                    $event = $this->eventGenerator->createDialEvent();
                    $this->eventProcessor->processEvent($event);
                    break;
                case 6:
                case 7:
                    $event = $this->eventGenerator->createHangupEvent();
                    $this->eventProcessor->processEvent($event);
                    break;
            }

            $operationCount++;

            // Memory monitoring
            if ($operationCount % $memoryCheckInterval === 0) {
                $currentTime = microtime(true);
                $currentMemory = memory_get_usage(true);
                $memoryGrowth = $currentMemory - $baselineMemory;
                $elapsedTime = $currentTime - $startTime;

                $memoryReadings[] = [
                    'operation' => $operationCount,
                    'memory'    => $currentMemory,
                    'growth'    => $memoryGrowth,
                    'elapsed'   => $elapsedTime,
                ];

                if ($currentMemory > $peakMemory) {
                    $peakMemory = $currentMemory;
                }

                // Check for memory stability violations
                if ($memoryGrowth > $maxMemoryGrowth) {
                    $stabilityViolations++;
                }

                // Progress indicator every minute
                if (floor($elapsedTime / 60) !== floor(($elapsedTime - 30) / 60)) {
                    $minutesElapsed = floor($elapsedTime / 60);
                    echo "- Minute {$minutesElapsed}: {$operationCount} operations, ".
                         'Memory: '.number_format($currentMemory / 1024 / 1024, 2).'MB, '.
                         'Growth: '.number_format($memoryGrowth / 1024 / 1024, 2)."MB\n";
                }
            }

            // Force garbage collection periodically
            if ($operationCount % 200 === 0) {
                gc_collect_cycles();
            }

            // Wait for next operation
            $opEnd = microtime(true);
            $operationTime = ($opEnd - $opStart) * 1000000; // Convert to microseconds
            $remainingTime = $operationInterval - $operationTime;

            if ($remainingTime > 0) {
                usleep($remainingTime);
            }
        }

        $actualEndTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        $actualDuration = $actualEndTime - $startTime;
        $totalGrowth = $finalMemory - $baselineMemory;
        $operationsPerSecond = $operationCount / $actualDuration;

        // Analyze memory trend
        $this->analyzeMemoryTrend($memoryReadings);

        // Performance assertions
        $this->assertEquals(
            0,
            $stabilityViolations,
            "Memory stability violations detected: {$stabilityViolations}"
        );

        $this->assertLessThan(
            $maxMemoryGrowth,
            $totalGrowth,
            'Total memory growth exceeded limit: '.number_format($totalGrowth / 1024 / 1024, 2).'MB'
        );

        // Log final stability metrics
        echo "\n";
        echo "Long-Running Stability Results:\n";
        echo '- Actual duration: '.number_format($actualDuration / 60, 1)." minutes\n";
        echo "- Operations completed: {$operationCount}\n";
        echo '- Baseline memory: '.number_format($baselineMemory / 1024 / 1024, 2)."MB\n";
        echo '- Final memory: '.number_format($finalMemory / 1024 / 1024, 2)."MB\n";
        echo '- Peak memory: '.number_format($peakMemory / 1024 / 1024, 2)."MB\n";
        echo '- Total growth: '.number_format($totalGrowth / 1024 / 1024, 2)."MB\n";
        echo '- Operations per second: '.number_format($operationsPerSecond, 2)."\n";
        echo "- Stability violations: {$stabilityViolations}\n";

        $this->asteriskService->disconnect();
    }

    /**
     * Test garbage collection effectiveness.
     *
     * @group performance
     */
    public function testGarbageCollectionEffectiveness()
    {
        $cycleCount = 20;
        $operationsPerCycle = 200;
        $maxMemoryRetention = 5 * 1024 * 1024; // 5MB max retained after GC
        $gcEffectivenessThreshold = 0.8; // 80% of allocated memory should be freed

        $this->asteriskService->connect();

        $startTime = microtime(true);
        $baselineMemory = memory_get_usage(true);
        $gcResults = [];

        echo "\n";
        echo "Garbage Collection Effectiveness Test:\n";
        echo "- Cycles: {$cycleCount}\n";
        echo "- Operations per cycle: {$operationsPerCycle}\n";
        echo '- Baseline memory: '.number_format($baselineMemory / 1024 / 1024, 2)."MB\n";

        for ($cycle = 0; $cycle < $cycleCount; $cycle++) {
            $cycleStartMemory = memory_get_usage(true);

            // Perform operations that should create garbage
            for ($op = 0; $op < $operationsPerCycle; $op++) {
                // Create temporary objects and references
                $tempData = [];
                for ($i = 0; $i < 50; $i++) {
                    $tempData[] = $this->eventGenerator->createDialEvent();
                }

                // Process events (creates more objects)
                foreach (array_slice($tempData, 0, 10) as $event) {
                    $this->eventProcessor->processEvent($event);
                }

                // Perform service operations
                $this->asteriskService->getStatus();

                // Unset temporary data
                unset($tempData);
            }

            $preGcMemory = memory_get_usage(true);
            $preGcRealMemory = memory_get_usage(false);

            // Force garbage collection
            $collectedCycles = gc_collect_cycles();

            $postGcMemory = memory_get_usage(true);
            $postGcRealMemory = memory_get_usage(false);

            $memoryFreedReal = $preGcRealMemory - $postGcRealMemory;
            $memoryFreedTotal = $preGcMemory - $postGcMemory;
            $memoryRetained = $postGcMemory - $cycleStartMemory;

            $gcResults[] = [
                'cycle'             => $cycle,
                'pre_gc_memory'     => $preGcMemory,
                'post_gc_memory'    => $postGcMemory,
                'memory_freed'      => $memoryFreedTotal,
                'memory_freed_real' => $memoryFreedReal,
                'memory_retained'   => $memoryRetained,
                'cycles_collected'  => $collectedCycles,
                'effectiveness'     => $preGcRealMemory > 0 ? ($memoryFreedReal / $preGcRealMemory) : 0,
            ];

            // Assert memory retention is within limits
            $this->assertLessThan(
                $maxMemoryRetention,
                $memoryRetained,
                "Memory retention too high in cycle {$cycle}: ".
                number_format($memoryRetained / 1024 / 1024, 2).'MB'
            );

            // Progress indicator
            if ($cycle % 5 === 0) {
                echo "- Cycle {$cycle}: Freed ".number_format($memoryFreedReal / 1024 / 1024, 2).'MB real, '.
                     'Retained '.number_format($memoryRetained / 1024 / 1024, 2)."MB\n";
            }
        }

        $endTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        $totalTime = $endTime - $startTime;
        $totalGrowth = $finalMemory - $baselineMemory;

        // Analyze GC effectiveness
        $totalFreed = array_sum(array_column($gcResults, 'memory_freed_real'));
        $avgEffectiveness = array_sum(array_column($gcResults, 'effectiveness')) / count($gcResults);
        $totalCyclesCollected = array_sum(array_column($gcResults, 'cycles_collected'));

        // Performance assertions
        $this->assertGreaterThan(
            $gcEffectivenessThreshold,
            $avgEffectiveness,
            'Average GC effectiveness too low: '.number_format($avgEffectiveness * 100, 1).'%'
        );

        $this->assertLessThan(
            $maxMemoryRetention * 2, // Allow some growth over baseline
            $totalGrowth,
            'Total memory growth too high: '.number_format($totalGrowth / 1024 / 1024, 2).'MB'
        );

        // Log GC effectiveness results
        echo "\n";
        echo "Garbage Collection Results:\n";
        echo '- Total execution time: '.number_format($totalTime, 3)."s\n";
        echo '- Baseline memory: '.number_format($baselineMemory / 1024 / 1024, 2)."MB\n";
        echo '- Final memory: '.number_format($finalMemory / 1024 / 1024, 2)."MB\n";
        echo '- Total growth: '.number_format($totalGrowth / 1024 / 1024, 2)."MB\n";
        echo '- Total memory freed: '.number_format($totalFreed / 1024 / 1024, 2)."MB\n";
        echo '- Average GC effectiveness: '.number_format($avgEffectiveness * 100, 1)."%\n";
        echo "- Total cycles collected: {$totalCyclesCollected}\n";
        echo '- Cycles per GC run: '.number_format($totalCyclesCollected / $cycleCount, 1)."\n";

        $this->asteriskService->disconnect();
    }

    /**
     * Test memory usage during concurrent sustained load.
     *
     * @group performance
     */
    public function testConcurrentSustainedMemoryLoad()
    {
        $connectionCount = 5;
        $operationsPerConnection = 300;
        $maxTotalMemoryGrowth = 30 * 1024 * 1024; // 30MB max total growth
        $maxExecutionTime = 90; // 90 seconds limit

        $startTime = microtime(true);
        $baselineMemory = memory_get_usage(true);
        $services = [];
        $peakMemory = $baselineMemory;
        $memoryReadings = [];

        // Create multiple connections
        for ($i = 0; $i < $connectionCount; $i++) {
            $mockClient = $this->createMockClient();
            $this->mockClients[] = $mockClient;

            $service = new AsteriskManagerService($mockClient);
            $service->connect();
            $services[] = $service;
        }

        $connectionMemory = memory_get_usage(true);
        $connectionOverhead = $connectionMemory - $baselineMemory;

        echo "\n";
        echo "Concurrent Sustained Memory Load Test:\n";
        echo "- Connections: {$connectionCount}\n";
        echo "- Operations per connection: {$operationsPerConnection}\n";
        echo '- Baseline memory: '.number_format($baselineMemory / 1024 / 1024, 2)."MB\n";
        echo '- Connection overhead: '.number_format($connectionOverhead / 1024 / 1024, 2)."MB\n";

        $totalOperations = 0;

        // Perform sustained operations across all connections
        for ($operation = 0; $operation < $operationsPerConnection; $operation++) {
            foreach ($services as $index => $service) {
                // Vary operations across connections
                switch (($operation + $index) % 6) {
                    case 0:
                        $service->getStatus();
                        break;
                    case 1:
                        $service->originateCall('SIP/1001', '2002');
                        break;
                    case 2:
                        $service->hangupCall('SIP/1001-00000001');
                        break;
                    case 3:
                        $service->isConnected();
                        break;
                    case 4:
                        $event = $this->eventGenerator->createDialEvent();
                        $this->eventProcessor->processEvent($event);
                        break;
                    case 5:
                        $event = $this->eventGenerator->createHangupEvent();
                        $this->eventProcessor->processEvent($event);
                        break;
                }

                $totalOperations++;

                // Memory monitoring
                if ($totalOperations % 100 === 0) {
                    $currentMemory = memory_get_usage(true);
                    $memoryGrowth = $currentMemory - $baselineMemory;

                    $memoryReadings[] = [
                        'operation' => $totalOperations,
                        'memory'    => $currentMemory,
                        'growth'    => $memoryGrowth,
                    ];

                    if ($currentMemory > $peakMemory) {
                        $peakMemory = $currentMemory;
                    }

                    // Assert memory growth is reasonable
                    $this->assertLessThan(
                        $maxTotalMemoryGrowth,
                        $memoryGrowth,
                        "Memory growth exceeded limit at operation {$totalOperations}: ".
                        number_format($memoryGrowth / 1024 / 1024, 2).'MB'
                    );
                }
            }

            // Periodic garbage collection
            if ($operation % 50 === 0) {
                gc_collect_cycles();
            }

            // Progress indicator
            if ($operation % 100 === 0) {
                $currentMemory = memory_get_usage(true);
                echo '- Progress: '.number_format(($operation / $operationsPerConnection) * 100, 1).'%, '.
                     'Memory: '.number_format($currentMemory / 1024 / 1024, 2)."MB\n";
            }
        }

        $endTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        $totalTime = $endTime - $startTime;
        $totalGrowth = $finalMemory - $baselineMemory;
        $operationsPerSecond = $totalOperations / $totalTime;
        $memoryPerOperation = $totalGrowth / $totalOperations;

        // Performance assertions
        $this->assertLessThan(
            $maxExecutionTime,
            $totalTime,
            "Concurrent sustained operations took too long: {$totalTime}s"
        );

        $this->assertLessThan(
            $maxTotalMemoryGrowth,
            $totalGrowth,
            'Total memory growth exceeded limit: '.number_format($totalGrowth / 1024 / 1024, 2).'MB'
        );

        // Log concurrent sustained load results
        echo "\n";
        echo "Concurrent Sustained Load Results:\n";
        echo "- Total operations: {$totalOperations}\n";
        echo '- Total execution time: '.number_format($totalTime, 3)."s\n";
        echo '- Baseline memory: '.number_format($baselineMemory / 1024 / 1024, 2)."MB\n";
        echo '- Final memory: '.number_format($finalMemory / 1024 / 1024, 2)."MB\n";
        echo '- Peak memory: '.number_format($peakMemory / 1024 / 1024, 2)."MB\n";
        echo '- Total growth: '.number_format($totalGrowth / 1024 / 1024, 2)."MB\n";
        echo '- Operations per second: '.number_format($operationsPerSecond, 2)."\n";
        echo '- Memory per operation: '.number_format($memoryPerOperation, 0)." bytes\n";
        echo '- Memory per connection: '.number_format($totalGrowth / $connectionCount, 0)." bytes\n";

        // Clean up connections
        foreach ($services as $service) {
            $service->disconnect();
        }
    }

    /**
     * Analyze memory trend from readings.
     */
    private function analyzeMemoryTrend(array $memoryReadings): void
    {
        if (count($memoryReadings) < 4) {
            return;
        }

        $firstQuarter = array_slice($memoryReadings, 0, count($memoryReadings) / 4);
        $lastQuarter = array_slice($memoryReadings, -count($memoryReadings) / 4);

        $firstAvg = array_sum(array_column($firstQuarter, 'growth')) / count($firstQuarter);
        $lastAvg = array_sum(array_column($lastQuarter, 'growth')) / count($lastQuarter);

        $trend = $lastAvg - $firstAvg;
        $trendPercentage = $firstAvg > 0 ? ($trend / $firstAvg) * 100 : 0;

        echo "- Memory trend analysis:\n";
        echo '  * First quarter avg growth: '.number_format($firstAvg / 1024 / 1024, 2)."MB\n";
        echo '  * Last quarter avg growth: '.number_format($lastAvg / 1024 / 1024, 2)."MB\n";
        echo '  * Trend: '.number_format($trend / 1024 / 1024, 2).'MB ('.
             number_format($trendPercentage, 1)."%)\n";
    }

    /**
     * Create a mock PAMI client for testing.
     */
    private function createMockClient(): ClientImpl
    {
        $mockClient = Mockery::mock(ClientImpl::class);

        $mockClient->shouldReceive('open')
            ->once()
            ->andReturn(true);

        $mockClient->shouldReceive('close')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        // Mock successful responses
        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getKeys')->andReturn(['Response' => 'Success']);

        $mockClient->shouldReceive('send')
            ->zeroOrMoreTimes()
            ->andReturn($mockResponse);

        return $mockClient;
    }

    protected function tearDown(): void
    {
        // Clean up mock clients
        $this->mockClients = [];
        parent::tearDown();
    }
}
