# Moving playpoolnation.com off WordPress, staying on Hostinger

The site is now plain files built from `data/` (Astro), served by Hostinger's regular web hosting. There is no WordPress. Two small PHP scripts in `/api/` record clicks and form submissions in Supabase.

```
push to main → GitHub Action builds the site → commits it to the `deploy` branch
             → Hostinger's Git deployment pulls `deploy` into public_html
```

Status, 7 Nov 2026: Supabase is set up. playpoolnation.com is currently served by Cloudflare Pages, which was a temporary step and is retired below.

## What moved from WordPress

- **All 107 published venues**, exported on 6 Nov 2026 with `scripts/wordpress-export.php` and converted by `scripts/import-wordpress.mjs`.
- **Only facts with a recorded source** became facts (22, across 5 venues). Everything else stays unknown, as before.
- **The same URLs**, plus 301s for every other old URL. These are generated into `public_html/.htaccess` on each build by `scripts/redirects.mjs`.
- **The practice game** (GPL-3.0), as static files.
- **Same indexing as before.** 21 city pages and 3 metro pages are in `data/launch.json`, and 2 venue pages pass the data threshold.

Dropped: accounts and My Pool, WooCommerce and promotions, events, leagues and instructors (none were published), reviews.

## The move, in order

### 1. Back up WordPress
hPanel → **Websites** → playpoolnation.com → **Backups**: make a backup, or download `public_html` and the database. Keep it until you're sure you won't need anything from the old site.

### 2. Merge the pull request that adds Hostinger deploys
On merge, the **Deploy** action builds the site and creates the `deploy` branch. Check it ran under the repo's **Actions** tab.

### 3. Put the Supabase key on the server, outside public_html
1. In Supabase, go to Project Settings → **API Keys** → Legacy API keys, and copy the `service_role` key.
2. In hPanel **File Manager**, open `domains/playpoolnation.com/`, the folder *above* `public_html`.
3. Create the file `ppn-secrets.php` there, containing:
   ```php
   <?php return ['SUPABASE_URL' => 'https://fynwiiivlhpbsvclecmk.supabase.co', 'SUPABASE_SERVICE_KEY' => 'paste-the-service_role-key'];
   ```

It's outside the web folder, so nobody can download it. Deploys never touch it.

### 4. Empty public_html
Hostinger's Git deployment only clones into an empty folder. In File Manager, rename `public_html` to `public_html-wordpress` (that's reversible), then create a new empty `public_html`. If hPanel complains that WordPress is installed, remove the WordPress *installation entry* under **WordPress → Overview**, not the backup.

### 5. Connect the Git deployment
hPanel → **Advanced** → **Git**:
- Repository: `https://github.com/couchju1/PlayPoolNation.git` (public, so no key is needed)
- Branch: `deploy`
- Directory: leave blank (that means `public_html`)

Click **Create**, then **Deploy**. Then turn on **Auto deployment**: copy the webhook URL hPanel shows, and add it in GitHub under the repo's Settings → **Webhooks** → Add webhook (content type `application/json`, just the push event). Every push to `main` then goes live within a minute or two.

### 6. Check it on Hostinger before moving DNS
Use the site's preview link in hPanel (a `*.hostingersite.com` address). The `.htaccess` only redirects playpoolnation.com and www, so the preview works as is. Open a city page, a venue page and "Show map", and send a test "Suggest an edit". It should appear in Supabase's `submissions` table.

### 7. Move DNS back to Hostinger
1. hPanel → **Domains** → playpoolnation.com → **DNS / Nameservers**. Check that the DNS zone has:
   - `A` records for `@` and `www` pointing at your hosting IP
   - `MX` records for Hostinger Mail
   - the mail `TXT` records (SPF, DKIM, DMARC)

   Compare them with the records in Cloudflare and copy over anything that's missing.
2. Set the nameservers back to Hostinger's (**Use Hostinger nameservers**). If the domain is registered somewhere else, change them there.
3. hPanel → **Security** → **SSL**: make sure the free SSL is installed for playpoolnation.com and www. It may take a few minutes after DNS moves.
4. Changes can take a few hours. Send a test email to info@playpoolnation.com afterwards.

### 8. After it's live
- Spot-check `https://playpoolnation.com/listing/amsterdam-billiards/` (should redirect) and `https://www.playpoolnation.com/` (should go to the bare domain).
- Google Search Console: submit `https://playpoolnation.com/sitemap.xml`. Bing Webmaster Tools: add the site, then run `npm run indexnow`.
- Clean up Cloudflare: delete the `playpoolnation` Pages project, remove the domain from Cloudflare, and delete the `CLOUDFLARE_API_TOKEN` secret in GitHub and the Cloudflare API token itself.
- After a few weeks, delete `public_html-wordpress` and the old WordPress database in hPanel.

## Supabase on the free plan

Free projects pause after a week with too little database activity. The site only writes when someone taps Call, Directions or Website or sends a form. While it's paused, those clicks and forms are dropped (pages keep working); restore it from the Supabase dashboard. Venue data lives in git, so it's never at risk. Consider Pro once owners are shown their lead counts.

## Open issue: Google Places data

Every venue's address, phone and hours were checked against Google Places on 2 to 3 October 2026 (`listing_source.source` is `google_places`). Google's terms allow keeping only the place ID beyond 30 days. Re-confirm those details from each venue's own site or OpenStreetMap, and update `listing_source`. `tools/enrich` already reads venue sites, so this fits the monthly re-check.
