<?php

namespace AsteriskPbxManager\Exceptions;

use Illuminate\Support\Facades\Log;

/**
 * Utility class for handling errors securely without information disclosure.
 * 
 * This class provides methods to sanitize error messages and log detailed
 * information securely while presenting generic error messages to end users.
 */
class SecureErrorHandler
{
    /**
     * Error codes for different types of failures.
     */
    public const ERROR_CONNECTION_FAILED = 'AMI_CONNECTION_001';
    public const ERROR_AUTHENTICATION_FAILED = 'AMI_AUTH_002';
    public const ERROR_NETWORK_ERROR = 'AMI_NETWORK_003';
    public const ERROR_INVALID_CONFIG = 'AMI_CONFIG_004';
    public const ERROR_ACTION_FAILED = 'AMI_ACTION_005';
    public const ERROR_ACTION_TIMEOUT = 'AMI_ACTION_006';
    public const ERROR_INVALID_PARAMETER = 'AMI_PARAM_007';
    public const ERROR_MISSING_PARAMETER = 'AMI_PARAM_008';
    public const ERROR_PERMISSION_DENIED = 'AMI_PERM_009';

    /**
     * Generic error messages that don't expose sensitive information.
     */
    private static array $genericMessages = [
        self::ERROR_CONNECTION_FAILED => 'Unable to connect to the telephony system',
        self::ERROR_AUTHENTICATION_FAILED => 'Authentication failed',
        self::ERROR_NETWORK_ERROR => 'Network communication error occurred',
        self::ERROR_INVALID_CONFIG => 'System configuration error',
        self::ERROR_ACTION_FAILED => 'Operation could not be completed',
        self::ERROR_ACTION_TIMEOUT => 'Operation timed out',
        self::ERROR_INVALID_PARAMETER => 'Invalid request parameters',
        self::ERROR_MISSING_PARAMETER => 'Required parameters missing',
        self::ERROR_PERMISSION_DENIED => 'Insufficient permissions for this operation',
    ];

    /**
     * Create a sanitized error message and log detailed information.
     *
     * @param string $errorCode
     * @param string $detailedMessage
     * @param array $context
     * @param string|null $logLevel
     * @return string
     */
    public static function sanitizeError(
        string $errorCode, 
        string $detailedMessage, 
        array $context = [], 
        ?string $logLevel = 'error'
    ): string {
        // Get the generic message for the error code
        $genericMessage = self::$genericMessages[$errorCode] ?? 'An error occurred';
        
        // Log the detailed information securely (only in application logs)
        if ($logLevel) {
            Log::log($logLevel, 'AMI Error: ' . $detailedMessage, [
                'error_code' => $errorCode,
                'context' => self::sanitizeContext($context),
                'timestamp' => now()->toISOString(),
            ]);
        }
        
        // Return only the generic message to prevent information disclosure
        return $genericMessage . ' (Error Code: ' . $errorCode . ')';
    }

    /**
     * Sanitize context data to remove sensitive information.
     *
     * @param array $context
     * @return array
     */
    private static function sanitizeContext(array $context): array
    {
        $sanitized = $context;
        
        // Remove or mask sensitive keys
        $sensitiveKeys = [
            'password', 'secret', 'token', 'key', 'credential',
            'username', 'user', 'login', 'email',
            'host', 'hostname', 'ip', 'address', 'port',
            'path', 'file', 'directory'
        ];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = self::maskSensitiveValue($sanitized[$key]);
            }
        }
        
        // Recursively sanitize nested arrays
        foreach ($sanitized as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeContext($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Mask sensitive values while preserving some information for debugging.
     *
     * @param mixed $value
     * @return string
     */
    private static function maskSensitiveValue($value): string
    {
        if (is_string($value) && strlen($value) > 0) {
            if (strlen($value) <= 3) {
                return str_repeat('*', strlen($value));
            }
            
            // Show first and last character, mask the middle
            return substr($value, 0, 1) . str_repeat('*', strlen($value) - 2) . substr($value, -1);
        }
        
        return '[MASKED]';
    }

    /**
     * Check if the current environment should show detailed errors.
     * Only in development/testing environments with debug enabled.
     *
     * @return bool
     */
    public static function shouldShowDetailedErrors(): bool
    {
        // Handle cases where Laravel's config helper is not available (e.g., unit tests)
        if (!function_exists('config')) {
            return false;
        }
        
        try {
            return config('app.debug', false) && 
                   in_array(config('app.env'), ['local', 'development', 'testing']);
        } catch (\Exception $e) {
            // If config is not available, default to production mode (secure)
            return false;
        }
    }

    /**
     * Generate a secure error message based on environment.
     *
     * @param string $errorCode
     * @param string $detailedMessage
     * @param array $context
     * @return string
     */
    public static function generateSecureMessage(
        string $errorCode, 
        string $detailedMessage, 
        array $context = []
    ): string {
        // Log the detailed error for debugging
        $sanitizedMessage = self::sanitizeError($errorCode, $detailedMessage, $context);
        
        // In production, always return sanitized message
        if (!self::shouldShowDetailedErrors()) {
            return $sanitizedMessage;
        }
        
        // In development, optionally show more details but still sanitized
        return $sanitizedMessage . ' [Debug: ' . substr($detailedMessage, 0, 100) . ']';
    }

    /**
     * Create a unique error reference ID for tracking.
     *
     * @return string
     */
    public static function generateErrorReference(): string
    {
        return 'ERR_' . strtoupper(uniqid());
    }
}