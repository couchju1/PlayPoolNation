# Moving playpoolnation.com off WordPress

The new site is built and checked. The WordPress site keeps running until the domain points at Cloudflare Pages. Nothing on WordPress has been changed.

## What moved

- **All 107 published venues**, exported from WordPress on 6 Nov 2026 with `scripts/wordpress-export.php` and converted by `scripts/import-wordpress.mjs`. Addresses, phones, websites, coordinates, weekly hours and time zones are as WordPress had them.
- **Only facts with a recorded source** became facts (22, across 5 venues), each with its source URL and date. Everything the old site showed without a source stays unknown, as before.
- **The same URLs.** `/place/<slug>/`, `/places/<state>/<city>/`, `/places/<state>/`, the three metro pages, `/practice/`, `/add-a-venue/`, `/list-your-venue/`, `/privacy-policy/`, `/terms-of-service/`.
- **301s for everything else**, in `dist/_redirects` (generated on each build): old `/listing/…` URLs, one-venue city pages (to the state, or to the metro page that covers them), `/find-pool-halls/`, `/add-your-hall/`, `/region/…`, `/category/…`, events, leagues, instructors, My Pool, account, shop and cart pages.
- **The practice game** (tailuge/billiards, GPL-3.0) is copied as static files with its license and source zip. It loads only when Play is pressed.
- **Same indexing as before.** The 21 city pages and 3 metro pages that were in the WordPress sitemap are in `data/launch.json`. The 2 venue pages that passed the data threshold are still the only indexed venue pages.

## What was dropped

Accounts and My Pool, WooCommerce, the paid promotions (they were never switched on), events, leagues and instructors (none were published), reviews, and the theme's 75 scripts per venue page. Leagues come back as facts on venues through the review queue.

## Before the switch

1. **Supabase.** Create a project, run `db/schema.sql` in the SQL editor, then `npm run db:seed-sql` and run `db/seed.sql`.
2. **Cloudflare Pages.** Connect the GitHub repo. Build command `npm run build`, output `dist`, Node 22. Add the environment variables `SUPABASE_URL` and `SUPABASE_SERVICE_KEY` (as a secret). Optionally `PUBLIC_PLAUSIBLE_DOMAIN`.
3. **Rate limit** `/api/submit` and `/api/event` with a Cloudflare rate-limiting rule (for example 20 requests a minute per IP).
4. **Check the preview URL.** Open a city page, a venue page, "Show map", the Tonight panel, and send yourself a test "Suggest an edit". It should appear in the `submissions` table.
5. **Review the legal pages.** `/privacy-policy/` and `/terms-of-service/` were rewritten for a site with no accounts, no cookies and no shop. They name Cloudflare and Supabase as providers, so keep them true.
6. **Mail.** Moving DNS to Cloudflare must keep the MX and TXT records for info@playpoolnation.com (Hostinger Mail). Copy every existing DNS record before you change nameservers.

## The switch

1. Add `playpoolnation.com` and `www` as custom domains in the Pages project, and let Cloudflare move DNS (or point the A/CNAME records at Pages). Add a redirect rule from `www.playpoolnation.com/*` to `https://playpoolnation.com/$1` so there is one canonical host.
2. When it's live, crawl it once: `npm run build` already ran the checks, but also spot-check that old URLs like `/find-pool-halls/` and `/listing/amsterdam-billiards/` redirect.
3. In Google Search Console, submit `https://playpoolnation.com/sitemap.xml` (same URL as before, now an index of the split sitemaps).
4. Set up Bing Webmaster Tools, submit the sitemap, then run `npm run indexnow`.
5. Keep the Hostinger plan for a month in case you need to look something up, then cancel it.

## Open issue: Google Places data

Every venue's address, phone and hours were checked against Google Places on 2 to 3 October 2026 (`listing_source.source` is `google_places`). Google's terms allow keeping the place ID indefinitely but other Places content for only 30 days, and that window has passed. Before or soon after launch, re-confirm those details from each venue's own site or OpenStreetMap and update `listing_source`. `tools/enrich` already reads venue sites, so this fits the monthly re-check.
