<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform domain
    |--------------------------------------------------------------------------
    | Every store gets `{slug}.{domain}` for free and keeps it forever, even
    | after attaching a custom domain — merchants use it to preview changes and
    | it is the fallback when their own DNS breaks.
    */
    'domain' => env('STOREFRONT_DOMAIN', 'matgarpro.com'),

    /*
    |--------------------------------------------------------------------------
    | Scheme and port used when building storefront URLs
    |--------------------------------------------------------------------------
    | Only ever set outside production. The hostname stays bare — the resolver
    | matches on `Host` with the port already stripped, so a port baked into
    | `domain` makes every storefront 404. These two are appended when a link
    | is built, and nowhere else.
    |
    | Locally this makes stores reachable as `{slug}.localhost:8000`: browsers
    | resolve any `*.localhost` to 127.0.0.1 with no hosts-file entry, which a
    | `.test` domain needs and never has.
    */
    'scheme' => env('STOREFRONT_SCHEME', 'https'),

    'port' => env('STOREFRONT_PORT'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard hostname
    |--------------------------------------------------------------------------
    | The merchant dashboard is pinned to this host so it cannot shadow a
    | storefront's own pages. Leave it empty only in throwaway environments —
    | with no constraint the dashboard answers on every hostname, including
    | merchants' domains.
    */
    'app_domain' => env('STOREFRONT_APP_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | DNS targets handed to merchants
    |--------------------------------------------------------------------------
    | `a` holds every IP that may serve storefront traffic (edge nodes, blue and
    | green during a migration). A domain is considered pointed at us when it
    | resolves to ANY of them, or CNAMEs to `cname`.
    */
    'dns' => [
        'a' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('STOREFRONT_DNS_A', '')),
        ))),
        'cname' => env('STOREFRONT_DNS_CNAME', 'connect.matgarpro.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved sub-domains
    |--------------------------------------------------------------------------
    | Slugs a merchant may not take, because they either already resolve to our
    | own infrastructure or would let a store impersonate the platform.
    */
    'reserved_slugs' => [
        'www', 'api', 'app', 'admin', 'dashboard', 'cdn', 'assets', 'static',
        'mail', 'smtp', 'imap', 'pop', 'ftp', 'ns1', 'ns2', 'mx', 'connect',
        'status', 'help', 'support', 'docs', 'blog', 'pay', 'checkout',
        'billing', 'account', 'accounts', 'auth', 'login', 'register',
        'matgarpro', 'store', 'stores', 'shop', 'test', 'staging', 'dev',
    ],

    /*
    |--------------------------------------------------------------------------
    | Domains that may never be attached
    |--------------------------------------------------------------------------
    | Guards against a merchant claiming a domain they do not own in order to
    | trigger a certificate request, or shadowing our own hostnames.
    */
    'blocked_domains' => [
        'localhost', 'example.com', 'example.org', 'example.net',
        'google.com', 'facebook.com', 'instagram.com', 'tiktok.com',
        'whatsapp.com', 'apple.com', 'microsoft.com', 'amazon.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain verification
    |--------------------------------------------------------------------------
    | `recheck_minutes` is how often the background verifier retries a pending
    | domain; `give_up_after_hours` flips it to `failed` so the merchant sees a
    | real error instead of a spinner that never resolves. Failed is not final —
    | the merchant can retry once they have fixed their DNS.
    */
    'verification' => [
        'recheck_minutes' => 5,
        'give_up_after_hours' => 48,
    ],

];
