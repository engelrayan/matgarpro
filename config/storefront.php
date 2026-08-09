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

    /*
    |--------------------------------------------------------------------------
    | Certificates for merchant domains
    |--------------------------------------------------------------------------
    | A merchant attaches their own domain and expects a padlock. Nobody is
    | going to buy over cash-on-delivery from a page the browser calls "Not
    | secure", so this is not a nicety — an uncertified custom domain is worse
    | for the merchant than not having one.
    |
    | The flow: certbot proves control over the hostname by serving a file from
    | our own webroot (which works because the storefront is nginx's
    | `default_server`, so any merchant hostname already lands on us), then we
    | write a small vhost naming that certificate and reload nginx.
    |
    | `enabled` is off by default. On a laptop there is no certbot, no nginx
    | and no port 80 reachable from the internet, and a queue worker retrying
    | against Let's Encrypt from a dev machine would burn the account's real
    | rate limit.
    */
    'ssl' => [
        'enabled' => (bool) env('STOREFRONT_SSL_ENABLED', false),

        // Where expiry notices from the CA go. Required by ACME.
        'email' => env('STOREFRONT_SSL_EMAIL', 'ops@matgarpro.com'),

        'certbot' => env('STOREFRONT_CERTBOT_BIN', '/usr/bin/certbot'),

        // The directory certbot answers the HTTP-01 challenge from. Must be
        // the storefront's public root — the same one nginx serves.
        'webroot' => env('STOREFRONT_SSL_WEBROOT', '/var/www/matgarpro/public'),

        /*
        | One file per certified domain, included by nginx. Kept in their own
        | directory so a domain can be un-certified by deleting one file, and
        | so nothing this code writes can ever land inside the hand-maintained
        | vhost.
        */
        /*
        | Outside `sites-enabled`, not inside it. Debian's nginx.conf carries
        | `include /etc/nginx/sites-enabled/*;` — a sub-directory there is
        | matched by that glob and nginx refuses to start, taking every site on
        | the box down with it. Its own directory, included by its own line.
        */
        'vhost_dir' => env('STOREFRONT_SSL_VHOST_DIR', '/etc/nginx/matgarpro-domains'),

        // The shared server body every generated vhost includes: root, PHP,
        // headers. Written once by hand so a change reaches every merchant
        // domain at the same time.
        'vhost_include' => env('STOREFRONT_SSL_VHOST_INCLUDE', '/etc/nginx/snippets/matgarpro-storefront.conf'),

        'reload_command' => env('STOREFRONT_SSL_RELOAD', 'sudo /usr/sbin/nginx -s reload'),

        /*
        | Let's Encrypt allows 5 failed validations per hostname per hour. A
        | job that retries every minute exhausts that in five, and then the
        | domain is locked out for everyone — including the merchant who fixes
        | their DNS two minutes later. Backoff is measured in hours for that
        | reason, not minutes.
        */
        'retry_hours' => [1, 6, 24],
        'max_attempts' => 6,
    ],

];
