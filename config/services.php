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
    | WhatsApp. Credentials are per store, not per platform — the number that
    | messages a customer belongs to the merchant they bought from. Only the
    | things that are the same for everybody live here.
    */
    'whatsapp' => [
        // Meta pins behaviour to a Graph version; bumping it is a decision, not
        // something that should drift with whatever is newest.
        'cloud_api_version' => env('WHATSAPP_CLOUD_API_VERSION', 'v21.0'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
