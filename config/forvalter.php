<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Public share URL (Bifrost / Cloudflare tunnel)
    |--------------------------------------------------------------------------
    |
    | When set, the app forces generated URLs to this host. The Bifrost share
    | tunnel rewrites the Host header to forvalter.ddev.site for routing, so the
    | app can't otherwise discover its public hostname. Set SHARE_URL while a
    | share session is open (e.g. to view on a phone); unset it to stop.
    |
    */
    'share_url' => env('SHARE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Expense categories (kategori)
    |--------------------------------------------------------------------------
    |
    | The loose grouping shown as pills on the expense form. This is a separate
    | axis from the tax-critical kostnadstype (App\Enums\ExpenseType): category
    | is descriptive, kostnadstype decides the tax treatment. Stored as a plain
    | string on the expense so the list can grow without a migration.
    |
    */
    'categories' => [
        'Vedlikehold',
        'Kommunale avgifter',
        'Felleskostnader',
        'Forsikring',
        'Renter',
        'Strøm',
        'Bompenger',
        'Parkering',
        'Annonsering',
        'Møbler og utstyr',
        'Diverse',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax-year fallback defaults
    |--------------------------------------------------------------------------
    |
    | Real values live in the tax_year_settings table (brief §3.5) and may be
    | edited per income year. These are only seed defaults / fallbacks and must
    | be verified against Skatteetaten before any filing — Forvalter is a
    | preparation aid, not tax advice (brief §6).
    |
    */
    'tax_defaults' => [
        'mileage_rate_ore_per_km' => 350,        // statens sats, 3,50 kr/km
        'capital_income_tax_rate' => 0.22,       // kapitalinntekt 22 %
        'asset_threshold_ore' => 3_000_000,      // driftsmiddel direct-expense limit, kr 30 000
        'business_unit_threshold' => 5,          // virksomhet rule-of-thumb (informational)
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership split
    |--------------------------------------------------------------------------
    |
    | The portfolio is owned and run 50/50 (brief §1). The only place this
    | matters is the year-end export, which presents each person's half.
    |
    */
    'owner_split' => [1, 1],

    /*
    |--------------------------------------------------------------------------
    | AI document analysis (brief §7)
    |--------------------------------------------------------------------------
    |
    | The analyzer is swappable behind App\Services\DocumentAnalysis\
    | DocumentAnalyzer. 'auto' uses Claude when an Anthropic key is configured
    | and otherwise falls back to the deterministic stub, so the intake flow
    | works end-to-end with or without a key. AI output is always a suggestion
    | a human confirms — never auto-applied.
    |
    */
    'ai' => [
        'driver' => env('FORVALTER_AI_DRIVER', 'auto'), // auto | claude | fake
        'model' => env('FORVALTER_AI_MODEL', 'claude-opus-4-8'),
        'max_tokens' => 2048,
    ],

];
