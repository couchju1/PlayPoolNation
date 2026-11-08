/**
 * Following and events: the Follow button and Upcoming events on venue pages, and the player
 * page (/me/) news feed of events, Wall posts and reviews from the venues you follow.
 * Reads that anyone may make go straight to Supabase's REST API with the publishable key;
 * anything signed-in loads the Supabase library.
 */
import { SUPABASE_KEY, SUPABASE_URL, savedUser, sb } from './sb.ts';
import { card as wallCard, paintBall, type Post } from './wall.ts';
import { stamps } from './passport.ts';

interface Ev { id: number; venue_slug: string; kind: string; title: string; body: string; starts_at: string; ends_at: string | null; status: string }
interface V { i: string; n: string; u: string; c: string; h: { tz: string } }

const KIND: Record<string, string> = { tournament: 'Tournament', league: 'League night', special: 'Special', music: 'Live music', other: 'Event' };
const me = savedUser();
const signin = (next: string) => `/account/?next=${encodeURIComponent(next)}`;

async function rest(path: string, init?: RequestInit): Promise<any> {
  const res = await fetch(`${SUPABASE_URL}/rest/v1/${path}`, { ...init, headers: { apikey: SUPABASE_KEY, 'Content-Type': 'application/json', ...(init?.headers || {}) } });
  if (!res.ok) throw new Error(String(res.status));
  return res.json();
}
let venueList: Promise<V[]> | null = null;
const venues = () => (venueList ??= fetch('/data/venues.json').then((r) => r.json()));

/** A wall-clock date and time in a venue's time zone, as a UTC instant. */
export function zonedToUtc(date: string, time: string, tz: string): Date {
  const [y, mo, d] = date.split('-').map(Number);
  const [h, mi] = time.split(':').map(Number);
  const guess = Date.UTC(y, mo - 1, d, h, mi);
  // How far the zone is from UTC at that moment, then correct once more across a DST edge.
  const offset = (at: number) => {
    const p: Record<string, number> = {};
    new Intl.DateTimeFormat('en-US', { timeZone: tz, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
      .formatToParts(new Date(at)).forEach((x) => { if (x.type !== 'literal') p[x.type] = Number(x.value); });
    return Date.UTC(p.year, p.month - 1, p.day, p.hour % 24, p.minute) - at;
  };
  let t = guess - offset(guess);
  t = guess - offset(t);
  return new Date(t);
}
const fmt = (iso: string, tz: string, o: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('en-US', { timeZone: tz, ...o }).format(new Date(iso));

/** One event, as a card with a calendar-page date badge. */
function eventCard(e: Ev, tz: string, venue: V | null, manage = false) {
  const li = document.createElement('li');
  li.className = 'event' + (e.status === 'cancelled' ? ' is-cancelled' : '');
  li.dataset.kind = 'event';
  li.dataset.at = e.starts_at;
  li.innerHTML = `<div class="event-date" aria-hidden="true"><b></b><span></span><em></em></div><div class="event-main"><p class="event-top"><span class="post-tag"></span><span class="event-when"></span></p><h3 class="event-title"></h3><p class="event-where"></p><p class="event-body"></p><p class="event-actions"></p></div>`;
  li.querySelector('.event-date b')!.textContent = fmt(e.starts_at, tz, { month: 'short' });
  li.querySelector('.event-date span')!.textContent = fmt(e.starts_at, tz, { day: 'numeric' });
  li.querySelector('.event-date em')!.textContent = fmt(e.starts_at, tz, { weekday: 'short' });
  const tag = li.querySelector('.post-tag')!;
  tag.textContent = e.status === 'cancelled' ? 'Cancelled' : KIND[e.kind] || 'Event';
  tag.className = `post-tag post-tag--${e.status === 'cancelled' ? 'wait' : e.kind === 'tournament' || e.kind === 'league' ? 'league' : 'tables_open'}`;
  const start = fmt(e.starts_at, tz, { weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  li.querySelector('.event-when')!.textContent = start + (e.ends_at ? ` – ${fmt(e.ends_at, tz, { hour: 'numeric', minute: '2-digit' })}` : '');
  li.querySelector('.event-title')!.textContent = e.title;
  const where = li.querySelector('.event-where')!;
  if (venue) {
    const a = document.createElement('a');
    a.href = `${venue.u}#events`;
    a.textContent = venue.n;
    where.append(a, ` · ${venue.c}`);
  } else where.remove();
  const body = li.querySelector('.event-body')!;
  if (e.body) body.textContent = e.body; else body.remove();
  const actions = li.querySelector('.event-actions')!;
  if (manage) {
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.textContent = e.status === 'cancelled' ? 'Un-cancel' : 'Mark cancelled';
    toggle.addEventListener('click', async () => {
      const c = await sb();
      await c.from('events').update({ status: e.status === 'cancelled' ? 'live' : 'cancelled' }).eq('id', e.id);
      document.dispatchEvent(new Event('ppn:events'));
    });
    const del = document.createElement('button');
    del.type = 'button';
    del.textContent = 'Delete';
    del.addEventListener('click', async () => {
      if (!confirm('Delete this event?')) return;
      const c = await sb();
      await c.from('events').delete().eq('id', e.id);
      document.dispatchEvent(new Event('ppn:events'));
    });
    actions.append(toggle, del);
  } else actions.remove();
  return li;
}

/** A review in the feed. */
function reviewCard(r: { id: number; venue_slug: string; rating: number; body: string; created_at: string; user_id: string }, name: string, venue: V | null) {
  const li = document.createElement('li');
  li.className = 'post feed-review';
  li.dataset.kind = 'review';
  li.dataset.at = r.created_at;
  li.innerHTML = '<span class="ball" aria-hidden="true"></span><div class="post-main"><p class="post-top"><b class="post-name"></b> reviewed <span class="post-where"></span></p><p class="review-top"><span class="stars" aria-hidden="true"></span><span class="review-date"></span></p><p class="post-body"></p></div>';
  paintBall(li.querySelector('.ball')!, name);
  li.querySelector('.post-name')!.textContent = name;
  if (venue) {
    const a = document.createElement('a');
    a.href = `${venue.u}#reviews`;
    a.textContent = venue.n;
    li.querySelector('.post-where')!.append(a);
  }
  const stars = li.querySelector<HTMLElement>('.stars')!;
  stars.style.setProperty('--pct', `${(r.rating / 5) * 100}%`);
  li.querySelector('.review-top')!.setAttribute('aria-label', `${r.rating} out of 5`);
  li.querySelector('.review-date')!.textContent = new Date(r.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const body = li.querySelector('.post-body')!;
  if (r.body) body.textContent = r.body; else body.remove();
  return li;
}

/* ---------------------------------------------------------- Follow button */
async function setFollow(slug: string, on: boolean) {
  const c = await sb();
  const { error } = on ? await c.from('follows').insert({ user_id: me!.id, venue_slug: slug }) : await c.from('follows').delete().eq('user_id', me!.id).eq('venue_slug', slug);
  if (error && !/duplicate/i.test(error.message)) throw error;
}
const follow = document.querySelector<HTMLElement>('[data-follow]');
if (follow) {
  const slug = follow.dataset.follow!;
  const btn = follow.querySelector<HTMLButtonElement>('[data-follow-btn]')!;
  const count = follow.querySelector<HTMLElement>('[data-follow-count]')!;
  let n = 0;
  const draw = (on: boolean) => {
    btn.setAttribute('aria-pressed', String(on));
    btn.textContent = on ? 'Following' : 'Follow';
    btn.classList.toggle('is-on', on);
    count.textContent = n ? `${n} ${n === 1 ? 'follower' : 'followers'}` : '';
  };
  rest('rpc/follower_count', { method: 'POST', body: JSON.stringify({ p_slug: slug }) }).then((x) => { n = Number(x) || 0; draw(btn.classList.contains('is-on')); }).catch(() => {});
  if (me) {
    sb().then(async (c) => {
      const { data } = await c.from('follows').select('venue_slug').eq('user_id', me.id).eq('venue_slug', slug).maybeSingle();
      draw(!!data);
    }).catch(() => {});
  }
  draw(false);
  btn.addEventListener('click', async () => {
    if (!me) { location.assign(signin(location.pathname)); return; }
    const on = !btn.classList.contains('is-on');
    n += on ? 1 : -1;
    draw(on);
    try { await setFollow(slug, on); } catch { n += on ? -1 : 1; draw(!on); }
  });
}

/* ------------------------------------------------- Upcoming events (venue) */
const evBox = document.querySelector<HTMLElement>('[data-events]');
if (evBox) {
  const slug = evBox.dataset.venue!;
  const tz = evBox.dataset.tz!;
  const list = evBox.querySelector<HTMLOListElement>('[data-event-list]')!;
  const form = evBox.querySelector<HTMLFormElement>('[data-event-form]')!;
  const newBtn = evBox.querySelector<HTMLButtonElement>('[data-event-new]')!;
  const msg = form.querySelector<HTMLElement>('[data-event-msg]')!;
  let manage = false;
  const since = () => new Date(Date.now() - 6 * 3600e3).toISOString();
  const load = async () => {
    let rows: Ev[];
    if (manage) {
      const c = await sb();
      ({ data: rows } = await c.from('events').select('*').eq('venue_slug', slug).gte('starts_at', since()).order('starts_at'));
    } else {
      rows = await rest(`events?select=*&venue_slug=eq.${slug}&status=in.(live,cancelled)&starts_at=gte.${encodeURIComponent(since())}&order=starts_at.asc`);
    }
    list.replaceChildren(...(rows || []).map((e) => eventCard(e, tz, null, manage)));
    if (!rows?.length && manage) list.innerHTML = '<li class="feed-empty">No upcoming events. Post one and followers will see it in their feed.</li>';
    evBox.hidden = !(rows?.length || manage);
  };
  document.addEventListener('ppn:events', () => load());
  load().catch(() => {});
  if (me) {
    sb().then(async (c) => {
      const { data } = await c.rpc('can_manage_venue', { p_slug: slug });
      if (!data) return;
      manage = true;
      newBtn.hidden = false;
      await load();
    }).catch(() => {});
  }
  newBtn.addEventListener('click', () => {
    form.hidden = !form.hidden;
    if (!form.hidden) {
      const today = new Intl.DateTimeFormat('en-CA', { timeZone: tz }).format(new Date());
      const d = form.elements.namedItem('date') as HTMLInputElement;
      if (!d.value) d.value = today;
      d.min = today;
      (form.elements.namedItem('title') as HTMLInputElement).focus();
    }
  });
  form.querySelector('[data-event-cancel]')!.addEventListener('click', () => { form.hidden = true; });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const date = String(fd.get('date')), start = String(fd.get('start')), end = String(fd.get('end') || '');
    const starts = zonedToUtc(date, start, tz);
    let ends: Date | null = end ? zonedToUtc(date, end, tz) : null;
    if (ends && ends <= starts) ends = new Date(ends.getTime() + 86400e3); // ends after midnight
    msg.textContent = 'Posting…';
    try {
      const c = await sb();
      const { error } = await c.from('events').insert({
        venue_slug: slug, kind: fd.get('kind'), title: String(fd.get('title')).trim(), body: String(fd.get('body') || '').trim(),
        starts_at: starts.toISOString(), ends_at: ends ? ends.toISOString() : null, created_by: me!.id,
      });
      if (error) throw error;
      form.reset();
      form.hidden = true;
      msg.textContent = '';
      await load();
    } catch {
      msg.textContent = 'Not posted. Check the details and try again.';
    }
  });
}

/* ---------------------------------------------------------- Player page */
const page = document.querySelector<HTMLElement>('[data-me]');
if (page) {
  if (!me) location.replace(signin('/me/'));
  else (async () => {
    const c = await sb();
    const { data: { session } } = await c.auth.getSession();
    if (!session) { location.replace(signin('/me/')); return; }
    const user = session.user;
    const [{ data: prof }, { data: fl }, { data: mine }, all] = await Promise.all([
      c.from('profiles').select('display_name, created_at').eq('id', user.id).maybeSingle(),
      c.from('follows').select('venue_slug, created_at').eq('user_id', user.id).order('created_at', { ascending: false }),
      c.from('reviews').select('id').eq('user_id', user.id),
      venues(),
    ]);
    const byId = new Map(all.map((v) => [v.i, v]));
    const name = prof?.display_name || user.user_metadata?.display_name || user.email.split('@')[0];
    paintBall(page.querySelector('[data-me-ball]')!, name);
    page.querySelector('[data-me-name]')!.textContent = name;
    page.querySelector('[data-me-since]')!.textContent = `Member since ${new Date(prof?.created_at || user.created_at).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}`;
    const slugs: string[] = (fl || []).map((f: any) => f.venue_slug);
    page.querySelector('[data-me-following]')!.textContent = String(slugs.length);
    page.querySelector('[data-me-reviews]')!.textContent = String(mine?.length || 0);
    page.querySelector('[data-me-stamps]')!.textContent = String(Object.keys(stamps()).length);

    const ul = page.querySelector<HTMLUListElement>('[data-me-list]')!;
    ul.replaceChildren(...slugs.map((s) => {
      const v = byId.get(s);
      const li = document.createElement('li');
      li.innerHTML = '<a></a><button type="button" class="me-unfollow">Unfollow</button>';
      const a = li.querySelector('a')!;
      a.href = v?.u || `/place/${s}/`;
      a.innerHTML = '<b></b><span></span>';
      a.querySelector('b')!.textContent = v?.n || s;
      a.querySelector('span')!.textContent = v?.c || '';
      li.querySelector('button')!.addEventListener('click', async () => { await setFollow(s, false); location.reload(); });
      return li;
    }));
    page.querySelector<HTMLElement>('[data-me-hint]')!.hidden = slugs.length > 0;

    const items = page.querySelector<HTMLOListElement>('[data-me-items]')!;
    const suggest = page.querySelector<HTMLElement>('[data-me-suggest]')!;
    suggest.querySelectorAll<HTMLButtonElement>('[data-suggest-follow]').forEach((b) => {
      if (slugs.includes(b.dataset.suggestFollow!)) b.closest('li')!.remove();
      b.addEventListener('click', async () => { b.disabled = true; b.textContent = 'Following'; await setFollow(b.dataset.suggestFollow!, true); location.reload(); });
    });

    let feed: HTMLElement[] = [];
    if (slugs.length) {
      const ids = slugs.slice(0, 100);
      const since = new Date(Date.now() - 6 * 3600e3).toISOString();
      const [evs, wall, revs] = await Promise.all([
        rest(`events?select=*&venue_slug=in.(${ids.join(',')})&status=in.(live,cancelled)&starts_at=gte.${encodeURIComponent(since)}&order=starts_at.asc&limit=100`).catch(() => []),
        fetch(`/api/feed?vs=${ids.join(',')}`, { cache: 'no-store' }).then((r) => r.json()).then((j) => j.posts || []).catch(() => []),
        rest(`reviews?select=id,venue_slug,user_id,rating,body,created_at&venue_slug=in.(${ids.join(',')})&order=created_at.desc&limit=30`).catch(() => []),
      ]);
      const uids = [...new Set((revs as any[]).map((r) => r.user_id))];
      const names = new Map<string, string>();
      if (uids.length) (await rest(`profiles?select=id,display_name&id=in.(${uids.join(',')})`).catch(() => [])).forEach((p: any) => names.set(p.id, p.display_name));
      // Upcoming events lead, soonest first; then everything else, newest first.
      const evCards = (evs as Ev[]).map((e) => eventCard(e, byId.get(e.venue_slug)?.h.tz || 'America/Chicago', byId.get(e.venue_slug) || null));
      const rest2 = [
        ...(wall as Post[]).map((p) => { const li = wallCard(p, byId.get(p.venue_slug) ? { n: byId.get(p.venue_slug)!.n, u: byId.get(p.venue_slug)!.u, c: byId.get(p.venue_slug)!.c } : null); li.dataset.kind = 'wall'; li.dataset.at = p.created_at; return li; }),
        ...(revs as any[]).map((r) => reviewCard(r, names.get(r.user_id) || 'A player', byId.get(r.venue_slug) || null)),
      ].sort((a, b) => b.dataset.at!.localeCompare(a.dataset.at!));
      feed = [...evCards, ...rest2];
    }
    const empty = (what: string) => {
      const li = document.createElement('li');
      li.className = 'feed-empty';
      li.textContent = slugs.length ? `Nothing ${what} from the bars you follow yet.` : 'Your feed is empty. Follow a few bars and their events, check-ins and reviews show up here.';
      return li;
    };
    const show = (tab: string) => {
      const list = feed.filter((li) => tab === 'all' || li.dataset.kind === tab);
      items.replaceChildren(...(list.length ? list : [empty(tab === 'event' ? 'coming up' : tab === 'wall' ? 'on the Wall' : tab === 'review' ? 'reviewed' : 'posted')]));
    };
    page.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((t) => t.addEventListener('click', () => {
      page.querySelectorAll('[data-tab]').forEach((x) => x.setAttribute('aria-selected', String(x === t)));
      show(t.dataset.tab!);
    }));
    show('all');
    suggest.hidden = slugs.length >= 3 || !suggest.querySelector('li');
    page.hidden = false;
    document.querySelector<HTMLElement>('[data-me-loading]')!.hidden = true;
  })().catch(() => { document.querySelector('[data-me-loading]')!.textContent = 'Your feed could not load right now. Try again in a minute.'; });
}
