<?php

use App\Http\Controllers\Dashboard\BuilderController;
use App\Http\Controllers\Dashboard\AbandonedCartController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\DamanShipmentController;
use App\Http\Controllers\Dashboard\OrderController;
use App\Http\Controllers\Dashboard\OrderWhatsappController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\ProfitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Merchant dashboard
|--------------------------------------------------------------------------
| Ownership is checked per record in the controllers rather than by scoping
| the route: a merchant may own several stores, so the URL alone can never
| establish who a product belongs to.
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    // POST, not PATCH: the form is multipart and PHP does not populate files on
    // a spoofed PATCH body.
    Route::post('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('products/{product}/duplicate', [ProductController::class, 'replicate'])->name('products.duplicate');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Managed on one screen — a store has a handful of categories, and three
    // navigations to rename one is three too many.
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    // POST, not PATCH: the form carries an image, and PHP does not populate
    // uploads on a spoofed PATCH body.
    Route::post('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::put('categories-order', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    /*
    | Store builder.
    |
    | `{page}` is validated against the section registry inside the controller
    | rather than by a route constraint, so adding a page is one edit in the
    | registry and not two in two files.
    */
    Route::get('builder/products', [BuilderController::class, 'products'])->name('builder.products');
    Route::post('builder/upload', [BuilderController::class, 'upload'])->name('builder.upload');
    Route::get('builder/{page?}', [BuilderController::class, 'index'])->name('builder');
    Route::put('builder/{page}', [BuilderController::class, 'update'])->name('builder.update');
    Route::post('builder/{page}/publish', [BuilderController::class, 'publish'])->name('builder.publish');
    Route::post('builder/{page}/discard', [BuilderController::class, 'discard'])->name('builder.discard');
    Route::post('builder/{page}/reset', [BuilderController::class, 'reset'])->name('builder.reset');

    Route::get('reports/profit', ProfitController::class)->name('reports.profit');

    Route::get('carts', [AbandonedCartController::class, 'index'])->name('carts.index');
    Route::patch('carts/{cart}/contacted', [AbandonedCartController::class, 'contacted'])->name('carts.contacted');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/waybills', [OrderController::class, 'waybills'])->name('orders.waybills');
    Route::get('orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    // Inline cell edit from the grid.
    Route::patch('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::patch('orders-bulk/status', [OrderController::class, 'bulkStatus'])->name('orders.bulk-status');
    // Hand a batch over to Daman, which routes it to the carrier the merchant
    // is contracted with and answers with the waybill.
    Route::post('orders-bulk/daman', [DamanShipmentController::class, 'store'])->name('orders.daman');

    // Ask the customer to confirm, by hand — for a store that leaves auto-send
    // off, and for the order whose first message never got an answer.
    Route::post('orders/{order}/whatsapp', [OrderWhatsappController::class, 'store'])->name('orders.whatsapp');
});
