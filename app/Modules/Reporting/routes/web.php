<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Reporting\Http\Controllers\Web\ReportWebController;
use App\Modules\Reporting\Http\Controllers\Web\ClientReportWebController;
use App\Modules\Reporting\Http\Controllers\Web\CampaignResultWebController;
use App\Modules\Reporting\Http\Controllers\Web\SharedReportWebController;

// Public Shared Reports
Route::get('/shared/internal-reports/{token}', [SharedReportWebController::class, 'show'])->name('web.internal-reports.shared');

Route::middleware(['auth'])->group(function () {
    // Reports
    Route::get('/internal-reports',                     [ReportWebController::class, 'index'])->name('web.internal-reports.index');
    Route::get('/internal-reports/compare',             [ReportWebController::class, 'compare'])->name('web.internal-reports.compare');
    Route::get('/internal-reports/create',              [ReportWebController::class, 'create'])->name('web.internal-reports.create');
    Route::get('/internal-reports/{report}',            [ReportWebController::class, 'show'])->name('web.internal-reports.show');
    Route::post('/internal-reports/{report}/comments',  [ReportWebController::class, 'storeComment'])->name('web.internal-reports.comments.store');
    Route::delete('/internal-reports/{report}/comments/{comment}', [ReportWebController::class, 'destroyComment'])->name('web.internal-reports.comments.destroy');

    // Client Reports
    Route::get('/client-updates',                            [ClientReportWebController::class, 'index'])->name('web.client-updates.index');
    Route::post('/client-updates',                           [ClientReportWebController::class, 'store'])->name('web.client-updates.store');
    Route::patch('/client-updates/{report}',                 [ClientReportWebController::class, 'update'])->name('web.client-updates.update');
    Route::delete('/client-updates/{report}',                [ClientReportWebController::class, 'destroy'])->name('web.client-updates.destroy');
    Route::post('/client-updates/{report}/send',             [ClientReportWebController::class, 'markSent'])->name('web.client-updates.send');
    Route::post('/client-updates/{report}/draft',            [ClientReportWebController::class, 'generateDraft'])->name('web.client-updates.draft');

    // Campaign Performance Results
    Route::post('/campaign-results',                         [CampaignResultWebController::class, 'store'])->name('web.campaign-results.store');
    Route::patch('/campaign-results/{result}',               [CampaignResultWebController::class, 'update'])->name('web.campaign-results.update');
    Route::delete('/campaign-results/{result}',              [CampaignResultWebController::class, 'destroy'])->name('web.campaign-results.destroy');
});
