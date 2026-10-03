# Directory relaunch (October 2026)

> **Platform upgrade (3 October 2026).** Most custom behaviour now lives in the
> `playpoolnation-core` plugin. See `wordpress/playpoolnation-core/README.md`.
> Several URLs below changed, and each old URL 301-redirects to its new one:
> `/find-pool-halls/` → `/places/`, `/add-your-hall/` → `/add-a-venue/`,
> `/listing/<slug>/` → `/place/<name-city-st>/`, `/region/<x>/` → `/places/<state>/<city>/`.
> Owners list their own venue at `/list-your-venue/`.

The live site at playpoolnation.com runs on **WordPress** (Hostinger) with the
**My Listing** theme and **Elementor Pro**. The directory was rebuilt there,
not in the Next.js app in this repo.

## What changed on the live site

### Directory page: `/find-pool-halls/` (page 1669)
- Elementor page built from native widgets plus My Listing's Explore widget
  (list + map + filters, template "explore-1").
- Sections: felt-green hero with live counts, search/map app, browse-by-state
  list, owner call to action.
- Set as the theme's Explore page (`options_general_explore_listings_page`).

### Add-listing page: `/add-your-hall/` (page 1670)
- Intro band plus My Listing's Add Listing widget (package step, then form).
- Set as the theme's Add Listing page and header CTA target.

### Listings
- 97 real venues across 22 metros / 18 states, imported as `job_listing`
  posts of listing type `place` ("Venues").
- Candidates came from web research; every one was checked against Google
  Places and only kept if Google reported it `OPERATIONAL`. 11 candidates were
  dropped (permanently/temporarily closed, or the address now belongs to a
  different business).
- Each listing has address + map pin, phone, website, weekly hours (drives the
  "Open now" filter), state (region taxonomy), and category.
- Descriptions are a neutral template inviting owners to claim the listing.
  No amenity data was invented.
- Review list: [`venues-imported.csv`](venues-imported.csv). Each row carries
  `_ppn_place_id` / `_ppn_verified` post meta on the site.

### Listing type "Venues" (case27_listing_type 1589)
- Explore filters: name search, location + radius, type, state, open now,
  amenities, sort (A to Z default, nearby, top rated, newest).
- Review categories: Overall, Table condition, Service, Value.
- Categories: Pool Halls, Bars with Pool Tables.
- Amenity tags (replaced theme demo car tags): Full Bar, Kitchen, Darts,
  Leagues, Tournaments, Cue Rental, Pro Shop, Free Parking, Open Late,
  Smoke-Free, All Ages, Free Pool Night.
- Previous search config backed up in post meta `_ppn_backup_search_page`.

### Demo cleanup (all trashed items are restorable from wp-admin Trash)
- 40 GeoDirectory demo places, 10 Lorem Ipsum blog posts.
- Pages for inactive GeoDirectory / UsersWP plugins.
- Demo regions (Amsterdam, London, Prague, Rome) and demo tags.
- Main menu rebuilt (Find Pool Halls, Add Your Hall, My Account);
  footer social links to the theme author's accounts removed; `gd-menu` deleted.
- Global header (Elementor 675) now uses the Main Menu.
- Global footer (Elementor 669) rebuilt; the old demo footer is backed up in
  post meta `_ppn_backup_elementor_data`.

## Design direction: "Rail & Chalk"
Saved and active in the site's Novamira design library.

| Role | Value |
| --- | --- |
| Page ground (bone) | `#F2F4F1` |
| Ink | `#14201B` |
| Felt / felt-deep | `#0E3B2D` / `#0A2A20` |
| Accent (chalk blue) / hover | `#2F6FE4` / `#245BC2` |
| Headings | Archivo 800 |
| Body | Figtree 400/600 |

Applied site-wide: My Listing brand color `#2F6FE4` and background
`#F2F4F1` (Theme Options), Elementor kit 676 global colors and fonts, and
site-wide CSS in the kit's Custom CSS (fonts, buttons, listing cards).
The previous kit settings are backed up in post meta `_ppn_backup_page_settings`.

## Home page (page 1576)
Rebuilt in Elementor; the old demo layout is backed up in post meta
`_ppn_backup_elementor_data`. Sections:
- Hero with live counts and My Listing's basic search (submits to the directory).
- "Rooms players travel for": listing-feed carousel of 10 hand-picked halls.
- "Built for the night you want to play": counts plus what the directory does.
- "Pick a city": 22 metro links that open the directory centered on that city
  (`search_location`, `lat`, `lng`, `proximity`, `sort=nearby`). The counts
  beside each city are static and need updating as listings are added.
- Owner call to action.

## Listings are free
- Packages are disabled for the Venues type (previous settings backed up in
  `_ppn_backup_settings_page`), so submissions and claims cost nothing.
- New submissions and claims still require admin approval.
- Listing duration is unlimited (`job_manager_submission_duration` empty).
- Every imported listing has a tagline such as "Pool hall in Chicago, IL".

## Decisions
- Google Maps API key in My Listing settings is the owner's own key.

## Logo (October 2026)
Map pin holding a striped 9-ball, with the wordmark PlayPool + **Nation** in
Archivo ExtraBold (type converted to outlines, so no font is needed).
Source files are in [`../brand/`](../brand/):
- `playpoolnation-logo.svg` / `.png`: horizontal logo for light grounds
- `playpoolnation-logo-reversed.svg` / `.png`: for dark grounds
- `playpoolnation-mark.svg`: pin mark only
- `playpoolnation-icon.svg` / `-512.png`: square app/browser icon

On the site: header logo (custom logo, attachment 1836), footer reversed logo
(1837), site icon (1838). Copies live in `wp-content/uploads/ppn-brand/`.
Listings without photos use a branded felt cover (attachment 1843, set as the
Venues type's `default_cover_image`).

## Sioux Falls, SD
10 venues added and verified as operating with Google Places:
Rack City Billiards, JJ's Billiards & Darts, Bigs Bar, Nickel Spot, Lucky's,
Lucky's on Louise, Thirsty Duck, Tommy Jack's Pub, Upper Cut Bar & Grill,
Gibs Sports Bar. Not added: Detour Bar (closed permanently) and Gateway
(the Google listing name doesn't match). Silver Moon Bar was imported, then
trashed because the location could not be confirmed.

## Live counts
`wp-content/novamira-sandbox/ppn-stats.php` registers shortcodes:
- `[ppn_stat type="venues|states|metros"]`: live numbers (6-hour cache, cleared
  when a listing is saved or trashed).
- `[ppn_metros]`: city links with live counts; metros are defined in the
  `ppn_metros` option as [label, search_location, lat, lng, radius_miles].
- `[ppn_states]`: state links with counts (directory page).

## Home page v2
Full-bleed photo hero with a single-row search, featured-hall carousel,
editorial split with photo, city links over a pool hall photo, owner section
with photo. Photos are from Unsplash (Unsplash License: free commercial use,
no attribution required), stored in the Media Library (attachments
1839-1842, with the photo ID in each caption). Previous layout backed up in
post meta `_ppn_backup_elementor_data_v2`.

## Platform upgrade (3 October 2026)

- **Pool data with Yes / No / Unknown.** Covers table counts by size, brands, pricing, amenities, leagues and tournaments. Unknown is never shown as "no".
- **New listing types.** Tournaments (converted from the theme's Event type) and Leagues (new), linked to venues, with `/tournaments/` and `/leagues/` pages.
- **Community tools.** Claims need admin approval. Suggest an edit and Add a venue go to **PlayPoolNation → Review queue**. Saved Places uses the theme's bookmarks.
- **SEO.**
  - Readable venue URLs; state and city pages at `/places/<state>/<city>/` with their own titles, descriptions and canonicals.
  - Filter pages, thin regions and utility pages are noindexed and kept out of ThinkRank's `/sitemap.xml`, which now lists 155 URLs.
  - One accurate JSON-LD block per venue.
- **Ingestion.** CSV importer with duplicate detection and per-field provenance; a weaker source never overwrites a stronger one.

### Pool data added from published sources

Only exact figures were stored. Where the sources disagreed or gave no number, the field was left unknown.

| Venue | Stored | Source |
| --- | --- | --- |
| Rack City Billiards, Sioux Falls SD | 18 tables: 16 at 7', 2 at 9'; Diamond and Valley; darts | Venue's own description on [FindTourneys](https://www.findtourneys.com/poolhall.php?id=2137) |
| Bigs Bar, Sioux Falls SD | 12 tables; hourly pricing ($8 for 1½ hours); darts | [bigsbar.com](https://www.bigsbar.com/billiards-darts) |
| Bangin Ballz Billiards Bar, Las Vegas NV | 7' and 9' tables; Rasson and Diamond; hourly rates. Total left unknown because the rate card lists Diamond tables beyond the 30 Rasson tables. | [banginballzbilliards.com](https://banginballzbilliards.com/) |
| Buffalo Billiards, Philadelphia PA | Hourly, per-player rates | [buffalobilliardsphilly.com](https://www.buffalobilliardsphilly.com/) |
| Pockets Billiards & Brew, San Diego CA | Hourly, from $9 | [pocketsbilliardsandbrew.com](https://www.pocketsbilliardsandbrew.com/) |
