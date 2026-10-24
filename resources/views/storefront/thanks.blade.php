@extends('storefront.layout')

@section('title', 'تم استلام طلبك — ' . $store->name)

@section('content')
{{-- The confirmation, shaped like the sheet Apple Pay leaves behind: a check
     that lands with a small spring, one sentence of what happens next, and
     the receipt as a grouped list. The customer's next real moment is a phone
     call, so the phone number they gave is the most prominent fact here. --}}
<div class="mx-auto max-w-md px-4 pb-10 pt-10 md:pt-16">
    <div class="text-center">
        <span class="animate-ios-pop mx-auto flex size-[72px] items-center justify-center rounded-full bg-success text-success-foreground shadow-[0_10px_30px_-10px_hsl(var(--success)/0.7)]">
            <svg class="size-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
        </span>

        <h1 class="animate-ios-rise mt-6 text-large-title">وصلنا طلبك</h1>

        <p class="animate-ios-rise mx-auto mt-2 max-w-xs text-callout text-muted-foreground [animation-delay:80ms]">
            هنكلمك على <span class="tabular font-semibold text-foreground" dir="ltr">{{ $order->customer_phone }}</span>
            علشان نأكّد الطلب قبل الشحن.
        </p>
    </div>

    <div class="animate-ios-rise mt-8 space-y-6 [animation-delay:140ms]">
        <div class="ios-group">
            <div class="ios-row justify-between">
                <span class="text-[15px] text-muted-foreground">رقم الطلب</span>
                <span class="tabular text-[17px] font-bold">#{{ $order->number }}</span>
            </div>

            @foreach ($order->items as $item)
                <div class="ios-row items-start justify-between">
                    <span class="text-[15px] leading-snug">
                        {{ $item->name }}
                        @if ($item->variant_label)
                            <span class="text-muted-foreground">({{ $item->variant_label }})</span>
                        @endif
                        <span class="tabular text-muted-foreground">× {{ $item->quantity }}</span>
                    </span>
                    <span class="tabular shrink-0 text-[15px]">{{ number_format((float) $item->total, 2) }}</span>
                </div>
            @endforeach

            @foreach ($order->items->pluck('offer_label')->filter()->unique() as $offerLabel)
                <div class="ios-row justify-between gap-3">
                    <span class="text-[15px] text-muted-foreground">العرض</span>
                    <span class="text-[15px] font-medium">{{ $offerLabel }}</span>
                </div>
            @endforeach

            @if ((float) $order->shipping_amount > 0)
                <div class="ios-row justify-between">
                    <span class="text-[15px] text-muted-foreground">الشحن ({{ $order->governorate }})</span>
                    <span class="tabular shrink-0 text-[15px]">{{ number_format((float) $order->shipping_amount, 2) }}</span>
                </div>
            @endif

            <div class="ios-row justify-between">
                <span class="text-[15px] text-muted-foreground">الإجمالي</span>
                <span class="tabular text-[20px] font-bold tracking-tight">
                    {{ number_format((float) $order->total, 2) }} <span class="text-[13px] font-medium text-muted-foreground">{{ $store->currency }}</span>
                </span>
            </div>

            <div class="ios-row justify-between">
                <span class="text-[15px] text-muted-foreground">طريقة الدفع</span>
                <span class="text-[15px] font-medium">الدفع عند الاستلام</span>
            </div>
        </div>

        <a href="{{ route('storefront.home') }}" class="ios-btn-lg ios-btn-tinted w-full">
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
