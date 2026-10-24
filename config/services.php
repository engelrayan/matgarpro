<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    | Daman — the wakil a merchant ships through.
    |
    | One host for both environments: the merchant's key (dm_test_ / dm_live_)
    | is what decides whether a shipment reaches a real carrier, so there is
    | nothing to configure per store beyond the key itself.
    */
    'daman' => [
        'base_url' => env('DAMAN_API_URL', 'https://api.daman-pay.com/api/v1/integration'),
    ],

    /*
    | WhatsApp. A store either brings its own line (credentials on the store)
    | or uses the platform's official number below, which needs nothing from
    | the merchant at all.
    */
    'whatsapp' => [
        // Meta pins behaviour to a Graph version; bumping it is a decision, not
        // something that should drift with whatever is newest.
        'cloud_api_version' => env('WHATSAPP_CLOUD_API_VERSION', 'v21.0'),

        /*
        | The platform's own Cloud API number, shared by every store that
        | switches it on. Official, so the confirm/cancel buttons work and the
        | number cannot be banned for "automation"; one approved template,
        | created once with `php artisan whatsapp:platform-template`.
        |
        | Left empty, the option simply does not appear to merchants.
        */
        'platform' => [
            'access_token' => env('PLATFORM_WHATSAPP_TOKEN'),
            'phone_number_id' => env('PLATFORM_WHATSAPP_PHONE_NUMBER_ID'),
            // The business account that owns the number — only needed to
            // create the template, never to send.
            'business_account_id' => env('PLATFORM_WHATSAPP_WABA_ID'),
            // Required: on a number every store shares, an unsigned webhook
            // would let a stranger confirm or cancel anybody's orders.
            'app_secret' => env('PLATFORM_WHATSAPP_APP_SECRET'),
            'verify_token' => env('PLATFORM_WHATSAPP_VERIFY_TOKEN'),
            'template' => env('PLATFORM_WHATSAPP_TEMPLATE', 'order_confirmation'),
            'template_language' => env('PLATFORM_WHATSAPP_TEMPLATE_LANGUAGE', 'ar'),
            // One nudge this long after the first message, then — if the
            // reminder goes unanswered for `give_up_after_hours` too — the
            // order goes on the merchant's call list. See whatsapp:remind.
            'remind_after_hours' => (int) env('PLATFORM_WHATSAPP_REMIND_AFTER_HOURS', 3),
            'give_up_after_hours' => (int) env('PLATFORM_WHATSAPP_GIVE_UP_AFTER_HOURS', 12),
        ],
    ],

    /*
    | Claude. One platform key — merchants never bring their own, and never see
    | it. Left empty, every AI feature tells the merchant it is unavailable
    | instead of failing somewhere inside a job. Models, caps and prices are
    | in config/ai.php.
    */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
