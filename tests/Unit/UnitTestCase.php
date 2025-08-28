<?php

namespace AsteriskPbxManager\Tests\Unit;

use Orchestra\Testbench\TestCase;
use AsteriskPbxManager\AsteriskPbxManagerServiceProvider;
use Mockery;

/**
 * Base test case for unit tests with Laravel integration.
 */
abstract class UnitTestCase extends TestCase
{
    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            AsteriskPbxManagerServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup basic Asterisk configuration
        $app['config']->set('asterisk-pbx-manager.connection', [
            'host' => '127.0.0.1',
            'port' => 5038,
            'username' => 'admin',
            'secret' => 'test',
            'connect_timeout' => 10,
            'read_timeout' => 10,
            'scheme' => 'tcp://',
        ]);

        $app['config']->set('asterisk-pbx-manager.events', [
            'enabled' => true,
            'broadcast' => true,
            'log_to_database' => true,
        ]);

        $app['config']->set('asterisk-pbx-manager.broadcasting', [
            'channel_prefix' => 'asterisk',
            'private_channels' => false,
        ]);
    }

    /**
     * Setup the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock PAMI client for testing.
     *
     * @return \Mockery\MockInterface
     */
    protected function createMockPamiClient(): \Mockery\MockInterface
    {
        return Mockery::mock(\PAMI\Client\Impl\ClientImpl::class);
    }

    /**
     * Create a mock PAMI response for testing.
     *
     * @param bool $success
     * @param string|null $message
     * @param array $keys
     * @return \Mockery\MockInterface
     */
    protected function createMockResponse(bool $success = true, ?string $message = null, array $keys = []): \Mockery\MockInterface
    {
        $response = Mockery::mock(\PAMI\Message\Response\ResponseMessage::class);
        $response->shouldReceive('isSuccess')->andReturn($success);
        $response->shouldReceive('getMessage')->andReturn($message);
        $response->shouldReceive('getKeys')->andReturn($keys);
        
        return $response;
    }

    /**
     * Create a mock PAMI action for testing.
     *
     * @param string $actionName
     * @param string|null $actionId
     * @return \Mockery\MockInterface
     */
    protected function createMockAction(string $actionName = 'TestAction', ?string $actionId = null): \Mockery\MockInterface
    {
        $action = Mockery::mock(\PAMI\Message\Action\ActionMessage::class);
        $action->shouldReceive('getAction')->andReturn($actionName);
        $action->shouldReceive('getActionId')->andReturn($actionId ?? uniqid());
        
        return $action;
    }

    /**
     * Create a mock PAMI event for testing.
     *
     * @param string $eventName
     * @param array $keys
     * @return \Mockery\MockInterface
     */
    protected function createMockEvent(string $eventName = 'TestEvent', array $keys = []): \Mockery\MockInterface
    {
        $event = Mockery::mock(\PAMI\Message\Event\EventMessage::class);
        $event->shouldReceive('getEventName')->andReturn($eventName);
        $event->shouldReceive('getKeys')->andReturn($keys);
        
        // Mock getKey method to return specific values
        $event->shouldReceive('getKey')->andReturnUsing(function ($key) use ($keys) {
            return $keys[$key] ?? null;
        });
        
        return $event;
    }

    /**
     * Create a mock originate action for testing.
     *
     * @param string $channel
     * @return \Mockery\MockInterface
     */
    protected function createMockOriginateAction(string $channel = 'SIP/1001'): \Mockery\MockInterface
    {
        $action = Mockery::mock(\PAMI\Message\Action\OriginateAction::class);
        $action->shouldReceive('getAction')->andReturn('Originate');
        $action->shouldReceive('getActionId')->andReturn(uniqid());
        $action->shouldReceive('setExtension')->andReturnSelf();
        $action->shouldReceive('setContext')->andReturnSelf();
        $action->shouldReceive('setPriority')->andReturnSelf();
        $action->shouldReceive('setTimeout')->andReturnSelf();
        
        return $action;
    }

    /**
     * Create a mock hangup action for testing.
     *
     * @param string $channel
     * @return \Mockery\MockInterface
     */
    protected function createMockHangupAction(string $channel = 'SIP/1001'): \Mockery\MockInterface
    {
        $action = Mockery::mock(\PAMI\Message\Action\HangupAction::class);
        $action->shouldReceive('getAction')->andReturn('Hangup');
        $action->shouldReceive('getActionId')->andReturn(uniqid());
        
        return $action;
    }

    /**
     * Create a mock core status action for testing.
     *
     * @return \Mockery\MockInterface
     */
    protected function createMockCoreStatusAction(): \Mockery\MockInterface
    {
        $action = Mockery::mock(\PAMI\Message\Action\CoreStatusAction::class);
        $action->shouldReceive('getAction')->andReturn('CoreStatus');
        $action->shouldReceive('getActionId')->andReturn(uniqid());
        
        return $action;
    }

    /**
     * Create a mock dial event for testing.
     *
     * @param string $channel
     * @param string $destination
     * @param string $subEvent
     * @return \Mockery\MockInterface
     */
    protected function createMockDialEvent(
        string $channel = 'SIP/1001-00000001',
        string $destination = 'SIP/1002-00000002',
        string $subEvent = 'Begin'
    ): \Mockery\MockInterface {
        return $this->createMockEvent('Dial', [
            'Channel' => $channel,
            'Destination' => $destination,
            'SubEvent' => $subEvent,
            'CallerIDNum' => '1001',
            'CallerIDName' => 'Test User',
            'UniqueId' => uniqid(),
        ]);
    }

    /**
     * Create a mock hangup event for testing.
     *
     * @param string $channel
     * @param string $cause
     * @param int $duration
     * @return \Mockery\MockInterface
     */
    protected function createMockHangupEvent(
        string $channel = 'SIP/1001-00000001',
        string $cause = '16',
        int $duration = 30
    ): \Mockery\MockInterface {
        return $this->createMockEvent('Hangup', [
            'Channel' => $channel,
            'Cause' => $cause,
            'Cause-txt' => 'Normal call clearing',
            'Duration' => $duration,
            'CallerIDNum' => '1001',
            'CallerIDName' => 'Test User',
            'UniqueId' => uniqid(),
        ]);
    }

    /**
     * Create a mock queue member added event for testing.
     *
     * @param string $queue
     * @param string $interface
     * @param string $memberName
     * @return \Mockery\MockInterface
     */
    protected function createMockQueueMemberAddedEvent(
        string $queue = 'test-queue',
        string $interface = 'SIP/1001',
        string $memberName = 'Test Member'
    ): \Mockery\MockInterface {
        return $this->createMockEvent('QueueMemberAdded', [
            'Queue' => $queue,
            'Interface' => $interface,
            'MemberName' => $memberName,
            'Status' => '1',
            'Penalty' => '0',
            'CallsTaken' => '5',
            'LastCall' => time() - 300,
            'Paused' => '0',
        ]);
    }

    /**
     * Assert that an exception was thrown with the expected message.
     *
     * @param string $expectedClass
     * @param string $expectedMessage
     * @param callable $callback
     */
    protected function assertExceptionThrown(string $expectedClass, string $expectedMessage, callable $callback): void
    {
        $thrown = false;
        $thrownException = null;

        try {
            $callback();
        } catch (\Exception $e) {
            $thrown = true;
            $thrownException = $e;
        }

        $this->assertTrue($thrown, "Expected exception {$expectedClass} was not thrown");
        $this->assertInstanceOf($expectedClass, $thrownException);
        $this->assertStringContainsString($expectedMessage, $thrownException->getMessage());
    }

    /**
     * Create test configuration array.
     *
     * @return array
     */
    protected function getTestConfig(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 5038,
            'username' => 'testuser',
            'secret' => 'testsecret',
            'connect_timeout' => 10,
            'read_timeout' => 10,
            'scheme' => 'tcp://',
        ];
    }
}