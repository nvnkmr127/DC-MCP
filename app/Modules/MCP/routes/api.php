<?php

use Illuminate\Support\Facades\Route;

Route::get('mcp/providers', 'McpConnectionController@providers');
Route::post('mcp/connections/{mcpConnection}/sync', 'McpConnectionController@sync');
Route::post('mcp/connections/{mcpConnection}/test', 'McpConnectionController@test');
Route::apiResource('mcp/connections', 'McpConnectionController')
    ->parameters(['connections' => 'mcpConnection']);
