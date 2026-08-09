<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Support\ArabicNumerals;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $store = $this->currentStore($request);
        $status = $request->string('status')->toString();

        // Allow-listed so a crafted `sort` cannot order by an arbitrary column.
        $sortable = ['number', 'customer_name', 'governorate', 'total', 'status', 'created_at'];
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'id';
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $orders = $store->orders()
            ->with('items')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->string('gov')->toString(), fn ($q, $gov) => $q->where('governorate', $gov))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%")
                    ->orWhere('number', $term),
            ))
            ->orderBy($sort, $direction)
            ->paginate((int) $request->integer('per_page', 50) ?: 50)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer_name' => $o->customer_name,
                'customer_phone' => $o->customer_phone,
                'customer_phone_alt' => $o->customer_phone_alt,
                'governorate' => $o->governorate,
                'city' => $o->city,
                'address' => $o->address,
                'note' => $o->note,
                'total' => $o->total,
                'status' => $o->status,
                'items_summary' => $o->items
                    ->map(fn ($i) => $i->quantity . '× ' . $i->name . ($i->variant_label ? " ({$i->variant_label})" : ''))
                    ->implode(' · '),
                'created_at' => $o->created_at->format('Y-m-d H:i'),
                'daman' => $this->damanColumn($o),
            ]);

        return Inertia::render('orders/Index', [
            'orders' => $orders,
            'filters' => [
                'status' => $status,
                'q' => $request->string('q')->toString(),
                'gov' => $request->string('gov')->toString(),
                'sort' => $sort,
                'dir' => $direction,
            ],
            'currency' => $store->currency,
            // Drives the "شحن عبر ضمان" button. Absent rather than disabled
            // when the store has not linked an account — an action a merchant
            // cannot take is clutter on the screen they use all day.
            'daman_enabled' => (bool) $store->damanIntegration?->canShip(),
            'counts' => $this->countsByStatus($store),
            'statuses' => collect(Order::STATUSES)
                ->map(fn ($s) => ['value' => $s, 'label' => (new Order(['status' => $s]))->statusLabel()])
                ->all(),
            // Only the governorates this store actually ships to — a full list
            // of 27 is a filter nobody scrolls through.
            'governorates' => $store->orders()
                ->whereNotNull('governorate')
                ->distinct()
                ->orderBy('governorate')
                ->pluck('governorate'),
        ]);
    }

    /**
     * The operations view of a single order.
     *
     * Everything needed to confirm and dispatch it on one screen — this is the
     * page a merchant works from all day, so nothing here should require a
     * second tab.
     */
    public function show(Request $request, Order $order): Response
    {
        $this->authorizeOrder($request, $order);

        $store = $this->currentStore($request);
        $order->load('items');

        return Inertia::render('orders/Show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'status_label' => $order->statusLabel(),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_phone_alt' => $order->customer_phone_alt,
                'customer_email' => $order->customer_email,
                'governorate' => $order->governorate,
                'city' => $order->city,
                'address' => $order->address,
                'note' => $order->note,
                'subtotal' => $order->subtotal,
                'shipping_amount' => $order->shipping_amount,
                'discount_amount' => $order->discount_amount,
                'total' => $order->total,
                'payment_method' => $order->payment_method,
                'ip' => $order->ip,
                'tracking' => $order->tracking,
                'created_at' => $order->created_at->format('Y-m-d H:i'),
                'created_ago' => $order->created_at->diffForHumans(),
                'daman' => $order->isWithDaman() || filled($order->daman_error) ? [
                    'order_number' => $order->daman_order_number,
                    'tracking_number' => $order->daman_tracking_number,
                    'carrier' => $order->daman_carrier_name,
                    'status' => $order->daman_status,
                    'status_note' => $order->daman_status_note,
                    'sent_at' => $order->daman_sent_at?->format('Y-m-d H:i'),
                    'error' => $order->daman_error,
                ] : null,
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->name,
                    'variant_label' => $item->variant_label,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                ]),
            ],
            'currency' => $store->currency,
            'daman_enabled' => (bool) $store->damanIntegration?->canShip(),
            'statuses' => $this->statusOptions(),
            /*
             | How this customer has behaved before, across this store.
             |
             | The single most useful number on a cash-on-delivery order: a
             | buyer who has refused three parcels is a parcel the merchant
             | pays to ship twice. Scoped to the store — one merchant's history
             | is not ours to hand to another.
             */
            'customer_history' => $this->customerHistory($store, $order),
            // Reachable without going back to the list.
            'neighbours' => [
                'prev' => $store->orders()->where('id', '<', $order->id)->max('id'),
                'next' => $store->orders()->where('id', '>', $order->id)->min('id'),
            ],
        ]);
    }

    /**
     * The Daman cell, or nothing at all.
     *
     * Null for an order that was never sent, so the grid can leave the column
     * empty instead of printing a row of dashes.
     *
     * @return array<string,mixed>|null
     */
    private function damanColumn(Order $order): ?array
    {
        if (! $order->isWithDaman() && blank($order->daman_error)) {
            return null;
        }

        return [
            'order_number' => $order->daman_order_number,
            // The carrier's own waybill — the number a courier's call centre
            // recognises, which Daman's order number is not.
            'tracking_number' => $order->daman_tracking_number,
            'carrier' => $order->daman_carrier_name,
            'status_note' => $order->daman_status_note,
            'error' => $order->daman_error,
        ];
    }

    /** @return array<string,mixed> */
    private function customerHistory(Store $store, Order $order): array
    {
        $previous = $store->orders()
            ->where('customer_phone', $order->customer_phone)
            ->whereKeyNot($order->id);

        $total = (clone $previous)->count();
        $delivered = (clone $previous)->where('status', Order::STATUS_DELIVERED)->count();
        $refused = (clone $previous)
            ->whereIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
            ->count();

        return [
            'orders' => $total,
            'delivered' => $delivered,
            'refused' => $refused,
            // Only meaningful once something has actually been settled; a
            // rate out of zero is a number that misleads.
            'delivery_rate' => $delivered + $refused > 0
                ? (int) round($delivered / ($delivered + $refused) * 100)
                : null,
        ];
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);

        $order->update($validated);

        return back()->with('status', 'order-updated');
    }

    /**
     * Edit one cell of one order.
     *
     * Only contact and delivery details. Totals, items and quantities are not
     * here on purpose: they are money and stock, and a spreadsheet-style
     * click-and-type is the wrong safety level for changing either.
     */
    public function update(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $validated = $request->validate([
            'customer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'required', 'string', 'max:32'],
            'customer_phone_alt' => ['sometimes', 'nullable', 'string', 'max:32'],
            'governorate' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'required', Rule::in(Order::STATUSES)],
        ], [
            'customer_name.required' => 'الاسم ماينفعش يفضل فاضي.',
            'customer_phone.required' => 'رقم التليفون ماينفعش يفضل فاضي.',
        ]);

        // Phones are normalised on the way in from the storefront; a merchant
        // typing one by hand deserves the same treatment.
        foreach (['customer_phone', 'customer_phone_alt'] as $field) {
            if (isset($validated[$field]) && $validated[$field] !== null) {
                $validated[$field] = ArabicNumerals::digitsOnly($validated[$field]);
            }
        }

        $order->update($validated);

        return back(fallback: route('orders.index'))->with('status', 'order-updated');
    }

    /**
     * Move several orders at once.
     *
     * The single most-used action on this screen: a merchant confirms a
     * morning's orders in one pass, not one dialog at a time.
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);

        // Scoped to the merchant's own store rather than trusting the posted
        // ids — a crafted list would otherwise reach another store's orders.
        $store->orders()
            ->whereIn('id', $validated['ids'])
            ->update(['status' => $validated['status']]);

        return back()->with('status', 'orders-updated');
    }

    /**
     * Printable waybills for one or more orders.
     *
     * A GET so it opens in a tab and the browser's own print dialog does the
     * work — no PDF library, and the merchant keeps the paper size and printer
     * settings they already use.
     */
    public function waybills(Request $request): View
    {
        $store = $this->currentStore($request);

        $ids = collect(explode(',', $request->string('ids')->toString()))
            ->filter(fn ($id) => is_numeric($id))
            ->take(200);

        abort_if($ids->isEmpty(), 404);

        return view('dashboard.waybill', [
            'store' => $store,
            // Scoped to the merchant's own store: the ids arrive in a URL that
            // anyone signed in could edit by hand.
            'orders' => $store->orders()
                ->with('items')
                ->whereIn('id', $ids)
                ->orderBy('number')
                ->get(),
        ]);
    }

    /**
     * Orders as a spreadsheet.
     *
     * CSV with a UTF-8 BOM rather than a real .xlsx: Excel on Windows assumes
     * the system codepage without it and turns every Arabic name into mojibake,
     * which is exactly the file a merchant would forward to a courier.
     */
    public function export(Request $request): StreamedResponse
    {
        $store = $this->currentStore($request);
        $status = $request->string('status')->toString();

        $filename = 'orders-' . $store->slug . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($store, $status, $request) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'رقم الطلب', 'التاريخ', 'العميل', 'الموبايل', 'موبايل بديل',
                'المحافظة', 'المنطقة', 'العنوان', 'المنتجات', 'الإجمالي',
                'طريقة الدفع', 'الحالة', 'ملاحظات',
            ]);

            // Chunked: a store with 50k orders must not build the whole file in
            // memory before the download starts.
            $store->orders()
                ->with('items')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->string('gov')->toString(), fn ($q, $gov) => $q->where('governorate', $gov))
                ->orderBy('number')
                ->chunk(500, function ($orders) use ($out) {
                    foreach ($orders as $order) {
                        fputcsv($out, [
                            $order->number,
                            $order->created_at->format('Y-m-d H:i'),
                            $order->customer_name,
                            $order->customer_phone,
                            $order->customer_phone_alt,
                            $order->governorate,
                            $order->city,
                            $order->address,
                            $order->items->map(fn ($i) => "{$i->quantity}× {$i->name}"
                                . ($i->variant_label ? " ({$i->variant_label})" : ''))->implode(' | '),
                            $order->total,
                            $order->payment_method,
                            $order->statusLabel(),
                            $order->note,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<int,array{value:string,label:string}> */
    private function statusOptions(): array
    {
        return collect(Order::STATUSES)
            ->map(fn ($status) => [
                'value' => $status,
                'label' => (new Order(['status' => $status]))->statusLabel(),
            ])
            ->all();
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless(
            $request->user()->stores()->whereKey($order->store_id)->exists(),
            403,
        );
    }

    /** Tab counters. One grouped query rather than one per status. */
    private function countsByStatus(Store $store): array
    {
        $counts = $store->orders()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(Order::STATUSES)
            ->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])
            ->put('all', (int) $counts->sum())
            ->all();
    }

    private function currentStore(Request $request): Store
    {
        return $request->user()->currentStore();
    }
}
