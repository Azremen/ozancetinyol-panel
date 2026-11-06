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
   initial administrator should be created.
5. Install dependencies, generate this app's own key, migrate, seed, and build
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
proxies when TLS terminates upstream. HTTPS responses send HSTS without
`includeSubDomains` or preload until all subdomains have verified TLS support.

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
