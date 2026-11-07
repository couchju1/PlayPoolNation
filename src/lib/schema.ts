/** JSON-LD. Real values only: no rating without reviews, no image without a photo. */
import { SITE } from './data.ts';
import type { Venue } from './types.ts';
import * as T from './text.ts';
import { SIZE_WORDS } from './format.ts';

const DAY_URIS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].map((d) => `https://schema.org/${d}`);

export type Crumb = [name: string, url: string];

export function breadcrumb(crumbs: Crumb[], pageUrl: string) {
  return {
    '@type': 'BreadcrumbList',
    '@id': SITE + pageUrl + '#breadcrumb',
    itemListElement: crumbs.map(([name, url], i) => ({ '@type': 'ListItem', position: i + 1, name, item: SITE + (url || pageUrl) })),
  };
}

export function itemList(name: string, pageUrl: string, list: Venue[]) {
  return {
    '@type': 'ItemList',
    '@id': SITE + pageUrl + '#places',
    name,
    numberOfItems: list.length,
    itemListElement: list.map((v, i) => ({ '@type': 'ListItem', position: i + 1, url: SITE + v.url, name: v.name })),
  };
}

export function faqPage(pageUrl: string, faq: { q: string; a: string }[]) {
  return {
    '@type': 'FAQPage',
    '@id': SITE + pageUrl + '#faq',
    mainEntity: faq.map((q) => ({ '@type': 'Question', name: q.q, acceptedAnswer: { '@type': 'Answer', text: q.a } })),
  };
}

/** The most specific LocalBusiness subtype schema.org has for the venue. */
export function businessType(v: Venue): string {
  if ((v.type === 'bars-with-pool-tables' || v.type === 'sports-bar')) return 'BarOrPub';
  if (v.type === 'bowling-center') return 'BowlingAlley';
  return 'SportsActivityLocation';
}

const hhmm = (m: number) => `${String(Math.floor((m % 1440) / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;

export function openingHours(ranges: [number, number][]) {
  const out = [];
  for (const [s, e] of ranges) {
    if (e <= s) continue;
    const day = Math.floor(s / 1440) % 7;
    if (e - s >= 1440) out.push({ '@type': 'OpeningHoursSpecification', dayOfWeek: DAY_URIS[day], opens: '00:00', closes: '23:59' });
    else out.push({ '@type': 'OpeningHoursSpecification', dayOfWeek: DAY_URIS[day], opens: hhmm(s), closes: hhmm(e) });
  }
  return out;
}

export function venueSchema(v: Venue) {
  const a = v.address;
  const data: Record<string, unknown> = {
    '@type': businessType(v),
    '@id': SITE + v.url + '#venue',
    name: v.name,
    url: SITE + v.url,
  };
  if (a.street || a.city) {
    data.address = Object.fromEntries(
      Object.entries({ '@type': 'PostalAddress', streetAddress: a.street, addressLocality: a.city, addressRegion: a.state, postalCode: a.postal_code, addressCountry: a.country || 'US' }).filter(([, x]) => x),
    );
  }
  if (v.geo) data.geo = { '@type': 'GeoCoordinates', latitude: v.geo.lat, longitude: v.geo.lng };
  if (v.phone) data.telephone = v.phone;
  if (v.website) data.sameAs = [v.website];
  if (v.photo) data.image = v.photo;
  if (v.hours?.ranges?.length && v.status !== 'permanently-closed') data.openingHoursSpecification = openingHours(v.hours.ranges);
  const features: { '@type': string; name: string; value: number | boolean }[] = [];
  const t = T.tables(v);
  if (t) features.push({ '@type': 'LocationFeatureSpecification', name: 'Pool tables', value: t });
  for (const s of T.sizes(v)) features.push({ '@type': 'LocationFeatureSpecification', name: `${SIZE_WORDS[s]} tables`, value: v.sizeCounts[s] ?? true });
  for (const b of T.brands(v)) features.push({ '@type': 'LocationFeatureSpecification', name: `${b} tables`, value: true });
  for (const g of v.games) features.push({ '@type': 'LocationFeatureSpecification', name: g, value: true });
  if (v.food) features.push({ '@type': 'LocationFeatureSpecification', name: 'Food', value: true });
  if (v.leagues.length) features.push({ '@type': 'LocationFeatureSpecification', name: 'Pool leagues', value: true });
  if (features.length) data.amenityFeature = features;
  return data;
}

export function graph(nodes: object[]) {
  return JSON.stringify({ '@context': 'https://schema.org', '@graph': nodes }).replace(/</g, '\\u003c');
}
