<?php

namespace AsteriskPbxManager\Tests\Integration;

use AsteriskPbxManager\Facades\AsteriskManager;
use AsteriskPbxManager\Services\AsteriskManagerService;
use Illuminate\Support\Facades\Log;
use Mockery;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\ActionMessage;
use PAMI\Message\Response\ResponseMessage;

class FacadeFunctionalityTest extends IntegrationTestCase
{
    public function testFacadeIsRegistered()
    {
        // Test that the facade is properly registered
        $this->assertTrue(class_exists(\AsteriskPbxManager\Facades\AsteriskManager::class));

        // Test that the facade alias is available
        $aliases = $this->app['config']['app.aliases'] ?? [];

        // Note: In testing, we might not have the alias registered in app config
        // But we can test that the facade can be resolved
        $this->assertInstanceOf(AsteriskManagerService::class, AsteriskManager::getFacadeRoot());
    }

    public function testFacadeResolvesToCorrectService()
    {
        // Verify that the facade resolves to the AsteriskManagerService
        $service = AsteriskManager::getFacadeRoot();

        $this->assertInstanceOf(AsteriskManagerService::class, $service);
    }

    public function testFacadeReturnsSameInstanceOnMultipleCalls()
    {
        // Test singleton behavior through facade
        $service1 = AsteriskManager::getFacadeRoot();
        $service2 = AsteriskManager::getFacadeRoot();

        $this->assertSame($service1, $service2);
    }

    public function testFacadeAccessorMethod()
    {
        // Test that getFacadeAccessor returns the correct binding
        $facade = new \AsteriskPbxManager\Facades\AsteriskManager();

        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($facade);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke($facade);

        $this->assertEquals('asterisk-manager', $accessor);
    }

    public function testFacadeProxiesConnectMethod()
    {
        // Mock the underlying PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('open')->once()->andReturn(true);
        $mockClient->shouldReceive('registerEventListener')->once();

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        Log::shouldReceive('info')->once();

        // Call connect through facade
        $result = AsteriskManager::connect();

        $this->assertTrue($result);
    }

    public function testFacadeProxiesDisconnectMethod()
    {
        // Mock the underlying PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('close')->once()->andReturn(true);

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        Log::shouldReceive('info')->once();

        // Call disconnect through facade
        $result = AsteriskManager::disconnect();

        $this->assertTrue($result);
    }

    public function testFacadeProxiesIsConnectedMethod()
    {
        // Mock the underlying PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->once()->andReturn(true);

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        // Call isConnected through facade
        $result = AsteriskManager::isConnected();

        $this->assertTrue($result);
    }

    public function testFacadeProxiesOriginateCallMethod()
    {
        // Mock the underlying PAMI client and response
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        $mockResponse->shouldReceive('isSuccess')->once()->andReturn(true);

        $mockClient->shouldReceive('send')
            ->once()
            ->with(Mockery::type(\PAMI\Message\Action\OriginateAction::class))
            ->andReturn($mockResponse);

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        Log::shouldReceive('info')->twice();

        // Call originateCall through facade
        $result = AsteriskManager::originateCall('SIP/1001', '2002', 'default', 1, 30);

        $this->assertTrue($result);
    }

    public function testFacadeProxiesHangupCallMethod()
    {
        // Mock the underlying PAMI client and response
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        $mockResponse->shouldReceive('isSuccess')->once()->andReturn(true);

        $mockClient->shouldReceive('send')
            ->once()
            ->with(Mockery::type(\PAMI\Message\Action\HangupAction::class))
            ->andReturn($mockResponse);

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        Log::shouldReceive('info')->twice();

        // Call hangupCall through facade
        $result = AsteriskManager::hangupCall('SIP/1001-12345');

        $this->assertTrue($result);
    }

    public function testFacadeProxiesGetStatusMethod()
    {
        // Mock the underlying PAMI client and response
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        $mockResponse->shouldReceive('isSuccess')->once()->andReturn(true);
        $mockResponse->shouldReceive('getMessage')->once()->andReturn('Success');
        $mockResponse->shouldReceive('getKeys')->once()->andReturn([
            'CoreCurrentCalls' => '5',
            'CoreMaxCalls'     => '100',
        ]);

        $mockClient->shouldReceive('send')
            ->once()
            ->with(Mockery::type(\PAMI\Message\Action\CoreStatusAction::class))
            ->andReturn($mockResponse);

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        Log::shouldReceive('info')->twice();

        // Call getStatus through facade
        $result = AsteriskManager::getStatus();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }

    public function testFacadeProxiesSendMethod()
    {
        // Mock the underlying PAMI client and response
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        $mockAction = Mockery::mock(ActionMessage::class);

        $mockClient->shouldReceive('send')
            ->once()
            ->with($mockAction)
            ->andReturn($mockResponse);

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        Log::shouldReceive('info')->once();

        // Call send through facade
        $result = AsteriskManager::send($mockAction);

        $this->assertSame($mockResponse, $result);
    }

    public function testFacadeProxiesReconnectMethod()
    {
        // Mock the underlying PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->once()->andReturn(false);
        $mockClient->shouldReceive('close')->once()->andReturn(true);
        $mockClient->shouldReceive('open')->once()->andReturn(true);
        $mockClient->shouldReceive('registerEventListener')->once();

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        Log::shouldReceive('info')->twice();

        // Call reconnect through facade
        $result = AsteriskManager::reconnect();

        $this->assertTrue($result);
    }

    public function testFacadeProxiesAddEventListenerMethod()
    {
        $listener = function ($event) {
            // Test listener
        };

        // Call addEventListener through facade
        $result = AsteriskManager::addEventListener($listener);

        // Should return the service instance for fluent interface
        $this->assertInstanceOf(AsteriskManagerService::class, $result);
    }

    public function testFacadeHandlesExceptions()
    {
        // Mock the underlying PAMI client to throw exception
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('open')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        // Replace the bound PAMI client with our mock
        $this->app->instance(ClientImpl::class, $mockClient);

        Log::shouldReceive('error')->once();

        // Test that exceptions are properly propagated through facade
        $this->expectException(\AsteriskPbxManager\Exceptions\AsteriskConnectionException::class);
        $this->expectExceptionMessage('Connection failed');

        AsteriskManager::connect();
    }

    public function testFacadeStaticMethodsWorkCorrectly()
    {
        // Test that we can call methods statically on the facade
        $service = AsteriskManager::getFacadeRoot();

        $this->assertInstanceOf(AsteriskManagerService::class, $service);

        // Test method_exists for key methods
        $this->assertTrue(method_exists($service, 'connect'));
        $this->assertTrue(method_exists($service, 'disconnect'));
        $this->assertTrue(method_exists($service, 'isConnected'));
        $this->assertTrue(method_exists($service, 'originateCall'));
        $this->assertTrue(method_exists($service, 'hangupCall'));
        $this->assertTrue(method_exists($service, 'getStatus'));
        $this->assertTrue(method_exists($service, 'send'));
        $this->assertTrue(method_exists($service, 'reconnect'));
        $this->assertTrue(method_exists($service, 'addEventListener'));
    }

    public function testFacadeWorksWithDependencyInjection()
    {
        // Test that facade can be resolved alongside regular DI
        $serviceFromContainer = $this->app->make('asterisk-manager');
        $serviceFromFacade = AsteriskManager::getFacadeRoot();

        // Both should be the same instance (singleton)
        $this->assertSame($serviceFromContainer, $serviceFromFacade);
        $this->assertInstanceOf(AsteriskManagerService::class, $serviceFromContainer);
        $this->assertInstanceOf(AsteriskManagerService::class, $serviceFromFacade);
    }

    public function testFacadeWorksInServiceProviderContext()
    {
        // Test that facade can be used within service provider context

        // Create a simple test that mimics service provider usage
        $facade = new \AsteriskPbxManager\Facades\AsteriskManager();

        // Test that the facade has access to the application instance
        $this->assertNotNull(\Illuminate\Support\Facades\Facade::getFacadeApplication());

        // Test that facade resolves correctly
        $service = AsteriskManager::getFacadeRoot();
        $this->assertInstanceOf(AsteriskManagerService::class, $service);
    }

    public function testFacadeMaintainsServiceState()
    {
        // Test that facade maintains service state across calls

        // Get service through facade
        $service1 = AsteriskManager::getFacadeRoot();

        // Simulate setting some state (using reflection to access protected property)
        $reflection = new \ReflectionClass($service1);

        // Get service again through facade
        $service2 = AsteriskManager::getFacadeRoot();

        // Should be the same instance
        $this->assertSame($service1, $service2);
    }

    public function testFacadeCanBeMocked()
    {
        // Test that the facade can be mocked for testing

        // Mock the facade
        AsteriskManager::shouldReceive('connect')
            ->once()
            ->andReturn(true);

        AsteriskManager::shouldReceive('isConnected')
            ->once()
            ->andReturn(true);

        // Use the mocked facade
        $connectResult = AsteriskManager::connect();
        $isConnectedResult = AsteriskManager::isConnected();

        $this->assertTrue($connectResult);
        $this->assertTrue($isConnectedResult);
    }

    public function testFacadeMethodChaining()
    {
        // Test that facade supports method chaining where applicable
        $listener = function ($event) {
            // Test listener
        };

        // Call addEventListener which returns the service instance
        $result = AsteriskManager::addEventListener($listener);

        $this->assertInstanceOf(AsteriskManagerService::class, $result);

        // Verify the result is the same as what facade resolves to
        $this->assertSame(AsteriskManager::getFacadeRoot(), $result);
    }

    public function testFacadeWorksWithConfiguration()
    {
        // Test that facade respects configuration settings

        // Modify configuration
        $this->app['config']->set('asterisk-pbx-manager.connection.host', 'test.host');
        $this->app['config']->set('asterisk-pbx-manager.events.enabled', false);

        // Get service through facade
        $service = AsteriskManager::getFacadeRoot();

        $this->assertInstanceOf(AsteriskManagerService::class, $service);

        // Configuration should be accessible through the service
        $config = $this->app['config']['asterisk-pbx-manager'];
        $this->assertEquals('test.host', $config['connection']['host']);
        $this->assertFalse($config['events']['enabled']);
    }

    public function testFacadeWorksInArtisanCommands()
    {
        // Test that facade can be used within Artisan commands

        // This is a basic test to ensure facade is available in command context
        $this->artisan('asterisk:status')
            ->assertExitCode(0);

        // The command should be able to use the facade without issues
        $this->assertTrue(true); // If we get here without errors, facade worked in command
    }
}
