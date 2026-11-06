/**
 * POST /api/event: one click on Call, Directions, Website, Claim or Suggest an edit,
 * or one venue page view. This is the data owners get shown ("we sent you X calls").
 * Stored: venue, event, where on the site, page path, city, device, traffic source
 * bucket and the hour. Never stored: IP address, cookies, user agent, user ID or
 * the full referrer. Bots and automated browsers are dropped.
 */
import { insert, isBot, json, sameOrigin, type Env } from '../../src/server/functions-lib.ts';

const EVENTS = new Set(['call', 'directions', 'website', 'claim', 'suggest_edit', 'view']);
const WHERE = new Set(['venue_page', 'city_page', 'hub_page', 'home_tonight', 'search', 'state_page', '']);
const DEVICES = new Set(['mobile', 'desktop']);
const SOURCES = new Set(['google', 'bing', 'facebook', 'reddit', 'ai', 'direct', 'other']);
const SLUG = /^[a-z0-9-]{2,140}$/;

export const onRequestPost: PagesFunction<Env> = async ({ request, env }) => {
  if (isBot(request) || !sameOrigin(request)) return new Response(null, { status: 204 });
  let b: Record<string, string>;
  try {
    b = await request.json();
  } catch {
    return new Response(null, { status: 204 });
  }
  if (!EVENTS.has(b.e) || !SLUG.test(b.v || '')) return new Response(null, { status: 204 });
  const hour = new Date();
  hour.setUTCMinutes(0, 0, 0);
  await insert(env, 'click_events', {
    venue_slug: b.v,
    event: b.e,
    placement: WHERE.has(b.w) ? b.w : 'other',
    path: String(b.p || '').split('?')[0].slice(0, 200),
    city_key: /^[a-z0-9-]+\/[a-z0-9-]+$/.test(b.c || '') ? b.c : null,
    device: DEVICES.has(b.d) ? b.d : null,
    source: SOURCES.has(b.s) ? b.s : 'other',
    hour_utc: hour.toISOString(),
  }).catch(() => false);
  return new Response(null, { status: 204 });
};

export const onRequest: PagesFunction = async () => json({ error: 'POST only' }, 405);
