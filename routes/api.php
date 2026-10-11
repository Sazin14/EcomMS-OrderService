<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FulfillmentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\ReturnController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer / Storefront routes
|--------------------------------------------------------------------------
|
| Apply your existing VerifyJwt + CheckAuthorization middleware to these
| routes according to your project's final auth setup.
|
*/

Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/items', [CartController::class, 'addItem']);
    Route::patch('/items/{item}', [CartController::class, 'updateItem']);
    Route::delete('/items/{item}', [CartController::class, 'removeItem']);
});

Route::post('/checkout', [CheckoutController::class, 'store']);

Route::get('/orders', [OrderController::class, 'index']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

Route::get('/preorders', [PreorderController::class, 'index']);
Route::post('/preorders', [PreorderController::class, 'store']);
Route::delete('/preorders/{preorder}', [PreorderController::class, 'destroy']);

Route::post('/orders/{order}/returns', [ReturnController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Internal / Staff routes
|--------------------------------------------------------------------------
*/

Route::prefix('internal')->group(function () {
    Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);
    Route::post('/orders/{order}/process', [OrderController::class, 'process']);

    Route::post('/orders/{order}/fulfillments', [FulfillmentController::class, 'store']);
    Route::post('/fulfillments/{fulfillment}/ship', [FulfillmentController::class, 'ship']);

    Route::post('/orders/{order}/inventory-fulfill', [FulfillmentController::class, 'fulfillInventory']);

    Route::get('/returns', [ReturnController::class, 'index']);
    Route::post('/returns/{return}/approve', [ReturnController::class, 'approve']);
    Route::post('/returns/{return}/complete', [ReturnController::class, 'complete']);

    Route::get('/payments/{payment}/attempts', [PaymentController::class, 'attempts']);
    Route::post('/orders/{order}/refunds', [PaymentController::class, 'refund']);
    Route::post('/refunds/{refund}/complete', [PaymentController::class, 'completeRefund']);
});
