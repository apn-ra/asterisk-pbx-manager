<?php

namespace AsteriskPbxManager\Tests\Unit\Services;

use AsteriskPbxManager\Services\AuditLoggingService;
use AsteriskPbxManager\Models\AuditLog;
use PAMI\Message\Action\ActionMessage;
use PAMI\Message\Response\ResponseMessage;
use Orchestra\Testbench\TestCase;
use Mockery;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLoggingServiceTest extends TestCase
{
    private AuditLoggingService $auditLogger;

    protected function getPackageProviders($app)
    {
        return [
            \AsteriskPbxManager\AsteriskPbxManagerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Set up test database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up audit logging configuration
        $app['config']->set('asterisk-pbx-manager.audit.enabled', true);
        $app['config']->set('asterisk-pbx-manager.audit.log_to_database', true);
        $app['config']->set('asterisk-pbx-manager.audit.log_to_file', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../../src/Migrations');
        
        $this->auditLogger = new AuditLoggingService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testIsEnabledReturnsTrueWhenConfigured()
    {
        $this->assertTrue($this->auditLogger->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenDisabled()
    {
        // Temporarily change config
        config(['asterisk-pbx-manager.audit.enabled' => false]);
        
        $auditLogger = new AuditLoggingService();
        $this->assertFalse($auditLogger->isEnabled());
        
        // Restore config
        config(['asterisk-pbx-manager.audit.enabled' => true]);
    }

    public function testLogActionWithSuccessfulResponse()
    {
        // Mock action and response
        $action = Mockery::mock(ActionMessage::class);
        $action->shouldReceive('getAction')->andReturn('Originate');
        $action->shouldReceive('getVariables')->andReturn(['channel' => 'SIP/1001']);
        $action->shouldReceive('getActionId')->andReturn('action123');

        $response = Mockery::mock(ResponseMessage::class);
        $response->shouldReceive('isSuccess')->andReturn(true);
        $response->shouldReceive('getResponse')->andReturn('Success');
        $response->shouldReceive('getMessage')->andReturn('Call originated');
        $response->shouldReceive('getKeys')->andReturn(['status' => 'success']);
        $response->shouldReceive('getActionId')->andReturn('action123');

        // Log the action
        $this->auditLogger->logAction($action, $response, 1.5, ['test' => 'context']);

        // Verify the audit log was created in database
        $this->assertDatabaseHas('audit_logs', [
            'action_type' => 'ami_action',
            'action_name' => 'Originate',
            'success' => true,
            'execution_time' => 1.5,
        ]);
    }

    public function testLogActionWithFailedResponse()
    {
        // Mock action and failed response
        $action = Mockery::mock(ActionMessage::class);
        $action->shouldReceive('getAction')->andReturn('Hangup');
        $action->shouldReceive('getVariables')->andReturn(['channel' => 'SIP/1001']);
        $action->shouldReceive('getActionId')->andReturn('action456');

        $response = Mockery::mock(ResponseMessage::class);
        $response->shouldReceive('isSuccess')->andReturn(false);
        $response->shouldReceive('getResponse')->andReturn('Error');
        $response->shouldReceive('getMessage')->andReturn('Channel not found');
        $response->shouldReceive('getKeys')->andReturn(['status' => 'error']);
        $response->shouldReceive('getActionId')->andReturn('action456');

        // Mock Auth facade
        Auth::shouldReceive('check')->andReturn(false);

        // Mock AuditLog model
        AuditLog::shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return $data['action_type'] === 'ami_action' &&
                   $data['action_name'] === 'Hangup' &&
                   $data['success'] === false;
        }))->andReturn(true);

        // Mock Log facade for file logging
        Log::shouldReceive('channel')->with('single')->andReturnSelf();
        Log::shouldReceive('log')->once();

        $this->auditLogger->logAction($action, $response, 0.5);
    }

    public function testLogActionWithNoResponse()
    {
        // Mock action without response (exception scenario)
        $action = Mockery::mock(ActionMessage::class);
        $action->shouldReceive('getAction')->andReturn('CoreStatus');
        $action->shouldReceive('getVariables')->andReturn([]);
        $action->shouldReceive('getActionId')->andReturn('action789');

        // Mock Auth facade
        Auth::shouldReceive('check')->andReturn(false);

        // Mock AuditLog model
        AuditLog::shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return $data['action_type'] === 'ami_action' &&
                   $data['action_name'] === 'CoreStatus' &&
                   $data['success'] === false &&
                   $data['response_data'] === null;
        }))->andReturn(true);

        // Mock Log facade for file logging
        Log::shouldReceive('channel')->with('single')->andReturnSelf();
        Log::shouldReceive('log')->once();

        $this->auditLogger->logAction($action, null, 0.2);
    }

    public function testLogConnectionSuccess()
    {
        // Mock Auth facade
        Auth::shouldReceive('check')->andReturn(false);

        // Mock AuditLog model
        AuditLog::shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return $data['action_type'] === 'connection' &&
                   $data['action_name'] === 'connect' &&
                   $data['success'] === true;
        }))->andReturn(true);

        // Mock Log facade
        Log::shouldReceive('channel')->with('single')->andReturnSelf();
        Log::shouldReceive('log')->once();

        $this->auditLogger->logConnection('connect', true, ['host' => '127.0.0.1']);
    }

    public function testLogConnectionFailure()
    {
        // Mock Auth facade
        Auth::shouldReceive('check')->andReturn(false);

        // Mock AuditLog model
        AuditLog::shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return $data['action_type'] === 'connection' &&
                   $data['action_name'] === 'connect' &&
                   $data['success'] === false;
        }))->andReturn(true);

        // Mock Log facade
        Log::shouldReceive('channel')->with('single')->andReturnSelf();
        Log::shouldReceive('log')->once();

        $this->auditLogger->logConnection('connect', false, [
            'host' => '127.0.0.1',
            'error' => 'Connection refused'
        ]);
    }

    public function testWithContextAddsContext()
    {
        $context = ['user_action' => 'manual_call', 'source' => 'web_interface'];
        
        $result = $this->auditLogger->withContext($context);
        
        $this->assertInstanceOf(AuditLoggingService::class, $result);
        $this->assertSame($this->auditLogger, $result);
    }

    public function testClearContextResetsContext()
    {
        $this->auditLogger->withContext(['test' => 'data']);
        $result = $this->auditLogger->clearContext();
        
        $this->assertInstanceOf(AuditLoggingService::class, $result);
        $this->assertSame($this->auditLogger, $result);
    }

    public function testLogActionSkipsWhenDisabled()
    {
        Config::shouldReceive('get')
            ->with('asterisk-pbx-manager.audit.enabled', false)
            ->andReturn(false);
        Config::shouldReceive('get')
            ->with('asterisk-pbx-manager.audit.log_to_database', true)
            ->andReturn(true);
        Config::shouldReceive('get')
            ->with('asterisk-pbx-manager.audit.log_to_file', true)
            ->andReturn(true);

        $auditLogger = new AuditLoggingService();

        $action = Mockery::mock(ActionMessage::class);
        $response = Mockery::mock(ResponseMessage::class);

        // AuditLog::create should not be called
        AuditLog::shouldReceive('create')->never();
        Log::shouldReceive('channel')->never();

        $auditLogger->logAction($action, $response);
    }

    public function testLogActionWithAuthenticatedUser()
    {
        // Mock authenticated user
        $user = Mockery::mock();
        $user->shouldReceive('getAttribute')->with('name')->andReturn('John Doe');
        $user->shouldReceive('getAttribute')->with('email')->andReturn('john@example.com');
        $user->name = 'John Doe';
        $user->email = 'john@example.com';

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn(123);
        Auth::shouldReceive('user')->andReturn($user);

        // Mock action
        $action = Mockery::mock(ActionMessage::class);
        $action->shouldReceive('getAction')->andReturn('Originate');
        $action->shouldReceive('getVariables')->andReturn([]);
        $action->shouldReceive('getActionId')->andReturn('action123');

        $response = Mockery::mock(ResponseMessage::class);
        $response->shouldReceive('isSuccess')->andReturn(true);
        $response->shouldReceive('getResponse')->andReturn('Success');
        $response->shouldReceive('getMessage')->andReturn('OK');
        $response->shouldReceive('getKeys')->andReturn([]);
        $response->shouldReceive('getActionId')->andReturn('action123');

        // Mock AuditLog model
        AuditLog::shouldReceive('create')->once()->with(Mockery::on(function ($data) {
            return $data['user_id'] === 123 &&
                   $data['user_name'] === 'John Doe';
        }))->andReturn(true);

        // Mock Log facade
        Log::shouldReceive('channel')->with('single')->andReturnSelf();
        Log::shouldReceive('log')->once();

        $this->auditLogger->logAction($action, $response);
    }

    public function testDatabaseLoggingFallsBackToFileOnException()
    {
        // Mock action
        $action = Mockery::mock(ActionMessage::class);
        $action->shouldReceive('getAction')->andReturn('Test');
        $action->shouldReceive('getVariables')->andReturn([]);
        $action->shouldReceive('getActionId')->andReturn('test123');

        $response = Mockery::mock(ResponseMessage::class);
        $response->shouldReceive('isSuccess')->andReturn(true);
        $response->shouldReceive('getResponse')->andReturn('Success');
        $response->shouldReceive('getMessage')->andReturn('OK');
        $response->shouldReceive('getKeys')->andReturn([]);
        $response->shouldReceive('getActionId')->andReturn('test123');

        // Mock Auth facade
        Auth::shouldReceive('check')->andReturn(false);

        // Mock AuditLog to throw exception
        AuditLog::shouldReceive('create')->once()->andThrow(new \Exception('Database error'));

        // Mock Log facade - should be called twice (once for fallback error, once for file logging)
        Log::shouldReceive('error')->once()->with('Failed to write audit log to database', Mockery::any());
        Log::shouldReceive('channel')->with('single')->andReturnSelf();
        Log::shouldReceive('log')->once();

        $this->auditLogger->logAction($action, $response);
    }
}