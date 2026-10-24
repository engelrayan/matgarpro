<?php

namespace App\Console\Commands;

use App\Services\Whatsapp\PlatformWhatsapp;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Creates the platform number's one message template at Meta, or reports
 * whether Meta has approved it yet.
 *
 * Run once when the platform number is set up. The words and the two buttons
 * come from PlatformWhatsapp, so what Meta approves is exactly what the code
 * fills in — typing the template by hand into Business Manager is how the two
 * end up a comma apart and every send is refused.
 */
class CreatePlatformWhatsappTemplate extends Command
{
    protected $signature = 'whatsapp:platform-template
                            {--status : Only show whether Meta has approved it}';

    protected $description = 'Create the platform WhatsApp confirmation template at Meta, or check its approval';

    public function handle(): int
    {
        $waba = (string) config('services.whatsapp.platform.business_account_id');
        $token = (string) config('services.whatsapp.platform.access_token');

        if ($waba === '' || $token === '') {
            $this->error('محتاجين PLATFORM_WHATSAPP_WABA_ID و PLATFORM_WHATSAPP_TOKEN في ملف .env الأول.');

            return self::FAILURE;
        }

        $url = 'https://graph.facebook.com/' . config('services.whatsapp.cloud_api_version') . "/{$waba}/message_templates";

        try {
            $response = $this->option('status')
                ? Http::withToken($token)->acceptJson()->get($url, ['name' => PlatformWhatsapp::template()])
                : Http::withToken($token)->acceptJson()->post($url, PlatformWhatsapp::templateDefinition());
        } catch (ConnectionException) {
            $this->error('مقدرناش نوصل لميتا. جرّب تاني.');

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('ميتا رفضت: ' . (data_get($response->json(), 'error.error_user_msg') ?? data_get($response->json(), 'error.message') ?? $response->status()));

            return self::FAILURE;
        }

        $status = $this->option('status')
            ? data_get($response->json(), 'data.0.status', 'NOT_FOUND')
            : data_get($response->json(), 'status', 'PENDING');

        $this->info('القالب «' . PlatformWhatsapp::template() . "» حالته عند ميتا: {$status}");

        if ($status !== 'APPROVED') {
            // Usually minutes, sometimes a day. Until then sends are refused.
            $this->line('لما يبقى APPROVED الرسايل هتشتغل. اتأكد بـ: php artisan whatsapp:platform-template --status');
        }

        return self::SUCCESS;
    }
}
