---
name: Bug Report
about: Create a report to help us improve the Asterisk PBX Manager package
title: '[BUG] '
labels: ['bug', 'needs-triage']
assignees: ''
---

## Bug Description
A clear and concise description of what the bug is.

## Environment Information
**Package Version:** (e.g., v1.2.3)
**Laravel Version:** (e.g., 12.25.0)
**PHP Version:** (e.g., 8.4.11)
**Asterisk Version:** (e.g., 18.20.0)
**Operating System:** (e.g., Ubuntu 22.04, Windows 11, macOS 14)
**PAMI Version:** (e.g., 2.0.x)

## Configuration
Please share relevant configuration (remove sensitive data):

**AMI Configuration:**
```env
ASTERISK_AMI_HOST=
ASTERISK_AMI_PORT=
ASTERISK_AMI_USERNAME=
# Don't include ASTERISK_AMI_SECRET
```

**Package Configuration:**
```php
// Contents of config/asterisk-pbx-manager.php (relevant sections only)
```

## Steps to Reproduce
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

## Expected Behavior
A clear and concise description of what you expected to happen.

## Actual Behavior
A clear and concise description of what actually happened.

## Code Examples
If applicable, add code examples that demonstrate the issue:

```php
// Your code here
```

## Error Messages
If there are error messages, please include them here:

```
Error message or stack trace
```

## Log Output
Please include relevant log entries from Laravel logs and Asterisk logs:

**Laravel Log:**
```
[timestamp] Log entries...
```

**Asterisk AMI Log (if available):**
```
AMI log entries...
```

## Screenshots
If applicable, add screenshots to help explain your problem.

## Additional Context
Add any other context about the problem here. This might include:
- Network configuration details
- Firewall settings
- Other packages that might be interacting
- Recent changes to your setup

## Possible Solution
If you have an idea of what might be causing the issue or how to fix it, please describe it here.

## Checklist
- [ ] I have searched existing issues to ensure this is not a duplicate
- [ ] I have included all relevant environment information
- [ ] I have provided steps to reproduce the issue
- [ ] I have included error messages and logs where applicable
- [ ] I have removed any sensitive information from the report