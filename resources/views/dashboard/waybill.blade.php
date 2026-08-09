<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>بوليصة شحن — {{ $orders->count() }} طلب</title>

    <style>
        /*
         | Self-contained CSS, not the app stylesheet.
         |
         | This page is printed, and print is the one place where the design
         | system's theme tokens are wrong: a printer has no dark mode, ink
         | costs money, and a coloured panel comes out as a grey smear.
         */
        @page { size: A5; margin: 8mm; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #000;
            background: #fff;
        }

        .waybill {
            border: 2px solid #000;
            padding: 10px;
            /* One label per sheet: a courier tears these apart. */
            page-break-after: always;
        }

        .waybill:last-child { page-break-after: auto; }

        .row { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .between { border-bottom: 1px solid #000; padding-bottom: 6px; margin-bottom: 8px; }

        .store { font-size: 15px; font-weight: 700; }
        .muted { color: #444; font-size: 10px; }

        .number { font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }

        .block { margin-bottom: 8px; }
        .label { font-size: 9px; font-weight: 700; text-transform: uppercase; color: #444; }

        /* The two things a courier reads from arm's length. */
        .name { font-size: 15px; font-weight: 700; }
        .phone { font-size: 17px; font-weight: 700; direction: ltr; text-align: right; letter-spacing: 0.5px; }

        .address { font-size: 12px; }

        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: right; font-size: 10px; }
        th { background: #eee; font-weight: 700; }

        .cod {
            border: 2px solid #000;
            padding: 8px;
            margin-top: 8px;
            text-align: center;
        }

        .cod-amount { font-size: 24px; font-weight: 700; }

        .note { margin-top: 6px; font-size: 10px; border-top: 1px dashed #000; padding-top: 4px; }

        .sign { display: flex; justify-content: space-between; margin-top: 14px; font-size: 9px; }
        .sign span { border-top: 1px solid #000; padding-top: 3px; width: 45%; text-align: center; }

        @media screen {
            body { background: #f4f4f5; padding: 20px; }
            .waybill { max-width: 420px; margin: 0 auto 20px; background: #fff; }
            .print-bar { max-width: 420px; margin: 0 auto 16px; text-align: center; }
            .print-bar button {
                font: inherit; font-weight: 600; cursor: pointer;
                padding: 10px 24px; border: 0; border-radius: 8px;
                background: #0f766e; color: #fff;
            }
        }

        @media print { .print-bar { display: none; } }
    </style>
</head>
<body>

<div class="print-bar">
    <button onclick="window.print()">اطبع {{ $orders->count() }} بوليصة</button>
</div>

@foreach ($orders as $order)
    <div class="waybill">
        <div class="row between">
            <div>
                <div class="store">{{ $store->name }}</div>
                <div class="muted">{{ $store->canonicalHost() }}</div>
            </div>
            <div style="text-align:left">
                <div class="number">#{{ $order->number }}</div>
                <div class="muted">{{ $order->created_at->format('Y-m-d') }}</div>
            </div>
        </div>

        <div class="block">
            <div class="label">المرسل إليه</div>
            <div class="name">{{ $order->customer_name }}</div>
            <div class="phone">{{ $order->customer_phone }}</div>
            @if ($order->customer_phone_alt)
                <div class="phone">{{ $order->customer_phone_alt }}</div>
            @endif
        </div>

        <div class="block">
            <div class="label">العنوان</div>
            <div class="address">
                {{ collect([$order->governorate, $order->city])->filter()->implode(' — ') }}
            </div>
            <div class="address">{{ $order->address }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th style="width:15%">الكمية</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->name }}
                            @if ($item->variant_label)
                                <span class="muted">({{ $item->variant_label }})</span>
                            @endif
                        </td>
                        <td>{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- The number the courier collects. Biggest thing on the label after
             the phone, because getting it wrong costs the merchant the sale. --}}
        <div class="cod">
            <div class="label">المطلوب تحصيله — الدفع عند الاستلام</div>
            <div class="cod-amount">{{ number_format((float) $order->total, 2) }} {{ $store->currency }}</div>
        </div>

        @if ($order->note)
            <div class="note"><strong>ملاحظة:</strong> {{ $order->note }}</div>
        @endif

        <div class="sign">
            <span>توقيع المستلم</span>
            <span>توقيع المندوب</span>
        </div>
    </div>
@endforeach

</body>
</html>
