<?php

namespace AsteriskPbxManager\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use AsteriskPbxManager\Models\AmiAuditLog;

/**
 * Service for comprehensive audit logging of all AMI actions.
 * 
 * This service provides detailed audit trails for security, compliance,
 * and debugging purposes, capturing user context, action details,
 * execution results, and security-relevant information.
 * 
 * @package AsteriskPbxManager\Services
 * @author Asterisk PBX Manager Package
 * @since 1.0.0
 */
class AuditLogger
{
    /**
     * Log levels for different types of audit events.
     */
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';  
    const LEVEL_ERROR = 'error';
    const LEVEL_SECURITY = 'security';

    /**
     * Action categories for classification.
     */
    const CATEGORY_CONNECTION = 'connection';
    const CATEGORY_CALL_CONTROL = 'call_control';
    const CATEGORY_QUEUE_MANAGEMENT = 'queue_management';
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_MONITORING = 'monitoring';

    /**
     * Audit configuration.
     *
     * @var array
     */
    protected array $config;

    /**
     * Create a new audit logger instance.
     */
    public function __construct()
    {
        $this->config = config('asterisk-pbx-manager.audit', []);
    }

    /**
     * Log the start of an AMI action execution.
     *
     * @param string $executionId Unique execution identifier
     * @param mixed $action The AMI action object
     * @param array $options Action execution options
     * @param array $context Additional context information
     * @return string Audit log entry ID
     */
    public function logActionStart(string $executionId, $action, array $options = [], array $context = []): string
    {
        $auditData = $this->buildAuditData([
            'execution_id' => $executionId,
            'event_type' => 'action_start',
            'action_class' => get_class($action),
            'action_name' => $this->extractActionName($action),
            'options' => $this->sanitizeOptions($options),
            'user_context' => $this->getUserContext(),
            'system_context' => $this->getSystemContext(),
            'additional_context' => $context,
            'level' => self::LEVEL_INFO,
            'category' => $this->categorizeAction($action),
        ]);

        return $this->writeAuditLog($auditData);
    }

    /**
     * Log the completion of an AMI action execution.
     *
     * @param string $executionId Unique execution identifier
     * @param mixed $action The AMI action object
     * @param array $result Action execution result
     * @param float $executionTimeMs Execution time in milliseconds
     * @return string Audit log entry ID
     */
    public function logActionComplete(string $executionId, $action, array $result, float $executionTimeMs): string
    {
        $level = $result['success'] ? self::LEVEL_INFO : self::LEVEL_WARNING;
        
        $auditData = $this->buildAuditData([
            'execution_id' => $executionId,
            'event_type' => 'action_complete',
            'action_class' => get_class($action),
            'action_name' => $this->extractActionName($action),
            'success' => $result['success'],
            'execution_time_ms' => $executionTimeMs,
            'response_data' => $this->sanitizeResponseData($result),
            'level' => $level,
            'category' => $this->categorizeAction($action),
        ]);

        return $this->writeAuditLog($auditData);
    }

    /**
     * Log an AMI action execution failure.
     *
     * @param string $executionId Unique execution identifier
     * @param mixed $action The AMI action object
     * @param \Exception $exception The exception that occurred
     * @param array $context Additional context information
     * @return string Audit log entry ID
     */
    public function logActionFailure(string $executionId, $action, \Exception $exception, array $context = []): string
    {
        $auditData = $this->buildAuditData([
            'execution_id' => $executionId,
            'event_type' => 'action_failure',
            'action_class' => get_class($action),
            'action_name' => $this->extractActionName($action),
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'exception_class' => get_class($exception),
            'stack_trace' => $this->sanitizeStackTrace($exception->getTraceAsString()),
            'additional_context' => $context,
            'level' => self::LEVEL_ERROR,
            'category' => $this->categorizeAction($action),
        ]);

        return $this->writeAuditLog($auditData);
    }

    /**
     * Log a security-related event.
     *
     * @param string $eventType Type of security event
     * @param array $details Event details
     * @param string $severity Severity level
     * @return string Audit log entry ID
     */
    public function logSecurityEvent(string $eventType, array $details, string $severity = self::LEVEL_SECURITY): string
    {
        $auditData = $this->buildAuditData([
            'event_type' => $eventType,
            'security_details' => $details,
            'user_context' => $this->getUserContext(),
            'system_context' => $this->getSystemContext(),
            'level' => $severity,
            'category' => 'security',
        ]);

        return $this->writeAuditLog($auditData);
    }

    /**
     * Build comprehensive audit data structure.
     *
     * @param array $data Base audit data
     * @return array Complete audit data structure
     */
    protected function buildAuditData(array $data): array
    {
        return array_merge([
            'audit_id' => $this->generateAuditId(),
            'timestamp' => Carbon::now(),
            'session_id' => session()->getId() ?? null,
            'request_id' => request()->header('X-Request-ID') ?? null,
            'ip_address' => request()->ip() ?? null,
            'user_agent' => request()->userAgent() ?? null,
        ], $data);
    }

    /**
     * Get user context information.
     *
     * @return array User context data
     */
    protected function getUserContext(): array
    {
        $user = Auth::user();
        
        if (!$user) {
            return [
                'authenticated' => false,
                'user_id' => null,
                'username' => null,
                'roles' => [],
            ];
        }

        return [
            'authenticated' => true,
            'user_id' => $user->id ?? null,
            'username' => $user->username ?? $user->email ?? null,
            'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [],
            'permissions' => method_exists($user, 'getAllPermissions') ? 
                $user->getAllPermissions()->pluck('name')->toArray() : [],
        ];
    }

    /**
     * Get system context information.
     *
     * @return array System context data
     */
    protected function getSystemContext(): array
    {
        return [
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_name' => $_SERVER['SERVER_NAME'] ?? null,
            'process_id' => getmypid(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Extract action name from action object.
     *
     * @param mixed $action AMI action object
     * @return string Action name
     */
    protected function extractActionName($action): string
    {
        if (method_exists($action, 'getAction')) {
            return $action->getAction();
        }
        
        if (method_exists($action, 'getName')) {
            return $action->getName();
        }
        
        $className = get_class($action);
        return substr($className, strrpos($className, '\\') + 1);
    }

    /**
     * Categorize action for classification.
     *
     * @param mixed $action AMI action object
     * @return string Action category
     */
    protected function categorizeAction($action): string
    {
        $actionName = strtolower($this->extractActionName($action));
        
        $categories = [
            'originate' => self::CATEGORY_CALL_CONTROL,
            'hangup' => self::CATEGORY_CALL_CONTROL,
            'bridge' => self::CATEGORY_CALL_CONTROL,
            'redirect' => self::CATEGORY_CALL_CONTROL,
            'queueadd' => self::CATEGORY_QUEUE_MANAGEMENT,
            'queueremove' => self::CATEGORY_QUEUE_MANAGEMENT,
            'queuepause' => self::CATEGORY_QUEUE_MANAGEMENT,
            'queueunpause' => self::CATEGORY_QUEUE_MANAGEMENT,
            'login' => self::CATEGORY_CONNECTION,
            'logoff' => self::CATEGORY_CONNECTION,
            'ping' => self::CATEGORY_MONITORING,
            'status' => self::CATEGORY_MONITORING,
            'reload' => self::CATEGORY_SYSTEM,
            'command' => self::CATEGORY_SYSTEM,
        ];

        foreach ($categories as $pattern => $category) {
            if (strpos($actionName, $pattern) !== false) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * Sanitize action options for logging.
     *
     * @param array $options Original options
     * @return array Sanitized options
     */
    protected function sanitizeOptions(array $options): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'key', 'auth'];
        $sanitized = $options;

        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize response data for logging.
     *
     * @param array $result Original result data
     * @return array Sanitized result data
     */
    protected function sanitizeResponseData(array $result): array
    {
        // Remove potentially sensitive response data
        $sanitized = $result;
        unset($sanitized['response']); // Remove raw response object
        
        return $sanitized;
    }

    /**
     * Sanitize stack trace for logging.
     *
     * @param string $stackTrace Original stack trace
     * @return string Sanitized stack trace
     */
    protected function sanitizeStackTrace(string $stackTrace): string
    {
        // Remove sensitive file paths and keep only relevant parts
        $lines = explode("\n", $stackTrace);
        $sanitized = [];
        
        foreach (array_slice($lines, 0, 10) as $line) { // Limit to first 10 lines
            $sanitized[] = preg_replace('/\/[^\/]*\/[^\/]*\//', '/.../', $line);
        }
        
        return implode("\n", $sanitized);
    }

    /**
     * Write audit log entry.
     *
     * @param array $auditData Complete audit data
     * @return string Audit log entry ID
     */
    protected function writeAuditLog(array $auditData): string
    {
        $auditId = $auditData['audit_id'];

        try {
            // Log to Laravel log system
            Log::channel($this->config['log_channel'] ?? 'default')->info('AMI Audit Log', $auditData);

            // Store in database if enabled
            if ($this->config['database_logging'] ?? true) {
                $this->storeDatabaseAuditLog($auditData);
            }

            // Send to external audit system if configured
            if ($this->config['external_audit_url'] ?? null) {
                $this->sendExternalAuditLog($auditData);
            }

        } catch (\Exception $e) {
            // Fallback logging if audit logging fails
            Log::error('Audit logging failed', [
                'audit_id' => $auditId,
                'error' => $e->getMessage(),
                'original_data' => $auditData
            ]);
        }

        return $auditId;
    }

    /**
     * Store audit log in database.
     *
     * @param array $auditData Audit data to store
     * @return void
     */
    protected function storeDatabaseAuditLog(array $auditData): void
    {
        try {
            DB::table('ami_audit_logs')->insert([
                'audit_id' => $auditData['audit_id'],
                'execution_id' => $auditData['execution_id'] ?? null,
                'event_type' => $auditData['event_type'],
                'level' => $auditData['level'],
                'category' => $auditData['category'] ?? 'other',
                'user_id' => $auditData['user_context']['user_id'] ?? null,
                'session_id' => $auditData['session_id'],
                'ip_address' => $auditData['ip_address'],
                'action_name' => $auditData['action_name'] ?? null,
                'success' => $auditData['success'] ?? null,
                'execution_time_ms' => $auditData['execution_time_ms'] ?? null,
                'audit_data' => json_encode($auditData),
                'created_at' => $auditData['timestamp'],
                'updated_at' => $auditData['timestamp'],
            ]);
        } catch (\Exception $e) {
            Log::error('Database audit logging failed', [
                'error' => $e->getMessage(),
                'audit_id' => $auditData['audit_id']
            ]);
        }
    }

    /**
     * Send audit log to external system.
     *
     * @param array $auditData Audit data to send
     * @return void
     */
    protected function sendExternalAuditLog(array $auditData): void
    {
        // Implementation for external audit system integration
        // This could use HTTP client to send to SIEM, logging service, etc.
    }

    /**
     * Generate unique audit ID.
     *
     * @return string Unique audit identifier
     */
    protected function generateAuditId(): string
    {
        return 'audit_' . uniqid() . '_' . time();
    }

    /**
     * Check if audit logging is enabled.
     *
     * @return bool True if enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }
}