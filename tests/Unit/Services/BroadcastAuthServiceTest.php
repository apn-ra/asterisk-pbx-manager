<?php

namespace AsteriskPbxManager\Tests\Unit\Services;

use Orchestra\Testbench\TestCase;
use Mockery;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use AsteriskPbxManager\Services\BroadcastAuthService;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use AsteriskPbxManager\AsteriskPbxManagerServiceProvider;

/**
 * Unit tests for BroadcastAuthService.
 * 
 * Tests authentication functionality for broadcast events including
 * user authentication, token authentication, and permission checking.
 */
class BroadcastAuthServiceTest extends TestCase
{
    protected $authFactory;
    protected $guard;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->authFactory = Mockery::mock(AuthFactory::class);
        $this->guard = Mockery::mock(Guard::class);
        
        $this->authFactory->shouldReceive('guard')
            ->with('web')
            ->andReturn($this->guard)
            ->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Get the package providers.
     */
    protected function getPackageProviders($app)
    {
        return [
            AsteriskPbxManagerServiceProvider::class,
        ];
    }

    /**
     * Create service instance with configuration.
     */
    protected function createService(array $config = []): BroadcastAuthService
    {
        $this->app['config']->set('asterisk-pbx-manager.broadcasting.authentication', $config);
        
        return new BroadcastAuthService($this->authFactory);
    }

    public function testIsEnabledReturnsFalseWhenDisabled()
    {
        $service = $this->createService(['enabled' => false]);
        $this->assertFalse($service->isEnabled());
    }

    public function testIsEnabledReturnsTrueWhenEnabled()
    {
        $service = $this->createService(['enabled' => true]);
        $this->assertTrue($service->isEnabled());
    }

    public function testAuthenticateUserReturnsTrueWhenDisabled()
    {
        $service = $this->createService(['enabled' => false]);
        $result = $service->authenticateUser();
        $this->assertTrue($result);
    }

    public function testAuthenticateUserReturnsFalseWhenNoUser()
    {
        $service = $this->createService(['enabled' => true]);
        
        $this->guard->shouldReceive('user')->once()->andReturn(null);
        
        $result = $service->authenticateUser();
        $this->assertFalse($result);
    }

    public function testAuthenticateUserReturnsTrueWithValidUser()
    {
        $service = $this->createService(['enabled' => true]);
        
        $mockUser = Mockery::mock();
        $mockUser->id = 123;
        
        $this->guard->shouldReceive('user')->once()->andReturn($mockUser);
        
        $result = $service->authenticateUser();
        $this->assertTrue($result);
    }

    public function testAuthenticateUserWithProvidedUser()
    {
        $service = $this->createService(['enabled' => true]);
        
        $mockUser = Mockery::mock();
        $mockUser->id = 456;
        
        // Guard should not be called when user is provided
        $this->guard->shouldNotReceive('user');
        
        $result = $service->authenticateUser($mockUser);
        $this->assertTrue($result);
    }

    public function testAuthenticateUserChecksPermissionsWhenRequired()
    {
        $service = $this->createService([
            'enabled' => true,
            'required_permissions' => 'asterisk.events.listen'
        ]);
        
        $mockUser = new class {
            public $id = 123;
            public function can($permission) {
                return $permission === 'asterisk.events.listen';
            }
        };
        
        $this->guard->shouldReceive('user')->once()->andReturn($mockUser);
        
        $result = $service->authenticateUser();
        $this->assertTrue($result);
    }

    public function testAuthenticateUserFailsWhenPermissionDenied()
    {
        $service = $this->createService([
            'enabled' => true,
            'required_permissions' => 'asterisk.events.listen'
        ]);
        
        $mockUser = new class {
            public $id = 123;
            public function can($permission) {
                return false; // Always deny permission
            }
        };
        
        $this->guard->shouldReceive('user')->once()->andReturn($mockUser);
        
        $result = $service->authenticateUser();
        $this->assertFalse($result);
    }

    public function testAuthenticateUserWithCustomPermissionMethod()
    {
        $service = $this->createService([
            'enabled' => true,
            'required_permissions' => 'asterisk.events.listen'
        ]);
        
        $mockUser = new class {
            public $id = 123;
            public function hasPermission($permission) {
                return $permission === 'asterisk.events.listen';
            }
        };
        
        $this->guard->shouldReceive('user')->once()->andReturn($mockUser);
        
        $result = $service->authenticateUser();
        $this->assertTrue($result);
    }

    public function testAuthenticateTokenReturnsTrueWhenDisabled()
    {
        $service = $this->createService(['enabled' => false]);
        $result = $service->authenticateToken('any-token');
        $this->assertTrue($result);
    }

    public function testAuthenticateTokenReturnsTrueWhenTokenAuthDisabled()
    {
        $service = $this->createService([
            'enabled' => true,
            'token_based' => false
        ]);
        $result = $service->authenticateToken('any-token');
        $this->assertTrue($result);
    }

    public function testAuthenticateTokenReturnsFalseWhenNoTokensConfigured()
    {
        $service = $this->createService([
            'enabled' => true,
            'token_based' => true,
            'allowed_tokens' => []
        ]);
        $result = $service->authenticateToken('test-token');
        $this->assertFalse($result);
    }

    public function testAuthenticateTokenReturnsTrueWithValidToken()
    {
        $service = $this->createService([
            'enabled' => true,
            'token_based' => true,
            'allowed_tokens' => ['valid-token', 'another-token']
        ]);
        $result = $service->authenticateToken('valid-token');
        $this->assertTrue($result);
    }

    public function testAuthenticateTokenReturnsFalseWithInvalidToken()
    {
        $service = $this->createService([
            'enabled' => true,
            'token_based' => true,
            'allowed_tokens' => ['valid-token', 'another-token']
        ]);
        $result = $service->authenticateToken('invalid-token');
        $this->assertFalse($result);
    }

    public function testCanReceiveBroadcastReturnsTrueWhenDisabled()
    {
        $service = $this->createService(['enabled' => false]);
        $result = $service->canReceiveBroadcast();
        $this->assertTrue($result);
    }

    public function testCanReceiveBroadcastPrefersTokenAuth()
    {
        $service = $this->createService([
            'enabled' => true,
            'token_based' => true,
            'allowed_tokens' => ['valid-token']
        ]);
        
        // Should not call user authentication when token is provided
        $this->guard->shouldNotReceive('user');
        
        $result = $service->canReceiveBroadcast(null, 'valid-token');
        $this->assertTrue($result);
    }

    public function testCanReceiveBroadcastFallsBackToUserAuth()
    {
        $service = $this->createService([
            'enabled' => true,
            'token_based' => false
        ]);
        
        $mockUser = Mockery::mock();
        $mockUser->id = 123;
        
        $this->guard->shouldReceive('user')->once()->andReturn($mockUser);
        
        $result = $service->canReceiveBroadcast();
        $this->assertTrue($result);
    }

    public function testGetMiddlewareReturnsConfiguredValue()
    {
        $service = $this->createService(['middleware' => 'custom-auth']);
        $this->assertEquals('custom-auth', $service->getMiddleware());
    }

    public function testGetMiddlewareReturnsDefaultValue()
    {
        $service = $this->createService([]);
        $this->assertEquals('auth', $service->getMiddleware());
    }

    public function testGetConfigReturnsConfigWithoutSensitiveData()
    {
        $service = $this->createService([
            'enabled' => true,
            'guard' => 'web',
            'allowed_tokens' => ['secret-token']
        ]);
        
        $config = $service->getConfig();
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('web', $config['guard']);
        $this->assertArrayNotHasKey('allowed_tokens', $config);
    }

    public function testAuthenticateUserThrowsExceptionOnError()
    {
        $service = $this->createService(['enabled' => true]);
        
        $this->guard->shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('Auth error'));
        
        $this->expectException(ActionExecutionException::class);
        $this->expectExceptionMessage('Broadcast authentication failed: Auth error');
        
        $service->authenticateUser();
    }

    public function testCustomGuardConfiguration()
    {
        $service = $this->createService([
            'enabled' => true,
            'guard' => 'api'
        ]);
        
        $this->authFactory->shouldReceive('guard')
            ->with('api')
            ->andReturn($this->guard);
        
        $mockUser = Mockery::mock();
        $mockUser->id = 123;
        
        $this->guard->shouldReceive('user')->once()->andReturn($mockUser);
        
        $result = $service->authenticateUser();
        $this->assertTrue($result);
    }

    public function testPermissionCheckWithPermissionsProperty()
    {
        $service = $this->createService([
            'enabled' => true,
            'required_permissions' => 'asterisk.events.listen'
        ]);
        
        $mockUser = Mockery::mock();
        $mockUser->id = 123;
        $mockUser->permissions = ['asterisk.events.listen', 'other.permission'];
        
        $this->guard->shouldReceive('user')->once()->andReturn($mockUser);
        
        $result = $service->authenticateUser();
        $this->assertTrue($result);
    }

    public function testPermissionCheckFailsWithoutPermissionMethods()
    {
        $service = $this->createService([
            'enabled' => true,
            'required_permissions' => 'asterisk.events.listen'
        ]);
        
        $mockUser = Mockery::mock();
        $mockUser->id = 123;
        // No can(), hasPermission() methods or permissions property
        
        $this->guard->shouldReceive('user')->once()->andReturn($mockUser);
        
        $result = $service->authenticateUser();
        $this->assertFalse($result);
    }
}