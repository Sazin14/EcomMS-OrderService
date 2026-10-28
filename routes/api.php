<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\ReturnController;
use Illuminate\Support\Facades\Route;

/*
 * Payment gateway -> us. No JWT: the X-Signature header is checked instead.
 */
Route::post('payments/webhook', [PaymentController::class, 'webhook']);

/*
 * Customer (JWT whose "type" claim is customer). Every query is limited to the caller's own data.
 */
Route::middleware(['auth.jwt', 'customer'])->group(function () {
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'addItem']);
    Route::patch('cart/items/{item}', [CartController::class, 'updateItem']);
    Route::delete('cart/items/{item}', [CartController::class, 'removeItem']);
    Route::delete('cart', [CartController::class, 'clear']);

    Route::post('checkout', [CheckoutController::class, 'store']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{order}/pay', [OrderController::class, 'pay']);

    Route::get('preorders', [PreorderController::class, 'index']);

    Route::get('returns', [ReturnController::class, 'index']);
    Route::post('orders/{order}/returns', [ReturnController::class, 'store']);
});

/*
 * Staff (JWT + Redis authorization + one permission per route).
 * New permissions to create in AuthService: order.view order.confirm order.cancel fulfillment.manage
 *                                           payment.manage return.manage refund.manage
 */
Route::middleware(['auth.jwt', 'authz'])->prefix('admin')->group(function () {
    Route::get('orders', [Admin\OrderController::class, 'index'])->middleware('permission:order.view');
    Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->middleware('permission:order.view');
    Route::post('orders/{order}/confirm', [Admin\OrderController::class, 'confirm'])->middleware('permission:order.confirm');
    Route::post('orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->middleware('permission:order.cancel');
    Route::post('orders/{order}/resolve-attention', [Admin\OrderController::class, 'resolveAttention'])->middleware('permission:order.confirm');

    Route::get('preorders', [Admin\PreorderController::class, 'index'])->middleware('permission:order.view');
    Route::get('payments/{payment}/attempts', [PaymentController::class, 'attempts'])->middleware('permission:payment.manage');

    Route::post('orders/{order}/fulfillment', [Admin\FulfillmentController::class, 'store'])->middleware('permission:fulfillment.manage');
    Route::post('fulfillments/{fulfillment}/pack', [Admin\FulfillmentController::class, 'pack'])->middleware('permission:fulfillment.manage');
    Route::post('fulfillments/{fulfillment}/ship', [Admin\FulfillmentController::class, 'ship'])->middleware('permission:fulfillment.manage');
    Route::post('fulfillments/{fulfillment}/deliver', [Admin\FulfillmentController::class, 'deliver'])->middleware('permission:fulfillment.manage');
    Route::post('fulfillments/{fulfillment}/delivery-failed', [Admin\FulfillmentController::class, 'deliveryFailed'])->middleware('permission:fulfillment.manage');

    Route::get('returns', [Admin\ReturnController::class, 'index'])->middleware('permission:return.manage');
    Route::get('returns/{return}', [Admin\ReturnController::class, 'show'])->middleware('permission:return.manage');
    Route::post('returns/{return}/approve', [Admin\ReturnController::class, 'approve'])->middleware('permission:return.manage');
    Route::post('returns/{return}/reject', [Admin\ReturnController::class, 'reject'])->middleware('permission:return.manage');
    Route::post('returns/{return}/receive', [Admin\ReturnController::class, 'receive'])->middleware('permission:return.manage');
    Route::post('returns/{return}/complete', [Admin\ReturnController::class, 'complete'])->middleware('permission:return.manage');

    Route::get('refunds', [Admin\RefundController::class, 'index'])->middleware('permission:refund.manage');
    Route::post('orders/{order}/refunds', [Admin\RefundController::class, 'store'])->middleware('permission:refund.manage');
    Route::post('refunds/{refund}/process', [Admin\RefundController::class, 'process'])->middleware('permission:refund.manage');
    Route::post('refunds/{refund}/complete', [Admin\RefundController::class, 'complete'])->middleware('permission:refund.manage');
});
