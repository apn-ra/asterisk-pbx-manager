<?php

namespace AsteriskPbxManager\Exceptions;

use Exception;

/**
 * Exception thrown when connection to Asterisk Manager Interface fails.
 */
class AsteriskConnectionException extends Exception
{
    /**
     * Create a new Asterisk connection exception instance.
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(string $message = 'Failed to connect to Asterisk Manager Interface', int $code = 0, Exception $previous = null)
    {
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
        return new static("Connection to Asterisk AMI timed out after {$timeout} seconds");
    }

    /**
     * Create exception for authentication failure.
     *
     * @param string $username
     * @return static
     */
    public static function authenticationFailed(string $username): static
    {
        return new static("Authentication failed for AMI user: {$username}");
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
        return new static("Network error connecting to {$host}:{$port} - {$error}");
    }

    /**
     * Create exception for invalid configuration.
     *
     * @param string $parameter
     * @return static
     */
    public static function invalidConfiguration(string $parameter): static
    {
        return new static("Invalid AMI configuration parameter: {$parameter}");
    }
}