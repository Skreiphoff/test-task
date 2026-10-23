<?php

declare(strict_types=1);

use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminTestToolsController;
use App\Http\Middleware\EnsureAdminTestToolsEnabled;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/orders');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.orders.index'));
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
});

Route::prefix('admin/test')->middleware(EnsureAdminTestToolsEnabled::class)->name('admin.test.')->group(function () {
    Route::post('/orders', [AdminTestToolsController::class, 'createOrder'])->name('create');
    Route::post('/orders/{id}/status/{status}', [AdminTestToolsController::class, 'transition'])->name('transition');
    Route::post('/orders/{id}/duplicate', [AdminTestToolsController::class, 'duplicate'])->name('duplicate');
    Route::post('/orders/{id}/stale', [AdminTestToolsController::class, 'stale'])->name('stale');
});
