# PlayPoolNation Core

Site plugin for playpoolnation.com. It extends the My Listing theme rather than replacing it: venues, tournaments and leagues are still My Listing listings (`job_listing` posts), and this plugin adds the pool-specific data model, trust features, SEO fixes and tooling.

## Where it runs

It is currently loaded from `wp-content/novamira-sandbox/playpoolnation-core/` by a small loader (`wp-content/novamira-sandbox/playpoolnation-core.php`). The sandbox skips the code automatically if it ever causes a fatal error. To promote it to a normal plugin, upload this folder as `wp-content/plugins/playpoolnation-core/`. Then remove the sandbox loader and activate it under Plugins. Do the removal and activation in one step, because loading both copies would declare the same classes twice.

`DEPLOYED_COMMIT` in the deployed folder names the git commit that is live.

## What it adds

| Area | Classes | Notes |
| --- | --- | --- |
| Pool data model | `Pool_Schema`, `Pool_Data`, `Venue`, `Helpers\Tri_State` | Table counts by size, brands (open list), pricing, amenities. Every attribute is Yes / No / Unknown: a term means yes, a slug in `_ppn_known_no` means no, neither means unknown. Blank counts are unknown; `0` means none. |
| Provenance | `Provenance` | Per-field source, URL and time (`_ppn_field_sources`). Lower-ranked sources never overwrite higher-ranked ones: admin 50, owner 40, official website 30, community 20, web research 15, Google Places / OpenStreetMap 10, import 5. |
| Verification | `Verification` | `_ppn_verification_status` (unverified, community, owner, admin). Badges show only when backed by data; an approved claim raises a venue to owner. |
| Locations & URLs | `Locations`, `Seo` | Regions are hierarchical: `/places/<state>/<city>/`. Venues use `/place/<name-city-st>/`. Old `/listing/…`, `/region/…`, `/find-pool-halls/` and `/add-your-hall/` URLs 301 to the new ones. |
| Leagues & events | `Play`, `Events` | Linked to venues through My Listing relations. Venues get derived "Leagues" and "Tournaments" filter terms. A daily cron keeps them current. Events use the `tournament` listing type, shown to visitors as **Events** at `/event/<slug>/` and `/events/`. Each event has a type: tournament, league sign-up, clinic, exhibition, free pool night, watch party or other. |
| Posting events | `Events` | `[ppn_post_event]` on `/post-an-event/`. Administrators and owners of a claimed venue (posting for that venue) publish instantly; everyone else's events are pending in the review queue. Events can repeat weekly, every two weeks or monthly until a chosen date. Ended events show "This event has ended", are noindexed and left out of the sitemap, and the person who posted gets one reminder email. Account menu gets "Post an event". |
| Instructors | `Instructors` | `instructor` listing type at `/instructor/<slug>/` and `/instructors/`. Fields: certifications (the four PBIA levels or other), what they teach, games, lesson formats, rate, rate notes, service area, and up to 5 venues where they teach. Sign-up uses the theme's add-listing form on `/teach/` (held for approval). The location is reduced to "City, ST" on save, and instructors get no region terms. A certification shows **Verified** only after an admin ticks it in the "Credential verification" box on the profile. Venue pages list instructors who teach there. Reviews are on (Overall, Teaching, Value). The directory stays noindexed and out of the sitemap until it has a profile. |
| Venue page | `Display`, `Listing_Config` | Shortcodes for the summary, pool tables, amenities, leagues, tournaments, trust block and Near Me. Sections with no real data are hidden. |
| Structured data | `Schema_Org` | `SportsActivityLocation`, `BarOrPub` or `BowlingAlley` for venues and `Event` for tournaments. Values are real only: no rating without reviews, no image without a photo. The theme's generic LocalBusiness markup is turned off. |
| SEO | `Seo`, `Seo_Meta` | Works with ThinkRank, not instead of it. Noindexes search, filter pages, thin regions and venue types, cart and account pages, and the Hostinger preview domain. Keeps them out of ThinkRank's sitemap. Supplies factual titles, descriptions and canonicals for state, city, venue-type and venue pages through ThinkRank's own meta. Values an admin sets in ThinkRank always win. |
| Community | `Forms`, `Moderation`, `Claims` | Suggest an edit, Add a venue (duplicate check, honeypot, signed time token, per-IP rate limit) and claim approval. Everything lands in **PlayPoolNation → Review queue** in wp-admin, which also counts pending events, pending instructor profiles and unchecked instructor certifications. |
| Ingestion | `Importer`, `Helpers\Dedupe`, `Geocoder` | CSV importer (wp-admin and `wp ppn import <file> [--source=] [--publish] [--dry-run]`). Matches by external ID, phone, website domain, distance and name similarity. Possible duplicates go to review; nothing is overwritten by a weaker source. |
| Stats | `Stats` | `[ppn_stat]`, `[ppn_metros]`, `[ppn_states]` live counts (cached, flushed on change). |

## Migrations

`Install` runs pending migrations once, on an administrator's request or with `wp ppn migrate`, and records them in the `ppn_core_migrations` option. Each migration is additive: it backs up My Listing settings it rewrites to `_ppn_backup_<meta>` and never deletes venue data.

| Key | What it does |
| --- | --- |
| `2026_10_01_tables` | Creates `wp_ppn_import_log`. |
| `2026_10_02_taxonomies` | Registers the pool taxonomies through My Listing's custom-taxonomy setting. Theme demo taxonomies are dropped only when empty. |
| `2026_10_03_terms` | Seeds table sizes, brands, pricing models, league organizations, play types, game types, venue types and amenities. |
| `2026_10_04_listing_types` | Configures the Venue, Tournament and League listing types (fields, search filters, page layout, cards). |
| `2026_10_05_backfill` | Parses addresses into city and state, assigns regions and sets readable slugs. |
| `2026_10_06_urls` | Region base `places`, listing-type permalinks and the redirect map. |
| `2026_10_07_seo` | Noindex list for utility pages. |
| `2026_10_08_pages_nav` | Places, Tournaments, Leagues, Add a Venue and List Your Venue pages, plus navigation and home copy. |
| `2026_10_09_retire_legacy` | Disables the obsolete GeoDirectory mu-plugin (file kept as `.disabled`). |
| `2026_10_10_rewrites`, `2026_10_11_city_rule` | Refreshes My Listing's cached listing-type URL bases and rewrite rules. |
| `2026_10_12_more_taxonomies`, `2026_10_13_more_terms` | Registers and seeds the event-type, instructor-credential, lesson-focus and lesson-format taxonomies. |
| `2026_10_14_events_instructors` | Relabels the tournament type as Events (`/event/`), adds the Instructor type, renames `/tournaments/` to `/events/` (301), and creates `/instructors/`, `/post-an-event/` and `/teach/`. Also updates navigation, home copy and the venue page layout. |
| `2026_10_15_type_permalinks` | Refreshes listing-type URL bases for `event` and `instructor`. |

## Tests

- Unit (no WordPress): `composer install && vendor/bin/phpunit -c tests/phpunit.xml`.
- Integration (live install, inside a rolled-back transaction, with mail and geocoding stubbed): `wp eval-file tests/integration/run.php`. As of 2026-10-03, 96/96 pass.

## Adding pool data

Only add facts with a source. Use the CSV importer with `source_url` set, or edit the venue's **Pool details** box in wp-admin, which records admin provenance. Leave a value blank when it is not known; never enter a guess.
