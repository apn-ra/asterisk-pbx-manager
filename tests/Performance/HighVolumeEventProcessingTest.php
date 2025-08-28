<?php

namespace AsteriskPbxManager\Tests\Performance;

use AsteriskPbxManager\Services\EventProcessor;
use AsteriskPbxManager\Tests\Unit\UnitTestCase;
use AsteriskPbxManager\Tests\Unit\Generators\EventGenerator;
use Mockery;
use PAMI\Message\Event\DialEvent;
use PAMI\Message\Event\HangupEvent;
use PAMI\Message\Event\BridgeEnterEvent;
use PAMI\Message\Event\QueueMemberAddedEvent;

class HighVolumeEventProcessingTest extends UnitTestCase
{
    private EventProcessor $eventProcessor;
    private EventGenerator $eventGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventGenerator = new EventGenerator();
        $this->eventProcessor = new EventProcessor();
    }

    /**
     * Test high-volume event processing performance
     * 
     * @group performance
     */
    public function testHighVolumeEventProcessing()
    {
        $eventCount = 1000;
        $maxMemoryUsage = 50 * 1024 * 1024; // 50MB limit
        $maxExecutionTime = 30; // 30 seconds limit
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $events = $this->generateMultipleEvents($eventCount);
        
        $processedEvents = 0;
        $peakMemory = 0;
        
        foreach ($events as $event) {
            $this->eventProcessor->processEvent($event);
            $processedEvents++;
            
            $currentMemory = memory_get_usage(true);
            if ($currentMemory > $peakMemory) {
                $peakMemory = $currentMemory;
            }
            
            // Check memory usage periodically
            if ($processedEvents % 100 === 0) {
                $this->assertLessThan(
                    $maxMemoryUsage, 
                    $currentMemory - $startMemory,
                    "Memory usage exceeded limit after processing {$processedEvents} events"
                );
            }
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $eventsPerSecond = $eventCount / $executionTime;
        
        // Performance assertions
        $this->assertLessThan(
            $maxExecutionTime, 
            $executionTime,
            "Event processing took too long: {$executionTime}s for {$eventCount} events"
        );
        
        $this->assertLessThan(
            $maxMemoryUsage,
            $memoryUsed,
            "Memory usage too high: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB"
        );
        
        $this->assertGreaterThan(
            50, 
            $eventsPerSecond,
            "Event processing rate too slow: {$eventsPerSecond} events/second"
        );
        
        // Log performance metrics
        echo "\n";
        echo "Performance Metrics:\n";
        echo "- Events processed: {$eventCount}\n";
        echo "- Execution time: " . number_format($executionTime, 3) . "s\n";
        echo "- Memory used: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB\n";
        echo "- Peak memory: " . number_format($peakMemory / 1024 / 1024, 2) . "MB\n";
        echo "- Events per second: " . number_format($eventsPerSecond, 2) . "\n";
        echo "- Memory per event: " . number_format($memoryUsed / $eventCount, 0) . " bytes\n";
    }

    /**
     * Test concurrent event processing simulation
     * 
     * @group performance
     */
    public function testConcurrentEventProcessing()
    {
        $batchSize = 100;
        $numberOfBatches = 10;
        $maxMemoryUsage = 30 * 1024 * 1024; // 30MB limit
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $totalProcessed = 0;
        $peakMemory = 0;
        
        for ($batch = 0; $batch < $numberOfBatches; $batch++) {
            $events = $this->generateMultipleEvents($batchSize);
            
            // Simulate concurrent processing by processing events in chunks
            $chunks = array_chunk($events, 10);
            
            foreach ($chunks as $chunk) {
                foreach ($chunk as $event) {
                    $this->eventProcessor->processEvent($event);
                    $totalProcessed++;
                }
                
                $currentMemory = memory_get_usage(true);
                if ($currentMemory > $peakMemory) {
                    $peakMemory = $currentMemory;
                }
                
                // Force garbage collection to simulate real-world memory management
                if ($totalProcessed % 200 === 0) {
                    gc_collect_cycles();
                }
            }
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $eventsPerSecond = $totalProcessed / $executionTime;
        
        // Performance assertions
        $this->assertLessThan(
            $maxMemoryUsage,
            $memoryUsed,
            "Concurrent processing memory usage too high: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB"
        );
        
        $this->assertGreaterThan(
            30, 
            $eventsPerSecond,
            "Concurrent processing rate too slow: {$eventsPerSecond} events/second"
        );
        
        // Log concurrent processing metrics
        echo "\n";
        echo "Concurrent Processing Metrics:\n";
        echo "- Total events processed: {$totalProcessed}\n";
        echo "- Batches: {$numberOfBatches} x {$batchSize} events\n";
        echo "- Execution time: " . number_format($executionTime, 3) . "s\n";
        echo "- Memory used: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB\n";
        echo "- Peak memory: " . number_format($peakMemory / 1024 / 1024, 2) . "MB\n";
        echo "- Events per second: " . number_format($eventsPerSecond, 2) . "\n";
    }

    /**
     * Test memory leak detection during extended processing
     * 
     * @group performance
     */
    public function testMemoryLeakDetection()
    {
        $iterations = 10;
        $eventsPerIteration = 200;
        $memoryGrowthThreshold = 5 * 1024 * 1024; // 5MB max growth
        
        $initialMemory = memory_get_usage(true);
        $memoryReadings = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $events = $this->generateMultipleEvents($eventsPerIteration);
            
            foreach ($events as $event) {
                $this->eventProcessor->processEvent($event);
            }
            
            // Force garbage collection
            gc_collect_cycles();
            
            $currentMemory = memory_get_usage(true);
            $memoryReadings[] = $currentMemory;
            
            // Check for excessive memory growth
            $memoryGrowth = $currentMemory - $initialMemory;
            
            if ($i > 2) { // Allow some initial growth
                $this->assertLessThan(
                    $memoryGrowthThreshold,
                    $memoryGrowth,
                    "Potential memory leak detected at iteration {$i}: " . 
                    number_format($memoryGrowth / 1024 / 1024, 2) . "MB growth"
                );
            }
        }
        
        // Analyze memory trend
        $firstHalf = array_slice($memoryReadings, 0, $iterations / 2);
        $secondHalf = array_slice($memoryReadings, $iterations / 2);
        
        $firstHalfAvg = array_sum($firstHalf) / count($firstHalf);
        $secondHalfAvg = array_sum($secondHalf) / count($secondHalf);
        
        $memoryTrend = $secondHalfAvg - $firstHalfAvg;
        
        echo "\n";
        echo "Memory Leak Detection:\n";
        echo "- Iterations: {$iterations}\n";
        echo "- Events per iteration: {$eventsPerIteration}\n";
        echo "- Initial memory: " . number_format($initialMemory / 1024 / 1024, 2) . "MB\n";
        echo "- Final memory: " . number_format(end($memoryReadings) / 1024 / 1024, 2) . "MB\n";
        echo "- Memory trend: " . number_format($memoryTrend / 1024 / 1024, 2) . "MB\n";
        echo "- Total growth: " . number_format((end($memoryReadings) - $initialMemory) / 1024 / 1024, 2) . "MB\n";
        
        // Assert no significant memory leak
        $this->assertLessThan(
            $memoryGrowthThreshold / 2,
            $memoryTrend,
            "Memory leak detected: " . number_format($memoryTrend / 1024 / 1024, 2) . "MB trend increase"
        );
    }

    /**
     * Test event processing with different event types distribution
     * 
     * @group performance
     */
    public function testMixedEventTypeProcessing()
    {
        $eventCount = 500;
        $maxExecutionTime = 15; // 15 seconds limit
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Generate mixed event types
        $events = $this->generateMixedEvents($eventCount);
        
        $eventTypeStats = [];
        
        foreach ($events as $event) {
            $eventType = get_class($event);
            
            if (!isset($eventTypeStats[$eventType])) {
                $eventTypeStats[$eventType] = ['count' => 0, 'time' => 0];
            }
            
            $eventStart = microtime(true);
            $this->eventProcessor->processEvent($event);
            $eventEnd = microtime(true);
            
            $eventTypeStats[$eventType]['count']++;
            $eventTypeStats[$eventType]['time'] += ($eventEnd - $eventStart);
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $totalTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        $this->assertLessThan(
            $maxExecutionTime,
            $totalTime,
            "Mixed event processing took too long: {$totalTime}s"
        );
        
        echo "\n";
        echo "Mixed Event Type Processing:\n";
        echo "- Total events: {$eventCount}\n";
        echo "- Total time: " . number_format($totalTime, 3) . "s\n";
        echo "- Memory used: " . number_format($memoryUsed / 1024 / 1024, 2) . "MB\n";
        
        foreach ($eventTypeStats as $type => $stats) {
            $avgTime = $stats['time'] / $stats['count'];
            echo "- {$type}: {$stats['count']} events, avg " . 
                 number_format($avgTime * 1000, 2) . "ms each\n";
        }
    }

    /**
     * Generate multiple events for testing
     */
    private function generateMultipleEvents(int $count): array
    {
        $events = [];
        
        for ($i = 0; $i < $count; $i++) {
            // Distribute event types
            $eventType = $i % 4;
            
            switch ($eventType) {
                case 0:
                    $events[] = $this->eventGenerator->createDialEvent();
                    break;
                case 1:
                    $events[] = $this->eventGenerator->createHangupEvent();
                    break;
                case 2:
                    $events[] = $this->eventGenerator->createBridgeEvent();
                    break;
                case 3:
                    $events[] = $this->eventGenerator->createQueueMemberEvent();
                    break;
            }
        }
        
        return $events;
    }

    /**
     * Generate mixed event types with realistic distribution
     */
    private function generateMixedEvents(int $count): array
    {
        $events = [];
        
        for ($i = 0; $i < $count; $i++) {
            // Realistic distribution: more dial/hangup events than others
            $rand = rand(1, 100);
            
            if ($rand <= 40) {
                $events[] = $this->eventGenerator->createDialEvent();
            } elseif ($rand <= 80) {
                $events[] = $this->eventGenerator->createHangupEvent();
            } elseif ($rand <= 90) {
                $events[] = $this->eventGenerator->createBridgeEvent();
            } else {
                $events[] = $this->eventGenerator->createQueueMemberEvent();
            }
        }
        
        return $events;
    }
}