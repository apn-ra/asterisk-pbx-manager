# Semantic Versioning Strategy

This document outlines the semantic versioning strategy for the Asterisk PBX Manager Laravel package.

## Version Format

We follow [Semantic Versioning 2.0.0](https://semver.org/) using the format `MAJOR.MINOR.PATCH`:

- **MAJOR**: Incremented for incompatible API changes
- **MINOR**: Incremented for backwards-compatible functionality additions
- **PATCH**: Incremented for backwards-compatible bug fixes

### Pre-release Versions

Pre-release versions use the format `MAJOR.MINOR.PATCH-LABEL.NUMBER`:

- `1.0.0-alpha.1` - Alpha releases for early testing
- `1.0.0-beta.1` - Beta releases for feature-complete testing
- `1.0.0-rc.1` - Release candidates for final testing

## Version Guidelines

### Major Version (X.0.0)

Increment when making incompatible API changes:

- Breaking changes to public API methods
- Removing deprecated features
- Changing method signatures
- Updating minimum PHP or Laravel version requirements
- Major architectural changes

**Examples:**
- Removing a public method from `AsteriskManagerService`
- Changing constructor parameters in service classes
- Updating from Laravel 11 to Laravel 12 support

### Minor Version (0.X.0)

Increment when adding functionality in a backwards-compatible manner:

- Adding new public methods or classes
- Adding new configuration options
- Adding new event types
- Adding new Artisan commands
- Deprecating features (without removal)

**Examples:**
- Adding new queue management methods
- Introducing new event broadcasting features
- Adding new configuration parameters with defaults

### Patch Version (0.0.X)

Increment for backwards-compatible bug fixes:

- Fixing bugs without changing the API
- Performance improvements
- Documentation updates
- Internal refactoring
- Security fixes that don't break compatibility

**Examples:**
- Fixing connection timeout handling
- Correcting event processing logic
- Updating PHPDoc comments

## Release Process

### 1. Pre-Release Checklist

Before any release:

- [ ] All tests are passing
- [ ] Code coverage is maintained or improved
- [ ] Documentation is updated
- [ ] CHANGELOG.md is updated with new version
- [ ] Version number is updated in relevant files
- [ ] Security vulnerabilities are addressed

### 2. Release Types

#### Development Releases (0.x.x)

- Used during initial development
- May have breaking changes between minor versions
- Full semantic versioning starts at 1.0.0

#### Stable Releases (1.0.0+)

- Follow strict semantic versioning
- Breaking changes only in major versions
- Backward compatibility maintained within major version

### 3. Release Timeline

- **Major releases**: Every 12-18 months
- **Minor releases**: Every 2-3 months
- **Patch releases**: As needed for critical bugs/security

## Branching Strategy

### Main Branches

- `main` - Stable production-ready code
- `develop` - Integration branch for features

### Supporting Branches

- `feature/*` - Feature development branches
- `release/*` - Release preparation branches  
- `hotfix/*` - Critical production fixes

### Version Branch Management

1. **Feature Development**
   ```bash
   git checkout -b feature/new-queue-management develop
   # Development work
   git checkout develop
   git merge --no-ff feature/new-queue-management
   ```

2. **Release Preparation**
   ```bash
   git checkout -b release/1.2.0 develop
   # Update version numbers, documentation
   git checkout main
   git merge --no-ff release/1.2.0
   git tag -a v1.2.0 -m "Release version 1.2.0"
   ```

3. **Hotfix Process**
   ```bash
   git checkout -b hotfix/1.1.1 main
   # Fix critical issue
   git checkout main
   git merge --no-ff hotfix/1.1.1
   git tag -a v1.1.1 -m "Hotfix version 1.1.1"
   ```

## Version Management

### Version File Locations

Update version numbers in:

1. `composer.json` (if using version field)
2. `src/AsteriskPbxManagerServiceProvider.php` (version constant)
3. `README.md` (installation examples)
4. `CHANGELOG.md` (new version entry)

### Git Tagging

All releases must be tagged:

```bash
# Lightweight tag
git tag v1.2.0

# Annotated tag (preferred)
git tag -a v1.2.0 -m "Release version 1.2.0"

# Push tags
git push origin --tags
```

### Tag Format

- Use `v` prefix: `v1.2.0`
- No additional prefixes or suffixes
- Match exactly with semantic version

## Deprecation Policy

### Deprecation Process

1. **Mark as Deprecated**
   - Add `@deprecated` PHPDoc annotation
   - Trigger deprecation notice in code
   - Document in CHANGELOG.md

2. **Deprecation Period**
   - Minimum one minor version cycle
   - Provide migration documentation
   - Suggest alternatives in deprecation notice

3. **Removal**
   - Remove in next major version
   - Document breaking change in CHANGELOG.md
   - Provide migration guide

### Example Deprecation

```php
/**
 * Get queue status.
 * 
 * @deprecated since 1.2.0, use getQueueDetails() instead
 * @see getQueueDetails()
 */
public function getQueueStatus(string $queue): array
{
    trigger_error(
        'getQueueStatus() is deprecated, use getQueueDetails() instead',
        E_USER_DEPRECATED
    );
    
    return $this->getQueueDetails($queue);
}
```

## Backward Compatibility

### Commitment

Within a major version:
- Public API methods remain available
- Method signatures don't change
- Return types remain compatible
- Configuration structure remains stable

### Breaking Change Examples

**Allowed in Major Versions Only:**
- Removing public methods
- Changing method signatures
- Changing return types incompatibly
- Removing configuration options
- Changing default behavior significantly

**Allowed in Minor Versions:**
- Adding optional parameters with defaults
- Adding new public methods
- Adding new configuration options with defaults
- Expanding return types (adding properties)

## Version Documentation

### CHANGELOG.md Format

```markdown
# Changelog

## [1.2.0] - 2024-08-28

### Added
- New queue analytics features
- Real-time event monitoring dashboard

### Changed
- Improved connection handling performance

### Deprecated
- `getQueueStatus()` method (use `getQueueDetails()`)

### Fixed
- Event processing memory leak
- Connection timeout edge cases

### Security
- Updated PAMI dependency for security fixes

## [1.1.0] - 2024-07-15
...
```

### Release Notes

Each release should include:
- High-level overview of changes
- Migration guide for breaking changes
- New feature highlights
- Performance improvements
- Security updates

## Automation

### GitHub Actions

Automate version management:

```yaml
name: Release
on:
  push:
    tags: ['v*']
jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - name: Create Release
        uses: actions/create-release@v1
        with:
          tag_name: ${{ github.ref }}
          release_name: Release ${{ github.ref }}
          draft: false
          prerelease: false
```

### Packagist Integration

- Automatic updates on git tag push
- Webhook configuration for immediate updates
- Version constraint validation

## Version History

### Development Milestones

- `0.1.0` - Initial development release
- `0.5.0` - Core AMI functionality complete
- `1.0.0` - First stable release
- `1.1.0` - Queue management features
- `1.2.0` - Event processing enhancements
- `2.0.0` - Laravel 12 compatibility (future)

This versioning strategy ensures predictable releases, clear upgrade paths, and maintains package stability while allowing for continuous improvement and feature development.