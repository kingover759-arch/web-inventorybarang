<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetTransactionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
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
    // 1. Dashboard (Ringkasan Statistik)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. Asset (Tabel Pengelolaan Aset)
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    Route::match(['put', 'post'], '/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

    // Cetak Laporan (Fitur lama yang dipertahankan)
    Route::get('/cetak-laporan', [AssetController::class, 'cetakLaporan'])->name('assets.cetak');

    // 3. Keluar-Masuk (Peminjaman)
    Route::get('/keluar-masuk', [AssetTransactionController::class, 'index'])->name('transactions.index');
    Route::post('/keluar-masuk', [AssetTransactionController::class, 'store'])->name('transactions.store');
    Route::delete('/keluar-masuk/{transaction}', [AssetTransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::get('/keluar-masuk/export', [AssetTransactionController::class, 'exportExcel'])->name('transactions.export');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';