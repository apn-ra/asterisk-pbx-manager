<?php

namespace AsteriskPbxManager\Services;

use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use Illuminate\Support\Facades\Log;

/**
 * Configuration Validator Service
 * 
 * Handles validation and sanitization of Asterisk PBX Manager configuration
 * to ensure security and proper functionality.
 */
class ConfigurationValidator
{
    /**
     * Valid AMI schemes
     */
    private const VALID_SCHEMES = ['tcp://', 'ssl://'];

    /**
     * Valid logging levels
     */
    private const VALID_LOG_LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /**
     * Maximum values for configuration parameters
     */
    private const MAX_PORT = 65535;
    private const MIN_PORT = 1;
    private const MAX_TIMEOUT = 300;
    private const MIN_TIMEOUT = 1;
    private const MAX_RECONNECTION_ATTEMPTS = 10;
    private const MAX_RECONNECTION_DELAY = 300;
    private const MAX_USERNAME_LENGTH = 64;
    private const MAX_SECRET_LENGTH = 128;
    private const MAX_CONTEXT_LENGTH = 80;

    /**
     * Validate and sanitize complete configuration array
     *
     * @param array $config Configuration array
     * @return array Validated and sanitized configuration
     * @throws AsteriskConnectionException If validation fails
     */
    public function validateConfiguration(array $config): array
    {
        Log::info('Starting configuration validation');

        $validated = [];

        // Validate connection settings
        $validated['connection'] = $this->validateConnection($config['connection'] ?? []);

        // Validate event settings
        $validated['events'] = $this->validateEvents($config['events'] ?? []);

        // Validate reconnection settings
        $validated['reconnection'] = $this->validateReconnection($config['reconnection'] ?? []);

        // Validate logging settings
        $validated['logging'] = $this->validateLogging($config['logging'] ?? []);

        // Validate queue settings
        $validated['queues'] = $this->validateQueues($config['queues'] ?? []);

        // Validate broadcasting settings
        $validated['broadcasting'] = $this->validateBroadcasting($config['broadcasting'] ?? []);

        Log::info('Configuration validation completed successfully');

        return $validated;
    }

    /**
     * Validate AMI connection configuration
     *
     * @param array $connection Connection configuration
     * @return array Validated connection configuration
     * @throws AsteriskConnectionException If validation fails
     */
    private function validateConnection(array $connection): array
    {
        $validated = [];

        // Validate and sanitize host
        $host = $this->sanitizeString($connection['host'] ?? '127.0.0.1');
        if (empty($host)) {
            throw new AsteriskConnectionException('AMI host cannot be empty');
        }

        if (!$this->isValidHost($host)) {
            throw new AsteriskConnectionException('Invalid AMI host format: ' . $host);
        }
        $validated['host'] = $host;

        // Validate port
        $port = (int) ($connection['port'] ?? 5038);
        if ($port < self::MIN_PORT || $port > self::MAX_PORT) {
            throw new AsteriskConnectionException(
                sprintf('AMI port must be between %d and %d, got: %d', self::MIN_PORT, self::MAX_PORT, $port)
            );
        }
        $validated['port'] = $port;

        // Validate and sanitize username
        $username = $this->sanitizeString($connection['username'] ?? '');
        if (empty($username)) {
            throw new AsteriskConnectionException('AMI username cannot be empty');
        }
        if (strlen($username) > self::MAX_USERNAME_LENGTH) {
            throw new AsteriskConnectionException(
                sprintf('AMI username cannot exceed %d characters', self::MAX_USERNAME_LENGTH)
            );
        }
        if (!$this->isValidUsername($username)) {
            throw new AsteriskConnectionException('AMI username contains invalid characters');
        }
        $validated['username'] = $username;

        // Validate and sanitize secret
        $secret = $this->sanitizeString($connection['secret'] ?? '');
        if (empty($secret) || $secret === 'your_ami_secret') {
            throw new AsteriskConnectionException('AMI secret must be configured and cannot be default value');
        }
        if (strlen($secret) > self::MAX_SECRET_LENGTH) {
            throw new AsteriskConnectionException(
                sprintf('AMI secret cannot exceed %d characters', self::MAX_SECRET_LENGTH)
            );
        }
        $validated['secret'] = $secret;

        // Validate connection timeout
        $connectTimeout = (int) ($connection['connect_timeout'] ?? 10);
        if ($connectTimeout < self::MIN_TIMEOUT || $connectTimeout > self::MAX_TIMEOUT) {
            throw new AsteriskConnectionException(
                sprintf('Connect timeout must be between %d and %d seconds', self::MIN_TIMEOUT, self::MAX_TIMEOUT)
            );
        }
        $validated['connect_timeout'] = $connectTimeout;

        // Validate read timeout
        $readTimeout = (int) ($connection['read_timeout'] ?? 10);
        if ($readTimeout < self::MIN_TIMEOUT || $readTimeout > self::MAX_TIMEOUT) {
            throw new AsteriskConnectionException(
                sprintf('Read timeout must be between %d and %d seconds', self::MIN_TIMEOUT, self::MAX_TIMEOUT)
            );
        }
        $validated['read_timeout'] = $readTimeout;

        // Validate scheme
        $scheme = $this->sanitizeString($connection['scheme'] ?? 'tcp://');
        if (!in_array($scheme, self::VALID_SCHEMES, true)) {
            throw new AsteriskConnectionException(
                sprintf('Invalid AMI scheme: %s. Valid schemes: %s', $scheme, implode(', ', self::VALID_SCHEMES))
            );
        }
        $validated['scheme'] = $scheme;

        return $validated;
    }

    /**
     * Validate event configuration
     *
     * @param array $events Event configuration
     * @return array Validated event configuration
     */
    private function validateEvents(array $events): array
    {
        return [
            'enabled' => (bool) ($events['enabled'] ?? true),
            'broadcast' => (bool) ($events['broadcast'] ?? true),
            'log_to_database' => (bool) ($events['log_to_database'] ?? true),
        ];
    }

    /**
     * Validate reconnection configuration
     *
     * @param array $reconnection Reconnection configuration
     * @return array Validated reconnection configuration
     * @throws AsteriskConnectionException If validation fails
     */
    private function validateReconnection(array $reconnection): array
    {
        $validated = [];

        $validated['enabled'] = (bool) ($reconnection['enabled'] ?? true);

        $maxAttempts = (int) ($reconnection['max_attempts'] ?? 3);
        if ($maxAttempts < 0 || $maxAttempts > self::MAX_RECONNECTION_ATTEMPTS) {
            throw new AsteriskConnectionException(
                sprintf('Reconnection max attempts must be between 0 and %d', self::MAX_RECONNECTION_ATTEMPTS)
            );
        }
        $validated['max_attempts'] = $maxAttempts;

        $delay = (int) ($reconnection['delay_seconds'] ?? 5);
        if ($delay < 0 || $delay > self::MAX_RECONNECTION_DELAY) {
            throw new AsteriskConnectionException(
                sprintf('Reconnection delay must be between 0 and %d seconds', self::MAX_RECONNECTION_DELAY)
            );
        }
        $validated['delay_seconds'] = $delay;

        return $validated;
    }

    /**
     * Validate logging configuration
     *
     * @param array $logging Logging configuration
     * @return array Validated logging configuration
     * @throws AsteriskConnectionException If validation fails
     */
    private function validateLogging(array $logging): array
    {
        $validated = [];

        $validated['enabled'] = (bool) ($logging['enabled'] ?? true);

        $level = strtolower($this->sanitizeString($logging['level'] ?? 'info'));
        if (!in_array($level, self::VALID_LOG_LEVELS, true)) {
            throw new AsteriskConnectionException(
                sprintf('Invalid log level: %s. Valid levels: %s', $level, implode(', ', self::VALID_LOG_LEVELS))
            );
        }
        $validated['level'] = $level;

        $channel = $this->sanitizeString($logging['channel'] ?? 'default');
        if (empty($channel)) {
            throw new AsteriskConnectionException('Log channel cannot be empty');
        }
        if (!$this->isValidChannelName($channel)) {
            throw new AsteriskConnectionException('Log channel contains invalid characters');
        }
        $validated['channel'] = $channel;

        return $validated;
    }

    /**
     * Validate queue configuration
     *
     * @param array $queues Queue configuration
     * @return array Validated queue configuration
     * @throws AsteriskConnectionException If validation fails
     */
    private function validateQueues(array $queues): array
    {
        $validated = [];

        $context = $this->sanitizeString($queues['default_context'] ?? 'default');
        if (empty($context)) {
            throw new AsteriskConnectionException('Default context cannot be empty');
        }
        if (strlen($context) > self::MAX_CONTEXT_LENGTH) {
            throw new AsteriskConnectionException(
                sprintf('Default context cannot exceed %d characters', self::MAX_CONTEXT_LENGTH)
            );
        }
        if (!$this->isValidContext($context)) {
            throw new AsteriskConnectionException('Default context contains invalid characters');
        }
        $validated['default_context'] = $context;

        $priority = (int) ($queues['default_priority'] ?? 1);
        if ($priority < 1 || $priority > 999) {
            throw new AsteriskConnectionException('Default priority must be between 1 and 999');
        }
        $validated['default_priority'] = $priority;

        return $validated;
    }

    /**
     * Validate broadcasting configuration
     *
     * @param array $broadcasting Broadcasting configuration
     * @return array Validated broadcasting configuration
     * @throws AsteriskConnectionException If validation fails
     */
    private function validateBroadcasting(array $broadcasting): array
    {
        $validated = [];

        $prefix = $this->sanitizeString($broadcasting['channel_prefix'] ?? 'asterisk');
        if (empty($prefix)) {
            throw new AsteriskConnectionException('Channel prefix cannot be empty');
        }
        if (!$this->isValidChannelName($prefix)) {
            throw new AsteriskConnectionException('Channel prefix contains invalid characters');
        }
        $validated['channel_prefix'] = $prefix;

        $validated['private_channels'] = (bool) ($broadcasting['private_channels'] ?? false);

        return $validated;
    }

    /**
     * Sanitize string input by removing null bytes and trimming
     *
     * @param mixed $input Input to sanitize
     * @return string Sanitized string
     */
    private function sanitizeString($input): string
    {
        if (!is_string($input)) {
            $input = (string) $input;
        }

        // Remove null bytes and other control characters
        $sanitized = str_replace(["\0", "\r", "\n", "\t"], '', $input);
        
        // Trim whitespace
        $sanitized = trim($sanitized);

        return $sanitized;
    }

    /**
     * Check if host is valid (IP address or hostname)
     *
     * @param string $host Host to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidHost(string $host): bool
    {
        // Check if it's a valid IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Check for invalid patterns first
        if (strpos($host, '..') !== false || // consecutive dots
            strpos($host, '--') !== false || // consecutive hyphens
            strpos($host, '.-') !== false || // dot followed by hyphen
            strpos($host, '-.') !== false || // hyphen followed by dot
            $host[0] === '.' || $host[0] === '-' || // starts with dot or hyphen
            substr($host, -1) === '.' || substr($host, -1) === '-') { // ends with dot or hyphen
            return false;
        }

        // Check if it's a valid hostname (single label without dots)
        if (preg_match('/^[a-zA-Z0-9-]+$/', $host) && strlen($host) <= 63) {
            return true;
        }

        // Check if it's a valid domain name (multiple labels with dots)
        if (preg_match('/^[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/', $host)) {
            // Additional validation: each label should not exceed 63 characters
            $labels = explode('.', $host);
            foreach ($labels as $label) {
                if (strlen($label) > 63 || empty($label)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Check if username contains only valid characters
     *
     * @param string $username Username to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidUsername(string $username): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $username);
    }

    /**
     * Check if channel name contains only valid characters
     *
     * @param string $channel Channel name to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidChannelName(string $channel): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $channel);
    }

    /**
     * Check if context contains only valid characters
     *
     * @param string $context Context to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidContext(string $context): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $context);
    }

    /**
     * Validate configuration and throw exception with details if invalid
     *
     * @param array $config Configuration to validate
     * @return void
     * @throws AsteriskConnectionException If validation fails
     */
    public function validateOrFail(array $config): void
    {
        try {
            $this->validateConfiguration($config);
        } catch (AsteriskConnectionException $e) {
            Log::error('Configuration validation failed', [
                'error' => $e->getMessage(),
                'config_keys' => array_keys($config)
            ]);
            throw $e;
        }
    }

    /**
     * Get sanitized and validated configuration for safe usage
     *
     * @param array $config Raw configuration
     * @return array Sanitized configuration
     */
    public function getSafeConfiguration(array $config): array
    {
        $validated = $this->validateConfiguration($config);
        
        // Create safe version without exposing sensitive data in logs
        $safe = $validated;
        if (isset($safe['connection']['secret'])) {
            $safe['connection']['secret'] = '[HIDDEN]';
        }

        return $safe;
    }
}