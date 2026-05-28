<?php

use Illuminate\Support\Facades\Route;

Route::get('notifications', 'NotificationsController@index');
Route::get('notifications/unread-count', 'NotificationsController@unreadCount');
Route::post('notifications/{id}/read', 'NotificationsController@markRead');
Route::post('notifications/mark-all-read', 'NotificationsController@markAllRead');
Route::put('notifications/preferences', 'NotificationsController@updatePreferences');
