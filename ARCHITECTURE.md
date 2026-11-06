# CMS panel architecture

## Responsibility and stack

The panel is a Laravel 12 and Filament 5 application. It manages website
content, appointment requests, users, and permissions. It uses Spatie
Permission, Media Library, and Activitylog, with the Filament Media Library
plugin for media administration.

The [public frontend](../ozancetinyol-front/ARCHITECTURE.md) reads the same MariaDB database.
The panel is the sole owner of shared schema migrations.

## Code map

| Location | Responsibility |
| --- | --- |
| `app/Providers/Filament/AdminPanelProvider.php` | Panel registration, authentication, middleware, and non-local URL behavior |
| `app/Filament/Resources/` | Administrative content and record management |
| `app/Filament/Resources/BaseResource.php` | Shared resource authorization via `GlobalPolicy` |
| `app/Models/` | Shared domain records, users, and media |
| `app/Models/Media.php` | Media Library model with activity logging |
| `database/migrations/` | Shared database schema |
| `database/seeders/RoleSeeder.php` | Role setup and permission-cache invalidation |
| `database/seeders/AdminUserSeeder.php` | Optional initial administrator creation |
| `routes/web.php` | Robots response excluding indexing |
| `routes/img.php` | Image resizing endpoint |
| `routes/api.php` | Currently empty API route file |
| `tests/Feature/`, `tests/Unit/` | PHPUnit regression tests |

## Administration and authorization

The Filament panel is mounted at this application's root and requires
authentication. Its provider applies no-index middleware and forces HTTPS/root
URL behavior outside the local environment. The robots response also disallows
indexing; neither indexing control replaces access control.
Global CSP middleware and the exception response callback apply security
headers to normal and error responses. Script/style inline permissions and
script evaluation remain documented Filament/Alpine compatibility exceptions;
third-party script and connection origins are otherwise explicitly restricted.

Resources manage appointments, activities, facilities, menus, pages, posts,
roles, sliders, testimonials, URLs, and users. Existing resources inherit
authorization from `BaseResource`, which delegates to `GlobalPolicy`.
Retain this path for new resource operations.

Role seeding defines `Admin`, `Manager`, `Personnel Manager`, and `Personnel`
and clears the permission cache. The administrator seeder only creates the
initial account when both `ADMIN_EMAIL` and `ADMIN_PASSWORD` are configured;
`ADMIN_NAME` supplies its configured name.
Role assignment on user create/edit and edits to Admin/Developer accounts are
restricted to Admin/Developer actors, independently of delegated user-write
permissions. Inline active-state toggles on posts, testimonials, and facilities
enforce the same per-record update authorization as their edit pages.

## Shared content and media flow

1. Authorized administrators edit CMS records and upload media through Filament.
2. Eloquent persists content to the shared database; Media Library handles
   uploads and the media model records activity.
3. The frontend reads shared content records and renders them in both locales.
4. The frontend's configured media URLs point to this panel's public media
   endpoints rather than a separate upload copy.

The panel environment example uses `FILESYSTEM_DISK=public` and `PANEL_URL`
for explicit public media URLs. Preserve config-driven disk and URL behavior.
There is no content synchronization API required for these shared reads.

The public image resize endpoint accepts `format=jpg` for social preview
images. This explicit format overrides WebP content negotiation and has a
separate source-versioned cache key; unsupported explicit formats are rejected.
JPEG quality is reduced as needed to keep social previews below 600 KB.

### Navigation, homepage and legal content

`MenuStructure` validates Header menus as root + child only and Footer menus
as flat records, on both forms and model saves. New sidebar menus and menu SEO
inputs are removed; retained SEO columns/data are untouched. Existing sidebar
records remain visible in the CMS as legacy records, never implicitly converted
to Header. Edit or soft-delete them normally. Repair old deeper/nested records
explicitly: detach the deepest children first, then edit the parent/type.
Public navigation only renders configured Header roots/children and Footer
roots; unsupported legacy records are retained but not displayed.

Admin/Developer-only `HomePageSettings` stores `home_sections` as seven unique
`{section, enabled}` rows, ordered by their array position. Allowed identifiers:
`slider/about/services/testimonials/news/location/appointment`. Toggle or drag
existing rows; this is not a page builder. Missing settings use the original
order with all sections enabled. `home_testimonials_background` is consumed via
the frontend's configured shared-media URL helper.

Admin/Developer-only `LegalPageSettings` stores bilingual
`legal_{cookies,privacy}_{title,meta,content}` settings transactionally with cache
invalidation. Blank values retain each language's original public text; rich
content is sanitized on the frontend. Existing legal routes/URLs stay unchanged.

### Page URLs and aliases

The page create/edit form includes its main URL, suggested from the Turkish
title only when creating with an empty path. Title changes never rewrite URLs.
`PageUrls` normalizes relative paths and rejects unsafe, reserved or occupied
paths (including aliases and soft-deleted URL records). The oldest active URL
by ULID is canonical in both applications. Editing it retains every other URL
and creates an alias for the old path; aliases redirect to the canonical path.
Selecting an already occupied alias as a new canonical path returns an explicit
validation error rather than silently moving/deleting records.

Filament database transactions are explicitly enabled on page create/edit:
page data, media relationship database writes and URL changes share the same
transaction (`afterCreate`/`afterSave` run before commit). Physical uploaded
files follow Media Library's existing lifecycle, not database rollback semantics.
The advanced URL resource remains directly accessible at `/urls` for authorized
alias maintenance but is hidden from navigation; ordinary page creation no
longer requires a separate URL workflow. No shared schema migration is needed.

The `/img/{width}/{height}` endpoint always reads the configured `public`
disk, rejects non-positive dimensions, fingerprints source files for cache
invalidation, and varies responses by `Accept` when negotiating WebP.

## Appointment contract

The frontend validates and stores pending requests; the panel manages them in
the shared `appointment_requests` table. Its schema is defined in
`database/migrations/2026_11_02_000001_create_appointment_requests_table.php`.

Records use a ULID primary key, name, phone, nullable preferred date and note,
nullable `privacy_consent_at`, a two-character locale, indexed status, and
timestamps. The appointment models in both applications share fillable fields
and casts. Update both sides when modifying this contract; do not turn a
request into a confirmed booking implicitly.

## Operational boundaries

- Both applications use the same database name and credentials, but separate
  `APP_KEY` values and session cookies. The panel example uses
  `ozancetinyol_panel_session`; shared session storage is not shared login.
- Run shared migrations here before enabling frontend appointment requests.
- Back up production data before migrations. Never use `migrate:fresh` on
  retained shared data or run destructive tests against it.
- Verify authorization, media behavior, translations, accessibility, and
  Livewire markup when changing the corresponding surfaces.

See [README.md](README.md) for setup and [AGENTS.md](AGENTS.md) for validation.
