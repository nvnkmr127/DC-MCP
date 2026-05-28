<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\BriefingController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\CalendarController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\McpController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SuggestionController;
use App\Http\Controllers\Web\ContentCalendarController;
use App\Http\Controllers\Web\ClientPortalController;
use App\Modules\ClientPortal\Http\Controllers\PortalController;

/*
|--------------------------------------------------------------------------
| Web Routes — Inertia SPA
|--------------------------------------------------------------------------
| Route names are prefixed with "web." to avoid conflicts with API routes
| that share the same resource names.
*/

// ─── Client Portal (separate auth — magic link based) ───────────────────────
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login',           [PortalController::class, 'showLogin'])->name('login');
    Route::get('/auth/{token}',    [PortalController::class, 'handleMagicLink'])->name('magic');
    Route::post('/logout',         [PortalController::class, 'logout'])->name('logout');
    Route::get('/dashboard',       [PortalController::class, 'dashboard'])->name('dashboard');
    Route::post('/requests',       [PortalController::class, 'submitRequest'])->name('requests.store');
});

// Auth (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register']);

    // One-click demo login — disabled in production (returns 404)
    Route::get('/dev/login/{role}', [AuthController::class, 'quickLogin'])->name('dev.login');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Authenticated routes
Route::middleware(['auth'])->group(function () {

    // Redirect root to dashboard
    Route::get('/', fn() => redirect()->to('/dashboard'));

    // /settings → redirect to profile
    Route::get('/settings', fn() => redirect()->to('/settings/profile'));

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::get('/projects',                    [ProjectController::class, 'index'])->name('web.projects.index');
    Route::get('/projects/create',             [ProjectController::class, 'create'])->name('web.projects.create');
    Route::post('/projects',                   [ProjectController::class, 'store'])->name('web.projects.store');
    Route::get('/projects/{project}',          [ProjectController::class, 'show'])->name('web.projects.show');
    Route::get('/projects/{project}/edit',     [ProjectController::class, 'edit'])->name('web.projects.edit');
    Route::patch('/projects/{project}',        [ProjectController::class, 'update'])->name('web.projects.update');
    Route::delete('/projects/{project}',       [ProjectController::class, 'destroy'])->name('web.projects.destroy');
    Route::get('/projects/{project}/kanban',   [ProjectController::class, 'kanban'])->name('web.projects.kanban');
    Route::get('/projects/{project}/stats',    [ProjectController::class, 'stats'])->name('web.projects.stats');

    // Tasks
    Route::get('/tasks',                       [TaskController::class, 'index'])->name('web.tasks.index');
    Route::get('/tasks/create',                [TaskController::class, 'create'])->name('web.tasks.create');
    Route::post('/tasks',                      [TaskController::class, 'store'])->name('web.tasks.store');
    Route::get('/tasks/{task}',                [TaskController::class, 'show'])->name('web.tasks.show');
    Route::get('/tasks/{task}/edit',           [TaskController::class, 'edit'])->name('web.tasks.edit');
    Route::patch('/tasks/{task}',              [TaskController::class, 'update'])->name('web.tasks.update');
    Route::delete('/tasks/{task}',             [TaskController::class, 'destroy'])->name('web.tasks.destroy');
    Route::post('/tasks/{task}/move',          [TaskController::class, 'move'])->name('web.tasks.move');
    Route::post('/tasks/{task}/comments',      [TaskController::class, 'storeComment'])->name('web.tasks.comments.store');
    Route::post('/tasks/{task}/log-time',      [TaskController::class, 'logTime'])->name('web.tasks.time.store');
    Route::post('/attachments',                [TaskController::class, 'uploadAttachment'])->name('web.attachments.store');
    Route::delete('/attachments/{attachment}', [TaskController::class, 'destroyAttachment'])->name('web.attachments.destroy');
    Route::delete('/tasks/{task}/comments/{comment}',       [TaskController::class, 'destroyComment'])->name('web.tasks.comments.destroy');
    Route::delete('/tasks/{task}/time-entries/{timeEntry}', [TaskController::class, 'destroyTimeEntry'])->name('web.tasks.time.destroy');

    // Clients
    Route::get('/clients',                     [ClientController::class, 'index'])->name('web.clients.index');
    Route::get('/clients/create',              [ClientController::class, 'create'])->name('web.clients.create');
    Route::post('/clients',                    [ClientController::class, 'store'])->name('web.clients.store');
    Route::get('/clients/{client}',            [ClientController::class, 'show'])->name('web.clients.show');
    Route::get('/clients/{client}/edit',       [ClientController::class, 'edit'])->name('web.clients.edit');
    Route::patch('/clients/{client}',          [ClientController::class, 'update'])->name('web.clients.update');
    Route::delete('/clients/{client}',         [ClientController::class, 'destroy'])->name('web.clients.destroy');

    // Content Calendar
    Route::get('/content',                                    [ContentCalendarController::class, 'index'])->name('web.content.index');
    Route::post('/content',                                   [ContentCalendarController::class, 'store'])->name('web.content.store');
    Route::patch('/content/{contentItem}',                    [ContentCalendarController::class, 'update'])->name('web.content.update');
    Route::delete('/content/{contentItem}',                   [ContentCalendarController::class, 'destroy'])->name('web.content.destroy');
    Route::post('/content/{contentItem}/convert-to-task',     [ContentCalendarController::class, 'convertToTask'])->name('web.content.convert');

    // Client Portal Management (CEO only)
    Route::get('/settings/client-portal',                                     [ClientPortalController::class, 'manage'])->name('web.settings.portal');
    Route::get('/settings/client-portal/{client}',                            [ClientPortalController::class, 'showClient'])->name('web.settings.portal.client');
    Route::post('/settings/client-portal/{client}/invite',                    [ClientPortalController::class, 'inviteUser'])->name('web.settings.portal.invite');
    Route::post('/settings/client-portal/users/{portalUser}/resend',          [ClientPortalController::class, 'resendInvite'])->name('web.settings.portal.resend');
    Route::post('/settings/client-portal/users/{portalUser}/toggle',          [ClientPortalController::class, 'toggleUser'])->name('web.settings.portal.toggle');
    Route::post('/settings/client-portal/share',                              [ClientPortalController::class, 'share'])->name('web.settings.portal.share');
    Route::delete('/settings/client-portal/shares/{portalShare}',             [ClientPortalController::class, 'revokeShare'])->name('web.settings.portal.revoke');
    Route::post('/settings/client-portal/requests/{portalRequest}/to-task',   [ClientPortalController::class, 'convertRequestToTask'])->name('web.settings.portal.request.task');
    Route::post('/settings/client-portal/requests/{portalRequest}/close',     [ClientPortalController::class, 'closeRequest'])->name('web.settings.portal.request.close');

    // AI Task Suggestions (Founder approval flow)
    Route::get('/suggestions',                            [SuggestionController::class, 'index'])->name('web.suggestions.index');
    Route::post('/suggestions/{suggestion}/approve',      [SuggestionController::class, 'approve'])->name('web.suggestions.approve');
    Route::post('/suggestions/{suggestion}/reject',       [SuggestionController::class, 'reject'])->name('web.suggestions.reject');
    Route::post('/suggestions/bulk-approve',              [SuggestionController::class, 'bulkApprove'])->name('web.suggestions.bulk-approve');
    Route::post('/suggestions/from-email',                [SuggestionController::class, 'fromEmail'])->name('web.suggestions.from-email');

    // Daily Briefings
    Route::get('/briefings',                   [BriefingController::class, 'index'])->name('web.briefings.index');
    Route::get('/briefings/{briefing}',        [BriefingController::class, 'show'])->name('web.briefings.show');
    Route::post('/briefings/generate',         [BriefingController::class, 'generate'])->name('web.briefings.generate');

    // Reports
    Route::get('/reports',                     [ReportController::class, 'index'])->name('web.reports.index');
    Route::get('/reports/create',              [ReportController::class, 'create'])->name('web.reports.create');
    Route::get('/reports/{report}',            [ReportController::class, 'show'])->name('web.reports.show');

    // Calendar
    Route::get('/calendar',                    [CalendarController::class, 'index'])->name('web.calendar.index');

    // Notifications
    Route::get('/notifications',                           [NotificationController::class, 'index'])->name('web.notifications.index');
    Route::post('/notifications/{notification}/read',      [NotificationController::class, 'markRead'])->name('web.notifications.read');
    Route::post('/notifications/read-all',                 [NotificationController::class, 'markAllRead'])->name('web.notifications.readAll');

    // MCP Settings
    Route::get('/settings/mcp',                            [McpController::class, 'index'])->name('web.settings.mcp');
    Route::post('/settings/mcp',                           [McpController::class, 'store'])->name('web.settings.mcp.store');
    Route::get('/settings/mcp/{connection}',               [McpController::class, 'show'])->name('web.settings.mcp.show');
    Route::patch('/settings/mcp/{connection}',             [McpController::class, 'update'])->name('web.settings.mcp.update');
    Route::delete('/settings/mcp/{connection}',            [McpController::class, 'destroy'])->name('web.settings.mcp.destroy');
    Route::post('/settings/mcp/{connection}/test',         [McpController::class, 'test'])->name('web.settings.mcp.test');
    Route::post('/settings/mcp/{connection}/sync',         [McpController::class, 'sync'])->name('web.settings.mcp.sync');

    // User / Org Settings
    Route::get('/settings/profile',                        [SettingsController::class, 'profile'])->name('web.settings.profile');
    Route::patch('/settings/profile',                      [SettingsController::class, 'updateProfile'])->name('web.settings.profile.update');
    Route::patch('/settings/password',                     [SettingsController::class, 'updatePassword'])->name('web.settings.password.update');
    Route::get('/settings/organization',                   [SettingsController::class, 'organization'])->name('web.settings.organization');
    Route::patch('/settings/organization',                 [SettingsController::class, 'updateOrganization'])->name('web.settings.organization.update');
    Route::get('/settings/team',                           [SettingsController::class, 'team'])->name('web.settings.team');
    Route::post('/settings/team/invite',                   [SettingsController::class, 'invite'])->name('web.settings.team.invite');
});
