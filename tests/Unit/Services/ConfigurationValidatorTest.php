<?php

namespace AsteriskPbxManager\Tests\Unit\Services;

use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Services\ConfigurationValidator;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\TestCase;

class ConfigurationValidatorTest extends TestCase
{
    private ConfigurationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConfigurationValidator();

        // Mock the Log facade to prevent actual logging during tests
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_validates_complete_configuration_successfully()
    {
        $config = $this->getValidConfiguration();

        $result = $this->validator->validateConfiguration($config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('connection', $result);
        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('reconnection', $result);
        $this->assertArrayHasKey('logging', $result);
        $this->assertArrayHasKey('queues', $result);
        $this->assertArrayHasKey('broadcasting', $result);
    }

    /** @test */
    public function it_validates_connection_configuration_successfully()
    {
        $config = $this->getValidConfiguration();

        $result = $this->validator->validateConfiguration($config);

        $connection = $result['connection'];
        $this->assertEquals('192.168.1.100', $connection['host']);
        $this->assertEquals(5038, $connection['port']);
        $this->assertEquals('admin', $connection['username']);
        $this->assertEquals('secure_secret', $connection['secret']);
        $this->assertEquals(10, $connection['connect_timeout']);
        $this->assertEquals(10, $connection['read_timeout']);
        $this->assertEquals('tcp://', $connection['scheme']);
    }

    /** @test */
    public function it_throws_exception_for_empty_host()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['host'] = '';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI host cannot be empty');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_invalid_host()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['host'] = 'invalid..host..name';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Invalid AMI host format');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_validates_ip_addresses_as_valid_hosts()
    {
        $config = $this->getValidConfiguration();

        // Test IPv4
        $config['connection']['host'] = '192.168.1.1';
        $result = $this->validator->validateConfiguration($config);
        $this->assertEquals('192.168.1.1', $result['connection']['host']);

        // Test IPv6
        $config['connection']['host'] = '::1';
        $result = $this->validator->validateConfiguration($config);
        $this->assertEquals('::1', $result['connection']['host']);
    }

    /** @test */
    public function it_validates_hostnames_as_valid_hosts()
    {
        $config = $this->getValidConfiguration();

        // Test hostname
        $config['connection']['host'] = 'asterisk-server';
        $result = $this->validator->validateConfiguration($config);
        $this->assertEquals('asterisk-server', $result['connection']['host']);

        // Test domain
        $config['connection']['host'] = 'asterisk.example.com';
        $result = $this->validator->validateConfiguration($config);
        $this->assertEquals('asterisk.example.com', $result['connection']['host']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_port_ranges()
    {
        $config = $this->getValidConfiguration();

        // Test port too low
        $config['connection']['port'] = 0;
        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI port must be between 1 and 65535');
        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_port_too_high()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['port'] = 65536;

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI port must be between 1 and 65535');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_empty_username()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['username'] = '';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI username cannot be empty');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_username_too_long()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['username'] = str_repeat('a', 65);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI username cannot exceed 64 characters');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_invalid_username_characters()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['username'] = 'admin@domain';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI username contains invalid characters');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_empty_or_default_secret()
    {
        $config = $this->getValidConfiguration();

        // Test empty secret
        $config['connection']['secret'] = '';
        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI secret must be configured and cannot be default value');
        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_default_secret_value()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['secret'] = 'your_ami_secret';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI secret must be configured and cannot be default value');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_secret_too_long()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['secret'] = str_repeat('a', 129);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('AMI secret cannot exceed 128 characters');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_invalid_timeout_values()
    {
        $config = $this->getValidConfiguration();

        // Test connect timeout too low
        $config['connection']['connect_timeout'] = 0;
        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Connect timeout must be between 1 and 300 seconds');
        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_timeout_too_high()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['read_timeout'] = 301;

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Read timeout must be between 1 and 300 seconds');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_invalid_scheme()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['scheme'] = 'http://';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Invalid AMI scheme: http://. Valid schemes: tcp://, ssl://');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_validates_ssl_scheme()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['scheme'] = 'ssl://';

        $result = $this->validator->validateConfiguration($config);

        $this->assertEquals('ssl://', $result['connection']['scheme']);
    }

    /** @test */
    public function it_validates_event_configuration()
    {
        $config = $this->getValidConfiguration();

        $result = $this->validator->validateConfiguration($config);

        $events = $result['events'];
        $this->assertTrue($events['enabled']);
        $this->assertTrue($events['broadcast']);
        $this->assertTrue($events['log_to_database']);
    }

    /** @test */
    public function it_handles_missing_event_configuration()
    {
        $config = $this->getValidConfiguration();
        unset($config['events']);

        $result = $this->validator->validateConfiguration($config);

        $events = $result['events'];
        $this->assertTrue($events['enabled']);
        $this->assertTrue($events['broadcast']);
        $this->assertTrue($events['log_to_database']);
    }

    /** @test */
    public function it_validates_reconnection_configuration()
    {
        $config = $this->getValidConfiguration();

        $result = $this->validator->validateConfiguration($config);

        $reconnection = $result['reconnection'];
        $this->assertTrue($reconnection['enabled']);
        $this->assertEquals(3, $reconnection['max_attempts']);
        $this->assertEquals(5, $reconnection['delay_seconds']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_reconnection_attempts()
    {
        $config = $this->getValidConfiguration();
        $config['reconnection']['max_attempts'] = 11;

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Reconnection max attempts must be between 0 and 10');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_invalid_reconnection_delay()
    {
        $config = $this->getValidConfiguration();
        $config['reconnection']['delay_seconds'] = 301;

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Reconnection delay must be between 0 and 300 seconds');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_validates_logging_configuration()
    {
        $config = $this->getValidConfiguration();

        $result = $this->validator->validateConfiguration($config);

        $logging = $result['logging'];
        $this->assertTrue($logging['enabled']);
        $this->assertEquals('info', $logging['level']);
        $this->assertEquals('default', $logging['channel']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_log_level()
    {
        $config = $this->getValidConfiguration();
        $config['logging']['level'] = 'invalid_level';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Invalid log level: invalid_level');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_validates_all_log_levels()
    {
        $config = $this->getValidConfiguration();
        $validLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

        foreach ($validLevels as $level) {
            $config['logging']['level'] = $level;
            $result = $this->validator->validateConfiguration($config);
            $this->assertEquals($level, $result['logging']['level']);
        }
    }

    /** @test */
    public function it_throws_exception_for_empty_log_channel()
    {
        $config = $this->getValidConfiguration();
        $config['logging']['channel'] = '';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Log channel cannot be empty');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_validates_queue_configuration()
    {
        $config = $this->getValidConfiguration();

        $result = $this->validator->validateConfiguration($config);

        $queues = $result['queues'];
        $this->assertEquals('default', $queues['default_context']);
        $this->assertEquals(1, $queues['default_priority']);
    }

    /** @test */
    public function it_throws_exception_for_empty_context()
    {
        $config = $this->getValidConfiguration();
        $config['queues']['default_context'] = '';

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Default context cannot be empty');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_context_too_long()
    {
        $config = $this->getValidConfiguration();
        $config['queues']['default_context'] = str_repeat('a', 81);

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Default context cannot exceed 80 characters');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_throws_exception_for_invalid_priority()
    {
        $config = $this->getValidConfiguration();
        $config['queues']['default_priority'] = 1000;

        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Default priority must be between 1 and 999');

        $this->validator->validateConfiguration($config);
    }

    /** @test */
    public function it_validates_broadcasting_configuration()
    {
        $config = $this->getValidConfiguration();

        $result = $this->validator->validateConfiguration($config);

        $broadcasting = $result['broadcasting'];
        $this->assertEquals('asterisk', $broadcasting['channel_prefix']);
        $this->assertFalse($broadcasting['private_channels']);
    }

    /** @test */
    public function it_sanitizes_string_input()
    {
        $config = $this->getValidConfiguration();
        $config['connection']['host'] = "  192.168.1.1\0\r\n\t  ";

        $result = $this->validator->validateConfiguration($config);

        $this->assertEquals('192.168.1.1', $result['connection']['host']);
    }

    /** @test */
    public function it_validates_or_fails_method()
    {
        $invalidConfig = $this->getValidConfiguration();
        $invalidConfig['connection']['host'] = '';

        $this->expectException(AsteriskConnectionException::class);

        $this->validator->validateOrFail($invalidConfig);
    }

    /** @test */
    public function it_gets_safe_configuration_without_exposing_secrets()
    {
        $config = $this->getValidConfiguration();

        $safeConfig = $this->validator->getSafeConfiguration($config);

        $this->assertEquals('[HIDDEN]', $safeConfig['connection']['secret']);
        $this->assertEquals('admin', $safeConfig['connection']['username']);
    }

    /**
     * Get a valid configuration array for testing.
     */
    private function getValidConfiguration(): array
    {
        return [
            'connection' => [
                'host'            => '192.168.1.100',
                'port'            => 5038,
                'username'        => 'admin',
                'secret'          => 'secure_secret',
                'connect_timeout' => 10,
                'read_timeout'    => 10,
                'scheme'          => 'tcp://',
            ],
            'events' => [
                'enabled'         => true,
                'broadcast'       => true,
                'log_to_database' => true,
            ],
            'reconnection' => [
                'enabled'       => true,
                'max_attempts'  => 3,
                'delay_seconds' => 5,
            ],
            'logging' => [
                'enabled' => true,
                'level'   => 'info',
                'channel' => 'default',
            ],
            'queues' => [
                'default_context'  => 'default',
                'default_priority' => 1,
            ],
            'broadcasting' => [
                'channel_prefix'   => 'asterisk',
                'private_channels' => false,
            ],
        ];
    }
}
