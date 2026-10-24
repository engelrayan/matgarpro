<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\StoreWhatsappIntegration;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Console\Command;

/**
 * The follow-up a customer-service agent used to make by phone.
 *
 * A customer on the platform number who has not tapped anything gets the
 * message once more; one who still says nothing lands on the merchant's call
 * list (`whatsapp_state = no_reply`) instead of being chased forever. Only on
 * the platform number: a session gateway that messages people twice is how a
 * merchant's own line gets banned.
 */
class RemindWhatsappConfirmations extends Command
{
    protected $signature = 'whatsapp:remind';

    protected $description = 'Nudge customers who have not confirmed on WhatsApp, then hand the silent ones to the merchant';

    /** Nobody wants an order reminder at 2am; these wait for the morning run. */
    private const QUIET_FROM = 22;

    private const QUIET_UNTIL = 9;

    public function handle(WhatsappSender $sender): int
    {
        $remindAfter = (int) config('services.whatsapp.platform.remind_after_hours');
        $giveUpAfter = (int) config('services.whatsapp.platform.give_up_after_hours');

        // Before the reminders, so a quiet night still moves the long-silent
        // ones onto the call list for the morning.
        $handedOver = $this->platformOrders()
            ->whereNotNull('whatsapp_reminded_at')
            ->where('whatsapp_reminded_at', '<=', now()->subHours($giveUpAfter))
            ->update(['whatsapp_state' => 'no_reply']);

        $reminded = 0;

        if (! $this->isQuietHours()) {
            $due = $this->platformOrders()
                ->whereNull('whatsapp_reminded_at')
                ->where('whatsapp_sent_at', '<=', now()->subHours($remindAfter))
                ->with('items', 'store.whatsappIntegration')
                ->get();

            foreach ($due as $order) {
                $link = $order->store->whatsappIntegration;

                if (! $link?->canSend()) {
                    continue;
                }

                if ($sender->sendConfirmation($link, $order)['ok']) {
                    $order->forceFill(['whatsapp_reminded_at' => now()])->save();
                    $reminded++;
                }
            }
        }

        $this->line("اتبعت تذكير لـ {$reminded} عميل، و{$handedOver} طلب نزل لقايمة المكالمات.");

        return self::SUCCESS;
    }

    /** Pending orders still waiting on a tap, on stores using the platform number. */
    private function platformOrders(): \Illuminate\Database\Eloquent\Builder
    {
        return Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('whatsapp_state', 'sent')
            ->whereHas('store.whatsappIntegration', fn ($q) => $q
                ->where('driver', StoreWhatsappIntegration::DRIVER_PLATFORM)
                ->where('is_active', true));
    }

    private function isQuietHours(): bool
    {
        $hour = (int) now()->setTimezone('Africa/Cairo')->format('G');

        return $hour >= self::QUIET_FROM || $hour < self::QUIET_UNTIL;
    }
}
