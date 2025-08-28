<?php

namespace AsteriskPbxManager\Services;

use AsteriskPbxManager\Exceptions\ActionExecutionException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ActionExecutor
{
    protected AsteriskManagerService $asteriskManager;
    protected AuditLogger $auditLogger;
    protected array $actionQueue = [];
    protected array $actionResults = [];
    protected bool $batchMode = false;
    protected int $batchSize = 10;
    protected int $delayBetweenActions = 100; // milliseconds

    /**
     * Create a new action executor instance.
     */
    public function __construct(AsteriskManagerService $asteriskManager, AuditLogger $auditLogger)
    {
        $this->asteriskManager = $asteriskManager;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Execute a single action immediately.
     */
    public function execute($action, array $options = []): array
    {
        $executionId = $this->generateExecutionId();

        // Log action start for audit trail
        if ($this->auditLogger->isEnabled()) {
            $this->auditLogger->logActionStart($executionId, $action, $options);
        }

        try {
            $startTime = microtime(true);

            Log::info('Executing AMI action', [
                'execution_id' => $executionId,
                'action_type'  => get_class($action),
                'options'      => $options,
            ]);

            $response = $this->asteriskManager->send($action);

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

            $result = [
                'execution_id'      => $executionId,
                'success'           => $response->isSuccess(),
                'response'          => $response,
                'execution_time_ms' => round($executionTime, 2),
                'timestamp'         => Carbon::now(),
                'action_type'       => get_class($action),
                'options'           => $options,
            ];

            // Log action completion for audit trail
            if ($this->auditLogger->isEnabled()) {
                $this->auditLogger->logActionComplete($executionId, $action, $result, $result['execution_time_ms']);
            }

            if (!$response->isSuccess()) {
                $result['error'] = $response->getMessage();
                Log::warning('AMI action failed', [
                    'execution_id'      => $executionId,
                    'error'             => $response->getMessage(),
                    'execution_time_ms' => $result['execution_time_ms'],
                ]);
            } else {
                Log::info('AMI action completed successfully', [
                    'execution_id'      => $executionId,
                    'execution_time_ms' => $result['execution_time_ms'],
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            // Log action failure for audit trail
            if ($this->auditLogger->isEnabled()) {
                $this->auditLogger->logActionFailure($executionId, $action, $e);
            }

            $result = [
                'execution_id' => $executionId,
                'success'      => false,
                'error'        => $e->getMessage(),
                'exception'    => get_class($e),
                'timestamp'    => Carbon::now(),
                'action_type'  => get_class($action),
                'options'      => $options,
            ];

            Log::error('AMI action execution failed with exception', [
                'execution_id' => $executionId,
                'error'        => $e->getMessage(),
                'exception'    => get_class($e),
                'trace'        => $e->getTraceAsString(),
            ]);

            throw new ActionExecutionException(
                "Action execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Start batch mode for queuing multiple actions.
     */
    public function startBatch(int $batchSize = 10): self
    {
        $this->batchMode = true;
        $this->batchSize = $batchSize;
        $this->actionQueue = [];
        $this->actionResults = [];

        Log::info('Started batch action mode', [
            'batch_size' => $batchSize,
        ]);

        return $this;
    }

    /**
     * Add an action to the batch queue.
     */
    public function queue($action, array $options = []): self
    {
        if (!$this->batchMode) {
            throw new ActionExecutionException('Not in batch mode. Call startBatch() first.');
        }

        $this->actionQueue[] = [
            'action'    => $action,
            'options'   => $options,
            'queued_at' => Carbon::now(),
        ];

        Log::debug('Action queued for batch execution', [
            'action_type'    => get_class($action),
            'queue_position' => count($this->actionQueue),
            'options'        => $options,
        ]);

        return $this;
    }

    /**
     * Execute all queued actions in batches.
     */
    public function executeBatch(): Collection
    {
        if (!$this->batchMode) {
            throw new ActionExecutionException('Not in batch mode. Call startBatch() first.');
        }

        $batchId = $this->generateBatchId();
        $totalActions = count($this->actionQueue);
        $batches = array_chunk($this->actionQueue, $this->batchSize);
        $results = collect();

        Log::info('Starting batch execution', [
            'batch_id'      => $batchId,
            'total_actions' => $totalActions,
            'batch_size'    => $this->batchSize,
            'total_batches' => count($batches),
        ]);

        $startTime = microtime(true);

        foreach ($batches as $batchIndex => $batch) {
            $batchResults = $this->executeBatchChunk($batch, $batchId, $batchIndex + 1);
            $results = $results->merge($batchResults);

            // Add delay between batches if configured
            if ($this->delayBetweenActions > 0 && $batchIndex < count($batches) - 1) {
                usleep($this->delayBetweenActions * 1000); // Convert ms to microseconds
            }
        }

        $endTime = microtime(true);
        $totalExecutionTime = ($endTime - $startTime) * 1000;

        Log::info('Batch execution completed', [
            'batch_id'                => $batchId,
            'total_actions'           => $totalActions,
            'successful_actions'      => $results->where('success', true)->count(),
            'failed_actions'          => $results->where('success', false)->count(),
            'total_execution_time_ms' => round($totalExecutionTime, 2),
        ]);

        // Reset batch mode
        $this->batchMode = false;
        $this->actionQueue = [];

        return $results;
    }

    /**
     * Execute a chunk of actions in a batch.
     */
    protected function executeBatchChunk(array $chunk, string $batchId, int $chunkNumber): Collection
    {
        $results = collect();

        Log::info('Executing batch chunk', [
            'batch_id'         => $batchId,
            'chunk_number'     => $chunkNumber,
            'actions_in_chunk' => count($chunk),
        ]);

        foreach ($chunk as $index => $queuedAction) {
            try {
                $result = $this->execute($queuedAction['action'], $queuedAction['options']);
                $result['batch_id'] = $batchId;
                $result['chunk_number'] = $chunkNumber;
                $result['chunk_position'] = $index + 1;
                $result['queued_at'] = $queuedAction['queued_at'];

                $results->push($result);
            } catch (\Exception $e) {
                $errorResult = [
                    'execution_id'   => $this->generateExecutionId(),
                    'batch_id'       => $batchId,
                    'chunk_number'   => $chunkNumber,
                    'chunk_position' => $index + 1,
                    'success'        => false,
                    'error'          => $e->getMessage(),
                    'exception'      => get_class($e),
                    'timestamp'      => Carbon::now(),
                    'action_type'    => get_class($queuedAction['action']),
                    'options'        => $queuedAction['options'],
                    'queued_at'      => $queuedAction['queued_at'],
                ];

                $results->push($errorResult);

                Log::error('Batch action failed', [
                    'batch_id'     => $batchId,
                    'chunk_number' => $chunkNumber,
                    'error'        => $e->getMessage(),
                ]);
            }

            // Add small delay between individual actions if configured
            if ($this->delayBetweenActions > 0 && $index < count($chunk) - 1) {
                usleep(($this->delayBetweenActions / 10) * 1000); // 10% of batch delay
            }
        }

        return $results;
    }

    /**
     * Schedule actions for later execution using Laravel queues.
     */
    public function schedule($action, array $options = [], ?Carbon $executeAt = null): string
    {
        $scheduleId = $this->generateScheduleId();

        $jobData = [
            'schedule_id'  => $scheduleId,
            'action'       => serialize($action),
            'options'      => $options,
            'scheduled_at' => $executeAt ?? Carbon::now(),
            'created_at'   => Carbon::now(),
        ];

        if ($executeAt) {
            Queue::laterOn('asterisk-actions', $executeAt, new ExecuteScheduledAction($jobData));
        } else {
            Queue::pushOn('asterisk-actions', new ExecuteScheduledAction($jobData));
        }

        Log::info('Action scheduled for execution', [
            'schedule_id' => $scheduleId,
            'action_type' => get_class($action),
            'execute_at'  => $executeAt ? $executeAt->toISOString() : 'now',
            'options'     => $options,
        ]);

        return $scheduleId;
    }

    /**
     * Aggregate results from multiple executions.
     */
    public function aggregateResults(Collection $results): array
    {
        $aggregation = [
            'total_actions'             => $results->count(),
            'successful_actions'        => $results->where('success', true)->count(),
            'failed_actions'            => $results->where('success', false)->count(),
            'success_rate'              => 0,
            'total_execution_time_ms'   => $results->sum('execution_time_ms'),
            'average_execution_time_ms' => $results->avg('execution_time_ms'),
            'min_execution_time_ms'     => $results->min('execution_time_ms'),
            'max_execution_time_ms'     => $results->max('execution_time_ms'),
            'actions_by_type'           => [],
            'errors_by_type'            => [],
            'execution_timeline'        => [],
        ];

        // Calculate success rate
        if ($aggregation['total_actions'] > 0) {
            $aggregation['success_rate'] = round(
                ($aggregation['successful_actions'] / $aggregation['total_actions']) * 100,
                2
            );
        }

        // Group by action type
        $aggregation['actions_by_type'] = $results->groupBy('action_type')
            ->map(function ($group) {
                return [
                    'count'                     => $group->count(),
                    'successful'                => $group->where('success', true)->count(),
                    'failed'                    => $group->where('success', false)->count(),
                    'average_execution_time_ms' => round($group->avg('execution_time_ms'), 2),
                ];
            })->toArray();

        // Group errors by type
        $failedResults = $results->where('success', false);
        if ($failedResults->count() > 0) {
            $aggregation['errors_by_type'] = $failedResults->groupBy('error')
                ->map->count()
                ->toArray();
        }

        // Create execution timeline (for visualization)
        $aggregation['execution_timeline'] = $results->map(function ($result) {
            return [
                'execution_id'      => $result['execution_id'],
                'timestamp'         => $result['timestamp']->toISOString(),
                'success'           => $result['success'],
                'execution_time_ms' => $result['execution_time_ms'] ?? 0,
                'action_type'       => $result['action_type'],
            ];
        })->toArray();

        return $aggregation;
    }

    /**
     * Set delay between actions in batch mode.
     */
    public function setDelay(int $delayMs): self
    {
        $this->delayBetweenActions = $delayMs;

        return $this;
    }

    /**
     * Get current batch queue status.
     */
    public function getBatchStatus(): array
    {
        return [
            'batch_mode'               => $this->batchMode,
            'queued_actions'           => count($this->actionQueue),
            'batch_size'               => $this->batchSize,
            'delay_between_actions_ms' => $this->delayBetweenActions,
        ];
    }

    /**
     * Clear the action queue.
     */
    public function clearQueue(): self
    {
        $this->actionQueue = [];
        Log::info('Action queue cleared');

        return $this;
    }

    /**
     * Generate unique execution ID.
     */
    protected function generateExecutionId(): string
    {
        return 'exec_'.uniqid().'_'.time();
    }

    /**
     * Generate unique batch ID.
     */
    protected function generateBatchId(): string
    {
        return 'batch_'.uniqid().'_'.time();
    }

    /**
     * Generate unique schedule ID.
     */
    protected function generateScheduleId(): string
    {
        return 'sched_'.uniqid().'_'.time();
    }
}

/**
 * Job class for scheduled action execution.
 */
class ExecuteScheduledAction
{
    public array $jobData;

    public function __construct(array $jobData)
    {
        $this->jobData = $jobData;
    }

    public function handle(ActionExecutor $executor): void
    {
        try {
            $action = unserialize($this->jobData['action']);
            $options = $this->jobData['options'];

            Log::info('Executing scheduled action', [
                'schedule_id' => $this->jobData['schedule_id'],
                'action_type' => get_class($action),
            ]);

            $executor->execute($action, $options);
        } catch (\Exception $e) {
            Log::error('Scheduled action execution failed', [
                'schedule_id' => $this->jobData['schedule_id'],
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Exception $exception): void
    {
        Log::error('Scheduled action job failed', [
            'schedule_id' => $this->jobData['schedule_id'],
            'error'       => $exception->getMessage(),
        ]);
    }
}
