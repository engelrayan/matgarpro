@extends('storefront.layout')

@section('title', 'تم استلام طلبك — ' . $store->name)

@section('content')
<div class="mx-auto max-w-lg px-5 py-16">
    <div class="surface-lux p-8 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success/10 text-success">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
        </span>

        <h1 class="mt-6 text-2xl font-bold tracking-tight">وصلنا طلبك</h1>

        <p class="mt-3 text-sm text-muted-foreground">
            هنكلمك على <span class="font-medium text-foreground" dir="ltr">{{ $order->customer_phone }}</span>
            علشان نأكّد الطلب قبل الشحن.
        </p>

        <div class="mt-7 rounded-xl bg-muted/60 p-4 text-right">
            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">رقم الطلب</span>
                <span class="tabular font-bold">#{{ $order->number }}</span>
            </div>

            <hr class="my-3 border-border">

            @foreach ($order->items as $item)
                <div class="flex items-start justify-between gap-3 py-1 text-sm">
                    <span>
                        {{ $item->name }}
                        @if ($item->variant_label)
                            <span class="text-muted-foreground">({{ $item->variant_label }})</span>
                        @endif
                        <span class="text-muted-foreground">× {{ $item->quantity }}</span>
                    </span>
                    <span class="tabular shrink-0">{{ number_format((float) $item->total, 2) }}</span>
                </div>
            @endforeach

            <hr class="my-3 border-border">

            <div class="flex items-center justify-between">
                <span class="text-sm text-muted-foreground">الإجمالي</span>
                <span class="tabular text-lg font-bold">
                    {{ number_format((float) $order->total, 2) }} {{ $store->currency }}
                </span>
            </div>

            <p class="mt-2 text-xs text-muted-foreground">الدفع عند الاستلام</p>
        </div>

        <a href="{{ route('storefront.home') }}" class="btn-outline mt-7 w-full">
            ارجع للمتجر
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
/*
 * Browser-side Purchase.
 *
 * `eventID` is the same string the queued Conversions API job sends, derived
 * from the order id on both sides. Meta uses it to recognise the two copies as
 * one conversion — without it the merchant sees every sale twice and optimises
 * against numbers that are double the truth.
 *
 * Both copies are sent on purpose: the browser one arrives instantly, the
 * server one arrives even when an ad blocker eats this script.
 */
if (window.fbq) {
    const purchase = @json($purchaseEvent);

    fbq('track', 'Purchase', {
        currency: purchase.currency,
        value: purchase.value,
        contents: purchase.contents,
        content_type: 'product',
        num_items: purchase.num_items,
    }, {
        eventID: purchase.event_id,
    });
}
</script>
@endpush
