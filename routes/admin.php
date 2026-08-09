<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\Auth\AdminSessionController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\ThemeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform panel
|--------------------------------------------------------------------------
| Required from web.php, so it inherits the dashboard's domain constraint and
| never answers on a merchant's own hostname. `admin` is already in
| `storefront.reserved_slugs`, so no store can take the sub-domain either.
|
| Every authenticated route carries `admin.active` as well as `auth:admin`:
| the guard proves the session was signed, the middleware proves the account
| is still allowed in today. The handful that change the platform's rules
| rather than apply them add `admin.super` on top.
|
| Orders are absent by design — the panel reports on them in aggregate and
| cannot open one. See StoreController.
*/

Route::prefix('admin')->name('admin.')->group(function (): void {

    Route::middleware('guest.admin')->group(function (): void {
        Route::get('login', [AdminSessionController::class, 'create'])->name('login');
        // The throttling that matters is inside AdminLoginRequest, on two keys.
        // This one is only a ceiling on request volume.
        Route::post('login', [AdminSessionController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('login.store');
    });

    Route::middleware(['auth:admin', 'admin.active'])->group(function (): void {
        Route::post('logout', [AdminSessionController::class, 'destroy'])->name('logout');

        Route::get('/', OverviewController::class)->name('overview');

        // ---- Stores -------------------------------------------------------
        Route::get('stores', [StoreController::class, 'index'])->name('stores.index');
        Route::get('stores/{store}', [StoreController::class, 'show'])->name('stores.show');
        Route::patch('stores/{store}/status', [StoreController::class, 'status'])->name('stores.status');
        Route::patch('stores/{store}/billing', [StoreController::class, 'billing'])->name('stores.billing');
        Route::patch('stores/{store}/theme', [StoreController::class, 'theme'])->name('stores.theme');
        // POST, not PATCH: this one appends a ledger row rather than editing a
        // field, and it is never idempotent — sending it twice moves the money
        // twice, on purpose.
        Route::post('stores/{store}/wallet', [StoreController::class, 'wallet'])->name('stores.wallet');

        // ---- Merchants (read-only) ----------------------------------------
        Route::get('merchants', [MerchantController::class, 'index'])->name('merchants.index');
        Route::get('merchants/{merchant}', [MerchantController::class, 'show'])->name('merchants.show');

        // ---- Themes -------------------------------------------------------
        Route::get('themes', [ThemeController::class, 'index'])->name('themes.index');

        // ---- Domains ------------------------------------------------------
        Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
        Route::post('domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
        Route::post('domains/{domain}/primary', [DomainController::class, 'primary'])->name('domains.primary');
        Route::delete('domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

        // ---- Audit trail --------------------------------------------------
        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

        // ---- Rules of the platform: super only -----------------------------
        Route::middleware('admin.super')->group(function (): void {
            Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
            Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
            Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
            Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

            Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
            Route::post('admins', [AdminController::class, 'store'])->name('admins.store');
            Route::patch('admins/{admin}', [AdminController::class, 'update'])->name('admins.update');
            Route::patch('admins/{admin}/password', [AdminController::class, 'password'])->name('admins.password');
        });
    });
});
