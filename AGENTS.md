# CMS panel agent guidelines

## Scope and context

These instructions apply to this Laravel and Filament CMS application. Read
[README.md](README.md) for setup and [ARCHITECTURE.md](ARCHITECTURE.md) for
authorization, storage, and shared-database boundaries. The public website is
a separate application in [../ozancetinyol-front](../ozancetinyol-front/README.md).

## Implementation rules

- This application owns the shared database schema and migrations. Coordinate
  schema, model, and media-contract changes with the frontend consumers.
- Follow existing Filament resource, form, table, action, and model patterns.
  Preserve Turkish/English content and existing translation conventions.
- Preserve resource authorization through
  `app/Filament/Resources/BaseResource.php` and `GlobalPolicy`; do not bypass
  permission checks in new pages or actions.
- Preserve admin authentication, no-index behavior, and the provider's
  non-local HTTPS handling.
- Keep role seeding and permission-cache invalidation consistent with existing
  Spatie Permission behavior. Initial admin creation must use configured
  `ADMIN_*` values, never hardcoded credentials.
- Use the existing Spatie Media Library integration and activity logging.
  Resolve disk behavior through configuration, not direct runtime `env()` calls.
- Keep social image resize output deterministic when an explicit format is
  requested; do not let WebP content negotiation override `format=jpg`.
- Preserve the shared appointment fields and casts, including locale, status,
  and privacy-consent timestamp.
- Keep application keys and session-cookie names separate from the frontend.
  Never commit credentials or use shared/production data for destructive tests.
- Preserve existing `.claude` skills and consult their permission and media
  guidance when changing those integrations.

## Validation

Run commands from this directory. Use the existing PHPUnit test style and
select tests relevant to the changed behavior:

```powershell
php artisan test --compact tests/Feature/PanelHtmlMarkupTest.php
php artisan test --compact --filter=FrameworkTranslationsTest
```

Run `npm run build` for asset changes and `composer validate` for Composer
manifest changes. Use `php artisan test --compact` for broader regression
coverage only after confirming the test database is disposable.
Back up shared data before production migrations; never run `migrate:fresh`
against data that must be retained. Update related documentation with contract
or setup changes.
