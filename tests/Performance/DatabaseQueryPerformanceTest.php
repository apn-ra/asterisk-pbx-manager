<?php

namespace AsteriskPbxManager\Tests\Performance;

use AsteriskPbxManager\Tests\Integration\IntegrationTestCase;
use AsteriskPbxManager\Models\CallLog;
use AsteriskPbxManager\Models\AsteriskEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DatabaseQueryPerformanceTest extends IntegrationTestCase
{
    private int $testRecordCount = 1000;
    private int $largeBatchSize = 500;
    private array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations to ensure tables exist
        $this->loadMigrationsFrom(__DIR__ . '/../../src/Migrations');
        
        // Seed test data
        $this->seedTestData();
    }

    /**
     * Test call log query performance with various filters
     * 
     * @group performance
     */
    public function testCallLogQueryPerformance()
    {
        $maxQueryTime = 0.5; // 500ms per query limit
        $queryResults = [];
        
        echo "\n";
        echo "Call Log Query Performance Test:\n";
        echo "- Test records: {$this->testRecordCount}\n";
        echo "- Max query time: {$maxQueryTime}s\n";
        
        // Test 1: Simple select all with limit
        $startTime = microtime(true);
        $results = CallLog::limit(100)->get();
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        $queryResults['select_all_limited'] = [
            'time' => $queryTime,
            'count' => $results->count(),
            'query' => 'SELECT * FROM asterisk_call_logs LIMIT 100'
        ];
        
        $this->assertLessThan(
            $maxQueryTime,
            $queryTime,
            "Simple select with limit took too long: {$queryTime}s"
        );
        
        // Test 2: Date range query (should use index)
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        
        $startTime = microtime(true);
        $results = CallLog::whereBetween('started_at', [$startDate, $endDate])->get();
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        $queryResults['date_range_query'] = [
            'time' => $queryTime,
            'count' => $results->count(),
            'query' => 'WHERE started_at BETWEEN ? AND ?'
        ];
        
        $this->assertLessThan(
            $maxQueryTime,
            $queryTime,
            "Date range query took too long: {$queryTime}s"
        );
        
        // Test 3: Channel-based filtering
        $startTime = microtime(true);
        $results = CallLog::where('channel', 'like', 'SIP/%')->limit(50)->get();
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        $queryResults['channel_filter'] = [
            'time' => $queryTime,
            'count' => $results->count(),
            'query' => 'WHERE channel LIKE "SIP/%" LIMIT 50'
        ];
        
        $this->assertLessThan(
            $maxQueryTime,
            $queryTime,
            "Channel filter query took too long: {$queryTime}s"
        );
        
        // Test 4: Status-based aggregation
        $startTime = microtime(true);
        $results = CallLog::select('call_status', DB::raw('COUNT(*) as count'))
            ->groupBy('call_status')
            ->get();
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        $queryResults['status_aggregation'] = [
            'time' => $queryTime,
            'count' => $results->count(),
            'query' => 'GROUP BY call_status with COUNT'
        ];
        
        $this->assertLessThan(
            $maxQueryTime,
            $queryTime,
            "Status aggregation query took too long: {$queryTime}s"
        );
        
        // Test 5: Complex join-like query with subquery
        $startTime = microtime(true);
        $results = CallLog::where('duration', '>', 60)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(25)
            ->get();
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;
        
        $queryResults['complex_filter'] = [
            'time' => $queryTime,
            'count' => $results->count(),
            'query' => 'Complex WHERE + ORDER BY + LIMIT'
        ];
        
        $this->assertLessThan(
            $maxQueryTime * 2, // Allow more time for complex queries
            $queryTime,
            "Complex filter query took too long: {$queryTime}s"
        );
        
        // Log query performance results
        echo "\n";
        echo "Call Log Query Results:\n";
        foreach ($queryResults as $testName => $result) {
            echo "- {$testName}: " . number_format($result['time'] * 1000, 2) . "ms, " .
                 "{$result['count']} records\n";
        }
        
        $avgQueryTime = array_sum(array_column($queryResults, 'time')) / count($queryResults);
        echo "- Average query time: " . number_format($avgQueryTime * 1000, 2) . "ms\n";
        
        $this->performanceMetrics['call_log_queries'] = $queryResults;
    }

    /**
     * Test event insertion performance with bulk operations
     * 
     * @group performance
     */
    public function testEventInsertionPerformance()
    {
        $insertBatchSizes = [10, 50, 100, 250];
        $maxInsertTime = 2.0; // 2 seconds per batch
        $insertResults = [];
        
        echo "\n";
        echo "Event Insertion Performance Test:\n";
        
        foreach ($insertBatchSizes as $batchSize) {
            // Prepare batch data
            $events = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $events[] = [
                    'event_type' => 'Dial',
                    'event_data' => json_encode([
                        'Channel' => 'SIP/1001-' . str_pad($i, 8, '0', STR_PAD_LEFT),
                        'CallerIDNum' => '1001',
                        'CallerIDName' => 'Test User ' . $i,
                        'ConnectedLineNum' => '2002',
                        'Uniqueid' => '1234567890.' . $i,
                        'DialStatus' => 'ANSWER'
                    ]),
                    'processed' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            // Test single insert performance
            $startTime = microtime(true);
            foreach ($events as $eventData) {
                AsteriskEvent::create($eventData);
            }
            $endTime = microtime(true);
            $singleInsertTime = $endTime - $startTime;
            
            // Clean up for bulk insert test
            AsteriskEvent::truncate();
            
            // Test bulk insert performance
            $startTime = microtime(true);
            AsteriskEvent::insert($events);
            $endTime = microtime(true);
            $bulkInsertTime = $endTime - $startTime;
            
            $insertResults[$batchSize] = [
                'single_insert_time' => $singleInsertTime,
                'bulk_insert_time' => $bulkInsertTime,
                'single_per_record' => $singleInsertTime / $batchSize,
                'bulk_per_record' => $bulkInsertTime / $batchSize,
                'performance_gain' => $singleInsertTime / $bulkInsertTime
            ];
            
            // Performance assertions
            $this->assertLessThan(
                $maxInsertTime,
                $singleInsertTime,
                "Single insert for batch size {$batchSize} took too long: {$singleInsertTime}s"
            );
            
            $this->assertLessThan(
                $maxInsertTime / 2, // Bulk should be much faster
                $bulkInsertTime,
                "Bulk insert for batch size {$batchSize} took too long: {$bulkInsertTime}s"
            );
            
            // Assert bulk insert is significantly faster
            $this->assertGreaterThan(
                2.0, // At least 2x faster
                $insertResults[$batchSize]['performance_gain'],
                "Bulk insert not sufficiently faster than single inserts for batch size {$batchSize}"
            );
            
            // Clean up for next test
            AsteriskEvent::truncate();
            
            echo "- Batch size {$batchSize}: Single " . number_format($singleInsertTime * 1000, 2) . "ms, " .
                 "Bulk " . number_format($bulkInsertTime * 1000, 2) . "ms, " .
                 "Gain: " . number_format($insertResults[$batchSize]['performance_gain'], 1) . "x\n";
        }
        
        // Log insertion performance summary
        echo "\n";
        echo "Insertion Performance Summary:\n";
        foreach ($insertResults as $batchSize => $result) {
            echo "- Batch {$batchSize}: " . number_format($result['bulk_per_record'] * 1000, 3) . "ms per record (bulk)\n";
        }
        
        $this->performanceMetrics['event_insertion'] = $insertResults;
    }

    /**
     * Test database indexing effectiveness
     * 
     * @group performance
     */
    public function testDatabaseIndexingEffectiveness()
    {
        $indexTests = [];
        $maxQueryTime = 0.3; // 300ms per indexed query
        
        echo "\n";
        echo "Database Indexing Effectiveness Test:\n";
        echo "- Test records: {$this->testRecordCount}\n";
        
        // Test 1: Primary key lookup (should be fastest)
        $randomId = CallLog::inRandomOrder()->first()->id;
        
        $startTime = microtime(true);
        $result = CallLog::find($randomId);
        $endTime = microtime(true);
        $primaryKeyTime = $endTime - $startTime;
        
        $indexTests['primary_key_lookup'] = [
            'time' => $primaryKeyTime,
            'found' => $result !== null,
            'query' => 'Primary key lookup'
        ];
        
        $this->assertLessThan(
            0.1, // Primary key should be very fast
            $primaryKeyTime,
            "Primary key lookup took too long: {$primaryKeyTime}s"
        );
        
        // Test 2: Created_at index (timestamp queries)
        $testDate = Carbon::now()->subHours(12);
        
        $startTime = microtime(true);
        $results = CallLog::where('created_at', '>=', $testDate)->count();
        $endTime = microtime(true);
        $timestampIndexTime = $endTime - $startTime;
        
        $indexTests['timestamp_index'] = [
            'time' => $timestampIndexTime,
            'count' => $results,
            'query' => 'Timestamp index query'
        ];
        
        $this->assertLessThan(
            $maxQueryTime,
            $timestampIndexTime,
            "Timestamp index query took too long: {$timestampIndexTime}s"
        );
        
        // Test 3: Channel column index (if exists)
        $testChannel = 'SIP/1001';
        
        $startTime = microtime(true);
        $results = CallLog::where('channel', 'like', $testChannel . '%')->count();
        $endTime = microtime(true);
        $channelIndexTime = $endTime - $startTime;
        
        $indexTests['channel_index'] = [
            'time' => $channelIndexTime,
            'count' => $results,
            'query' => 'Channel column query'
        ];
        
        // Test 4: Status column query
        $startTime = microtime(true);
        $results = CallLog::where('status', 'completed')->count();
        $endTime = microtime(true);
        $statusIndexTime = $endTime - $startTime;
        
        $indexTests['status_query'] = [
            'time' => $statusIndexTime,
            'count' => $results,
            'query' => 'Status column query'
        ];
        
        // Test 5: Complex compound query
        $startTime = microtime(true);
        $results = CallLog::where('status', 'completed')
            ->where('duration', '>', 30)
            ->where('created_at', '>=', Carbon::now()->subDays(1))
            ->count();
        $endTime = microtime(true);
        $compoundQueryTime = $endTime - $startTime;
        
        $indexTests['compound_query'] = [
            'time' => $compoundQueryTime,
            'count' => $results,
            'query' => 'Compound query (status + duration + timestamp)'
        ];
        
        $this->assertLessThan(
            $maxQueryTime * 2, // Compound queries can take longer
            $compoundQueryTime,
            "Compound query took too long: {$compoundQueryTime}s"
        );
        
        // Analyze index effectiveness
        $this->analyzeIndexEffectiveness($indexTests);
        
        // Log index test results
        echo "\n";
        echo "Index Effectiveness Results:\n";
        foreach ($indexTests as $testName => $result) {
            echo "- {$testName}: " . number_format($result['time'] * 1000, 2) . "ms";
            if (isset($result['count'])) {
                echo " ({$result['count']} records)";
            }
            echo "\n";
        }
        
        $this->performanceMetrics['index_effectiveness'] = $indexTests;
    }

    /**
     * Test large dataset query performance
     * 
     * @group performance
     */
    public function testLargeDatasetQueryPerformance()
    {
        // Create additional test data for large dataset testing
        $this->seedLargeDataset();
        
        $largeQueryTests = [];
        $maxLargeQueryTime = 2.0; // 2 seconds for large dataset queries
        
        echo "\n";
        echo "Large Dataset Query Performance Test:\n";
        
        // Test 1: Count all records
        $startTime = microtime(true);
        $totalRecords = CallLog::count();
        $endTime = microtime(true);
        $countTime = $endTime - $startTime;
        
        $largeQueryTests['count_all'] = [
            'time' => $countTime,
            'count' => $totalRecords,
            'query' => 'COUNT(*) on full dataset'
        ];
        
        echo "- Total records in dataset: {$totalRecords}\n";
        
        // Test 2: Pagination performance
        $pageSize = 50;
        $pageNumber = 10;
        
        $startTime = microtime(true);
        $results = CallLog::skip(($pageNumber - 1) * $pageSize)
            ->take($pageSize)
            ->orderBy('created_at', 'desc')
            ->get();
        $endTime = microtime(true);
        $paginationTime = $endTime - $startTime;
        
        $largeQueryTests['pagination'] = [
            'time' => $paginationTime,
            'count' => $results->count(),
            'query' => "Pagination (page {$pageNumber}, {$pageSize} per page)"
        ];
        
        $this->assertLessThan(
            $maxLargeQueryTime,
            $paginationTime,
            "Pagination query took too long: {$paginationTime}s"
        );
        
        // Test 3: Large result set with filtering
        $startTime = microtime(true);
        $results = CallLog::where('duration', '>', 0)
            ->orderBy('duration', 'desc')
            ->limit(100)
            ->get();
        $endTime = microtime(true);
        $largeFilterTime = $endTime - $startTime;
        
        $largeQueryTests['large_filter'] = [
            'time' => $largeFilterTime,
            'count' => $results->count(),
            'query' => 'Large filtered result set'
        ];
        
        // Test 4: Aggregation query on large dataset
        $startTime = microtime(true);
        $aggregation = CallLog::selectRaw('
            status,
            COUNT(*) as call_count,
            AVG(duration) as avg_duration,
            MAX(duration) as max_duration,
            MIN(duration) as min_duration
        ')
            ->groupBy('status')
            ->get();
        $endTime = microtime(true);
        $aggregationTime = $endTime - $startTime;
        
        $largeQueryTests['aggregation'] = [
            'time' => $aggregationTime,
            'count' => $aggregation->count(),
            'query' => 'Complex aggregation with GROUP BY'
        ];
        
        $this->assertLessThan(
            $maxLargeQueryTime,
            $aggregationTime,
            "Aggregation query took too long: {$aggregationTime}s"
        );
        
        // Test 5: Date range query on large dataset
        $startTime = microtime(true);
        $results = CallLog::whereBetween('created_at', [
            Carbon::now()->subDays(30),
            Carbon::now()
        ])->count();
        $endTime = microtime(true);
        $dateRangeTime = $endTime - $startTime;
        
        $largeQueryTests['date_range_large'] = [
            'time' => $dateRangeTime,
            'count' => $results,
            'query' => '30-day date range on large dataset'
        ];
        
        // Log large dataset results
        echo "\n";
        echo "Large Dataset Query Results:\n";
        foreach ($largeQueryTests as $testName => $result) {
            echo "- {$testName}: " . number_format($result['time'] * 1000, 2) . "ms";
            if (isset($result['count'])) {
                echo " ({$result['count']} records)";
            }
            echo "\n";
        }
        
        $avgLargeQueryTime = array_sum(array_column($largeQueryTests, 'time')) / count($largeQueryTests);
        echo "- Average large query time: " . number_format($avgLargeQueryTime * 1000, 2) . "ms\n";
        
        $this->performanceMetrics['large_dataset_queries'] = $largeQueryTests;
    }

    /**
     * Test concurrent database operations performance
     * 
     * @group performance
     */
    public function testConcurrentDatabaseOperations()
    {
        $concurrentTests = [];
        $operationCount = 200;
        $maxConcurrentTime = 5.0; // 5 seconds for concurrent operations
        
        echo "\n";
        echo "Concurrent Database Operations Test:\n";
        echo "- Operations: {$operationCount}\n";
        
        // Test 1: Mixed read/write operations
        $startTime = microtime(true);
        
        for ($i = 0; $i < $operationCount; $i++) {
            if ($i % 4 === 0) {
                // Insert operation
                CallLog::create([
                    'channel' => 'SIP/concurrent-' . $i,
                    'caller_id' => '999' . $i,
                    'called_number' => '888' . $i,
                    'call_start' => now(),
                    'call_end' => now()->addMinutes(rand(1, 10)),
                    'duration' => rand(60, 600),
                    'status' => 'completed'
                ]);
            } elseif ($i % 4 === 1) {
                // Read operation
                CallLog::where('status', 'completed')->count();
            } elseif ($i % 4 === 2) {
                // Update operation
                $randomRecord = CallLog::inRandomOrder()->first();
                if ($randomRecord) {
                    $randomRecord->update(['status' => 'updated']);
                }
            } else {
                // Aggregation operation
                CallLog::selectRaw('AVG(duration) as avg_duration')->first();
            }
        }
        
        $endTime = microtime(true);
        $concurrentTime = $endTime - $startTime;
        
        $concurrentTests['mixed_operations'] = [
            'time' => $concurrentTime,
            'operations' => $operationCount,
            'ops_per_second' => $operationCount / $concurrentTime,
            'avg_time_per_op' => $concurrentTime / $operationCount
        ];
        
        $this->assertLessThan(
            $maxConcurrentTime,
            $concurrentTime,
            "Concurrent operations took too long: {$concurrentTime}s"
        );
        
        // Test 2: Batch operations vs individual operations
        $batchData = [];
        for ($i = 0; $i < 50; $i++) {
            $batchData[] = [
                'event_type' => 'BatchTest',
                'event_data' => json_encode(['test' => $i]),
                'processed' => false,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        // Individual inserts
        $startTime = microtime(true);
        foreach ($batchData as $data) {
            AsteriskEvent::create($data);
        }
        $endTime = microtime(true);
        $individualTime = $endTime - $startTime;
        
        // Clean up
        AsteriskEvent::where('event_type', 'BatchTest')->delete();
        
        // Batch insert
        $startTime = microtime(true);
        AsteriskEvent::insert($batchData);
        $endTime = microtime(true);
        $batchTime = $endTime - $startTime;
        
        $concurrentTests['batch_comparison'] = [
            'individual_time' => $individualTime,
            'batch_time' => $batchTime,
            'batch_advantage' => $individualTime / $batchTime,
            'records' => count($batchData)
        ];
        
        // Log concurrent operation results
        echo "\n";
        echo "Concurrent Operations Results:\n";
        echo "- Mixed operations: " . number_format($concurrentTime, 3) . "s total, " .
             number_format($concurrentTests['mixed_operations']['ops_per_second'], 1) . " ops/sec\n";
        echo "- Individual vs Batch: " . number_format($individualTime * 1000, 2) . "ms vs " .
             number_format($batchTime * 1000, 2) . "ms, " .
             number_format($concurrentTests['batch_comparison']['batch_advantage'], 1) . "x faster\n";
        
        $this->performanceMetrics['concurrent_operations'] = $concurrentTests;
    }

    /**
     * Seed initial test data
     */
    private function seedTestData(): void
    {
        // Clear existing data
        CallLog::truncate();
        AsteriskEvent::truncate();
        
        // Create call log test data
        for ($i = 0; $i < $this->testRecordCount; $i++) {
            $startTime = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23));
            $endTime = $startTime->copy()->addSeconds(rand(10, 3600));
            $duration = $endTime->diffInSeconds($startTime);
            
            CallLog::create([
                'channel' => 'SIP/100' . ($i % 10) . '-' . str_pad($i, 8, '0', STR_PAD_LEFT),
                'unique_id' => '1234567890.' . $i,
                'caller_id_num' => '100' . ($i % 10),
                'caller_id_name' => 'Test User ' . ($i % 10),
                'connected_to' => '200' . ($i % 5),
                'connected_name' => 'Dest User ' . ($i % 5),
                'context' => 'default',
                'extension' => '200' . ($i % 5),
                'direction' => ['inbound', 'outbound', 'internal'][rand(0, 2)],
                'call_type' => 'voice',
                'started_at' => $startTime,
                'answered_at' => rand(0, 1) ? $startTime->copy()->addSeconds(rand(1, 10)) : null,
                'ended_at' => $endTime,
                'total_duration' => $duration,
                'talk_duration' => rand(0, 1) ? rand(10, $duration) : 0,
                'ring_duration' => rand(1, 30),
                'call_status' => ['connected', 'missed', 'busy', 'no_answer', 'failed'][rand(0, 4)],
                'hangup_cause' => ['NORMAL_CLEARING', 'USER_BUSY', 'NO_ANSWER'][rand(0, 2)]
            ]);
        }
        
        // Create asterisk event test data
        for ($i = 0; $i < $this->testRecordCount; $i++) {
            AsteriskEvent::create([
                'event_type' => ['Dial', 'Hangup', 'Bridge', 'QueueMemberAdded'][rand(0, 3)],
                'event_data' => json_encode([
                    'Channel' => 'SIP/100' . ($i % 10) . '-' . str_pad($i, 8, '0', STR_PAD_LEFT),
                    'UniqueId' => '1234567890.' . $i,
                    'Event' => 'TestEvent'
                ]),
                'processed' => rand(0, 1) === 1,
                'created_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))
            ]);
        }
    }

    /**
     * Seed large dataset for performance testing
     */
    private function seedLargeDataset(): void
    {
        // Add more records in batches for performance
        $additionalRecords = 2000;
        $batchSize = 100;
        
        for ($batch = 0; $batch < $additionalRecords / $batchSize; $batch++) {
            $batchData = [];
            
            for ($i = 0; $i < $batchSize; $i++) {
                $recordNum = ($batch * $batchSize) + $i + $this->testRecordCount;
                
                $batchData[] = [
                    'channel' => 'SIP/bulk' . ($recordNum % 20),
                    'caller_id' => 'bulk' . ($recordNum % 20),
                    'called_number' => 'dest' . ($recordNum % 10),
                    'call_start' => Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 23)),
                    'call_end' => Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 22)),
                    'duration' => rand(5, 7200),
                    'status' => ['answered', 'busy', 'no_answer', 'completed', 'failed'][rand(0, 4)],
                    'created_at' => Carbon::now()->subDays(rand(0, 60)),
                    'updated_at' => Carbon::now()->subDays(rand(0, 60))
                ];
            }
            
            CallLog::insert($batchData);
        }
    }

    /**
     * Analyze index effectiveness based on query times
     */
    private function analyzeIndexEffectiveness(array $indexTests): void
    {
        $baselineTime = $indexTests['primary_key_lookup']['time'];
        
        echo "\n";
        echo "Index Effectiveness Analysis:\n";
        echo "- Baseline (Primary Key): " . number_format($baselineTime * 1000, 2) . "ms\n";
        
        foreach ($indexTests as $testName => $result) {
            if ($testName === 'primary_key_lookup') continue;
            
            $relativePerformance = $result['time'] / $baselineTime;
            $effectiveness = $relativePerformance < 10 ? 'Good' : ($relativePerformance < 50 ? 'Fair' : 'Poor');
            
            echo "- {$testName}: " . number_format($relativePerformance, 1) . "x slower, " .
                 "Effectiveness: {$effectiveness}\n";
        }
    }

    protected function tearDown(): void
    {
        // Generate performance summary
        $this->generatePerformanceSummary();
        
        parent::tearDown();
    }

    /**
     * Generate comprehensive performance summary
     */
    private function generatePerformanceSummary(): void
    {
        if (empty($this->performanceMetrics)) {
            return;
        }
        
        echo "\n";
        echo "=== DATABASE PERFORMANCE SUMMARY ===\n";
        
        foreach ($this->performanceMetrics as $category => $metrics) {
            echo "\n{$category}:\n";
            
            switch ($category) {
                case 'call_log_queries':
                    $avgTime = array_sum(array_column($metrics, 'time')) / count($metrics);
                    echo "- Average query time: " . number_format($avgTime * 1000, 2) . "ms\n";
                    echo "- Fastest query: " . number_format(min(array_column($metrics, 'time')) * 1000, 2) . "ms\n";
                    echo "- Slowest query: " . number_format(max(array_column($metrics, 'time')) * 1000, 2) . "ms\n";
                    break;
                    
                case 'event_insertion':
                    $bestBulkPerformance = min(array_column($metrics, 'bulk_per_record'));
                    echo "- Best bulk insert performance: " . number_format($bestBulkPerformance * 1000, 3) . "ms per record\n";
                    $avgGain = array_sum(array_column($metrics, 'performance_gain')) / count($metrics);
                    echo "- Average bulk insert advantage: " . number_format($avgGain, 1) . "x\n";
                    break;
                    
                case 'index_effectiveness':
                    $indexedQueries = array_filter($metrics, function($key) {
                        return $key !== 'primary_key_lookup';
                    }, ARRAY_FILTER_USE_KEY);
                    $avgIndexTime = array_sum(array_column($indexedQueries, 'time')) / count($indexedQueries);
                    echo "- Average indexed query time: " . number_format($avgIndexTime * 1000, 2) . "ms\n";
                    break;
            }
        }
        
        echo "\n=== END PERFORMANCE SUMMARY ===\n";
    }
}