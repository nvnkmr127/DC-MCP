<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Auth\Http\Controllers\Web\AuthWebController;
use App\Modules\Auth\Http\Controllers\Web\SettingsWebController;
use App\Modules\Auth\Http\Controllers\Web\RoleWebController;
use App\Modules\Auth\Http\Controllers\Web\TwoFactorWebController;
use App\Modules\Auth\Http\Controllers\Web\MyActivityWebController;
use App\Modules\Auth\Http\Controllers\Api\RegisterApiController;
use App\Modules\Auth\Http\Controllers\Api\LoginApiController;
use App\Modules\Auth\Http\Controllers\Web\SetupController;

Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthWebController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthWebController::class, 'login']);
    Route::get('/register', [AuthWebController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthWebController::class, 'register']);
    Route::get('/dev/login/{role}', [AuthWebController::class, 'quickLogin'])->name('dev.login');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
    
    // Organization Setup / Onboarding
    Route::get('/setup', [SetupController::class, 'index'])->name('web.setup.index');
    Route::post('/setup', [SetupController::class, 'complete'])->name('web.setup.complete');
    
    Route::get('/settings/profile',                        [SettingsWebController::class, 'profile'])->name('web.settings.profile');
    Route::patch('/settings/profile',                      [SettingsWebController::class, 'updateProfile'])->name('web.settings.profile.update');
    Route::patch('/settings/password',                     [SettingsWebController::class, 'updatePassword'])->name('web.settings.password.update');
    
    Route::get('/my-activity',                             [MyActivityWebController::class, 'index'])->name('web.my-activity');

    // Advanced Account Settings
    Route::delete('/settings/sessions/{id}',               [SettingsWebController::class, 'destroySession'])->name('web.settings.sessions.destroy');
    Route::post('/settings/tokens',                        [SettingsWebController::class, 'createToken'])->name('web.settings.tokens.store');
    Route::delete('/settings/tokens/{id}',                 [SettingsWebController::class, 'revokeToken'])->name('web.settings.tokens.destroy');
    Route::get('/settings/export-data',                    [SettingsWebController::class, 'exportData'])->name('web.settings.export');
    Route::get('/settings/organization',                   [SettingsWebController::class, 'organization'])->name('web.settings.organization');
    Route::patch('/settings/organization',                 [SettingsWebController::class, 'updateOrganization'])->name('web.settings.organization.update');
    Route::post('/settings/maintenance',                   [SettingsWebController::class, 'toggleMaintenance'])->name('web.settings.maintenance.toggle');
    Route::get('/settings/health',                         [\App\Modules\Auth\Http\Controllers\Web\HealthWebController::class, 'index'])->name('web.settings.health');
    Route::get('/settings/audit-export',                   [SettingsWebController::class, 'exportAuditLog'])->name('web.settings.audit-export');
    Route::get('/settings/team',                           [SettingsWebController::class, 'team'])->name('web.settings.team');    Route::post('/settings/team/bulk-invite',              [SettingsWebController::class, 'bulkInvite'])->name('web.settings.team.bulk-invite');
    Route::post('/settings/team/bulk-invite',              [SettingsWebController::class, 'bulkInvite'])->name('web.settings.team.bulk-invite');
    Route::get('/settings/team/{user}/activity',           [SettingsWebController::class, 'memberActivity'])->name('web.settings.team.activity');
    Route::patch('/settings/team/{user}/role',             [SettingsWebController::class, 'updateMemberRole'])->name('web.settings.team.role.update');    Route::post('/settings/team/{user}/transfer-work',     [SettingsWebController::class, 'transferWork'])->name('web.settings.team.transfer-work');
    Route::post('/settings/team/{user}/toggle-active',     [SettingsWebController::class, 'toggleActive'])->name('web.settings.team.toggle-active');    Route::patch('/settings/roles/{role}',                 [RoleWebController::class, 'update'])->name('web.settings.roles.update');
    Route::delete('/settings/roles/{role}',                [RoleWebController::class, 'destroy'])->name('web.settings.roles.destroy');
    Route::get('/settings/localization',                   [SettingsWebController::class, 'localization'])->name('web.settings.localization');
    Route::get('/settings/notifications',                  [SettingsWebController::class, 'notifications'])->name('web.settings.notifications');
    Route::patch('/settings/notifications',                [SettingsWebController::class, 'updateNotifications'])->name('web.settings.notifications.update');
    
    Route::get('/settings/security/two-factor',            [TwoFactorWebController::class, 'show'])->name('web.settings.two-factor');
    Route::post('/settings/security/two-factor/enable',    [TwoFactorWebController::class, 'enable'])->name('web.settings.two-factor.enable');
    Route::post('/settings/security/two-factor/confirm',   [TwoFactorWebController::class, 'confirm'])->name('web.settings.two-factor.confirm');
    Route::get('/settings/security/two-factor/disable',   [TwoFactorWebController::class, 'disable'])->name('web.settings.two-factor.disable');
    
    // Data Import
    Route::get('/settings/import',                         [\App\Modules\Auth\Http\Controllers\Web\DataImportController::class, 'index'])->name('web.settings.import');
    Route::get('/settings/import/template',                [\App\Modules\Auth\Http\Controllers\Web\DataImportController::class, 'downloadTemplate'])->name('web.settings.import.template');
    Route::post('/settings/import/upload',                 [\App\Modules\Auth\Http\Controllers\Web\DataImportController::class, 'upload'])->name('web.settings.import.upload');

    // Trash / Soft Deletes
    Route::get('/settings/trash',                          [\App\Modules\Auth\Http\Controllers\Web\TrashController::class, 'index'])->name('web.settings.trash');
    Route::post('/settings/trash/{type}/{id}/restore',     [\App\Modules\Auth\Http\Controllers\Web\TrashController::class, 'restore'])->name('web.settings.trash.restore');
    Route::post('/settings/trash/bulk-delete',             [\App\Modules\Auth\Http\Controllers\Web\TrashController::class, 'bulkDelete'])->name('web.settings.trash.bulk-delete');
});
