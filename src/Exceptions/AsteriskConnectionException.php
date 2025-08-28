<?php

namespace AsteriskPbxManager\Exceptions;

use Exception;

/**
 * Exception thrown when connection to Asterisk Manager Interface fails.
 * Uses secure error handling to prevent information disclosure.
 */
class AsteriskConnectionException extends Exception
{
    /**
     * The error reference ID for tracking.
     *
     * @var string|null
     */
    protected ?string $errorReference = null;

    /**
     * Create a new Asterisk connection exception instance.
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(string $message = 'Failed to connect to Asterisk Manager Interface', int $code = 0, Exception $previous = null)
    {
        $this->errorReference = SecureErrorHandler::generateErrorReference();
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for connection timeout.
     *
     * @param int $timeout
     * @return static
     */
    public static function timeout(int $timeout): static
    {
        $detailedMessage = "Connection to Asterisk AMI timed out after {$timeout} seconds";
        $secureMessage = SecureErrorHandler::generateSecureMessage(
            SecureErrorHandler::ERROR_CONNECTION_FAILED,
            $detailedMessage,
            ['timeout_seconds' => $timeout]
        );
        
        return new static($secureMessage);
    }

    /**
     * Create exception for authentication failure.
     *
     * @param string $username
     * @return static
     */
    public static function authenticationFailed(string $username): static
    {
        $detailedMessage = "Authentication failed for AMI user: {$username}";
        $secureMessage = SecureErrorHandler::generateSecureMessage(
            SecureErrorHandler::ERROR_AUTHENTICATION_FAILED,
            $detailedMessage,
            ['username' => $username]
        );
        
        return new static($secureMessage);
    }

    /**
     * Create exception for network error.
     *
     * @param string $host
     * @param int $port
     * @param string $error
     * @return static
     */
    public static function networkError(string $host, int $port, string $error): static
    {
        $detailedMessage = "Network error connecting to {$host}:{$port} - {$error}";
        $secureMessage = SecureErrorHandler::generateSecureMessage(
            SecureErrorHandler::ERROR_NETWORK_ERROR,
            $detailedMessage,
            ['host' => $host, 'port' => $port, 'network_error' => $error]
        );
        
        return new static($secureMessage);
    }

    /**
     * Create exception for invalid configuration.
     *
     * @param string $parameter
     * @return static
     */
    public static function invalidConfiguration(string $parameter): static
    {
        $detailedMessage = "Invalid AMI configuration parameter: {$parameter}";
        $secureMessage = SecureErrorHandler::generateSecureMessage(
            SecureErrorHandler::ERROR_INVALID_CONFIG,
            $detailedMessage,
            ['parameter' => $parameter]
        );
        
        return new static($secureMessage);
    }

    /**
     * Get the error reference ID for tracking.
     *
     * @return string|null
     */
    public function getErrorReference(): ?string
    {
        return $this->errorReference;
    }
}