<?php
use Illuminate\Support\Facades\Route;
use App\Modules\ContentCalendar\Http\Controllers\Web\ContentCalendarWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/content',                                    [ContentCalendarWebController::class, 'index'])->name('web.content.index');
    Route::post('/content',                                   [ContentCalendarWebController::class, 'store'])->name('web.content.store');
    Route::patch('/content/{contentItem}',                    [ContentCalendarWebController::class, 'update'])->name('web.content.update');
    Route::delete('/content/{contentItem}',                   [ContentCalendarWebController::class, 'destroy'])->name('web.content.destroy');
    Route::post('/content/{contentItem}/convert-to-task',     [ContentCalendarWebController::class, 'convertToTask'])->name('web.content.convert');
});
