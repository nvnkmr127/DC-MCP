<?php
use Illuminate\Support\Facades\Route;
use App\Modules\ClientPortal\Http\Controllers\PortalController;
use App\Modules\ClientPortal\Http\Controllers\ClientPortalWebController;
use App\Modules\ClientPortal\Http\Controllers\ClientSurveyWebController;

// Magic link auth & portal dashboard
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login',           [PortalController::class, 'showLogin'])->name('login');
    Route::get('/auth/{token}',    [PortalController::class, 'handleMagicLink'])->name('magic');
    Route::get('/auth/{token}/link', [PortalController::class, 'handleMagicLink'])->name('magic-link');
    Route::post('/logout',         [PortalController::class, 'logout'])->name('logout');
    Route::get('/dashboard',       [PortalController::class, 'dashboard'])->name('dashboard');
    Route::post('/requests',       [PortalController::class, 'submitRequest'])->name('requests.store');
});

Route::middleware(['auth'])->group(function () {
    // Client Portal Management (CEO only)
    Route::get('/settings/client-portal',                                     [ClientPortalWebController::class, 'manage'])->name('web.settings.portal');
    Route::get('/settings/client-portal/{client}',                            [ClientPortalWebController::class, 'showClient'])->name('web.settings.portal.client');
    Route::post('/settings/client-portal/{client}/invite',                    [ClientPortalWebController::class, 'inviteUser'])->name('web.settings.portal.invite');
    Route::post('/settings/client-portal/users/{portalUser}/resend',          [ClientPortalWebController::class, 'resendInvite'])->name('web.settings.portal.resend');
    Route::post('/settings/client-portal/users/{portalUser}/toggle',          [ClientPortalWebController::class, 'toggleUser'])->name('web.settings.portal.toggle');
    Route::post('/settings/client-portal/share',                              [ClientPortalWebController::class, 'share'])->name('web.settings.portal.share');
    Route::delete('/settings/client-portal/shares/{portalShare}',             [ClientPortalWebController::class, 'revokeShare'])->name('web.settings.portal.revoke');
    Route::post('/settings/client-portal/requests/{portalRequest}/to-task',   [ClientPortalWebController::class, 'convertRequestToTask'])->name('web.settings.portal.request.task');
    Route::post('/settings/client-portal/requests/{portalRequest}/close',     [ClientPortalWebController::class, 'closeRequest'])->name('web.settings.portal.request.close');

    // NPS Surveys Admin
    Route::get('/client-surveys',                            [ClientSurveyWebController::class, 'index'])->name('web.client-surveys.index');
    Route::post('/client-surveys/send',                      [ClientSurveyWebController::class, 'send'])->name('web.client-surveys.send');
    Route::delete('/client-surveys/{survey}',                [ClientSurveyWebController::class, 'destroy'])->name('web.client-surveys.destroy');
});

// NPS Survey Public Response Routes (No Auth)
Route::get('/survey/{token}',  [ClientSurveyWebController::class, 'showForm'])->name('survey.form');
Route::post('/survey/{token}', [ClientSurveyWebController::class, 'respond'])->name('survey.respond');
