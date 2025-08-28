<?php

namespace AsteriskPbxManager\Tests\Unit\Mocks;

use PAMI\Message\Response\ResponseMessage;
use Mockery;
use Mockery\MockInterface;

/**
 * Factory for creating AMI response mocks for testing.
 */
class ResponseMockFactory
{
    /**
     * Create a success response for Originate action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createOriginateSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Originate successfully queued',
            'ActionID' => 'originate_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create an error response for Originate action.
     *
     * @param string $reason Error reason
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createOriginateErrorResponse(string $reason = 'No such extension/context', array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Error',
            'Message' => $reason,
            'ActionID' => 'originate_' . uniqid(),
        ];

        return PamiMockFactory::createErrorResponse($reason, array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a success response for Hangup action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createHangupSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Channel Hungup',
            'ActionID' => 'hangup_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create an error response for Hangup action.
     *
     * @param string $reason Error reason
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createHangupErrorResponse(string $reason = 'No such channel', array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Error',
            'Message' => $reason,
            'ActionID' => 'hangup_' . uniqid(),
        ];

        return PamiMockFactory::createErrorResponse($reason, array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a success response for QueueAdd action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createQueueAddSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Added interface to queue',
            'ActionID' => 'queueadd_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create an error response for QueueAdd action.
     *
     * @param string $reason Error reason
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createQueueAddErrorResponse(string $reason = 'No such queue', array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Error',
            'Message' => $reason,
            'ActionID' => 'queueadd_' . uniqid(),
        ];

        return PamiMockFactory::createErrorResponse($reason, array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a success response for QueueRemove action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createQueueRemoveSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Removed interface from queue',
            'ActionID' => 'queueremove_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create an error response for QueueRemove action.
     *
     * @param string $reason Error reason
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createQueueRemoveErrorResponse(string $reason = 'Interface not in queue', array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Error',
            'Message' => $reason,
            'ActionID' => 'queueremove_' . uniqid(),
        ];

        return PamiMockFactory::createErrorResponse($reason, array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a success response for QueuePause action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createQueuePauseSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Interface paused successfully',
            'ActionID' => 'queuepause_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a response for CoreStatus action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createCoreStatusResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Core Status',
            'CoreStartupDate' => '2024-08-28',
            'CoreStartupTime' => '12:00:00',
            'CoreReloadDate' => '2024-08-28',
            'CoreReloadTime' => '12:00:00',
            'CoreCurrentCalls' => '5',
            'CoreMaxCalls' => '500',
            'CoreMaxLoadAvg' => '0.5',
            'CoreRunningThreads' => '25',
            'CoreMaxFilehandles' => '1024',
            'RealTimeEnabled' => 'Yes',
            'CDREnabled' => 'Yes',
            'HTTPEnabled' => 'Yes',
            'ActionID' => 'corestatus_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a response for Queues action.
     *
     * @param array $queues Queue data
     * @return MockInterface
     */
    public static function createQueuesResponse(array $queues = []): MockInterface
    {
        if (empty($queues)) {
            $queues = [
                [
                    'Queue' => 'support',
                    'Max' => '0',
                    'Strategy' => 'ringall',
                    'Calls' => '0',
                    'Holdtime' => '0',
                    'TalkTime' => '0',
                    'Completed' => '0',
                    'Abandoned' => '0',
                    'ServiceLevel' => '60',
                    'ServicelevelPerf' => '0.0',
                    'Weight' => '0',
                ],
                [
                    'Queue' => 'sales',
                    'Max' => '0',
                    'Strategy' => 'leastrecent',
                    'Calls' => '2',
                    'Holdtime' => '30',
                    'TalkTime' => '120',
                    'Completed' => '15',
                    'Abandoned' => '1',
                    'ServiceLevel' => '60',
                    'ServicelevelPerf' => '93.8',
                    'Weight' => '0',
                ],
            ];
        }

        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getEventList')->andReturn($queues);
        $mockResponse->shouldReceive('getKey')->andReturnUsing(function ($key) use ($queues) {
            if ($key === 'Response') return 'Success';
            if ($key === 'Message') return 'Queue status will follow';
            if ($key === 'ActionID') return 'queues_' . uniqid();
            return null;
        });

        return $mockResponse;
    }

    /**
     * Create a response for Status action (channel status).
     *
     * @param array $channels Channel data
     * @return MockInterface
     */
    public static function createStatusResponse(array $channels = []): MockInterface
    {
        if (empty($channels)) {
            $channels = [
                [
                    'Channel' => 'SIP/1001-00000001',
                    'CallerIDNum' => '1001',
                    'CallerIDName' => 'John Doe',
                    'ConnectedLineNum' => '1002',
                    'ConnectedLineName' => 'Jane Smith',
                    'AccountCode' => '',
                    'ChannelState' => '6',
                    'ChannelStateDesc' => 'Up',
                    'Context' => 'internal',
                    'Extension' => '1002',
                    'Priority' => '1',
                    'Seconds' => '45',
                    'Uniqueid' => '1234567890.1',
                    'BridgedChannel' => 'SIP/1002-00000002',
                    'BridgedUniqueid' => '1234567890.2',
                ],
                [
                    'Channel' => 'SIP/1002-00000002',
                    'CallerIDNum' => '1002',
                    'CallerIDName' => 'Jane Smith',
                    'ConnectedLineNum' => '1001',
                    'ConnectedLineName' => 'John Doe',
                    'AccountCode' => '',
                    'ChannelState' => '6',
                    'ChannelStateDesc' => 'Up',
                    'Context' => 'internal',
                    'Extension' => '1001',
                    'Priority' => '1',
                    'Seconds' => '45',
                    'Uniqueid' => '1234567890.2',
                    'BridgedChannel' => 'SIP/1001-00000001',
                    'BridgedUniqueid' => '1234567890.1',
                ],
            ];
        }

        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getEventList')->andReturn($channels);
        $mockResponse->shouldReceive('getKey')->andReturnUsing(function ($key) {
            if ($key === 'Response') return 'Success';
            if ($key === 'Message') return 'Channel status will follow';
            if ($key === 'ActionID') return 'status_' . uniqid();
            return null;
        });

        return $mockResponse;
    }

    /**
     * Create a response for Command action.
     *
     * @param string $command The command executed
     * @param array $output Command output lines
     * @return MockInterface
     */
    public static function createCommandResponse(string $command, array $output = []): MockInterface
    {
        if (empty($output)) {
            $output = ["Command '{$command}' executed successfully"];
        }

        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getKey')->andReturnUsing(function ($key) use ($command, $output) {
            if ($key === 'Response') return 'Success';
            if ($key === 'Message') return implode("\n", $output);
            if ($key === 'ActionID') return 'command_' . uniqid();
            return null;
        });

        return $mockResponse;
    }

    /**
     * Create authentication success response.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createLoginSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Authentication accepted',
            'ActionID' => 'login_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create authentication error response.
     *
     * @param string $reason Error reason
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createLoginErrorResponse(string $reason = 'Authentication failed', array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Error',
            'Message' => $reason,
            'ActionID' => 'login_' . uniqid(),
        ];

        return PamiMockFactory::createErrorResponse($reason, array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a timeout response.
     *
     * @param string $action Action that timed out
     * @return MockInterface
     */
    public static function createTimeoutResponse(string $action = 'Unknown'): MockInterface
    {
        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(false);
        $mockResponse->shouldReceive('getKey')->andReturnUsing(function ($key) use ($action) {
            if ($key === 'Response') return 'Error';
            if ($key === 'Message') return "Timeout executing action: {$action}";
            if ($key === 'ActionID') return strtolower($action) . '_' . uniqid();
            return null;
        });

        return $mockResponse;
    }

    /**
     * Create a response for Bridge action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createBridgeSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Bridge completed successfully',
            'ActionID' => 'bridge_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create an error response for Bridge action.
     *
     * @param string $reason Error reason
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createBridgeErrorResponse(string $reason = 'Channel not found', array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Error',
            'Message' => $reason,
            'ActionID' => 'bridge_' . uniqid(),
        ];

        return PamiMockFactory::createErrorResponse($reason, array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a response for Redirect action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createRedirectSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Redirect successful',
            'ActionID' => 'redirect_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a response for Monitor action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createMonitorSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Started monitoring channel',
            'ActionID' => 'monitor_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a response for StopMonitor action.
     *
     * @param array $customKeys Custom response keys
     * @return MockInterface
     */
    public static function createStopMonitorSuccessResponse(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Response' => 'Success',
            'Message' => 'Stopped monitoring channel',
            'ActionID' => 'stopmonitor_' . uniqid(),
        ];

        return PamiMockFactory::createSuccessResponse(array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a permission denied response.
     *
     * @param string $action Action that was denied
     * @return MockInterface
     */
    public static function createPermissionDeniedResponse(string $action = 'Unknown'): MockInterface
    {
        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(false);
        $mockResponse->shouldReceive('getKey')->andReturnUsing(function ($key) use ($action) {
            if ($key === 'Response') return 'Error';
            if ($key === 'Message') return "Permission denied for action: {$action}";
            if ($key === 'ActionID') return strtolower($action) . '_' . uniqid();
            return null;
        });

        return $mockResponse;
    }

    /**
     * Create a response for SIPPeers action.
     *
     * @param array $peers SIP peer data
     * @return MockInterface
     */
    public static function createSIPPeersResponse(array $peers = []): MockInterface
    {
        if (empty($peers)) {
            $peers = [
                [
                    'ObjectName' => '1001',
                    'ChanType' => 'SIP',
                    'ChanObjectType' => 'peer',
                    'IPaddress' => '192.168.1.100',
                    'IPport' => '5060',
                    'Dynamic' => 'yes',
                    'AutoForcerport' => 'yes',
                    'Forcerport' => 'no',
                    'AutoComedia' => 'no',
                    'Comedia' => 'no',
                    'VideoSupport' => 'no',
                    'TextSupport' => 'no',
                    'ACL' => 'no',
                    'Status' => 'Unmonitored',
                    'RealtimeDevice' => 'no',
                ],
                [
                    'ObjectName' => '1002',
                    'ChanType' => 'SIP',
                    'ChanObjectType' => 'peer',
                    'IPaddress' => '192.168.1.101',
                    'IPport' => '5060',
                    'Dynamic' => 'yes',
                    'AutoForcerport' => 'yes',
                    'Forcerport' => 'no',
                    'AutoComedia' => 'no',
                    'Comedia' => 'no',
                    'VideoSupport' => 'no',
                    'TextSupport' => 'no',
                    'ACL' => 'no',
                    'Status' => 'OK (15 ms)',
                    'RealtimeDevice' => 'no',
                ],
            ];
        }

        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getEventList')->andReturn($peers);
        $mockResponse->shouldReceive('getKey')->andReturnUsing(function ($key) {
            if ($key === 'Response') return 'Success';
            if ($key === 'Message') return 'Peer status list will follow';
            if ($key === 'ActionID') return 'sippeers_' . uniqid();
            return null;
        });

        return $mockResponse;
    }

    /**
     * Create a response sequence for multi-response actions.
     *
     * @param array $responses Array of response configurations
     * @return array Array of mock responses
     */
    public static function createResponseSequence(array $responses): array
    {
        $mockResponses = [];

        foreach ($responses as $responseConfig) {
            $type = $responseConfig['type'] ?? 'success';
            $action = $responseConfig['action'] ?? 'Generic';
            $data = $responseConfig['data'] ?? [];

            switch ($type) {
                case 'success':
                    $mockResponses[] = PamiMockFactory::createSuccessResponse($data);
                    break;
                case 'error':
                    $message = $responseConfig['message'] ?? 'Action failed';
                    $mockResponses[] = PamiMockFactory::createErrorResponse($message, $data);
                    break;
                case 'timeout':
                    $mockResponses[] = self::createTimeoutResponse($action);
                    break;
                case 'permission_denied':
                    $mockResponses[] = self::createPermissionDeniedResponse($action);
                    break;
                default:
                    $mockResponses[] = PamiMockFactory::createSuccessResponse($data);
                    break;
            }
        }

        return $mockResponses;
    }
}