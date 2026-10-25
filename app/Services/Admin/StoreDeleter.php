<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AiGeneration;
use App\Models\CustomerReputation;
use App\Models\Store;
use App\Services\Customers\ReputationService;
use App\Services\Whatsapp\Waha\WahaClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a store for good: its rows, its files, its WhatsApp session.
 *
 * Permanent rather than the model's soft delete. A soft-deleted store keeps
 * every customer's name, phone and address in the database — the opposite of
 * what the privacy page promises a merchant who asks to be removed — and
 * keeps its sub-domain taken. So the operator types the store's slug to
 * confirm, and only a super admin can do it at all.
 *
 * The database does most of it: every table that belongs to a store is
 * declared ON DELETE CASCADE. What it cannot do is here — files on disk, the
 * WAHA session on our gateway, and the network delivery record of the
 * customers this store shipped to, which has to be counted again without it.
 * The merchant's own account stays; they may have other stores.
 */
class StoreDeleter
{
    /** The public-disk folders a store's uploads and generated images live in. */
    private const PUBLIC_DIRECTORIES = ['stores/%d', 'samples/%d', 'demo/%d', 'builder/%d'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ReputationService $reputation,
        private readonly WahaClient $waha,
    ) {}

    /** @return array{orders: int, products: int} what went with it */
    public function delete(Store $store, Admin $admin): array
    {
        $counts = [
            'orders' => $store->orders()->withTrashed()->count(),
            'products' => $store->products()->withTrashed()->count(),
        ];
        $owner = $store->user;

        // Read before the cascade takes the entries with it.
        $reputationIds = DB::table('customer_reputation_entries')
            ->where('store_id', $store->id)
            ->distinct()
            ->pluck('customer_reputation_id');
        $generationIds = AiGeneration::where('store_id', $store->id)->pluck('id');

        // Unlink the phone from our gateway first: once the row is gone,
        // nothing knows the session belongs to anyone.
        $link = $store->whatsappIntegration;
        if ($link?->isWaha()) {
            $this->waha->disconnect($link);
        }

        DB::transaction(function () use ($store, $admin, $counts, $owner) {
            $this->audit->log(
                action: 'store.deleted',
                summary: "مسح متجر «{$store->name}» ({$store->slug}) نهائيًا — {$counts['orders']} طلب و{$counts['products']} منتج"
                    . ($owner ? " — صاحبه {$owner->email}" : ''),
                // No subject: it is about to stop existing. The summary and
                // the snapshot below are what is left to identify it by.
                changes: [
                    'store' => [
                        'from' => ['id' => $store->id, 'name' => $store->name, 'slug' => $store->slug, 'owner' => $owner?->email],
                        'to' => null,
                    ],
                ],
                actor: $admin,
            );

            $store->forceDelete();
        });

        // After the commit, and best effort: a folder that fails to delete
        // is clutter, not a reason to bring the store back.
        foreach (self::PUBLIC_DIRECTORIES as $pattern) {
            $this->forget(fn () => Storage::disk('public')->deleteDirectory(sprintf($pattern, $store->id)));
        }
        foreach ($generationIds as $id) {
            $this->forget(fn () => Storage::disk('local')->deleteDirectory("ai-inputs/{$id}"));
        }

        CustomerReputation::whereIn('id', $reputationIds)->get()
            ->each(fn (CustomerReputation $r) => $this->reputation->recount($r));

        return $counts;
    }

    private function forget(callable $cleanup): void
    {
        try {
            $cleanup();
        } catch (\Throwable $e) {
            Log::warning('Store deletion left a file behind', ['error' => $e->getMessage()]);
        }
    }
}
