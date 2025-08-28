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
        'host'            => env('ASTERISK_AMI_HOST', '127.0.0.1'),
        'port'            => env('ASTERISK_AMI_PORT', 5038),
        'username'        => env('ASTERISK_AMI_USERNAME', 'admin'),
        'secret'          => env('ASTERISK_AMI_SECRET', 'your_ami_secret'),
        'connect_timeout' => env('ASTERISK_AMI_CONNECT_TIMEOUT', 10),
        'read_timeout'    => env('ASTERISK_AMI_READ_TIMEOUT', 10),
        'scheme'          => env('ASTERISK_AMI_SCHEME', 'tcp://'),
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
        'enabled'         => env('ASTERISK_EVENTS_ENABLED', true),
        'broadcast'       => env('ASTERISK_EVENTS_BROADCAST', true),
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
        'enabled'       => env('ASTERISK_RECONNECTION_ENABLED', true),
        'max_attempts'  => env('ASTERISK_RECONNECTION_MAX_ATTEMPTS', 3),
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
        'level'   => env('ASTERISK_LOGGING_LEVEL', 'info'),
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
        'default_context'  => env('ASTERISK_DEFAULT_CONTEXT', 'default'),
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
        'channel_prefix'   => env('ASTERISK_BROADCAST_CHANNEL_PREFIX', 'asterisk'),
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
            'enabled'              => env('ASTERISK_BROADCAST_AUTH_ENABLED', false),
            'guard'                => env('ASTERISK_BROADCAST_AUTH_GUARD', 'web'),
            'middleware'           => env('ASTERISK_BROADCAST_AUTH_MIDDLEWARE', 'auth'),
            'required_permissions' => env('ASTERISK_BROADCAST_PERMISSIONS', 'asterisk.events.listen'),
            'token_based'          => env('ASTERISK_BROADCAST_TOKEN_AUTH', false),
            'allowed_tokens'       => explode(',', env('ASTERISK_BROADCAST_ALLOWED_TOKENS', '')),
            'user_model'           => env('ASTERISK_BROADCAST_USER_MODEL', 'App\\Models\\User'),
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
        'enabled'         => env('ASTERISK_AUDIT_ENABLED', true),
        'log_to_database' => env('ASTERISK_AUDIT_LOG_TO_DATABASE', true),
        'log_to_file'     => env('ASTERISK_AUDIT_LOG_TO_FILE', true),
        'log_channel'     => env('ASTERISK_AUDIT_LOG_CHANNEL', 'default'),

        /*
        |--------------------------------------------------------------------------
        | Audit Data Retention
        |--------------------------------------------------------------------------
        |
        | Configure how long audit logs are retained in the database.
        |
        */
        'retention' => [
            'days'         => env('ASTERISK_AUDIT_RETENTION_DAYS', 90),
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
            'log_failed_actions'     => env('ASTERISK_AUDIT_LOG_FAILURES', true),
            'log_connection_events'  => env('ASTERISK_AUDIT_LOG_CONNECTIONS', true),
            'excluded_actions'       => explode(',', env('ASTERISK_AUDIT_EXCLUDED_ACTIONS', '')),
            'sensitive_actions'      => explode(',', env('ASTERISK_AUDIT_SENSITIVE_ACTIONS', 'login,command')),
        ],

        /*
        |--------------------------------------------------------------------------
        | Data Sanitization
        |--------------------------------------------------------------------------
        |
        | Configure which data should be sanitized in audit logs.
        |
        */
        'sanitization' => [
            'sensitive_keys'    => ['secret', 'password', 'authsecret', 'md5secret', 'token'],
            'redaction_text'    => '[REDACTED]',
            'log_request_data'  => env('ASTERISK_AUDIT_LOG_REQUEST_DATA', true),
            'log_response_data' => env('ASTERISK_AUDIT_LOG_RESPONSE_DATA', true),
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
            'async_logging'    => env('ASTERISK_AUDIT_ASYNC', false),
            'batch_size'       => env('ASTERISK_AUDIT_BATCH_SIZE', 100),
            'queue_connection' => env('ASTERISK_AUDIT_QUEUE_CONNECTION', 'default'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Configure metrics collection and reporting for Asterisk operations.
    | Metrics help monitor performance, reliability, and usage patterns.
    |
    */
    'metrics' => [
        /*
        |--------------------------------------------------------------------------
        | Metrics Collection
        |--------------------------------------------------------------------------
        |
        | Enable or disable metrics collection and configure collection behavior.
        |
        */
        'enabled'           => env('ASTERISK_METRICS_ENABLED', true),
        'track_performance' => env('ASTERISK_METRICS_TRACK_PERFORMANCE', true),
        'track_errors'      => env('ASTERISK_METRICS_TRACK_ERRORS', true),
        'track_connections' => env('ASTERISK_METRICS_TRACK_CONNECTIONS', true),
        'track_actions'     => env('ASTERISK_METRICS_TRACK_ACTIONS', true),
        'track_events'      => env('ASTERISK_METRICS_TRACK_EVENTS', true),

        /*
        |--------------------------------------------------------------------------
        | Metrics Storage
        |--------------------------------------------------------------------------
        |
        | Configure how metrics are stored and cached.
        |
        */
        'storage' => [
            'cache_driver'     => env('ASTERISK_METRICS_CACHE_DRIVER', 'default'),
            'retention_hours'  => env('ASTERISK_METRICS_RETENTION_HOURS', 24),
            'cleanup_interval' => env('ASTERISK_METRICS_CLEANUP_INTERVAL', 60), // minutes
            'batch_size'       => env('ASTERISK_METRICS_BATCH_SIZE', 1000),
        ],

        /*
        |--------------------------------------------------------------------------
        | Performance Settings
        |--------------------------------------------------------------------------
        |
        | Configure performance-related settings for metrics collection.
        |
        */
        'performance' => [
            'async_collection'   => env('ASTERISK_METRICS_ASYNC', false),
            'sampling_rate'      => env('ASTERISK_METRICS_SAMPLING_RATE', 1.0), // 0.0 to 1.0
            'aggregation_window' => env('ASTERISK_METRICS_AGGREGATION_WINDOW', 300), // seconds
            'max_memory_usage'   => env('ASTERISK_METRICS_MAX_MEMORY_MB', 128),
        ],

        /*
        |--------------------------------------------------------------------------
        | Reporting Configuration
        |--------------------------------------------------------------------------
        |
        | Configure how metrics are reported and exported.
        |
        */
        'reporting' => [
            'enabled'         => env('ASTERISK_METRICS_REPORTING_ENABLED', true),
            'export_formats'  => explode(',', env('ASTERISK_METRICS_EXPORT_FORMATS', 'json,prometheus')),
            'auto_reports'    => env('ASTERISK_METRICS_AUTO_REPORTS', false),
            'report_schedule' => env('ASTERISK_METRICS_REPORT_SCHEDULE', 'hourly'),
            'export_path'     => env('ASTERISK_METRICS_EXPORT_PATH', storage_path('app/metrics')),
        ],

        /*
        |--------------------------------------------------------------------------
        | Alerting Configuration
        |--------------------------------------------------------------------------
        |
        | Configure alerting thresholds for metrics.
        |
        */
        'alerting' => [
            'enabled'                      => env('ASTERISK_METRICS_ALERTING_ENABLED', false),
            'error_rate_threshold'         => env('ASTERISK_METRICS_ERROR_RATE_THRESHOLD', 5.0), // percentage
            'response_time_threshold'      => env('ASTERISK_METRICS_RESPONSE_TIME_THRESHOLD', 1000), // milliseconds
            'connection_failure_threshold' => env('ASTERISK_METRICS_CONNECTION_FAILURE_THRESHOLD', 3),
            'notification_channels'        => explode(',', env('ASTERISK_METRICS_NOTIFICATION_CHANNELS', 'log')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Graceful Shutdown Configuration
    |--------------------------------------------------------------------------
    |
    | Configure graceful shutdown behavior for AMI connections and cleanup.
    | This ensures proper cleanup of resources when the application terminates.
    |
    */
    'shutdown' => [
        /*
        |--------------------------------------------------------------------------
        | Shutdown Timeout
        |--------------------------------------------------------------------------
        |
        | Maximum time (in seconds) to wait for graceful shutdown to complete.
        |
        */
        'timeout'            => env('ASTERISK_SHUTDOWN_TIMEOUT', 30),
        'connection_timeout' => env('ASTERISK_SHUTDOWN_CONNECTION_TIMEOUT', 10),

        /*
        |--------------------------------------------------------------------------
        | Signal Handling
        |--------------------------------------------------------------------------
        |
        | Configure system signal handling for graceful shutdown.
        |
        */
        'signals' => [
            'enabled'        => env('ASTERISK_SHUTDOWN_SIGNALS_ENABLED', true),
            'handle_sigterm' => env('ASTERISK_SHUTDOWN_HANDLE_SIGTERM', true),
            'handle_sigint'  => env('ASTERISK_SHUTDOWN_HANDLE_SIGINT', true),
            'handle_sighup'  => env('ASTERISK_SHUTDOWN_HANDLE_SIGHUP', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Cleanup Configuration
        |--------------------------------------------------------------------------
        |
        | Configure what should be cleaned up during shutdown.
        |
        */
        'cleanup' => [
            'close_connections'      => env('ASTERISK_SHUTDOWN_CLOSE_CONNECTIONS', true),
            'flush_logs'             => env('ASTERISK_SHUTDOWN_FLUSH_LOGS', true),
            'clear_cache'            => env('ASTERISK_SHUTDOWN_CLEAR_CACHE', false),
            'run_garbage_collection' => env('ASTERISK_SHUTDOWN_RUN_GC', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Logging Configuration
        |--------------------------------------------------------------------------
        |
        | Configure shutdown logging behavior.
        |
        */
        'logging' => [
            'log_shutdown_start'     => env('ASTERISK_SHUTDOWN_LOG_START', true),
            'log_shutdown_complete'  => env('ASTERISK_SHUTDOWN_LOG_COMPLETE', true),
            'log_connection_cleanup' => env('ASTERISK_SHUTDOWN_LOG_CONNECTIONS', true),
            'log_callback_execution' => env('ASTERISK_SHUTDOWN_LOG_CALLBACKS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configure circuit breaker pattern for reliability and failure protection.
    | Circuit breakers prevent cascading failures and provide automatic recovery.
    |
    */
    'circuit_breaker' => [
        /*
        |--------------------------------------------------------------------------
        | Circuit Breaker Enable/Disable
        |--------------------------------------------------------------------------
        |
        | Enable or disable circuit breaker functionality globally.
        |
        */
        'enabled' => env('ASTERISK_CIRCUIT_BREAKER_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Failure Threshold
        |--------------------------------------------------------------------------
        |
        | Number of consecutive failures before opening the circuit.
        |
        */
        'failure_threshold' => env('ASTERISK_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),

        /*
        |--------------------------------------------------------------------------
        | Recovery Timeout
        |--------------------------------------------------------------------------
        |
        | Time (in seconds) to wait before attempting recovery from open state.
        |
        */
        'recovery_timeout' => env('ASTERISK_CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 60),

        /*
        |--------------------------------------------------------------------------
        | Success Threshold
        |--------------------------------------------------------------------------
        |
        | Number of consecutive successes required to close a half-open circuit.
        |
        */
        'success_threshold' => env('ASTERISK_CIRCUIT_BREAKER_SUCCESS_THRESHOLD', 3),

        /*
        |--------------------------------------------------------------------------
        | Operation Timeout
        |--------------------------------------------------------------------------
        |
        | Maximum time (in seconds) to wait for an operation to complete.
        |
        */
        'timeout' => env('ASTERISK_CIRCUIT_BREAKER_TIMEOUT', 30),

        /*
        |--------------------------------------------------------------------------
        | Monitor Window
        |--------------------------------------------------------------------------
        |
        | Time window (in seconds) for monitoring and statistics collection.
        |
        */
        'monitor_window' => env('ASTERISK_CIRCUIT_BREAKER_MONITOR_WINDOW', 300),

        /*
        |--------------------------------------------------------------------------
        | Circuit Configuration
        |--------------------------------------------------------------------------
        |
        | Configure individual circuit behaviors.
        |
        */
        'circuits' => [
            'ami_connection' => [
                'failure_threshold' => env('ASTERISK_CB_AMI_CONNECTION_FAILURE_THRESHOLD', 3),
                'recovery_timeout'  => env('ASTERISK_CB_AMI_CONNECTION_RECOVERY_TIMEOUT', 30),
                'timeout'           => env('ASTERISK_CB_AMI_CONNECTION_TIMEOUT', 10),
            ],
            'ami_actions' => [
                'failure_threshold' => env('ASTERISK_CB_AMI_ACTIONS_FAILURE_THRESHOLD', 5),
                'recovery_timeout'  => env('ASTERISK_CB_AMI_ACTIONS_RECOVERY_TIMEOUT', 60),
                'timeout'           => env('ASTERISK_CB_AMI_ACTIONS_TIMEOUT', 30),
            ],
            'event_processing' => [
                'failure_threshold' => env('ASTERISK_CB_EVENT_PROCESSING_FAILURE_THRESHOLD', 10),
                'recovery_timeout'  => env('ASTERISK_CB_EVENT_PROCESSING_RECOVERY_TIMEOUT', 120),
                'timeout'           => env('ASTERISK_CB_EVENT_PROCESSING_TIMEOUT', 15),
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Fallback Configuration
        |--------------------------------------------------------------------------
        |
        | Configure fallback behaviors when circuits are open.
        |
        */
        'fallbacks' => [
            'ami_connection'   => env('ASTERISK_CB_AMI_CONNECTION_FALLBACK', 'cache'),
            'ami_actions'      => env('ASTERISK_CB_AMI_ACTIONS_FALLBACK', 'queue'),
            'event_processing' => env('ASTERISK_CB_EVENT_PROCESSING_FALLBACK', 'skip'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Monitoring and Alerting
        |--------------------------------------------------------------------------
        |
        | Configure monitoring and alerting for circuit breaker events.
        |
        */
        'monitoring' => [
            'log_state_changes'     => env('ASTERISK_CB_LOG_STATE_CHANGES', true),
            'log_failures'          => env('ASTERISK_CB_LOG_FAILURES', true),
            'log_recoveries'        => env('ASTERISK_CB_LOG_RECOVERIES', true),
            'alert_on_open'         => env('ASTERISK_CB_ALERT_ON_OPEN', true),
            'alert_on_recovery'     => env('ASTERISK_CB_ALERT_ON_RECOVERY', true),
            'notification_channels' => explode(',', env('ASTERISK_CB_NOTIFICATION_CHANNELS', 'log,email')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configure health check endpoints and monitoring settings. These settings
    | control how health checks are performed, cached, and reported.
    |
    */
    'health_check' => [
        /*
        |--------------------------------------------------------------------------
        | Health Check Endpoints
        |--------------------------------------------------------------------------
        |
        | Enable or disable health check endpoints and configure their behavior.
        |
        */
        'endpoints' => [
            'enabled'            => env('ASTERISK_HEALTH_ENDPOINTS_ENABLED', true),
            'detailed_endpoint'  => env('ASTERISK_HEALTH_DETAILED_ENABLED', true),
            'simple_endpoint'    => env('ASTERISK_HEALTH_SIMPLE_ENABLED', true),
            'status_endpoint'    => env('ASTERISK_HEALTH_STATUS_ENABLED', true),
            'liveness_endpoint'  => env('ASTERISK_HEALTH_LIVENESS_ENABLED', true),
            'readiness_endpoint' => env('ASTERISK_HEALTH_READINESS_ENABLED', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Caching Configuration
        |--------------------------------------------------------------------------
        |
        | Configure caching for health check results to improve performance
        | and reduce system load during frequent health checks.
        |
        */
        'cache' => [
            'enabled'          => env('ASTERISK_HEALTH_CACHE_ENABLED', true),
            'ttl'              => env('ASTERISK_HEALTH_CACHE_TTL', 30), // seconds
            'simple_ttl'       => env('ASTERISK_HEALTH_SIMPLE_CACHE_TTL', 10), // seconds
            'cache_key_prefix' => env('ASTERISK_HEALTH_CACHE_PREFIX', 'asterisk_health'),
            'cache_driver'     => env('ASTERISK_HEALTH_CACHE_DRIVER', null), // null uses default
        ],

        /*
        |--------------------------------------------------------------------------
        | Health Check Thresholds
        |--------------------------------------------------------------------------
        |
        | Configure thresholds for determining system health status.
        |
        */
        'thresholds' => [
            'connection_timeout'         => env('ASTERISK_HEALTH_CONNECTION_TIMEOUT', 5), // seconds
            'database_query_timeout'     => env('ASTERISK_HEALTH_DB_TIMEOUT', 2), // seconds
            'max_memory_usage_mb'        => env('ASTERISK_HEALTH_MAX_MEMORY_MB', 256),
            'max_execution_time_ms'      => env('ASTERISK_HEALTH_MAX_EXECUTION_MS', 1000),
            'event_age_warning_minutes'  => env('ASTERISK_HEALTH_EVENT_AGE_WARNING', 60),
            'min_recent_events_per_hour' => env('ASTERISK_HEALTH_MIN_EVENTS_PER_HOUR', 0),
        ],

        /*
        |--------------------------------------------------------------------------
        | Monitoring Configuration
        |--------------------------------------------------------------------------
        |
        | Configure which components to monitor and how to report issues.
        |
        */
        'monitoring' => [
            'check_ami_connection'        => env('ASTERISK_HEALTH_CHECK_AMI', true),
            'check_database'              => env('ASTERISK_HEALTH_CHECK_DATABASE', true),
            'check_configuration'         => env('ASTERISK_HEALTH_CHECK_CONFIG', true),
            'check_event_processing'      => env('ASTERISK_HEALTH_CHECK_EVENTS', true),
            'check_system_metrics'        => env('ASTERISK_HEALTH_CHECK_METRICS', true),
            'check_queues'                => env('ASTERISK_HEALTH_CHECK_QUEUES', true),
            'include_performance_metrics' => env('ASTERISK_HEALTH_INCLUDE_METRICS', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Critical Checks
        |--------------------------------------------------------------------------
        |
        | Define which health checks are considered critical for overall system health.
        | If any critical check fails, the overall health status will be unhealthy.
        |
        */
        'critical_checks' => [
            'ami_connection'   => env('ASTERISK_HEALTH_CRITICAL_AMI', true),
            'database'         => env('ASTERISK_HEALTH_CRITICAL_DATABASE', true),
            'configuration'    => env('ASTERISK_HEALTH_CRITICAL_CONFIG', true),
            'event_processing' => env('ASTERISK_HEALTH_CRITICAL_EVENTS', false),
            'system_metrics'   => env('ASTERISK_HEALTH_CRITICAL_METRICS', false),
            'queues'           => env('ASTERISK_HEALTH_CRITICAL_QUEUES', false),
        ],

        /*
        |--------------------------------------------------------------------------
        | Response Configuration
        |--------------------------------------------------------------------------
        |
        | Configure health check response format and content.
        |
        */
        'response' => [
            'include_version'        => env('ASTERISK_HEALTH_INCLUDE_VERSION', true),
            'include_timestamp'      => env('ASTERISK_HEALTH_INCLUDE_TIMESTAMP', true),
            'include_execution_time' => env('ASTERISK_HEALTH_INCLUDE_EXECUTION_TIME', true),
            'include_system_info'    => env('ASTERISK_HEALTH_INCLUDE_SYSTEM_INFO', true),
            'mask_sensitive_data'    => env('ASTERISK_HEALTH_MASK_SENSITIVE', true),
            'compact_mode'           => env('ASTERISK_HEALTH_COMPACT_MODE', false),
        ],

        /*
        |--------------------------------------------------------------------------
        | Security Configuration
        |--------------------------------------------------------------------------
        |
        | Configure security settings for health check endpoints.
        |
        */
        'security' => [
            'require_auth' => env('ASTERISK_HEALTH_REQUIRE_AUTH', false),
            'allowed_ips'  => explode(',', env('ASTERISK_HEALTH_ALLOWED_IPS', '')),
            'rate_limit'   => env('ASTERISK_HEALTH_RATE_LIMIT', 60), // requests per minute
            'hide_errors'  => env('ASTERISK_HEALTH_HIDE_ERRORS', false),
        ],

        /*
        |--------------------------------------------------------------------------
        | Integration Configuration
        |--------------------------------------------------------------------------
        |
        | Configure integration with external monitoring systems.
        |
        */
        'integration' => [
            'prometheus_metrics'   => env('ASTERISK_HEALTH_PROMETHEUS', false),
            'datadog_integration'  => env('ASTERISK_HEALTH_DATADOG', false),
            'newrelic_integration' => env('ASTERISK_HEALTH_NEWRELIC', false),
            'custom_webhook'       => env('ASTERISK_HEALTH_WEBHOOK_URL', null),
            'slack_notifications'  => env('ASTERISK_HEALTH_SLACK_ENABLED', false),
            'email_alerts'         => env('ASTERISK_HEALTH_EMAIL_ALERTS', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure connection pooling for high-load scenarios. Connection pooling
    | allows the package to maintain multiple AMI connections for better
    | performance and scalability under heavy load.
    |
    */
    'connection_pool' => [
        /*
        |--------------------------------------------------------------------------
        | Pool Enable/Disable
        |--------------------------------------------------------------------------
        |
        | Enable or disable connection pooling. When disabled, the package will
        | use direct connections as usual.
        |
        */
        'enabled' => env('ASTERISK_POOL_ENABLED', false),

        /*
        |--------------------------------------------------------------------------
        | Pool Size Configuration
        |--------------------------------------------------------------------------
        |
        | Configure the minimum and maximum number of connections in the pool.
        |
        */
        'min_connections' => env('ASTERISK_POOL_MIN_CONNECTIONS', 2),
        'max_connections' => env('ASTERISK_POOL_MAX_CONNECTIONS', 10),

        /*
        |--------------------------------------------------------------------------
        | Connection Timeouts
        |--------------------------------------------------------------------------
        |
        | Configure various timeout settings for connection pooling.
        |
        */
        'connection_timeout' => env('ASTERISK_POOL_CONNECTION_TIMEOUT', 10), // seconds
        'acquire_timeout'    => env('ASTERISK_POOL_ACQUIRE_TIMEOUT', 5), // seconds
        'idle_timeout'       => env('ASTERISK_POOL_IDLE_TIMEOUT', 300), // 5 minutes

        /*
        |--------------------------------------------------------------------------
        | Connection Lifecycle
        |--------------------------------------------------------------------------
        |
        | Configure connection lifecycle and recycling behavior.
        |
        */
        'max_connection_age'          => env('ASTERISK_POOL_MAX_CONNECTION_AGE', 3600), // 1 hour
        'max_idle_time'               => env('ASTERISK_POOL_MAX_IDLE_TIME', 300), // 5 minutes
        'max_requests_per_connection' => env('ASTERISK_POOL_MAX_REQUESTS_PER_CONNECTION', 1000),
        'enable_connection_recycling' => env('ASTERISK_POOL_ENABLE_RECYCLING', true),

        /*
        |--------------------------------------------------------------------------
        | Health Monitoring
        |--------------------------------------------------------------------------
        |
        | Configure health monitoring for pooled connections.
        |
        */
        'enable_health_monitoring'  => env('ASTERISK_POOL_ENABLE_HEALTH_MONITORING', true),
        'health_check_interval'     => env('ASTERISK_POOL_HEALTH_CHECK_INTERVAL', 60), // seconds
        'max_health_check_failures' => env('ASTERISK_POOL_MAX_HEALTH_CHECK_FAILURES', 3),

        /*
        |--------------------------------------------------------------------------
        | Pool Maintenance
        |--------------------------------------------------------------------------
        |
        | Configure automatic pool maintenance and cleanup.
        |
        */
        'cleanup_interval'         => env('ASTERISK_POOL_CLEANUP_INTERVAL', 300), // 5 minutes
        'enable_automatic_cleanup' => env('ASTERISK_POOL_ENABLE_AUTO_CLEANUP', true),
        'warmup_on_startup'        => env('ASTERISK_POOL_WARMUP_ON_STARTUP', true),

        /*
        |--------------------------------------------------------------------------
        | Pool Statistics and Monitoring
        |--------------------------------------------------------------------------
        |
        | Configure statistics collection and monitoring for the connection pool.
        |
        */
        'enable_statistics'        => env('ASTERISK_POOL_ENABLE_STATISTICS', true),
        'statistics_cache_ttl'     => env('ASTERISK_POOL_STATS_CACHE_TTL', 60), // seconds
        'log_pool_events'          => env('ASTERISK_POOL_LOG_EVENTS', true),
        'log_connection_lifecycle' => env('ASTERISK_POOL_LOG_CONNECTION_LIFECYCLE', false),

        /*
        |--------------------------------------------------------------------------
        | Load Balancing
        |--------------------------------------------------------------------------
        |
        | Configure load balancing behavior for connection selection.
        |
        */
        'load_balancing_strategy' => env('ASTERISK_POOL_LOAD_BALANCING', 'round_robin'), // round_robin, least_used, random
        'prefer_idle_connections' => env('ASTERISK_POOL_PREFER_IDLE', true),

        /*
        |--------------------------------------------------------------------------
        | Error Handling
        |--------------------------------------------------------------------------
        |
        | Configure error handling behavior for the connection pool.
        |
        */
        'retry_failed_connections'          => env('ASTERISK_POOL_RETRY_FAILED', true),
        'max_retry_attempts'                => env('ASTERISK_POOL_MAX_RETRY_ATTEMPTS', 3),
        'retry_delay'                       => env('ASTERISK_POOL_RETRY_DELAY', 1), // seconds
        'circuit_breaker_enabled'           => env('ASTERISK_POOL_CIRCUIT_BREAKER', false),
        'circuit_breaker_failure_threshold' => env('ASTERISK_POOL_CB_FAILURE_THRESHOLD', 10),
        'circuit_breaker_reset_timeout'     => env('ASTERISK_POOL_CB_RESET_TIMEOUT', 60), // seconds

        /*
        |--------------------------------------------------------------------------
        | Performance Tuning
        |--------------------------------------------------------------------------
        |
        | Configure performance-related settings for the connection pool.
        |
        */
        'connection_validation'          => env('ASTERISK_POOL_CONNECTION_VALIDATION', true),
        'validate_on_acquire'            => env('ASTERISK_POOL_VALIDATE_ON_ACQUIRE', true),
        'validate_on_release'            => env('ASTERISK_POOL_VALIDATE_ON_RELEASE', false),
        'eviction_policy'                => env('ASTERISK_POOL_EVICTION_POLICY', 'lru'), // lru, fifo, random
        'preemptive_connection_creation' => env('ASTERISK_POOL_PREEMPTIVE_CREATION', false),

        /*
        |--------------------------------------------------------------------------
        | Debug and Development
        |--------------------------------------------------------------------------
        |
        | Configure debug and development features for the connection pool.
        |
        */
        'debug_mode'             => env('ASTERISK_POOL_DEBUG', false),
        'detailed_logging'       => env('ASTERISK_POOL_DETAILED_LOGGING', false),
        'track_connection_usage' => env('ASTERISK_POOL_TRACK_USAGE', true),
        'export_metrics'         => env('ASTERISK_POOL_EXPORT_METRICS', false),
        'metrics_endpoint'       => env('ASTERISK_POOL_METRICS_ENDPOINT', '/metrics/pool'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Version
    |--------------------------------------------------------------------------
    |
    | The current version of the Asterisk PBX Manager package.
    |
    */
    'version' => env('ASTERISK_PBX_MANAGER_VERSION', '1.0.0'),
];
