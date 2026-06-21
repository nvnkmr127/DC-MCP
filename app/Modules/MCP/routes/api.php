<?php

use Illuminate\Support\Facades\Route;
use App\Modules\MCP\Http\Controllers\Api\V1\McpConnectionApiController;

Route::prefix('v1')->group(function () {
    Route::get('mcp/providers/catalogue', [McpConnectionApiController::class, 'getCatalogue']);
    Route::get('mcp/providers', [McpConnectionApiController::class, 'providers']);
    Route::get('mcp/providers/{provider}/oauth-url', [McpConnectionApiController::class, 'getOAuthUrl']);
    Route::post('mcp/providers/{provider}/oauth-exchange', [McpConnectionApiController::class, 'oauthExchange']);
    Route::get('mcp/providers/{provider}/status', [McpConnectionApiController::class, 'getProviderStatus']);
    Route::get('mcp/providers/{provider}/diagnostics', [McpConnectionApiController::class, 'getDiagnostics']);
    Route::post('mcp/connections/{mcpConnection}/sync', [McpConnectionApiController::class, 'sync']);
    Route::get('mcp/connections/{mcpConnection}/sync-preview', [McpConnectionApiController::class, 'syncPreview']);
    Route::post('mcp/connections/{mcpConnection}/mapping-preview', [McpConnectionApiController::class, 'mappingPreview']);
    Route::post('mcp/connections/{mcpConnection}/outbound-preview', [McpConnectionApiController::class, 'previewOutboundAction']);
    Route::post('mcp/connections/{mcpConnection}/test', [McpConnectionApiController::class, 'test']);
    Route::post('mcp/connections/{mcpConnection}/test-scopes', [McpConnectionApiController::class, 'testScopes']);
    Route::get('mcp/connections/{mcpConnection}/rate-limits', [McpConnectionApiController::class, 'getRateLimits']);
    Route::post('mcp/connections/{mcpConnection}/clone', [McpConnectionApiController::class, 'clone']);
    Route::get('mcp/connections/export', [McpConnectionApiController::class, 'export']);
    Route::post('mcp/connections/import', [McpConnectionApiController::class, 'import']);
    
    Route::apiResource('mcp/connections', McpConnectionApiController::class)
        ->parameters(['connections' => 'mcpConnection']);
    
    Route::prefix('mcp/connections')->group(function () {
        Route::post('/{mcpConnection}/pause', [McpConnectionApiController::class, 'pause']);
        Route::post('/{mcpConnection}/resume', [McpConnectionApiController::class, 'resume']);
        Route::get('/{mcpConnection}/trending', [McpConnectionApiController::class, 'getSyncTrending']);
    });
    
    Route::post('/mcp/logs/{logId}/retry', [McpConnectionApiController::class, 'retryLog']);
    Route::post('/mcp/webhooks/{eventId}/replay', [McpConnectionApiController::class, 'replayWebhook']);
    Route::get('/mcp/webhooks/dashboard', [\App\Modules\MCP\Http\Controllers\Api\V1\McpWebhookAnalyticsController::class, 'dashboard']);
    Route::get('/mcp/webhooks/analytics', [\App\Modules\MCP\Http\Controllers\Api\V1\McpWebhookAnalyticsController::class, 'analytics']);
    
    Route::apiResource('mcp/webhook-subscriptions', \App\Modules\MCP\Http\Controllers\Api\V1\McpWebhookSubscriptionController::class);
    Route::get('mcp/webhook-subscriptions/{subscription}/logs', [\App\Modules\MCP\Http\Controllers\Api\V1\McpWebhookSubscriptionController::class, 'logs']);
});