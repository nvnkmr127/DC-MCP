<?php

use Illuminate\Support\Facades\Route;

Route::post('auth/logout', 'LoginController@logout');
Route::get('auth/me', 'LoginController@me');
Route::put('auth/password', 'UserController@updatePassword');

// Organization
Route::get('organization', 'OrganizationController@show');
Route::put('organization', 'OrganizationController@update');
Route::get('organization/roles', 'OrganizationController@roles');
Route::post('organization/roles', 'OrganizationController@createRole');
Route::put('organization/roles/{role}', 'OrganizationController@updateRole');

// Team / user management
Route::get('team', 'UserController@index');
Route::post('team/invite', 'UserController@invite');
Route::get('team/{user}', 'UserController@show');
Route::put('team/{user}', 'UserController@update');
Route::post('team/{user}/assign-role', 'UserController@assignRole');
Route::post('team/{user}/deactivate', 'UserController@deactivate');
