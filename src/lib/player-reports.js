/**
 * Players' "How many tables?" answers for one venue, boiled down to what they agree on.
 * Used by /admin/ (src/scripts/admin.ts) and `npm run player-reports` (scripts/player-reports.mjs),
 * so both show the same numbers.
 *
 * - One answer per player: a signed-in player's latest answer replaces their earlier ones.
 * - Count: the middle answer (median); answers within 1 table of it (or 10% for big rooms) agree.
 * - Sizes and brands: each one with how many players named it. A size gets a count only
 *   when players who named just that one size agree on the number.
 *
 * @typedef {{ count: number|null, sizes: string[], brands: string[], when: string, note?: string, user?: string|null, created_at: string }} Answer
 */

const SIZE_ORDER = ['7ft', '8ft', '9ft', 'snooker', 'carom'];

/** @param {number[]} xs */
function median(xs) {
  const s = [...xs].sort((a, b) => a - b);
  const m = s.length >> 1;
  return s.length % 2 ? s[m] : Math.round((s[m - 1] + s[m]) / 2);
}

/** @param {number[]} counts */
function consensus(counts) {
  if (!counts.length) return null;
  const mid = median(counts);
  const near = counts.filter((c) => Math.abs(c - mid) <= Math.max(1, Math.round(mid * 0.1)));
  // The most common answer among those that agree; ties go to the middle one.
  const tally = new Map();
  for (const c of near) tally.set(c, (tally.get(c) || 0) + 1);
  const top = Math.max(...tally.values());
  const best = [...tally].filter(([, n]) => n === top).map(([c]) => c);
  return { value: best.length === 1 ? best[0] : median(best), agree: near.length, of: counts.length, counts };
}

/** @param {Answer[]} answers */
export function summarize(answers) {
  const latest = new Map();
  const sorted = [...answers].sort((a, b) => a.created_at.localeCompare(b.created_at));
  sorted.forEach((a, i) => latest.set(a.user || `anon-${i}`, a));
  const list = [...latest.values()];

  const count = consensus(list.map((a) => a.count).filter((c) => typeof c === 'number' && c > 0));

  const sizes = SIZE_ORDER.map((size) => {
    const named = list.filter((a) => a.sizes?.includes(size));
    if (!named.length) return null;
    const only = named.filter((a) => a.sizes.length === 1 && typeof a.count === 'number').map((a) => a.count);
    return { size, players: named.length, count: consensus(only) };
  }).filter(Boolean);

  const brandMap = new Map();
  for (const a of list) for (const b of a.brands || []) {
    const k = b.trim().toLowerCase();
    if (!k) continue;
    const e = brandMap.get(k) || { brand: b.trim(), players: 0 };
    e.players++;
    brandMap.set(k, e);
  }
  const brands = [...brandMap.values()].sort((a, b) => b.players - a.players);

  return {
    players: list.length,
    count,
    sizes,
    brands,
    last: list.length ? sorted[sorted.length - 1].created_at : null,
    notes: list.map((a) => a.note).filter(Boolean),
  };
}
