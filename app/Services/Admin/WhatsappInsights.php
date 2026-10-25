<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\StoreUsageEvent;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\PlatformWhatsapp;
use App\Services\Whatsapp\Waha\WahaClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * WhatsApp confirmations across the platform: who is on which channel, which
 * linked numbers have dropped, and how many confirmations went out, were
 * answered or failed.
 *
 * Counts and error messages only. Like the rest of the panel it never shows a
 * customer's phone number or what they were sent — those are the merchant's.
 */
class WhatsappInsights
{
    /** @return array<string,mixed> */
    public function all(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $links = StoreWhatsappIntegration::with('store:id,name,slug,is_demo')->get()
            ->filter(fn (StoreWhatsappIntegration $l) => $l->store && ! $l->store->is_demo);

        return [
            'channels' => [
                'platform' => $links->filter->isPlatform()->count(),
                'waha' => $links->filter->isWaha()->count(),
                'active' => $links->where('is_active', true)->count(),
            ],
            'problems' => $this->problems($links),
            'funnel' => $this->funnel($from, $to),
            'stores' => $this->stores($from, $to, $links),
            'failures' => $this->failures(),
            'billed' => (float) StoreUsageEvent::query()
                ->where('type', StoreUsageEvent::TYPE_WHATSAPP_CONFIRMATION)
                ->whereBetween('occurred_at', [$from, $to])
                ->sum('amount'),
            'meta' => $this->meta(),
        ];
    }

    /**
     * Linked numbers that cannot send right now: a WAHA session that is not
     * WORKING, or any link whose last attempt failed.
     *
     * @return array<int,array<string,mixed>>
     */
    private function problems($links): array
    {
        return $links
            ->filter(fn (StoreWhatsappIntegration $l) => $l->is_active && (
                ($l->isWaha() && $l->credential('status') !== WahaClient::WORKING) || filled($l->last_error)
            ))
            ->map(fn (StoreWhatsappIntegration $l) => [
                'store' => ['id' => $l->store->id, 'name' => $l->store->name],
                'channel' => $l->driver,
                'status' => $l->isWaha() ? ($l->credential('status') ?? '—') : null,
                'error' => $l->last_error,
                'since' => $l->updated_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string,int> */
    private function funnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->window($from, $to)->selectRaw($this->sums())->first();

        return collect(['sent', 'confirmed', 'cancelled', 'awaiting', 'no_reply', 'failed'])
            ->mapWithKeys(fn ($k) => [$k => (int) ($row->{$k} ?? 0)])
            ->all();
    }

    /** @return array<int,array<string,mixed>> busiest first */
    private function stores(CarbonImmutable $from, CarbonImmutable $to, $links): array
    {
        $channels = $links->mapWithKeys(fn ($l) => [$l->store_id => $l->driver]);
        $errors = $links->mapWithKeys(fn ($l) => [$l->store_id => $l->last_error]);

        return $this->window($from, $to)
            ->selectRaw('orders.store_id, ' . $this->sums())
            ->groupBy('orders.store_id')
            ->orderByRaw('sent desc, failed desc')
            ->limit(25)
            ->get()
            ->map(function ($row) use ($channels, $errors) {
                $answered = (int) $row->confirmed + (int) $row->cancelled;

                return [
                    'store_id' => (int) $row->store_id,
                    'channel' => $channels[$row->store_id] ?? null,
                    'sent' => (int) $row->sent,
                    'confirmed' => (int) $row->confirmed,
                    'cancelled' => (int) $row->cancelled,
                    'awaiting' => (int) $row->awaiting + (int) $row->no_reply,
                    'failed' => (int) $row->failed,
                    // Of the ones that went out, how many got any answer.
                    'answer_rate' => (int) $row->sent > 0 ? (int) round($answered / (int) $row->sent * 100) : null,
                    'error' => $errors[$row->store_id] ?? null,
                ];
            })
            ->pipe(function ($rows) {
                $names = DB::table('stores')->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');

                return $rows->map(fn ($r) => [...$r, 'store' => $names[$r['store_id']] ?? '—']);
            })
            ->all();
    }

    /** @return array<int,array<string,mixed>> the latest sends that did not go out */
    private function failures(): array
    {
        return WhatsappMessage::query()
            ->where('direction', WhatsappMessage::DIRECTION_OUT)
            ->where('status', 'failed')
            ->with('store:id,name')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (WhatsappMessage $m) => [
                'id' => $m->id,
                'store' => ['id' => $m->store_id, 'name' => $m->store?->name],
                'error' => Str::limit((string) $m->error, 200),
                'at' => $m->created_at->diffForHumans(),
            ])
            ->all();
    }

    /**
     * The platform number and its one template — Meta refuses every send
     * until the template is APPROVED, so it is the first thing to look at
     * when confirmations on the official number stop. Asked of Meta at most
     * every ten minutes.
     *
     * @return array<string,mixed>
     */
    private function meta(): array
    {
        if (! PlatformWhatsapp::configured()) {
            return ['configured' => false, 'template' => null, 'template_status' => null];
        }

        $status = Cache::get('admin:meta-template-status') ?? $this->askMeta();

        return ['configured' => true, 'template' => PlatformWhatsapp::template(), 'template_status' => $status];
    }

    private function askMeta(): ?string
    {
        $waba = (string) config('services.whatsapp.platform.business_account_id');

        if ($waba === '') {
            return null;
        }

        try {
            $response = Http::withToken((string) config('services.whatsapp.platform.access_token'))
                ->acceptJson()
                ->timeout(4)
                ->get('https://graph.facebook.com/' . config('services.whatsapp.cloud_api_version') . "/{$waba}/message_templates", [
                    'name' => PlatformWhatsapp::template(),
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        // Only an answer is cached; a failed lookup is asked again next time.
        $status = (string) data_get($response->json(), 'data.0.status', 'NOT_FOUND');
        Cache::put('admin:meta-template-status', $status, now()->addMinutes(10));

        return $status;
    }

    /** Orders whose confirmation went out — or failed to — in the window, demo shops excluded. */
    private function window(CarbonImmutable $from, CarbonImmutable $to)
    {
        return Order::query()
            ->join('stores', 'stores.id', '=', 'orders.store_id')
            ->where('stores.is_demo', false)
            ->where(fn ($q) => $q
                ->whereBetween('orders.whatsapp_sent_at', [$from, $to])
                ->orWhere(fn ($q) => $q->where('orders.whatsapp_state', 'failed')->whereBetween('orders.created_at', [$from, $to])));
    }

    private function sums(): string
    {
        return "SUM(orders.whatsapp_sent_at IS NOT NULL) AS sent,
                SUM(orders.whatsapp_state = 'confirmed') AS confirmed,
                SUM(orders.whatsapp_state = 'cancelled') AS cancelled,
                SUM(orders.whatsapp_state = 'sent') AS awaiting,
                SUM(orders.whatsapp_state = 'no_reply') AS no_reply,
                SUM(orders.whatsapp_state = 'failed') AS failed";
    }
}
