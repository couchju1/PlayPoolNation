/**
 * "The room": a venue's tables drawn to scale from above, grouped by size and,
 * when the models line adds up, by brand. Only sourced counts are drawn; tables
 * in the total whose size isn't known are drawn as outlines. Ported from the
 * plugin's Table_Room.
 */
import type { SizeKey } from './types.ts';

export const ROOM_SIZES: Record<SizeKey, [string, number]> = {
  '7ft': ['7-foot', 78], '8ft': ['8-foot', 88], '9ft': ['9-foot', 100], snooker: ['Snooker', 144], carom: ['Carom', 112],
};
export const MAX_DRAWN = 48;
const FEET: Record<string, SizeKey> = { '7': '7ft', '8': '8ft', '9': '9ft' };

export interface Group { size: SizeKey | ''; count: number; brand: string }

export function fromModels(models: string, sizeCounts: Partial<Record<SizeKey, number>>): Group[] | null {
  const text = models.replace(/[’′"]/g, "'").trim();
  if (!text || !Object.keys(sizeCounts).length) return null;
  const groups: Group[] = [];
  const sums: Partial<Record<SizeKey, number>> = {};
  for (const part of text.split(/\s*(?:,|;|\band\b)\s*/i)) {
    if (!part) continue;
    const m = /^(\d{1,3})\s+(.+?)\s+([789])(?:'|\s?-?\s?(?:ft|foot|feet)\b\.?)$/i.exec(part);
    if (!m) return null;
    const size = FEET[m[3]];
    groups.push({ size, count: Number(m[1]), brand: m[2].replace(/\s+/g, ' ').trim() });
    sums[size] = (sums[size] ?? 0) + Number(m[1]);
  }
  if (!groups.length) return null;
  for (const [size, n] of Object.entries(sums)) if (sizeCounts[size as SizeKey] !== n) return null;
  for (const [size, n] of Object.entries(sizeCounts)) if (!(size in sums) && n) groups.push({ size: size as SizeKey, count: n, brand: '' });
  return groups;
}

export function groups(sizeCounts: Partial<Record<SizeKey, number>>, total: number | null, models = ''): Group[] {
  const counts = Object.fromEntries(Object.entries(sizeCounts).filter(([s, n]) => s in ROOM_SIZES && (n ?? 0) > 0)) as Partial<Record<SizeKey, number>>;
  const out = fromModels(models, counts) ?? Object.entries(counts).map(([size, count]) => ({ size: size as SizeKey, count: count!, brand: '' }));
  const known = out.reduce((s, g) => s + g.count, 0);
  if (total !== null && total > known) out.push({ size: '', count: total - known, brand: '' });
  const len = (g: Group) => (g.size === '' ? -1 : ROOM_SIZES[g.size][1]);
  return out.sort((a, b) => len(b) - len(a) || b.count - a.count || a.brand.localeCompare(b.brand));
}

export function caption(g: Group, alone = false): string {
  if (g.size === '') return alone ? `${g.count}, size not known yet` : `${g.count} more, size not known`;
  return `${g.count} ${g.brand} ${ROOM_SIZES[g.size][0]}`.replace(/\s+/g, ' ').trim();
}

export function summary(list: Group[]): string {
  const total = list.reduce((s, g) => s + g.count, 0);
  const t = total === 1 ? ' table' : ' tables';
  if (list.length === 1 && list[0].size === '') return `${total}${t}, sizes not known yet.`;
  const parts = list.map((g) => caption(g));
  const joined = parts.length > 1 ? parts.slice(0, -1).join(', ') + ' and ' + parts[parts.length - 1] : parts[0];
  return total + t + (list.length > 1 || list[0].brand ? ': ' + joined : ', all ' + ROOM_SIZES[list[0].size as SizeKey][0]) + '.';
}
