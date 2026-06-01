<?php

use Illuminate\Support\Facades\Route;

Route::get('notifications', 'NotificationsApiController@index');
Route::get('notifications/unread-count', 'NotificationsApiController@unreadCount');
Route::post('notifications/{id}/read', 'NotificationsApiController@markRead');
Route::post('notifications/mark-all-read', 'NotificationsApiController@markAllRead');
Route::put('notifications/preferences', 'NotificationsApiController@updatePreferences');
