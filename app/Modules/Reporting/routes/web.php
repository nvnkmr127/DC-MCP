<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Reporting\Http\Controllers\Web\ReportWebController;
use App\Modules\Reporting\Http\Controllers\Web\ClientReportWebController;
use App\Modules\Reporting\Http\Controllers\Web\CampaignResultWebController;
use App\Modules\Reporting\Http\Controllers\Web\SharedReportWebController;

// Public Shared Reports
Route::get('/shared/reports/{token}', [SharedReportWebController::class, 'show'])->name('web.reports.shared');

Route::middleware(['auth'])->group(function () {
    // Reports
    Route::get('/reports',                     [ReportWebController::class, 'index'])->name('web.reports.index');
    Route::get('/reports/compare',             [ReportWebController::class, 'compare'])->name('web.reports.compare');
    Route::get('/reports/create',              [ReportWebController::class, 'create'])->name('web.reports.create');
    Route::get('/reports/{report}',            [ReportWebController::class, 'show'])->name('web.reports.show');
    Route::post('/reports/{report}/comments',  [ReportWebController::class, 'storeComment'])->name('web.reports.comments.store');
    Route::delete('/reports/{report}/comments/{comment}', [ReportWebController::class, 'destroyComment'])->name('web.reports.comments.destroy');

    // Client Reports
    Route::get('/client-reports',                            [ClientReportWebController::class, 'index'])->name('web.client-reports.index');
    Route::post('/client-reports',                           [ClientReportWebController::class, 'store'])->name('web.client-reports.store');
    Route::patch('/client-reports/{report}',                 [ClientReportWebController::class, 'update'])->name('web.client-reports.update');
    Route::delete('/client-reports/{report}',                [ClientReportWebController::class, 'destroy'])->name('web.client-reports.destroy');
    Route::post('/client-reports/{report}/send',             [ClientReportWebController::class, 'markSent'])->name('web.client-reports.send');
    Route::post('/client-reports/{report}/draft',            [ClientReportWebController::class, 'generateDraft'])->name('web.client-reports.draft');

    // Campaign Performance Results
    Route::post('/campaign-results',                         [CampaignResultWebController::class, 'store'])->name('web.campaign-results.store');
    Route::patch('/campaign-results/{result}',               [CampaignResultWebController::class, 'update'])->name('web.campaign-results.update');
    Route::delete('/campaign-results/{result}',              [CampaignResultWebController::class, 'destroy'])->name('web.campaign-results.destroy');
});
