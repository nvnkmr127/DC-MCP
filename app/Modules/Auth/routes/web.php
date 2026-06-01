<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Http\Controllers\AuthWebController;
use App\Modules\Auth\Http\Controllers\SettingsWebController;

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
});

// Public API endpoints (registered in web.php to run through web middleware group but with api features)
Route::prefix('api/v1')->middleware(['api', 'throttle:auth'])->group(function () {
    Route::post('auth/register', 'RegisterApiController@register');
    Route::post('auth/login', 'LoginApiController@login');
});
