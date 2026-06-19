<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Http\Controllers\Api\V1\UserApiController;
use App\Modules\Auth\Http\Controllers\Api\V1\LoginApiController;
use App\Modules\Auth\Http\Controllers\Api\V1\OrganizationApiController;

Route::prefix('v1')->group(function () {
    Route::post('auth/logout', [LoginApiController::class, 'logout']);
    Route::get('auth/me', [LoginApiController::class, 'me']);
    Route::put('auth/password', [UserApiController::class, 'updatePassword']);
    
    // Organization
    Route::get('organization', [OrganizationApiController::class, 'show']);
    Route::put('organization', [OrganizationApiController::class, 'update']);
    Route::get('organization/roles', [OrganizationApiController::class, 'roles']);
    Route::post('organization/roles', [OrganizationApiController::class, 'createRole']);
    Route::put('organization/roles/{role}', [OrganizationApiController::class, 'updateRole']);
    
    // Team / user management
    Route::get('team', [UserApiController::class, 'index']);
    Route::post('team/invite', [UserApiController::class, 'invite']);
    Route::get('team/{user}', [UserApiController::class, 'show']);
    Route::put('team/{user}', [UserApiController::class, 'update']);
    Route::post('team/{user}/assign-role', [UserApiController::class, 'assignRole']);
    Route::post('team/{user}/deactivate', [UserApiController::class, 'deactivate']);
    
});