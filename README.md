# Favr Events

Events and a community calendar for **Chambers of Commerce** and **associations**, with
**member-submitted events** that staff approve. Part of Favr Sites, alongside
[Favr Directory](https://github.com/26am/favr-directory) and [Favr Members](https://github.com/26am/favr-members).

- **Requires:** WordPress 6.7+, PHP 8.1+. Works alone; Directory and Members add features.
- **Design:** the suite spec is in Favr Directory:
  [`docs/specs/2026-09-23-favr-suite.md`](https://github.com/26am/favr-directory/blob/main/docs/specs/2026-09-23-favr-suite.md)

## Features

**Staff (wp-admin → Events)**
- One tabbed form: *Date & time* (all-day, multi-day, repeats), *Location* (in person, online or
  both), *Tickets & details* (cost, registration link, featured) and *Host*.
- Repeats: weekly, every N weeks, monthly on a date, or monthly by position ("2nd Tuesday",
  "last Friday"), with an optional end date. Occurrences are calculated on the fly.
- List with *When* and *Host* columns, sorted by date, with Upcoming and Past views.
- Settings: events page, event URL, default view, who may submit, notifications, accent color.

**Visitors**
- The **Events** block / `[favr_events]`: a list grouped by month, a month calendar that becomes
  an agenda on phones and in narrow columns, or a compact list for sidebars and home pages.
  Category filter and plain-link navigation (works without JavaScript and caches well).
- Event pages: when, where (with map link), online link, cost, **Register** button, **Add to
  calendar** (Google or .ics), upcoming dates for repeating events, and the host.
- **iCal feed** (`/?favr_events_ical=1`, optional `&category=slug`) to subscribe in Google, Apple
  or Outlook.
- **SEO:** schema.org `Event` (per occurrence, with Place/VirtualLocation, offers, organizer) and
  a Home › Events › Event breadcrumb. Merged into Yoast SEO or Rank Math when active. Events are in
  the WordPress sitemap; filtered calendar views are `noindex`.

**Members** (with Favr Members; or "anyone with an account" in Settings)
- A **My Events** dashboard tab (or `[favr_my_events]`): submit an event, see its status, edit it.
- New events wait as *pending* in the shared **Approvals** inbox. Staff approve (publish) or
  decline with a note; the member is emailed either way.
- Once published, ticket and contact details update immediately; changes to the name, date,
  place, description or image are reviewed first (`favr_events_member_access` filter).
- Members may name only a business they represent as the host.

**With Favr Directory**
- *Hosted by* a directory business shows its card on the event, and the business profile lists
  its upcoming events.

## Page builders

| Feature | Block | Elementor widget | Shortcode |
| --- | --- | --- | --- |
| Events (list, month, compact) | Events | Events | `[favr_events]` |
| Member submissions | My Events | My Events | `[favr_my_events]` |

- **Elementor Pro Theme Builder:** dynamic tags under *Favr Events*: Event Field (date and time,
  date, time, repeats, where, venue, address, cost, host, categories…) and Event Link (register,
  join online, map, Google Calendar, .ics, host business, event page). If your single-event
  template shows these itself, turn off the automatic details panel with
  `add_filter( 'favr_events_show_details', '__return_false' );`.
- **Block themes:** bind core blocks with the `favr-events/event` source (same keys).
- Each widget has an **Accent color**.

## Developers

```bash
composer install   # also runs Strauss (favr/core → vendor-prefixed/) and copies core assets
composer test
composer lint
wp favr-events seed      # sample events
wp favr-events upcoming  # list upcoming occurrences
wp favr-events reindex   # rebuild the date index
```

- Templates in `templates/` can be overridden from `yourtheme/favr-events/`.
- Filters: `favr_events_fields`, `favr_events_tabs`, `favr_events_can_submit`,
  `favr_events_member_access`, `favr_events_schema`, `favr_events_currency`, `favr_events_email`,
  `favr_events_template`.
- Dates are stored as local strings (`start_date`, `start_time`, …) in the site time zone. Two
  indexed timestamps (`_favr_event_first`, `_favr_event_last`) let one query find every event
  overlapping a date range before occurrences are expanded (`Model\Occurrences`, unit tested).
