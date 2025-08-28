<?php

namespace AsteriskPbxManager\Services;

use InvalidArgumentException;

/**
 * AMI Input Sanitizer Service
 *
 * This service provides comprehensive input sanitization for all AMI command parameters
 * to prevent AMI protocol injection attacks and ensure data integrity.
 *
 * The Asterisk Manager Interface uses key-value pairs separated by colons and messages
 * terminated by double CRLF. This sanitizer removes dangerous characters that could
 * be used to inject malicious AMI commands or corrupt the protocol.
 *
 * @package AsteriskPbxManager\Services
 */
class AmiInputSanitizer
{
    /**
     * Maximum length limits for different parameter types
     */
    private const MAX_LENGTHS = [
        'channel' => 64,
        'extension' => 32,
        'context' => 32,
        'queue' => 32,
        'interface' => 64,
        'variable' => 64,
        'value' => 256,
        'filename' => 128,
        'member_name' => 64,
        'reason' => 128,
        'parking_lot' => 32,
        'parking_space' => 8,
        'format' => 16
    ];

    /**
     * AMI protocol dangerous characters that must be removed or escaped
     */
    private const DANGEROUS_CHARS = [
        "\r",    // Carriage return - used in AMI message termination
        "\n",    // Line feed - used in AMI message termination
        "\0",    // Null byte - can terminate strings unexpectedly
        "\x01",  // Start of heading
        "\x02",  // Start of text
        "\x03",  // End of text
        "\x04",  // End of transmission
        "\x05",  // Enquiry
        "\x06",  // Acknowledge
        "\x07",  // Bell
        "\x08",  // Backspace
        "\x0B",  // Vertical tab
        "\x0C",  // Form feed
        "\x0E",  // Shift out
        "\x0F",  // Shift in
        "\x10",  // Data link escape
        "\x11",  // Device control 1
        "\x12",  // Device control 2
        "\x13",  // Device control 3
        "\x14",  // Device control 4
        "\x15",  // Negative acknowledge
        "\x16",  // Synchronous idle
        "\x17",  // End of transmission block
        "\x18",  // Cancel
        "\x19",  // End of medium
        "\x1A",  // Substitute
        "\x1B",  // Escape
        "\x1C",  // File separator
        "\x1D",  // Group separator
        "\x1E",  // Record separator
        "\x1F",  // Unit separator
        "\x7F"   // Delete
    ];

    /**
     * Sanitize channel name parameter.
     *
     * Channels follow format: TECHNOLOGY/identifier[-uniqueid]
     * Examples: SIP/1234-00000001, PJSIP/user@domain-00000002
     *
     * @param string $channel
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeChannel(string $channel): string
    {
        if (empty($channel)) {
            throw new InvalidArgumentException('Channel cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($channel);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'channel');

        // Validate format after sanitization
        if (!preg_match('/^[A-Z]+\/[a-zA-Z0-9_@.\-]+(-[0-9a-f]{8})?$/i', $sanitized)) {
            throw new InvalidArgumentException(
                'Invalid channel format after sanitization. Expected: TECHNOLOGY/identifier[-uniqueid]'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize extension parameter.
     *
     * Extensions can contain numbers, letters, and common dial characters.
     *
     * @param string $extension
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeExtension(string $extension): string
    {
        if (empty($extension)) {
            throw new InvalidArgumentException('Extension cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($extension);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'extension');

        // Allow only safe extension characters
        if (!preg_match('/^[a-zA-Z0-9_*#+\-]+$/', $sanitized)) {
            throw new InvalidArgumentException(
                'Extension contains invalid characters after sanitization. Allowed: letters, numbers, _, *, #, +, -'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize context parameter.
     *
     * Contexts are used in Asterisk dialplan routing.
     *
     * @param string $context
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeContext(string $context): string
    {
        if (empty($context)) {
            throw new InvalidArgumentException('Context cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($context);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'context');

        // Allow only safe context characters (alphanumeric, underscore, hyphen)
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $sanitized)) {
            throw new InvalidArgumentException(
                'Context contains invalid characters after sanitization. Allowed: letters, numbers, _, -'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize queue name parameter.
     *
     * @param string $queue
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeQueueName(string $queue): string
    {
        if (empty($queue)) {
            throw new InvalidArgumentException('Queue name cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($queue);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'queue');

        // Allow only safe queue name characters
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $sanitized)) {
            throw new InvalidArgumentException(
                'Queue name contains invalid characters after sanitization. Allowed: letters, numbers, _, -'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize interface parameter.
     *
     * Interfaces follow format: TECHNOLOGY/identifier
     * Examples: SIP/1234, PJSIP/user@domain
     *
     * @param string $interface
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeInterface(string $interface): string
    {
        if (empty($interface)) {
            throw new InvalidArgumentException('Interface cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($interface);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'interface');

        // Validate format after sanitization
        if (!preg_match('/^[A-Z]+\/[a-zA-Z0-9_@.\-]+$/i', $sanitized)) {
            throw new InvalidArgumentException(
                'Invalid interface format after sanitization. Expected: TECHNOLOGY/identifier'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize variable name parameter.
     *
     * @param string $variable
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeVariable(string $variable): string
    {
        if (empty($variable)) {
            throw new InvalidArgumentException('Variable name cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($variable);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'variable');

        // Allow only safe variable name characters (alphanumeric, underscore)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $sanitized)) {
            throw new InvalidArgumentException(
                'Variable name contains invalid characters after sanitization. Must start with letter/underscore, contain only letters, numbers, underscores'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize variable value parameter.
     *
     * Variable values can contain a wider range of characters but must be safe for AMI.
     *
     * @param string $value
     * @return string
     */
    public function sanitizeValue(string $value): string
    {
        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($value);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'value');

        // Escape AMI parameter separator if present
        $sanitized = str_replace(':', '\\:', $sanitized);

        return $sanitized;
    }

    /**
     * Sanitize filename parameter.
     *
     * @param string $filename
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeFilename(string $filename): string
    {
        if (empty($filename)) {
            throw new InvalidArgumentException('Filename cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($filename);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'filename');

        // Remove path traversal attempts and unsafe filename characters
        $sanitized = preg_replace('/[\/\\\\<>:"|?*]/', '', $sanitized);
        $sanitized = str_replace(['..', './'], '', $sanitized);

        // Ensure filename is not empty after sanitization
        if (empty($sanitized)) {
            throw new InvalidArgumentException('Filename is empty after sanitization');
        }

        return $sanitized;
    }

    /**
     * Sanitize member name parameter.
     *
     * @param string $memberName
     * @return string
     */
    public function sanitizeMemberName(string $memberName): string
    {
        if (empty($memberName)) {
            return '';
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($memberName);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'member_name');

        // Allow alphanumeric, spaces, and common name characters
        $sanitized = preg_replace('/[^a-zA-Z0-9 _\-.]/', '', $sanitized);

        return trim($sanitized);
    }

    /**
     * Sanitize reason parameter (used for pause reasons, etc.).
     *
     * @param string $reason
     * @return string
     */
    public function sanitizeReason(string $reason): string
    {
        if (empty($reason)) {
            return '';
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($reason);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'reason');

        // Allow alphanumeric, spaces, and common punctuation
        $sanitized = preg_replace('/[^a-zA-Z0-9 _\-.,!?]/', '', $sanitized);

        return trim($sanitized);
    }

    /**
     * Sanitize parking lot parameter.
     *
     * @param string $parkingLot
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeParkingLot(string $parkingLot): string
    {
        if (empty($parkingLot)) {
            throw new InvalidArgumentException('Parking lot cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($parkingLot);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'parking_lot');

        // Allow only safe parking lot characters
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $sanitized)) {
            throw new InvalidArgumentException(
                'Parking lot contains invalid characters after sanitization. Allowed: letters, numbers, _, -'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize parking space parameter.
     *
     * @param string $parkingSpace
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeParkingSpace(string $parkingSpace): string
    {
        if (empty($parkingSpace)) {
            throw new InvalidArgumentException('Parking space cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($parkingSpace);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'parking_space');

        // Parking spaces should be numeric
        if (!preg_match('/^[0-9]+$/', $sanitized)) {
            throw new InvalidArgumentException(
                'Parking space must be numeric after sanitization'
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize format parameter (for monitoring, etc.).
     *
     * @param string $format
     * @return string
     * @throws InvalidArgumentException
     */
    public function sanitizeFormat(string $format): string
    {
        if (empty($format)) {
            throw new InvalidArgumentException('Format cannot be empty');
        }

        // Remove dangerous characters
        $sanitized = $this->removeDangerousChars($format);

        // Apply length limit
        $sanitized = $this->applyLengthLimit($sanitized, 'format');

        // Allow only common audio format names
        if (!preg_match('/^[a-zA-Z0-9]+$/', $sanitized)) {
            throw new InvalidArgumentException(
                'Format contains invalid characters after sanitization. Allowed: letters, numbers'
            );
        }

        return strtolower($sanitized);
    }

    /**
     * Remove dangerous characters that could corrupt AMI protocol.
     *
     * @param string $input
     * @return string
     */
    private function removeDangerousChars(string $input): string
    {
        // Remove all dangerous characters
        $sanitized = str_replace(self::DANGEROUS_CHARS, '', $input);

        // Remove any remaining control characters except tab (0x09) and space (0x20)
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $sanitized);

        // Trim whitespace
        return trim($sanitized);
    }

    /**
     * Apply length limit to input parameter.
     *
     * @param string $input
     * @param string $type
     * @return string
     */
    private function applyLengthLimit(string $input, string $type): string
    {
        $maxLength = self::MAX_LENGTHS[$type] ?? 256;

        if (strlen($input) > $maxLength) {
            return substr($input, 0, $maxLength);
        }

        return $input;
    }

    /**
     * Check if a string contains any dangerous characters.
     *
     * @param string $input
     * @return bool
     */
    public function containsDangerousChars(string $input): bool
    {
        foreach (self::DANGEROUS_CHARS as $char) {
            if (strpos($input, $char) !== false) {
                return true;
            }
        }

        // Check for other control characters
        return preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', $input) === 1;
    }

    /**
     * Get information about what was sanitized from input.
     *
     * @param string $original
     * @param string $sanitized
     * @return array
     */
    public function getSanitizationInfo(string $original, string $sanitized): array
    {
        return [
            'original_length' => strlen($original),
            'sanitized_length' => strlen($sanitized),
            'characters_removed' => strlen($original) - strlen($sanitized),
            'had_dangerous_chars' => $this->containsDangerousChars($original),
            'was_truncated' => strlen($sanitized) < strlen($original)
        ];
    }
}