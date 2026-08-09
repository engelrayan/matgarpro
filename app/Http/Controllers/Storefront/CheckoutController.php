<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Orders\PlaceOrder;
use App\Exceptions\CheckoutException;
use App\Jobs\SendMetaPurchaseEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Store;
use App\Services\Storefront\FunnelTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Storefront\CartCapture;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PlaceOrder $placeOrder,
        private readonly FunnelTracker $funnel,
        private readonly CartCapture $carts,
    ) {}

    /**
     * Fired by the storefront when the customer starts filling the form.
     *
     * A separate endpoint rather than a flag on the order: the whole point is
     * to count the people who begin and never finish, and those never reach
     * `store()` at all.
     */
    public function start(Request $request, Store $store): Response
    {
        $product = $store->products()->find($request->integer('product_id'));

        $this->funnel->checkoutStart($request, $store, $product);

        // Same beacon carries whatever they have typed so far. One endpoint
        // rather than two: the browser is already calling this on the first
        // keystroke, and a second request per field would be traffic the
        // merchant pays for.
        $this->carts->remember($request, $store, $request->all());

        return response()->noContent();
    }

    public function store(CheckoutRequest $request, Store $store): RedirectResponse
    {
        $data = $request->validated();

        try {
            $order = $this->placeOrder->handle(
                store: $store,
                lines: [[
                    'product_id' => $data['product_id'],
                    'variant_id' => $data['variant_id'] ?? null,
                    'quantity' => $data['quantity'],
                ]],
                customer: [
                    ...$data,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 512),
                    // Read here and stored on the order: the Conversions API
                    // call runs in a queued job that has no access to the
                    // customer's cookies, and these two are the strongest
                    // match signals Meta has.
                    'tracking' => [
                        'fbp' => $request->cookie('_fbp'),
                        'fbc' => $request->cookie('_fbc'),
                        'source_url' => $request->headers->get('referer'),
                    ],
                ],
            );
        } catch (CheckoutException $e) {
            // Back to the product page with the reason on the form, not a 500.
            throw ValidationException::withMessages(['checkout' => $e->getMessage()]);
        }

        $this->funnel->order(
            $request,
            $store,
            $store->products()->find($data['product_id']),
        );

        // Close the draft this order came from, so the merchant never chases a
        // customer about an order they already placed.
        $this->carts->recover($request, $store, $order);

        return redirect()->route('storefront.thanks', $order->id);
    }

    public function thanks(Store $store, int $order): View
    {
        $order = $store->orders()->with('items')->find($order);

        if (! $order) {
            throw new NotFoundHttpException();
        }

        return view('storefront.thanks', [
            'store' => $store,
            'order' => $order,
            /*
             | Built here, not in the template. Blade's `@json` parses its
             | argument by scanning for the closing paren, so an array literal
             | inside an arrow function breaks the compiled view with a parse
             | error — at runtime, on the page that confirms a sale.
             */
            'purchaseEvent' => [
                'event_id' => SendMetaPurchaseEvent::eventIdFor($order),
                'currency' => $store->currency,
                'value' => (float) $order->total,
                'num_items' => (int) $order->items->sum('quantity'),
                'contents' => $order->items->map(fn ($item) => [
                    'id' => (string) ($item->product_id ?? $item->id),
                    'quantity' => $item->quantity,
                    'item_price' => (float) $item->unit_price,
                ])->all(),
            ],
        ]);
    }
}
