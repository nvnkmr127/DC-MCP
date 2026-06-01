<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Reporting\Http\Controllers\ReportWebController;
use App\Modules\Reporting\Http\Controllers\ClientReportWebController;
use App\Modules\Reporting\Http\Controllers\CampaignResultWebController;

Route::middleware(['auth'])->group(function () {
    // Reports
    Route::get('/reports',                     [ReportWebController::class, 'index'])->name('web.reports.index');
    Route::get('/reports/create',              [ReportWebController::class, 'create'])->name('web.reports.create');
    Route::get('/reports/{report}',            [ReportWebController::class, 'show'])->name('web.reports.show');

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
