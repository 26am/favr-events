# CLAUDE.md

## What this is

**Favr Events** (namespace `FavrEvents\`, data prefix `favr_event_`) is part of the Favr Sites
suite. The suite spec lives in Favr Directory: `docs/specs/2026-09-23-favr-suite.md`. PHP 8.1+,
WP 6.7+.

## Commands

```bash
composer install   # also runs Strauss → vendor-prefixed/ and copies favr/core assets → assets/core/
composer test      # PHPUnit (+ Brain Monkey where needed)
composer lint      # WPCS, keep at 0 errors
```

Local test site: `http://sermonator-test.local/`, with this repo symlinked to
`wp-content/plugins/favr-events`. `wp favr-events seed` creates sample events.

## Architecture

- **Fields** are declared once in `Model\Fields` (favr/core `FieldSet`) and drive the admin panel,
  the member form, sanitizing and meta registration. `host_business` is stored as a listing id and
  becomes a select only in forms (`Fields::forForm()`), so no listing query runs on every request.
- **Schedule:** local date/time strings; `Model\Event::bounds()` derives the first start/end and
  `Model\Occurrences` expands repeats (pure, unit tested). `Event::reindex()` keeps
  `_favr_event_first`/`_favr_event_last` current. Call it after any direct meta write.
- **Queries:** `Model\Repository::occurrences( $from, $to, $args )` returns occurrences in order.
- **Front end:** `Frontend\Calendar` (list/month/compact), `Frontend\Single` (details added via
  `the_content`, so any theme works), `Frontend\Ical`, `Frontend\Seo`.
- **Submissions:** `Editing\Submissions` (form, list, save), `Editing\Items` (where each item is
  stored and the edit/review policy), `Editing\Queues` (Approvals providers), `Editing\UploadRoute`.
- **Optional integrations** go through WordPress filters only: Favr Members
  (`favr_members_is_active`, `favr_members_dashboard_tabs`) and Favr Directory
  (`favr_directory_listings_for_user`, `favr_directory_after_profile`, `favr_directory_login_url`).
- **Shared code:** import from `FavrEvents\Vendor\FavrCore\…`. Never edit `vendor-prefixed/`.
