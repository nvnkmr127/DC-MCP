<?php

return [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'api_url' => env('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages'),
        // claude-sonnet-4-6 is the current production model. Override via ANTHROPIC_MODEL.
        // BriefingGenerator will throw if this is empty — no silent fallback.
        'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],
];
