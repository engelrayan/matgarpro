<?php

namespace App\Services\Pixels;

use App\Models\Order;
use App\Models\Store;
use App\Models\StorePixel;
use Illuminate\Support\Facades\Cache;

/**
 * Tells a merchant whether their tracking is actually working.
 *
 * Two questions the platforms themselves answer badly:
 *
 *  1. "Is the connection alive?" — answered by sending a real test event and
 *     reporting what Meta said, not by checking that a field is filled in.
 *  2. "How good is my match quality?" — Meta grades this in Events Manager
 *     days later and never says how to fix it. We can compute the same thing
 *     from our own orders today, and we know exactly which switch improves it.
 */
class PixelDiagnostics
{
    public function __construct(private readonly MetaConversionsApi $api) {}

    /**
     * Send a real test event and report Meta's answer.
     *
     * A genuine round-trip, not a format check: a token can be well-formed and
     * still revoked, and that is the case a merchant actually hits.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(StorePixel $pixel): array
    {
        if (! $pixel->canSendServerSide()) {
            return [
                'ok' => false,
                'message' => 'محتاج توكن Conversions API الأول عشان نقدر نختبر الاتصال.',
            ];
        }

        $result = $this->api->send($pixel, [
            'event_name' => 'PageView',
            'event_time' => now()->timestamp,
            // Prefixed so it is obvious in Events Manager where this came from,
            // and so it can never collide with a real order's event id.
            'event_id' => 'matgarpro-test-' . uniqid(),
            'action_source' => 'website',
            'event_source_url' => $pixel->store->canonicalUrl(),
            // Named as ourselves: this is not a real visitor, and labelling it
            // as one would put a fake person in the merchant's audience.
            'user_data' => UserData::build(userAgent: 'MatgarPro/1.0 (connection test)'),
        ]);

        $pixel->forceFill([
            'last_error' => $result['ok'] ? null : $result['error'],
            'last_event_at' => $result['ok'] ? now() : $pixel->last_event_at,
        ])->save();

        return [
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'الاتصال شغّال ✓ — ميتا استلمت الحدث. تقدر تشوفه في Events Manager خلال دقايق.'
                : 'ميتا رفضت الحدث: ' . $result['error'],
        ];
    }

    /**
     * Estimate Event Match Quality from the store's own recent orders.
     *
     * Meta scores conversions by how many identifying parameters arrive with
     * them; more parameters means more sales it can attribute back to an ad,
     * which is the difference between a campaign that looks unprofitable and
     * one that is.
     *
     * Computed from what we WOULD send for the last 100 orders, so the advice
     * is about this store's actual data rather than a generic checklist.
     *
     * @return array<string,mixed>
     */
    public function matchQuality(Store $store): array
    {
        return Cache::remember("emq:{$store->id}", now()->addMinutes(10), function () use ($store) {
            $orders = $store->orders()
                ->latest('id')
                ->limit(100)
                ->get(['customer_name', 'customer_phone', 'customer_email', 'governorate', 'city', 'tracking']);

            if ($orders->isEmpty()) {
                return ['available' => false];
            }

            $signals = [
                'phone' => [
                    'label' => 'رقم الموبايل',
                    // Weighted by how much Meta actually leans on each one.
                    'weight' => 30,
                    'have' => $orders->filter(fn (Order $o) => filled($o->customer_phone))->count(),
                    'fix' => null,
                ],
                'name' => [
                    'label' => 'الاسم',
                    'weight' => 15,
                    'have' => $orders->filter(fn (Order $o) => filled($o->customer_name))->count(),
                    'fix' => null,
                ],
                'email' => [
                    'label' => 'البريد الإلكتروني',
                    'weight' => 30,
                    'have' => $orders->filter(fn (Order $o) => filled($o->customer_email))->count(),
                    'fix' => 'شغّل حقل البريد في فورم الطلب — أقوى إشارة مطابقة بعد الموبايل.',
                    'fix_url' => '/settings/checkout',
                ],
                'city' => [
                    'label' => 'المدينة والمحافظة',
                    'weight' => 10,
                    'have' => $orders->filter(fn (Order $o) => filled($o->governorate) || filled($o->city))->count(),
                    'fix' => 'شغّل حقل المحافظة في فورم الطلب.',
                    'fix_url' => '/settings/checkout',
                ],
                'fbc' => [
                    'label' => 'معرّف نقرة الإعلان (fbc)',
                    'weight' => 15,
                    'have' => $orders->filter(fn (Order $o) => filled(data_get($o->tracking, 'fbc')))->count(),
                    'fix' => 'ده بيتسجّل لوحده من إعلانات فيسبوك. لو صفر، غالبًا إعلاناتك لسه ماجابتش طلبات.',
                ],
            ];

            $total = $orders->count();
            $score = 0;

            foreach ($signals as $key => $signal) {
                $coverage = $signal['have'] / $total;
                $score += $signal['weight'] * $coverage;
                $signals[$key]['coverage'] = (int) round($coverage * 100);
            }

            return [
                'available' => true,
                'orders_sampled' => $total,
                'score' => (int) round($score),
                'signals' => array_values($signals),
            ];
        });
    }
}
