/**
 * Words for venue and city pages, built only from each venue's own facts, so no
 * two pages read alike and nothing is guessed. Ported from the plugin's
 * About_Text and City_Text.
 *
 * Style: short sentences a 7th to 9th grader reads easily, contractions, no
 * dashes or semicolons, no hype. Comparisons say what they compare ("of the 2
 * places where we know the count").
 */
import { clock } from './open-status.js';
import { andList, SIZE_ORDER, SIZE_WORDS } from './format.ts';
import type { SizeKey, Venue } from './types.ts';

const TYPES: Record<string, [string, string, string, boolean]> = {
  // slug: [singular, plural, label, needs "with pool tables"]
  'pool-halls': ['pool hall', 'pool halls', 'Pool hall', false],
  'billiards-lounge': ['billiards lounge', 'billiards lounges', 'Billiards lounge', false],
  'bars-with-pool-tables': ['bar', 'bars', 'Bar', true],
  'sports-bar': ['sports bar', 'sports bars', 'Sports bar', true],
  'bowling-center': ['bowling center', 'bowling centers', 'Bowling center', true],
  recreation: ['recreation center', 'recreation centers', 'Recreation center', true],
  'private-clubs': ['private club', 'private clubs', 'Private club', true],
};

export const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
export const DAY_PLURALS = ['Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays'];
export const DAY_SHORT = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const NUMBER_WORDS: Record<number, string> = { 1: 'one', 2: 'two', 3: 'three', 4: 'four', 5: 'five', 6: 'six', 7: 'seven', 8: 'eight', 9: 'nine' };

export const TITLE_LIMIT = 59;
export const DESCRIPTION_LIMIT = 154;

type V = Pick<Venue, 'name' | 'type' | 'tables' | 'sizeCounts' | 'sizes' | 'brands' | 'pricing' | 'hourlyRate' | 'gamePrice' | 'food' | 'games' | 'leagues' | 'status'> & {
  street?: string;
  cityState?: string;
  hours?: [number, number][];
  photo?: boolean;
  claimed?: boolean;
};

const num = (n: number) => NUMBER_WORDS[n] ?? String(n);
const lowerClock = (m: number) => {
  const c = clock(m);
  return c === 'Midnight' || c === 'Noon' ? c.toLowerCase() : c;
};

export function typeLabel(slug: string): string {
  return TYPES[slug]?.[2] ?? 'Place to play';
}

export function typeNoun(slug: string): string {
  return TYPES[slug]?.[0] ?? 'place to play pool';
}

/** Known table count: the total, else the sum of the per-size counts. */
export function tables(v: Pick<V, 'tables' | 'sizeCounts'>): number | null {
  if (v.tables !== null && v.tables !== undefined) return v.tables;
  const counts = Object.values(v.sizeCounts || {}).filter((n) => (n ?? 0) > 0) as number[];
  return counts.length ? counts.reduce((a, b) => a + b, 0) : null;
}

export function sizes(v: Pick<V, 'sizes' | 'sizeCounts'>): SizeKey[] {
  const known = new Set<SizeKey>([...(Object.keys(v.sizeCounts || {}) as SizeKey[]).filter((k) => (v.sizeCounts[k] ?? 0) > 0), ...(v.sizes || [])]);
  return SIZE_ORDER.filter((s) => known.has(s));
}

export function hasSize(v: Pick<V, 'sizes' | 'sizeCounts'>, size: SizeKey): boolean {
  return sizes(v).includes(size);
}

export function brands(v: Pick<V, 'brands'>): string[] {
  return (v.brands || []).filter((b) => b.trim() !== '' && b.toLowerCase() !== 'other brand');
}

export function freePool(v: Pick<V, 'pricing'>): boolean {
  return (v.pricing || []).includes('free-pool');
}

/** How many of the three table facts are known: the count, the sizes, the brands. */
export function tableFacts(v: Pick<V, 'tables' | 'sizeCounts' | 'sizes' | 'brands'>): number {
  return Number(tables(v) !== null) + Number(sizes(v).length > 0) + Number(brands(v).length > 0);
}

/** Weekdays with a league night, 0 = Monday. */
export function leagueDays(v: Pick<V, 'leagues'>): number[] {
  return [...new Set((v.leagues || []).map((l) => l.day).filter((d): d is number => typeof d === 'number'))].sort((a, b) => a - b);
}

/* ------------------------------------------------------------ about text */

export function identity(f: V): string {
  const [noun, , , withTables] = TYPES[f.type] ?? ['place to play pool', '', '', false];
  const street = (f.street || '').trim();
  const where = (f.cityState || '').trim();
  const at = (street ? ` ${/^\d/.test(street) ? 'at' : 'on'} ${street}` : '') + (where ? ` in ${where}` : '');
  const article = /^[aeiou]/i.test(noun) ? 'an' : 'a';
  return `${f.name.trim()} is ${article} ${noun}${at}${withTables ? ' with pool tables' : ''}.`;
}

/** Merge a night that My Listing split at the end of the week back onto its day. */
function joinNights(input: [number, number][]): [number, number][] {
  const week = 10080;
  const ranges: ([number, number] | undefined)[] = input.filter((r) => r[1] > r[0]).map((r) => [r[0], r[1]]);
  ranges.forEach((ri, i) => {
    if (!ri || ri[0] % 1440 !== 0) return;
    const [s, e] = ri;
    for (let j = 0; j < ranges.length; j++) {
      const rj = ranges[j];
      if (i !== j && rj && (rj[1] === s || (s === 0 && rj[1] === week))) {
        rj[1] += e - s;
        ranges[i] = undefined;
        break;
      }
    }
  });
  return ranges.filter((r): r is [number, number] => !!r);
}

export function hoursSentences(f: Pick<V, 'status' | 'hours'>): string[] {
  if (f.status === 'temporarily-closed') return ["It's temporarily closed."];
  if (f.status === 'permanently-closed') return ["It's permanently closed."];
  const raw = (f.hours || []).filter((r) => r[1] > r[0]);
  if (!raw.length) return [];
  const week = 10080;
  const total = raw.reduce((sum, r) => sum + Math.min(week, r[1] - r[0]), 0);
  if (total >= week - 1) return ["It's open 24 hours a day."];
  const ranges = joinNights(raw);
  const close: Record<number, number> = {};
  for (const [s, e] of ranges) {
    const day = Math.floor(s / 1440) % 7;
    close[day] = Math.max(close[day] ?? 0, e - Math.floor(s / 1440) * 1440);
  }
  const openDays = Object.keys(close).map(Number).sort((a, b) => a - b);
  const closed = [0, 1, 2, 3, 4, 5, 6].filter((d) => !openDays.includes(d));
  let out = [closed.length ? `It's closed on ${andList(closed.map((d) => DAY_PLURALS[d]))}.` : "It's open every day."];
  const latest = Math.max(...Object.values(close));
  if (latest >= 1440) {
    const time = latest === 1440 ? 'midnight' : clock(latest - 1440);
    const late = openDays.filter((d) => close[d] === latest);
    const early = openDays.filter((d) => !late.includes(d));
    if (early.length === 1 && late.length >= 4) out.push(`It stays open until ${time} every night except ${DAY_PLURALS[early[0]]}.`);
    else if (late.length < openDays.length) out.push(`It stays open until ${time} on ${andList(late.map((d) => DAY_PLURALS[d]))}.`);
    else if (closed.length) out.push(`The other nights it stays open until ${time}.`);
    else out = [`It's open every day until ${time}.`];
  }
  return out;
}

function countOf(n: number, size: string): string {
  return `${num(n)} ${size} ${n === 1 ? 'table' : 'tables'}`;
}

function tableSentences(f: V): string[] {
  let total = f.tables;
  const counts = Object.fromEntries(Object.entries(f.sizeCounts || {}).filter(([, n]) => (n ?? 0) > 0)) as Record<SizeKey, number>;
  const sz = sizes(f);
  if (total === 0) return ["It doesn't have pool tables right now."];
  const countSum = Object.values(counts).reduce((a, b) => a + b, 0);
  if (total === null && countSum) total = countSum;
  if (total === null) {
    if (!sz.length) return ["We don't know the table count or sizes yet."];
    return [`The tables are ${andList(sz.map((s) => SIZE_WORDS[s]))}. We don't know how many there are yet.`];
  }
  const out = [`It has ${num(total)} ${total === 1 ? 'table' : 'tables'}.`];
  const entries = (Object.entries(counts) as [SizeKey, number][]).sort((a, b) => b[1] - a[1]);
  if (entries.length && countSum === total) {
    const [main, mainN] = entries[0];
    if (entries.length === 1) {
      const word = SIZE_WORDS[main];
      out.push(total === 1 ? `It's ${/^(8|[aeiou])/i.test(word) ? 'an' : 'a'} ${word} table.` : `All of them are ${word}.`);
    } else if (mainN * 2 > total) {
      out.push(`Most are ${SIZE_WORDS[main]}, plus ${andList(entries.slice(1).map(([s, n]) => countOf(n, SIZE_WORDS[s])))}.`);
    } else {
      out.push(`That's ${andList(entries.map(([s, n]) => `${num(n)} ${SIZE_WORDS[s]}`))}.`);
    }
  } else if (sz.length) {
    out.push(`They come in ${andList(sz.map((s) => SIZE_WORDS[s]))}.`);
  } else {
    out.push("We don't know the sizes yet.");
  }
  return out;
}

function priceSentence(f: V): string {
  const hourly = f.hourlyRate.trim();
  const game = f.gamePrice.trim();
  if (hourly && game) return `Pool is ${hourly} an hour or ${game} a game.`;
  if (hourly) return `Pool is ${hourly} an hour.`;
  if (game) return `Pool is ${game} a game.`;
  if (f.pricing.includes('free-pool')) return 'Pool is free here.';
  if (f.pricing.includes('hourly')) return 'You pay for pool by the hour.';
  if (f.pricing.includes('coin-op')) return 'The tables are coin-op.';
  if (f.pricing.includes('per-game')) return 'You pay for pool by the game.';
  return '';
}

/** What an owner could add, in the order players ask about it. */
export function unknowns(f: V): string[] {
  const missing: string[] = [];
  const counts = Object.keys(f.sizeCounts || {}).length > 0;
  const totalKnown = f.tables !== null;
  if (!totalKnown && !counts) missing.push('the table count');
  if (!f.sizes.length && !counts && !(totalKnown && f.tables === 0)) missing.push('table sizes');
  if (!f.hourlyRate.trim() && !f.gamePrice.trim()) missing.push('hourly rates');
  if (!f.leagues.length) missing.push('league nights');
  if (!f.photo) missing.push('photos');
  return missing;
}

export function about(f: V): { sentences: string[]; claim: { lead: string; rest: string } | null } {
  const sentences = [identity(f), ...hoursSentences(f), ...tableSentences(f)];
  const b = brands(f);
  if (b.length) sentences.push(`They're ${andList(b)} tables.`);
  const price = priceSentence(f);
  if (price) sentences.push(price);
  const games = f.games.map((g) => g.toLowerCase()).map((g) => (g === 'arcade' ? 'an arcade' : g));
  if (games.length) sentences.push(`There's ${andList(games)} too.`);
  if (f.leagues.length) sentences.push('League nights are listed below.');
  let claim = null;
  if (!f.claimed) {
    const missing = unknowns(f);
    claim = { lead: `Own ${f.name.trim()}?`, rest: missing.length ? ` to add ${andList(missing)}.` : ' to keep it current.' };
  }
  return { sentences, claim };
}

/* ------------------------------------------------------------- city text */

export function mostTables(venues: V[]): { names: string[]; tables: number; known: number; all: boolean } | null {
  const known = venues.filter((v) => (tables(v) ?? 0) > 0);
  if (known.length < 2) return null;
  const max = Math.max(...known.map((v) => tables(v)!));
  const names = known.filter((v) => tables(v) === max).map((v) => v.name);
  return { names, tables: max, known: known.length, all: known.length === venues.length };
}

export function withSize(venues: V[], size: SizeKey): string[] {
  return venues.filter((v) => hasSize(v, size)).map((v) => v.name);
}

/** "3 pool halls and 7 bars", biggest group first. */
export function typeMix(venues: V[]): string {
  const counts: Record<string, number> = {};
  for (const v of venues) {
    if (!TYPES[v.type]) return '';
    counts[v.type] = (counts[v.type] ?? 0) + 1;
  }
  return andList(
    Object.entries(counts)
      .sort((a, b) => b[1] - a[1])
      .map(([t, c]) => `${c} ${TYPES[t][c === 1 ? 0 : 1]}`),
  );
}

function namesHave(names: string[], what: string): string {
  if (names.length > 3) return `${names.length} of them have ${what}.`;
  return `${andList(names)} ${names.length === 1 ? 'has' : 'have'} ${what}.`;
}

function mostSentence(most: NonNullable<ReturnType<typeof mostTables>>): string {
  const who = andList(most.names);
  const verb = most.names.length === 1 ? 'has' : 'have';
  const each = most.names.length === 1 ? '' : ' each';
  return most.all
    ? `${who} ${verb} the most tables, with ${most.tables}${each}.`
    : `Of the ${most.known} places where we know the count, ${who} ${verb} the most tables, with ${most.tables}${each}.`;
}

function leagueOrgs(v: V): string[] {
  return v.leagues.flatMap((l) => l.orgs);
}

/** "Sioux Falls has 10 places to play pool on our map. That's 3 pool halls and 7 bars. …" */
export function intro(city: string, venues: V[]): string {
  const n = venues.length;
  if (!n) return '';
  const out = [n === 1 ? `${city} has one place to play pool on our map.` : `${city} has ${n} places to play pool on our map.`];
  const mix = typeMix(venues);
  if (n > 1 && mix) out.push(`That's ${mix}.`);
  const most = mostTables(venues);
  if (most) out.push(mostSentence(most));
  const nine = withSize(venues, '9ft');
  if (nine.length) out.push(namesHave(nine, '9-foot tables'));
  const free = venues.filter(freePool).map((v) => v.name);
  if (free.length) out.push(namesHave(free, 'free pool'));
  const league = venues.filter((v) => leagueDays(v).length);
  if (league.length) {
    const sets = league.map((v) => new Set(leagueOrgs(v)));
    const orgs = [...sets[0]].filter((o) => sets.every((s) => s.has(o)));
    const kind = orgs.length ? `${orgs[0]} leagues` : 'league nights';
    out.push(league.length === 1 ? `${league[0].name} runs ${kind} during the week.` : `${league.length} of them run ${kind} during the week.`);
  }
  return out.join(' ');
}

function priceLine(v: V): string {
  const hourly = v.hourlyRate.trim();
  const game = v.gamePrice.trim();
  if (hourly && game) return `${hourly} an hour or ${game} a game`;
  if (hourly) return `${hourly} an hour`;
  if (game) return `${game} a game`;
  for (const [slug, label] of [['free-pool', 'Free'], ['hourly', 'By the hour'], ['per-game', 'By the game'], ['coin-op', 'Coin-op tables']]) {
    if (v.pricing.includes(slug)) return label;
  }
  return '';
}

/** "Music Service on Wednesdays and Thursdays", one line per league group. */
export function leagueLines(v: V): string[] {
  const byWho = new Map<string, { days: Set<number>; times: Set<string> }>();
  for (const l of v.leagues) {
    const who = l.orgs.length ? andList(l.orgs) : l.name || 'League';
    const entry = byWho.get(who) ?? { days: new Set(), times: new Set() };
    if (typeof l.day === 'number') entry.days.add(l.day);
    if (l.time.trim()) entry.times.add(l.time.trim());
    byWho.set(who, entry);
  }
  return [...byWho.entries()].map(([who, { days, times }]) => {
    const d = [...days].sort((a, b) => a - b).map((x) => DAY_PLURALS[x]);
    return who + (d.length ? ` on ${andList(d)}` : '') + (times.size === 1 ? ` at ${[...times][0]}` : '');
  });
}

/** [label, value] lines for a venue's section, known facts only. */
export function factLines(v: V): [string, string][] {
  const lines: [string, string][] = [['Type', typeLabel(v.type)]];
  if ((v.street || '').trim()) lines.push(['Address', v.street!.trim()]);
  const t = tables(v);
  if (t !== null) lines.push(['Tables', t === 0 ? 'None right now' : String(t)]);
  const sz = sizes(v);
  if (sz.length) lines.push(['Sizes', andList(sz.map((s) => SIZE_WORDS[s] + (v.sizeCounts[s] ? ` (${v.sizeCounts[s]})` : '')))]);
  const b = brands(v);
  if (b.length) lines.push(['Brands', andList(b)]);
  const price = priceLine(v);
  if (price) lines.push(['Pool', price]);
  if (v.food) lines.push(['Food', 'Yes']);
  if (v.games.length) lines.push(['Also', andList(v.games)]);
  const leagues = leagueLines(v);
  if (leagues.length) lines.push(['Leagues', leagues.join('. ')]);
  return lines;
}

/** League nights across a city by weekday, Monday first. */
export function leagueNights(venues: V[]): [string, string[]][] {
  const days = new Map<number, Set<string>>();
  for (const v of venues) {
    for (const l of v.leagues) {
      if (typeof l.day !== 'number') continue;
      const who = l.orgs.length ? andList(l.orgs) : l.name || 'League';
      const set = days.get(l.day) ?? new Set();
      set.add(`${who} at ${v.name}${l.time.trim() ? ', ' + l.time.trim() : ''}`);
      days.set(l.day, set);
    }
  }
  return [...days.entries()].sort((a, b) => a[0] - b[0]).map(([d, lines]) => [DAYS[d], [...lines]]);
}

/** Opening hours by weekday, Monday first. A night past midnight stays on the day it started. */
export function weekHours(input: [number, number][]): { day: string; hours: string }[] {
  const ranges = joinNights(input);
  if (!ranges.length) return [];
  const byDay: Record<number, [number, number][]> = {};
  for (const r of ranges) (byDay[Math.floor(r[0] / 1440) % 7] ??= []).push(r);
  return DAYS.map((day, d) => {
    const spans = (byDay[d] ?? []).sort((a, b) => a[0] - b[0]);
    const text = spans.map((r) => (r[1] - r[0] >= 1440 ? 'Open 24 hours' : `${lowerClock(r[0])} to ${lowerClock(r[1])}`));
    const joined = text.join(', ');
    return { day, hours: text.length ? joined.charAt(0).toUpperCase() + joined.slice(1) : 'Closed' };
  });
}

/** Latest closing time across the week in minutes after the opening day's midnight. */
export function latestClose(input: [number, number][]): number {
  let latest = 0;
  for (const [s, e] of joinNights(input)) latest = Math.max(latest, e - Math.floor(s / 1440) * 1440);
  return latest;
}

/** Questions the data answers, and only those. */
export function faq(city: string, venues: V[]): { q: string; a: string }[] {
  const out: { q: string; a: string }[] = [];
  const most = mostTables(venues);
  if (most) {
    const who = andList(most.names);
    const verb = most.names.length === 1 ? 'has' : 'have';
    const each = most.names.length === 1 ? '' : ' each';
    out.push({
      q: `Which pool hall in ${city} has the most tables?`,
      a: most.all
        ? `${who} ${verb} the most, with ${most.tables} tables${each}.`
        : `Of the ${most.known} places in ${city} where we know the table count, ${who} ${verb} the most, with ${most.tables} tables${each}.`,
    });
  }
  const nine = venues.filter((v) => hasSize(v, '9ft'));
  if (nine.length) {
    const parts = nine.map((v) => {
      const n = v.sizeCounts['9ft'] ?? 0;
      return n ? `${v.name} has ${n === 1 ? 'one 9-foot table' : `${num(n)} 9-foot tables`}` : `${v.name} has 9-foot tables`;
    });
    const a = andList(parts);
    out.push({ q: `Where can I play on a 9-foot table in ${city}?`, a: a.charAt(0).toUpperCase() + a.slice(1) + '.' });
  }
  const free = venues.filter(freePool).map((v) => v.name);
  if (free.length) out.push({ q: `Is there free pool in ${city}?`, a: `Yes. ${andList(free)} ${free.length === 1 ? 'has' : 'have'} free pool.` });
  const league = leagueNights(venues);
  if (league.length) {
    const days = league.map(([d]) => d + 's');
    out.push({ q: `What nights are pool leagues in ${city}?`, a: `Leagues play on ${andList(days)}. The list below shows which league plays where.` });
  }
  return out;
}

/** Under 60 characters, the most specific version that fits. */
export function cityTitle(where: string, n: number, brand: string): string {
  const places = n === 1 ? 'A Place to Play Pool' : `${n} Places to Play Pool`;
  for (const t of [`${places} in ${where} | ${brand}`, `Pool Halls & Bars in ${where} | ${brand}`, `${places} in ${where}`, `Pool Halls in ${where}`]) {
    if (t.length <= TITLE_LIMIT) return t;
  }
  return where.slice(0, TITLE_LIMIT);
}

export function clip(text: string, limit = DESCRIPTION_LIMIT): string {
  return text.length <= limit ? text : text.slice(0, limit - 1).replace(/\s+\S*$/, '') + '…';
}

/** Under 155 characters: what the page compares, then its strongest fact if it fits. */
export function cityDescription(where: string, venues: V[]): string {
  const n = venues.length;
  let text = `${n === 1 ? 'One place to play pool' : `${n} places to play pool`} in ${where}, with table counts, sizes, league nights and who's open now.`;
  const facts: string[] = [];
  const most = mostTables(venues);
  if (most && most.names.length === 1) facts.push(`${most.names[0]} has ${most.tables} tables.`);
  const nine = withSize(venues, '9ft');
  if (nine.length) facts.push(namesHave(nine, '9-foot tables'));
  for (const f of facts) if ((text + ' ' + f).length <= DESCRIPTION_LIMIT) text += ' ' + f;
  return clip(text);
}
