<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | LOG_LEVEL should be "warning" in production and "debug" locally.
    | Set LOG_LEVEL=debug only for local development — it emits every query,
    | event dispatch, and framework internal into laravel.log.
    |
    */

    'channels' => [

        'stack' => [
            'driver'            => 'stack',
            // In production set LOG_STACK=daily,slack to route errors to Slack.
            // Locally keep LOG_STACK=daily to avoid Slack noise.
            'channels'          => explode(',', (string) env('LOG_STACK', 'daily')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver'               => 'single',
            'path'                 => storage_path('logs/laravel.log'),
            'level'                => env('LOG_LEVEL', 'warning'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver'               => 'daily',
            'path'                 => storage_path('logs/laravel.log'),
            'level'                => env('LOG_LEVEL', 'warning'),
            'days'                 => env('LOG_DAILY_DAYS', 30),
            'replace_placeholders' => true,
        ],

        // Dedicated channel for security and financial audit events.
        // Kept separate so it can be shipped to a SIEM without mixing with app noise.
        'audit' => [
            'driver'               => 'daily',
            'path'                 => storage_path('logs/audit.log'),
            'level'                => 'info',
            'days'                 => env('LOG_AUDIT_DAYS', 90),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver'               => 'slack',
            'url'                  => env('LOG_SLACK_WEBHOOK_URL'),
            'username'             => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji'                => env('LOG_SLACK_EMOJI', ':boom:'),
            'level'                => env('LOG_SLACK_LEVEL', 'error'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver'       => 'monolog',
            'level'        => env('LOG_LEVEL', 'warning'),
            'handler'      => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host'             => env('PAPERTRAIL_URL'),
                'port'             => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://' . env('PAPERTRAIL_URL') . ':' . env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver'       => 'monolog',
            'level'        => env('LOG_LEVEL', 'warning'),
            'handler'      => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter'  => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver'               => 'syslog',
            'level'                => env('LOG_LEVEL', 'warning'),
            'facility'             => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver'               => 'errorlog',
            'level'                => env('LOG_LEVEL', 'warning'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
