<?php

namespace AsteriskPbxManager\Http\Controllers;

use AsteriskPbxManager\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Health Check Controller for Asterisk PBX Manager.
 * 
 * Provides HTTP endpoints for monitoring the health and status of the
 * Asterisk PBX Manager system. Suitable for use with load balancers,
 * monitoring systems, and container orchestration platforms.
 * 
 * @package AsteriskPbxManager\Http\Controllers
 * @author Asterisk PBX Manager Team
 */
class HealthCheckController extends Controller
{
    /**
     * Health Check Service instance.
     *
     * @var HealthCheckService
     */
    protected HealthCheckService $healthCheckService;

    /**
     * Create a new Health Check Controller instance.
     *
     * @param HealthCheckService $healthCheckService
     */
    public function __construct(HealthCheckService $healthCheckService)
    {
        $this->healthCheckService = $healthCheckService;
    }

    /**
     * Get comprehensive health check status.
     * 
     * Returns detailed health information for all system components
     * including AMI connection, database, configuration, events, and metrics.
     * 
     * Suitable for detailed monitoring dashboards and diagnostics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function health(Request $request): JsonResponse
    {
        try {
            $useCache = !$request->boolean('no_cache', false);
            $healthData = $this->healthCheckService->performHealthCheck($useCache);
            
            $httpStatus = $healthData['healthy'] ? 200 : 503;
            
            return response()->json($healthData, $httpStatus, [
                'Cache-Control' => $useCache ? 'public, max-age=30' : 'no-cache, no-store',
                'X-Health-Check' => 'asterisk-pbx-manager',
                'X-Health-Status' => $healthData['healthy'] ? 'healthy' : 'unhealthy',
                'X-Health-Timestamp' => $healthData['timestamp'],
            ]);

        } catch (\Exception $e) {
            Log::error('Health check endpoint failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'healthy' => false,
                'status' => 'error',
                'timestamp' => now()->toISOString(),
                'error' => 'Health check system failure',
                'message' => 'Unable to perform health check'
            ], 503, [
                'X-Health-Check' => 'asterisk-pbx-manager',
                'X-Health-Status' => 'error',
                'Cache-Control' => 'no-cache, no-store',
            ]);
        }
    }

    /**
     * Get simple health check status.
     * 
     * Returns a lightweight health check response with minimal overhead.
     * Suitable for load balancer health checks and basic monitoring.
     * 
     * @return JsonResponse
     */
    public function healthz(): JsonResponse
    {
        try {
            $healthData = $this->healthCheckService->getSimpleHealth();
            $httpStatus = $healthData['healthy'] ? 200 : 503;
            
            return response()->json($healthData, $httpStatus, [
                'Cache-Control' => 'public, max-age=10',
                'X-Health-Check' => 'asterisk-pbx-manager-simple',
                'X-Health-Status' => $healthData['status'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'healthy' => false,
                'status' => 'error',
                'timestamp' => now()->toISOString()
            ], 503, [
                'X-Health-Check' => 'asterisk-pbx-manager-simple',
                'X-Health-Status' => 'error',
                'Cache-Control' => 'no-cache, no-store',
            ]);
        }
    }

    /**
     * Get liveness probe status.
     * 
     * Simple endpoint that returns 200 OK if the application is running.
     * Suitable for Kubernetes liveness probes and basic uptime monitoring.
     * 
     * @return JsonResponse
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toISOString(),
            'service' => 'asterisk-pbx-manager'
        ], 200, [
            'Cache-Control' => 'no-cache, no-store',
            'X-Health-Check' => 'liveness',
        ]);
    }

    /**
     * Get readiness probe status.
     * 
     * Returns 200 OK if the service is ready to handle requests.
     * Checks critical dependencies like AMI connection and database.
     * Suitable for Kubernetes readiness probes.
     * 
     * @return JsonResponse
     */
    public function ready(): JsonResponse
    {
        try {
            $healthData = $this->healthCheckService->getSimpleHealth();
            
            // For readiness, we're more strict about what constitutes "ready"
            $isReady = $healthData['healthy'] && $healthData['status'] === 'ok';
            $httpStatus = $isReady ? 200 : 503;
            
            return response()->json([
                'ready' => $isReady,
                'status' => $isReady ? 'ready' : 'not_ready',
                'timestamp' => now()->toISOString(),
                'service' => 'asterisk-pbx-manager'
            ], $httpStatus, [
                'Cache-Control' => 'no-cache, no-store',
                'X-Health-Check' => 'readiness',
                'X-Ready-Status' => $isReady ? 'ready' : 'not_ready',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
                'status' => 'not_ready',
                'timestamp' => now()->toISOString(),
                'error' => 'Readiness check failed'
            ], 503, [
                'Cache-Control' => 'no-cache, no-store',
                'X-Health-Check' => 'readiness',
                'X-Ready-Status' => 'error',
            ]);
        }
    }

    /**
     * Get system status and metrics.
     * 
     * Returns detailed system information including version, uptime,
     * and performance metrics. Suitable for monitoring dashboards.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $useCache = !$request->boolean('no_cache', false);
            $healthData = $this->healthCheckService->performHealthCheck($useCache);
            
            // Extract status information
            $status = [
                'service' => 'asterisk-pbx-manager',
                'version' => $healthData['version'] ?? '1.0.0',
                'timestamp' => $healthData['timestamp'],
                'uptime' => $this->getSystemUptime(),
                'healthy' => $healthData['healthy'],
                'execution_time_ms' => $healthData['execution_time'] ?? 0,
                'checks_summary' => $this->summarizeChecks($healthData['checks'] ?? []),
            ];

            // Add detailed metrics if requested
            if ($request->boolean('detailed', false)) {
                $status['detailed_checks'] = $healthData['checks'] ?? [];
            }

            $httpStatus = $healthData['healthy'] ? 200 : 503;
            
            return response()->json($status, $httpStatus, [
                'Cache-Control' => $useCache ? 'public, max-age=30' : 'no-cache, no-store',
                'X-Health-Check' => 'status',
                'X-Service-Version' => $status['version'],
            ]);

        } catch (\Exception $e) {
            Log::error('Status endpoint failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'service' => 'asterisk-pbx-manager',
                'healthy' => false,
                'timestamp' => now()->toISOString(),
                'error' => 'Status check failed'
            ], 503, [
                'Cache-Control' => 'no-cache, no-store',
                'X-Health-Check' => 'status',
            ]);
        }
    }

    /**
     * Clear health check cache.
     * 
     * Endpoint to manually clear health check caches.
     * Useful for forcing fresh health checks after system changes.
     * 
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->healthCheckService->clearCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Health check cache cleared',
                'timestamp' => now()->toISOString()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to clear health check cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear health check cache',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get system uptime information.
     * 
     * @return array Uptime information
     */
    protected function getSystemUptime(): array
    {
        try {
            // Try to get system uptime on Unix-like systems
            if (function_exists('sys_getloadavg') && is_readable('/proc/uptime')) {
                $uptime = file_get_contents('/proc/uptime');
                $uptimeSeconds = (float) explode(' ', $uptime)[0];
                
                return [
                    'seconds' => $uptimeSeconds,
                    'human' => $this->formatUptime($uptimeSeconds)
                ];
            }
            
            // Fallback: use PHP process start time (approximate)
            $processUptime = time() - $_SERVER['REQUEST_TIME_FLOAT'] ?? time();
            
            return [
                'seconds' => $processUptime,
                'human' => $this->formatUptime($processUptime),
                'note' => 'Approximate uptime based on request time'
            ];

        } catch (\Exception $e) {
            return [
                'seconds' => 0,
                'human' => 'Unknown',
                'error' => 'Could not determine uptime'
            ];
        }
    }

    /**
     * Format uptime seconds into human-readable format.
     * 
     * @param float $seconds
     * @return string
     */
    protected function formatUptime(float $seconds): string
    {
        $units = [
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1
        ];

        $parts = [];
        foreach ($units as $unit => $value) {
            if ($seconds >= $value) {
                $count = floor($seconds / $value);
                $parts[] = $count . ' ' . $unit . ($count > 1 ? 's' : '');
                $seconds %= $value;
            }
        }

        return empty($parts) ? '0 seconds' : implode(', ', $parts);
    }

    /**
     * Summarize health checks into a simple overview.
     * 
     * @param array $checks
     * @return array
     */
    protected function summarizeChecks(array $checks): array
    {
        $summary = [
            'total' => count($checks),
            'healthy' => 0,
            'unhealthy' => 0,
            'warning' => 0
        ];

        foreach ($checks as $check) {
            $status = $check['status'] ?? 'unknown';
            
            switch ($status) {
                case 'healthy':
                    $summary['healthy']++;
                    break;
                case 'unhealthy':
                    $summary['unhealthy']++;
                    break;
                case 'warning':
                    $summary['warning']++;
                    break;
            }
        }

        return $summary;
    }
}