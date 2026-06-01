<?php

use Illuminate\Support\Facades\Route;

Route::get('mcp/providers', 'McpConnectionApiController@providers');
Route::post('mcp/connections/{mcpConnection}/sync', 'McpConnectionApiController@sync');
Route::post('mcp/connections/{mcpConnection}/test', 'McpConnectionApiController@test');
Route::apiResource('mcp/connections', 'McpConnectionApiController')
    ->parameters(['connections' => 'mcpConnection']);
