<?php
use Illuminate\Support\Facades\Route;
use App\Modules\DataViz\Http\Controllers\Web\DashboardWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardWebController::class, 'index'])->name('dashboard');
    Route::get('/dashboard-builder', fn() => \Inertia\Inertia::render('DataViz/Index'))->name('web.dashboard-builder');
});

// Public Dashboard Route
Route::get('/public/dashboards/{token}', [\App\Modules\DataViz\Http\Controllers\Web\PublicDashboardController::class, 'show'])->name('public.dashboard.show');
