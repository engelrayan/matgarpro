<?php

/*
|--------------------------------------------------------------------------
| AI
|--------------------------------------------------------------------------
| Everything the platform asks Claude goes through App\Services\AI\ClaudeGateway,
| and everything the gateway needs to decide — which model, how hard to think,
| how much a store may spend — lives here. The API key itself is in
| services.anthropic, next to every other third-party credential.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | A Claude call takes seconds, sometimes tens of seconds. Those jobs get a
    | queue of their own so a burst of merchants writing product copy can never
    | sit in front of a customer's WhatsApp confirmation.
    |
    | Run a worker for it: php artisan queue:work --queue=ai
    */
    'queue' => env('AI_QUEUE', 'ai'),

    /*
    |--------------------------------------------------------------------------
    | Daily spend cap, per store
    |--------------------------------------------------------------------------
    | In US dollars, because that is what Anthropic bills us in. The estimate
    | is computed from the token counts the API reports on every call, so it
    | tracks reality rather than a guess made up front.
    |
    | It is a ceiling on our exposure, not a price: a store that hits it is
    | told to come back tomorrow, never charged. 0 switches AI off for
    | everybody without a deploy.
    */
    'daily_cost_cap_usd' => (float) env('AI_DAILY_COST_CAP_USD', 1.00),

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    | One entry per App\Services\AI\AiFeature case.
    |
    |   model        Sonnet for anything a customer will read; Haiku for
    |                classification (is this reply a yes or a no?) where it
    |                is ~20x cheaper and just as right.
    |   effort       How much the model thinks before answering. Costs output
    |                tokens; copywriting gains little from more of it.
    |   max_tokens   Hard ceiling on one answer, thinking included.
    |   daily_limit  Calls per store per day — the number a merchant sees
    |                ("you have N tries left"), separate from the money cap.
    |   total_limit  Calls per store, ever. Replaces daily_limit when set, for
    |                a feature a store needs once rather than every day.
    |   fallbacks    Let the API retry on another model when this one declines
    |                for policy reasons. Sonnet supports it; Haiku does not, so
    |                it is skipped for a Haiku model whatever this says.
    */
    'features' => [
        'product_writer' => [
            'model' => env('AI_PRODUCT_WRITER_MODEL', 'claude-sonnet-5-5'),
            'effort' => env('AI_PRODUCT_WRITER_EFFORT', 'low'),
            'max_tokens' => 4000,
            'daily_limit' => (int) env('AI_PRODUCT_WRITER_DAILY_LIMIT', 20),
            'fallbacks' => true,
        ],

        /*
         | A whole store's worth of copy in one answer, once per store. Kept at
         | low effort for time, not money: the answer is a few thousand tokens
         | and has to arrive inside the 70s request timeout. If real calls
         | creep towards that, give AI jobs their own queue connection with a
         | longer retry_after before raising either number.
         |
         | Haiku, because the store is free to the merchant: a plan costs about
         | a fifth of a cent on it against four to seven cents on Sonnet, and a
         | short, structured Arabic brief is well inside what it writes well.
         | Two runs per store, ever — one, and one more if the first missed.
         | Fallbacks stay on: they are skipped while the model is Haiku (which
         | has none) and come back by themselves if AI_STORE_BUILDER_MODEL is
         | set to a Sonnet.
         */
        'store_builder' => [
            'model' => env('AI_STORE_BUILDER_MODEL', 'claude-haiku-5-5'),
            'effort' => env('AI_STORE_BUILDER_EFFORT', 'low'),
            'max_tokens' => 6000,
            'daily_limit' => (int) env('AI_STORE_BUILDER_DAILY_LIMIT', 2),
            'total_limit' => (int) env('AI_STORE_BUILDER_TOTAL_LIMIT', 2),
            'fallbacks' => true,
        ],

        /*
         | One product's landing page: up to three photos in, a page of copy
         | out. Same time budget as the store builder, so the same low effort.
         */
        'landing_page' => [
            'model' => env('AI_LANDING_PAGE_MODEL', 'claude-sonnet-5-5'),
            'effort' => env('AI_LANDING_PAGE_EFFORT', 'low'),
            'max_tokens' => 5000,
            'daily_limit' => (int) env('AI_LANDING_PAGE_DAILY_LIMIT', 10),
            'fallbacks' => true,
        ],

        /*
         | The inbox's summary and suggested reply: one short conversation in,
         | a few lines out, many times a day. Haiku — about a tenth of a cent a
         | call — so it can stay free to the merchant under a daily cap.
         */
        'inbox_assistant' => [
            'model' => env('AI_INBOX_MODEL', 'claude-haiku-5-5'),
            'effort' => env('AI_INBOX_EFFORT', 'low'),
            'max_tokens' => 1500,
            'daily_limit' => (int) env('AI_INBOX_DAILY_LIMIT', 100),
            'fallbacks' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prices, USD per million tokens
    |--------------------------------------------------------------------------
    | Used only to estimate what a call cost; Anthropic's invoice is the truth.
    | A model missing from this table is priced at the most expensive row, so
    | forgetting to add one over-counts spend instead of hiding it.
    |
    | Cache writes are billed at 1.25x input, cache reads at 0.1x.
    */
    'pricing' => [
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_write' => 2.50, 'cache_read' => 0.20],
        'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50, 'cache_write' => 0.125, 'cache_read' => 0.01],
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00, 'cache_write' => 5.00, 'cache_read' => 0.20],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    | One attempt per job run, no retries inside it. The job retries instead,
    | after a pause, because a queue worker can wait for a busy API without
    | holding anything — and because the whole attempt has to finish inside
    | the queue's retry_after (90s), or a second worker picks the same job up
    | and pays for it twice.
    */
    'timeout' => (float) env('AI_TIMEOUT', 70),

];
