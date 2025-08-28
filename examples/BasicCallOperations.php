<?php

/**
 * Basic Call Operations Example
 * 
 * This example demonstrates basic call operations using the Asterisk PBX Manager package.
 * It shows how to originate calls, check connection status, and handle basic AMI operations.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AsteriskPbxManager\Facades\AsteriskManager;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use Illuminate\Support\Facades\Log;

class BasicCallOperationsExample
{
    private AsteriskManagerService $asteriskManager;

    public function __construct(AsteriskManagerService $asteriskManager)
    {
        $this->asteriskManager = $asteriskManager;
    }

    /**
     * Example 1: Check connection status
     */
    public function checkConnectionStatus(): bool
    {
        try {
            if (AsteriskManager::isConnected()) {
                echo "✅ Connected to Asterisk PBX\n";
                return true;
            } else {
                echo "❌ Not connected to Asterisk PBX\n";
                return false;
            }
        } catch (AsteriskConnectionException $e) {
            echo "❌ Connection error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Example 2: Originate a simple call
     */
    public function originateSimpleCall(string $channel, string $extension): bool
    {
        try {
            echo "📞 Originating call from {$channel} to {$extension}\n";
            
            $result = $this->asteriskManager->originateCall($channel, $extension);
            
            if ($result) {
                echo "✅ Call originated successfully\n";
                return true;
            } else {
                echo "❌ Failed to originate call\n";
                return false;
            }
        } catch (ActionExecutionException $e) {
            echo "❌ Action execution failed: " . $e->getMessage() . "\n";
            return false;
        } catch (\Exception $e) {
            echo "❌ Unexpected error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Example 3: Originate call with additional options
     */
    public function originateCallWithOptions(
        string $channel, 
        string $extension, 
        array $options = []
    ): bool {
        try {
            $defaultOptions = [
                'Context' => 'default',
                'Priority' => '1',
                'Timeout' => '30000',
                'CallerID' => 'System Call <1000>',
            ];

            $callOptions = array_merge($defaultOptions, $options);

            echo "📞 Originating call with options:\n";
            echo "   Channel: {$channel}\n";
            echo "   Extension: {$extension}\n";
            foreach ($callOptions as $key => $value) {
                echo "   {$key}: {$value}\n";
            }

            $result = $this->asteriskManager->originateCall(
                $channel, 
                $extension, 
                $callOptions
            );

            if ($result) {
                echo "✅ Call originated successfully with options\n";
                return true;
            } else {
                echo "❌ Failed to originate call with options\n";
                return false;
            }
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Example 4: Hangup a call
     */
    public function hangupCall(string $channel): bool
    {
        try {
            echo "📴 Hanging up call on channel: {$channel}\n";
            
            $result = $this->asteriskManager->hangupCall($channel);
            
            if ($result) {
                echo "✅ Call hung up successfully\n";
                return true;
            } else {
                echo "❌ Failed to hang up call\n";
                return false;
            }
        } catch (ActionExecutionException $e) {
            echo "❌ Hangup action failed: " . $e->getMessage() . "\n";
            return false;
        } catch (\Exception $e) {
            echo "❌ Unexpected error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Example 5: Get system status
     */
    public function getSystemStatus(): ?array
    {
        try {
            echo "ℹ️ Retrieving Asterisk system status...\n";
            
            $status = $this->asteriskManager->getStatus();
            
            if ($status) {
                echo "✅ System status retrieved:\n";
                foreach ($status as $key => $value) {
                    echo "   {$key}: {$value}\n";
                }
                return $status;
            } else {
                echo "❌ Failed to retrieve system status\n";
                return null;
            }
        } catch (\Exception $e) {
            echo "❌ Error retrieving status: " . $e->getMessage() . "\n";
            return null;
        }
    }

    /**
     * Example 6: Send custom AMI action
     */
    public function sendCustomAction(string $action, array $parameters = []): bool
    {
        try {
            echo "🔧 Sending custom AMI action: {$action}\n";
            
            $result = $this->asteriskManager->send($action, $parameters);
            
            if ($result) {
                echo "✅ Custom action executed successfully\n";
                return true;
            } else {
                echo "❌ Custom action failed\n";
                return false;
            }
        } catch (\Exception $e) {
            echo "❌ Custom action error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Example 7: Comprehensive call workflow
     */
    public function demonstrateCallWorkflow(): void
    {
        echo "\n🚀 Starting comprehensive call workflow demonstration\n";
        echo "=" . str_repeat("=", 50) . "\n";

        // Step 1: Check connection
        if (!$this->checkConnectionStatus()) {
            echo "❌ Cannot proceed without connection. Exiting.\n";
            return;
        }

        // Step 2: Get system status
        $this->getSystemStatus();

        // Step 3: Originate a basic call
        $channel = 'SIP/1001';
        $extension = '2002';
        $callOriginated = $this->originateSimpleCall($channel, $extension);

        if ($callOriginated) {
            // Wait a moment for call to establish
            echo "⏳ Waiting for call to establish...\n";
            sleep(5);

            // Step 4: Demonstrate hangup (in a real scenario, you'd get the actual channel)
            $activeChannel = $channel . '-' . time(); // Simulated channel name
            $this->hangupCall($activeChannel);
        }

        // Step 5: Demonstrate call with options
        $this->originateCallWithOptions($channel, '3001', [
            'Context' => 'internal',
            'CallerID' => 'Demo Call <9999>',
            'Timeout' => '45000',
        ]);

        // Step 6: Send custom action (Ping)
        $this->sendCustomAction('Ping');

        echo "\n✅ Call workflow demonstration completed\n";
        echo "=" . str_repeat("=", 50) . "\n";
    }
}

// Usage example when running as script
if (php_sapi_name() === 'cli') {
    echo "Asterisk PBX Manager - Basic Call Operations Example\n";
    echo "=" . str_repeat("=", 50) . "\n";

    try {
        // Note: In a real Laravel application, this would be injected
        $asteriskManager = app(AsteriskManagerService::class);
        $example = new BasicCallOperationsExample($asteriskManager);
        
        $example->demonstrateCallWorkflow();
        
    } catch (\Exception $e) {
        echo "❌ Failed to initialize example: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Additional helper functions for testing
class CallOperationHelpers
{
    /**
     * Validate channel format
     */
    public static function validateChannel(string $channel): bool
    {
        // Basic validation for common channel formats
        $patterns = [
            '/^SIP\/\w+$/',      // SIP/extension
            '/^IAX2\/\w+$/',     // IAX2/extension
            '/^DAHDI\/\d+$/',    // DAHDI/channel
            '/^Local\/\w+@\w+$/', // Local/extension@context
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $channel)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format call duration
     */
    public static function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        } else {
            return sprintf('%02d:%02d', $minutes, $secs);
        }
    }

    /**
     * Generate unique call ID
     */
    public static function generateCallId(): string
    {
        return 'call_' . time() . '_' . mt_rand(1000, 9999);
    }
}