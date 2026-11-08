# Ozan Çetinyol — CMS Panel

This Laravel and Filament application manages the website's editable content
and appointment requests for the Ozan Çetinyol physiotherapy practice. It is
one of two applications in this project: the sibling `../ozancetinyol-front` application
serves the Turkish/English public website. Both applications use the same
MariaDB database but keep separate `APP_KEY` values and session cookies; this
panel owns the shared schema and migrations.

## Documentation

- [Architecture](ARCHITECTURE.md): CMS resources, authorization, shared schema,
  appointment management, and media integration.
- [Agent guidelines](AGENTS.md): project rules and validation expectations.
- [Claude instructions](CLAUDE.md): entry point for Claude and existing skills.
- [Public website](../ozancetinyol-front/README.md): frontend setup and media configuration.

## Setup

1. Copy `.env.example` to `.env`.
2. Configure the database. Use the same database name and credentials in the
   public frontend application's environment file.
3. Set `APP_URL` to the panel's deployment URL and configure `PANEL_URL` if
   uploaded media needs an explicit public base URL.
4. Set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` before seeding if the
   initial administrator should be created. The seeder refuses passwords
   shorter than 12 characters or without mixed case, digits and symbols.
5. In production set `APP_ENV=production`, an HTTPS `APP_URL`,
   `SESSION_SECURE_COOKIE=true`, and `TRUSTED_PROXIES` when TLS terminates at
   nginx/Plesk or a load balancer (see "Uploads and request hardening").
6. Install dependencies, generate this app's own key, migrate, seed, and build
   assets:

```powershell
composer install
npm install
npm run build
php artisan key:generate
php artisan migrate --seed
```

Generate and keep a separate `APP_KEY` for this app; do not copy the frontend's
key. Keep the panel session-cookie name distinct from the frontend's.

To add the bilingual starter website content to an existing database without
running role or administrator seeders, run this from the panel directory:

```powershell
php artisan db:seed --class="Database\Seeders\PhysiotherapyContentSeeder"
```

The content seeder is safe to rerun: it preserves customized settings and
existing pages, menus, and services. It does not create fictional testimonials
or blog articles.

## Development

```powershell
php artisan serve
```

Run migrations here before using the public appointment form. Back up the
shared database before applying production migrations; do not run
`migrate:fresh` against data you need to keep.

Run the frontend on a different port if both applications are served locally.
Configure each `APP_URL` and the frontend media URLs to match the panel.

### Laragon virtual host

Use `http://ozancetinyolpanel.test` with the same `APP_URL`. Keep the custom
Apache configuration in
`C:/laragon/etc/apache2/sites-enabled/ozancetinyolpanel.test.conf` without an
`auto.` prefix. Its `DocumentRoot` and `Directory` must point to
`C:/laragon/www/ozancetinyol/ozancetinyol-panel/public`, with `AllowOverride All`.

Add `127.0.0.1 ozancetinyolpanel.test` to the Windows hosts file as administrator.
Keep this custom entry outside Laragon's generated entries (without the
`#laragon magic!` marker). Restart Apache after adding the virtual host.
The root redirects unauthenticated visitors to `/login`.
On first opening, the panel negotiates Turkish/English from the browser's
`Accept-Language` header, including regional variants such as `tr-TR`.
Missing or unsupported language preferences fall back to Turkish. An explicit
language-switcher selection takes precedence and is kept in the session,
including Livewire requests. The CMS `default_language` setting controls site
content defaults, not the panel's interface language.
HTTP pages do not upgrade assets to HTTPS through CSP; HTTPS pages retain
`upgrade-insecure-requests` and HSTS.

After changing the local URL, run `php artisan config:clear` and
`php artisan route:clear`. Keep these caches disabled during development.

### Content Security Policy and headers

CSP and security headers cover application errors and redirects as well as
normal pages. Apache supplies anti-framing, MIME-sniffing, Referrer-Policy and
Permissions-Policy headers for static files too. Frames cannot embed the panel;
camera, microphone, geolocation, payment and USB APIs are disabled.
Laravel's XSRF cookie stays readable for JavaScript CSRF clients, while session
cookie flags remain controlled by session configuration.

The installed Filament templates use inline scripts/styles, and Alpine uses
dynamic expression evaluation. The panel therefore retains `unsafe-inline` and
`unsafe-eval` as explicit framework compatibility exceptions. This is not a
strict nonce-based script policy. Do not remove these permissions or switch
Livewire to its CSP build without testing all resources, forms, actions and
navigation against a compatible Filament version.

Scripts are restricted to the panel and Cloudflare analytics; external
styles/fonts are restricted to the configured Font Awesome CDN. Connections
are limited to the panel and Cloudflare, plus local loopback Vite/HMR in the
local environment. Before adding external integrations or broadcasting,
explicitly authorize their required origins rather than generic HTTPS/WSS.
Images retain HTTPS/data/blob sources for media previews.
Vite binds to IPv4 loopback to keep development asset/HMR URLs CSP-compatible.

Production requires TLS and a server-level HTTP-to-HTTPS redirect; forcing
generated URLs to HTTPS does not replace this redirect. Configure trusted
proxies when TLS terminates upstream. HTTPS responses send HSTS with
`includeSubDomains` and `preload`; verify every subdomain serves TLS before
enabling it, and only keep `preload` after registering the domain with the HSTS
preload list.

### Uploads and request hardening

Because the panel CSP must allow inline scripts, any file that an attacker can
place on the panel origin becomes a working XSS or RCE vector. Uploads are
therefore locked down at several layers:

- **Livewire temporary uploads** (`config/livewire.php`) are written to the
  private `local` disk, never to the web-served `public` disk, and the upload
  endpoint only accepts `UploadRules::TEMPORARY_UPLOAD_EXTENSIONS` (raster
  images and `.ico`). This endpoint is reachable from the unauthenticated login
  page, so it must never accept scripts or markup. Set
  `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK` only to another private disk.
- **Filament uploads** use `App\Support\UploadRules` allow-lists. Do not call
  `->image()`: it accepts `image/*`, which includes SVG (scriptable). Use
  `->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)`; the favicon uses
  `UploadRules::FAVICON_MIME_TYPES` (PNG/ICO, ≤512 KB). `AppServiceProvider`
  applies the image allow-list and size cap as a default for every
  `FileUpload`/`SpatieMediaLibraryFileUpload`, so a component must override it
  explicitly to accept anything else. `SecurityHardeningTest` fails if
  `->image()` reappears under `app/Filament`.
- **Apache** (`public/.htaccess`) returns 403 for `/storage/*.php` and other
  server-side script extensions, and serves every `/storage/` file with a
  `sandbox` CSP so a stored SVG/HTML cannot run scripts when opened directly.
  Replicate both rules in nginx when not using Apache.
- **Stored paths are untrusted**: `SlidersTable::resolvePreviewUrl()` only
  renders relative public-disk paths or absolute HTTP(S) URLs and escapes them;
  the favicon URL is escaped in the `HEAD_START` render hook. FileUpload state
  can be tampered with by a client, so never interpolate it into HTML raw.

Other request-level controls:

- `App\Http\Middleware\TrustProxies` replaces Laravel's default and reads
  `TRUSTED_PROXIES` (comma-separated IPs/CIDRs, or `*` when the app is only
  reachable through a proxy). Without it, IP throttling, `blocked_ips` and
  HTTPS-only headers see the proxy address. Leave it empty when PHP is exposed
  directly; trusting unknown proxies lets clients spoof their IP.
- `Setting` masks values of keys matching `secret|password|token|api_key` in
  the activity log (`tapActivity`), and `User` excludes `password` and
  `remember_token` from logging, so activity readers cannot harvest credential
  material. Secrets that are not editable in the panel belong in `.env`.
- `Page::normalizeMapEmbedUrl()` accepts only exact Google Maps hosts
  (`Page::ALLOWED_MAP_EMBED_HOSTS`, mirrored in the frontend) with an embed
  path; both the page form and the homepage `home_map_embed_url` setting
  validate and normalize through it.
- `AdminUserSeeder` enforces a strong `ADMIN_PASSWORD` (≥12 chars, mixed case,
  digits, symbols). Rotate any administrator password that predates this rule.

## Appointment calendar and email operation

Admin/Developer users can manage Turkish/English KVKK/privacy and cookie
policy titles, SEO descriptions and content from **Yasal sayfalar / Legal
pages** at `/legal-page-settings`.

After backing up the shared database, run `php artisan migrate` here. Open
**Randevu çalışma saatleri / Appointment working hours** as Admin/Developer
and enable the actual working days, hours, appointment duration and holidays.
All times use `config('app.timezone')` from `.env`'s `APP_TIMEZONE`; days are
closed until configured. Set the same `APP_TIMEZONE` in both applications
(the examples use `Europe/Istanbul`). After changing it, clear or rebuild
the config cache in both apps. Existing booking date/time values are local
wall times and are not automatically converted to a different timezone.
Review existing appointments before changing a live clinic's timezone.
Emails snapshot the timezone at creation so delayed delivery cannot relabel
them; older outbox entries without a timezone use the current app setting.
Use **Müsaitlik takvimi / Availability calendar** on the appointment list.
The daily view includes previous/next-day and today controls, available/booked/
past slot counts, colored time cards and appointment status cards. It supports
mobile layouts and the panel's dark mode; pending requests do not reserve slots.
Working hours are available at `/appointment-settings` and from the appointment
list's working-hours button (Admin/Developer only). After deploying new panel
pages/routes, clear both Filament's component discovery cache and Laravel's
route cache:

```powershell
php artisan filament:clear-cached-components
php artisan route:clear
```

Filament's discovery cache is used only for web requests, so a successful
`route:list` alone does not prove the browser has the new pages. In production,
rebuild component discovery before caching routes:

```powershell
php artisan filament:cache-components
php artisan route:cache
```
Only confirmed appointments appear as booked on the public calendar.
Complete email/date/time on legacy requests before confirming.

Configure the panel's real SMTP mailer (`MAIL_MAILER=smtp`, host, port,
credentials and sender) and the site contact email in general settings.
Log/array mailers (including failover to log) are rejected: they do not deliver
email, so notifications stay pending until a real transport is configured.
Customer receipt/confirmation and admin pending/confirmation emails are
persisted with the appointment and delivered by the panel scheduler.
Failed deliveries remain pending and appear as a warning on the calendar.
Retries wait 1, 5, 15 and then 60 minutes after consecutive failures, without
discarding pending messages. Each run handles at most 100 messages and stops
starting new sends after five minutes (the current transport call may finish
after that budget). Sent messages are not sent again on ordinary retries.

Configure your server scheduler to run this from the panel directory every
minute (Windows Task Scheduler or cron, using absolute paths):

```powershell
php artisan schedule:run
```

For local continuous scheduling, keep `php artisan schedule:work` running.
Use one scheduler host and a persistent cache store. Monthly activity cleanup
runs on day 1 and removes only logs older than 30 days. To manually retry
pending mail, run `php artisan appointments:send-notifications`.
The sender uses a shared cache lock to prevent concurrent manual/scheduled runs.

`appointments:prune --force` runs on January 1 and July 1 at 03:00 in the
application timezone. It permanently deletes appointments whose last update
and preferred date are both older than six months (a missing date is allowed),
including soft-deleted records and their related notification copies. Other
notification records older than six months are deleted unless their appointment
date is within the protected recent/future window. Sent and failed notifications
are both in scope. The sender and cleanup share a lock to prevent concurrent
delivery/deletion. Cleanup does not remove server logs, backups or clinical
records. A six-monthly run means eligible data can remain until the next run,
so this is not an exact six-month maximum retention guarantee.

## Validation

Run relevant tests from this application's directory, with an isolated,
disposable test database:

```powershell
php artisan test --compact tests/Feature/PanelHtmlMarkupTest.php
php artisan test --compact --filter=FrameworkTranslationsTest
```

Use `php artisan test --compact` for the full suite, `npm run build` for asset
changes, and `composer validate` for Composer manifest changes.

## Before publication

Replace the sample profile and service content with verified information,
provide final contact details and branding, upload clinic photography through
the panel, and complete the privacy/cookie notices for the actual
data-processing practices.
