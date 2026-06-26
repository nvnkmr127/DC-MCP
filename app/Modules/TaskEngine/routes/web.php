<?php
use Illuminate\Support\Facades\Route;
use App\Modules\TaskEngine\Http\Controllers\Web\RecurringTaskWebController;
use App\Modules\TaskEngine\Http\Controllers\Web\SuggestionWebController;

Route::middleware(['auth'])->group(function () {
    // Recurring Tasks
    Route::post('/recurring-tasks',                          [RecurringTaskWebController::class, 'store'])->name('web.recurring-tasks.store');
    Route::patch('/recurring-tasks/{recurringTaskRule}',     [RecurringTaskWebController::class, 'update'])->name('web.recurring-tasks.update');
    Route::delete('/recurring-tasks/{recurringTaskRule}',    [RecurringTaskWebController::class, 'destroy'])->name('web.recurring-tasks.destroy');

    // AI Suggestions
    Route::post('/suggestions/{suggestion}/approve',      [SuggestionWebController::class, 'approve'])->name('web.suggestions.approve');
    Route::post('/suggestions/{suggestion}/reject',       [SuggestionWebController::class, 'reject'])->name('web.suggestions.reject');
    Route::post('/suggestions/bulk-approve',              [SuggestionWebController::class, 'bulkApprove'])->name('web.suggestions.bulk-approve');
    Route::post('/suggestions/from-email',                [SuggestionWebController::class, 'fromEmail'])->name('web.suggestions.from-email');
});
