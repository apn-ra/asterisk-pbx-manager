<?php

namespace AsteriskPbxManager\Services;

use PAMI\Message\Action\RedirectAction;
use PAMI\Message\Action\BridgeAction;
use PAMI\Message\Action\ParkAction;
use PAMI\Message\Action\ParkedCallsAction;
use PAMI\Message\Action\MonitorAction;
use PAMI\Message\Action\StopMonitorAction;
use PAMI\Message\Action\StatusAction;
use PAMI\Message\Action\GetVarAction;
use PAMI\Message\Action\SetVarAction;
use PAMI\Message\Action\AttendedTransferAction;
use Illuminate\Support\Facades\Log;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use AsteriskPbxManager\Services\AmiInputSanitizer;

class ChannelManagerService
{
    protected AsteriskManagerService $asteriskManager;
    protected AmiInputSanitizer $sanitizer;

    /**
     * Create a new channel manager service instance.
     */
    public function __construct(AsteriskManagerService $asteriskManager, AmiInputSanitizer $sanitizer)
    {
        $this->asteriskManager = $asteriskManager;
        $this->sanitizer = $sanitizer;
    }

    /**
     * Transfer a call to another extension (blind transfer).
     */
    public function blindTransfer(string $channel, string $extension, string $context = 'default'): bool
    {
        $channel = $this->validateChannel($channel);
        $extension = $this->validateExtension($extension);
        $context = $this->sanitizer->sanitizeContext($context);

        try {
            $action = new RedirectAction($channel, $extension, $context, 1);

            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                Log::info('Blind transfer executed successfully', [
                    'channel' => $channel,
                    'extension' => $extension,
                    'context' => $context
                ]);
                return true;
            } else {
                Log::warning('Failed to execute blind transfer', [
                    'channel' => $channel,
                    'extension' => $extension,
                    'context' => $context,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while executing blind transfer: ' . $e->getMessage(), [
                'channel' => $channel,
                'extension' => $extension,
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to execute blind transfer: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Execute an attended transfer between two channels.
     */
    public function attendedTransfer(string $channel1, string $channel2, string $extension, string $context = 'default'): bool
    {
        $this->validateChannel($channel1);
        $this->validateChannel($channel2);
        $this->validateExtension($extension);

        try {
            // First, bridge the channels
            $bridgeAction = new BridgeAction($channel1, $channel2);
            $bridgeResponse = $this->asteriskManager->send($bridgeAction);

            if (!$bridgeResponse->isSuccess()) {
                Log::warning('Failed to bridge channels for attended transfer', [
                    'channel1' => $channel1,
                    'channel2' => $channel2,
                    'response' => $bridgeResponse->getMessage()
                ]);
                return false;
            }

            // Then redirect to the target extension
            $redirectAction = new RedirectAction($channel1, $extension, $context, 1);
            $response = $this->asteriskManager->send($redirectAction);
            
            if ($response->isSuccess()) {
                Log::info('Attended transfer executed successfully', [
                    'channel1' => $channel1,
                    'channel2' => $channel2,
                    'extension' => $extension,
                    'context' => $context
                ]);
                return true;
            } else {
                Log::warning('Failed to execute attended transfer redirect', [
                    'channel1' => $channel1,
                    'channel2' => $channel2,
                    'extension' => $extension,
                    'context' => $context,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while executing attended transfer: ' . $e->getMessage(), [
                'channel1' => $channel1,
                'channel2' => $channel2,
                'extension' => $extension,
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to execute attended transfer: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Park a call.
     */
    public function parkCall(string $channel, ?string $parkingLot = null, ?int $timeout = null): array
    {
        $this->validateChannel($channel);

        try {
            $action = new ParkAction($channel);
            
            if ($parkingLot) {
                $action->setParkingLot($parkingLot);
            }
            
            if ($timeout) {
                $action->setTimeout($timeout);
            }

            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                $parkingSpace = $response->getKey('ParkingSpace') ?? null;
                $parkingTimeout = $response->getKey('ParkingTimeout') ?? $timeout;
                
                Log::info('Call parked successfully', [
                    'channel' => $channel,
                    'parking_space' => $parkingSpace,
                    'parking_lot' => $parkingLot,
                    'timeout' => $parkingTimeout
                ]);
                
                return [
                    'success' => true,
                    'parking_space' => $parkingSpace,
                    'parking_lot' => $parkingLot,
                    'timeout' => $parkingTimeout
                ];
            } else {
                Log::warning('Failed to park call', [
                    'channel' => $channel,
                    'parking_lot' => $parkingLot,
                    'response' => $response->getMessage()
                ]);
                
                return [
                    'success' => false,
                    'message' => $response->getMessage()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception while parking call: ' . $e->getMessage(), [
                'channel' => $channel,
                'parking_lot' => $parkingLot,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to park call: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Pickup a parked call.
     */
    public function pickupParkedCall(string $parkingSpace, string $channel, ?string $parkingLot = null): bool
    {
        $this->validateChannel($channel);
        $this->validateParkingSpace($parkingSpace);

        try {
            // Use redirect to connect the channel to the parked call
            $extension = $parkingSpace;
            $context = $parkingLot ? "parkedcalls-{$parkingLot}" : 'parkedcalls';
            
            $action = new RedirectAction($channel, $extension, $context, 1);
            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                Log::info('Parked call picked up successfully', [
                    'channel' => $channel,
                    'parking_space' => $parkingSpace,
                    'parking_lot' => $parkingLot
                ]);
                return true;
            } else {
                Log::warning('Failed to pickup parked call', [
                    'channel' => $channel,
                    'parking_space' => $parkingSpace,
                    'parking_lot' => $parkingLot,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while picking up parked call: ' . $e->getMessage(), [
                'channel' => $channel,
                'parking_space' => $parkingSpace,
                'parking_lot' => $parkingLot,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to pickup parked call: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get list of parked calls.
     */
    public function getParkedCalls(): array
    {
        try {
            $action = new ParkedCallsAction();
            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                $parkedCalls = $this->parseParkedCallsResponse($response);
                
                Log::info('Parked calls retrieved successfully', [
                    'count' => count($parkedCalls)
                ]);
                
                return $parkedCalls;
            } else {
                Log::warning('Failed to get parked calls', [
                    'response' => $response->getMessage()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception while getting parked calls: ' . $e->getMessage(), [
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to get parked calls: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Start monitoring a channel.
     */
    public function startMonitoring(string $channel, ?string $filename = null, string $format = 'wav', bool $mix = true): bool
    {
        $this->validateChannel($channel);

        try {
            $action = new MonitorAction($channel, $filename, $format, $mix);

            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                Log::info('Channel monitoring started successfully', [
                    'channel' => $channel,
                    'filename' => $filename,
                    'format' => $format,
                    'mix' => $mix
                ]);
                return true;
            } else {
                Log::warning('Failed to start channel monitoring', [
                    'channel' => $channel,
                    'filename' => $filename,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while starting channel monitoring: ' . $e->getMessage(), [
                'channel' => $channel,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to start channel monitoring: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Stop monitoring a channel.
     */
    public function stopMonitoring(string $channel): bool
    {
        $this->validateChannel($channel);

        try {
            $action = new StopMonitorAction($channel);
            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                Log::info('Channel monitoring stopped successfully', [
                    'channel' => $channel
                ]);
                return true;
            } else {
                Log::warning('Failed to stop channel monitoring', [
                    'channel' => $channel,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while stopping channel monitoring: ' . $e->getMessage(), [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to stop channel monitoring: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get channel status information.
     */
    public function getChannelStatus(?string $channel = null): array
    {
        if ($channel) {
            $this->validateChannel($channel);
        }

        try {
            $action = new StatusAction();
            if ($channel) {
                $action->setChannel($channel);
            }

            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                $channelData = $this->parseChannelStatusResponse($response);
                
                Log::info('Channel status retrieved successfully', [
                    'channel' => $channel ?? 'all',
                    'channel_count' => count($channelData)
                ]);
                
                return $channelData;
            } else {
                Log::warning('Failed to get channel status', [
                    'channel' => $channel ?? 'all',
                    'response' => $response->getMessage()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception while getting channel status: ' . $e->getMessage(), [
                'channel' => $channel ?? 'all',
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to get channel status: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get a channel variable value.
     */
    public function getChannelVariable(string $channel, string $variable): ?string
    {
        $this->validateChannel($channel);

        try {
            $action = new GetVarAction($channel, $variable);
            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                $value = $response->getKey('Value');
                
                Log::info('Channel variable retrieved successfully', [
                    'channel' => $channel,
                    'variable' => $variable,
                    'value' => $value
                ]);
                
                return $value;
            } else {
                Log::warning('Failed to get channel variable', [
                    'channel' => $channel,
                    'variable' => $variable,
                    'response' => $response->getMessage()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Exception while getting channel variable: ' . $e->getMessage(), [
                'channel' => $channel,
                'variable' => $variable,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to get channel variable: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Set a channel variable value.
     */
    public function setChannelVariable(string $channel, string $variable, string $value): bool
    {
        $this->validateChannel($channel);

        try {
            $action = new SetVarAction($channel, $variable, $value);
            $response = $this->asteriskManager->send($action);
            
            if ($response->isSuccess()) {
                Log::info('Channel variable set successfully', [
                    'channel' => $channel,
                    'variable' => $variable,
                    'value' => $value
                ]);
                return true;
            } else {
                Log::warning('Failed to set channel variable', [
                    'channel' => $channel,
                    'variable' => $variable,
                    'value' => $value,
                    'response' => $response->getMessage()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while setting channel variable: ' . $e->getMessage(), [
                'channel' => $channel,
                'variable' => $variable,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            throw new ActionExecutionException("Failed to set channel variable: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Validate and sanitize channel parameter.
     *
     * @param string $channel
     * @return string Sanitized channel
     * @throws \InvalidArgumentException
     */
    protected function validateChannel(string $channel): string
    {
        return $this->sanitizer->sanitizeChannel($channel);
    }

    /**
     * Validate and sanitize extension parameter.
     *
     * @param string $extension
     * @return string Sanitized extension
     * @throws \InvalidArgumentException
     */
    protected function validateExtension(string $extension): string
    {
        return $this->sanitizer->sanitizeExtension($extension);
    }

    /**
     * Validate and sanitize parking space parameter.
     *
     * @param string $parkingSpace
     * @return string Sanitized parking space
     * @throws \InvalidArgumentException
     */
    protected function validateParkingSpace(string $parkingSpace): string
    {
        return $this->sanitizer->sanitizeParkingSpace($parkingSpace);
    }

    /**
     * Parse parked calls response into structured data.
     */
    protected function parseParkedCallsResponse($response): array
    {
        $parkedCalls = [];
        
        // Placeholder implementation - would need to be adapted based on actual PAMI response format
        $events = $response->getEvents() ?? [];
        
        foreach ($events as $event) {
            if ($event->getName() === 'ParkedCall') {
                $parkedCalls[] = [
                    'channel' => $event->getKey('Channel'),
                    'parking_space' => $event->getKey('ParkingSpace'),
                    'parking_lot' => $event->getKey('ParkingLot'),
                    'timeout' => (int) $event->getKey('ParkingTimeout'),
                    'duration' => (int) $event->getKey('ParkingDuration'),
                    'caller_id_num' => $event->getKey('CallerIDNum'),
                    'caller_id_name' => $event->getKey('CallerIDName')
                ];
            }
        }
        
        return $parkedCalls;
    }

    /**
     * Parse channel status response into structured data.
     */
    protected function parseChannelStatusResponse($response): array
    {
        $channelData = [];
        
        // Placeholder implementation - would need to be adapted based on actual PAMI response format
        $events = $response->getEvents() ?? [];
        
        foreach ($events as $event) {
            if ($event->getName() === 'Status') {
                $channel = $event->getKey('Channel');
                $channelData[$channel] = [
                    'channel' => $channel,
                    'caller_id_num' => $event->getKey('CallerIDNum'),
                    'caller_id_name' => $event->getKey('CallerIDName'),
                    'account_code' => $event->getKey('AccountCode'),
                    'channel_state' => $event->getKey('ChannelState'),
                    'channel_state_desc' => $event->getKey('ChannelStateDesc'),
                    'context' => $event->getKey('Context'),
                    'extension' => $event->getKey('Extension'),
                    'priority' => (int) $event->getKey('Priority'),
                    'seconds' => (int) $event->getKey('Seconds'),
                    'bridge_id' => $event->getKey('BridgeId'),
                    'unique_id' => $event->getKey('Uniqueid')
                ];
            }
        }
        
        return $channelData;
    }
}