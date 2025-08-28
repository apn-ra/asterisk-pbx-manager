<?php

/**
 * Laravel Controller Integration Examples
 * 
 * These examples demonstrate how to integrate the Asterisk PBX Manager package
 * into Laravel controllers for web applications and APIs.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\QueueManagerService;
use AsteriskPbxManager\Services\EventProcessor;
use AsteriskPbxManager\Models\CallLog;
use AsteriskPbxManager\Models\AsteriskEvent;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;

/**
 * Example 1: Call Management Controller
 * 
 * This controller handles call-related operations like originating calls,
 * hanging up calls, and retrieving call history.
 */
class CallController extends Controller
{
    public function __construct(
        private AsteriskManagerService $asteriskManager
    ) {}

    /**
     * Originate a new call
     */
    public function originateCall(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|regex:/^(SIP|IAX2|DAHDI|Local)\/[\w\-@]+$/',
            'extension' => 'required|string|max:20',
            'context' => 'nullable|string|max:50',
            'priority' => 'nullable|integer|min:1',
            'timeout' => 'nullable|integer|min:1000|max:120000',
            'caller_id' => 'nullable|string|max:100',
        ]);

        try {
            $options = array_filter([
                'Context' => $request->input('context', 'default'),
                'Priority' => $request->input('priority', 1),
                'Timeout' => $request->input('timeout', 30000),
                'CallerID' => $request->input('caller_id'),
            ]);

            $result = $this->asteriskManager->originateCall(
                $request->input('channel'),
                $request->input('extension'),
                $options
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Call originated successfully',
                    'data' => [
                        'channel' => $request->input('channel'),
                        'extension' => $request->input('extension'),
                        'initiated_at' => now()->toISOString(),
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to originate call',
                'error' => 'AMI action returned failure'
            ], 422);

        } catch (AsteriskConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection to Asterisk failed',
                'error' => 'Please check Asterisk server status'
            ], 503);

        } catch (ActionExecutionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute call action',
                'error' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            logger()->error('Call origination failed', [
                'channel' => $request->input('channel'),
                'extension' => $request->input('extension'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Hangup an active call
     */
    public function hangupCall(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'required|string|max:100',
            'cause' => 'nullable|string|max:50',
        ]);

        try {
            $result = $this->asteriskManager->hangupCall(
                $request->input('channel'),
                $request->input('cause', 'Normal Clearing')
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Call hung up successfully',
                    'data' => [
                        'channel' => $request->input('channel'),
                        'cause' => $request->input('cause', 'Normal Clearing'),
                        'hung_up_at' => now()->toISOString(),
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to hang up call',
                'error' => 'Channel may not exist or already disconnected'
            ], 422);

        } catch (ActionExecutionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute hangup action',
                'error' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            logger()->error('Call hangup failed', [
                'channel' => $request->input('channel'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get call history with pagination and filtering
     */
    public function getCallHistory(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'caller_id' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:connected,completed,failed',
        ]);

        try {
            $query = CallLog::query();

            // Apply filters
            if ($request->filled('from_date')) {
                $query->where('connected_at', '>=', $request->input('from_date'));
            }

            if ($request->filled('to_date')) {
                $query->where('connected_at', '<=', $request->input('to_date'));
            }

            if ($request->filled('caller_id')) {
                $query->where('caller_id', 'like', '%' . $request->input('caller_id') . '%');
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Order by most recent
            $query->orderBy('connected_at', 'desc');

            // Paginate results
            $calls = $query->paginate(
                $request->input('per_page', 15),
                ['*'],
                'page',
                $request->input('page', 1)
            );

            return response()->json([
                'success' => true,
                'data' => $calls->items(),
                'pagination' => [
                    'current_page' => $calls->currentPage(),
                    'per_page' => $calls->perPage(),
                    'total' => $calls->total(),
                    'last_page' => $calls->lastPage(),
                ],
                'filters_applied' => $request->only(['from_date', 'to_date', 'caller_id', 'status']),
            ]);

        } catch (\Exception $e) {
            logger()->error('Failed to retrieve call history', [
                'filters' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve call history'
            ], 500);
        }
    }

    /**
     * Get call statistics dashboard
     */
    public function getCallStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:today,week,month,year',
            'timezone' => 'nullable|string|max:50',
        ]);

        try {
            $period = $request->input('period', 'today');
            $timezone = $request->input('timezone', config('app.timezone'));

            $dateRange = $this->getDateRange($period, $timezone);

            $stats = [
                'total_calls' => CallLog::whereBetween('connected_at', $dateRange)->count(),
                'completed_calls' => CallLog::whereBetween('connected_at', $dateRange)
                    ->where('status', 'completed')->count(),
                'failed_calls' => CallLog::whereBetween('connected_at', $dateRange)
                    ->where('status', 'failed')->count(),
                'average_duration' => CallLog::whereBetween('connected_at', $dateRange)
                    ->where('status', 'completed')
                    ->avg('duration') ?? 0,
                'total_duration' => CallLog::whereBetween('connected_at', $dateRange)
                    ->where('status', 'completed')
                    ->sum('duration') ?? 0,
            ];

            // Calculate additional metrics
            $stats['completion_rate'] = $stats['total_calls'] > 0 
                ? round(($stats['completed_calls'] / $stats['total_calls']) * 100, 2)
                : 0;

            $stats['failure_rate'] = $stats['total_calls'] > 0 
                ? round(($stats['failed_calls'] / $stats['total_calls']) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => $period,
                'date_range' => [
                    'from' => $dateRange[0]->toISOString(),
                    'to' => $dateRange[1]->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            logger()->error('Failed to generate call statistics', [
                'period' => $request->input('period'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate statistics'
            ], 500);
        }
    }

    /**
     * Helper method to get date range for statistics
     */
    private function getDateRange(string $period, string $timezone): array
    {
        $now = now($timezone);

        return match($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'week' => [$now->startOfWeek(), $now->endOfWeek()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            'year' => [$now->startOfYear(), $now->endOfYear()],
            default => [$now->startOfDay(), $now->endOfDay()],
        };
    }
}

/**
 * Example 2: Queue Management Controller
 * 
 * This controller handles queue-related operations like adding/removing agents,
 * monitoring queue status, and managing queue configuration.
 */
class QueueController extends Controller
{
    public function __construct(
        private QueueManagerService $queueManager,
        private AsteriskManagerService $asteriskManager
    ) {}

    /**
     * Add an agent to a queue
     */
    public function addQueueMember(Request $request): JsonResponse
    {
        $request->validate([
            'queue' => 'required|string|max:50',
            'member' => 'required|string|regex:/^(SIP|IAX2|DAHDI|Local)\/[\w\-@]+$/',
            'member_name' => 'nullable|string|max:100',
            'penalty' => 'nullable|integer|min:0|max:999',
            'paused' => 'nullable|boolean',
        ]);

        try {
            $options = [
                'penalty' => $request->input('penalty', 0),
                'paused' => $request->boolean('paused', false),
                'membername' => $request->input('member_name', ''),
            ];

            $result = $this->queueManager->addMember(
                $request->input('queue'),
                $request->input('member'),
                $options
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Queue member added successfully',
                    'data' => [
                        'queue' => $request->input('queue'),
                        'member' => $request->input('member'),
                        'member_name' => $request->input('member_name'),
                        'penalty' => $options['penalty'],
                        'paused' => $options['paused'],
                        'added_at' => now()->toISOString(),
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to add queue member',
                'error' => 'Member may already exist in queue'
            ], 422);

        } catch (ActionExecutionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute queue action',
                'error' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            logger()->error('Queue member addition failed', [
                'queue' => $request->input('queue'),
                'member' => $request->input('member'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove an agent from a queue
     */
    public function removeQueueMember(Request $request): JsonResponse
    {
        $request->validate([
            'queue' => 'required|string|max:50',
            'member' => 'required|string|max:100',
        ]);

        try {
            $result = $this->queueManager->removeMember(
                $request->input('queue'),
                $request->input('member')
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Queue member removed successfully',
                    'data' => [
                        'queue' => $request->input('queue'),
                        'member' => $request->input('member'),
                        'removed_at' => now()->toISOString(),
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove queue member',
                'error' => 'Member may not exist in queue'
            ], 422);

        } catch (ActionExecutionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute queue action',
                'error' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            logger()->error('Queue member removal failed', [
                'queue' => $request->input('queue'),
                'member' => $request->input('member'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Pause or unpause a queue member
     */
    public function pauseQueueMember(Request $request): JsonResponse
    {
        $request->validate([
            'queue' => 'required|string|max:50',
            'member' => 'required|string|max:100',
            'paused' => 'required|boolean',
            'reason' => 'nullable|string|max:200',
        ]);

        try {
            $result = $this->queueManager->pauseMember(
                $request->input('queue'),
                $request->input('member'),
                $request->boolean('paused'),
                $request->input('reason', '')
            );

            if ($result) {
                $action = $request->boolean('paused') ? 'paused' : 'unpaused';
                
                return response()->json([
                    'success' => true,
                    'message' => "Queue member {$action} successfully",
                    'data' => [
                        'queue' => $request->input('queue'),
                        'member' => $request->input('member'),
                        'paused' => $request->boolean('paused'),
                        'reason' => $request->input('reason'),
                        'updated_at' => now()->toISOString(),
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update queue member status',
                'error' => 'Member may not exist in queue'
            ], 422);

        } catch (ActionExecutionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute queue action',
                'error' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            logger()->error('Queue member pause/unpause failed', [
                'queue' => $request->input('queue'),
                'member' => $request->input('member'),
                'paused' => $request->boolean('paused'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get queue status and statistics
     */
    public function getQueueStatus(Request $request, string $queue = null): JsonResponse
    {
        try {
            $status = $this->queueManager->getQueueStatus($queue);

            if ($status) {
                return response()->json([
                    'success' => true,
                    'data' => $status,
                    'queue' => $queue ?: 'all',
                    'retrieved_at' => now()->toISOString(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve queue status',
                'error' => $queue ? "Queue '{$queue}' may not exist" : 'No queues found'
            ], 404);

        } catch (\Exception $e) {
            logger()->error('Queue status retrieval failed', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve queue status'
            ], 500);
        }
    }

    /**
     * Get queue performance dashboard
     */
    public function getQueueDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'queues' => 'nullable|array',
            'queues.*' => 'string|max:50',
            'metrics' => 'nullable|array',
            'metrics.*' => 'string|in:calls,agents,performance,efficiency',
        ]);

        try {
            $queues = $request->input('queues', []);
            $metrics = $request->input('metrics', ['calls', 'agents', 'performance']);

            $dashboard = [];

            // Get status for all queues or specified queues
            $queueStatus = $this->queueManager->getQueueStatus();

            if (!$queueStatus || !isset($queueStatus['queues'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No queue data available'
                ], 404);
            }

            foreach ($queueStatus['queues'] as $queueName => $queueData) {
                // Filter by specified queues if provided
                if (!empty($queues) && !in_array($queueName, $queues)) {
                    continue;
                }

                $queueDashboard = ['name' => $queueName];

                // Add requested metrics
                if (in_array('calls', $metrics)) {
                    $queueDashboard['calls'] = [
                        'waiting' => $queueData['calls'] ?? 0,
                        'completed' => $queueData['completed'] ?? 0,
                        'abandoned' => $queueData['abandoned'] ?? 0,
                        'hold_time' => $queueData['holdtime'] ?? 0,
                    ];
                }

                if (in_array('agents', $metrics)) {
                    $members = $queueData['members'] ?? [];
                    $available = count(array_filter($members, fn($m) => !($m['paused'] ?? false)));
                    
                    $queueDashboard['agents'] = [
                        'total' => count($members),
                        'available' => $available,
                        'paused' => count($members) - $available,
                    ];
                }

                if (in_array('performance', $metrics)) {
                    $queueDashboard['performance'] = [
                        'service_level' => $queueData['servicelevel'] ?? 0,
                        'talk_time' => $queueData['talktime'] ?? 0,
                        'strategy' => $queueData['strategy'] ?? 'unknown',
                    ];
                }

                $dashboard[] = $queueDashboard;
            }

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'metrics_included' => $metrics,
                'total_queues' => count($dashboard),
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            logger()->error('Queue dashboard generation failed', [
                'queues' => $request->input('queues'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate queue dashboard'
            ], 500);
        }
    }
}

/**
 * Example 3: System Status and Health Controller
 * 
 * This controller provides system monitoring and health check endpoints.
 */
class SystemController extends Controller
{
    public function __construct(
        private AsteriskManagerService $asteriskManager
    ) {}

    /**
     * Check Asterisk system health
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $health = [
                'asterisk_connection' => false,
                'ami_responsive' => false,
                'system_status' => null,
                'timestamp' => now()->toISOString(),
            ];

            // Check connection
            if ($this->asteriskManager->isConnected()) {
                $health['asterisk_connection'] = true;

                // Test AMI responsiveness with ping
                if ($this->asteriskManager->ping()) {
                    $health['ami_responsive'] = true;
                    
                    // Get system status
                    $status = $this->asteriskManager->getStatus();
                    if ($status) {
                        $health['system_status'] = $status;
                    }
                }
            }

            $overallHealth = $health['asterisk_connection'] && $health['ami_responsive'];

            return response()->json([
                'success' => true,
                'healthy' => $overallHealth,
                'checks' => $health,
            ], $overallHealth ? 200 : 503);

        } catch (\Exception $e) {
            logger()->error('Health check failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'healthy' => false,
                'error' => 'Health check failed',
                'timestamp' => now()->toISOString(),
            ], 503);
        }
    }

    /**
     * Get system information and statistics
     */
    public function getSystemInfo(): JsonResponse
    {
        try {
            $info = [
                'connection_status' => $this->asteriskManager->isConnected(),
                'package_version' => '1.0.0', // This would come from package config
                'server_info' => null,
                'recent_events' => [],
                'database_stats' => [],
            ];

            if ($info['connection_status']) {
                // Get server information
                $info['server_info'] = $this->asteriskManager->getStatus();

                // Get recent events from database
                $info['recent_events'] = AsteriskEvent::latest()
                    ->take(10)
                    ->get(['event_name', 'timestamp', 'server'])
                    ->toArray();

                // Get database statistics
                $info['database_stats'] = [
                    'total_call_logs' => CallLog::count(),
                    'total_events' => AsteriskEvent::count(),
                    'calls_today' => CallLog::whereDate('connected_at', today())->count(),
                    'events_today' => AsteriskEvent::whereDate('timestamp', today())->count(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $info,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            logger()->error('System info retrieval failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system information'
            ], 500);
        }
    }
}

/**
 * Example Routes (routes/api.php)
 * 
 * Route::prefix('asterisk')->middleware(['auth:sanctum'])->group(function () {
 *     // Call management routes
 *     Route::post('calls/originate', [CallController::class, 'originateCall']);
 *     Route::post('calls/hangup', [CallController::class, 'hangupCall']);
 *     Route::get('calls/history', [CallController::class, 'getCallHistory']);
 *     Route::get('calls/statistics', [CallController::class, 'getCallStatistics']);
 *     
 *     // Queue management routes
 *     Route::post('queues/members', [QueueController::class, 'addQueueMember']);
 *     Route::delete('queues/members', [QueueController::class, 'removeQueueMember']);
 *     Route::patch('queues/members/pause', [QueueController::class, 'pauseQueueMember']);
 *     Route::get('queues/{queue?}/status', [QueueController::class, 'getQueueStatus']);
 *     Route::get('queues/dashboard', [QueueController::class, 'getQueueDashboard']);
 *     
 *     // System monitoring routes
 *     Route::get('health', [SystemController::class, 'healthCheck']);
 *     Route::get('system/info', [SystemController::class, 'getSystemInfo']);
 * });
 */