<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Standup\Http\Controllers\StandupWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/standup',                                 [StandupWebController::class, 'index'])->name('web.standup.index');
    Route::post('/standup',                                [StandupWebController::class, 'store'])->name('web.standup.store');
    Route::post('/standup/{standup}/reviewed',             [StandupWebController::class, 'markReviewed'])->name('web.standup.reviewed');
});
