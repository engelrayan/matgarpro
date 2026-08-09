<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\StoreDomain;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The account and domain settings, on one screen.
 *
 * They used to be four separate pages — profile, password, appearance, domain
 * — each holding one small form and each costing a navigation to reach. A
 * merchant changing their password and then their domain paid for two page
 * loads and two rounds of finding the right link in a sidebar of eleven items.
 *
 * The forms still post to their original endpoints. This controller only
 * gathers what they need to render; nothing about how a password is changed
 * moved, which is what keeps a screen this size from becoming a screen nobody
 * dares to touch.
 */
class GeneralController extends Controller
{
    public function edit(Request $request): Response
    {
        $store = $request->user()->currentStore();

        return Inertia::render('settings/General', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),

            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description,
                'logo_url' => $store->logoUrl(),
                'platform_host' => $store->platformHost(),
                'canonical_url' => $store->canonicalUrl(),
            ],

            'domains' => $store->domains()->orderByDesc('is_primary')->orderBy('id')->get()
                ->map(fn (StoreDomain $d) => [
                    'id' => $d->id,
                    'domain' => $d->domain,
                    'status' => $d->status,
                    'is_primary' => $d->is_primary,
                    'is_apex' => $d->isApex(),
                    'last_error' => $d->last_error,
                    'last_checked_at' => $d->last_checked_at?->diffForHumans(),
                    'verified_at' => $d->verified_at?->toDateTimeString(),
                    'instructions' => $d->dnsInstructions(),
                    'ssl_status' => $d->ssl_status,
                    'ssl_message' => $d->sslMessage(),
                    'is_secure' => $d->isSecure(),
                ]),
        ]);
    }
}
