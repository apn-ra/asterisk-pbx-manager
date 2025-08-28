<?php

namespace AsteriskPbxManager\Tests\Unit\Mocks;

use Mockery;
use Mockery\MockInterface;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\ActionMessage;
use PAMI\Message\Event\EventMessage;
use PAMI\Message\Response\ResponseMessage;

/**
 * Factory for creating PAMI mocks for testing.
 */
class PamiMockFactory
{
    /**
     * Create a mock PAMI client.
     *
     * @param array $methods Methods to mock with their return values
     *
     * @return MockInterface
     */
    public static function createClient(array $methods = []): MockInterface
    {
        $mockClient = Mockery::mock(ClientImpl::class);

        // Default behaviors
        $defaultMethods = [
            'open'                  => true,
            'close'                 => true,
            'isConnected'           => true,
            'send'                  => self::createSuccessResponse(),
            'registerEventListener' => true,
        ];

        // Merge with provided methods
        $methods = array_merge($defaultMethods, $methods);

        foreach ($methods as $method => $returnValue) {
            if (is_callable($returnValue)) {
                $mockClient->shouldReceive($method)->andReturnUsing($returnValue);
            } elseif (is_array($returnValue) && isset($returnValue['exception'])) {
                $mockClient->shouldReceive($method)->andThrow($returnValue['exception']);
            } else {
                $mockClient->shouldReceive($method)->andReturn($returnValue);
            }
        }

        return $mockClient;
    }

    /**
     * Create a connected PAMI client mock.
     *
     * @return MockInterface
     */
    public static function createConnectedClient(): MockInterface
    {
        return self::createClient([
            'open'        => true,
            'isConnected' => true,
            'close'       => true,
        ]);
    }

    /**
     * Create a disconnected PAMI client mock.
     *
     * @return MockInterface
     */
    public static function createDisconnectedClient(): MockInterface
    {
        return self::createClient([
            'open'        => ['exception' => new \Exception('Connection failed')],
            'isConnected' => false,
            'send'        => ['exception' => new \Exception('Not connected')],
        ]);
    }

    /**
     * Create a PAMI client that fails after some operations.
     *
     * @param int $failAfter Number of successful operations before failure
     *
     * @return MockInterface
     */
    public static function createUnstableClient(int $failAfter = 3): MockInterface
    {
        $operationCount = 0;

        return self::createClient([
            'send' => function () use (&$operationCount, $failAfter) {
                $operationCount++;
                if ($operationCount > $failAfter) {
                    throw new \Exception('Connection lost');
                }

                return self::createSuccessResponse();
            },
        ]);
    }

    /**
     * Create a success response mock.
     *
     * @param array $keys Additional response keys
     *
     * @return MockInterface
     */
    public static function createSuccessResponse(array $keys = []): MockInterface
    {
        $mockResponse = Mockery::mock(ResponseMessage::class);

        $defaultKeys = [
            'Response' => 'Success',
            'Message'  => 'Command completed successfully',
            'ActionID' => 'action_'.uniqid(),
        ];

        $keys = array_merge($defaultKeys, $keys);

        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getKey')->andReturnUsing(function ($key) use ($keys) {
            return $keys[$key] ?? null;
        });
        $mockResponse->shouldReceive('getKeys')->andReturn($keys);
        $mockResponse->shouldReceive('getMessage')->andReturn($keys['Message']);
        $mockResponse->shouldReceive('getActionId')->andReturn($keys['ActionID']);

        return $mockResponse;
    }

    /**
     * Create an error response mock.
     *
     * @param string $message Error message
     * @param array  $keys    Additional response keys
     *
     * @return MockInterface
     */
    public static function createErrorResponse(string $message = 'Command failed', array $keys = []): MockInterface
    {
        $mockResponse = Mockery::mock(ResponseMessage::class);

        $defaultKeys = [
            'Response' => 'Error',
            'Message'  => $message,
            'ActionID' => 'action_'.uniqid(),
        ];

        $keys = array_merge($defaultKeys, $keys);

        $mockResponse->shouldReceive('isSuccess')->andReturn(false);
        $mockResponse->shouldReceive('getKey')->andReturnUsing(function ($key) use ($keys) {
            return $keys[$key] ?? null;
        });
        $mockResponse->shouldReceive('getKeys')->andReturn($keys);
        $mockResponse->shouldReceive('getMessage')->andReturn($keys['Message']);
        $mockResponse->shouldReceive('getActionId')->andReturn($keys['ActionID']);

        return $mockResponse;
    }

    /**
     * Create a mock AMI event.
     *
     * @param string $eventName Event name
     * @param array  $keys      Event data keys
     *
     * @return MockInterface
     */
    public static function createEvent(string $eventName, array $keys = []): MockInterface
    {
        $mockEvent = Mockery::mock(EventMessage::class);

        $defaultKeys = [
            'Event'     => $eventName,
            'Privilege' => 'system,all',
            'Timestamp' => time(),
        ];

        $keys = array_merge($defaultKeys, $keys);

        $mockEvent->shouldReceive('getEventName')->andReturn($eventName);
        $mockEvent->shouldReceive('getKey')->andReturnUsing(function ($key) use ($keys) {
            return $keys[$key] ?? null;
        });
        $mockEvent->shouldReceive('getKeys')->andReturn($keys);

        return $mockEvent;
    }

    /**
     * Create a Dial event mock.
     *
     * @param array $customKeys Custom event keys
     *
     * @return MockInterface
     */
    public static function createDialEvent(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Channel'              => 'SIP/1001-00000001',
            'ChannelState'         => '4',
            'ChannelStateDesc'     => 'Ring',
            'CallerIDNum'          => '1001',
            'CallerIDName'         => 'John Doe',
            'ConnectedLineNum'     => '1002',
            'ConnectedLineName'    => 'Jane Smith',
            'UniqueID'             => '1234567890.1',
            'DestChannel'          => 'SIP/1002-00000002',
            'DestChannelState'     => '5',
            'DestChannelStateDesc' => 'Ringing',
            'DestUniqueID'         => '1234567890.2',
            'DialStatus'           => 'ANSWER',
            'SubEvent'             => 'Begin',
            'Context'              => 'internal',
            'Extension'            => '1002',
            'Priority'             => '1',
        ];

        return self::createEvent('Dial', array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a Hangup event mock.
     *
     * @param array $customKeys Custom event keys
     *
     * @return MockInterface
     */
    public static function createHangupEvent(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Channel'           => 'SIP/1001-00000001',
            'ChannelState'      => '6',
            'ChannelStateDesc'  => 'Up',
            'CallerIDNum'       => '1001',
            'CallerIDName'      => 'John Doe',
            'ConnectedLineNum'  => '1002',
            'ConnectedLineName' => 'Jane Smith',
            'UniqueID'          => '1234567890.1',
            'Cause'             => '16',
            'CauseTxt'          => 'Normal Clearing',
            'Context'           => 'internal',
            'Extension'         => '1002',
            'Priority'          => '1',
        ];

        return self::createEvent('Hangup', array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a QueueMemberAdded event mock.
     *
     * @param array $customKeys Custom event keys
     *
     * @return MockInterface
     */
    public static function createQueueMemberAddedEvent(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Queue'          => 'support',
            'Interface'      => 'SIP/1001',
            'MemberName'     => 'Agent 1001',
            'StateInterface' => 'SIP/1001',
            'Membership'     => 'dynamic',
            'Penalty'        => '0',
            'CallsTaken'     => '0',
            'LastCall'       => '0',
            'LastPause'      => '0',
            'InCall'         => '0',
            'Status'         => '1',
            'Paused'         => '0',
        ];

        return self::createEvent('QueueMemberAdded', array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a Bridge event mock.
     *
     * @param array $customKeys Custom event keys
     *
     * @return MockInterface
     */
    public static function createBridgeEvent(array $customKeys = []): MockInterface
    {
        $defaultKeys = [
            'Channel1'    => 'SIP/1001-00000001',
            'Channel2'    => 'SIP/1002-00000002',
            'UniqueID1'   => '1234567890.1',
            'UniqueID2'   => '1234567890.2',
            'CallerID1'   => '1001',
            'CallerID2'   => '1002',
            'Bridgestate' => 'Link',
            'Bridgetype'  => 'core',
        ];

        return self::createEvent('Bridge', array_merge($defaultKeys, $customKeys));
    }

    /**
     * Create a mock action message.
     *
     * @param string $action Action name
     * @param array  $keys   Action parameters
     *
     * @return MockInterface
     */
    public static function createAction(string $action, array $keys = []): MockInterface
    {
        $mockAction = Mockery::mock(ActionMessage::class);

        $defaultKeys = [
            'Action'   => $action,
            'ActionID' => 'action_'.uniqid(),
        ];

        $keys = array_merge($defaultKeys, $keys);

        $mockAction->shouldReceive('getAction')->andReturn($action);
        $mockAction->shouldReceive('getActionId')->andReturn($keys['ActionID']);
        $mockAction->shouldReceive('getKey')->andReturnUsing(function ($key) use ($keys) {
            return $keys[$key] ?? null;
        });
        $mockAction->shouldReceive('getKeys')->andReturn($keys);
        $mockAction->shouldReceive('setKey')->andReturnSelf();
        $mockAction->shouldReceive('serialize')->andReturn(http_build_query($keys));

        return $mockAction;
    }

    /**
     * Create an Originate action mock.
     *
     * @param array $parameters Action parameters
     *
     * @return MockInterface
     */
    public static function createOriginateAction(array $parameters = []): MockInterface
    {
        $defaultParams = [
            'Channel'   => 'SIP/1001',
            'Context'   => 'internal',
            'Extension' => '1002',
            'Priority'  => '1',
            'Timeout'   => '30000',
            'CallerID'  => '1001',
        ];

        return self::createAction('Originate', array_merge($defaultParams, $parameters));
    }

    /**
     * Create a Hangup action mock.
     *
     * @param array $parameters Action parameters
     *
     * @return MockInterface
     */
    public static function createHangupAction(array $parameters = []): MockInterface
    {
        $defaultParams = [
            'Channel' => 'SIP/1001-00000001',
            'Cause'   => '16',
        ];

        return self::createAction('Hangup', array_merge($defaultParams, $parameters));
    }

    /**
     * Create a client with event simulation capabilities.
     *
     * @param array $events Events to simulate
     *
     * @return MockInterface
     */
    public static function createEventSimulatingClient(array $events = []): MockInterface
    {
        $eventListeners = [];

        $mockClient = self::createClient();

        $mockClient->shouldReceive('registerEventListener')
            ->andReturnUsing(function ($callback, $predicate = null) use (&$eventListeners) {
                $eventListeners[] = ['callback' => $callback, 'predicate' => $predicate];

                return true;
            });

        // Add method to simulate events
        $mockClient->shouldReceive('simulateEvent')
            ->andReturnUsing(function ($event) use (&$eventListeners) {
                foreach ($eventListeners as $listener) {
                    $predicate = $listener['predicate'];
                    if ($predicate === null || $predicate($event)) {
                        call_user_func($listener['callback'], $event);
                    }
                }
            });

        // Simulate provided events automatically
        foreach ($events as $event) {
            $mockClient->simulateEvent($event);
        }

        return $mockClient;
    }

    /**
     * Create a client that simulates connection timeouts.
     *
     * @param int $timeoutAfterSeconds Seconds before timeout
     *
     * @return MockInterface
     */
    public static function createTimeoutClient(int $timeoutAfterSeconds = 5): MockInterface
    {
        return self::createClient([
            'open' => function () use ($timeoutAfterSeconds) {
                sleep($timeoutAfterSeconds);

                throw new \Exception('Connection timeout');
            },
            'send' => function () {
                throw new \Exception('Request timeout');
            },
        ]);
    }

    /**
     * Create a performance testing client with delays.
     *
     * @param float $delaySeconds Delay in seconds for each operation
     *
     * @return MockInterface
     */
    public static function createSlowClient(float $delaySeconds = 1.0): MockInterface
    {
        return self::createClient([
            'send' => function () use ($delaySeconds) {
                usleep($delaySeconds * 1000000);

                return self::createSuccessResponse();
            },
        ]);
    }
}
