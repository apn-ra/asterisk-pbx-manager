<?php

declare(strict_types=1);

namespace AsteriskPbxManager\Services;

use AsteriskPbxManager\Models\AuditLog;
use PAMI\Message\Action\ActionMessage;
use PAMI\Message\Response\ResponseMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

/**
 * Service for auditing all AMI actions performed through the Asterisk PBX Manager.
 * 
 * This service provides comprehensive audit logging functionality that tracks:
 * - All AMI actions executed
 * - User context and authentication details
 * - Request/response payloads
 * - Execution timing and success/failure status
 * - IP addresses and session information
 * 
 * The audit logs can be stored in database and/or written to log files
 * based on configuration settings.
 */
class AuditLoggingService
{
    /**
     * @var bool Whether audit logging is enabled
     */
    private bool $enabled;

    /**
     * @var bool Whether to log to database
     */
    private bool $logToDatabase;

    /**
     * @var bool Whether to log to files
     */
    private bool $logToFile;

    /**
     * @var array Additional context data for audit logs
     */
    private array $context = [];

    /**
     * Initialize the audit logging service with configuration.
     */
    public function __construct()
    {
        $this->enabled = Config::get('asterisk-pbx-manager.audit.enabled', false);
        $this->logToDatabase = Config::get('asterisk-pbx-manager.audit.log_to_database', true);
        $this->logToFile = Config::get('asterisk-pbx-manager.audit.log_to_file', true);
    }

    /**
     * Log an AMI action execution with full context.
     *
     * @param ActionMessage $action The AMI action that was executed
     * @param ResponseMessage|null $response The response received (null if action failed)
     * @param float $executionTime Execution time in seconds
     * @param array $additionalContext Additional context data
     * @return void
     */
    public function logAction(
        ActionMessage $action,
        ?ResponseMessage $response = null,
        float $executionTime = 0.0,
        array $additionalContext = []
    ): void {
        if (!$this->enabled) {
            return;
        }

        $auditData = $this->buildAuditData($action, $response, $executionTime, $additionalContext);

        if ($this->logToDatabase) {
            $this->logToDatabase($auditData);
        }

        if ($this->logToFile) {
            $this->logToFile($auditData);
        }
    }

    /**
     * Log a connection event (connect/disconnect).
     *
     * @param string $event The connection event type
     * @param bool $success Whether the event was successful
     * @param array $additionalContext Additional context data
     * @return void
     */
    public function logConnection(string $event, bool $success, array $additionalContext = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $auditData = [
            'action_type' => 'connection',
            'action_name' => $event,
            'user_id' => $this->getCurrentUserId(),
            'user_name' => $this->getCurrentUserName(),
            'ip_address' => $this->getClientIpAddress(),
            'user_agent' => $this->getUserAgent(),
            'session_id' => $this->getSessionId(),
            'timestamp' => Carbon::now(),
            'success' => $success,
            'execution_time' => 0.0,
            'request_data' => null,
            'response_data' => null,
            'additional_context' => array_merge($this->context, $additionalContext),
        ];

        if ($this->logToDatabase) {
            $this->logToDatabase($auditData);
        }

        if ($this->logToFile) {
            $this->logToFile($auditData);
        }
    }

    /**
     * Set additional context data for all subsequent audit logs.
     *
     * @param array $context Context data to merge with existing context
     * @return self
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Clear the additional context data.
     *
     * @return self
     */
    public function clearContext(): self
    {
        $this->context = [];
        return $this;
    }

    /**
     * Check if audit logging is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Build comprehensive audit data from action and response.
     *
     * @param ActionMessage $action The AMI action
     * @param ResponseMessage|null $response The AMI response
     * @param float $executionTime Execution time in seconds
     * @param array $additionalContext Additional context data
     * @return array
     */
    private function buildAuditData(
        ActionMessage $action,
        ?ResponseMessage $response,
        float $executionTime,
        array $additionalContext
    ): array {
        return [
            'action_type' => 'ami_action',
            'action_name' => $action->getAction(),
            'user_id' => $this->getCurrentUserId(),
            'user_name' => $this->getCurrentUserName(),
            'ip_address' => $this->getClientIpAddress(),
            'user_agent' => $this->getUserAgent(),
            'session_id' => $this->getSessionId(),
            'timestamp' => Carbon::now(),
            'success' => $response !== null && $response->isSuccess(),
            'execution_time' => $executionTime,
            'request_data' => $this->sanitizeActionData($action),
            'response_data' => $response ? $this->sanitizeResponseData($response) : null,
            'additional_context' => array_merge($this->context, $additionalContext),
        ];
    }

    /**
     * Log audit data to database.
     *
     * @param array $auditData The audit data to log
     * @return void
     */
    private function logToDatabase(array $auditData): void
    {
        try {
            AuditLog::create($auditData);
        } catch (\Exception $e) {
            // Fallback to file logging if database logging fails
            Log::error('Failed to write audit log to database', [
                'error' => $e->getMessage(),
                'audit_data' => $auditData,
            ]);
        }
    }

    /**
     * Log audit data to file.
     *
     * @param array $auditData The audit data to log
     * @return void
     */
    private function logToFile(array $auditData): void
    {
        $logLevel = $auditData['success'] ? 'info' : 'warning';
        
        Log::channel('single')->log($logLevel, 'AMI Action Audit', [
            'audit_type' => 'asterisk_ami',
            'action' => $auditData['action_name'],
            'user' => $auditData['user_name'] ?: 'anonymous',
            'success' => $auditData['success'],
            'execution_time' => $auditData['execution_time'],
            'ip_address' => $auditData['ip_address'],
            'timestamp' => $auditData['timestamp']->toISOString(),
            'context' => $auditData['additional_context'],
        ]);
    }

    /**
     * Sanitize action data for logging (remove sensitive information).
     *
     * @param ActionMessage $action The AMI action
     * @return array
     */
    private function sanitizeActionData(ActionMessage $action): array
    {
        $data = $action->getVariables();
        $sensitiveKeys = ['secret', 'password', 'authsecret', 'md5secret'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[REDACTED]';
            }
        }
        
        return [
            'action' => $action->getAction(),
            'variables' => $data,
            'action_id' => $action->getActionId(),
        ];
    }

    /**
     * Sanitize response data for logging (remove sensitive information).
     *
     * @param ResponseMessage $response The AMI response
     * @return array
     */
    private function sanitizeResponseData(ResponseMessage $response): array
    {
        $data = $response->getKeys();
        $sensitiveKeys = ['secret', 'password', 'authsecret', 'md5secret'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[REDACTED]';
            }
        }
        
        return [
            'response' => $response->getResponse(),
            'message' => $response->getMessage(),
            'keys' => $data,
            'action_id' => $response->getActionId(),
        ];
    }

    /**
     * Get the current authenticated user ID.
     *
     * @return int|null
     */
    private function getCurrentUserId(): ?int
    {
        return Auth::check() ? Auth::id() : null;
    }

    /**
     * Get the current authenticated user name.
     *
     * @return string|null
     */
    private function getCurrentUserName(): ?string
    {
        return Auth::check() ? Auth::user()->name ?? Auth::user()->email ?? 'authenticated_user' : null;
    }

    /**
     * Get the client IP address.
     *
     * @return string|null
     */
    private function getClientIpAddress(): ?string
    {
        if (app()->runningInConsole()) {
            return 'console';
        }

        return request()->ip();
    }

    /**
     * Get the user agent string.
     *
     * @return string|null
     */
    private function getUserAgent(): ?string
    {
        if (app()->runningInConsole()) {
            return 'console';
        }

        return request()->header('User-Agent');
    }

    /**
     * Get the session ID.
     *
     * @return string|null
     */
    private function getSessionId(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return session()->getId();
    }
}