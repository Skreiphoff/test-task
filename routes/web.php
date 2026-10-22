<?php

use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminTestToolsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.orders.index'));
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
});

if (config('app.admin_test_tools')) {
    Route::prefix('admin/test')->name('admin.test.')->group(function () {
        Route::post('/orders', [AdminTestToolsController::class, 'createOrder'])->name('create');
        Route::post('/orders/{id}/status/{status}', [AdminTestToolsController::class, 'transition'])->name('transition');
        Route::post('/orders/{id}/duplicate', [AdminTestToolsController::class, 'duplicate'])->name('duplicate');
        Route::post('/orders/{id}/stale', [AdminTestToolsController::class, 'stale'])->name('stale');
    });
}
