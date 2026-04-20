<?php

use App\Http\Controllers\MonitorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatusPageController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [MonitorController::class, 'index'])->name('dashboard');
    Route::get('/monitors/create', [MonitorController::class, 'create'])->name('monitors.create');
    Route::post('/monitors', [MonitorController::class, 'store'])
        ->middleware('check.plan.limits:monitors')
        ->name('monitors.store');
    Route::get('/monitors/{monitor}', [MonitorController::class, 'show'])->name('monitors.show');
    Route::get('/monitors/{monitor}/metrics', [MonitorController::class, 'metrics'])->name('monitors.metrics');
    Route::get('/monitors/{monitor}/edit', [MonitorController::class, 'edit'])->name('monitors.edit');
    Route::put('/monitors/{monitor}', [MonitorController::class, 'update'])->name('monitors.update');
    Route::patch('/monitors/{monitor}/pause', [MonitorController::class, 'togglePause'])->name('monitors.toggle-pause');
    Route::delete('/monitors/{monitor}', [MonitorController::class, 'destroy'])->name('monitors.destroy');

    // Status Pages
    Route::get('/status-pages', [StatusPageController::class, 'index'])->name('status-pages.index');
    Route::get('/status-pages/create', [StatusPageController::class, 'create'])->name('status-pages.create');
    Route::post('/status-pages', [StatusPageController::class, 'store'])
        ->middleware('check.plan.limits:status-pages')
        ->name('status-pages.store');
    Route::get('/status-pages/{status_page}/edit', [StatusPageController::class, 'edit'])->name('status-pages.edit');
    Route::put('/status-pages/{status_page}', [StatusPageController::class, 'update'])->name('status-pages.update');
    Route::patch('/status-pages/{status_page}/toggle', [StatusPageController::class, 'toggle'])->name('status-pages.toggle');
    Route::get('/status-pages/{status_page}/configure', [StatusPageController::class, 'configure'])->name('status-pages.configure');
    Route::put('/status-pages/{status_page}/monitors', [StatusPageController::class, 'updateMonitors'])->name('status-pages.update-monitors');
    Route::delete('/status-pages/{status_page}', [StatusPageController::class, 'destroy'])->name('status-pages.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Public status page
Route::get('/status/{status_page:slug}', [StatusPageController::class, 'publicShow'])->name('status-pages.public');

require __DIR__.'/auth.php';
