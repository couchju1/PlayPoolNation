# Directory relaunch (October 2026)

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

The chalk-blue accent is applied on the new pages only, through the theme's
`--accent` variable in page CSS. The theme-wide brand color is still the
demo purple `#6c1cff` (Theme Options) and should be switched to `#2F6FE4`
if the rest of the site is restyled.

## Open items
- Listing packages (Basic $7.90 / Advanced $19.90 / Premium $29.90) are the
  theme's defaults; decide pricing and confirm WooPayments is set up.
- Confirm the Google Maps API key in My Listing settings belongs to this site's
  Google Cloud project.
- Home page (Elementor 1576) still contains theme demo sections.
