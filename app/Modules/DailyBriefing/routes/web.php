<?php
use Illuminate\Support\Facades\Route;
use App\Modules\DailyBriefing\Http\Controllers\BriefingWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/briefings',                   [BriefingWebController::class, 'index'])->name('web.briefings.index');
    Route::get('/briefings/{briefing}',        [BriefingWebController::class, 'show'])->name('web.briefings.show');
    Route::post('/briefings/generate',         [BriefingWebController::class, 'generate'])->name('web.briefings.generate');
});
