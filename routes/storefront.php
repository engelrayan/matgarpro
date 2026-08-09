<?php

use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\FeedController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront — what a customer sees
|--------------------------------------------------------------------------
| Served on the merchant's own domain and on {slug}.matgarpro.com. The `store`
| middleware has already resolved the tenant, so nothing here reads the Host.
|
| Blade, not Inertia: these pages are paid for with ad money, and a second of
| load time is a measurable share of the merchant's budget.
*/

Route::get('/', HomeController::class)->name('storefront.home');

// Product feed the ad platforms pull on their own schedule.
Route::get('/feed/{platform}.xml', FeedController::class)->name('storefront.feed');

Route::get('/search', [HomeController::class, 'search'])->name('storefront.search');

Route::get('/p/{slug}', ProductController::class)->name('storefront.product');

Route::get('/c/{slug}', [HomeController::class, 'category'])->name('storefront.category');

// Beacon fired when the customer starts filling the form. Separate from the
// order itself, because the people who start and never finish are exactly the
// number the conversion rate is about.
Route::post('/checkout/start', [CheckoutController::class, 'start'])->name('storefront.checkout.start');

Route::post('/checkout', [CheckoutController::class, 'store'])->name('storefront.checkout');

Route::get('/thanks/{order}', [CheckoutController::class, 'thanks'])->name('storefront.thanks');
