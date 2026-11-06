/**
 * The directory read from data/: venues, cities, states, metros and attribute
 * hubs, with the rules for which pages exist and which are indexed.
 *
 *   City pillar page   a city with 2+ venues. Indexed once listed in data/launch.json.
 *   Attribute hub      city + attribute, only when 2+ venues qualify on sourced facts
 *                      (and not every venue in the city, which would repeat the city page).
 *                      Otherwise the URL doesn't exist (404).
 *   Venue page         always built; indexed only when it passes the data threshold.
 *   State page         indexed with 2+ venues.
 */
import fs from 'node:fs';
import path from 'node:path';
import { miles } from './open-status.js';
import { money, slugify } from './format.ts';
import * as T from './text.ts';
import type { Fact, League, SizeKey, Venue, VenueRecord } from './types.ts';

export const SITE = 'https://playpoolnation.com';
export const BRAND = 'PlayPoolNation';

const ROOT = process.cwd();
const read = (p: string) => JSON.parse(fs.readFileSync(path.join(ROOT, p), 'utf8'));

/** How many of the three table facts (count, sizes, brands) a venue page needs before it is indexed. */
export const MIN_TABLE_FACTS = 2;
export const MIN_CITY_VENUES = 2;
export const MIN_HUB_VENUES = 2;
const NEARBY_MILES = 300;

const STATE_ABBR: Record<string, string> = {
  alabama: 'AL', alaska: 'AK', arizona: 'AZ', arkansas: 'AR', california: 'CA', colorado: 'CO', connecticut: 'CT', delaware: 'DE',
  florida: 'FL', georgia: 'GA', hawaii: 'HI', idaho: 'ID', illinois: 'IL', indiana: 'IN', iowa: 'IA', kansas: 'KS', kentucky: 'KY',
  louisiana: 'LA', maine: 'ME', maryland: 'MD', massachusetts: 'MA', michigan: 'MI', minnesota: 'MN', mississippi: 'MS', missouri: 'MO',
  montana: 'MT', nebraska: 'NE', nevada: 'NV', 'new-hampshire': 'NH', 'new-jersey': 'NJ', 'new-mexico': 'NM', 'new-york': 'NY',
  'north-carolina': 'NC', 'north-dakota': 'ND', ohio: 'OH', oklahoma: 'OK', oregon: 'OR', pennsylvania: 'PA', 'rhode-island': 'RI',
  'south-carolina': 'SC', 'south-dakota': 'SD', tennessee: 'TN', texas: 'TX', utah: 'UT', vermont: 'VT', virginia: 'VA',
  washington: 'WA', 'west-virginia': 'WV', wisconsin: 'WI', wyoming: 'WY', 'washington-d-c': 'DC',
};

/* ------------------------------------------------------------------ venues */

const LEAGUE_DAY: Record<string, number> = { monday: 0, tuesday: 1, wednesday: 2, thursday: 3, friday: 4, saturday: 5, sunday: 6 };

function leagueFrom(f: Fact): League {
  const v = f.value || {};
  const name = String(v.league || v.name || 'League');
  const org = v.org || (/\(([^)]+)\)\s*$/.exec(name)?.[1] ?? '');
  return { day: v.weekday ? (LEAGUE_DAY[String(v.weekday).toLowerCase()] ?? null) : null, time: String(v.time || ''), orgs: org ? [org] : [], name };
}

/** Facts that count toward indexing: approved, and not an assumed default. */
const counts = (f: Fact) => f.source_type !== 'default';

export function buildVenue(r: VenueRecord): Venue {
  const facts = r.facts || [];
  const of = (field: Fact['field']) => facts.filter((f) => f.field === field);
  const sizeCounts: Partial<Record<SizeKey, number>> = {};
  const sizes: SizeKey[] = [];
  for (const f of of('table_sizes')) {
    const size = f.value?.size as SizeKey;
    if (!size) continue;
    if (f.value.count) sizeCounts[size] = Number(f.value.count);
    if (!sizes.includes(size)) sizes.push(size);
  }
  const pricing = of('pricing_model').map((f) => String(f.value?.model || '')).filter(Boolean);
  const hourly = of('pricing_model').find((f) => f.value?.model === 'hourly' && f.value?.rate);
  const perGame = of('pricing_model').find((f) => f.value?.model === 'per-game' && f.value?.rate);
  const tableCount = of('table_count')[0];
  const statusFact = of('venue_status')[0];

  const view = {
    ...r,
    status: (statusFact?.value as VenueRecord['status']) || r.status || 'open',
    url: `/place/${r.slug}/`,
    anchor: slugify(r.name),
    cityKey: `${r.region.state}/${r.region.city}`,
    tables: tableCount ? Number(tableCount.value) : null,
    sizeCounts,
    sizes,
    brands: [...new Set(of('table_brands').map((f) => String(f.value)))],
    models: String(of('table_models')[0]?.value || ''),
    pricing,
    hourlyRate: hourly ? money(hourly.value.rate) : '',
    gamePrice: perGame ? money(perGame.value.rate) : '',
    pricingNotes: String(of('pricing_notes')[0]?.value || ''),
    food: of('food').some((f) => f.value === true),
    fullBar: of('full_bar').some((f) => f.value === true),
    games: [...new Set(of('games').map((f) => String(f.value)))],
    agepolicy: String(of('age_policy')[0]?.value || ''),
    leagues: of('league_nights').map(leagueFrom),
  } as Venue;

  // Table facts for the index rule ignore assumed defaults (7 ft when no size is stated).
  const real = buildCounted(facts.filter(counts));
  view.tableFacts = T.tableFacts(real);
  view.indexGaps = [
    !r.address.street.trim() && 'address',
    !r.phone && !r.website && 'phone or website',
    !r.hours?.ranges?.length && 'hours',
    view.tableFacts < MIN_TABLE_FACTS && 'table facts',
    view.status === 'permanently-closed' && 'closed',
  ].filter(Boolean) as string[];
  view.indexable = view.indexGaps.length === 0;
  view.lastChecked = [r.listing_source?.checked_at || '', ...facts.map((f) => f.checked_at || '')].sort().pop() || '';
  return view;
}

function buildCounted(facts: Fact[]) {
  const sizeCounts: Partial<Record<SizeKey, number>> = {};
  const sizes: SizeKey[] = [];
  for (const f of facts.filter((x) => x.field === 'table_sizes')) {
    if (f.value?.count) sizeCounts[f.value.size as SizeKey] = Number(f.value.count);
    sizes.push(f.value.size);
  }
  const tc = facts.find((f) => f.field === 'table_count');
  return { tables: tc ? Number(tc.value) : null, sizeCounts, sizes, brands: facts.filter((f) => f.field === 'table_brands').map((f) => String(f.value)) };
}

let cache: Venue[] | null = null;

export function venues(): Venue[] {
  if (cache) return cache;
  const dir = path.join(ROOT, 'data/venues');
  cache = fs
    .readdirSync(dir)
    .filter((f) => f.endsWith('.json'))
    .map((f) => buildVenue(JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'))))
    .sort((a, b) => a.name.localeCompare(b.name));
  // Two venues with the same name in a city get distinct anchors.
  const seen = new Map<string, number>();
  for (const v of cache) {
    const k = v.cityKey + '#' + v.anchor;
    const n = (seen.get(k) ?? 0) + 1;
    seen.set(k, n);
    if (n > 1) v.anchor += '-' + n;
  }
  return cache;
}

export function venue(slug: string): Venue | undefined {
  return venues().find((v) => v.slug === slug);
}

/** Facts for the text helpers. */
export function textFacts(v: Venue) {
  return { ...v, street: v.address.street, cityState: [v.address.city, v.address.state].filter(Boolean).join(', '), hours: v.hours?.ranges ?? [], photo: !!v.photo };
}

/** Most tables first, then by name, as the city page lists them. */
export function byTables(list: Venue[]): Venue[] {
  return [...list].sort((a, b) => (T.tables(b) ?? -1) - (T.tables(a) ?? -1) || a.name.localeCompare(b.name));
}

/* ------------------------------------------------------------------ places */

export interface City {
  key: string;
  state: string;
  stateName: string;
  slug: string;
  name: string;
  where: string; // "Sioux Falls, SD"
  url: string;
  venues: Venue[];
  center: { lat: number; lng: number } | null;
  launched: boolean;
}

export interface State {
  slug: string;
  name: string;
  abbr: string;
  url: string;
  venues: Venue[];
  cities: City[];
}

export interface Metro {
  slug: string;
  label: string;
  location: string;
  lat: number;
  lng: number;
  radius_miles: number;
  page: boolean;
  url: string;
  venues: Venue[];
  launched: boolean;
}

export interface Hub {
  slug: string;
  label: string; // chip text
  h1: (where: string) => string;
  test: (v: Venue) => boolean;
  lead: (where: string, names: string[]) => string;
}

const launch = read('data/launch.json');

function center(list: Venue[]) {
  const pts = list.filter((v) => v.geo);
  if (!pts.length) return null;
  return { lat: pts.reduce((s, v) => s + v.geo!.lat, 0) / pts.length, lng: pts.reduce((s, v) => s + v.geo!.lng, 0) / pts.length };
}

export function stateAbbr(slug: string): string {
  return STATE_ABBR[slug] ?? '';
}

/** Every city with at least one venue; `cityPages()` is the ones with a page. */
export function cities(): City[] {
  const map = new Map<string, Venue[]>();
  for (const v of venues()) {
    if (!v.region.state || !v.region.city) continue;
    map.set(v.cityKey, [...(map.get(v.cityKey) ?? []), v]);
  }
  return [...map.entries()]
    .map(([key, list]) => {
      const [state, slug] = key.split('/');
      const name = list[0].region.city_name;
      return {
        key,
        state,
        stateName: list[0].region.state_name,
        slug,
        name,
        where: `${name}, ${stateAbbr(state) || list[0].address.state}`,
        url: `/places/${state}/${slug}/`,
        venues: byTables(list),
        center: center(list),
        launched: Boolean(launch.cities?.[key]),
      };
    })
    .sort((a, b) => b.venues.length - a.venues.length || a.name.localeCompare(b.name));
}

export function cityPages(): City[] {
  return cities().filter((c) => c.venues.length >= MIN_CITY_VENUES);
}

export function cityOf(v: Venue): City | undefined {
  return cityPages().find((c) => c.key === v.cityKey);
}

export function states(): State[] {
  const map = new Map<string, Venue[]>();
  for (const v of venues()) if (v.region.state) map.set(v.region.state, [...(map.get(v.region.state) ?? []), v]);
  const pages = cityPages();
  return [...map.entries()]
    .map(([slug, list]) => ({
      slug,
      name: list[0].region.state_name,
      abbr: stateAbbr(slug),
      url: `/places/${slug}/`,
      venues: byTables(list),
      cities: pages.filter((c) => c.state === slug),
    }))
    .sort((a, b) => a.name.localeCompare(b.name));
}

export function metros(): Metro[] {
  return (read('data/metros.json') as Omit<Metro, 'url' | 'venues' | 'launched'>[]).map((m) => ({
    ...m,
    url: `/places/${m.slug}/`,
    venues: byTables(venues().filter((v) => v.geo && miles(m.lat, m.lng, v.geo.lat, v.geo.lng) <= m.radius_miles)),
    launched: Boolean(launch.metros?.[m.slug]),
  }));
}

export function metroPages(): Metro[] {
  return metros().filter((m) => m.page && m.venues.length >= MIN_CITY_VENUES);
}

/** Where a metro chip on the homepage goes: its city page, its metro page, or the search. */
export function metroLink(m: Metro): string {
  if (m.page) return m.url;
  const city = cityPages().find((c) => c.where === m.location || (c.name + ', ' + stateAbbr(c.state)) === m.location);
  return city ? city.url : `/search/?near=${encodeURIComponent(m.location)}`;
}

/* -------------------------------------------------------------------- hubs */

export const HUBS: Hub[] = [
  {
    slug: '9-foot-tables',
    label: '9-foot tables',
    h1: (w) => `Pool halls with 9-foot tables in ${w}`,
    test: (v) => T.hasSize(v, '9ft'),
    lead: (w, n) => `${n.length} places in ${w} have 9-foot tables, the size used in pro events.`,
  },
  {
    slug: 'pool-leagues',
    label: 'Pool leagues',
    h1: (w) => `Pool leagues in ${w}`,
    test: (v) => v.leagues.some((l) => l.day !== null),
    lead: (w, n) => `${n.length} places in ${w} host league nights. Here's which night each league plays.`,
  },
  {
    slug: 'free-pool',
    label: 'Free pool',
    h1: (w) => `Free pool in ${w}`,
    test: (v) => T.freePool(v),
    lead: (w, n) => `${n.length} places in ${w} have free pool.`,
  },
  {
    slug: 'open-late',
    label: 'Open late',
    h1: (w) => `Pool tables open late in ${w}`,
    test: (v) => v.status === 'open' && !!v.hours && T.latestClose(v.hours.ranges) >= 1440 + 60,
    lead: (w, n) => `${n.length} places in ${w} stay open until 1 AM or later on at least one night, by their posted hours.`,
  },
  {
    slug: 'snooker',
    label: 'Snooker',
    h1: (w) => `Snooker tables in ${w}`,
    test: (v) => T.hasSize(v, 'snooker'),
    lead: (w, n) => `${n.length} places in ${w} have snooker tables.`,
  },
];

export function hubsFor(city: City): { hub: Hub; venues: Venue[]; url: string }[] {
  return HUBS.map((hub) => ({ hub, venues: city.venues.filter(hub.test), url: `${city.url}${hub.slug}/` })).filter(
    (h) => h.venues.length >= MIN_HUB_VENUES && h.venues.length < city.venues.length,
  );
}

/* ----------------------------------------------------------------- nearby */

export function nearbyPlaces(from: { lat: number; lng: number } | null, exclude: string, limit = 6) {
  if (!from) return [];
  const out = [
    ...cityPages().filter((c) => c.url !== exclude && c.center).map((c) => ({ name: c.where, url: c.url, miles: Math.round(miles(from.lat, from.lng, c.center!.lat, c.center!.lng)) })),
    ...metroPages().filter((m) => m.url !== exclude).map((m) => ({ name: `${m.label} area`, url: m.url, miles: Math.round(miles(from.lat, from.lng, m.lat, m.lng)) })),
  ];
  return out.filter((n) => n.miles > 0 && n.miles <= NEARBY_MILES).sort((a, b) => a.miles - b.miles).slice(0, limit);
}

export function nearbyVenues(v: Venue, limit = 4): (Venue & { miles: number })[] {
  if (!v.geo) return [];
  return venues()
    .filter((o) => o.slug !== v.slug && o.geo)
    .map((o) => ({ ...o, miles: miles(v.geo!.lat, v.geo!.lng, o.geo!.lat, o.geo!.lng) }))
    .filter((o) => o.miles <= 25)
    .sort((a, b) => a.miles - b.miles)
    .slice(0, limit);
}

/** Hours, location and table facts every visitor's browser needs for "open now" and the Tonight panel. */
export function clientVenue(v: Venue) {
  return {
    i: v.slug,
    n: v.name,
    u: v.url,
    k: v.cityKey,
    c: v.address.city + ', ' + v.address.state,
    a: v.geo?.lat ?? null,
    o: v.geo?.lng ?? null,
    h: { tz: v.hours?.tz ?? 'America/Chicago', r: v.hours?.ranges ?? [], s: v.status },
    t: T.tables(v),
    z: T.sizes(v),
    p: v.phone,
    d: [v.address.street, v.address.city, v.address.state, v.address.postal_code].filter(Boolean).join(', '),
    l: T.leagueDays(v),
    y: v.type,
    f: [T.freePool(v) && 'free', v.food && 'food', T.hasSize(v, '9ft') && 'nine', v.leagues.length && 'leagues'].filter(Boolean),
  };
}
