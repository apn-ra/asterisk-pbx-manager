<?php

/**
 * Queue Management Operations Example
 * 
 * This example demonstrates queue management operations using the Asterisk PBX Manager package.
 * It shows how to add/remove queue members, pause/unpause agents, and monitor queue status.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AsteriskPbxManager\Services\QueueManagerService;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use Illuminate\Support\Facades\Log;

class QueueManagementExample
{
    private QueueManagerService $queueManager;
    private AsteriskManagerService $asteriskManager;

    public function __construct(
        QueueManagerService $queueManager,
        AsteriskManagerService $asteriskManager
    ) {
        $this->queueManager = $queueManager;
        $this->asteriskManager = $asteriskManager;
    }

    /**
     * Example 1: Add a member to a queue
     */
    public function addQueueMember(string $queue, string $member, array $options = []): bool
    {
        try {
            echo "👤 Adding member {$member} to queue {$queue}\n";

            $defaultOptions = [
                'penalty' => 0,
                'paused' => false,
                'membername' => '',
            ];

            $memberOptions = array_merge($defaultOptions, $options);

            $result = $this->queueManager->addMember($queue, $member, $memberOptions);

            if ($result) {
                echo "✅ Member added successfully\n";
                if (!empty($memberOptions['membername'])) {
                    echo "   Member name: {$memberOptions['membername']}\n";
                }
                echo "   Penalty: {$memberOptions['penalty']}\n";
                echo "   Paused: " . ($memberOptions['paused'] ? 'Yes' : 'No') . "\n";
                return true;
            } else {
                echo "❌ Failed to add member to queue\n";
                return false;
            }
        } catch (ActionExecutionException $e) {
            echo "❌ Queue add member failed: " . $e->getMessage() . "\n";
            return false;
        } catch (\Exception $e) {
            echo "❌ Unexpected error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Example 2: Remove a member from a queue
     */
    public function removeQueueMember(string $queue, string $member): bool
    {
        try {
            echo "🚪 Removing member {$member} from queue {$queue}\n";
            
            $result = $this->queueManager->removeMember($queue, $member);
            
            if ($result) {
                echo "✅ Member removed successfully\n";
                return true;
            } else {
                echo "❌ Failed to remove member from queue\n";
                return false;
            }
        } catch (ActionExecutionException $e) {
            echo "❌ Queue remove member failed: " . $e->getMessage() . "\n";
            return false;
        } catch (\Exception $e) {
            echo "❌ Unexpected error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Example 3: Pause/Unpause a queue member
     */
    public function pauseQueueMember(string $queue, string $member, bool $paused = true, string $reason = ''): bool
    {
        try {
            $action = $paused ? 'Pausing' : 'Unpausing';
            echo "⏸️ {$action} member {$member} in queue {$queue}\n";
            if ($paused && !empty($reason)) {
                echo "   Reason: {$reason}\n";
            }
            
            $result = $this->queueManager->pauseMember($queue, $member, $paused, $reason);
            
            if ($result) {
                echo "✅ Member " . ($paused ? 'paused' : 'unpaused') . " successfully\n";
                return true;
            } else {
                echo "❌ Failed to " . ($paused ? 'pause' : 'unpause') . " member\n";
                return false;
            }
        } catch (ActionExecutionException $e) {
            echo "❌ Queue pause/unpause failed: " . $e->getMessage() . "\n";
            return false;
        } catch (\Exception $e) {
            echo "❌ Unexpected error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Example 4: Get queue status
     */
    public function getQueueStatus(string $queue = ''): ?array
    {
        try {
            if (empty($queue)) {
                echo "📊 Retrieving status for all queues\n";
            } else {
                echo "📊 Retrieving status for queue: {$queue}\n";
            }
            
            $status = $this->queueManager->getQueueStatus($queue);
            
            if ($status) {
                echo "✅ Queue status retrieved:\n";
                $this->displayQueueStatus($status);
                return $status;
            } else {
                echo "❌ Failed to retrieve queue status\n";
                return null;
            }
        } catch (\Exception $e) {
            echo "❌ Error retrieving queue status: " . $e->getMessage() . "\n";
            return null;
        }
    }

    /**
     * Example 5: Get queue summary
     */
    public function getQueueSummary(string $queue): ?array
    {
        try {
            echo "📈 Getting summary for queue: {$queue}\n";
            
            $summary = $this->queueManager->getQueueSummary($queue);
            
            if ($summary) {
                echo "✅ Queue summary:\n";
                echo "   Queue Name: {$summary['name']}\n";
                echo "   Available Members: {$summary['available']}\n";
                echo "   Busy Members: {$summary['busy']}\n";
                echo "   Unavailable Members: {$summary['unavailable']}\n";
                echo "   Calls Waiting: {$summary['calls']}\n";
                echo "   Longest Hold Time: {$summary['holdtime']} seconds\n";
                return $summary;
            } else {
                echo "❌ Failed to get queue summary\n";
                return null;
            }
        } catch (\Exception $e) {
            echo "❌ Error getting queue summary: " . $e->getMessage() . "\n";
            return null;
        }
    }

    /**
     * Example 6: Bulk queue operations
     */
    public function bulkQueueOperations(): void
    {
        echo "\n🔄 Performing bulk queue operations\n";
        echo "=" . str_repeat("=", 50) . "\n";

        $queues = ['support', 'sales', 'technical'];
        $agents = [
            'SIP/1001' => 'John Doe',
            'SIP/1002' => 'Jane Smith',
            'SIP/1003' => 'Bob Johnson',
        ];

        // Add agents to all queues
        foreach ($queues as $queue) {
            echo "\n📋 Managing queue: {$queue}\n";
            
            foreach ($agents as $agent => $name) {
                $this->addQueueMember($queue, $agent, [
                    'membername' => $name,
                    'penalty' => rand(0, 3),
                ]);
            }
            
            // Get queue status after adding members
            $this->getQueueStatus($queue);
        }

        // Pause some agents
        echo "\n⏸️ Pausing some agents for break\n";
        $this->pauseQueueMember('support', 'SIP/1001', true, 'Lunch break');
        $this->pauseQueueMember('sales', 'SIP/1002', true, 'Training session');

        // Show final status
        echo "\n📊 Final queue status\n";
        $this->getQueueStatus();
    }

    /**
     * Example 7: Queue monitoring workflow
     */
    public function monitorQueueWorkflow(string $queue): void
    {
        echo "\n👀 Starting queue monitoring for: {$queue}\n";
        echo "=" . str_repeat("=", 50) . "\n";

        // Initial status check
        $initialStatus = $this->getQueueStatus($queue);
        
        if (!$initialStatus) {
            echo "❌ Cannot monitor non-existent queue\n";
            return;
        }

        // Monitor for a period (simulated)
        echo "\n⏱️ Monitoring queue for 30 seconds...\n";
        
        for ($i = 1; $i <= 6; $i++) {
            echo "📊 Check #{$i}:\n";
            $this->getQueueSummary($queue);
            
            if ($i < 6) {
                echo "   Waiting 5 seconds...\n";
                sleep(5);
            }
        }

        echo "\n✅ Queue monitoring completed\n";
    }

    /**
     * Helper method to display queue status
     */
    private function displayQueueStatus(array $status): void
    {
        if (isset($status['queues'])) {
            foreach ($status['queues'] as $queueName => $queueData) {
                echo "   Queue: {$queueName}\n";
                echo "     Strategy: " . ($queueData['strategy'] ?? 'unknown') . "\n";
                echo "     Calls: " . ($queueData['calls'] ?? '0') . "\n";
                echo "     Hold time: " . ($queueData['holdtime'] ?? '0') . " seconds\n";
                echo "     Talk time: " . ($queueData['talktime'] ?? '0') . " seconds\n";
                
                if (isset($queueData['members'])) {
                    echo "     Members:\n";
                    foreach ($queueData['members'] as $member => $memberData) {
                        $status = $memberData['paused'] ? 'Paused' : 'Available';
                        $calls = $memberData['callstaken'] ?? 0;
                        echo "       {$member}: {$status} (Calls: {$calls})\n";
                    }
                }
                echo "\n";
            }
        } else {
            foreach ($status as $key => $value) {
                echo "   {$key}: {$value}\n";
            }
        }
    }

    /**
     * Example 8: Advanced queue management
     */
    public function advancedQueueManagement(): void
    {
        echo "\n🚀 Advanced Queue Management Demonstration\n";
        echo "=" . str_repeat("=", 50) . "\n";

        $queue = 'support';
        $agents = [
            'SIP/1001' => ['name' => 'Senior Agent 1', 'penalty' => 1],
            'SIP/1002' => ['name' => 'Senior Agent 2', 'penalty' => 1],
            'SIP/1003' => ['name' => 'Junior Agent 1', 'penalty' => 3],
            'SIP/1004' => ['name' => 'Junior Agent 2', 'penalty' => 3],
        ];

        // Set up queue with priority-based agents
        echo "🏗️ Setting up priority-based queue structure\n";
        foreach ($agents as $agent => $config) {
            $this->addQueueMember($queue, $agent, [
                'membername' => $config['name'],
                'penalty' => $config['penalty'],
            ]);
        }

        // Show initial setup
        echo "\n📊 Initial queue setup:\n";
        $this->getQueueStatus($queue);

        // Simulate agent availability changes
        echo "\n🔄 Simulating agent availability changes:\n";
        
        // Senior agent goes on break
        $this->pauseQueueMember($queue, 'SIP/1001', true, 'Scheduled break');
        
        // Junior agent becomes busy
        $this->pauseQueueMember($queue, 'SIP/1003', true, 'Handling escalated call');

        // Show updated status
        echo "\n📊 Updated queue status:\n";
        $this->getQueueStatus($queue);

        // Resume agents
        echo "\n▶️ Resuming agents:\n";
        $this->pauseQueueMember($queue, 'SIP/1001', false);
        $this->pauseQueueMember($queue, 'SIP/1003', false);

        // Final status
        echo "\n📊 Final queue status:\n";
        $this->getQueueSummary($queue);
    }
}

// Usage example when running as script
if (php_sapi_name() === 'cli') {
    echo "Asterisk PBX Manager - Queue Management Example\n";
    echo "=" . str_repeat("=", 50) . "\n";

    try {
        // Note: In a real Laravel application, these would be injected
        $asteriskManager = app(AsteriskManagerService::class);
        $queueManager = app(QueueManagerService::class);
        
        $example = new QueueManagementExample($queueManager, $asteriskManager);
        
        // Run demonstrations
        $example->bulkQueueOperations();
        $example->monitorQueueWorkflow('support');
        $example->advancedQueueManagement();
        
    } catch (\Exception $e) {
        echo "❌ Failed to initialize example: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Additional queue management utilities
class QueueManagementHelpers
{
    /**
     * Calculate queue efficiency metrics
     */
    public static function calculateEfficiencyMetrics(array $queueData): array
    {
        $totalCalls = $queueData['calls'] ?? 0;
        $totalAgents = count($queueData['members'] ?? []);
        $availableAgents = 0;
        $totalCallsTaken = 0;

        foreach ($queueData['members'] ?? [] as $member => $data) {
            if (!($data['paused'] ?? false)) {
                $availableAgents++;
            }
            $totalCallsTaken += $data['callstaken'] ?? 0;
        }

        return [
            'total_calls' => $totalCalls,
            'total_agents' => $totalAgents,
            'available_agents' => $availableAgents,
            'utilization_rate' => $totalAgents > 0 ? ($availableAgents / $totalAgents) * 100 : 0,
            'calls_per_agent' => $availableAgents > 0 ? $totalCallsTaken / $availableAgents : 0,
            'efficiency_score' => self::calculateEfficiencyScore($queueData),
        ];
    }

    /**
     * Calculate efficiency score based on various metrics
     */
    private static function calculateEfficiencyScore(array $queueData): float
    {
        $holdTime = $queueData['holdtime'] ?? 0;
        $talkTime = $queueData['talktime'] ?? 1; // Avoid division by zero
        $calls = $queueData['calls'] ?? 0;
        
        // Simple efficiency calculation (higher is better)
        $efficiency = ($talkTime / ($holdTime + $talkTime)) * 100;
        
        // Penalty for too many waiting calls
        if ($calls > 5) {
            $efficiency *= 0.8;
        }
        
        return round($efficiency, 2);
    }

    /**
     * Generate queue performance report
     */
    public static function generatePerformanceReport(array $queues): string
    {
        $report = "Queue Performance Report\n";
        $report .= "=" . str_repeat("=", 50) . "\n\n";

        foreach ($queues as $queueName => $queueData) {
            $metrics = self::calculateEfficiencyMetrics($queueData);
            
            $report .= "Queue: {$queueName}\n";
            $report .= "  Total Agents: {$metrics['total_agents']}\n";
            $report .= "  Available Agents: {$metrics['available_agents']}\n";
            $report .= "  Utilization Rate: {$metrics['utilization_rate']}%\n";
            $report .= "  Calls per Agent: " . number_format($metrics['calls_per_agent'], 2) . "\n";
            $report .= "  Efficiency Score: {$metrics['efficiency_score']}%\n";
            $report .= "\n";
        }

        return $report;
    }
}