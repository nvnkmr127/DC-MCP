<?php

use Illuminate\Support\Facades\Route;

Route::post('auth/logout', 'LoginApiController@logout');
Route::get('auth/me', 'LoginApiController@me');
Route::put('auth/password', 'UserApiController@updatePassword');

// Organization
Route::get('organization', 'OrganizationApiController@show');
Route::put('organization', 'OrganizationApiController@update');
Route::get('organization/roles', 'OrganizationApiController@roles');
Route::post('organization/roles', 'OrganizationApiController@createRole');
Route::put('organization/roles/{role}', 'OrganizationApiController@updateRole');

// Team / user management
Route::get('team', 'UserApiController@index');
Route::post('team/invite', 'UserApiController@invite');
Route::get('team/{user}', 'UserApiController@show');
Route::put('team/{user}', 'UserApiController@update');
Route::post('team/{user}/assign-role', 'UserApiController@assignRole');
Route::post('team/{user}/deactivate', 'UserApiController@deactivate');
