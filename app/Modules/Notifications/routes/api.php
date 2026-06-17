<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Notifications\Http\Controllers\Api\NotificationsApiController;

Route::get('notifications', [NotificationsApiController::class, 'index']);
Route::get('notifications/unread-count', [NotificationsApiController::class, 'unreadCount']);
Route::post('notifications/{id}/read', [NotificationsApiController::class, 'markRead']);
Route::post('notifications/mark-all-read', [NotificationsApiController::class, 'markAllRead']);
Route::put('notifications/preferences', [NotificationsApiController::class, 'updatePreferences']);
