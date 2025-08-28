## Pull Request Description
Please include a summary of the changes and the related issue. Please also include relevant motivation and context.

**Fixes #** (issue number)

## Type of Change
Please delete options that are not relevant.

- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update
- [ ] Performance improvement
- [ ] Code refactoring (no functional changes)
- [ ] Test coverage improvement
- [ ] Configuration change
- [ ] Dependency update

## Component Areas
Which areas of the codebase does this PR affect?

- [ ] **Core Services** (AsteriskManagerService, EventProcessor, ActionExecutor)
- [ ] **AMI Integration** (PAMI client interaction, connection handling)
- [ ] **Event System** (Event classes, listeners, broadcasting)
- [ ] **Queue Management** (QueueManagerService, queue operations)
- [ ] **Call Management** (Originate, hangup, call monitoring)
- [ ] **Database/Models** (CallLog, AsteriskEvent models, migrations)
- [ ] **Configuration** (Config files, environment variables)
- [ ] **Artisan Commands** (CLI tools, monitoring commands)
- [ ] **Service Provider** (Laravel service registration)
- [ ] **Facades** (Laravel facade implementation)
- [ ] **Exception Handling** (Custom exceptions, error handling)
- [ ] **Testing** (Unit tests, integration tests)
- [ ] **Documentation** (README, API docs, examples)

## Asterisk/AMI Details
If this PR involves Asterisk Manager Interface changes:

**AMI Actions Affected:**
- [ ] Originate
- [ ] QueueAdd/QueueRemove
- [ ] QueueStatus/QueueSummary
- [ ] Status/CoreStatus
- [ ] Command
- [ ] Hangup
- [ ] Other: ___________

**AMI Events Handled:**
- [ ] Dial/DialBegin/DialEnd
- [ ] Queue events (Join, Leave, MemberStatus)
- [ ] Bridge events
- [ ] Hangup
- [ ] NewChannel/ChannelDestroy
- [ ] Other: ___________

**Asterisk Version Tested:**
- [ ] Asterisk 16.x
- [ ] Asterisk 18.x
- [ ] Asterisk 19.x
- [ ] Asterisk 20.x
- [ ] Asterisk 21.x

## Changes Made
Describe the changes made in detail:

### Code Changes
- 
- 
- 

### Configuration Changes
- 
- 

### Database Changes
- 
- 

## Testing
Please describe the tests that you ran to verify your changes.

### Test Environment
- **Laravel Version:** 
- **PHP Version:** 
- **Asterisk Version:** 
- **Database:** 
- **Operating System:** 

### Tests Performed
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed
- [ ] Performance testing (if applicable)
- [ ] Asterisk integration testing

### Test Results
```
# Paste test output here
```

## Breaking Changes
If this is a breaking change, please describe:
- What functionality is affected
- Migration steps required
- Backwards compatibility considerations

## Performance Impact
- [ ] No performance impact
- [ ] Performance improvement
- [ ] Potential performance degradation (explain below)

**Performance Notes:**

## Security Considerations
- [ ] No security implications
- [ ] Security improvement
- [ ] Potential security impact (explain below)

**Security Notes:**

## Documentation
- [ ] Code comments updated
- [ ] PHPDoc updated
- [ ] README updated
- [ ] API documentation updated
- [ ] Configuration documentation updated
- [ ] Examples updated
- [ ] CHANGELOG updated

## Dependencies
List any new dependencies or version changes:
- 
- 

## Screenshots/Logs
If applicable, add screenshots or log outputs that demonstrate the changes:

## Checklist
- [ ] My code follows the code style of this project
- [ ] I have performed a self-review of my own code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] I have made corresponding changes to the documentation
- [ ] My changes generate no new warnings or errors
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing unit tests pass locally with my changes
- [ ] Any dependent changes have been merged and published
- [ ] I have checked that this PR doesn't duplicate an existing one
- [ ] I have verified backwards compatibility or documented breaking changes

## Additional Notes
Add any additional notes, concerns, or considerations for reviewers:

---

**For Maintainers:**
- [ ] Code review completed
- [ ] Tests verified
- [ ] Documentation review completed
- [ ] Security review completed (if applicable)
- [ ] Performance impact assessed
- [ ] Ready for merge