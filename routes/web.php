<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Marketing site. Blade rather than Inertia: it is the page ad traffic lands
// on, so it ships no application JS at all.
Route::view('/', 'marketing.home')->name('home');

// Living style guide. Kept in the app (not a static export) so it breaks loudly
// the moment a design token is renamed or removed.
Route::view('design', 'design-system')->name('design-system');

Route::get('dashboard', \App\Http\Controllers\Dashboard\OverviewController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
// Platform panel. Same host as the dashboard, its own guard and its own login.
require __DIR__.'/admin.php';
