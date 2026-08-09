<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Orders\ShipOrdersViaDaman;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "شحن عبر ضمان" from the orders grid.
 *
 * The other half of the bulk bar: a merchant confirms the morning's orders in
 * one pass, ticks them, and hands the batch over. One request for the whole
 * selection, because one dialog per parcel is how a merchant stops using it.
 */
class DamanShipmentController extends Controller
{
    public function __construct(private readonly ShipOrdersViaDaman $shipper) {}

    public function store(Request $request): RedirectResponse
    {
        $store = $request->user()->currentStore();

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $result = $this->shipper->handle($store, $validated['ids']);

        // Carried whole rather than flattened into a sentence: the page shows
        // the failures per order, and a merchant fixing three addresses needs
        // to see which three.
        return back()->with('daman_result', $result);
    }
}
