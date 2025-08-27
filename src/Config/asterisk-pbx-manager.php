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
    ],
];