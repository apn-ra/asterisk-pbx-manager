<?php

namespace AsteriskPbxManager\Tests\Unit\Exceptions;

use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\SecureErrorHandler;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Mockery;

class AsteriskConnectionExceptionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testConstructorGeneratesErrorReference()
    {
        $exception = new AsteriskConnectionException('Test message');
        
        $this->assertNotNull($exception->getErrorReference());
        $this->assertStringStartsWith('ERR_', $exception->getErrorReference());
    }

    public function testTimeoutExceptionDoesNotExposeSensitiveInfo()
    {
        // Mock logging
        Log::shouldReceive('log')
            ->once()
            ->with('error', Mockery::type('string'), Mockery::type('array'));

        $exception = AsteriskConnectionException::timeout(30);
        
        // Should contain generic message and error code
        $this->assertStringContainsString('Unable to connect to the telephony system', $exception->getMessage());
        $this->assertStringContainsString('AMI_CONNECTION_001', $exception->getMessage());
        
        // Should not expose timeout value in message
        $this->assertStringNotContainsString('30', $exception->getMessage());
        
        // Should have error reference
        $this->assertNotNull($exception->getErrorReference());
    }

    public function testAuthenticationFailedDoesNotExposeUsername()
    {
        Log::shouldReceive('log')
            ->once()
            ->with('error', Mockery::type('string'), Mockery::type('array'));

        $exception = AsteriskConnectionException::authenticationFailed('admin');
        
        // Should contain generic authentication error message
        $this->assertStringContainsString('Authentication failed', $exception->getMessage());
        $this->assertStringContainsString('AMI_AUTH_002', $exception->getMessage());
        
        // Should not expose username in message
        $this->assertStringNotContainsString('admin', $exception->getMessage());
        
        // Should have error reference
        $this->assertNotNull($exception->getErrorReference());
    }

    public function testNetworkErrorDoesNotExposeHostInfo()
    {
        Log::shouldReceive('log')
            ->once()
            ->with('error', Mockery::type('string'), Mockery::type('array'));

        $exception = AsteriskConnectionException::networkError('192.168.1.100', 5038, 'Connection refused');
        
        // Should contain generic network error message
        $this->assertStringContainsString('Network communication error occurred', $exception->getMessage());
        $this->assertStringContainsString('AMI_NETWORK_003', $exception->getMessage());
        
        // Should not expose host, port, or specific error in message
        $this->assertStringNotContainsString('192.168.1.100', $exception->getMessage());
        $this->assertStringNotContainsString('5038', $exception->getMessage());
        $this->assertStringNotContainsString('Connection refused', $exception->getMessage());
        
        // Should have error reference
        $this->assertNotNull($exception->getErrorReference());
    }

    public function testInvalidConfigurationDoesNotExposeParameter()
    {
        Log::shouldReceive('log')
            ->once()
            ->with('error', Mockery::type('string'), Mockery::type('array'));

        $exception = AsteriskConnectionException::invalidConfiguration('ami_secret');
        
        // Should contain generic configuration error message
        $this->assertStringContainsString('System configuration error', $exception->getMessage());
        $this->assertStringContainsString('AMI_CONFIG_004', $exception->getMessage());
        
        // Should not expose parameter name in message
        $this->assertStringNotContainsString('ami_secret', $exception->getMessage());
        
        // Should have error reference
        $this->assertNotNull($exception->getErrorReference());
    }

    public function testErrorReferenceIsUnique()
    {
        $exception1 = new AsteriskConnectionException('Test 1');
        $exception2 = new AsteriskConnectionException('Test 2');
        
        $this->assertNotEquals(
            $exception1->getErrorReference(), 
            $exception2->getErrorReference()
        );
    }

    public function testLoggingContainsSensitiveDataButMessageDoesNot()
    {
        $loggedData = null;
        
        // Capture what gets logged
        Log::shouldReceive('log')
            ->once()
            ->with('error', Mockery::type('string'), Mockery::type('array'))
            ->andReturnUsing(function($level, $message, $context) use (&$loggedData) {
                $loggedData = $context;
            });

        $exception = AsteriskConnectionException::authenticationFailed('secretuser');
        
        // The exception message should not contain sensitive info
        $this->assertStringNotContainsString('secretuser', $exception->getMessage());
        
        // But the logged data should contain masked sensitive info
        $this->assertNotNull($loggedData);
        $this->assertArrayHasKey('context', $loggedData);
        $this->assertArrayHasKey('username', $loggedData['context']);
        
        // Username should be masked in logs
        $maskedUsername = $loggedData['context']['username'];
        $this->assertNotEquals('secretuser', $maskedUsername);
        $this->assertStringContainsString('*', $maskedUsername);
    }
}