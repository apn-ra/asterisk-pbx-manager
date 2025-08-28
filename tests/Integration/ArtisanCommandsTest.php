<?php

namespace AsteriskPbxManager\Tests\Integration;

use AsteriskPbxManager\Tests\Integration\IntegrationTestCase;
use AsteriskPbxManager\Commands\AsteriskStatus;
use AsteriskPbxManager\Commands\MonitorEvents;
use AsteriskPbxManager\Services\AsteriskManagerService;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Response\ResponseMessage;
use PAMI\Message\Event\EventMessage;
use Mockery;
use Illuminate\Support\Facades\Log;

class ArtisanCommandsTest extends IntegrationTestCase
{
    public function testAsteriskStatusCommandIsRegistered()
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();
        
        $this->assertArrayHasKey('asterisk:status', $commands);
        $this->assertInstanceOf(AsteriskStatus::class, $commands['asterisk:status']);
    }

    public function testMonitorEventsCommandIsRegistered()
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();
        
        $this->assertArrayHasKey('asterisk:monitor-events', $commands);
        $this->assertInstanceOf(MonitorEvents::class, $commands['asterisk:monitor-events']);
    }

    public function testAsteriskStatusCommandExecutesSuccessfully()
    {
        // Mock the PAMI client and response
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getKeys')->andReturn([
            'CoreCurrentCalls' => '5',
            'CoreMaxCalls' => '100',
            'CoreReloadTime' => '2024-08-28 07:38:00',
            'CoreStartupTime' => '2024-08-28 06:00:00'
        ]);
        
        $mockClient->shouldReceive('send')
            ->once()
            ->with(Mockery::type(\PAMI\Message\Action\CoreStatusAction::class))
            ->andReturn($mockResponse);
        
        // Mock connection methods
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        Log::shouldReceive('info')->times(2);
        
        $this->artisan('asterisk:status')
            ->expectsOutput('Asterisk System Status')
            ->expectsOutput('Current Calls: 5')
            ->expectsOutput('Maximum Calls: 100')
            ->assertExitCode(0);
    }

    public function testAsteriskStatusCommandHandlesConnectionFailure()
    {
        // Mock the PAMI client to simulate connection failure
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->andReturn(false);
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        $this->artisan('asterisk:status')
            ->expectsOutput('Error: Not connected to Asterisk server')
            ->assertExitCode(1);
    }

    public function testAsteriskStatusCommandHandlesActionFailure()
    {
        // Mock the PAMI client with failed response
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(false);
        $mockResponse->shouldReceive('getMessage')->andReturn('Permission denied');
        
        $mockClient->shouldReceive('send')
            ->once()
            ->with(Mockery::type(\PAMI\Message\Action\CoreStatusAction::class))
            ->andReturn($mockResponse);
        
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();
        
        $this->artisan('asterisk:status')
            ->expectsOutput('Error retrieving status: Permission denied')
            ->assertExitCode(1);
    }

    public function testAsteriskStatusCommandWithVerboseOption()
    {
        // Mock the PAMI client and response with more detailed data
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getKeys')->andReturn([
            'CoreCurrentCalls' => '15',
            'CoreMaxCalls' => '200',
            'CoreReloadTime' => '2024-08-28 07:38:00',
            'CoreStartupTime' => '2024-08-28 06:00:00',
            'CoreRunningThreads' => '25',
            'CoreMaxFilehandles' => '1024'
        ]);
        
        $mockClient->shouldReceive('send')
            ->once()
            ->with(Mockery::type(\PAMI\Message\Action\CoreStatusAction::class))
            ->andReturn($mockResponse);
        
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        Log::shouldReceive('info')->times(2);
        
        $this->artisan('asterisk:status', ['--verbose' => true])
            ->expectsOutput('Asterisk System Status (Verbose)')
            ->expectsOutput('Current Calls: 15')
            ->expectsOutput('Maximum Calls: 200')
            ->expectsOutput('Running Threads: 25')
            ->expectsOutput('Max File Handles: 1024')
            ->assertExitCode(0);
    }

    public function testAsteriskStatusCommandWithJsonOutput()
    {
        // Mock the PAMI client and response
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $responseData = [
            'CoreCurrentCalls' => '5',
            'CoreMaxCalls' => '100',
            'CoreReloadTime' => '2024-08-28 07:38:00',
        ];
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getKeys')->andReturn($responseData);
        
        $mockClient->shouldReceive('send')
            ->once()
            ->with(Mockery::type(\PAMI\Message\Action\CoreStatusAction::class))
            ->andReturn($mockResponse);
        
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        Log::shouldReceive('info')->times(2);
        
        $expectedJson = json_encode([
            'success' => true,
            'data' => $responseData
        ], JSON_PRETTY_PRINT);
        
        $this->artisan('asterisk:status', ['--json' => true])
            ->expectsOutput($expectedJson)
            ->assertExitCode(0);
    }

    public function testMonitorEventsCommandExecutesSuccessfully()
    {
        // Mock the PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Mock event listener registration
        $mockClient->shouldReceive('registerEventListener')
            ->once()
            ->with(Mockery::type('callable'));
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        // Since this is a long-running command, we'll test that it starts successfully
        // and then simulate a graceful shutdown
        $this->artisan('asterisk:monitor-events', ['--max-events' => 1])
            ->expectsOutput('Starting Asterisk event monitoring...')
            ->expectsOutput('Press Ctrl+C to stop monitoring')
            ->assertExitCode(0);
    }

    public function testMonitorEventsCommandHandlesConnectionFailure()
    {
        // Mock the PAMI client to simulate connection failure
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->andReturn(false);
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        $this->artisan('asterisk:monitor-events')
            ->expectsOutput('Error: Not connected to Asterisk server')
            ->assertExitCode(1);
    }

    public function testMonitorEventsCommandWithEventTypeFilter()
    {
        // Mock the PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Mock event listener registration
        $mockClient->shouldReceive('registerEventListener')
            ->once()
            ->with(Mockery::type('callable'));
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        $this->artisan('asterisk:monitor-events', [
            '--filter' => 'Dial',
            '--max-events' => 1
        ])
            ->expectsOutput('Starting Asterisk event monitoring...')
            ->expectsOutput('Event filter: Dial')
            ->assertExitCode(0);
    }

    public function testMonitorEventsCommandWithVerboseOutput()
    {
        // Mock the PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Mock event listener registration
        $mockClient->shouldReceive('registerEventListener')
            ->once()
            ->with(Mockery::type('callable'));
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        $this->artisan('asterisk:monitor-events', [
            '--verbose' => true,
            '--max-events' => 1
        ])
            ->expectsOutput('Starting Asterisk event monitoring (Verbose mode)...')
            ->expectsOutput('Monitoring all event types')
            ->assertExitCode(0);
    }

    public function testMonitorEventsCommandWithOutputFile()
    {
        // Mock the PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Mock event listener registration
        $mockClient->shouldReceive('registerEventListener')
            ->once()
            ->with(Mockery::type('callable'));
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        $outputFile = storage_path('app/test_events.log');
        
        $this->artisan('asterisk:monitor-events', [
            '--output' => $outputFile,
            '--max-events' => 1
        ])
            ->expectsOutput('Starting Asterisk event monitoring...')
            ->expectsOutput("Events will be logged to: {$outputFile}")
            ->assertExitCode(0);
        
        // Clean up test file if it was created
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }

    public function testCommandsHaveProperSignatures()
    {
        // Test AsteriskStatus command signature
        $statusCommand = $this->app->make(AsteriskStatus::class);
        $this->assertEquals('asterisk:status', $statusCommand->getName());
        $this->assertStringContains('Display Asterisk system status', $statusCommand->getDescription());
        
        // Test MonitorEvents command signature
        $monitorCommand = $this->app->make(MonitorEvents::class);
        $this->assertEquals('asterisk:monitor-events', $monitorCommand->getName());
        $this->assertStringContains('Monitor Asterisk events in real-time', $monitorCommand->getDescription());
    }

    public function testCommandsHaveProperOptions()
    {
        // Test AsteriskStatus command options
        $statusCommand = $this->app->make(AsteriskStatus::class);
        $statusDefinition = $statusCommand->getDefinition();
        
        $this->assertTrue($statusDefinition->hasOption('verbose'));
        $this->assertTrue($statusDefinition->hasOption('json'));
        
        // Test MonitorEvents command options
        $monitorCommand = $this->app->make(MonitorEvents::class);
        $monitorDefinition = $monitorCommand->getDefinition();
        
        $this->assertTrue($monitorDefinition->hasOption('filter'));
        $this->assertTrue($monitorDefinition->hasOption('verbose'));
        $this->assertTrue($monitorDefinition->hasOption('output'));
        $this->assertTrue($monitorDefinition->hasOption('max-events'));
    }

    public function testCommandsCanAccessAsteriskManagerService()
    {
        // Mock the service to verify it's accessible from commands
        $mockService = Mockery::mock(AsteriskManagerService::class);
        $mockService->shouldReceive('isConnected')->andReturn(true);
        
        $this->app->instance('asterisk-manager', $mockService);
        
        $statusCommand = $this->app->make(AsteriskStatus::class);
        $monitorCommand = $this->app->make(MonitorEvents::class);
        
        // Commands should be able to resolve the service
        $this->assertInstanceOf(AsteriskStatus::class, $statusCommand);
        $this->assertInstanceOf(MonitorEvents::class, $monitorCommand);
    }

    public function testCommandsHandleServiceExceptions()
    {
        // Mock the PAMI client to throw exception
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        $mockClient->shouldReceive('send')
            ->andThrow(new \Exception('AMI communication error'));
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();
        
        $this->artisan('asterisk:status')
            ->expectsOutput('Error: AMI communication error')
            ->assertExitCode(1);
    }

    public function testCommandsLogActivity()
    {
        // Mock the PAMI client
        $mockClient = Mockery::mock(ClientImpl::class);
        $mockResponse = Mockery::mock(ResponseMessage::class);
        
        $mockResponse->shouldReceive('isSuccess')->andReturn(true);
        $mockResponse->shouldReceive('getKeys')->andReturn(['CoreCurrentCalls' => '0']);
        
        $mockClient->shouldReceive('send')->andReturn($mockResponse);
        $mockClient->shouldReceive('isLoggedIn')->andReturn(true);
        
        // Replace the bound PAMI client
        $this->app->instance(ClientImpl::class, $mockClient);
        
        // Verify that commands log their activity
        Log::shouldReceive('info')
            ->with('Asterisk status command executed', Mockery::any())
            ->once();
            
        Log::shouldReceive('info')
            ->with('Retrieved Asterisk status successfully', Mockery::any())
            ->once();
        
        $this->artisan('asterisk:status')
            ->assertExitCode(0);
    }

    public function testCommandsCanRunInTestEnvironment()
    {
        // Verify that commands can run in the test environment
        $this->assertEquals('testing', $this->app->environment());
        
        // Commands should be able to access configuration
        $config = $this->app['config']['asterisk-pbx-manager'];
        $this->assertIsArray($config);
        
        // Commands should be registered and executable
        $this->assertTrue($this->app->bound(AsteriskStatus::class));
        $this->assertTrue($this->app->bound(MonitorEvents::class));
    }

    public function testCommandHelpText()
    {
        // Test that help text is displayed correctly
        $this->artisan('help', ['command_name' => 'asterisk:status'])
            ->expectsOutput('Display Asterisk system status and connection information')
            ->assertExitCode(0);
        
        $this->artisan('help', ['command_name' => 'asterisk:monitor-events'])
            ->expectsOutput('Monitor Asterisk events in real-time')
            ->assertExitCode(0);
    }
}