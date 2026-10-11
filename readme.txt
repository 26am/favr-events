=== Favr Events ===
Contributors: favrsites
Tags: events, calendar, chamber of commerce, association, ical
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Events and a community calendar for Chambers of Commerce and associations, with member-submitted events.

== Description ==

* Events with all-day, multi-day and repeating schedules (weekly, monthly by date or "2nd Tuesday").
* List, month calendar and compact views as a block or shortcode; category filters.
* Add to calendar (Google, Apple, Outlook) and a subscribable iCal feed.
* schema.org Event structured data and breadcrumbs, merged into Yoast SEO or Rank Math.
* Members submit events from their dashboard; staff approve them in one Approvals inbox.
* Works with Favr Directory (host businesses) and Favr Members (member submissions).

== Changelog ==

= 1.1.1 =
* Edit screen: the description editor is inside the event panel, on its own "Description" tab after "Date & time", instead of below the panel.
* Edit screen: WordPress's Excerpt box is replaced by a "Short summary" field on the Description tab (still saved as the excerpt; used by search engines and calendar apps).
* Edit screen: "Add event" always opens on the Date & time tab, whichever tab was used last.
* Wording: sentence case throughout wp-admin ("All events", "Add event", "Event categories", "Add category"), "Event name" as the title prompt, and "event" instead of "post" in list notices and the media window.
* Dashboard card dates read month first ("Fri, Oct 16").
* Fix: "sortable is not a function" script error on the events list, the categories screen and Event settings.
* Fix: events without a start date were counted but not shown in the events list; they are now listed first, marked "No date".
* Fix: on the event page, a description typed in the editor lost the opening tag of its first paragraph (and a one-line description was not a paragraph at all). Every paragraph is now wrapped; descriptions that carry their own paragraph tags are printed exactly as before.

= 1.1.0 =
* Events card (dates in the next 30 days) and "Add event" button on the Favr dashboard (Favr Sites plugin).

= 1.0.0 =
* First release.
