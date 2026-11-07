#!/usr/bin/env node
/**
 * Tell Bing (and the other IndexNow engines) which URLs changed. Bing feeds part
 * of AI search, so this is worth the minute it takes. After a deploy:
 *   npm run indexnow                      # every URL in the built sitemaps
 *   npm run indexnow -- /places/texas/austin/ /place/some-venue/   # just these
 * The key file is public/a6bf02c6884073b43676be55840624f3.txt (it has to be public).
 */
import fs from 'node:fs';
import path from 'node:path';

const SITE = 'https://playpoolnation.com';
const KEY = 'a6bf02c6884073b43676be55840624f3';
const dist = path.join(process.cwd(), 'dist');
let urls = process.argv.slice(2).map((u) => (u.startsWith('http') ? u : SITE + u));
if (!urls.length) {
  for (const f of fs.readdirSync(dist).filter((x) => /^sitemap-[a-z]+\.xml$/.test(x))) {
    urls.push(...[...fs.readFileSync(path.join(dist, f), 'utf8').matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]));
  }
}
if (!urls.length) {
  console.error('No URLs. Run npm run build first, or pass paths.');
  process.exit(1);
}
const res = await fetch('https://api.indexnow.org/indexnow', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json; charset=utf-8' },
  body: JSON.stringify({ host: new URL(SITE).host, key: KEY, keyLocation: `${SITE}/${KEY}.txt`, urlList: urls.slice(0, 10000) }),
});
console.log(`IndexNow: ${res.status} ${res.statusText} for ${urls.length} URLs.`);
process.exit(res.ok ? 0 : 1);
