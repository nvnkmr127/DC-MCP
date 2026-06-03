<?php

return [
    // Dashboard widget query cache TTL in seconds.
    // Set VIZ_CACHE_TTL=0 to disable caching (useful in development).
    'cache_ttl' => (int) env('VIZ_CACHE_TTL', 300),
];
