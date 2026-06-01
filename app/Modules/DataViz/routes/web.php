<?php
use Illuminate\Support\Facades\Route;
use App\Modules\DataViz\Http\Controllers\DashboardWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardWebController::class, 'index'])->name('dashboard');
    Route::get('/dashboard-builder', fn() => \Inertia\Inertia::render('DataViz/Index'))->name('web.dashboard-builder');
});
