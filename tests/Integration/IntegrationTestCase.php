<?php

namespace AsteriskPbxManager\Tests\Integration;

use Orchestra\Testbench\TestCase;
use AsteriskPbxManager\AsteriskPbxManagerServiceProvider;
use AsteriskPbxManager\Services\AsteriskManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base test case for integration tests.
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Additional setup for integration tests
        $this->setUpDatabase();
    }

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
     * Get package aliases.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app): array
    {
        return [
            'AsteriskManager' => \AsteriskPbxManager\Facades\AsteriskManager::class,
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
        // Setup the database configuration
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup cache configuration
        $app['config']->set('cache.default', 'array');

        // Setup session configuration
        $app['config']->set('session.driver', 'array');

        // Setup queue configuration
        $app['config']->set('queue.default', 'sync');

        // Setup Asterisk package configuration for testing
        $app['config']->set('asterisk-pbx-manager.connection', [
            'host' => '127.0.0.1',
            'port' => 5038,
            'username' => 'testuser',
            'secret' => 'testsecret',
            'connect_timeout' => 10,
            'read_timeout' => 10,
            'scheme' => 'tcp://',
        ]);

        $app['config']->set('asterisk-pbx-manager.events', [
            'enabled' => true,
            'broadcast' => false, // Disable for testing
            'log_to_database' => false, // Disable for testing
        ]);

        $app['config']->set('asterisk-pbx-manager.logging', [
            'enabled' => false, // Disable for testing
            'level' => 'debug',
            'channel' => 'default',
        ]);

        $app['config']->set('asterisk-pbx-manager.reconnection', [
            'enabled' => true,
            'max_attempts' => 3,
            'delay_seconds' => 1, // Faster for testing
        ]);
    }

    /**
     * Setup database for testing.
     */
    protected function setUpDatabase(): void
    {
        // Load any migrations that might be needed
        // This method can be overridden in specific test classes
    }

    /**
     * Assert that the service is properly registered.
     */
    protected function assertServiceRegistered(): void
    {
        $this->assertTrue($this->app->bound('asterisk-manager'));
        $this->assertInstanceOf(
            AsteriskManagerService::class,
            $this->app->make('asterisk-manager')
        );
    }

    /**
     * Assert that the facade is working.
     */
    protected function assertFacadeWorks(): void
    {
        $this->assertTrue(class_exists(\AsteriskPbxManager\Facades\AsteriskManager::class));
        
        // Test facade registration by checking if it resolves to the correct service
        $facade = new \AsteriskPbxManager\Facades\AsteriskManager();
        $this->assertInstanceOf(
            AsteriskManagerService::class,
            $this->app->make('asterisk-manager')
        );
    }

    /**
     * Assert that configuration is properly loaded.
     */
    protected function assertConfigurationLoaded(): void
    {
        $this->assertIsArray(config('asterisk-pbx-manager'));
        $this->assertIsArray(config('asterisk-pbx-manager.connection'));
        $this->assertIsArray(config('asterisk-pbx-manager.events'));
        $this->assertIsArray(config('asterisk-pbx-manager.logging'));
        
        // Check specific configuration values
        $this->assertEquals('127.0.0.1', config('asterisk-pbx-manager.connection.host'));
        $this->assertEquals(5038, config('asterisk-pbx-manager.connection.port'));
        $this->assertEquals('testuser', config('asterisk-pbx-manager.connection.username'));
    }

    /**
     * Create a test configuration override.
     *
     * @param array $overrides
     */
    protected function overrideConfig(array $overrides): void
    {
        foreach ($overrides as $key => $value) {
            $this->app['config']->set("asterisk-pbx-manager.{$key}", $value);
        }
    }

    /**
     * Mock the PAMI client in the container.
     *
     * @param \Mockery\MockInterface|null $mockClient
     * @return \Mockery\MockInterface
     */
    protected function mockPamiClient(?\Mockery\MockInterface $mockClient = null): \Mockery\MockInterface
    {
        if (!$mockClient) {
            $mockClient = \Mockery::mock(\PAMI\Client\Impl\ClientImpl::class);
        }

        $this->app->instance(\PAMI\Client\Impl\ClientImpl::class, $mockClient);
        
        return $mockClient;
    }

    /**
     * Get the Asterisk Manager service instance.
     *
     * @return AsteriskManagerService
     */
    protected function getAsteriskManager(): AsteriskManagerService
    {
        return $this->app->make('asterisk-manager');
    }

    /**
     * Create a mock event for testing.
     *
     * @param string $eventName
     * @param array $keys
     * @return \Mockery\MockInterface
     */
    protected function createMockEvent(string $eventName = 'TestEvent', array $keys = []): \Mockery\MockInterface
    {
        $event = \Mockery::mock(\PAMI\Message\Event\EventMessage::class);
        $event->shouldReceive('getEventName')->andReturn($eventName);
        $event->shouldReceive('getKeys')->andReturn($keys);
        
        // Mock getKey method to return specific values
        $event->shouldReceive('getKey')->andReturnUsing(function ($key) use ($keys) {
            return $keys[$key] ?? null;
        });
        
        return $event;
    }

    /**
     * Create a mock response for testing.
     *
     * @param bool $success
     * @param string|null $message
     * @param array $keys
     * @return \Mockery\MockInterface
     */
    protected function createMockResponse(bool $success = true, ?string $message = null, array $keys = []): \Mockery\MockInterface
    {
        $response = \Mockery::mock(\PAMI\Message\Response\ResponseMessage::class);
        $response->shouldReceive('isSuccess')->andReturn($success);
        $response->shouldReceive('getMessage')->andReturn($message);
        $response->shouldReceive('getKeys')->andReturn($keys);
        
        return $response;
    }

    /**
     * Simulate an Asterisk event.
     *
     * @param string $eventName
     * @param array $data
     */
    protected function simulateEvent(string $eventName, array $data = []): void
    {
        $event = $this->createMockEvent($eventName, $data);
        
        // Fire the event through Laravel's event system
        switch ($eventName) {
            case 'Dial':
                event(new \AsteriskPbxManager\Events\CallConnected($event));
                break;
            case 'Hangup':
                event(new \AsteriskPbxManager\Events\CallEnded($event));
                break;
            case 'QueueMemberAdded':
                event(new \AsteriskPbxManager\Events\QueueMemberAdded($event));
                break;
            default:
                event(new \AsteriskPbxManager\Events\AsteriskEvent($event));
                break;
        }
    }

    /**
     * Assert that an event was dispatched.
     *
     * @param string $eventClass
     */
    protected function assertEventDispatched(string $eventClass): void
    {
        $this->assertTrue(
            collect($this->app->make('events')->getFiredEvents())->has($eventClass),
            "Event {$eventClass} was not dispatched"
        );
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /**
     * Skip a test if PAMI is not available.
     */
    protected function skipIfPamiNotAvailable(): void
    {
        if (!class_exists(\PAMI\Client\Impl\ClientImpl::class)) {
            $this->markTestSkipped('PAMI library is not available');
        }
    }

    /**
     * Skip a test if running in CI environment without Asterisk.
     */
    protected function skipIfNoAsterisk(): void
    {
        if (env('CI') === true || env('SKIP_ASTERISK_TESTS') === true) {
            $this->markTestSkipped('Skipping Asterisk tests in CI environment');
        }
    }
}