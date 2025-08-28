<?php

/**
 * Advanced Integration Patterns Example
 * 
 * This example demonstrates advanced integration patterns for the Asterisk PBX Manager package
 * including middleware, job queues, broadcasting, service coordination, and complex workflows.
 */

namespace App\Examples;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\QueueManagerService;
use AsteriskPbxManager\Services\EventProcessor;
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use AsteriskPbxManager\Models\CallLog;

/**
 * Example 1: Advanced Middleware Integration
 * 
 * Custom middleware for rate limiting, authentication, and connection health checks
 */
class AsteriskConnectionMiddleware
{
    public function __construct(
        private AsteriskManagerService $asteriskManager
    ) {}

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // Check if Asterisk is connected
        if (!$this->asteriskManager->isConnected()) {
            return response()->json([
                'success' => false,
                'message' => 'Asterisk PBX is not connected',
                'error' => 'Service temporarily unavailable'
            ], 503);
        }

        // Add connection info to request
        $request->merge([
            '_asterisk_connected' => true,
            '_asterisk_status' => $this->getConnectionStatus()
        ]);

        return $next($request);
    }

    private function getConnectionStatus(): array
    {
        return [
            'connected_at' => Cache::get('asterisk_connected_at'),
            'last_ping' => Cache::get('asterisk_last_ping'),
            'connection_health' => 'good'
        ];
    }
}

/**
 * Rate limiting middleware for AMI operations
 */
class AsteriskRateLimitMiddleware
{
    /**
     * Handle rate limiting for AMI operations
     */
    public function handle(Request $request, \Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = 'asterisk_rate_limit:' . $request->ip();
        
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return response()->json([
                'success' => false,
                'message' => 'Too many AMI requests',
                'retry_after' => $decayMinutes * 60
            ], 429);
        }

        Cache::put($key, $attempts + 1, $decayMinutes * 60);

        return $next($request);
    }
}

/**
 * Example 2: Advanced Job Queue Integration
 * 
 * Queued jobs for handling heavy AMI operations and event processing
 */
class ProcessCallAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        private string $callUniqueId,
        private array $callData
    ) {}

    /**
     * Execute the job
     */
    public function handle(AsteriskManagerService $asteriskManager): void
    {
        try {
            Log::info('Processing call analytics', [
                'unique_id' => $this->callUniqueId,
                'call_data' => $this->callData
            ]);

            // Perform complex analytics
            $analytics = $this->performCallAnalytics();

            // Store analytics results
            $this->storeAnalyticsResults($analytics);

            // Trigger follow-up actions
            $this->triggerFollowUpActions($analytics);

        } catch (\Exception $e) {
            Log::error('Call analytics processing failed', [
                'unique_id' => $this->callUniqueId,
                'error' => $e->getMessage()
            ]);
            
            $this->fail($e);
        }
    }

    private function performCallAnalytics(): array
    {
        return [
            'call_quality_score' => $this->calculateQualityScore(),
            'customer_satisfaction_prediction' => $this->predictSatisfaction(),
            'agent_performance_metrics' => $this->calculateAgentMetrics(),
            'cost_analysis' => $this->calculateCosts(),
            'compliance_check' => $this->checkCompliance(),
        ];
    }

    private function calculateQualityScore(): float
    {
        $duration = $this->callData['duration'] ?? 0;
        $holdTime = $this->callData['hold_time'] ?? 0;
        $transferCount = $this->callData['transfers'] ?? 0;

        $score = 100;
        
        // Deduct points for long hold times
        if ($holdTime > 60) {
            $score -= min(($holdTime - 60) / 10, 30);
        }
        
        // Deduct points for multiple transfers
        $score -= $transferCount * 10;
        
        // Bonus for reasonable duration
        if ($duration >= 120 && $duration <= 600) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    private function predictSatisfaction(): string
    {
        $qualityScore = $this->calculateQualityScore();
        
        return match(true) {
            $qualityScore >= 80 => 'high',
            $qualityScore >= 60 => 'medium',
            default => 'low'
        };
    }

    private function calculateAgentMetrics(): array
    {
        return [
            'response_time' => $this->callData['response_time'] ?? 0,
            'resolution_rate' => $this->callData['resolved'] ?? false ? 100 : 0,
            'professionalism_score' => rand(80, 100), // Would be calculated from voice analysis
        ];
    }

    private function calculateCosts(): array
    {
        $duration = $this->callData['duration'] ?? 0;
        $costPerMinute = 0.05; // Example rate

        return [
            'call_cost' => ($duration / 60) * $costPerMinute,
            'agent_cost' => ($duration / 60) * 0.50, // Example agent cost
            'infrastructure_cost' => 0.01,
            'total_cost' => (($duration / 60) * ($costPerMinute + 0.50)) + 0.01
        ];
    }

    private function checkCompliance(): array
    {
        return [
            'recording_enabled' => $this->callData['recorded'] ?? false,
            'consent_obtained' => $this->callData['consent'] ?? false,
            'data_retention_compliant' => true,
            'privacy_policy_followed' => true,
        ];
    }

    private function storeAnalyticsResults(array $analytics): void
    {
        // Store in database or external analytics service
        Cache::put("call_analytics:{$this->callUniqueId}", $analytics, 86400);
    }

    private function triggerFollowUpActions(array $analytics): void
    {
        // Trigger customer satisfaction survey if prediction is low
        if ($analytics['customer_satisfaction_prediction'] === 'low') {
            Queue::push(new SendSatisfactionSurveyJob($this->callUniqueId));
        }

        // Schedule agent training if performance is low
        if ($analytics['agent_performance_metrics']['professionalism_score'] < 70) {
            Queue::push(new ScheduleAgentTrainingJob($this->callData['agent_id'] ?? null));
        }
    }
}

/**
 * Job for bulk queue operations
 */
class BulkQueueOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        private string $operation,
        private array $queues,
        private array $members,
        private array $options = []
    ) {}

    public function handle(QueueManagerService $queueManager): void
    {
        try {
            foreach ($this->queues as $queue) {
                foreach ($this->members as $member) {
                    match($this->operation) {
                        'add' => $queueManager->addMember($queue, $member, $this->options),
                        'remove' => $queueManager->removeMember($queue, $member),
                        'pause' => $queueManager->pauseMember($queue, $member, true, $this->options['reason'] ?? ''),
                        'unpause' => $queueManager->pauseMember($queue, $member, false),
                        default => throw new \InvalidArgumentException("Unknown operation: {$this->operation}")
                    };

                    // Brief pause to avoid overwhelming the AMI
                    usleep(100000); // 0.1 seconds
                }
            }

            Log::info('Bulk queue operation completed', [
                'operation' => $this->operation,
                'queues' => $this->queues,
                'members' => count($this->members)
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk queue operation failed', [
                'operation' => $this->operation,
                'error' => $e->getMessage()
            ]);
            
            $this->fail($e);
        }
    }
}

/**
 * Example 3: Advanced Event Broadcasting
 * 
 * Real-time event broadcasting with enhanced data and filtering
 */
class EnhancedCallEvent implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        private string $eventType,
        private string $uniqueId,
        private array $callData,
        private array $metadata = []
    ) {}

    /**
     * Get the channels the event should broadcast on
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('asterisk.calls'),
            new Channel('asterisk.call.' . $this->uniqueId),
        ];

        // Add queue-specific channels if applicable
        if (isset($this->callData['queue'])) {
            $channels[] = new Channel('asterisk.queue.' . $this->callData['queue']);
        }

        // Add agent-specific channels
        if (isset($this->callData['agent_id'])) {
            $channels[] = new PrivateChannel('asterisk.agent.' . $this->callData['agent_id']);
        }

        return $channels;
    }

    /**
     * Get the event name for broadcasting
     */
    public function broadcastAs(): string
    {
        return 'call.' . $this->eventType;
    }

    /**
     * Get the data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'unique_id' => $this->uniqueId,
            'event_type' => $this->eventType,
            'call_data' => $this->callData,
            'metadata' => array_merge($this->metadata, [
                'timestamp' => now()->toISOString(),
                'server' => config('app.name'),
            ]),
            'analytics' => $this->getCallAnalytics(),
        ];
    }

    private function getCallAnalytics(): array
    {
        return Cache::get("call_analytics:{$this->uniqueId}", []);
    }
}

/**
 * Example 4: Service Coordination and Complex Workflows
 * 
 * Orchestrator for complex multi-service operations
 */
class CallWorkflowOrchestrator
{
    public function __construct(
        private AsteriskManagerService $asteriskManager,
        private QueueManagerService $queueManager,
        private EventProcessor $eventProcessor
    ) {}

    /**
     * Orchestrate a complex call routing workflow
     */
    public function executeSmartRouting(array $callRequest): array
    {
        $workflowId = 'workflow_' . time() . '_' . mt_rand(1000, 9999);
        
        try {
            Log::info('Starting smart routing workflow', [
                'workflow_id' => $workflowId,
                'call_request' => $callRequest
            ]);

            // Step 1: Analyze caller and determine routing strategy
            $routingStrategy = $this->determineRoutingStrategy($callRequest);

            // Step 2: Check agent availability and queue status
            $availableAgents = $this->findAvailableAgents($routingStrategy);

            // Step 3: Apply business rules and priorities
            $finalRouting = $this->applyBusinessRules($routingStrategy, $availableAgents, $callRequest);

            // Step 4: Execute the routing decision
            $executionResult = $this->executeRouting($finalRouting, $callRequest);

            // Step 5: Set up monitoring and fallback procedures
            $this->setupCallMonitoring($executionResult, $workflowId);

            return [
                'success' => true,
                'workflow_id' => $workflowId,
                'routing_decision' => $finalRouting,
                'execution_result' => $executionResult
            ];

        } catch (\Exception $e) {
            Log::error('Smart routing workflow failed', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage()
            ]);

            // Execute fallback routing
            return $this->executeFallbackRouting($callRequest, $workflowId);
        }
    }

    private function determineRoutingStrategy(array $callRequest): array
    {
        $strategy = [
            'type' => 'standard',
            'priority' => 'normal',
            'preferred_queue' => 'general',
            'skill_requirements' => [],
            'language_preference' => 'en',
        ];

        // Analyze caller ID for VIP status
        if ($this->isVipCaller($callRequest['caller_id'] ?? '')) {
            $strategy['type'] = 'vip';
            $strategy['priority'] = 'high';
            $strategy['preferred_queue'] = 'vip_support';
        }

        // Check for emergency numbers
        if ($this->isEmergencyCall($callRequest['destination'] ?? '')) {
            $strategy['type'] = 'emergency';
            $strategy['priority'] = 'critical';
            $strategy['preferred_queue'] = 'emergency';
        }

        // Determine required skills based on call type
        $strategy['skill_requirements'] = $this->determineRequiredSkills($callRequest);

        return $strategy;
    }

    private function findAvailableAgents(array $routingStrategy): array
    {
        $queueStatus = $this->queueManager->getQueueStatus($routingStrategy['preferred_queue']);
        $availableAgents = [];

        if (isset($queueStatus['members'])) {
            foreach ($queueStatus['members'] as $member => $memberData) {
                if (!($memberData['paused'] ?? false) && ($memberData['status'] ?? '') === 'available') {
                    $availableAgents[] = [
                        'member' => $member,
                        'penalty' => $memberData['penalty'] ?? 0,
                        'calls_taken' => $memberData['callstaken'] ?? 0,
                        'skills' => $this->getAgentSkills($member),
                    ];
                }
            }
        }

        // Sort by penalty (lower is better) and calls taken (for load balancing)
        usort($availableAgents, function($a, $b) {
            if ($a['penalty'] === $b['penalty']) {
                return $a['calls_taken'] <=> $b['calls_taken'];
            }
            return $a['penalty'] <=> $b['penalty'];
        });

        return $availableAgents;
    }

    private function applyBusinessRules(array $strategy, array $availableAgents, array $callRequest): array
    {
        $routing = [
            'strategy' => $strategy,
            'selected_agent' => null,
            'fallback_queue' => 'general',
            'timeout' => 30,
            'max_retries' => 3,
        ];

        // Apply priority-based timeout adjustments
        $routing['timeout'] = match($strategy['priority']) {
            'critical' => 10,
            'high' => 20,
            'normal' => 30,
            'low' => 45,
            default => 30
        };

        // Select best available agent
        if (!empty($availableAgents)) {
            foreach ($availableAgents as $agent) {
                if ($this->agentHasRequiredSkills($agent['skills'], $strategy['skill_requirements'])) {
                    $routing['selected_agent'] = $agent;
                    break;
                }
            }

            // If no agent has required skills, select best available
            if (!$routing['selected_agent']) {
                $routing['selected_agent'] = $availableAgents[0];
            }
        }

        return $routing;
    }

    private function executeRouting(array $routing, array $callRequest): array
    {
        $result = [
            'routed' => false,
            'channel' => null,
            'queue' => null,
            'agent' => null,
            'started_at' => now(),
        ];

        try {
            // Direct agent routing if available
            if ($routing['selected_agent']) {
                $channel = $routing['selected_agent']['member'];
                
                $callResult = $this->asteriskManager->originateCall(
                    $callRequest['channel'],
                    $channel,
                    [
                        'Timeout' => $routing['timeout'] * 1000,
                        'Priority' => $routing['strategy']['priority'] === 'critical' ? '1' : '2',
                        'Context' => 'internal',
                    ]
                );

                if ($callResult) {
                    $result['routed'] = true;
                    $result['channel'] = $channel;
                    $result['agent'] = $routing['selected_agent']['member'];
                }
            }

            // Fallback to queue routing
            if (!$result['routed']) {
                $queueResult = $this->routeToQueue($callRequest, $routing);
                $result = array_merge($result, $queueResult);
            }

        } catch (\Exception $e) {
            Log::error('Routing execution failed', [
                'routing' => $routing,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    private function routeToQueue(array $callRequest, array $routing): array
    {
        $queue = $routing['strategy']['preferred_queue'];
        
        // Implementation would depend on specific queue routing logic
        return [
            'routed' => true,
            'queue' => $queue,
            'routing_method' => 'queue',
        ];
    }

    private function setupCallMonitoring(array $executionResult, string $workflowId): void
    {
        if ($executionResult['routed']) {
            // Schedule monitoring job
            Queue::later(
                now()->addSeconds(30),
                new MonitorCallProgressJob($workflowId, $executionResult)
            );

            // Set up event listeners for this specific call
            $this->registerCallEventListeners($workflowId, $executionResult);
        }
    }

    private function registerCallEventListeners(string $workflowId, array $executionResult): void
    {
        Event::listen(CallConnected::class, function (CallConnected $event) use ($workflowId) {
            Log::info('Workflow call connected', [
                'workflow_id' => $workflowId,
                'unique_id' => $event->uniqueId
            ]);
        });

        Event::listen(CallEnded::class, function (CallEnded $event) use ($workflowId) {
            Log::info('Workflow call ended', [
                'workflow_id' => $workflowId,
                'unique_id' => $event->uniqueId,
                'duration' => $event->duration
            ]);

            // Queue analytics processing
            Queue::push(new ProcessCallAnalyticsJob($event->uniqueId, [
                'workflow_id' => $workflowId,
                'duration' => $event->duration,
                'cause' => $event->cause,
            ]));
        });
    }

    private function executeFallbackRouting(array $callRequest, string $workflowId): array
    {
        Log::info('Executing fallback routing', ['workflow_id' => $workflowId]);

        // Simple fallback: route to general queue
        try {
            $result = $this->asteriskManager->originateCall(
                $callRequest['channel'],
                'Queue/general',
                ['Timeout' => 60000, 'Context' => 'default']
            );

            return [
                'success' => $result,
                'workflow_id' => $workflowId,
                'routing_decision' => ['type' => 'fallback', 'queue' => 'general'],
                'execution_result' => ['routed' => $result, 'routing_method' => 'fallback']
            ];

        } catch (\Exception $e) {
            Log::error('Fallback routing failed', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'workflow_id' => $workflowId,
                'error' => 'All routing attempts failed'
            ];
        }
    }

    // Helper methods
    private function isVipCaller(string $callerId): bool
    {
        $vipNumbers = Cache::get('vip_numbers', ['1001', '1002', '9999']);
        return in_array(preg_replace('/[^0-9]/', '', $callerId), $vipNumbers);
    }

    private function isEmergencyCall(string $destination): bool
    {
        return in_array($destination, ['911', '999', '112', 'emergency']);
    }

    private function determineRequiredSkills(array $callRequest): array
    {
        // This would typically analyze the call data to determine required skills
        return ['customer_service', 'phone_support'];
    }

    private function getAgentSkills(string $member): array
    {
        // This would typically fetch from a database or cache
        return Cache::get("agent_skills:{$member}", ['customer_service', 'phone_support']);
    }

    private function agentHasRequiredSkills(array $agentSkills, array $requiredSkills): bool
    {
        return empty(array_diff($requiredSkills, $agentSkills));
    }
}

/**
 * Additional job classes referenced in examples
 */
class SendSatisfactionSurveyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(private string $callUniqueId) {}

    public function handle(): void
    {
        Log::info('Sending satisfaction survey', ['call_id' => $this->callUniqueId]);
        // Implementation would send survey via email, SMS, or call
    }
}

class ScheduleAgentTrainingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(private ?string $agentId) {}

    public function handle(): void
    {
        if ($this->agentId) {
            Log::info('Scheduling agent training', ['agent_id' => $this->agentId]);
            // Implementation would schedule training session
        }
    }
}

class MonitorCallProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        private string $workflowId,
        private array $executionResult
    ) {}

    public function handle(): void
    {
        Log::info('Monitoring call progress', [
            'workflow_id' => $this->workflowId,
            'execution_result' => $this->executionResult
        ]);
        // Implementation would monitor call status and take actions if needed
    }
}

/**
 * Usage Examples:
 * 
 * // Using middleware in routes
 * Route::middleware([AsteriskConnectionMiddleware::class, AsteriskRateLimitMiddleware::class])
 *     ->group(function () {
 *         Route::post('/call/smart-route', function (Request $request) {
 *             $orchestrator = app(CallWorkflowOrchestrator::class);
 *             return $orchestrator->executeSmartRouting($request->all());
 *         });
 *     });
 * 
 * // Dispatching analytics job
 * ProcessCallAnalyticsJob::dispatch($uniqueId, $callData);
 * 
 * // Broadcasting enhanced events
 * event(new EnhancedCallEvent('connected', $uniqueId, $callData, $metadata));
 * 
 * // Bulk queue operations
 * BulkQueueOperationJob::dispatch('add', ['support', 'sales'], ['SIP/1001', 'SIP/1002']);
 */