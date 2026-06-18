<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Http\Controllers\Web\AuthWebController;
use App\Modules\Auth\Http\Controllers\Web\SettingsWebController;
use App\Modules\Auth\Http\Controllers\Web\RoleWebController;
use App\Modules\Auth\Http\Controllers\Web\TwoFactorWebController;
use App\Modules\Auth\Http\Controllers\Api\RegisterApiController;
use App\Modules\Auth\Http\Controllers\Api\LoginApiController;

Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthWebController::class, 'login']);
    Route::get('/register', [AuthWebController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthWebController::class, 'register']);
    Route::get('/dev/login/{role}', [AuthWebController::class, 'quickLogin'])->name('dev.login');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
    
    Route::get('/settings/profile',                        [SettingsWebController::class, 'profile'])->name('web.settings.profile');
    Route::patch('/settings/profile',                      [SettingsWebController::class, 'updateProfile'])->name('web.settings.profile.update');
    Route::patch('/settings/password',                     [SettingsWebController::class, 'updatePassword'])->name('web.settings.password.update');
    Route::get('/settings/organization',                   [SettingsWebController::class, 'organization'])->name('web.settings.organization');
    Route::patch('/settings/organization',                 [SettingsWebController::class, 'updateOrganization'])->name('web.settings.organization.update');
    Route::get('/settings/team',                           [SettingsWebController::class, 'team'])->name('web.settings.team');
    Route::post('/settings/team/invite',                   [SettingsWebController::class, 'invite'])->name('web.settings.team.invite');
    Route::patch('/settings/team/{user}/role',             [SettingsWebController::class, 'updateMemberRole'])->name('web.settings.team.role.update');
    Route::get('/settings/roles',                          [RoleWebController::class, 'index'])->name('web.settings.roles');
    Route::post('/settings/roles',                         [RoleWebController::class, 'store'])->name('web.settings.roles.store');
    Route::patch('/settings/roles/{role}',                 [RoleWebController::class, 'update'])->name('web.settings.roles.update');
    Route::delete('/settings/roles/{role}',                [RoleWebController::class, 'destroy'])->name('web.settings.roles.destroy');
    Route::get('/settings/localization',                   [SettingsWebController::class, 'localization'])->name('web.settings.localization');
    Route::get('/settings/notifications',                  [SettingsWebController::class, 'notifications'])->name('web.settings.notifications');
    Route::patch('/settings/notifications',                [SettingsWebController::class, 'updateNotifications'])->name('web.settings.notifications.update');
    
    Route::get('/settings/security/two-factor',            [TwoFactorWebController::class, 'show'])->name('web.settings.two-factor');
    Route::post('/settings/security/two-factor/enable',    [TwoFactorWebController::class, 'enable'])->name('web.settings.two-factor.enable');
    Route::post('/settings/security/two-factor/confirm',   [TwoFactorWebController::class, 'confirm'])->name('web.settings.two-factor.confirm');
    Route::get('/settings/security/two-factor/disable',   [TwoFactorWebController::class, 'disable'])->name('web.settings.two-factor.disable');
    
    // Trash / Soft Deletes
    Route::get('/settings/trash',                          [\App\Modules\Auth\Http\Controllers\Web\TrashController::class, 'index'])->name('web.settings.trash');
    Route::post('/settings/trash/{type}/{id}/restore',     [\App\Modules\Auth\Http\Controllers\Web\TrashController::class, 'restore'])->name('web.settings.trash.restore');
});

// Public API endpoints (registered in web.php to run through web middleware group but with api features)
Route::prefix('api/v1')->middleware(['api', 'throttle:auth'])->group(function () {
    Route::post('auth/register', [RegisterApiController::class, 'register']);
    Route::post('auth/login', [LoginApiController::class, 'login']);
});
