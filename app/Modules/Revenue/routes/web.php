<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Revenue\Http\Controllers\Web\RetainerWebController;
use App\Modules\Revenue\Http\Controllers\Web\InvoiceWebController;
use App\Modules\Revenue\Http\Controllers\Web\ProspectWebController;
use App\Modules\Revenue\Http\Controllers\Web\SowWebController;
use App\Modules\Revenue\Http\Controllers\Web\ExpenseWebController;
use App\Modules\Revenue\Http\Controllers\Web\GSTReportWebController;
use App\Modules\Revenue\Http\Controllers\Web\PaymentReceiptWebController;
use App\Modules\Revenue\Http\Controllers\Web\ProposalWebController;
use App\Modules\Revenue\Http\Controllers\Web\PurchaseOrderWebController;
use App\Modules\Revenue\Http\Controllers\Web\CreditNoteWebController;
use App\Modules\Revenue\Http\Controllers\Web\VendorWebController;
use App\Modules\Revenue\Http\Controllers\Web\CampaignBudgetWebController;
use App\Modules\Revenue\Http\Controllers\Web\RateCardWebController;
use App\Modules\Revenue\Http\Controllers\Web\FinancialWebController;

Route::middleware(['auth'])->group(function () {
    
    // ==========================================
    // Accessible by CEO and Project Manager
    // ==========================================
    Route::middleware(['role:ceo|project_manager'])->group(function () {
        // Retainers
        Route::get('/retainers',                               [RetainerWebController::class, 'index'])->name('web.retainers.index');
        Route::post('/retainers',                              [RetainerWebController::class, 'store'])->name('web.retainers.store');
        Route::patch('/retainers/{retainer}',                  [RetainerWebController::class, 'update'])->name('web.retainers.update');
        Route::delete('/retainers/{retainer}',                 [RetainerWebController::class, 'destroy'])->name('web.retainers.destroy');

        // Invoices
        Route::post('/invoices',                               [InvoiceWebController::class, 'store'])->name('web.invoices.store');
        Route::patch('/invoices/{invoice}',                    [InvoiceWebController::class, 'update'])->name('web.invoices.update');
        Route::delete('/invoices/{invoice}',                   [InvoiceWebController::class, 'destroy'])->name('web.invoices.destroy');
        Route::post('/invoices/{invoice}/payments',            [PaymentReceiptWebController::class, 'store'])->name('web.payments.store');
        Route::delete('/payments/{receipt}',                   [PaymentReceiptWebController::class, 'destroy'])->name('web.payments.destroy');

        // Prospects / Sales Pipeline
        Route::get('/prospects',                               [ProspectWebController::class, 'index'])->name('web.prospects.index');
        Route::post('/prospects',                              [ProspectWebController::class, 'store'])->name('web.prospects.store');
        Route::patch('/prospects/{prospect}',                  [ProspectWebController::class, 'update'])->name('web.prospects.update');
        Route::delete('/prospects/{prospect}',                 [ProspectWebController::class, 'destroy'])->name('web.prospects.destroy');
        Route::post('/prospects/{prospect}/activity',          [ProspectWebController::class, 'addActivity'])->name('web.prospects.activity');
        Route::post('/prospects/{prospect}/convert',           [ProspectWebController::class, 'convert'])->name('web.prospects.convert');

        // SOW Tracker
        Route::post('/sow',                                    [SowWebController::class, 'store'])->name('web.sow.store');
        Route::patch('/sow/{sow}',                             [SowWebController::class, 'update'])->name('web.sow.update');
        Route::delete('/sow/{sow}',                            [SowWebController::class, 'destroy'])->name('web.sow.destroy');

        // Expenses
        Route::post('/expenses',                               [ExpenseWebController::class, 'store'])->name('web.expenses.store');
        Route::patch('/expenses/{expense}',                    [ExpenseWebController::class, 'update'])->name('web.expenses.update');
        Route::delete('/expenses/{expense}',                   [ExpenseWebController::class, 'destroy'])->name('web.expenses.destroy');

        // Proposals
        Route::post('/proposals',                              [ProposalWebController::class, 'store'])->name('web.proposals.store');
        Route::get('/proposals/{proposal}',                    [ProposalWebController::class, 'show'])->name('web.proposals.show');
        Route::patch('/proposals/{proposal}',                  [ProposalWebController::class, 'update'])->name('web.proposals.update');
        Route::delete('/proposals/{proposal}',                 [ProposalWebController::class, 'destroy'])->name('web.proposals.destroy');
        Route::post('/proposals/{proposal}/send',              [ProposalWebController::class, 'markSent'])->name('web.proposals.send');
        Route::post('/proposals/{proposal}/accept',            [ProposalWebController::class, 'accept'])->name('web.proposals.accept');
        Route::post('/proposals/{proposal}/reject',            [ProposalWebController::class, 'reject'])->name('web.proposals.reject');
        Route::post('/proposals/{proposal}/convert-to-sow',    [ProposalWebController::class, 'convertToSow'])->name('web.proposals.convert-to-sow');

        // Purchase Orders
        Route::get('/purchase-orders',                         [PurchaseOrderWebController::class, 'index'])->name('web.purchase-orders.index');
        Route::post('/purchase-orders',                        [PurchaseOrderWebController::class, 'store'])->name('web.purchase-orders.store');
        Route::patch('/purchase-orders/{po}',                  [PurchaseOrderWebController::class, 'update'])->name('web.purchase-orders.update');
        Route::delete('/purchase-orders/{po}',                 [PurchaseOrderWebController::class, 'destroy'])->name('web.purchase-orders.destroy');
        Route::post('/purchase-orders/{po}/status',            [PurchaseOrderWebController::class, 'updateStatus'])->name('web.purchase-orders.status');

        // Campaign Budgets
        Route::get('/campaign-budgets',                        [CampaignBudgetWebController::class, 'index'])->name('web.campaign-budgets.index');
        Route::post('/campaign-budgets',                       [CampaignBudgetWebController::class, 'store'])->name('web.campaign-budgets.store');
        Route::patch('/campaign-budgets/{campaignBudget}/spend',[CampaignBudgetWebController::class, 'updateSpend'])->name('web.campaign-budgets.spend');

        // Vendors
        Route::post('/vendors',                                [VendorWebController::class, 'store'])->name('web.vendors.store');
        Route::patch('/vendors/{vendorContract}',              [VendorWebController::class, 'update'])->name('web.vendors.update');
        Route::delete('/vendors/{vendorContract}',             [VendorWebController::class, 'destroy'])->name('web.vendors.destroy');
    });

    // ==========================================
    // Accessible by CEO ONLY
    // ==========================================
    Route::middleware(['role:ceo'])->group(function () {
        // GST Report
        Route::get('/gst-report',                              [GSTReportWebController::class, 'index'])->name('web.gst-report.index');

        // Credit Notes
        Route::get('/credit-notes',                            [CreditNoteWebController::class, 'index'])->name('web.credit-notes.index');
        Route::post('/credit-notes',                           [CreditNoteWebController::class, 'store'])->name('web.credit-notes.store');
        Route::patch('/credit-notes/{note}',                   [CreditNoteWebController::class, 'update'])->name('web.credit-notes.update');
        Route::delete('/credit-notes/{note}',                  [CreditNoteWebController::class, 'destroy'])->name('web.credit-notes.destroy');
        Route::post('/credit-notes/{note}/apply',              [CreditNoteWebController::class, 'apply'])->name('web.credit-notes.apply');

        // Rate Cards
        Route::get('/rate-cards',                              [RateCardWebController::class, 'index'])->name('web.rate-cards.index');
        Route::post('/rate-cards',                             [RateCardWebController::class, 'store'])->name('web.rate-cards.store');
        Route::patch('/rate-cards/{rateCard}',                 [RateCardWebController::class, 'update'])->name('web.rate-cards.update');
        Route::delete('/rate-cards/{rateCard}',                [RateCardWebController::class, 'destroy'])->name('web.rate-cards.destroy');

        // Financials (P&L)
        Route::get('/financials',                              [FinancialWebController::class, 'index'])->name('web.financials.index');
    });
});
