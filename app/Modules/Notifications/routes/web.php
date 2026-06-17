<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Notifications\Http\Controllers\Web\NotificationWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/notifications',                           [NotificationWebController::class, 'index'])->name('web.notifications.index');
    Route::post('/notifications/{notification}/read',      [NotificationWebController::class, 'markRead'])->name('web.notifications.read');
    Route::post('/notifications/read-all',                 [NotificationWebController::class, 'markAllRead'])->name('web.notifications.readAll');
});
