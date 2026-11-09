#!/usr/bin/env node
/**
 * Players' "How many tables?" answers (from the card on venue pages) become suggested facts
 * in `npm run review`, under the "players" folder:
 *   SUPABASE_URL=... SUPABASE_SERVICE_KEY=... npm run player-reports
 * or, to try it without the database:  npm run player-reports -- --from answers.json
 * (a JSON array of submissions rows: { venue_slug, payload, created_at }).
 *
 * Nothing goes on the site from here: each suggestion still needs a yes in the review tool.
 * Facts are marked as from players (source_type "community"), with how many agreed.
 * Two or more players agreeing is "stated"; a single player is "implied".
 * Sizes and brands the venue already has from a source are left alone.
 */
import fs from 'node:fs';
import path from 'node:path';
import { summarize } from '../src/lib/player-reports.js';

const SITE = 'https://playpoolnation.com';
const ROOT = process.cwd();
const OUT = path.join(ROOT, 'tools/enrich/out/players');
const VENUES = path.join(ROOT, 'data/venues');
const today = new Date().toISOString().slice(0, 10);

async function rows() {
  const i = process.argv.indexOf('--from');
  if (i > 0) return JSON.parse(fs.readFileSync(process.argv[i + 1], 'utf8'));
  const { SUPABASE_URL: url, SUPABASE_SERVICE_KEY: key } = process.env;
  if (!url || !key) {
    console.error('Set SUPABASE_URL and SUPABASE_SERVICE_KEY (or pass --from answers.json).');
    process.exit(1);
  }
  const all = [];
  for (let from = 0; ; from += 1000) {
    const res = await fetch(`${url}/rest/v1/submissions?select=venue_slug,payload,created_at,status&kind=eq.edit&payload->>type=eq.tables&status=in.(new,done)&order=created_at`, {
      headers: { apikey: key, Authorization: `Bearer ${key}`, Range: `${from}-${from + 999}` },
    });
    if (!res.ok) throw new Error(`submissions: ${res.status} ${await res.text()}`);
    const page = await res.json();
    all.push(...page);
    if (page.length < 1000) return all;
  }
}

const LABEL = { '7ft': '7-ft', '8ft': '8-ft', '9ft': '9-ft', snooker: 'snooker', carom: 'carom' };
const players = (n) => `${n} ${n === 1 ? 'player' : 'players'}`;

const bySlug = new Map();
for (const r of await rows()) {
  if (!r.venue_slug || r.payload?.type !== 'tables') continue;
  const list = bySlug.get(r.venue_slug) || [];
  list.push({ ...r.payload, created_at: r.created_at });
  bySlug.set(r.venue_slug, list);
}

fs.mkdirSync(OUT, { recursive: true });
for (const f of fs.readdirSync(OUT)) if (f.endsWith('.json') && !f.startsWith('_')) fs.unlinkSync(path.join(OUT, f));
const state = { venues: {} };
let written = 0, factsOut = 0;
for (const [slug, answers] of bySlug) {
  const file = path.join(VENUES, `${slug}.json`);
  if (!fs.existsSync(file)) continue;
  const record = JSON.parse(fs.readFileSync(file, 'utf8'));
  const has = (key) => record.facts.some((f) => f.key === key && f.source_type !== 'default');
  const s = summarize(answers);
  const base = {
    source_url: `${SITE}/place/${slug}/#tables`,
    source_type: 'community',
    checked_at: (s.last || today).slice(0, 10),
  };
  const facts = [];
  if (s.count) {
    facts.push({
      ...base,
      field: 'table_count',
      key: 'table_count',
      value: s.count.value,
      confidence: s.count.agree >= 2 ? 'stated' : 'implied',
      flags: ['from players', s.count.agree >= 2 ? `${s.count.agree} of ${s.count.of} agree` : 'one player only'],
      quote_snippet: `${players(s.count.of)} answered: ${s.count.counts.join(', ')} tables`,
    });
  }
  for (const z of s.sizes) {
    const key = `table_sizes:${z.size}`;
    if (has(key)) continue;
    const counted = z.count && z.count.agree >= 1 ? z.count.value : null;
    facts.push({
      ...base,
      field: 'table_sizes',
      key,
      value: counted ? { size: z.size, count: counted } : { size: z.size },
      confidence: z.players >= 2 ? 'stated' : 'implied',
      flags: ['from players', z.players >= 2 ? `${players(z.players)} saw ${LABEL[z.size]}` : 'one player only'],
      quote_snippet: `${players(z.players)} saw ${LABEL[z.size]} tables${counted ? ` (${counted} of them)` : ''}`,
    });
  }
  for (const b of s.brands) {
    const key = `table_brands:${b.brand.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}`;
    if (has(key)) continue;
    facts.push({
      ...base,
      field: 'table_brands',
      key,
      value: b.brand,
      confidence: b.players >= 2 ? 'stated' : 'implied',
      flags: ['from players', b.players >= 2 ? `${players(b.players)} named it` : 'one player only'],
      quote_snippet: `${players(b.players)} named ${b.brand}`,
    });
  }
  if (s.notes.length) for (const f of facts) f.flags.push(`notes: ${s.notes.join(' | ').slice(0, 200)}`);
  if (!facts.length) continue;
  fs.writeFileSync(path.join(OUT, `players-${slug}.json`), JSON.stringify({ schema: 1, slug, kind: 'listing', checked_at: today, players: s.players, facts }, null, 2) + '\n');
  state.venues[slug] = { status: 'done', name: record.name, kind: 'listing', at: today, stated: facts.filter((f) => f.confidence === 'stated').length, note: `${players(s.players)}` };
  written++;
  factsOut += facts.length;
}
fs.writeFileSync(path.join(OUT, '_state.json'), JSON.stringify(state, null, 2) + '\n');
console.log(`${bySlug.size} venues with answers; ${factsOut} suggested facts for ${written} venues in tools/enrich/out/players/.`);
console.log('Next: npm run review, then pick "players".');
