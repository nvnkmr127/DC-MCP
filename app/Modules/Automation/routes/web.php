<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Automation\Http\Controllers\WorkflowWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/workflows',                                 [WorkflowWebController::class, 'index'])->name('web.workflows.index');
    Route::post('/workflows',                                [WorkflowWebController::class, 'store'])->name('web.workflows.store');
    Route::patch('/workflows/{workflow}',                    [WorkflowWebController::class, 'update'])->name('web.workflows.update');
    Route::delete('/workflows/{workflow}',                   [WorkflowWebController::class, 'destroy'])->name('web.workflows.destroy');
    Route::post('/workflows/{workflow}/toggle',              [WorkflowWebController::class, 'toggleActive'])->name('web.workflows.toggle');
});
