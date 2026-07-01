# CLAUDE.md — Forvalter

Guidance for AI coding agents (and humans) working in this repo. Read this first.

## What this is

**Forvalter** is a private property-management web app for **one Bergen landlord** who
rents out a few apartments. Rental income is taxed as **kapitalinntekt (private
person, 22 % flat), NOT næringsvirksomhet** — this framing drives every tax decision.
**North-star deliverable:** solid year-end documentation for the *skattemelding*
(Årsoppgjør). It is a **preparation aid, not tax advice** — every figure must be
verifiable against Skatteetaten.

## Hard rules (do not violate)

- **Code & database in English; all user-facing text in Norwegian (bokmål).** Currency
  NOK, dates `dd.mm.yyyy`.
- **Money is integer øre** (BIGINT), never floats. Use the `App\Support\Money` value
  object + `App\Casts\MoneyCast`. Never do float kr math.
- **Login-only, no public registration.** Provision users with
  `php artisan app:create-user --name= --email= --password=` (idempotent = also a
  password reset; interactive prompts over SSH).
- **`kostnadstype` (App\Enums\ExpenseType) is the most important field** — it decides
  tax treatment and is captured at entry, never inferred later. It is a *different
  axis* from the descriptive `category` (kategori). See Domain model below.
- **AI advises, a human confirms** — AI output is always a reviewable suggestion,
  never auto-applied/auto-booked.

## Stack

Laravel **13** · Livewire **4** (single-file components) · Tailwind **v4** (CSS-first) ·
MySQL/MariaDB via **DDEV** · Anthropic Claude (`anthropic-ai/sdk` ^0.31) · dompdf.

## Dev environment — the #1 gotcha

**The Laravel app is in a SUBDIRECTORY of the DDEV project root.** DDEV root is
`…/Biforst/forvalter` (docroot `forvalter/public`); the app + `artisan` +
`composer.json` live in `…/Biforst/forvalter/forvalter`.

➡️ **Always run app commands as `ddev exec -d /var/www/html/forvalter "<cmd>"`**
(artisan, composer, npm). Plain `ddev artisan` fails ("Could not open input file").

After editing any Blade/Livewire view, run
`ddev exec -d /var/www/html/forvalter "php artisan view:clear"`.
After editing CSS/JS or any Blade that adds Tailwind classes, run
`ddev exec -d /var/www/html/forvalter "npm run build"` (assets → `public/build`).

**Local logins** (seeded): `fredrik@ross.land` / `fredrik` (the owner's), plus
`fredrik.rossland@maksimer.com` & `eier2@forvalter.local` / `password`.

**Sharing to a phone:** `bifrost share` (cloudflared tunnel) rewrites the Host to
`forvalter.ddev.site`, so a `SHARE_URL` env var + `URL::forceRootUrl()` in
`AppServiceProvider::boot()` fixes generated URLs. **Remove `SHARE_URL` from `.env`
to stop sharing — and it must be UNSET in production.**

## Livewire 4 conventions (this project)

- Single-file components: `resources/views/components/⚡name.blade.php` and page
  components `resources/views/pages/⚡name.blade.php` (note the `⚡` filename prefix).
- Routed with `Route::livewire('/url', 'pages::name')` in `routes/web.php`.
- Layouts in `resources/views/layouts/`, referenced as `layouts::app` via
  `#[Layout('layouts::app')]`.
- SFC shape: `<?php new #[Layout('layouts::app')] class extends Component { … }; ?>`
  then the Blade template. Data via a `with(): array` method.
- A Livewire action that returns a redirect must NOT be typed `: void`
  (`return $this->redirect(route(...), navigate: true)` fatals on a void method).
- **Blade gotcha (hit 3×):** `word@if(...)` glues `@if` to the preceding word, so
  Blade skips the `@if` but still parses the `@endif` → "unexpected endif" 500.
  **Always keep whitespace before `@if`**, or compute the value in `@php`.

## Design system ("Blå · Modern", design-handoff-3)

CSS-first tokens in `resources/css/app.css` `@theme`. Changing a token hex re-skins the
whole app (every view uses `text-ink`/`bg-terra`/`text-positive`/`border-line`).

- Accent **blue `#2C5CE6`** — the token is still named **`--color-terra`** (value is now
  blue) so legacy markup didn't need touching; `terra-soft #eef2fe`. `--color-teal*`
  aliases also fold into blue.
- Fonts: **Schibsted Grotesk** (`--font-display` / `.font-display`) for logo, headings,
  big numbers; **Hanken Grotesk** for body. `.tnum` = tabular-nums.
- Ink `#16181d`, greys `#4b5563`/`#6b7280`/`#9aa0ab`, lines `rgba(0,0,0,.07)`.
  Green `#0e9f6e` (positive money), **red `--color-negative #d64545`** (overdue/restanse).
  Cool surfaces `--color-panel #f5f7fc`, `--color-chip #eef1f6`, dark hero `#14161b`.
- Cards (`<x-card>`) are flat (border only). Shared components: `brand`, `nav-link`
  (pill), `stat` (Schibsted numbers, tones incl. `negative`), `status-pill`,
  `bilag-preview` (thumbnail + lightbox), `bottom-nav`.
- Mobile inputs are forced to `font-size:16px` (≤767px) to stop iOS focus-zoom; form
  controls have `min-width:0` to avoid modal side-scroll.

## Domain model (`app/Models/`)

- **Property** (eiendom) → **Unit** (boenhet). `>1 unit = bygård, 1 = frittstående`
  (derived via `isBuilding()`, no type field). `costBasis()` = purchase price + Σ
  påkostning (derived). **Felleskostnader = property-level expense with `unit_id` null**,
  never split to units.
- **Tenant** (name/email/phone) ←→ **Tenancy** (starts_on/ends_on/monthly_rent_ore/
  deposit_ore) → **Income** (rent per unit per month; `received_on` = paid date, null =
  outstanding; deposits are NOT income). **Rent history lives per-month in Income** (each
  row has its own `amount_ore`); the tenancy holds only the *current* rate. There is
  deliberately **no rent-regulation machinery** (user rejected notices/KPI/history-table).
  The unit page (`⚡unit-show`) is the rent hub: a **year switcher**, **"Generér husleie
  for {year}"** at a per-year rate (+ "marker betalt" to backfill a past year), **per-month
  amount/date editing** (pencil on paid rows, "Registrer" on unpaid; blank date = utestående),
  and **"Legg til tidligere leieforhold"** to record a prior tenant + backfill their received
  rent — all without disturbing the current lease (`currentTenancy` = latest `starts_on`).
- **Expense** — `type` (ExpenseType) is the tax axis; `category` is descriptive. Saving
  hook sets `income_year` from date. Soft-deletes.
- **ExpenseType** enum: Maintenance/Operating/Finance (deductible now) · Improvement
  (påkostning → capitalised to cost basis, NOT deductible now) · CapitalAsset
  (driftsmiddel → depreciated). `category` list is in `config/forvalter.php`.
- **Trip** (kjøring) — `distance_km` holds the FULL distance; `round_trip` bool;
  saving hook computes `income_year` + `deduction_ore = distance_km × rate`. Rate
  snapshotted from **TaxYearSetting** per income year (`mileage_rate_ore_per_km`, default
  **350 øre = kr 3,50/km**).
- **TripFavorite** — saved routes (label, property, leg distance, round_trip); nameable.
- **Document** (bilag, immutable, `hash`) → **DocumentAnalysis** (AI extraction;
  statuses Pending/Draft/Confirmed/Discarded/Failed). **Loan**, **DepreciableAsset** +
  **AssetDepreciation** (saldoavskrivning).
- Ownership is treated as a simple blanket **50/50** (`config('forvalter.owner_split')`).
  The user explicitly does NOT want per-owner tracking or virksomhet-threshold warnings.

## Key subsystems

- **AI intake (Innboks)** — `app/Services/DocumentAnalysis/`: swappable `DocumentAnalyzer`
  (`ClaudeDocumentAnalyzer` ↔ `FakeDocumentAnalyzer`, bound in `AppServiceProvider`,
  `config('forvalter.ai.driver')` = auto|claude|fake → Claude when
  `services.anthropic.key` set). Versioned `ReceiptExtractionPrompt`. Drop a bilag
  anywhere (or, on mobile, the Innboks "Ta bilde / Velg fil" buttons) → `IngestDocumentController`
  stores it + a Pending analysis → `AnalyzeDocumentJob` (dispatched `->afterResponse()`,
  no queue worker needed) → Draft in `/innboks` → review → book via expenses-create
  (`?analysis=`). Extracts vendor/date/total/vat/invoice/kid + suggested type/category +
  **property_hint** (matched to a Property by `PropertyMatcher`) + **toll_passings**.
  **Gotcha:** the model returns kroner as JSON numbers (`249.8`) → use
  `ClaudeDocumentAnalyzer::kronerToMoney()` (round ×100), NOT `Money::fromKronerString`.
- **Duplicate detection** — `App\Services\DuplicateFinder`: flags an already-booked bilag
  (same file hash / invoice no. / KID / amount+date). Advisory banner; never blocks.
- **Toll matching** — `App\Services\Toll\TollMatcher`: matches each bompassering to a Trip
  **by date** and **sums the actual charged passings** (so a round trip's two charges both
  count). **DECIDED: do not change to one-per-day or doubling.** Review/book at
  `/innboks/bom/{analysis}`, splits per property.
- **Årsoppgjør** — `App\Services\TaxExport\AnnualReport` (pure read model: per-property net
  by kostnadstype, påkostning held out, 50/50). `/arsoppgjor` + exports via
  `AnnualExportController`: **PDF** (dompdf, view `exports/annual-report-pdf`), **CSV**
  (`;`+comma decimals+BOM), **bilag ZIP** (all receipts foldered per property + manifest).
- **Documents** served auth-gated + inline via `documents.show` (`/bilag/{document}`).

## Tax facts (researched, see also memory)

- Private landlord → **no formal næringskjørebok duty**; just substantiate each trip
  (date · destination · purpose · km). **Klokkeslett/time is NOT required** (that's the
  firmabil regime). km rate **kr 3,50** (2025 & 2026). **Bompenger/ferje/parkering are
  deductible on top of** the per-km rate. Round trip = full there-and-back km.

## PWA (iOS-focused)

Installable: `public/manifest.webmanifest`, `public/sw.js` (cache-first `/build/*`,
network-first navigations, **bypasses `/livewire/*` + all POST**), icons + `public/splash/`
launch images, head wiring + SW registration + a custom **pull-to-refresh** (standalone
only) in `layouts/app.blade.php`. **iOS limits:** no manifest `shortcuts`, no Web Share
Target (can't share a PDF into the app — use the in-app picker), no background GPS. After
deploying, fully close & reopen the installed app to pick up the new service worker.

## Deploy

GitHub `git@github.com:exlil/forvalter.git`, branch **`main`** → **Laravel Forge**
auto-deploys `forvalter.ross.land`. `.gitignore` excludes `.env` (holds
`ANTHROPIC_API_KEY` — never commit), `/vendor`, `/node_modules`, `/public/build`,
`/.playwright-mcp`. Forge deploy script must run: `composer install --no-dev`,
`npm ci && npm run build`, `php artisan migrate --force`. Server `.env`: set APP_KEY, DB,
`ANTHROPIC_API_KEY`; leave `SHARE_URL` empty. HTTPS required (PWA/SW).

## Known placeholders / deferred

- The bygård/property page **"Rediger" button is still a dead placeholder** (would edit
  Property name/address/purchase price). Unit "Rediger" IS implemented.
- **Offline data entry** (log a trip/expense with no signal) is deferred "Phase 2" —
  Livewire needs the server per action; a true offline-capture or a Capacitor native
  wrapper (for background GPS) is the future path if needed.

## Working style here

When verifying with the browser/Playwright + test data, **always clean up the test
data afterward** (the owner uses the live DB; she has cleared demo data to test
manually). Use `ddev exec … tinker --execute="…"` for quick DB ops. Commit only when
asked; commit messages end with the `Claude-Session:` trailer.
