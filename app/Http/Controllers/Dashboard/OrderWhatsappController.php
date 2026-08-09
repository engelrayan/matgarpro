<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "ابعت التأكيد" from the order screen.
 *
 * The manual counterpart to the automatic send: for a store that leaves
 * auto-send off, for an order whose first message failed, and for the customer
 * who never answered and is worth one more try.
 *
 * Sent inline rather than queued — the merchant is looking at the screen and
 * has asked for an answer now, including when the answer is why it failed.
 */
class OrderWhatsappController extends Controller
{
    public function __construct(private readonly WhatsappSender $sender) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless(
            $request->user()->stores()->whereKey($order->store_id)->exists(),
            403,
        );

        $store = $request->user()->currentStore();
        $link = $store->whatsappIntegration;

        if (! $link?->canSend()) {
            return back()->with('error', 'اربط واتساب الأول من الإعدادات.');
        }

        // Cleared so the sender treats this as a fresh attempt rather than a
        // message already on its way.
        $order->forceFill(['whatsapp_state' => null, 'whatsapp_error' => null])->save();

        $result = $this->sender->sendConfirmation($link, $order->fresh());

        return back()->with(
            $result['ok'] ? 'status' : 'error',
            $result['ok'] ? 'الرسالة اتبعتت للعميل.' : ($result['error'] ?? 'الرسالة ماتبعتتش.'),
        );
    }
}
