# PlayPoolNation

A data-first directory of pool halls and bars with pool tables, built as a static site over a database we control. It replaces the WordPress + My Listing site at playpoolnation.com.

The idea: Google already answers "pool hall near me". It doesn't answer "where's a 9-foot Diamond table open after midnight in Omaha." Every page here is built from facts with a source, a checked date and a confidence level. Unknown stays blank.

## Stack

| Layer | What | Where |
| --- | --- | --- |
| Front end | Astro, static HTML, no framework runtime | `src/` |
| Data | One JSON file per venue, every fact with its source | `data/venues/` |
| Database | Supabase (Postgres) for clicks, submissions and, later, owner accounts | `db/schema.sql` |
| Review queue | Local web page, a person approves every fact | `scripts/review.mjs` |
| Fact finding | `tools/enrich` (Python): OpenStreetMap, league operators, venue sites | `tools/enrich/` |
| Maps | MapLibre + OpenStreetMap tiles, loaded only on "Show map" | `src/scripts/site.ts` |
| Search | In the browser over `/data/venues.json` (move to Typesense past a few thousand venues) | `src/scripts/search.ts` |
| Hosting | Cloudflare Pages, with Pages Functions for `/api/event` and `/api/submit` | `functions/`, `wrangler.toml` |
| Analytics | First-party click table (calls, directions, website taps), optional Plausible | `functions/api/event.ts` |

## Commands

```bash
npm install
```

```bash
npm run dev
```

```bash
npm test
```

```bash
npm run build
```

`npm run build` builds every page, writes `dist/_redirects` (every old WordPress URL to its new page), then crawls the result and fails on duplicate titles or H1s, broken internal links, pages without exactly one H1, and sitemap URLs that are noindex or redirect.

```bash
npm run review
```

Opens the review queue at http://localhost:5178. It shows what `tools/enrich` found next to what the site has now, with the source link and quote. Approving writes the fact into `data/venues/<slug>.json`. Commit and push, and the next deploy publishes it.

```bash
npm run indexnow
```

After a deploy, tells Bing and the other IndexNow engines about every URL in the sitemaps (or pass paths to send only those).

## Which pages exist and which are indexed

All in `src/lib/data.ts`:

- **City pillar page** `/places/<state>/<city>/`: a city with 2+ venues. Indexed once the city is listed in `data/launch.json`. This is the main ranking asset.
- **Attribute hub** `/places/<state>/<city>/<hub>/` (9-foot tables, pool leagues, free pool, open late, snooker): only when 2+ venues qualify on sourced facts, and not every venue in the city. Otherwise there is no page (404).
- **Venue page** `/place/<slug>/`: always built, `noindex` until it has an address, a phone or website, hours, and 2 of the 3 table facts (count, sizes, brands). An assumed 7 ft default doesn't count. It's indexed on the next build with nothing to switch on.
- **State page** `/places/<state>/`: indexed with 2+ venues. **Metro page** `/places/<metro>/` for metros set `"page": true` in `data/metros.json`.
- **Search** `/search/` and **Suggest an edit** are always noindex and never in a sitemap.

Sitemaps are split by type (`/sitemap-cities.xml`, `-hubs`, `-listings`, `-states`, `-pages`), with `/sitemap.xml` as the index, so Search Console shows which template has indexing trouble.

## Adding a city (one wave)

1. `python3 tools/enrich/enrich.py run <city>`. It reads our listings from `data/venues/`, discovers venues and reads their sites.
2. `npm run review`. Approve or reject every fact and new venue.
3. When the city's data is complete, add it to `data/launch.json`.
4. `npm run build`, commit, push. After the deploy, `npm run indexnow -- /places/<state>/<city>/`.
5. Watch Search Console for 3 to 4 weeks before the next wave of 5 to 10 cities.

Monthly: `python3 tools/enrich/enrich.py recheck <city>`, then `npm run review` for whatever changed.

## Folders

```
src/lib/          data rules, text from facts, open/closed, schema.org, sitemaps (unit tested)
src/pages/        routes
src/components/   page parts
src/scripts/      what the browser adds: open status, click counts, filters, map, Tonight panel, search
functions/api/    Cloudflare Pages Functions: /api/event, /api/submit
data/venues/      the venues, one file each
data/launch.json  launched (indexed) cities
db/               Supabase schema and generated seed
scripts/          import from WordPress, review queue, redirects, build check, IndexNow, Supabase seed/pull
tools/enrich/     fact finding
wordpress/        the old site's plugin, kept until the cutover is done
docs/             cutover plan and history
```

See [docs/astro-cutover.md](docs/astro-cutover.md) for moving the domain off WordPress.
