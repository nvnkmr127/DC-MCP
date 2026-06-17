<?php

use Illuminate\Support\Facades\Route;
use App\Modules\MCP\Http\Controllers\Api\McpConnectionApiController;

Route::get('mcp/providers', [McpConnectionApiController::class, 'providers']);
Route::post('mcp/connections/{mcpConnection}/sync', [McpConnectionApiController::class, 'sync']);
Route::post('mcp/connections/{mcpConnection}/test', [McpConnectionApiController::class, 'test']);
Route::apiResource('mcp/connections', McpConnectionApiController::class)
    ->parameters(['connections' => 'mcpConnection']);
