# Security Policy

## Supported Versions

We actively maintain and provide security updates for the following versions of the Asterisk PBX Manager Laravel package:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| 0.x     | :x:                |

## Security Considerations

### Asterisk Manager Interface (AMI) Security

The Asterisk PBX Manager package connects to Asterisk systems via the Manager Interface, which provides administrative access to your PBX system. Please consider the following security implications:

#### Network Security
- **Firewall Configuration**: Ensure AMI port (default 5038) is only accessible from trusted networks
- **TLS/SSL**: Use encrypted connections when possible (`scheme: tcps://`)
- **IP Restrictions**: Configure Asterisk to only accept AMI connections from specific IP addresses
- **Network Segmentation**: Place Asterisk servers in secure network segments

#### Authentication Security
- **Strong Credentials**: Use strong, unique passwords for AMI users
- **Principle of Least Privilege**: Grant only necessary AMI permissions to users
- **Credential Rotation**: Regularly rotate AMI credentials
- **Environment Variables**: Never commit AMI credentials to version control

#### Application Security
- **Input Validation**: All AMI commands are validated before execution
- **SQL Injection Prevention**: Database queries use parameter binding
- **XSS Prevention**: Event data is properly escaped when displayed
- **CSRF Protection**: Use Laravel's CSRF protection for web interfaces

### Configuration Security

#### Environment Variables
Always use environment variables for sensitive configuration:

```env
# Never commit these values
ASTERISK_AMI_SECRET=your_strong_password_here
ASTERISK_AMI_USERNAME=your_ami_user

# Use strong passwords and rotate regularly
ASTERISK_AMI_HOST=internal.asterisk.server
ASTERISK_AMI_PORT=5038
```

#### Database Security
- Use encrypted database connections when possible
- Limit database user permissions to necessary operations only
- Enable database query logging for audit purposes

### Event Broadcasting Security

When using Laravel's broadcasting features:

- **Authentication**: Implement proper channel authentication
- **Authorization**: Use channel authorization to control access
- **Data Sanitization**: Sanitize event data before broadcasting
- **Rate Limiting**: Implement rate limiting for event subscriptions

## Reporting a Vulnerability

We take security vulnerabilities seriously. If you discover a security vulnerability in the Asterisk PBX Manager package, please report it responsibly.

### How to Report

1. **Do NOT** create a public GitHub issue for security vulnerabilities
2. **Do NOT** discuss the vulnerability publicly until it has been addressed

**Preferred Method:**
Send an email to: **security@[your-domain].com**

**Include the following information:**
- Description of the vulnerability
- Steps to reproduce the issue
- Potential impact assessment
- Your contact information
- Any proof-of-concept code (if applicable)

### Response Timeline

- **Initial Response**: Within 24 hours
- **Assessment**: Within 72 hours
- **Status Update**: Weekly updates until resolution
- **Resolution Target**: 30 days for critical issues, 90 days for others

### Disclosure Policy

- We will acknowledge receipt of your vulnerability report
- We will assess the vulnerability and determine its severity
- We will work on a fix and coordinate disclosure timing with you
- We will credit you for the discovery (unless you prefer to remain anonymous)
- We will notify users of security updates through our release notes

## Security Best Practices

### For Users

#### AMI Configuration
```php
// config/asterisk-pbx-manager.php
return [
    'connection' => [
        // Use environment variables
        'host' => env('ASTERISK_AMI_HOST'),
        'port' => env('ASTERISK_AMI_PORT', 5038),
        'username' => env('ASTERISK_AMI_USERNAME'),
        'secret' => env('ASTERISK_AMI_SECRET'),
        'scheme' => env('ASTERISK_AMI_SCHEME', 'tcps://'), // Use TLS
        
        // Connection timeouts
        'connect_timeout' => env('ASTERISK_AMI_CONNECT_TIMEOUT', 10),
        'read_timeout' => env('ASTERISK_AMI_READ_TIMEOUT', 10),
    ],
    
    // Event security
    'events' => [
        'enabled' => env('ASTERISK_EVENTS_ENABLED', true),
        'broadcast' => env('ASTERISK_EVENTS_BROADCAST', false), // Disable if not needed
        'log_to_database' => env('ASTERISK_LOG_TO_DATABASE', true),
        
        // Filter sensitive events
        'filtered_events' => [
            'UserEvent', // May contain sensitive data
            'VarSet',    // Variable values might be sensitive
        ],
    ],
];
```

#### Asterisk Manager.conf Security
```ini
[general]
enabled = yes
port = 5038
bindaddr = 10.0.0.100  ; Bind to specific interface
tlsenable = yes        ; Enable TLS
tlsbindaddr = 0.0.0.0:5039
tlscertfile = /etc/asterisk/keys/manager.crt
tlsprivatekey = /etc/asterisk/keys/manager.key

[ami_user]
secret = very_strong_password_here
read = system,call,log,verbose,command,agent,user,config
write = system,call,originate,agent,user,config
; Limit permissions to minimum required
```

#### Laravel Security
- Keep Laravel and all dependencies updated
- Use HTTPS for all web interfaces
- Implement proper authentication and authorization
- Enable Laravel's security headers
- Use Content Security Policy (CSP) headers
- Implement rate limiting for API endpoints

### For Developers

#### Code Security
- Validate all inputs before processing
- Use parameterized queries for database operations
- Implement proper error handling without information disclosure
- Use Laravel's built-in security features
- Follow secure coding practices

#### Testing Security
- Include security tests in your test suite
- Test with various input combinations
- Verify proper error handling
- Test authentication and authorization
- Perform penetration testing on staging environments

## Security Resources

### Documentation
- [Laravel Security Documentation](https://laravel.com/docs/security)
- [Asterisk Security Guide](https://wiki.asterisk.org/wiki/display/AST/Asterisk+Security)
- [OWASP PHP Security Guide](https://owasp.org/www-project-php-security-cheat-sheet/)

### Tools
- [Laravel Security Checker](https://github.com/enlightn/security-checker)
- [PHPStan Security Rules](https://github.com/phpstan/phpstan)
- [OWASP ZAP](https://owasp.org/www-project-zap/)

## Contact

For security-related questions or concerns:
- Security Email: ronniel.castanito@apntelecom.com
- General Support: ronniel.castanito@apntelecom.com

---

**Note**: This security policy is part of our commitment to maintaining a secure and reliable Asterisk PBX Manager package. We encourage responsible disclosure and appreciate the security community's efforts to keep our users safe.