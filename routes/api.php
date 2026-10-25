<?php

use App\Http\Controllers\Api\DamanWebhookController;
use App\Http\Controllers\Api\PlatformWhatsappWebhookController;
use App\Http\Controllers\Api\TlsCheckController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — /api/*
|--------------------------------------------------------------------------
| Controllers here are thin adapters over the same Actions the dashboard uses,
| so the mobile app never needs a second implementation of any rule.
*/

/*
| Asked by the edge proxy before it issues a certificate for an unknown
| hostname. Answering 200 for anything would let a stranger point DNS at us and
| burn through the CA's rate limit, so it only ever confirms hostnames a
| merchant has actually attached.
|
| Unauthenticated on purpose — the proxy calls it on the request path — but
| rate-limited and side-effect free.
*/
Route::get('tls/check', TlsCheckController::class)
    ->middleware('throttle:120,1')
    ->name('api.tls.check');

/*
| Parcel status pushed by Daman.
|
| Unauthenticated in the session sense — Daman has no login here — but the
| token in the path names the store and the body is HMAC-signed, and the
| controller refuses anything that fails either check. Rate-limited generously:
| a store dispatching a few hundred parcels a day generates a burst of these
| whenever a courier syncs.
*/
Route::post('integrations/daman/webhook/{token}', DamanWebhookController::class)
    ->middleware('throttle:600,1')
    ->name('api.daman.webhook');

/*
| Everything on a store's own WhatsApp number, pushed by our WAHA server:
| messages, poll votes, ticks and the session's status. The platform's
| official number has its own webhook below.
*/
Route::post('integrations/whatsapp/webhook/{token}', [WhatsappWebhookController::class, 'handle'])
    ->middleware('throttle:600,1')
    ->name('api.whatsapp.webhook');

/*
| Replies to the platform's own WhatsApp number, for every store that uses it.
|
| One fixed URL, set once in the Meta app. No token in the path: nothing here
| names a store, and the body is trusted only on Meta's signature.
*/
Route::get('integrations/whatsapp/platform', [PlatformWhatsappWebhookController::class, 'verify'])
    ->middleware('throttle:60,1')
    ->name('api.whatsapp.platform.verify');

Route::post('integrations/whatsapp/platform', [PlatformWhatsappWebhookController::class, 'handle'])
    ->middleware('throttle:1200,1')
    ->name('api.whatsapp.platform.webhook');
