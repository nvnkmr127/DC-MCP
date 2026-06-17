<?php
use Illuminate\Support\Facades\Route;
use App\Modules\MCP\Http\Controllers\Web\McpWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/settings/mcp',                            [McpWebController::class, 'index'])->name('web.settings.mcp');
    Route::post('/settings/mcp',                           [McpWebController::class, 'store'])->name('web.settings.mcp.store');
    Route::get('/settings/mcp/{connection}',               [McpWebController::class, 'show'])->name('web.settings.mcp.show');
    Route::patch('/settings/mcp/{connection}',             [McpWebController::class, 'update'])->name('web.settings.mcp.update');
    Route::delete('/settings/mcp/{connection}',            [McpWebController::class, 'destroy'])->name('web.settings.mcp.destroy');
    Route::post('/settings/mcp/{connection}/test',         [McpWebController::class, 'test'])->name('web.settings.mcp.test');
    Route::post('/settings/mcp/{connection}/sync',         [McpWebController::class, 'sync'])->name('web.settings.mcp.sync');
});
