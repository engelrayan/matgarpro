<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\WhatsappInsights;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** WhatsApp confirmations across the platform — see WhatsappInsights. */
class WhatsappController extends Controller
{
    public function index(Request $request, WhatsappInsights $insights): Response
    {
        [$from, $to, $range] = OverviewController::window($request);

        return Inertia::render('admin/Whatsapp', [
            'range' => $range,
            'insights' => $insights->all($from, $to),
            'currency' => config('billing.currency'),
        ]);
    }
}
