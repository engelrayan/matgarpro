<?php

return [

    'meta' => [
        /*
        |----------------------------------------------------------------------
        | Graph API version
        |----------------------------------------------------------------------
        | Pinned, never "latest": Meta deprecates versions on a schedule, and a
        | floating version means conversions start failing on a date nobody
        | wrote down.
        */
        'version' => env('META_GRAPH_VERSION', 'v21.0'),

        'endpoint' => 'https://graph.facebook.com',

        /*
        |----------------------------------------------------------------------
        | Request timeout (seconds)
        |----------------------------------------------------------------------
        | Short by design. The call runs in a queued job, so a slow Meta never
        | touches the customer's page — but a worker stuck for 30s per event is
        | a worker not sending the next one.
        */
        'timeout' => 5,

        /*
        |----------------------------------------------------------------------
        | Retries
        |----------------------------------------------------------------------
        | Meta accepts events up to 7 days old, so a delayed retry is still a
        | valid conversion. Backoff is in seconds, one entry per attempt.
        */
        'retry_backoff' => [30, 120, 600, 3_600],
    ],

];
