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
| Hosting | Hostinger web hosting: static files, `.htaccess` (generated), two PHP scripts for `/api/event` and `/api/submit` | `public/api/`, `scripts/redirects.mjs` |
| Deploys | GitHub Action builds on push to `main` and commits the result to the `deploy` branch, which Hostinger's Git deployment pulls | `.github/workflows/deploy.yml` |
| Analytics | First-party click table (calls, directions, website taps), optional Plausible | `public/api/event.php` |
| The Wall | Player check-ins and posts on venue pages, live feeds on city pages, the homepage and `/live/`. No accounts; posts table in Supabase, reached only through PHP | `public/api/post.php`, `feed.php`, `react.php`, `src/scripts/wall.ts` |

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

`npm run build` builds every page, writes `dist/.htaccess` (every old WordPress URL to its new page, https and www, the `/api/` routes), then crawls the result and fails on duplicate titles or H1s, broken internal links, pages without exactly one H1, and sitemap URLs that are noindex or redirect.

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
4. `npm run build`, commit, push to `main` (it deploys itself). Then `npm run indexnow -- /places/<state>/<city>/`.
5. Watch Search Console for 3 to 4 weeks before the next wave of 5 to 10 cities.

Monthly: `python3 tools/enrich/enrich.py recheck <city>`, then `npm run review` for whatever changed.

## Folders

```
src/lib/          data rules, text from facts, open/closed, schema.org, sitemaps (unit tested)
src/pages/        routes
src/components/   page parts
src/scripts/      what the browser adds: open status, click counts, filters, map, Tonight panel, search
public/api/       PHP for Hostinger: /api/event, /api/submit, and the Wall's /api/post, /api/feed, /api/react (Supabase key in ppn-secrets.php above public_html)
data/venues/      the venues, one file each
data/launch.json  launched (indexed) cities
db/               Supabase schema and generated seed
scripts/          import from WordPress, review queue, redirects, build check, IndexNow, Supabase seed/pull
tools/enrich/     fact finding
wordpress/        the old site's plugin, kept until the cutover is done
docs/             cutover plan and history
```

See [docs/astro-cutover.md](docs/astro-cutover.md) for the move from WordPress to the static site on Hostinger.

## The Wall

Players post check-ins and updates on venue pages ("Tables open", "League night", a note up to 280 characters), and can cheer each other's posts. City pages, the homepage and `/live/` show the latest posts. There are no accounts: a display name is kept in the visitor's browser.

Guard rails, all in `public/api/post.php`: no links, a short blocklist, unknown venues refused, 3 posts per visitor per 10 minutes and 15 per day (counted under a salted hash, the address is never stored), a hidden field and a minimum time on the page for bots. Three reports hide a post. To take one down by hand, set its `status` to `hidden` in the `posts` table.

The `posts` table and the `post_react` function are in `db/schema.sql`; run that part once in the Supabase SQL editor before the Wall goes live.

## Pool Passport

`/passport/` collects a stamp for every venue a player has played, grouped by city guide, with each city's poster turning from grey to full color once it has a stamp and a "Complete" seal when every hall is stamped. Stamps come from "I played here" on venue pages or a Wall check-in (any kind but a plain note). They live in the player's browser only (`localStorage` key `ppn_passport`, see `src/scripts/passport.ts`); nothing is sent to the server.

## Accounts and reviews

Players create an account at `/account/` (email and password through Supabase Auth, email confirmed by a link) and review venues: 1 to 5 stars and up to 2,000 characters, one review per player per venue, editable and deletable. Venue pages show a ratings card (average, count, spread) and the reviews; city pages show each venue's average. Display names are public; emails never are. Players can delete their account and reviews from `/account/`.

**Sharing reviewer emails with venues.** Each review has a box, unticked by default: "Share my email with <venue> so they can contact me about events and offers." Players can untick it later on `/account/`. Only reviews with it ticked show up in:

```sql
select * from private.review_email_leads where venue_slug = 'rack-city-billiards-sioux-falls-sd';
```

Run that in the Supabase SQL editor and use "Export to CSV" to send a venue its list. The `private` schema isn't reachable through the public API. An owner with an approved claim can also fetch their own venue's list with `rpc('venue_review_leads', { p_slug })`. The privacy policy describes all of this.

**Supabase settings this needs** (Authentication in the dashboard):
- URL Configuration: Site URL `https://playpoolnation.com`, redirect URL `https://playpoolnation.com/account/**`.
- SMTP: a real mailbox (for example `noreply@playpoolnation.com` on Hostinger: `smtp.hostinger.com`, port 465). Without it Supabase sends at most 2 emails an hour, which blocks sign-ups.

The tables, policies and functions are in `db/schema.sql` (profiles, reviews, `private.review_email_leads`, `venue_review_leads`, `delete_my_account`). The browser uses the project's publishable key (`src/scripts/sb.ts`); row-level security decides what it can do.
