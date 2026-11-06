#!/usr/bin/env node
/**
 * Crawl of the built site, run after every build (a Screaming Frog pass before
 * each launch wave, in miniature). The build fails on:
 *  - a page without exactly one H1, or without a title or description
 *  - two indexable pages with the same title or the same H1
 *  - a canonical that isn't the page's own URL
 *  - a sitemap URL that is missing, noindex or redirected
 *  - an internal link to a page that doesn't exist
 *  - invalid JSON-LD
 *  - the old Hostinger temporary domain anywhere
 * Warnings: long titles or descriptions, indexable pages left out of the sitemaps.
 */
import fs from 'node:fs';
import path from 'node:path';

const SITE = 'https://playpoolnation.com';
const dist = path.join(process.cwd(), 'dist');
const errors = [];
const warnings = [];

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name)]));
}
const files = walk(dist);
const html = files.filter((f) => f.endsWith('.html'));
const urlOf = (f) => '/' + path.relative(dist, f).replace(/index\.html$/, '').replace(/\\/g, '/');
const decode = (s) => s.replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');
const text = (s) => decode(s.replace(/<[^>]+>/g, '')).replace(/\s+/g, ' ').trim();

const redirects = new Set(
  fs.existsSync(path.join(dist, '_redirects'))
    ? fs.readFileSync(path.join(dist, '_redirects'), 'utf8').split('\n').filter((l) => l && !l.startsWith('#')).map((l) => l.split(' ')[0])
    : [],
);
const pages = new Map();
for (const f of html) {
  const url = urlOf(f);
  if (url === '/404.html' || url.startsWith('/practice-game/')) continue; // the game's own files
  const s = fs.readFileSync(f, 'utf8');
  const title = text(/<title>([\s\S]*?)<\/title>/.exec(s)?.[1] ?? '');
  const desc = decode(/<meta name="description" content="([^"]*)"/.exec(s)?.[1] ?? '');
  const h1s = [...s.matchAll(/<h1[^>]*>([\s\S]*?)<\/h1>/g)].map((m) => text(m[1]));
  const robots = /<meta name="robots" content="([^"]*)"/.exec(s)?.[1] ?? '';
  const canonical = /<link rel="canonical" href="([^"]*)"/.exec(s)?.[1] ?? '';
  const indexable = !robots.includes('noindex');
  pages.set(url, { title, desc, h1s, indexable });

  if (h1s.length !== 1) errors.push(`${url}: ${h1s.length} H1s`);
  if (!title) errors.push(`${url}: no title`);
  if (!desc) errors.push(`${url}: no description`);
  if (title.length > 60) warnings.push(`${url}: title is ${title.length} characters`);
  if (desc.length > 160) warnings.push(`${url}: description is ${desc.length} characters`);
  if (canonical !== SITE + url) errors.push(`${url}: canonical is ${canonical}`);
  if (/hostingersite/i.test(s)) errors.push(`${url}: mentions the Hostinger temporary domain`);
  for (const m of s.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) {
    try {
      JSON.parse(m[1]);
    } catch {
      errors.push(`${url}: JSON-LD does not parse`);
    }
  }
  for (const m of s.matchAll(/href="(\/[^"#?]*)/g)) {
    const href = m[1];
    if (href.startsWith('/api/') || href.startsWith('/_astro/')) continue;
    const target = path.join(dist, href);
    const ok = fs.existsSync(target) && (fs.statSync(target).isFile() || fs.existsSync(path.join(target, 'index.html')));
    if (!ok) errors.push(`${url}: broken link ${href}`);
  }
}

for (const field of ['title', 'h1']) {
  const seen = new Map();
  for (const [url, p] of pages) {
    if (!p.indexable) continue;
    const key = field === 'title' ? p.title : p.h1s[0];
    if (!key) continue;
    if (seen.has(key)) errors.push(`duplicate ${field} on ${seen.get(key)} and ${url}: "${key}"`);
    else seen.set(key, url);
  }
}

const inSitemap = new Set();
for (const f of files.filter((x) => /sitemap-[a-z]+\.xml$/.test(x))) {
  for (const m of fs.readFileSync(f, 'utf8').matchAll(/<loc>([^<]+)<\/loc>/g)) {
    const url = m[1].replace(SITE, '');
    inSitemap.add(url);
    const p = pages.get(url);
    if (!p) errors.push(`${path.basename(f)}: ${url} has no page`);
    else if (!p.indexable) errors.push(`${path.basename(f)}: ${url} is noindex`);
    if (redirects.has(url)) errors.push(`${path.basename(f)}: ${url} redirects`);
  }
}
for (const [url, p] of pages) if (p.indexable && !inSitemap.has(url)) warnings.push(`${url}: indexable but not in a sitemap`);

const indexed = [...pages.values()].filter((p) => p.indexable).length;
for (const w of warnings) console.warn('warning: ' + w);
for (const e of errors) console.error('error: ' + e);
console.log(`checked ${pages.size} pages (${indexed} indexable, ${pages.size - indexed} noindex), ${inSitemap.size} sitemap URLs: ${errors.length} errors, ${warnings.length} warnings.`);
process.exit(errors.length ? 1 : 0);
