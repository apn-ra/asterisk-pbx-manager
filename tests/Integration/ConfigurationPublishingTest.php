<?php

namespace AsteriskPbxManager\Tests\Integration;

use AsteriskPbxManager\Tests\Integration\IntegrationTestCase;
use AsteriskPbxManager\AsteriskPbxManagerServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class ConfigurationPublishingTest extends IntegrationTestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configPath = $this->app->configPath('asterisk-pbx-manager.php');
    }

    protected function tearDown(): void
    {
        // Clean up published config file after each test
        if (File::exists($this->configPath)) {
            File::delete($this->configPath);
        }
        
        parent::tearDown();
    }

    public function testConfigurationCanBePublished()
    {
        // Ensure config file doesn't exist initially
        $this->assertFalse(File::exists($this->configPath));
        
        // Publish the configuration
        $exitCode = Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $this->assertEquals(0, $exitCode);
        $this->assertTrue(File::exists($this->configPath));
    }

    public function testPublishedConfigurationIsValidPhp()
    {
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        // Verify the published file is valid PHP
        $this->assertTrue(File::exists($this->configPath));
        
        // Load the configuration and verify it's a valid array
        $publishedConfig = include $this->configPath;
        $this->assertIsArray($publishedConfig);
    }

    public function testPublishedConfigurationHasRequiredKeys()
    {
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $publishedConfig = include $this->configPath;
        
        // Verify all required top-level keys are present
        $requiredKeys = ['connection', 'events', 'logging', 'queue', 'channel', 'security'];
        
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $publishedConfig, "Published config is missing required key: {$key}");
        }
    }

    public function testPublishedConfigurationConnectionSection()
    {
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $publishedConfig = include $this->configPath;
        $connectionConfig = $publishedConfig['connection'];
        
        $requiredConnectionKeys = [
            'host', 'port', 'username', 'secret', 
            'connect_timeout', 'read_timeout', 'scheme'
        ];
        
        foreach ($requiredConnectionKeys as $key) {
            $this->assertArrayHasKey($key, $connectionConfig, "Connection config is missing key: {$key}");
        }
        
        // Verify default values
        $this->assertEquals('127.0.0.1', $connectionConfig['host']);
        $this->assertEquals(5038, $connectionConfig['port']);
        $this->assertEquals('tcp://', $connectionConfig['scheme']);
    }

    public function testPublishedConfigurationEventsSection()
    {
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $publishedConfig = include $this->configPath;
        $eventsConfig = $publishedConfig['events'];
        
        $requiredEventsKeys = ['enabled', 'broadcast', 'listeners'];
        
        foreach ($requiredEventsKeys as $key) {
            $this->assertArrayHasKey($key, $eventsConfig, "Events config is missing key: {$key}");
        }
        
        // Verify default values
        $this->assertIsBool($eventsConfig['enabled']);
        $this->assertIsBool($eventsConfig['broadcast']);
        $this->assertIsArray($eventsConfig['listeners']);
    }

    public function testPublishedConfigurationLoggingSection()
    {
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $publishedConfig = include $this->configPath;
        $loggingConfig = $publishedConfig['logging'];
        
        $requiredLoggingKeys = ['enabled', 'channel', 'level'];
        
        foreach ($requiredLoggingKeys as $key) {
            $this->assertArrayHasKey($key, $loggingConfig, "Logging config is missing key: {$key}");
        }
        
        // Verify default values
        $this->assertIsBool($loggingConfig['enabled']);
        $this->assertIsString($loggingConfig['channel']);
        $this->assertIsString($loggingConfig['level']);
    }

    public function testConfigurationMergingWithDefaults()
    {
        // Test that published config merges correctly with package defaults
        
        // First, get the original package config
        $originalConfig = $this->app['config']['asterisk-pbx-manager'];
        
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        // Modify the published config
        $publishedConfig = include $this->configPath;
        $publishedConfig['connection']['host'] = 'custom.asterisk.server';
        $publishedConfig['connection']['port'] = 9999;
        $publishedConfig['events']['enabled'] = false;
        
        File::put($this->configPath, '<?php return ' . var_export($publishedConfig, true) . ';');
        
        // Reload the application to pick up the published config
        $this->refreshApplication();
        
        // Verify that the custom values are used
        $mergedConfig = $this->app['config']['asterisk-pbx-manager'];
        
        $this->assertEquals('custom.asterisk.server', $mergedConfig['connection']['host']);
        $this->assertEquals(9999, $mergedConfig['connection']['port']);
        $this->assertFalse($mergedConfig['events']['enabled']);
        
        // Verify that non-overridden values still use defaults
        $this->assertEquals($originalConfig['connection']['scheme'], $mergedConfig['connection']['scheme']);
        $this->assertEquals($originalConfig['logging']['channel'], $mergedConfig['logging']['channel']);
    }

    public function testPartialConfigurationPublishing()
    {
        // Test publishing only specific sections of config
        
        // Create a minimal config file that only overrides some values
        $partialConfig = [
            'connection' => [
                'host' => 'partial.asterisk.server',
                'port' => 7777,
                // Intentionally missing other connection keys
            ],
            'events' => [
                'enabled' => false,
                // Intentionally missing other event keys
            ],
            // Intentionally missing logging and queue sections
        ];
        
        File::put($this->configPath, '<?php return ' . var_export($partialConfig, true) . ';');
        
        // Reload the application
        $this->refreshApplication();
        
        $mergedConfig = $this->app['config']['asterisk-pbx-manager'];
        
        // Verify custom values are applied
        $this->assertEquals('partial.asterisk.server', $mergedConfig['connection']['host']);
        $this->assertEquals(7777, $mergedConfig['connection']['port']);
        $this->assertFalse($mergedConfig['events']['enabled']);
        
        // Verify missing values fall back to package defaults
        $this->assertArrayHasKey('username', $mergedConfig['connection']);
        $this->assertArrayHasKey('secret', $mergedConfig['connection']);
        $this->assertArrayHasKey('broadcast', $mergedConfig['events']);
        $this->assertArrayHasKey('logging', $mergedConfig);
        $this->assertArrayHasKey('queue', $mergedConfig);
    }

    public function testConfigurationValidation()
    {
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $publishedConfig = include $this->configPath;
        
        // Test connection validation
        $this->assertIsString($publishedConfig['connection']['host']);
        $this->assertIsInt($publishedConfig['connection']['port']);
        $this->assertIsString($publishedConfig['connection']['username']);
        $this->assertIsString($publishedConfig['connection']['secret']);
        $this->assertIsInt($publishedConfig['connection']['connect_timeout']);
        $this->assertIsInt($publishedConfig['connection']['read_timeout']);
        $this->assertIsString($publishedConfig['connection']['scheme']);
        
        // Test events validation
        $this->assertIsBool($publishedConfig['events']['enabled']);
        $this->assertIsBool($publishedConfig['events']['broadcast']);
        $this->assertIsArray($publishedConfig['events']['listeners']);
        
        // Test logging validation
        $this->assertIsBool($publishedConfig['logging']['enabled']);
        $this->assertIsString($publishedConfig['logging']['channel']);
        $this->assertIsString($publishedConfig['logging']['level']);
        
        // Test queue validation
        $this->assertIsInt($publishedConfig['queue']['default_penalty']);
        $this->assertIsBool($publishedConfig['queue']['default_paused']);
        $this->assertIsInt($publishedConfig['queue']['member_timeout']);
    }

    public function testEnvironmentVariableReferences()
    {
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $publishedConfigContent = File::get($this->configPath);
        
        // Verify that the published config uses env() helpers for configurable values
        $this->assertStringContains("env('ASTERISK_AMI_HOST'", $publishedConfigContent);
        $this->assertStringContains("env('ASTERISK_AMI_PORT'", $publishedConfigContent);
        $this->assertStringContains("env('ASTERISK_AMI_USERNAME'", $publishedConfigContent);
        $this->assertStringContains("env('ASTERISK_AMI_SECRET'", $publishedConfigContent);
    }

    public function testPublishingWithForceFlag()
    {
        // First publish
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config'
        ]);
        
        $this->assertTrue(File::exists($this->configPath));
        
        // Modify the published file
        File::put($this->configPath, '<?php return ["test" => "modified"];');
        $modifiedContent = File::get($this->configPath);
        $this->assertStringContains('modified', $modifiedContent);
        
        // Publish again without force (should not overwrite)
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config'
        ]);
        
        $contentAfterRegularPublish = File::get($this->configPath);
        $this->assertStringContains('modified', $contentAfterRegularPublish);
        
        // Publish with force flag (should overwrite)
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $contentAfterForcePublish = File::get($this->configPath);
        $this->assertStringNotContainsString('modified', $contentAfterForcePublish);
        
        // Verify it's back to the original config structure
        $restoredConfig = include $this->configPath;
        $this->assertIsArray($restoredConfig);
        $this->assertArrayHasKey('connection', $restoredConfig);
    }

    public function testPublishingSpecificTags()
    {
        // Test that only config files are published when using config tag
        $exitCode = Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config'
        ]);
        
        $this->assertEquals(0, $exitCode);
        $this->assertTrue(File::exists($this->configPath));
        
        // Verify no other files were published (like migrations)
        $migrationPath = $this->app->databasePath('migrations');
        $migrationFiles = File::glob($migrationPath . '/*asterisk*.php');
        
        // Migrations shouldn't be published with config tag
        $this->assertEmpty($migrationFiles, 'Migration files should not be published with config tag');
    }

    public function testConfigurationArrayStructure()
    {
        // Publish the configuration
        Artisan::call('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config',
            '--force' => true
        ]);
        
        $publishedConfig = include $this->configPath;
        
        // Verify the structure matches expected format
        $this->assertIsArray($publishedConfig['connection']);
        $this->assertIsArray($publishedConfig['events']);
        $this->assertIsArray($publishedConfig['events']['listeners']);
        $this->assertIsArray($publishedConfig['logging']);
        $this->assertIsArray($publishedConfig['queue']);
        $this->assertIsArray($publishedConfig['channel']);
        $this->assertIsArray($publishedConfig['security']);
        
        // Verify nested arrays have expected structure
        $this->assertIsArray($publishedConfig['queue']['strategies']);
        $this->assertIsArray($publishedConfig['channel']['dtmf']);
        $this->assertIsArray($publishedConfig['security']['validation']);
    }
}