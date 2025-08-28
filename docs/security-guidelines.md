# Security Configuration Guidelines

**Asterisk PBX Manager Laravel Package**  
**Version:** 1.0  
**Date:** August 28, 2025  

## Overview

This document provides comprehensive security configuration guidelines for deploying and operating the Asterisk PBX Manager Laravel package in production environments. These guidelines cover configuration hardening, authentication, authorization, network security, audit logging, and monitoring best practices.

## Table of Contents

1. [AMI Connection Security](#ami-connection-security)
2. [Authentication and Authorization](#authentication-and-authorization)
3. [Input Validation and Sanitization](#input-validation-and-sanitization)
4. [Audit Logging Configuration](#audit-logging-configuration)
5. [Network Security](#network-security)
6. [Error Handling and Information Disclosure](#error-handling-and-information-disclosure)
7. [Configuration Hardening](#configuration-hardening)
8. [Monitoring and Alerting](#monitoring-and-alerting)
9. [Deployment Security](#deployment-security)
10. [Security Checklists](#security-checklists)

## AMI Connection Security

### 1. Secure AMI Credentials

**Environment Variables:**
```env
# Use strong, unique credentials
ASTERISK_AMI_USERNAME=pbx_manager_secure
ASTERISK_AMI_SECRET=your_very_strong_ami_password_here

# Use non-default AMI port if possible
ASTERISK_AMI_PORT=5039

# Restrict connection to specific host
ASTERISK_AMI_HOST=10.0.1.100
```

**Best Practices:**
- Use complex passwords with minimum 20 characters
- Include uppercase, lowercase, numbers, and special characters
- Rotate AMI credentials regularly (quarterly recommended)
- Never use default credentials (admin/admin)
- Store credentials securely using Laravel's encryption

### 2. AMI User Permissions

**Asterisk manager.conf configuration:**
```ini
[pbx_manager_secure]
secret = your_very_strong_ami_password_here
deny = 0.0.0.0/0.0.0.0
permit = 10.0.1.0/255.255.255.0
; Restrict to minimum required permissions
read = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan
write = system,call,log,verbose,command,agent,user,config,dtmf,reporting,cdr,dialplan
; Avoid using 'all' permissions
```

**Permission Guidelines:**
- Grant only minimum required AMI permissions
- Regularly audit and review AMI user permissions
- Use separate AMI users for different application functions
- Monitor AMI permission usage through audit logs

### 3. Connection Security

**Configuration:**
```php
// config/asterisk-pbx-manager.php
'connection' => [
    'scheme' => env('ASTERISK_AMI_SCHEME', 'tcp://'),
    'connect_timeout' => env('ASTERISK_AMI_CONNECT_TIMEOUT', 5),
    'read_timeout' => env('ASTERISK_AMI_READ_TIMEOUT', 10),
],
```

**Recommendations:**
- Use TLS encryption when available (AMI over TLS)
- Set reasonable timeout values to prevent resource exhaustion
- Implement connection pooling limits
- Monitor connection attempts and failures

## Authentication and Authorization

### 1. Event Broadcasting Authentication

**Enable Authentication:**
```env
ASTERISK_BROADCAST_AUTH_ENABLED=true
ASTERISK_BROADCAST_AUTH_GUARD=web
ASTERISK_BROADCAST_AUTH_MIDDLEWARE=auth,verified
ASTERISK_BROADCAST_PERMISSIONS=asterisk.events.listen
```

**Token-based Authentication:**
```env
ASTERISK_BROADCAST_TOKEN_AUTH=true
ASTERISK_BROADCAST_ALLOWED_TOKENS=secure_token_1,secure_token_2
```

**Best Practices:**
- Always enable authentication for event broadcasting
- Use specific permissions rather than broad access
- Implement token rotation for API access
- Monitor authentication attempts and failures

### 2. API Access Control

**Laravel Policies:**
```php
// app/Policies/AsteriskPolicy.php
class AsteriskPolicy
{
    public function originateCall(User $user)
    {
        return $user->hasPermission('asterisk.originate');
    }
    
    public function viewAuditLogs(User $user)
    {
        return $user->hasRole('asterisk.audit.viewer');
    }
}
```

**Route Protection:**
```php
// routes/web.php
Route::middleware(['auth', 'can:use-asterisk'])->group(function () {
    Route::post('/asterisk/originate', [AsteriskController::class, 'originate'])
         ->middleware('can:asterisk.originate');
});
```

## Input Validation and Sanitization

### 1. AMI Input Sanitization

The package includes built-in input sanitization for all AMI commands. Ensure it's properly configured:

```php
// Configuration is handled automatically
// Review AmiInputSanitizer class for customization
```

**Validation Rules:**
- Channel names: alphanumeric, slash, dash, underscore only
- Extensions: numeric, plus, asterisk, hash only
- Context names: alphanumeric, dash, underscore only
- Variables: escaped special characters

### 2. Custom Validation

**Implement Additional Validation:**
```php
// app/Http/Requests/OriginateCallRequest.php
class OriginateCallRequest extends FormRequest
{
    public function rules()
    {
        return [
            'channel' => 'required|string|regex:/^[A-Za-z0-9\/_-]+$/',
            'extension' => 'required|string|regex:/^[0-9+*#]+$/',
            'context' => 'required|string|regex:/^[A-Za-z0-9_-]+$/',
        ];
    }
}
```

## Audit Logging Configuration

### 1. Enable Comprehensive Audit Logging

**Environment Configuration:**
```env
ASTERISK_AUDIT_ENABLED=true
ASTERISK_AUDIT_LOG_TO_DATABASE=true
ASTERISK_AUDIT_LOG_TO_FILE=true
ASTERISK_AUDIT_LOG_SUCCESS=true
ASTERISK_AUDIT_LOG_FAILURES=true
ASTERISK_AUDIT_LOG_CONNECTIONS=true
```

### 2. Data Retention and Cleanup

**Configuration:**
```env
ASTERISK_AUDIT_RETENTION_DAYS=365
ASTERISK_AUDIT_AUTO_CLEANUP=true
```

**Automated Cleanup:**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        AuditLog::cleanup(config('asterisk-pbx-manager.audit.retention.days', 90));
    })->daily();
}
```

### 3. Sensitive Data Protection

**Configuration:**
```php
// config/asterisk-pbx-manager.php
'audit' => [
    'sanitization' => [
        'sensitive_keys' => [
            'secret', 'password', 'authsecret', 'md5secret', 'token',
            'apikey', 'auth_token', 'session_id'
        ],
        'redaction_text' => '[REDACTED]',
    ],
],
```

## Network Security

### 1. Firewall Configuration

**Asterisk Server:**
```bash
# Allow AMI access only from application servers
iptables -A INPUT -p tcp --dport 5038 -s 10.0.1.0/24 -j ACCEPT
iptables -A INPUT -p tcp --dport 5038 -j DROP
```

**Application Server:**
```bash
# Restrict outbound AMI connections
iptables -A OUTPUT -p tcp --dport 5038 -d 10.0.1.100 -j ACCEPT
iptables -A OUTPUT -p tcp --dport 5038 -j DROP
```

### 2. VPN and Network Segmentation

**Recommendations:**
- Place Asterisk servers in isolated network segments
- Use VPN for remote access to AMI interfaces
- Implement network monitoring and intrusion detection
- Regular network security assessments

### 3. SSL/TLS Configuration

**For Web Interface:**
```nginx
server {
    listen 443 ssl;
    ssl_certificate /path/to/certificate.pem;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE+AESGCM:ECDHE+CHACHA20:DHE+AESGCM:DHE+CHACHA20:!aNULL:!MD5:!DSS;
    ssl_prefer_server_ciphers off;
}
```

## Error Handling and Information Disclosure

### 1. Secure Error Handling

**Production Configuration:**
```env
APP_DEBUG=false
LOG_LEVEL=error
```

**Custom Error Pages:**
```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($exception instanceof AsteriskConnectionException) {
        return response()->json([
            'error' => 'Service temporarily unavailable'
        ], 503);
    }
    
    return parent::render($request, $exception);
}
```

### 2. Information Disclosure Prevention

**Logging Configuration:**
```php
// config/logging.php
'channels' => [
    'asterisk' => [
        'driver' => 'daily',
        'path' => storage_path('logs/asterisk.log'),
        'level' => 'info',
        'days' => 30,
        'permission' => 0600, // Restrict file permissions
    ],
],
```

## Configuration Hardening

### 1. Environment Security

**File Permissions:**
```bash
chmod 600 .env
chmod 755 storage/logs
chmod 755 storage/app
chown -R www-data:www-data storage/
```

**Environment Variables:**
```env
# Session Security
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# CSRF Protection
CSRF_COOKIE_SECURE=true
CSRF_COOKIE_HTTP_ONLY=true

# Database Security
DB_HOST=127.0.0.1  # Use specific IP, not localhost
DB_PORT=3306       # Consider non-standard ports
```

### 2. Laravel Security Configuration

**Configuration Hardening:**
```php
// config/app.php
'debug' => env('APP_DEBUG', false),
'url' => env('APP_URL', 'https://your-domain.com'),

// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', true),
'http_only' => env('SESSION_HTTP_ONLY', true),
'same_site' => env('SESSION_SAME_SITE', 'strict'),
```

## Monitoring and Alerting

### 1. Security Monitoring

**Key Metrics to Monitor:**
- Failed AMI connection attempts
- Unusual call origination patterns
- Authentication failures
- Permission violations
- Configuration changes

**Monitoring Implementation:**
```php
// app/Listeners/SecurityEventListener.php
class SecurityEventListener
{
    public function handle($event)
    {
        if ($this->isSecurityEvent($event)) {
            $this->sendAlert($event);
            $this->logSecurityEvent($event);
        }
    }
}
```

### 2. Audit Log Analysis

**Regular Reviews:**
- Daily review of failed authentication attempts
- Weekly analysis of unusual access patterns
- Monthly audit of user permissions
- Quarterly security assessment

**Automated Alerts:**
```php
// Create alerts for suspicious activities
if ($failedAttempts > 10) {
    Mail::to('security@company.com')->send(new SecurityAlert($event));
}
```

## Deployment Security

### 1. Production Deployment

**Server Hardening:**
```bash
# Update system packages
apt update && apt upgrade -y

# Install security updates automatically
apt install unattended-upgrades
dpkg-reconfigure unattended-upgrades

# Configure firewall
ufw enable
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
```

### 2. Application Security

**Composer Security:**
```bash
# Check for security vulnerabilities
composer audit

# Keep dependencies updated
composer update --with-dependencies
```

**Laravel Security:**
```bash
# Clear caches in production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
chmod -R 755 bootstrap/cache
chmod -R 755 storage
```

## Security Checklists

### Pre-Deployment Checklist

- [ ] AMI credentials configured with strong passwords
- [ ] AMI user permissions restricted to minimum required
- [ ] Authentication enabled for all public interfaces
- [ ] Input validation implemented and tested
- [ ] Audit logging enabled and configured
- [ ] Error handling configured for production
- [ ] Network security implemented (firewall, VPN)
- [ ] SSL/TLS certificates installed and configured
- [ ] File permissions set correctly
- [ ] Security monitoring and alerting configured
- [ ] Backup and disaster recovery procedures tested

### Post-Deployment Checklist

- [ ] All security configurations verified
- [ ] Monitoring systems operational
- [ ] Audit logs being generated and stored
- [ ] Security alerts functional
- [ ] Performance impact of security measures assessed
- [ ] Documentation updated with environment-specific details
- [ ] Security incident response procedures in place
- [ ] Regular security review schedule established

### Regular Maintenance Checklist

#### Daily
- [ ] Review security alerts and audit logs
- [ ] Monitor system performance and resource usage
- [ ] Check for failed authentication attempts

#### Weekly  
- [ ] Review audit log patterns and anomalies
- [ ] Verify backup and logging systems
- [ ] Update security threat intelligence

#### Monthly
- [ ] Review and rotate API tokens
- [ ] Audit user permissions and access
- [ ] Update security documentation
- [ ] Test incident response procedures

#### Quarterly
- [ ] Rotate AMI credentials
- [ ] Comprehensive security assessment
- [ ] Update security policies and procedures
- [ ] Review and update monitoring thresholds

## Incident Response

### 1. Security Incident Classification

**Severity Levels:**
- **Critical:** Unauthorized AMI access, data breach
- **High:** Authentication bypass, privilege escalation
- **Medium:** Brute force attempts, unusual access patterns
- **Low:** Minor configuration issues, warning alerts

### 2. Response Procedures

**Immediate Actions:**
1. Isolate affected systems
2. Preserve audit logs and evidence
3. Notify security team and stakeholders
4. Implement containment measures
5. Begin forensic analysis

**Recovery Steps:**
1. Patch vulnerabilities
2. Rotate compromised credentials
3. Update security configurations
4. Restore from clean backups if necessary
5. Conduct post-incident review

## Compliance Considerations

### 1. Data Protection

**GDPR/Privacy:**
- Audit logs may contain personal data
- Implement data retention policies
- Provide data subject access rights
- Ensure lawful basis for processing

### 2. Industry Standards

**Telecommunications Security:**
- Follow NIST Cybersecurity Framework
- Implement ISO 27001 controls where applicable
- Consider industry-specific requirements
- Regular security assessments and audits

## Conclusion

Security is an ongoing process that requires continuous attention and improvement. These guidelines provide a foundation for secure deployment and operation of the Asterisk PBX Manager package. Regular review and updates of security measures are essential to maintain protection against evolving threats.

For additional security support or to report security vulnerabilities, please contact the security team at security@asterisk-pbx-manager.com.

---

**Document Revision History:**
- v1.0 - Initial security guidelines (August 28, 2025)

**Next Review Date:** November 28, 2025