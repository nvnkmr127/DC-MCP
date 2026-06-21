<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME', env('APP_NAME', 'DC MCP')),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    // Horizon requires Redis. Switch QUEUE_CONNECTION=redis in production.
    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'
    ),

    // Horizon UI is behind web+auth middleware; add 'role:ceo' as needed.
    'middleware' => ['web', 'auth'],

    'waits' => [
        'redis:default' => 60,
    ],

    'trim' => [
        'recent'        => 60,     // 1 hour
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,  // 7 days
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    'silenced' => [],

    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    'defaults' => [
        'supervisor-default' => [
            // Horizon requires Redis. Set HORIZON_CONNECTION=redis and QUEUE_CONNECTION=redis in production.
            'connection'          => env('HORIZON_CONNECTION', 'redis'),
            'queue'               => ['high', 'default'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 2,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            // Memory per worker — raise if jobs OOM
            'memory'              => 256,
            // Align with longest job timeout
            'timeout'             => 60,
            'nice'                => 0,
        ],
        'supervisor-ai' => [
            'connection'          => env('HORIZON_CONNECTION', 'redis'),
            'queue'               => ['ai-tasks'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 3,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 512,
            'timeout'             => 210,
            'nice'                => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses'      => 10,
                'balanceMaxShift'   => 2,
                'balanceCooldown'   => 3,
            ],
            'supervisor-ai' => [
                'maxProcesses'      => 5,
                'balanceMaxShift'   => 1,
                'balanceCooldown'   => 3,
            ],
        ],

        'local' => [
            'supervisor-default' => [
                'maxProcesses' => 2,
            ],
            'supervisor-ai' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'routes',
        'composer.lock',
        '.env',
    ],
];
