/**
 * POST /api/submit: Add a venue, claim a venue, or suggest an edit. Goes into the
 * submissions table for a person to review; nothing on the site changes from here.
 * Spam checks: honeypot field, a minimum time on the form, size limits, same origin.
 * Add a Cloudflare rate-limiting rule on /api/submit as well.
 */
import { insert, json, sameOrigin, type Env } from '../../src/server/functions-lib.ts';

const KINDS = new Set(['add', 'claim', 'edit']);
const clean = (v: unknown, max: number) => String(v ?? '').replace(/[\u0000-\u001f\u007f]/g, ' ').trim().slice(0, max);

export const onRequestPost: PagesFunction<Env> = async ({ request, env }) => {
  if (!sameOrigin(request)) return json({ error: 'bad origin' }, 403);
  let b: Record<string, unknown>;
  try {
    b = await request.json();
  } catch {
    return json({ error: 'bad request' }, 400);
  }
  const kind = String(b.kind);
  if (!KINDS.has(kind)) return json({ error: 'bad request' }, 400);
  // Bots fill the hidden field or submit instantly. Pretend it worked.
  if (clean(b.website_url, 10) || Number(b.elapsed_ms) < 3000) return json({ ok: true });
  const email = clean(b.email, 160);
  if (email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return json({ error: 'check the email address' }, 400);
  if (kind === 'claim' && !email) return json({ error: 'an email is needed to claim' }, 400);
  if (kind === 'add' && (!clean(b.name, 120) || !clean(b.city, 80))) return json({ error: 'name and city are needed' }, 400);
  if (kind === 'edit' && !clean(b.details, 4000)) return json({ error: 'say what to change' }, 400);
  const venue = clean(b.venue, 140);
  const ok = await insert(env, 'submissions', {
    kind,
    venue_slug: /^[a-z0-9-]{2,140}$/.test(venue) ? venue : null,
    payload: {
      name: clean(b.name, 120),
      street: clean(b.street, 160),
      city: clean(b.city, 80),
      contact: clean(b.contact, 200),
      person: clean(b.person, 100),
      role: clean(b.role, 40),
      details: clean(b.details, 4000),
      source: clean(b.source, 300),
    },
    email: email || null,
  }).catch(() => false);
  return ok ? json({ ok: true }) : json({ error: 'not saved' }, 503);
};

export const onRequest: PagesFunction = async () => json({ error: 'POST only' }, 405);
