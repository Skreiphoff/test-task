<?php

declare(strict_types=1);

use App\Http\Controllers\MarketplaceWebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Middleware\VerifyMarketplaceSignature;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/marketplace', MarketplaceWebhookController::class)
    ->middleware(VerifyMarketplaceSignature::class);

Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
