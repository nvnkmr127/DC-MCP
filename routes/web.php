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
use App\Http\Controllers\Web\RetainerController;
use App\Http\Controllers\Web\InvoiceController;
use App\Http\Controllers\Web\ProspectController;
use App\Http\Controllers\Web\StandupController;
use App\Http\Controllers\Web\SowController;
use App\Http\Controllers\Web\CapacityController;
use App\Http\Controllers\Web\FinancialController;
use App\Http\Controllers\Web\ExpenseController;
use App\Http\Controllers\Web\PayrollController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\CampaignBudgetController;
use App\Http\Controllers\Web\VendorController;
use App\Http\Controllers\Web\TimesheetController;
use App\Http\Controllers\Web\ClientCommunicationController;
use App\Http\Controllers\Web\GoalController;
use App\Http\Controllers\Web\DeliverableController;
use App\Http\Controllers\Web\OneOnOneController;
use App\Http\Controllers\Web\RecurringTaskController;
use App\Modules\ClientPortal\Http\Controllers\PortalController;
use App\Http\Controllers\Web\PaymentReceiptController;
use App\Http\Controllers\Web\GSTReportController;
use App\Http\Controllers\Web\LeaveController;
use App\Http\Controllers\Web\ProposalController;
use App\Http\Controllers\Web\PerformanceReviewController;
use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\HiringController;
use App\Http\Controllers\Web\ProjectTemplateController;
use App\Http\Controllers\Web\IssueController;
use App\Http\Controllers\Web\RateCardController;
use App\Http\Controllers\Web\ClientReportController;
use App\Http\Controllers\Web\FreelancerController;
use App\Http\Controllers\Web\KnowledgeBaseController;
use App\Http\Controllers\Web\ClientSurveyController;
use App\Http\Controllers\Web\CampaignResultController;
use App\Http\Controllers\Web\AuditChecklistController;
use App\Http\Controllers\Web\SprintController;
use App\Http\Controllers\Web\AssetApprovalController;
use App\Http\Controllers\Web\WorkflowController;
use App\Http\Controllers\Web\PurchaseOrderController;
use App\Http\Controllers\Web\CreditNoteController;

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

    // Dashboard Builder (DataViz)
    Route::get('/dashboard-builder', fn() => \Inertia\Inertia::render('DataViz/Index'))->name('web.dashboard-builder');

    // Help
    Route::get('/help', fn() => \Inertia\Inertia::render('Help/Index'))->name('web.help');

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

    // Revenue — Retainers
    Route::get('/retainers',                               [RetainerController::class, 'index'])->name('web.retainers.index');
    Route::post('/retainers',                              [RetainerController::class, 'store'])->name('web.retainers.store');
    Route::patch('/retainers/{retainer}',                  [RetainerController::class, 'update'])->name('web.retainers.update');
    Route::delete('/retainers/{retainer}',                 [RetainerController::class, 'destroy'])->name('web.retainers.destroy');

    // Revenue — Invoices
    Route::post('/invoices',                               [InvoiceController::class, 'store'])->name('web.invoices.store');
    Route::patch('/invoices/{invoice}',                    [InvoiceController::class, 'update'])->name('web.invoices.update');
    Route::delete('/invoices/{invoice}',                   [InvoiceController::class, 'destroy'])->name('web.invoices.destroy');

    // Sales Pipeline — Prospects
    Route::get('/prospects',                               [ProspectController::class, 'index'])->name('web.prospects.index');
    Route::post('/prospects',                              [ProspectController::class, 'store'])->name('web.prospects.store');
    Route::patch('/prospects/{prospect}',                  [ProspectController::class, 'update'])->name('web.prospects.update');
    Route::delete('/prospects/{prospect}',                 [ProspectController::class, 'destroy'])->name('web.prospects.destroy');
    Route::post('/prospects/{prospect}/activity',          [ProspectController::class, 'addActivity'])->name('web.prospects.activity');

    // EOD Standup
    Route::get('/standup',                                 [StandupController::class, 'index'])->name('web.standup.index');
    Route::post('/standup',                                [StandupController::class, 'store'])->name('web.standup.store');
    Route::post('/standup/{standup}/reviewed',             [StandupController::class, 'markReviewed'])->name('web.standup.reviewed');

    // SOW Tracker
    Route::get('/sow',                                     [SowController::class, 'index'])->name('web.sow.index');
    Route::post('/sow',                                    [SowController::class, 'store'])->name('web.sow.store');
    Route::patch('/sow/{sow}',                             [SowController::class, 'update'])->name('web.sow.update');
    Route::delete('/sow/{sow}',                            [SowController::class, 'destroy'])->name('web.sow.destroy');

    // Team Capacity
    Route::get('/capacity',                                [CapacityController::class, 'index'])->name('web.capacity.index');

    // P&L / Financials
    Route::get('/financials',                              [FinancialController::class, 'index'])->name('web.financials.index');

    // Expenses
    Route::post('/expenses',                               [ExpenseController::class, 'store'])->name('web.expenses.store');
    Route::patch('/expenses/{expense}',                    [ExpenseController::class, 'update'])->name('web.expenses.update');
    Route::delete('/expenses/{expense}',                   [ExpenseController::class, 'destroy'])->name('web.expenses.destroy');

    // Payroll
    Route::get('/payroll',                                 [PayrollController::class, 'index'])->name('web.payroll.index');
    Route::post('/payroll',                                [PayrollController::class, 'store'])->name('web.payroll.store');
    Route::post('/payroll/{payrollRecord}/paid',           [PayrollController::class, 'markPaid'])->name('web.payroll.paid');
    Route::post('/payroll/bulk-generate',                  [PayrollController::class, 'bulkGenerate'])->name('web.payroll.bulk-generate');

    // Client Onboarding
    Route::get('/onboarding',                              [OnboardingController::class, 'index'])->name('web.onboarding.index');
    Route::post('/onboarding',                             [OnboardingController::class, 'store'])->name('web.onboarding.store');
    Route::post('/onboarding/{onboarding}/advance',        [OnboardingController::class, 'advance'])->name('web.onboarding.advance');
    Route::post('/onboarding/{onboarding}/checklist',      [OnboardingController::class, 'toggleChecklist'])->name('web.onboarding.checklist');
    Route::post('/onboarding/{onboarding}/nps',            [OnboardingController::class, 'submitNps'])->name('web.onboarding.nps');

    // Campaign Budgets
    Route::get('/campaign-budgets',                          [CampaignBudgetController::class, 'index'])->name('web.campaign-budgets.index');
    Route::post('/campaign-budgets',                         [CampaignBudgetController::class, 'store'])->name('web.campaign-budgets.store');
    Route::patch('/campaign-budgets/{campaignBudget}/spend', [CampaignBudgetController::class, 'updateSpend'])->name('web.campaign-budgets.spend');

    // Vendor Contracts
    Route::post('/vendors',                                  [VendorController::class, 'store'])->name('web.vendors.store');
    Route::patch('/vendors/{vendorContract}',                [VendorController::class, 'update'])->name('web.vendors.update');
    Route::delete('/vendors/{vendorContract}',               [VendorController::class, 'destroy'])->name('web.vendors.destroy');

    // Timesheets
    Route::get('/timesheets',                                [TimesheetController::class, 'index'])->name('web.timesheets.index');
    Route::post('/timesheets/timer/start',                   [TimesheetController::class, 'startTimer'])->name('web.timesheets.timer.start');
    Route::post('/timesheets/timer/{timeEntry}/stop',        [TimesheetController::class, 'stopTimer'])->name('web.timesheets.timer.stop');

    // Client Communications
    Route::post('/clients/{client}/communications',          [ClientCommunicationController::class, 'store'])->name('web.client-communications.store');
    Route::patch('/client-communications/{communication}',   [ClientCommunicationController::class, 'update'])->name('web.client-communications.update');
    Route::delete('/client-communications/{communication}',  [ClientCommunicationController::class, 'destroy'])->name('web.client-communications.destroy');

    // Goals / OKRs
    Route::get('/goals',                                     [GoalController::class, 'index'])->name('web.goals.index');
    Route::post('/goals',                                    [GoalController::class, 'store'])->name('web.goals.store');
    Route::patch('/goals/{goal}',                            [GoalController::class, 'update'])->name('web.goals.update');
    Route::delete('/goals/{goal}',                           [GoalController::class, 'destroy'])->name('web.goals.destroy');
    Route::patch('/goals/{goal}/kr',                         [GoalController::class, 'updateKeyResult'])->name('web.goals.kr');

    // Deliverable Submissions
    Route::post('/sow/deliverables/{sowDeliverable}/submit',        [DeliverableController::class, 'submit'])->name('web.deliverables.submit');
    Route::post('/deliverables/{deliverableSubmission}/approve',    [DeliverableController::class, 'approve'])->name('web.deliverables.approve');
    Route::post('/deliverables/{deliverableSubmission}/revision',   [DeliverableController::class, 'requestRevision'])->name('web.deliverables.revision');

    // Task Dependencies
    Route::post('/tasks/{task}/dependencies',                [TaskController::class, 'addDependency'])->name('web.tasks.dependencies.add');
    Route::delete('/tasks/{task}/dependencies/{dependency}', [TaskController::class, 'removeDependency'])->name('web.tasks.dependencies.remove');

    // 1:1 Notes
    Route::get('/one-on-one',                                [OneOnOneController::class, 'index'])->name('web.one-on-one.index');
    Route::post('/one-on-one',                               [OneOnOneController::class, 'store'])->name('web.one-on-one.store');
    Route::patch('/one-on-one/{oneOnOneNote}',               [OneOnOneController::class, 'update'])->name('web.one-on-one.update');
    Route::post('/one-on-one/{oneOnOneNote}/action-item',    [OneOnOneController::class, 'toggleActionItem'])->name('web.one-on-one.action-item');

    // Upsell Flag
    Route::patch('/clients/{client}/upsell',                 [ClientController::class, 'flagUpsell'])->name('web.clients.upsell');
    Route::post('/clients/{client}/success-score',           [ClientController::class, 'updateSuccessScore'])->name('web.clients.success-score');

    // Recurring Tasks
    Route::get('/recurring-tasks',                           [RecurringTaskController::class, 'index'])->name('web.recurring-tasks.index');
    Route::post('/recurring-tasks',                          [RecurringTaskController::class, 'store'])->name('web.recurring-tasks.store');
    Route::patch('/recurring-tasks/{recurringTaskRule}',     [RecurringTaskController::class, 'update'])->name('web.recurring-tasks.update');
    Route::delete('/recurring-tasks/{recurringTaskRule}',    [RecurringTaskController::class, 'destroy'])->name('web.recurring-tasks.destroy');

    // Payment Receipts
    Route::post('/invoices/{invoice}/payments',              [PaymentReceiptController::class, 'store'])->name('web.payments.store');
    Route::delete('/payments/{receipt}',                     [PaymentReceiptController::class, 'destroy'])->name('web.payments.destroy');

    // GST Report
    Route::get('/gst-report',                                [GSTReportController::class, 'index'])->name('web.gst-report.index');

    // Leave Management
    Route::get('/leave',                                     [LeaveController::class, 'index'])->name('web.leave.index');
    Route::post('/leave',                                    [LeaveController::class, 'store'])->name('web.leave.store');
    Route::post('/leave/{leave}/approve',                    [LeaveController::class, 'approve'])->name('web.leave.approve');
    Route::post('/leave/{leave}/reject',                     [LeaveController::class, 'reject'])->name('web.leave.reject');
    Route::delete('/leave/{leave}',                          [LeaveController::class, 'destroy'])->name('web.leave.destroy');

    // Proposals
    Route::get('/proposals',                                 [ProposalController::class, 'index'])->name('web.proposals.index');
    Route::post('/proposals',                                [ProposalController::class, 'store'])->name('web.proposals.store');
    Route::get('/proposals/{proposal}',                      [ProposalController::class, 'show'])->name('web.proposals.show');
    Route::patch('/proposals/{proposal}',                    [ProposalController::class, 'update'])->name('web.proposals.update');
    Route::delete('/proposals/{proposal}',                   [ProposalController::class, 'destroy'])->name('web.proposals.destroy');
    Route::post('/proposals/{proposal}/send',                [ProposalController::class, 'markSent'])->name('web.proposals.send');
    Route::post('/proposals/{proposal}/accept',              [ProposalController::class, 'accept'])->name('web.proposals.accept');
    Route::post('/proposals/{proposal}/reject',              [ProposalController::class, 'reject'])->name('web.proposals.reject');

    // Performance Reviews
    Route::get('/reviews',                                   [PerformanceReviewController::class, 'index'])->name('web.reviews.index');
    Route::post('/reviews',                                  [PerformanceReviewController::class, 'store'])->name('web.reviews.store');
    Route::patch('/reviews/{review}',                        [PerformanceReviewController::class, 'update'])->name('web.reviews.update');
    Route::post('/reviews/{review}/submit',                  [PerformanceReviewController::class, 'submit'])->name('web.reviews.submit');
    Route::post('/reviews/{review}/acknowledge',             [PerformanceReviewController::class, 'acknowledge'])->name('web.reviews.acknowledge');

    // Announcements
    Route::get('/announcements',                             [AnnouncementController::class, 'index'])->name('web.announcements.index');
    Route::post('/announcements',                            [AnnouncementController::class, 'store'])->name('web.announcements.store');
    Route::patch('/announcements/{announcement}',            [AnnouncementController::class, 'update'])->name('web.announcements.update');
    Route::delete('/announcements/{announcement}',           [AnnouncementController::class, 'destroy'])->name('web.announcements.destroy');

    // Hiring Pipeline
    Route::get('/hiring',                                    [HiringController::class, 'index'])->name('web.hiring.index');
    Route::post('/hiring/openings',                          [HiringController::class, 'storeOpening'])->name('web.hiring.openings.store');
    Route::patch('/hiring/openings/{opening}',               [HiringController::class, 'updateOpening'])->name('web.hiring.openings.update');
    Route::delete('/hiring/openings/{opening}',              [HiringController::class, 'destroyOpening'])->name('web.hiring.openings.destroy');
    Route::post('/hiring/openings/{opening}/candidates',     [HiringController::class, 'storeCandidate'])->name('web.hiring.candidates.store');
    Route::patch('/hiring/candidates/{candidate}',           [HiringController::class, 'updateCandidate'])->name('web.hiring.candidates.update');
    Route::delete('/hiring/candidates/{candidate}',          [HiringController::class, 'destroyCandidate'])->name('web.hiring.candidates.destroy');

    // Project Templates
    Route::get('/project-templates',                         [ProjectTemplateController::class, 'index'])->name('web.project-templates.index');
    Route::post('/project-templates',                        [ProjectTemplateController::class, 'store'])->name('web.project-templates.store');
    Route::patch('/project-templates/{template}',            [ProjectTemplateController::class, 'update'])->name('web.project-templates.update');
    Route::delete('/project-templates/{template}',           [ProjectTemplateController::class, 'destroy'])->name('web.project-templates.destroy');
    Route::post('/project-templates/{template}/create-project', [ProjectTemplateController::class, 'createProject'])->name('web.project-templates.create-project');

    // Issues
    Route::get('/issues',                                    [IssueController::class, 'index'])->name('web.issues.index');
    Route::post('/issues',                                   [IssueController::class, 'store'])->name('web.issues.store');
    Route::patch('/issues/{issue}',                          [IssueController::class, 'update'])->name('web.issues.update');
    Route::delete('/issues/{issue}',                         [IssueController::class, 'destroy'])->name('web.issues.destroy');
    Route::post('/issues/{issue}/task',                      [IssueController::class, 'convertToTask'])->name('web.issues.task');

    // Rate Cards
    Route::get('/rate-cards',                                [RateCardController::class, 'index'])->name('web.rate-cards.index');
    Route::post('/rate-cards',                               [RateCardController::class, 'store'])->name('web.rate-cards.store');
    Route::patch('/rate-cards/{rateCard}',                   [RateCardController::class, 'update'])->name('web.rate-cards.update');
    Route::delete('/rate-cards/{rateCard}',                  [RateCardController::class, 'destroy'])->name('web.rate-cards.destroy');

    // Client Reports
    Route::get('/client-reports',                            [ClientReportController::class, 'index'])->name('web.client-reports.index');
    Route::post('/client-reports',                           [ClientReportController::class, 'store'])->name('web.client-reports.store');
    Route::patch('/client-reports/{report}',                 [ClientReportController::class, 'update'])->name('web.client-reports.update');
    Route::delete('/client-reports/{report}',                [ClientReportController::class, 'destroy'])->name('web.client-reports.destroy');
    Route::post('/client-reports/{report}/send',             [ClientReportController::class, 'markSent'])->name('web.client-reports.send');
    Route::post('/client-reports/{report}/draft',            [ClientReportController::class, 'generateDraft'])->name('web.client-reports.draft');

    // Bulk Task Creator
    Route::post('/projects/{project}/bulk-tasks',            [TaskController::class, 'bulkStore'])->name('web.projects.bulk-tasks');

    // Freelancers
    Route::get('/freelancers',                               [FreelancerController::class, 'index'])->name('web.freelancers.index');
    Route::post('/freelancers',                              [FreelancerController::class, 'store'])->name('web.freelancers.store');
    Route::patch('/freelancers/{freelancer}',                [FreelancerController::class, 'update'])->name('web.freelancers.update');
    Route::delete('/freelancers/{freelancer}',               [FreelancerController::class, 'destroy'])->name('web.freelancers.destroy');
    Route::post('/freelancers/{freelancer}/assignments',     [FreelancerController::class, 'storeAssignment'])->name('web.freelancers.assignments.store');
    Route::patch('/freelancer-assignments/{assignment}',     [FreelancerController::class, 'updateAssignment'])->name('web.freelancer-assignments.update');
    Route::delete('/freelancer-assignments/{assignment}',    [FreelancerController::class, 'destroyAssignment'])->name('web.freelancer-assignments.destroy');

    // Knowledge Base
    Route::get('/knowledge-base',                            [KnowledgeBaseController::class, 'index'])->name('web.knowledge-base.index');
    Route::post('/knowledge-base',                           [KnowledgeBaseController::class, 'store'])->name('web.knowledge-base.store');
    Route::get('/knowledge-base/{article}',                  [KnowledgeBaseController::class, 'show'])->name('web.knowledge-base.show');
    Route::patch('/knowledge-base/{article}',                [KnowledgeBaseController::class, 'update'])->name('web.knowledge-base.update');
    Route::delete('/knowledge-base/{article}',               [KnowledgeBaseController::class, 'destroy'])->name('web.knowledge-base.destroy');

    // NPS Surveys
    Route::get('/client-surveys',                            [ClientSurveyController::class, 'index'])->name('web.client-surveys.index');
    Route::post('/client-surveys/send',                      [ClientSurveyController::class, 'send'])->name('web.client-surveys.send');
    Route::delete('/client-surveys/{survey}',                [ClientSurveyController::class, 'destroy'])->name('web.client-surveys.destroy');

    // Campaign Performance
    Route::post('/campaign-results',                         [CampaignResultController::class, 'store'])->name('web.campaign-results.store');
    Route::patch('/campaign-results/{result}',               [CampaignResultController::class, 'update'])->name('web.campaign-results.update');
    Route::delete('/campaign-results/{result}',              [CampaignResultController::class, 'destroy'])->name('web.campaign-results.destroy');

    // Audit Checklists
    Route::get('/audit-checklists',                          [AuditChecklistController::class, 'index'])->name('web.audit-checklists.index');
    Route::post('/audit-checklists',                         [AuditChecklistController::class, 'store'])->name('web.audit-checklists.store');
    Route::patch('/audit-checklists/{checklist}',            [AuditChecklistController::class, 'update'])->name('web.audit-checklists.update');
    Route::delete('/audit-checklists/{checklist}',           [AuditChecklistController::class, 'destroy'])->name('web.audit-checklists.destroy');

    // Sprints
    Route::get('/sprints',                                   [SprintController::class, 'index'])->name('web.sprints.index');
    Route::post('/sprints',                                  [SprintController::class, 'store'])->name('web.sprints.store');
    Route::patch('/sprints/{sprint}',                        [SprintController::class, 'update'])->name('web.sprints.update');
    Route::delete('/sprints/{sprint}',                       [SprintController::class, 'destroy'])->name('web.sprints.destroy');
    Route::post('/sprints/{sprint}/tasks',                   [SprintController::class, 'addTask'])->name('web.sprints.tasks.add');
    Route::delete('/sprints/{sprint}/tasks/{task}',          [SprintController::class, 'removeTask'])->name('web.sprints.tasks.remove');

    // Asset Approvals
    Route::get('/asset-approvals',                           [AssetApprovalController::class, 'index'])->name('web.asset-approvals.index');
    Route::post('/asset-approvals',                          [AssetApprovalController::class, 'store'])->name('web.asset-approvals.store');
    Route::patch('/asset-approvals/{approval}',              [AssetApprovalController::class, 'update'])->name('web.asset-approvals.update');
    Route::delete('/asset-approvals/{approval}',             [AssetApprovalController::class, 'destroy'])->name('web.asset-approvals.destroy');

    // Workflows
    Route::get('/workflows',                                 [WorkflowController::class, 'index'])->name('web.workflows.index');
    Route::post('/workflows',                                [WorkflowController::class, 'store'])->name('web.workflows.store');
    Route::patch('/workflows/{workflow}',                    [WorkflowController::class, 'update'])->name('web.workflows.update');
    Route::delete('/workflows/{workflow}',                   [WorkflowController::class, 'destroy'])->name('web.workflows.destroy');
    Route::post('/workflows/{workflow}/toggle',              [WorkflowController::class, 'toggleActive'])->name('web.workflows.toggle');

    // Purchase Orders
    Route::get('/purchase-orders',                           [PurchaseOrderController::class, 'index'])->name('web.purchase-orders.index');
    Route::post('/purchase-orders',                          [PurchaseOrderController::class, 'store'])->name('web.purchase-orders.store');
    Route::patch('/purchase-orders/{po}',                    [PurchaseOrderController::class, 'update'])->name('web.purchase-orders.update');
    Route::delete('/purchase-orders/{po}',                   [PurchaseOrderController::class, 'destroy'])->name('web.purchase-orders.destroy');
    Route::post('/purchase-orders/{po}/status',              [PurchaseOrderController::class, 'updateStatus'])->name('web.purchase-orders.status');

    // Credit Notes
    Route::get('/credit-notes',                              [CreditNoteController::class, 'index'])->name('web.credit-notes.index');
    Route::post('/credit-notes',                             [CreditNoteController::class, 'store'])->name('web.credit-notes.store');
    Route::patch('/credit-notes/{note}',                     [CreditNoteController::class, 'update'])->name('web.credit-notes.update');
    Route::delete('/credit-notes/{note}',                    [CreditNoteController::class, 'destroy'])->name('web.credit-notes.destroy');
    Route::post('/credit-notes/{note}/apply',                [CreditNoteController::class, 'apply'])->name('web.credit-notes.apply');
});

// NPS Survey (public, no auth)
Route::get('/survey/{token}',  [ClientSurveyController::class, 'showForm'])->name('survey.form');
Route::post('/survey/{token}', [ClientSurveyController::class, 'respond'])->name('survey.respond');

// Client Portal (client-facing, no main auth required)
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login',              [PortalController::class, 'showLogin'])->name('login');
    Route::get('/auth/{token}',       [PortalController::class, 'handleMagicLink'])->name('magic-link');
    Route::post('/logout',            [PortalController::class, 'logout'])->name('logout');
    Route::get('/dashboard',          [PortalController::class, 'dashboard'])->name('dashboard');
    Route::post('/requests',          [PortalController::class, 'submitRequest'])->name('requests.store');
});
