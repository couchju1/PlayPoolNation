/** Formatting helpers. Pure functions, unit tested. */
import type { SizeKey } from './types.ts';

export const SIZE_ORDER: SizeKey[] = ['7ft', '8ft', '9ft', 'snooker', 'carom'];
export const SIZE_WORDS: Record<SizeKey, string> = { '7ft': '7-foot', '8ft': '8-foot', '9ft': '9-foot', snooker: 'snooker', carom: 'carom' };
export const SIZE_SHORT: Record<SizeKey, string> = { '7ft': "7'", '8ft': "8'", '9ft': "9'", snooker: 'Snooker', carom: 'Carom' };

export function slugify(text: string): string {
  return text
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[&+]/g, ' and ')
    .replace(/['`"’]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/** "a, b and c". */
export function andList(items: string[]): string {
  const list = items.map(String).filter((s) => s.length);
  const last = list.pop();
  return list.length ? `${list.join(', ')} and ${last}` : last ?? '';
}

/** "7' & 9'". */
export function joinList(items: string[]): string {
  const list = items.filter((s) => s.length);
  if (list.length <= 1) return list[0] ?? '';
  return `${list.slice(0, -1).join(', ')} & ${list[list.length - 1]}`;
}

export function sizesLabel(sizes: SizeKey[]): string {
  return joinList(SIZE_ORDER.filter((s) => sizes.includes(s)).map((s) => SIZE_SHORT[s]));
}

export function money(amount: unknown): string {
  if (amount === '' || amount === null || amount === undefined || Number.isNaN(Number(amount))) return '';
  const n = Number(amount);
  return '$' + (Math.floor(n) === n ? n.toLocaleString('en-US') : n.toFixed(2));
}

/** "(605) 271-2951" to "+16052712951" for tel: links; '' when it isn't a phone number. */
export function e164(phone: string): string {
  const plus = phone.trim().startsWith('+');
  const digits = phone.replace(/\D+/g, '');
  if (digits.length === 10 && !plus) return '+1' + digits;
  if (digits.length === 11 && digits[0] === '1') return '+' + digits;
  return plus && digits.length >= 8 ? '+' + digits : '';
}

export function directionsUrl(destination: string): string {
  return 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(destination.trim());
}

/** The host a link points at, for "Source: bigsbar.com". */
export function host(url: string): string {
  try {
    return new URL(url).host.replace(/^www\./, '');
  } catch {
    return '';
  }
}

/** "2026-10-26" to "Oct 26, 2026". */
export function shortDate(ymd: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd);
  if (!m) return '';
  const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
}

export function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!);
}
