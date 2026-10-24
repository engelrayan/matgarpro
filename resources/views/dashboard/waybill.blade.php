<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بوالص الشحن — {{ $orders->count() }} طلب</title>
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700" rel="stylesheet" />

    @php
        /*
         | Three sizes, one template. The paper is set per size with @page —
         | the browser's own print dialog then needs no fiddling — and every
         | measurement below is in the label's own scale (--s), so the 10×15
         | sticker is the A5 label shrunk, not a second design to keep in step.
         */
        $page = [
            'a4' => ['size' => 'A4', 'margin' => '8mm', 'scale' => '0.92', 'height' => '136mm'],
            'a5' => ['size' => 'A5', 'margin' => '7mm', 'scale' => '1', 'height' => 'auto'],
            // Narrower than A5 but taller for its width: the text stays near
            // full size and the label uses its height rather than shrinking.
            'label' => ['size' => '100mm 150mm', 'margin' => '3mm', 'scale' => '1.15', 'height' => '142mm'],
        ][$size];

        $labels = ['a4' => 'A4 — بوليصتين في الورقة', 'a5' => 'A5', 'label' => 'استيكر ١٠×١٥'];
    @endphp

    <style>
        /*
         | Self-contained CSS, not the app stylesheet.
         |
         | This page is printed, and print is the one place where the design
         | system's theme tokens are wrong: a printer has no dark mode, ink
         | costs money, and a coloured panel comes out as a grey smear. Black on
         | white, solid rules, one inverted block for the amount to collect.
         */
        @page { size: {{ $page['size'] }}; margin: {{ $page['margin'] }}; }

        :root { --s: {{ $page['scale'] }}; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif;
            font-size: calc(11px * var(--s));
            line-height: 1.45;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet { display: grid; gap: 6mm; }

        .waybill {
            display: flex;
            flex-direction: column;
            min-height: {{ $page['height'] }};
            border: 1.5px solid #000;
            border-radius: 3mm;
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* One label per page, or two on an A4 sheet: a courier tears these apart. */
        @if ($size === 'a4')
            .waybill:nth-child(2n) { page-break-after: always; break-after: page; }
            .waybill:last-child { page-break-after: auto; break-after: auto; }
        @else
            .waybill { page-break-after: always; break-after: page; }
            .waybill:last-child { page-break-after: auto; break-after: auto; }
        @endif

        .section { padding: calc(8px * var(--s)) calc(10px * var(--s)); }
        .section + .section { border-top: 1px solid #000; }

        /* ── Header ─────────────────────────────────────────────────────── */
        .head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .brand { display: flex; align-items: center; gap: calc(8px * var(--s)); min-width: 0; }
        .brand img { height: calc(30px * var(--s)); width: auto; max-width: calc(80px * var(--s)); object-fit: contain; }
        .store { font-size: calc(15px * var(--s)); font-weight: 700; line-height: 1.2; }
        .host { font-size: calc(9px * var(--s)); color: #333; direction: ltr; text-align: right; }

        .order-no { text-align: left; }
        .order-no .label { text-align: left; }
        .order-no .value { font-size: calc(22px * var(--s)); font-weight: 700; line-height: 1; letter-spacing: -0.5px; direction: ltr; }
        .order-no .date { font-size: calc(9px * var(--s)); color: #333; direction: ltr; }

        .label { font-size: calc(8.5px * var(--s)); font-weight: 700; color: #333; letter-spacing: 0.2px; }

        /* ── Recipient + QR ─────────────────────────────────────────────── */
        .recipient { display: flex; gap: calc(10px * var(--s)); align-items: flex-start; }
        .recipient .who { flex: 1; min-width: 0; }

        /* The two things a courier reads from arm's length. */
        .name { font-size: calc(16px * var(--s)); font-weight: 700; line-height: 1.25; }
        .phones { display: flex; flex-wrap: wrap; gap: 2px calc(12px * var(--s)); margin-top: 2px; }
        .phone { font-size: calc(17px * var(--s)); font-weight: 700; direction: ltr; letter-spacing: 0.4px; }
        .phone.alt { font-weight: 600; font-size: calc(14px * var(--s)); }

        .place { margin-top: calc(6px * var(--s)); }
        .gov { font-size: calc(13px * var(--s)); font-weight: 700; }
        .address { font-size: calc(12px * var(--s)); }

        .qr { width: calc(78px * var(--s)); flex-shrink: 0; text-align: center; }
        .qr svg { width: 100%; height: auto; display: block; }
        .qr .hint { font-size: calc(7.5px * var(--s)); color: #333; margin-top: 2px; }

        /* ── Items ──────────────────────────────────────────────────────── */
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: calc(3px * var(--s)) calc(4px * var(--s)); text-align: right; font-size: calc(10.5px * var(--s)); vertical-align: top; }
        th { font-size: calc(8.5px * var(--s)); color: #333; font-weight: 700; border-bottom: 1px solid #000; }
        td { border-bottom: 1px dotted #888; }
        tr:last-child td { border-bottom: 0; }
        td.qty, th.qty { width: 14%; text-align: center; }
        .variant { color: #333; }

        /* ── Amount to collect ──────────────────────────────────────────── */
        /* The number the courier collects: the one inverted block on the
           label, because getting it wrong costs the merchant the sale. */
        .cod { display: flex; align-items: center; justify-content: space-between; gap: 8px; background: #000; color: #fff; }
        .cod .label { color: #fff; }
        .cod .amount { font-size: calc(22px * var(--s)); font-weight: 700; direction: ltr; line-height: 1.1; }
        .cod .breakdown { font-size: calc(8.5px * var(--s)); opacity: 0.85; }

        .note { font-size: calc(10px * var(--s)); }

        .foot { margin-top: auto; display: flex; justify-content: space-between; gap: 10px; font-size: calc(8.5px * var(--s)); }
        .foot .sign { flex: 1; border-top: 1px solid #000; padding-top: 2px; text-align: center; margin-top: calc(14px * var(--s)); }
        .awb { font-size: calc(9.5px * var(--s)); }
        .awb strong { direction: ltr; display: inline-block; }

        /* ── Screen only ────────────────────────────────────────────────── */
        @media screen {
            body { background: #eceff1; padding: 16px; }
            /* On screen, each size at roughly its printed proportions. */
            .sheet { max-width: {{ $size === 'label' ? '380px' : '560px' }}; margin: 0 auto; }
            .waybill { background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
            .bar {
                max-width: 560px; margin: 0 auto 16px; display: flex; flex-wrap: wrap; gap: 8px;
                align-items: center; justify-content: space-between;
                background: #fff; border-radius: 12px; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.12);
            }
            .sizes { display: flex; gap: 6px; flex-wrap: wrap; }
            .sizes a {
                font-size: 13px; text-decoration: none; color: #333; padding: 6px 10px;
                border: 1px solid #ccc; border-radius: 999px;
            }
            .sizes a.on { border-color: #0f766e; color: #0f766e; font-weight: 600; background: #f0fdfa; }
            .print {
                font: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
                padding: 10px 20px; border: 0; border-radius: 10px; background: #0f766e; color: #fff;
            }
            .empty { max-width: 560px; margin: 40px auto; text-align: center; color: #555; font-size: 15px; }
        }

        @media print { .bar, .empty { display: none; } }
    </style>
</head>
<body>

@if ($orders->isEmpty())
    <p class="empty">مفيش طلبات تطبعلها بوالص هنا.</p>
@else
    <div class="bar">
        <div class="sizes">
            @foreach ($sizes as $option)
                <a href="?ids={{ $ids }}&size={{ $option }}" data-size="{{ $option }}" @class(['on' => $option === $size])>{{ $labels[$option] }}</a>
            @endforeach
        </div>
        <button class="print" onclick="window.print()">اطبع {{ $orders->count() }} بوليصة</button>
    </div>
@endif

<div class="sheet">
@foreach ($orders as $order)
    <div class="waybill">
        {{-- ── Who it is from, and which order ─────────────────────────── --}}
        <div class="section head">
            <div class="brand">
                @if ($store->logoUrl())
                    <img src="{{ $store->logoUrl() }}" alt="">
                @endif
                <div>
                    <div class="store">{{ $store->name }}</div>
                    <div class="host">{{ $store->canonicalHost() }}</div>
                </div>
            </div>
            <div class="order-no">
                <div class="label">رقم الطلب</div>
                <div class="value">#{{ $order->number }}</div>
                <div class="date">{{ $order->created_at->format('Y-m-d') }}</div>
            </div>
        </div>

        {{-- ── Who it is for ─────────────────────────────────────────────── --}}
        <div class="section recipient">
            <div class="who">
                <div class="label">المستلم</div>
                <div class="name">{{ $order->customer_name }}</div>
                <div class="phones">
                    <span class="phone">{{ $order->customer_phone }}</span>
                    @if ($order->customer_phone_alt)
                        <span class="phone alt">{{ $order->customer_phone_alt }}</span>
                    @endif
                </div>
                <div class="place">
                    <div class="gov">{{ collect([$order->governorate, $order->city])->filter()->implode(' — ') }}</div>
                    @if ($order->address)
                        <div class="address">{{ $order->address }}</div>
                    @endif
                </div>
            </div>
            <div class="qr">
                {!! $qr[$order->id] !!}
                <div class="hint">امسح وافتح الطلب</div>
            </div>
        </div>

        {{-- ── What is in the parcel ─────────────────────────────────────── --}}
        <div class="section">
            <table>
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th class="qty">العدد</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                {{ $item->name }}
                                @if ($item->variant_label)
                                    <span class="variant">— {{ $item->variant_label }}</span>
                                @endif
                            </td>
                            <td class="qty">{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                    {{-- Said once, however many sizes the offer was split into. --}}
                    @foreach ($order->items->pluck('offer_label')->filter()->unique() as $offerLabel)
                        <tr>
                            <td colspan="2"><span class="variant">العرض: {{ $offerLabel }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section cod">
            <div>
                <div class="label">المطلوب تحصيله — الدفع عند الاستلام</div>
                {{-- So the customer can see why the amount is more than the
                     price they saw on the ad. --}}
                @if ((float) $order->shipping_amount > 0)
                    <div class="breakdown">
                        المنتجات {{ number_format((float) $order->subtotal, 2) }} + الشحن {{ number_format((float) $order->shipping_amount, 2) }}
                    </div>
                @endif
            </div>
            <div class="amount">{{ number_format((float) $order->total, 2) }} {{ $store->currency }}</div>
        </div>

        @if ($order->note || $order->daman_tracking_number)
            <div class="section">
                @if ($order->note)
                    <div class="note"><strong>ملاحظة:</strong> {{ $order->note }}</div>
                @endif
                @if ($order->daman_tracking_number)
                    <div class="awb">
                        بوليصة {{ $order->daman_carrier_name ?? 'ضمان' }}: <strong>{{ $order->daman_tracking_number }}</strong>
                    </div>
                @endif
            </div>
        @endif

        <div class="section foot">
            <span class="sign">توقيع المستلم</span>
            <span class="sign">توقيع المندوب</span>
        </div>
    </div>
@endforeach
</div>

<script>
    /*
     * Remember the size the merchant last printed in, and open in it next
     * time. Kept in this browser only — it is a property of the printer on
     * this desk, not of the store.
     */
    (function () {
        try {
            const url = new URL(location.href);
            const stored = localStorage.getItem('waybill-size');

            // By id, not by the original request: "unprinted" has just marked
            // these, so asking for it again would find nothing.
            if (!url.searchParams.has('size') && stored && stored !== @json($size) && @json($ids) !== '') {
                location.replace(`?ids=${@json($ids)}&size=${encodeURIComponent(stored)}`);
                return;
            }

            localStorage.setItem('waybill-size', @json($size));
        } catch (e) {
            // Storage blocked: the page still prints, in the default size.
        }
    })();
</script>
</body>
</html>
