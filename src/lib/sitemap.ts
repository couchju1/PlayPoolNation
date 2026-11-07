/** Sitemaps split by page type, so Search Console shows which template has indexing trouble. Indexed pages only. */
import { SITE, cityPages, hubsFor, metroPages, states, venues, MIN_CITY_VENUES } from './data.ts';
import type { Venue } from './types.ts';

const last = (list: Venue[]) => list.map((v) => v.lastChecked || v.updated_at).sort().pop() || '';

export const SITEMAPS: Record<string, () => { loc: string; lastmod: string }[]> = {
  pages: () => ['/', '/places/', '/practice/', '/how-we-check/', '/add-a-venue/', '/list-your-venue/', '/privacy-policy/', '/terms-of-service/'].map((loc) => ({ loc, lastmod: loc === '/' || loc === '/places/' ? last(venues()) : '' })),
  states: () => states().filter((s) => s.venues.length >= MIN_CITY_VENUES).map((s) => ({ loc: s.url, lastmod: last(s.venues) })),
  cities: () => [
    ...cityPages().filter((c) => c.launched).map((c) => ({ loc: c.url, lastmod: last(c.venues) })),
    ...metroPages().filter((m) => m.launched).map((m) => ({ loc: m.url, lastmod: last(m.venues) })),
  ],
  hubs: () => cityPages().filter((c) => c.launched).flatMap((c) => hubsFor(c).map((h) => ({ loc: h.url, lastmod: last(h.venues) }))),
  listings: () => venues().filter((v) => v.indexable).map((v) => ({ loc: v.url, lastmod: v.lastChecked || v.updated_at })),
};

export function urlset(entries: { loc: string; lastmod: string }[]): string {
  const body = entries.map((e) => `  <url><loc>${SITE}${e.loc}</loc>${e.lastmod ? `<lastmod>${e.lastmod}</lastmod>` : ''}</url>`).join('\n');
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${body}\n</urlset>\n`;
}

export function index(): string {
  const body = Object.keys(SITEMAPS).map((k) => `  <sitemap><loc>${SITE}/sitemap-${k}.xml</loc></sitemap>`).join('\n');
  return `<?xml version="1.0" encoding="UTF-8"?>\n<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${body}\n</sitemapindex>\n`;
}
