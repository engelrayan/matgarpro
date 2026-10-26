<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CarrierCollection;
use App\Services\Shipping\CarrierLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الشحن والتحصيل: shipments by carrier, the money each one holds, and the
 * collections the merchant records when a carrier pays.
 */
class ShipmentController extends Controller
{
    public function __construct(private readonly CarrierLedger $ledger) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->currentStore();
        $carriers = $this->ledger->carriers($store);

        return Inertia::render('shipments/Index', [
            'carriers' => $carriers,
            'totals' => $this->ledger->totals($carriers),
            'collections' => CarrierCollection::query()
                ->where('store_id', $store->id)
                ->latest('received_on')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (CarrierCollection $c) => [
                    'id' => $c->id,
                    'carrier' => $c->carrier,
                    'amount' => (float) $c->amount,
                    'expected_amount' => (float) $c->expected_amount,
                    'difference' => $c->difference(),
                    'orders_count' => $c->orders_count,
                    'received_on' => $c->received_on->toDateString(),
                    'reference' => $c->reference,
                    'note' => $c->note,
                ]),
            'currency' => $store->currency,
            'manualKey' => CarrierLedger::MANUAL,
            'damanLinked' => (bool) $store->damanIntegration()->exists(),
        ]);
    }

    /** The delivered, unpaid orders of one carrier, for the collection form. */
    public function due(Request $request): JsonResponse
    {
        $carrier = (string) $request->query('carrier', '');
        abort_if($carrier === '', 404);

        return response()->json([
            'orders' => $this->ledger->due($request->user()->currentStore(), $carrier)
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    // Hidden until a top-up, here as everywhere.
                    'customer_name' => $o->isLocked() ? 'طلب مخفي' : $o->customer_name,
                    'governorate' => $o->governorate,
                    'total' => (float) $o->total,
                    'tracking' => $o->daman_tracking_number,
                    'delivered_on' => $o->updated_at?->toDateString(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'carrier' => ['required', 'string', 'max:120'],
            'order_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'order_ids.*' => ['integer'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'order_ids.required' => 'اختار الطلبات اللي الفلوس دي عنها.',
            'amount.required' => 'اكتب المبلغ اللي وصلك.',
            'received_on.before_or_equal' => 'التاريخ مايكونش في المستقبل.',
        ]);

        $collection = $this->ledger->record(
            $request->user()->currentStore(),
            $request->user(),
            $data['carrier'],
            array_map('intval', $data['order_ids']),
            (float) $data['amount'],
            $data['received_on'],
            $data['reference'] ?? null,
            $data['note'] ?? null,
        );

        return back()->with('status', 'collection-recorded')->with('collection_id', $collection->id);
    }

    public function destroy(Request $request, CarrierCollection $collection): RedirectResponse
    {
        abort_unless($collection->store_id === $request->user()->currentStore()->id, 404);

        $this->ledger->undo($collection);

        return back()->with('status', 'collection-undone');
    }
}
