# Forvalter

Privat verktøy for forvaltning av en utleieportefølje — bygd for én utleier
(kapitalinntekt, ikke næring). Holder styr på eiendommer, leieforhold og
husleie, leser bilag med AI, fører kjørebok, og lager underlag for
skattemeldingen ved årsslutt.

## Funksjoner

- **Boliger** — eiendommer (frittstående + bygård), enheter, leieforhold med
  husleie-reskontro (betalt/forfalt per måned), depositum og leietakerbytte.
- **Innboks (AI-intake)** — slipp et bilag hvor som helst → Claude leser det i
  bakgrunnen, foreslår beløp/dato/kostnadstype/eiendom → du bekrefter og bokfører.
- **Kjørebok** — turer med sats per km; bompenger matches mot kjøreboka.
- **Årsoppgjør** — netto per eiendom gruppert på kostnadstype, 50/50-fordeling,
  med PDF, regneark og en ZIP med alle bilag som dokumentasjon.
- **PWA** — installerbar på iPhone (full-skjerm, hjem-skjerm-ikon, splash).

## Stack

Laravel 13 · Livewire 4 (single-file components) · Tailwind v4 · MySQL/MariaDB ·
Anthropic Claude (`anthropic-ai/sdk`) · dompdf.

## Lokalt (DDEV)

Appen ligger i undermappen `forvalter/`. Kjør app-kommandoer i containeren:

```bash
ddev exec -d /var/www/html/forvalter "composer install"
ddev exec -d /var/www/html/forvalter "npm install && npm run build"
ddev exec -d /var/www/html/forvalter "php artisan migrate --seed"
```

Krever `ANTHROPIC_API_KEY` i `.env` for ekte AI-analyse (uten nøkkel brukes en
deterministisk stub).

## Deploy (Forge)

Auto-deploy fra `main`. Deploy-skriptet må bygge frontend (siden `public/build`
er git-ignorert):

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
```

I server-`.env`: sett `APP_KEY`, database, `ANTHROPIC_API_KEY`. La `SHARE_URL`
være tom (den er kun for den lokale Bifrost-tunnelen). HTTPS kreves for PWA.
