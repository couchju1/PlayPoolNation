# tools/enrich

Builds the data nobody else has: for every venue, the table count, table sizes, table brands, pricing model, league nights and free-pool nights, each with a source. It also finds venues we're missing. It runs here (in a checkout, from Claude Code or a terminal), never inside WordPress. Its output is imported for review, never published straight from the scrape.

Python 3.10+ standard library only. `pdftotext` (poppler-utils) is used for PDFs when present.

## Run it

```sh
python3 tools/enrich/enrich.py run sioux-falls     # listings, discovery, enrichment for one city
python3 tools/enrich/enrich.py run --next          # next unfinished city in keyword-report order
python3 tools/enrich/enrich.py cities              # the order, and which cities are done
python3 tools/enrich/enrich.py recheck sioux-falls # monthly: re-read sources, flag changes and closures
python3 tools/enrich/enrich.py verify sioux-falls  # every fact's source URL still answers
python3 tools/enrich/enrich.py progress            # rebuild progress.md
```

Then on the server, from the checkout:

```sh
wp ppn enrich import sioux-falls   # loads tools/enrich/out/sioux-falls/ into the review queue
```

and review under **PlayPoolNation → Review** in wp-admin.

Options: `--limit N` stops after N venues; `--delay S` sets the seconds between requests to one domain (default 4); `--rediscover` runs Step A again; `--listings FILE` uses `wp ppn enrich export <city>` output instead of reading the public site.

## Order of work

Cities come from the latest `data/reports/keyword-report-*.md` ("Cities by search volume"), then any extra cities in `cities.json`. A city is finished before the next starts (`run --next` resumes the unfinished one first). Inside a city, pool halls go first, then bars; our own listings go before newly found venues.

## Step A: discovery

- **OpenStreetMap** through the Overpass API. There is one query per city, inside the city's administrative boundary: `sport=billiards|pool|snooker` (this includes `leisure=sports_centre`), `billiards=yes`, names with "billiard" or "pool hall", and bars or pubs whose description mentions pool tables or billiards. The same query also returns every bar and pub. Those don't become new venues; they only fill in a missing website or phone on a listing we already have (`osm-listing-links.json`). The query sends the User-Agent below, uses one request per city, and on 429/504 or an unreachable server the run pauses (exit 75). Run it again later to resume.
- **Google Places** Text Search ("pool hall in <city>", "billiards in <city>") runs only when `GOOGLE_PLACES_API_KEY` is set in the environment, `tools/enrich/.env` or the repo `.env`. Google's terms let us keep the place ID indefinitely, but other content for at most 30 days. Each Places candidate carries `places_expires`, and every run strips the details from expired ones (`store.purge_places`) and keeps the place ID.
- **League operators**' public host-location pages, listed per city in `cities.json` (`league_pages`). Lines that pair a venue name with a street address become candidates. Lines that name one of our venues are read for league nights.
- Candidates are merged across sources and matched against our listings by normalized name, street address, phone and distance (`ppn_enrich/match.py`; same scoring idea as the plugin's `Helpers\Dedupe`). Only non-matches go to `out/<city>/new-venues.json`, and possible matches carry a "may already be listed" flag. Nothing is created on the site.

## Step B: enrichment

For each venue, the sources are read in this order:

1. The venue's own website: the home page, then up to 8 linked pages on the same site whose address or link text looks like about, games, pool, tables, menu, events, leagues, specials, hours or pricing, plus linked PDFs of menus, specials or events.
2. The venue's own Facebook page, but only if it can be read without logging in. Facebook's robots.txt shuts out crawlers, so in practice this is skipped and recorded as such.
3. League operator pages (the facts gathered in Step A).

Each request checks the site's robots.txt first, as user-agent `PlayPoolNationBot` (an unreachable robots.txt means stay out). Requests to one domain are spaced 4+ seconds apart. The User-Agent is `PlayPoolNationBot/1.0 (+https://playpoolnation.com/; info@playpoolnation.com)` and the `From:` header is `info@playpoolnation.com`. The tool never reads Google Maps, Google search results, Yelp, or any site that needs a login, and it uses no third-party scrapers.

Only these fields are extracted (`ppn_enrich/extract.py`, plain rules, no AI calls): `table_count`, `table_sizes` (7/8/9 ft, snooker, with counts when stated), `table_brands`, `pricing_model` (coin-op, hourly with rate, per game with price, free pool with days), `league_nights` (league + weekday), `age_policy`, `food`, `full_bar`, and `venue_status` for closures.

Every fact has `value`, `source_url`, `quote_snippet` (under 20 words, for review only and never shown on the site), `checked_at` and `confidence`:

- **stated**: the page says it outright ("18 pool tables", "APA league Tuesdays", "free pool Sundays").
- **implied**: it goes to review with a flag. Examples: "over 20 tables", a league with no weekday, "bar-size tables" read as 7 ft, cocktails on the menu read as a full bar.

The tool doesn't guess. "Lots of tables" leaves `table_count` empty. When sources disagree, the better source wins (own site, then Facebook, then league pages), the other value is kept under `conflicts`, and the fact is flagged.

Pages and PDFs are never kept. A PDF exists in a temp file only while `pdftotext` reads it. Each venue ends as one `out/<city>/<venue-slug>.json` of a few KB.

## Crashes and pauses

State lives in `out/<city>/_state.json`, and every file is written atomically. A venue is marked done only after its file is on disk, so after a crash, a Ctrl-C or a usage-limit pause, the same command skips finished venues and redoes at most the one in flight. `PPN_ENRICH_CRASH_AFTER=N` kills the process after N venues, for testing exactly that.

## Step D: keeping it current

`recheck <city>` re-reads every venue's sources, monthly. The venue file is rewritten with `previous_checked_at` and a `changes` list (new, changed and gone facts; a fact counts as gone only when its own page was read again and no longer says it). Closures ("permanently closed" on their own site) arrive as `venue_status` facts. `wp ppn enrich import` then reopens any item you had approved or rejected whose value has changed, with a note saying what it was. Unchanged items stay decided.

## Files

```
cities.json                 per-city settings (league pages, OSM boundary name)
progress.md                 venues done, facts per field, hit rates, run log
out/<city>/_state.json      resume state
out/<city>/listings.json    our listings for the city (from the public site or the export)
out/<city>/new-venues.json  venues found in Step A that we don't list
out/<city>/<slug>.json      facts for one venue
out/<city>/league-facts.json, osm-listing-links.json, _candidates.json   Step A working files
tests/                      python3 -m unittest discover tools/enrich/tests
```
