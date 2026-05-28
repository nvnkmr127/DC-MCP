<?php

use Illuminate\Support\Facades\Route;

// Public webhook endpoints — no auth required, validated by provider signature
Route::post('webhooks/mcp/{provider}/{connectionId}', 'McpConnectionController@webhook');
