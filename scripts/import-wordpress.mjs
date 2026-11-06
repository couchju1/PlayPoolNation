#!/usr/bin/env node
/**
 * One-time move of the WordPress venues into data/venues/<slug>.json.
 *
 *   node scripts/import-wordpress.mjs <export.json>
 *
 * <export.json> comes from scripts/wordpress-export.php, run on the old site
 * (wp eval-file scripts/wordpress-export.php > export.json). Only facts the old
 * site had a recorded source for become facts here. Everything else stays
 * unknown, the same rule the plugin used.
 *
 * Existing venue files are left alone unless you pass --force, so facts
 * approved here after the move are never overwritten by the old site.
 */
import fs from 'node:fs';
import path from 'node:path';

const [, , file, ...flags] = process.argv;
if (!file) {
  console.error('Usage: node scripts/import-wordpress.mjs <export.json> [--force]');
  process.exit(1);
}
const force = flags.includes('--force');
const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const outDir = path.join(root, 'data/venues');
fs.mkdirSync(outDir, { recursive: true });

const data = JSON.parse(fs.readFileSync(file, 'utf8'));

const SIZE = { '7-foot': '7ft', '8-foot': '8ft', '9-foot': '9ft', snooker: 'snooker', carom: 'carom' };
const COUNT_SIZE = { 'tables-7ft': '7ft', 'tables-8ft': '8ft', 'tables-9ft': '9ft', 'tables-snooker': 'snooker', 'tables-carom': 'carom' };
const BRANDS = { diamond: 'Diamond', valley: 'Valley', brunswick: 'Brunswick', rasson: 'Rasson', predator: 'Predator', olhausen: 'Olhausen', gandy: 'Gandy' };
const GAMES = { darts: 'Darts', arcade: 'Arcade' };
const SOURCE_TYPE = { official_website: 'official_website', web_research: 'web_research', admin: 'admin', owner: 'owner', community: 'community', default: 'default' };

function fact(field, key, value, src, extra = {}) {
  return {
    field,
    key,
    value,
    source_url: src.url || '',
    source_type: SOURCE_TYPE[src.source] || 'web_research',
    checked_at: src.at || '',
    confidence: 'stated',
    flags: [],
    approved_at: src.at || '',
    approved_by: 'wordpress-import',
    ...extra,
  };
}

function venueFacts(v) {
  const fs_ = v.field_sources || {};
  const facts = [];
  const sizesWithCount = new Set();

  if (fs_['number-of-tables'] && v.counts['number-of-tables'] != null) {
    facts.push(fact('table_count', 'table_count', v.counts['number-of-tables'], fs_['number-of-tables']));
  }
  for (const [field, size] of Object.entries(COUNT_SIZE)) {
    if (fs_[field] && v.counts[field] != null && v.counts[field] > 0) {
      facts.push(fact('table_sizes', `table_sizes:${size}`, { size, count: v.counts[field] }, fs_[field]));
      sizesWithCount.add(size);
    }
  }
  for (const slug of v.terms['table-size'] || []) {
    const src = fs_[`table-size:${slug}`];
    const size = SIZE[slug];
    if (src && size && !sizesWithCount.has(size)) {
      facts.push(fact('table_sizes', `table_sizes:${size}`, { size, count: null }, src, src.source === 'default' ? { confidence: 'implied', flags: ['size not stated, 7 ft by default'] } : {}));
    }
  }
  for (const slug of v.terms['table-brand'] || []) {
    const src = fs_[`table-brand:${slug}`];
    if (src && BRANDS[slug]) facts.push(fact('table_brands', `table_brands:${slug}`, BRANDS[slug], src));
  }
  for (const slug of v.terms['pool-pricing'] || []) {
    const src = fs_[`pool-pricing:${slug}`];
    if (!src) continue;
    const value = { model: slug, rate: null };
    if (slug === 'hourly' && fs_['hourly-rate'] && v.fields['hourly-rate']) value.rate = Number(v.fields['hourly-rate']);
    if (slug === 'per-game' && fs_['game-price'] && v.fields['game-price']) value.rate = Number(v.fields['game-price']);
    facts.push(fact('pricing_model', `pricing_model:${slug}`, value, src));
  }
  if (fs_['pricing-notes'] && v.fields['pricing-notes']) facts.push(fact('pricing_notes', 'pricing_notes', v.fields['pricing-notes'], fs_['pricing-notes']));
  if (fs_['table-models'] && v.fields['table-models']) facts.push(fact('table_models', 'table_models', v.fields['table-models'], fs_['table-models']));
  for (const slug of v.terms.case27_job_listing_tags || []) {
    const src = fs_[`case27_job_listing_tags:${slug}`];
    if (!src) continue;
    if (GAMES[slug]) facts.push(fact('games', `games:${slug}`, GAMES[slug], src));
    if (slug === 'food') facts.push(fact('food', 'food', true, src));
    if (slug === 'full-bar') facts.push(fact('full_bar', 'full_bar', true, src));
  }
  return facts;
}

const venues = data.venues || [];
let written = 0;
let kept = 0;
for (const v of venues) {
  const state = v.regions.find((r) => r.parent === 0);
  const city = v.regions.find((r) => r.parent !== 0);
  const a = v.address_parts;
  const record = {
    schema: 1,
    slug: v.slug,
    wp_id: v.id,
    name: v.name,
    type: (v.terms.job_listing_category || [])[0] || 'pool-halls',
    status: v.status || 'open',
    address: { street: a.street, city: a.city, state: a.state, postal_code: a.postal_code, country: a.country || 'US' },
    geo: v.lat != null && v.lng != null ? { lat: v.lat, lng: v.lng } : null,
    region: {
      state: state?.slug || '',
      state_name: state?.name || '',
      city: city?.slug || '',
      city_name: city?.name || a.city,
    },
    phone: v.phone || '',
    website: v.website || '',
    hours: v.hours?.r?.length ? { tz: v.hours.tz, ranges: v.hours.r } : null,
    // Where the name, address, phone, website and hours came from. Google Places
    // content may be kept 30 days; recheck these from the venue's own site.
    listing_source: {
      source: v.source || 'import',
      url: v.source_url || '',
      checked_at: v.last_checked || v.verified || '',
      place_id: v.ext_ids?.google_places || '',
      osm_id: v.ext_ids?.openstreetmap || '',
    },
    claimed: Boolean(v.claimed),
    verification: v.verification || 'unverified',
    photo: v.photo || null,
    created_at: (v.created || '').slice(0, 10),
    updated_at: (v.modified || '').slice(0, 10),
    facts: venueFacts(v),
  };
  const dest = path.join(outDir, `${v.slug}.json`);
  if (fs.existsSync(dest) && !force) {
    kept++;
    continue;
  }
  fs.writeFileSync(dest, JSON.stringify(record, null, 2) + '\n');
  written++;
}

if (Array.isArray(data.metros)) {
  const metrosFile = path.join(root, 'data/metros.json');
  if (!fs.existsSync(metrosFile)) {
    // Set "page": true on metros whose venues are spread over several suburbs with no city page of their own.
    const slug = (label) => label.toLowerCase().replace(/\./g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    const metros = data.metros.map(([label, location, lat, lng, radius]) => ({ label, location, lat, lng, radius_miles: radius, slug: slug(label), page: false }));
    fs.writeFileSync(metrosFile, JSON.stringify(metros, null, 2) + '\n');
  }
}

console.log(`${written} venue files written, ${kept} already there (use --force to overwrite).`);
