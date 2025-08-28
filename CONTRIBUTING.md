# Contributing to Asterisk PBX Manager

Thank you for your interest in contributing to the Asterisk PBX Manager Laravel package! This guide will help you get started with development, understand our standards, and submit high-quality contributions.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Documentation Standards](#documentation-standards)
- [Pull Request Process](#pull-request-process)
- [Issue Reporting](#issue-reporting)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Release Process](#release-process)

## Code of Conduct

This project adheres to a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to the project maintainers.

## Getting Started

### Prerequisites

Before contributing, ensure you have:

- **PHP 8.4 or higher**
- **Composer** for dependency management
- **Laravel 12.0+** for testing integration
- **Git** for version control
- **Docker** (optional, for Asterisk testing)
- **Node.js and npm** (if working on frontend components)

### Fork and Clone

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/your-username/asterisk-pbx-manager.git
   cd asterisk-pbx-manager
   ```

3. **Add the upstream repository**:
   ```bash
   git remote add upstream https://github.com/apn-ra/asterisk-pbx-manager.git
   ```

## Development Setup

### 1. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install development dependencies
composer install --dev
```

### 2. Environment Configuration

Create a local development environment:

```bash
# Copy environment template
cp .env.example .env.dev

# Configure development settings
nano .env.dev
```

Basic development configuration:

```env
# Development Environment
APP_ENV=local
APP_DEBUG=true

# Asterisk Configuration (point to test server or Docker)
ASTERISK_AMI_HOST=127.0.0.1
ASTERISK_AMI_PORT=5038
ASTERISK_AMI_USERNAME=admin
ASTERISK_AMI_SECRET=dev_password

# Development Settings
ASTERISK_DEBUG_MODE=true
ASTERISK_LOG_LEVEL=debug
ASTERISK_ENABLE_PROFILING=true

# Testing Settings
ASTERISK_MOCK_MODE=false
ASTERISK_EVENTS_ENABLED=true
```

### 3. Development Tools Setup

#### Code Quality Tools

```bash
# Install PHP CS Fixer for code formatting
composer global require friendsofphp/php-cs-fixer

# Install PHPStan for static analysis
composer global require phpstan/phpstan

# Install Psalm (alternative to PHPStan)
composer global require vimeo/psalm
```

#### IDE Configuration

##### VS Code
Create `.vscode/settings.json`:
```json
{
    "php.validate.executablePath": "/usr/bin/php",
    "php-cs-fixer.executablePath": "php-cs-fixer",
    "php-cs-fixer.config": ".php-cs-fixer.php",
    "editor.formatOnSave": true,
    "files.associations": {
        "*.php": "php"
    }
}
```

##### PhpStorm
- Install Laravel Plugin
- Configure PHP CS Fixer
- Set up PHPUnit test runner
- Enable PSR-12 code style

### 4. Testing Environment Setup

#### Database Setup

```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE asterisk_pbx_test;"

# Configure test environment
cp .env.dev .env.testing
```

Test environment configuration:
```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# Mock mode for unit tests
ASTERISK_MOCK_MODE=true
ASTERISK_EVENTS_ENABLED=false
ASTERISK_LOG_TO_DATABASE=false
```

#### Docker Test Environment (Optional)

For testing with real Asterisk:

```bash
# Start Asterisk container
docker-compose -f docker/docker-compose.test.yml up -d

# Configure for Docker testing
export ASTERISK_AMI_HOST=localhost
export ASTERISK_AMI_PORT=5038
```

### 5. Verify Setup

```bash
# Run health check
php artisan asterisk:health-check --verbose

# Run test suite
composer test

# Check code style
composer cs:check
```

## Development Workflow

### Branch Strategy

We use **Git Flow** workflow:

- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/*` - Individual feature branches
- `hotfix/*` - Critical bug fixes
- `release/*` - Release preparation

### Creating Feature Branches

```bash
# Update local develop branch
git checkout develop
git pull upstream develop

# Create feature branch
git checkout -b feature/your-feature-name

# Make changes and commit
git add .
git commit -m "feat: add new feature"

# Push to your fork
git push origin feature/your-feature-name
```

### Commit Message Standards

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

**Types:**
- `feat` - New features
- `fix` - Bug fixes
- `docs` - Documentation changes
- `style` - Code style changes (formatting, etc.)
- `refactor` - Code refactoring
- `test` - Adding or updating tests
- `chore` - Maintenance tasks

**Examples:**
```bash
feat(queue): add bulk member operations
fix(connection): resolve timeout issues
docs(api): update service documentation
test(events): add event processing tests
```

## Coding Standards

### PHP Standards

We follow **PSR-12** coding standard with additional rules:

#### Code Style Configuration

Create `.php-cs-fixer.php`:
```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => false,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
            ],
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'single_trait_insert_per_statement' => true,
    ])
    ->setFinder($finder)
    ->setUsingCache(true);
```

#### Type Declarations

Always use type hints:
```php
// ✅ Good
public function originateCall(string $channel, string $extension, array $options = []): bool
{
    // Implementation
}

// ❌ Bad
public function originateCall($channel, $extension, $options = [])
{
    // Implementation
}
```

#### Documentation

All public methods must have PHPDoc comments:
```php
/**
 * Originate a call from a channel to an extension.
 *
 * @param string $channel Source channel (e.g., 'SIP/1001')
 * @param string $extension Destination extension
 * @param array<string, mixed> $options Additional call options
 * @return bool True if call initiated successfully
 * @throws ActionExecutionException If the originate action fails
 */
public function originateCall(string $channel, string $extension, array $options = []): bool
{
    // Implementation
}
```

#### Error Handling

Use specific exceptions:
```php
// ✅ Good
try {
    $result = $this->client->send($action);
} catch (ConnectionException $e) {
    throw new AsteriskConnectionException("Failed to connect to AMI: " . $e->getMessage(), 0, $e);
} catch (TimeoutException $e) {
    throw new ActionExecutionException("AMI action timed out: " . $e->getMessage(), 0, $e);
}

// ❌ Bad
try {
    $result = $this->client->send($action);
} catch (Exception $e) {
    throw $e;
}
```

### Code Quality Tools

#### Running Code Style Checks

```bash
# Check code style
composer cs:check

# Fix code style automatically
composer cs:fix
```

#### Static Analysis

```bash
# Run PHPStan
composer analyse

# Run Psalm
composer psalm
```

#### Configuration Files

**composer.json scripts:**
```json
{
    "scripts": {
        "test": "phpunit",
        "test-coverage": "phpunit --coverage-html coverage",
        "cs:check": "php-cs-fixer fix --dry-run --diff",
        "cs:fix": "php-cs-fixer fix",
        "analyse": "phpstan analyse",
        "psalm": "psalm"
    }
}
```

## Testing Guidelines

### Test Structure

We use three types of tests:

1. **Unit Tests** - Test individual classes/methods
2. **Integration Tests** - Test component interactions
3. **Performance Tests** - Test under load conditions

### Test Organization

```
tests/
├── Unit/
│   ├── Services/
│   ├── Events/
│   ├── Models/
│   └── Facades/
├── Integration/
│   ├── Commands/
│   ├── ServiceProvider/
│   └── Database/
└── Performance/
    ├── ConnectionLoad/
    ├── EventProcessing/
    └── MemoryUsage/
```

### Writing Tests

#### Unit Test Example

```php
<?php

namespace AsteriskPbxManager\Tests\Unit\Services;

use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use PAMI\Client\Impl\ClientImpl;
use PHPUnit\Framework\TestCase;
use Mockery;

class AsteriskManagerServiceTest extends TestCase
{
    private AsteriskManagerService $service;
    private ClientImpl $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockClient = Mockery::mock(ClientImpl::class);
        $this->service = new AsteriskManagerService($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testConnectionSuccess(): void
    {
        // Arrange
        $this->mockClient
            ->shouldReceive('open')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->service->connect();

        // Assert
        $this->assertTrue($result);
    }

    public function testConnectionFailure(): void
    {
        // Arrange
        $this->mockClient
            ->shouldReceive('open')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        // Act & Assert
        $this->expectException(AsteriskConnectionException::class);
        $this->expectExceptionMessage('Failed to connect to AMI');
        
        $this->service->connect();
    }
}
```

#### Integration Test Example

```php
<?php

namespace AsteriskPbxManager\Tests\Integration;

use AsteriskPbxManager\AsteriskPbxManagerServiceProvider;
use AsteriskPbxManager\Services\AsteriskManagerService;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [AsteriskPbxManagerServiceProvider::class];
    }

    public function testServiceRegistration(): void
    {
        // Act
        $service = $this->app->make('asterisk-manager');

        // Assert
        $this->assertInstanceOf(AsteriskManagerService::class, $service);
    }

    public function testConfigurationPublishing(): void
    {
        // Act
        $this->artisan('vendor:publish', [
            '--provider' => AsteriskPbxManagerServiceProvider::class,
            '--tag' => 'config'
        ])->assertExitCode(0);

        // Assert
        $this->assertTrue($this->app['files']->exists(config_path('asterisk-pbx-manager.php')));
    }
}
```

### Test Requirements

- **Coverage**: Maintain >90% code coverage
- **Assertions**: Each test should have meaningful assertions
- **Independence**: Tests must be independent and repeatable
- **Speed**: Unit tests should run in <1 second each
- **Mocking**: Mock external dependencies (PAMI, database, etc.)

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit --testsuite=Unit

# Run with coverage
composer test-coverage

# Run performance tests
./vendor/bin/phpunit --testsuite=Performance
```

## Documentation Standards

### API Documentation

All public methods require comprehensive documentation:

```php
/**
 * Add a member to a specified queue.
 *
 * This method adds an agent to an Asterisk queue with optional configuration
 * parameters such as penalty and initial pause state.
 *
 * @param string $queue Queue name
 * @param string $member Member channel (e.g., 'SIP/1001')
 * @param array<string, mixed> $options Additional member options
 * @param int $options['penalty'] Member penalty (0-999, lower = higher priority)
 * @param bool $options['paused'] Whether to add member in paused state
 * @param string $options['membername'] Display name for the member
 * @return bool True if member added successfully
 * @throws ActionExecutionException If the action fails
 * @throws AsteriskConnectionException If not connected to AMI
 * 
 * @example
 * ```php
 * $result = $queueManager->addMember('support', 'SIP/1001', [
 *     'penalty' => 1,
 *     'paused' => false,
 *     'membername' => 'John Doe'
 * ]);
 * ```
 */
public function addMember(string $queue, string $member, array $options = []): bool
{
    // Implementation
}
```

### README Updates

When adding features:

1. Update feature list in README
2. Add usage examples
3. Update configuration examples if needed
4. Add to troubleshooting section if applicable

### Changelog

Follow [Keep a Changelog](https://keepachangelog.com/) format:

```markdown
## [1.2.0] - 2024-01-15

### Added
- Bulk queue member operations
- Connection pooling for high-load scenarios
- Performance monitoring capabilities

### Changed
- Improved error handling for connection timeouts
- Updated PAMI library to version 2.1

### Fixed
- Memory leak in event processing
- SSL certificate validation issues

### Security
- Added input sanitization for AMI commands
```

## Pull Request Process

### Before Submitting

Ensure your contribution:

1. **Follows coding standards** (run `composer cs:fix`)
2. **Passes all tests** (run `composer test`)
3. **Has adequate test coverage** (>90%)
4. **Includes documentation** for new features
5. **Updates relevant examples** if applicable

### PR Template

When creating a PR, use this template:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Performance tests pass (if applicable)
- [ ] Manual testing completed

## Checklist
- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Code is commented, particularly in hard-to-understand areas
- [ ] Documentation updated
- [ ] Changes generate no new warnings
- [ ] Tests added/updated for changes
- [ ] All tests pass locally

## Screenshots (if applicable)
Add screenshots or code examples

## Related Issues
Closes #123
References #456
```

### Review Process

1. **Automated checks** run (CI/CD pipeline)
2. **Code review** by maintainers
3. **Testing** in development environment
4. **Documentation review** if applicable
5. **Approval and merge** by project maintainers

### PR Guidelines

- **One feature per PR** - Keep PRs focused
- **Clear commit history** - Use meaningful commit messages
- **Up-to-date branch** - Rebase on latest develop
- **Tests included** - All new code must have tests
- **Documentation** - Update docs for new features

## Issue Reporting

### Bug Reports

Use the bug report template:

```markdown
## Bug Description
A clear description of the bug

## Environment
- PHP Version: 8.4.x
- Laravel Version: 12.x
- Package Version: 1.x.x
- Asterisk Version: 18.x
- OS: Ubuntu 22.04

## Steps to Reproduce
1. Configure AMI with...
2. Call method...
3. Observe error...

## Expected Behavior
What should happen

## Actual Behavior
What actually happens

## Error Messages
```
Full error messages and stack traces
```

## Additional Context
Any other relevant information
```

### Feature Requests

Use the feature request template:

```markdown
## Feature Description
Clear description of the requested feature

## Use Case
Why is this feature needed?

## Proposed Implementation
How should this feature work?

## Alternatives Considered
Other approaches you considered

## Additional Context
Any other relevant information
```

### Issue Labels

Common labels used:
- `bug` - Something isn't working
- `enhancement` - New feature or request
- `documentation` - Improvements to documentation
- `good first issue` - Good for newcomers
- `help wanted` - Extra attention needed
- `priority: high` - Critical issues
- `status: needs review` - Needs maintainer review

## Security Vulnerabilities

If you discover a security vulnerability, please:

1. **DO NOT** create a public issue
2. **Email** security@apn-ra.com with details
3. **Include** steps to reproduce
4. **Wait** for confirmation before public disclosure

We take security seriously and will respond within 48 hours.

## Release Process

### Version Numbering

We follow [Semantic Versioning](https://semver.org/):

- `MAJOR.MINOR.PATCH`
- `1.2.3` = Major.Minor.Patch

### Release Types

- **Patch** (1.0.1) - Bug fixes
- **Minor** (1.1.0) - New features, backward compatible
- **Major** (2.0.0) - Breaking changes

### Release Workflow

1. **Create release branch** from develop
2. **Update version** in composer.json
3. **Update CHANGELOG.md**
4. **Run full test suite**
5. **Create pull request** to main
6. **Tag release** after merge
7. **Publish** to Packagist
8. **Update documentation**

## Development Resources

### Useful Links

- [Laravel Documentation](https://laravel.com/docs)
- [PAMI Library](https://github.com/marcelog/PAMI)
- [Asterisk Documentation](https://docs.asterisk.org/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

### Development Tools

- [Laravel Telescope](https://laravel.com/docs/telescope) - Debugging
- [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar) - Debug info
- [Clockwork](https://underground.works/clockwork/) - Performance profiling

### Community

- [GitHub Discussions](https://github.com/apn-ra/asterisk-pbx-manager/discussions)
- [Issues](https://github.com/apn-ra/asterisk-pbx-manager/issues)
- [Discord Server](https://discord.gg/asterisk-pbx-manager)

## Recognition

Contributors will be:

- **Listed** in CONTRIBUTORS.md
- **Mentioned** in release notes
- **Credited** in package documentation
- **Invited** to maintainer team (for significant contributions)

Thank you for contributing to Asterisk PBX Manager! 🎉