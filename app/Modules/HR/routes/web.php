<?php
use Illuminate\Support\Facades\Route;
use App\Modules\HR\Http\Controllers\Web\LeaveWebController;
use App\Modules\HR\Http\Controllers\Web\HiringWebController;
use App\Modules\HR\Http\Controllers\Web\PerformanceReviewWebController;
use App\Modules\HR\Http\Controllers\Web\AnnouncementWebController;
use App\Modules\ProjectManagement\Http\Controllers\Web\FreelancerWebController;
use App\Modules\HR\Http\Controllers\Web\KnowledgeBaseWebController;
use App\Modules\HR\Http\Controllers\Web\OneOnOneWebController;
use App\Modules\HR\Http\Controllers\Web\PayrollWebController;

Route::middleware(['auth'])->group(function () {
    // Leave Management
    Route::get('/leave',                                     [LeaveWebController::class, 'index'])->name('web.leave.index');
    Route::post('/leave',                                    [LeaveWebController::class, 'store'])->name('web.leave.store');
    Route::post('/leave/{leave}/approve',                    [LeaveWebController::class, 'approve'])->name('web.leave.approve');
    Route::post('/leave/{leave}/reject',                     [LeaveWebController::class, 'reject'])->name('web.leave.reject');
    Route::delete('/leave/{leave}',                          [LeaveWebController::class, 'destroy'])->name('web.leave.destroy');

    // Hiring Pipeline
    Route::get('/hiring',                                    [HiringWebController::class, 'index'])->name('web.hiring.index');
    Route::post('/hiring/openings',                          [HiringWebController::class, 'storeOpening'])->name('web.hiring.openings.store');
    Route::patch('/hiring/openings/{opening}',               [HiringWebController::class, 'updateOpening'])->name('web.hiring.openings.update');
    Route::delete('/hiring/openings/{opening}',              [HiringWebController::class, 'destroyOpening'])->name('web.hiring.openings.destroy');
    Route::post('/hiring/openings/{opening}/candidates',     [HiringWebController::class, 'storeCandidate'])->name('web.hiring.candidates.store');
    Route::patch('/hiring/candidates/{candidate}',           [HiringWebController::class, 'updateCandidate'])->name('web.hiring.candidates.update');
    Route::delete('/hiring/candidates/{candidate}',          [HiringWebController::class, 'destroyCandidate'])->name('web.hiring.candidates.destroy');

    // Performance Reviews
    Route::get('/reviews',                                   [PerformanceReviewWebController::class, 'index'])->name('web.reviews.index');
    Route::post('/reviews',                                  [PerformanceReviewWebController::class, 'store'])->name('web.reviews.store');
    Route::patch('/reviews/{review}',                        [PerformanceReviewWebController::class, 'update'])->name('web.reviews.update');
    Route::post('/reviews/{review}/submit',                  [PerformanceReviewWebController::class, 'submit'])->name('web.reviews.submit');
    Route::post('/reviews/{review}/acknowledge',             [PerformanceReviewWebController::class, 'acknowledge'])->name('web.reviews.acknowledge');

    // Announcements
    Route::get('/announcements',                             [AnnouncementWebController::class, 'index'])->name('web.announcements.index');
    Route::post('/announcements',                            [AnnouncementWebController::class, 'store'])->name('web.announcements.store');
    Route::patch('/announcements/{announcement}',            [AnnouncementWebController::class, 'update'])->name('web.announcements.update');
    Route::delete('/announcements/{announcement}',           [AnnouncementWebController::class, 'destroy'])->name('web.announcements.destroy');

    // Freelancers
    Route::get('/freelancers',                               [FreelancerWebController::class, 'index'])->name('web.freelancers.index');
    Route::post('/freelancers',                              [FreelancerWebController::class, 'store'])->name('web.freelancers.store');
    Route::patch('/freelancers/{freelancer}',                [FreelancerWebController::class, 'update'])->name('web.freelancers.update');
    Route::delete('/freelancers/{freelancer}',               [FreelancerWebController::class, 'destroy'])->name('web.freelancers.destroy');
    Route::post('/freelancers/{freelancer}/assignments',     [FreelancerWebController::class, 'storeAssignment'])->name('web.freelancers.assignments.store');
    Route::patch('/freelancer-assignments/{assignment}',     [FreelancerWebController::class, 'updateAssignment'])->name('web.freelancer-assignments.update');
    Route::delete('/freelancer-assignments/{assignment}',    [FreelancerWebController::class, 'destroyAssignment'])->name('web.freelancer-assignments.destroy');

    // Knowledge Base
    Route::get('/knowledge-base',                            [KnowledgeBaseWebController::class, 'index'])->name('web.knowledge-base.index');
    Route::post('/knowledge-base',                           [KnowledgeBaseWebController::class, 'store'])->name('web.knowledge-base.store');
    Route::get('/knowledge-base/{article}',                  [KnowledgeBaseWebController::class, 'show'])->name('web.knowledge-base.show');
    Route::patch('/knowledge-base/{article}',                [KnowledgeBaseWebController::class, 'update'])->name('web.knowledge-base.update');
    Route::delete('/knowledge-base/{article}',               [KnowledgeBaseWebController::class, 'destroy'])->name('web.knowledge-base.destroy');

    // 1:1 Notes
    Route::get('/one-on-one',                                [OneOnOneWebController::class, 'index'])->name('web.one-on-one.index');
    Route::post('/one-on-one',                               [OneOnOneWebController::class, 'store'])->name('web.one-on-one.store');
    Route::patch('/one-on-one/{oneOnOneNote}',               [OneOnOneWebController::class, 'update'])->name('web.one-on-one.update');
    Route::post('/one-on-one/{oneOnOneNote}/action-item',    [OneOnOneWebController::class, 'toggleActionItem'])->name('web.one-on-one.action-item');

    // Payroll Management
    Route::get('/payroll',                                   [PayrollWebController::class, 'index'])->name('web.payroll.index');
    Route::post('/payroll',                                  [PayrollWebController::class, 'store'])->name('web.payroll.store');
    Route::post('/payroll/bulk-generate',                    [PayrollWebController::class, 'bulkGenerate'])->name('web.payroll.bulk-generate');
    Route::post('/payroll/{payrollRecord}/paid',             [PayrollWebController::class, 'markPaid'])->name('web.payroll.paid');
});
