<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Asterisk Manager Interface Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection settings for the Asterisk Manager
    | Interface (AMI). These settings are used to establish a connection to
    | your Asterisk PBX system using the PAMI library.
    |
    */
    'connection' => [
        'host' => env('ASTERISK_AMI_HOST', '127.0.0.1'),
        'port' => env('ASTERISK_AMI_PORT', 5038),
        'username' => env('ASTERISK_AMI_USERNAME', 'admin'),
        'secret' => env('ASTERISK_AMI_SECRET', 'your_ami_secret'),
        'connect_timeout' => env('ASTERISK_AMI_CONNECT_TIMEOUT', 10),
        'read_timeout' => env('ASTERISK_AMI_READ_TIMEOUT', 10),
        'scheme' => env('ASTERISK_AMI_SCHEME', 'tcp://'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how Asterisk events are handled by the package. You can
    | enable or disable event processing, broadcasting, and database logging.
    |
    */
    'events' => [
        'enabled' => env('ASTERISK_EVENTS_ENABLED', true),
        'broadcast' => env('ASTERISK_EVENTS_BROADCAST', true),
        'log_to_database' => env('ASTERISK_LOG_TO_DATABASE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconnection Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic reconnection behavior when the AMI connection is lost.
    |
    */
    'reconnection' => [
        'enabled' => env('ASTERISK_RECONNECTION_ENABLED', true),
        'max_attempts' => env('ASTERISK_RECONNECTION_MAX_ATTEMPTS', 3),
        'delay_seconds' => env('ASTERISK_RECONNECTION_DELAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for AMI operations and events.
    |
    */
    'logging' => [
        'enabled' => env('ASTERISK_LOGGING_ENABLED', true),
        'level' => env('ASTERISK_LOGGING_LEVEL', 'info'),
        'channel' => env('ASTERISK_LOGGING_CHANNEL', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for queue operations.
    |
    */
    'queues' => [
        'default_context' => env('ASTERISK_DEFAULT_CONTEXT', 'default'),
        'default_priority' => env('ASTERISK_DEFAULT_PRIORITY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure event broadcasting channels and settings.
    |
    */
    'broadcasting' => [
        'channel_prefix' => env('ASTERISK_BROADCAST_CHANNEL_PREFIX', 'asterisk'),
        'private_channels' => env('ASTERISK_BROADCAST_PRIVATE', false),
        
        /*
        |--------------------------------------------------------------------------
        | Authentication Configuration
        |--------------------------------------------------------------------------
        |
        | Configure authentication for event broadcasting. When enabled,
        | broadcasted events will require proper authentication.
        |
        */
        'authentication' => [
            'enabled' => env('ASTERISK_BROADCAST_AUTH_ENABLED', false),
            'guard' => env('ASTERISK_BROADCAST_AUTH_GUARD', 'web'),
            'middleware' => env('ASTERISK_BROADCAST_AUTH_MIDDLEWARE', 'auth'),
            'required_permissions' => env('ASTERISK_BROADCAST_PERMISSIONS', 'asterisk.events.listen'),
            'token_based' => env('ASTERISK_BROADCAST_TOKEN_AUTH', false),
            'allowed_tokens' => explode(',', env('ASTERISK_BROADCAST_ALLOWED_TOKENS', '')),
            'user_model' => env('ASTERISK_BROADCAST_USER_MODEL', 'App\\Models\\User'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure comprehensive audit logging for all AMI actions. This provides
    | detailed audit trails for security, compliance, and debugging purposes.
    |
    */
    'audit' => [
        'enabled' => env('ASTERISK_AUDIT_ENABLED', true),
        'log_channel' => env('ASTERISK_AUDIT_LOG_CHANNEL', 'default'),
        'database_logging' => env('ASTERISK_AUDIT_DATABASE_LOGGING', true),
        'external_audit_url' => env('ASTERISK_AUDIT_EXTERNAL_URL', null),
        
        /*
        |--------------------------------------------------------------------------
        | Audit Data Retention
        |--------------------------------------------------------------------------
        |
        | Configure how long audit logs are retained in the database.
        |
        */
        'retention' => [
            'days' => env('ASTERISK_AUDIT_RETENTION_DAYS', 90),
            'auto_cleanup' => env('ASTERISK_AUDIT_AUTO_CLEANUP', true),
        ],
        
        /*
        |--------------------------------------------------------------------------
        | Audit Filtering
        |--------------------------------------------------------------------------
        |
        | Configure which types of actions should be audited.
        |
        */
        'filters' => [
            'log_successful_actions' => env('ASTERISK_AUDIT_LOG_SUCCESS', true),
            'log_failed_actions' => env('ASTERISK_AUDIT_LOG_FAILURES', true),
            'log_security_events' => env('ASTERISK_AUDIT_LOG_SECURITY', true),
            'excluded_actions' => explode(',', env('ASTERISK_AUDIT_EXCLUDED_ACTIONS', 'ping')),
            'sensitive_actions' => explode(',', env('ASTERISK_AUDIT_SENSITIVE_ACTIONS', 'login,command')),
        ],
        
        /*
        |--------------------------------------------------------------------------
        | Performance Settings
        |--------------------------------------------------------------------------
        |
        | Configure audit logging performance settings.
        |
        */
        'performance' => [
            'async_logging' => env('ASTERISK_AUDIT_ASYNC', true),
            'batch_size' => env('ASTERISK_AUDIT_BATCH_SIZE', 100),
            'queue_connection' => env('ASTERISK_AUDIT_QUEUE_CONNECTION', 'default'),
        ],
    ],
];