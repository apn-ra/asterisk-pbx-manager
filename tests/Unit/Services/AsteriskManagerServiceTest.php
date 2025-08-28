<?php

namespace AsteriskPbxManager\Tests\Unit\Services;

use AsteriskPbxManager\Tests\Unit\UnitTestCase;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\HangupAction;
use PAMI\Message\Action\CoreStatusAction;
use PAMI\Message\Response\ResponseMessage;
use Mockery;
use Illuminate\Support\Facades\Log;

class AsteriskManagerServiceTest extends UnitTestCase
{
    private AsteriskManagerService $service;
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockClient = $this->createMockPamiClient();
        $this->service = new AsteriskManagerService($this->mockClient);
    }

    public function testConstructorSetsClientCorrectly()
    {
        $client = $this->createMockPamiClient();
        $service = new AsteriskManagerService($client);
        
        $this->assertInstanceOf(AsteriskManagerService::class, $service);
    }

    public function testConnectSuccess()
    {
        $this->mockClient
            ->shouldReceive('open')
            ->once()
            ->andReturn(true);
            
        $this->mockClient
            ->shouldReceive('registerEventListener')
            ->once()
            ->with(Mockery::type('callable'));

        Log::shouldReceive('info')
            ->once()
            ->with('Connected to Asterisk Manager Interface', Mockery::any());

        $result = $this->service->connect();
        
        $this->assertTrue($result);
    }

    public function testConnectFailure()
    {
        $this->mockClient
            ->shouldReceive('open')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to connect to Asterisk: Connection failed', Mockery::any());

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Connection failed');
        
        $this->service->connect();
    }

    public function testDisconnectSuccess()
    {
        $this->mockClient
            ->shouldReceive('close')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Disconnected from Asterisk Manager Interface', Mockery::any());

        $result = $this->service->disconnect();
        
        $this->assertTrue($result);
    }

    public function testDisconnectFailure()
    {
        $this->mockClient
            ->shouldReceive('close')
            ->once()
            ->andThrow(new \Exception('Disconnect failed'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to disconnect from Asterisk: Disconnect failed', Mockery::any());

        $result = $this->service->disconnect();
        
        $this->assertFalse($result);
    }

    public function testIsConnectedWhenConnected()
    {
        $this->mockClient
            ->shouldReceive('isLoggedIn')
            ->once()
            ->andReturn(true);

        $result = $this->service->isConnected();
        
        $this->assertTrue($result);
    }

    public function testIsConnectedWhenNotConnected()
    {
        $this->mockClient
            ->shouldReceive('isLoggedIn')
            ->once()
            ->andReturn(false);

        $result = $this->service->isConnected();
        
        $this->assertFalse($result);
    }

    public function testReconnectSuccess()
    {
        // First call to isConnected returns false (not connected)
        $this->mockClient
            ->shouldReceive('isLoggedIn')
            ->once()
            ->andReturn(false);

        // Close existing connection
        $this->mockClient
            ->shouldReceive('close')
            ->once()
            ->andReturn(true);

        // Open new connection
        $this->mockClient
            ->shouldReceive('open')
            ->once()
            ->andReturn(true);

        // Register event listeners
        $this->mockClient
            ->shouldReceive('registerEventListener')
            ->once()
            ->with(Mockery::type('callable'));

        Log::shouldReceive('info')->twice();

        $result = $this->service->reconnect();
        
        $this->assertTrue($result);
    }

    public function testReconnectWhenAlreadyConnected()
    {
        $this->mockClient
            ->shouldReceive('isLoggedIn')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Already connected to Asterisk Manager Interface', Mockery::any());

        $result = $this->service->reconnect();
        
        $this->assertTrue($result);
    }

    public function testSendActionSuccess()
    {
        $mockAction = $this->createMockOriginateAction('SIP/1001');
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockClient
            ->shouldReceive('send')
            ->once()
            ->with($mockAction)
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('AMI action sent successfully', Mockery::any());

        $result = $this->service->send($mockAction);
        
        $this->assertSame($mockResponse, $result);
    }

    public function testSendActionFailure()
    {
        $mockAction = $this->createMockOriginateAction('SIP/1001');

        $this->mockClient
            ->shouldReceive('send')
            ->once()
            ->with($mockAction)
            ->andThrow(new \Exception('Send failed'));

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to send AMI action: Send failed', Mockery::any());

        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Send failed');
        
        $this->service->send($mockAction);
    }

    public function testOriginateCallSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockClient
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(OriginateAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('AMI action sent successfully', Mockery::any());

        Log::shouldReceive('info')
            ->once()
            ->with('Call originated successfully', Mockery::any());

        $result = $this->service->originateCall('SIP/1001', '2002', 'default', 1, 30);
        
        $this->assertTrue($result);
    }

    public function testOriginateCallFailure()
    {
        $mockResponse = $this->createMockResponse(false, 'Failed', []);

        $this->mockClient
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(OriginateAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('AMI action sent successfully', Mockery::any());

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to originate call', Mockery::any());

        $result = $this->service->originateCall('SIP/1001', '2002', 'default', 1, 30);
        
        $this->assertFalse($result);
    }

    public function testOriginateCallWithInvalidChannel()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter channel cannot be empty');
        
        $this->service->originateCall('', '2002', 'default', 1, 30);
    }

    public function testHangupCallSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', []);

        $this->mockClient
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(HangupAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')->twice();

        $result = $this->service->hangupCall('SIP/1001-12345');
        
        $this->assertTrue($result);
    }

    public function testHangupCallFailure()
    {
        $mockResponse = $this->createMockResponse(false, 'Channel not found', []);

        $this->mockClient
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(HangupAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('AMI action sent successfully', Mockery::any());

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to hangup call', Mockery::any());

        $result = $this->service->hangupCall('SIP/1001-12345');
        
        $this->assertFalse($result);
    }

    public function testHangupCallWithInvalidChannel()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter channel cannot be empty');
        
        $this->service->hangupCall('');
    }

    public function testGetStatusSuccess()
    {
        $mockResponse = $this->createMockResponse(true, 'Success', [
            'CoreCurrentCalls' => '5',
            'CoreMaxCalls' => '100',
            'CoreReloadTime' => '2024-08-28 07:38:00'
        ]);

        $this->mockClient
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(CoreStatusAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')->twice();

        $result = $this->service->getStatus();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('current_calls', $result);
        $this->assertArrayHasKey('max_calls', $result);
        $this->assertArrayHasKey('reload_time', $result);
        $this->assertEquals('5', $result['current_calls']);
        $this->assertEquals('100', $result['max_calls']);
        $this->assertEquals('2024-08-28 07:38:00', $result['reload_time']);
    }

    public function testGetStatusFailure()
    {
        $mockResponse = $this->createMockResponse(false, 'Permission denied', []);

        $this->mockClient
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(CoreStatusAction::class))
            ->andReturn($mockResponse);

        Log::shouldReceive('info')
            ->once()
            ->with('AMI action sent successfully', Mockery::any());

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get system status', Mockery::any());

        $result = $this->service->getStatus();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAddEventListener()
    {
        $listener = function($event) {
            // Test listener
        };

        $result = $this->service->addEventListener($listener);
        
        $this->assertSame($this->service, $result);
    }



    public function testDestructor()
    {
        $this->mockClient
            ->shouldReceive('isLoggedIn')
            ->once()
            ->andReturn(true);

        $this->mockClient
            ->shouldReceive('close')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('info')
            ->once()
            ->with('Disconnected from Asterisk Manager Interface', Mockery::any());

        // Manually trigger destructor
        unset($this->service);
    }
}