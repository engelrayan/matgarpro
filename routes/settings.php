<?php

use App\Http\Controllers\Settings\CatalogController;
use App\Http\Controllers\Settings\CheckoutFieldsController;
use App\Http\Controllers\Settings\DamanController;
use App\Http\Controllers\Settings\DomainController;
use App\Http\Controllers\Settings\GeneralController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PixelController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\StoreController;
use App\Http\Controllers\Settings\ThemeController;
use App\Http\Controllers\Settings\WhatsappController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    // The store, not the user profile: a merchant opening settings is far more
    // often there to change their storefront than their own password.
    Route::redirect('settings', 'settings/store');

    /*
    | Account and domain, on one screen.
    |
    | The four forms it carries still post to their own endpoints below — this
    | route only renders them together. The old single-purpose pages stay
    | reachable: they are what password-reset mails and a merchant's own
    | bookmarks point at, and breaking those to tidy a sidebar is a bad trade.
    */
    Route::get('settings/general', [GeneralController::class, 'edit'])->name('settings.general');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    // Store identity. POST rather than PATCH: the form carries the logo file,
    // and PHP does not populate uploads on a spoofed PATCH body.
    Route::get('settings/store', [StoreController::class, 'edit'])->name('store.edit');
    Route::post('settings/store', [StoreController::class, 'update'])->name('store.update');

    Route::get('settings/themes', [ThemeController::class, 'edit'])->name('themes.edit');
    Route::put('settings/themes', [ThemeController::class, 'update'])->name('themes.update');

    Route::get('settings/pixels', [PixelController::class, 'edit'])->name('pixels.edit');
    Route::put('settings/pixels', [PixelController::class, 'update'])->name('pixels.update');
    Route::patch('settings/pixels/{pixel}/token', [PixelController::class, 'token'])->name('pixels.token');
    // Sends a real event to Meta and reports the answer.
    Route::post('settings/pixels/{pixel}/test', [PixelController::class, 'test'])->name('pixels.test');

    // Read-only: the feed needs no configuration, only instructions.
    Route::get('settings/catalog', [CatalogController::class, 'edit'])->name('catalog.edit');

    Route::get('settings/checkout', [CheckoutFieldsController::class, 'edit'])->name('checkout.edit');
    Route::put('settings/checkout', [CheckoutFieldsController::class, 'update'])->name('checkout.update');

    // Custom domains. `{domain}` is a StoreDomain id; ownership is checked in
    // the controller, never inferred from the route.
    Route::get('settings/domains', [DomainController::class, 'edit'])->name('domains.edit');
    Route::post('settings/domains', [DomainController::class, 'store'])->name('domains.store');
    Route::post('settings/domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
    Route::post('settings/domains/{domain}/primary', [DomainController::class, 'primary'])->name('domains.primary');
    Route::delete('settings/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

    /*
    | Shipping through Daman. The merchant's carrier contracts live on their
    | Daman account, so all that is configured here is the key that reaches it.
    */
    Route::get('settings/daman', [DamanController::class, 'edit'])->name('daman.edit');
    Route::put('settings/daman', [DamanController::class, 'update'])->name('daman.update');
    Route::patch('settings/daman/toggle', [DamanController::class, 'toggle'])->name('daman.toggle');
    Route::put('settings/daman/webhook', [DamanController::class, 'webhookSecret'])->name('daman.webhook');
    Route::put('settings/daman/pricing', [DamanController::class, 'pricing'])->name('daman.pricing');
    Route::delete('settings/daman', [DamanController::class, 'destroy'])->name('daman.destroy');

    /*
    | The store's own WhatsApp line, for confirming orders.
    |
    | `message` is separate from `update`: the wording changes weekly and the
    | credentials almost never, and asking a merchant to re-paste a token to fix
    | a typo in a sentence is how a screen stops being used.
    */
    Route::get('settings/whatsapp', [WhatsappController::class, 'edit'])->name('whatsapp.edit');
    Route::put('settings/whatsapp', [WhatsappController::class, 'update'])->name('whatsapp.update');
    Route::put('settings/whatsapp/message', [WhatsappController::class, 'message'])->name('whatsapp.message');
    Route::post('settings/whatsapp/test', [WhatsappController::class, 'test'])->name('whatsapp.test');
    Route::patch('settings/whatsapp/toggle', [WhatsappController::class, 'toggle'])->name('whatsapp.toggle');
    Route::delete('settings/whatsapp', [WhatsappController::class, 'destroy'])->name('whatsapp.destroy');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});
