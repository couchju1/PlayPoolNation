#!/usr/bin/env node
/**
 * Phase 2: when venues and facts are edited in Supabase (owner claims, admin),
 * write them back to data/venues/*.json before a build:
 *   SUPABASE_URL=... SUPABASE_SERVICE_KEY=... node scripts/db-pull.mjs
 * Pages stay static; only the build reads the database.
 */
import fs from 'node:fs';
import path from 'node:path';

const { SUPABASE_URL: url, SUPABASE_SERVICE_KEY: key } = process.env;
if (!url || !key) {
  console.log('SUPABASE_URL / SUPABASE_SERVICE_KEY not set: building from data/venues as it is.');
  process.exit(0);
}
const get = async (table, query) => {
  const rows = [];
  for (let from = 0; ; from += 1000) {
    const res = await fetch(`${url}/rest/v1/${table}?${query}`, { headers: { apikey: key, Authorization: `Bearer ${key}`, Range: `${from}-${from + 999}` } });
    if (!res.ok) throw new Error(`${table}: ${res.status} ${await res.text()}`);
    const page = await res.json();
    rows.push(...page);
    if (page.length < 1000) return rows;
  }
};
const venues = await get('venues', 'select=*&published=eq.true&order=slug');
const facts = await get('facts', 'select=*&order=id');
const dir = path.join(process.cwd(), 'data/venues');
const keep = new Set();
for (const v of venues) {
  const record = {
    schema: 1, slug: v.slug, wp_id: v.wp_id ?? undefined, name: v.name, type: v.type, status: v.status,
    address: { street: v.street, city: v.city, state: v.state, postal_code: v.postal_code, country: v.country },
    geo: v.lat !== null && v.lng !== null ? { lat: v.lat, lng: v.lng } : null,
    region: { state: v.region_state, state_name: v.region_state_name, city: v.region_city, city_name: v.region_city_name },
    phone: v.phone, website: v.website, hours: v.hours_ranges ? { tz: v.hours_tz, ranges: v.hours_ranges } : null,
    listing_source: v.listing_source, claimed: v.claimed, verification: v.verification, photo: v.photo,
    created_at: v.created_at, updated_at: v.updated_at,
    facts: facts.filter((f) => f.venue_slug === v.slug).map(({ id, venue_slug, ...f }) => ({ ...f, evidence_url: f.evidence_url ?? undefined })),
  };
  fs.writeFileSync(path.join(dir, `${v.slug}.json`), JSON.stringify(record, null, 2) + '\n');
  keep.add(`${v.slug}.json`);
}
for (const f of fs.readdirSync(dir)) if (f.endsWith('.json') && !keep.has(f)) fs.unlinkSync(path.join(dir, f));
console.log(`${venues.length} venues, ${facts.length} facts pulled.`);
