/**
 * /dashboard/: a venue's numbers for its approved owner or an admin. Lead counts come from
 * click_events (row-level security lets only the owner or an admin read them), the rest from
 * the same public sources the venue page uses. The chart is plain SVG: stacked bars per day,
 * with a legend, per-day hover and a table view.
 */
import { SUPABASE_KEY, SUPABASE_URL, savedUser, sb } from './sb.ts';

interface V { i: string; n: string; u: string; c: string; h: { tz: string } }
type Click = { event: string; source: string; device: string | null; hour_utc: string };

const SERIES = [
  { key: 'call', label: 'Calls', color: '#2a78d6' },
  { key: 'directions', label: 'Directions', color: '#eb6834' },
  { key: 'website', label: 'Website taps', color: '#1baf7a' },
] as const;
const SOURCES: Record<string, string> = { google: 'Google', bing: 'Bing and other search', direct: 'Direct or typed in', facebook: 'Facebook', reddit: 'Reddit', ai: 'AI assistants', other: 'Other sites' };
const DAY = 86400e3;

const root = document.querySelector<HTMLElement>('[data-dash]')!;
const msg = document.querySelector<HTMLElement>('[data-dash-msg]')!;
const $ = <T extends Element = HTMLElement>(s: string) => root.querySelector<T>(s)!;
const set = (k: string, v: string) => root.querySelectorAll(`[data-k="${k}"]`).forEach((el) => (el.textContent = v));
const pub = (path: string) => fetch(`${SUPABASE_URL}/rest/v1/${path}`, { headers: { apikey: SUPABASE_KEY } }).then((r) => (r.ok ? r.json() : []));

function delta(cur: number, prev: number) {
  if (!cur && !prev) return '';
  if (!prev) return 'New this month';
  const pct = Math.round(((cur - prev) / prev) * 100);
  // The line above the tiles says what it's compared with, so the tile stays short.
  return pct === 0 ? 'No change' : `${pct > 0 ? '▲' : '▼'} ${Math.abs(pct)}%`;
}

function drawChart(days: { day: string; label: string; v: Record<string, number> }[]) {
  const box = $('[data-chart]');
  const tip = $('[data-chart-tip]');
  const W = Math.max(320, box.clientWidth), H = 220, padL = 28, padB = 24, padT = 8;
  const totals = days.map((d) => SERIES.reduce((a, s) => a + d.v[s.key], 0));
  const max = Math.max(3, ...totals);
  const step = max <= 5 ? 1 : max <= 12 ? 2 : Math.ceil(max / 5);
  const top = Math.ceil(max / step) * step;
  const y = (n: number) => padT + (H - padT - padB) * (1 - n / top);
  const bw = (W - padL) / days.length;
  const barW = Math.max(3, Math.min(18, bw - 4));
  let svg = `<svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}" aria-hidden="true">`;
  for (let g = 0; g <= top; g += step) svg += `<line x1="${padL}" x2="${W}" y1="${y(g)}" y2="${y(g)}" class="grid"/><text x="${padL - 6}" y="${y(g) + 4}" text-anchor="end" class="axis">${g}</text>`;
  days.forEach((d, i) => {
    const x = padL + i * bw + (bw - barW) / 2;
    let base = 0;
    const segs = SERIES.filter((s) => d.v[s.key] > 0);
    segs.forEach((s, j) => {
      const y1 = y(base + d.v[s.key]), y0 = y(base);
      const h = Math.max(1, y0 - y1 - (j > 0 ? 2 : 0)); // 2px gap between stacked segments
      const r = j === segs.length - 1 ? Math.min(4, h / 2, barW / 2) : 0; // rounded top on the last segment only
      svg += `<path d="M${x},${y0 - (j > 0 ? 2 : 0)}v${-(h - r)}${r ? `q0,${-r} ${r},${-r}h${barW - 2 * r}q${r},0 ${r},${r}` : `h${barW}`}v${h - r}z" fill="${s.color}"/>`;
      base += d.v[s.key];
    });
    if (i % 7 === 0 || (i === days.length - 1 && i % 7 > 3)) svg += `<text x="${x + barW / 2}" y="${H - 6}" text-anchor="middle" class="axis">${d.label}</text>`;
    svg += `<rect x="${padL + i * bw}" y="0" width="${bw}" height="${H - padB}" fill="transparent" data-i="${i}" class="hit"/>`;
  });
  box.innerHTML = svg + '</svg>';
  box.querySelectorAll<SVGRectElement>('.hit').forEach((r) => {
    const show = () => {
      const d = days[Number(r.dataset.i)];
      tip.innerHTML = `<b>${d.day}</b>` + SERIES.map((s) => `<span><i style="--c:${s.color}"></i>${s.label}<em>${d.v[s.key]}</em></span>`).join('') + `<span class="tip-views">Page views<em>${d.v.view}</em></span>`;
      const bb = r.getBoundingClientRect(), pb = box.getBoundingClientRect();
      tip.style.left = `${Math.min(Math.max(bb.left - pb.left + bb.width / 2, 80), pb.width - 80)}px`;
      tip.hidden = false;
      r.classList.add('on');
    };
    r.addEventListener('mouseenter', show);
    r.addEventListener('click', show);
    r.addEventListener('mouseleave', () => { tip.hidden = true; r.classList.remove('on'); });
  });
  const body = $('[data-chart-table] tbody');
  body.replaceChildren(...days.slice().reverse().map((d) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<th scope="row"></th><td>${d.v.call}</td><td>${d.v.directions}</td><td>${d.v.website}</td><td>${d.v.view}</td>`;
    tr.querySelector('th')!.textContent = d.day;
    return tr;
  }));
  $('[data-chart-empty]').hidden = totals.some(Boolean);
}

async function load(c: any, v: V) {
  const slug = v.i;
  $('[data-dash-name]').textContent = v.n;
  $('[data-dash-where]').textContent = v.c;
  root.querySelector<HTMLAnchorElement>('[data-go="events"]')!.href = `${v.u}#events`;
  root.querySelector<HTMLAnchorElement>('[data-go="photos"]')!.href = `${v.u}#photos`;
  root.querySelector<HTMLAnchorElement>('[data-go="page"]')!.href = v.u;
  history.replaceState(null, '', `?v=${slug}`);
  const now = Date.now();
  const since = new Date(now - 60 * DAY).toISOString();
  const [{ data: clicks }, follows, reviews, leads, feed, events, photos] = await Promise.all([
    c.from('click_events').select('event,source,device,hour_utc').eq('venue_slug', slug).gte('hour_utc', since).range(0, 9999),
    fetch(`${SUPABASE_URL}/rest/v1/rpc/follower_count`, { method: 'POST', headers: { apikey: SUPABASE_KEY, 'Content-Type': 'application/json' }, body: JSON.stringify({ p_slug: slug }) }).then((r) => r.json()).catch(() => 0),
    pub(`reviews?select=rating&venue_slug=eq.${slug}`),
    c.rpc('venue_review_leads', { p_slug: slug }).then((r: any) => r.data || []),
    fetch(`/api/feed?v=${slug}`, { cache: 'no-store' }).then((r) => r.json()).then((j) => j.posts || []).catch(() => []),
    pub(`events?select=id&venue_slug=eq.${slug}&status=eq.live&starts_at=gte.${encodeURIComponent(new Date(now - 6 * 3600e3).toISOString())}`),
    pub(`venue_photos?select=id&venue_slug=eq.${slug}`),
  ]);
  const all: Click[] = clicks || [];
  const cur = all.filter((x) => now - Date.parse(x.hour_utc) <= 30 * DAY);
  const prev = all.filter((x) => now - Date.parse(x.hour_utc) > 30 * DAY);
  const count = (list: Click[], e: string) => list.filter((x) => x.event === e).length;
  const leadsOf = (list: Click[]) => SERIES.reduce((a, s) => a + count(list, s.key), 0);
  set('leads', String(leadsOf(cur)));
  root.querySelector('[data-d="leads"]')!.textContent = delta(leadsOf(cur), leadsOf(prev));
  for (const k of ['view', 'call', 'directions', 'website']) {
    set(k, String(count(cur, k)));
    root.querySelector(`[data-d="${k}"]`)!.textContent = delta(count(cur, k), count(prev, k));
  }
  set('followers', String(Number(follows) || 0));
  const n = reviews.length;
  set('rating', n ? (reviews.reduce((a: number, r: any) => a + r.rating, 0) / n).toFixed(1) : '–');
  set('reviews', n ? `${n} ${n === 1 ? 'review' : 'reviews'}` : 'No reviews yet');
  set('emails', String(leads.length));
  const csv = $('[data-csv]');
  csv.hidden = !leads.length;
  $('[data-csv-none]').hidden = leads.length > 0;
  csv.onclick = () => {
    const q = (s: string) => `"${String(s ?? '').replace(/"/g, '""')}"`;
    const rows = [['Email', 'Name', 'Rating', 'Reviewed on'], ...leads.map((l: any) => [l.email, l.display_name, l.rating, l.created_at.slice(0, 10)])];
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([rows.map((r) => r.map(q).join(',')).join('\r\n')], { type: 'text/csv' }));
    a.download = `${slug}-email-leads.csv`;
    a.click();
  };
  set('wall', String(feed.filter((p: any) => now - Date.parse(p.created_at) <= 30 * DAY).length));
  set('events', String(events.length));
  set('photos', String(photos.length));

  // Days in the venue's own time zone, oldest first.
  const dayKey = (t: number) => new Intl.DateTimeFormat('en-CA', { timeZone: v.h.tz }).format(new Date(t));
  const days = Array.from({ length: 30 }, (_, i) => {
    const t = now - (29 - i) * DAY;
    return { key: dayKey(t), day: new Intl.DateTimeFormat('en-US', { timeZone: v.h.tz, weekday: 'short', month: 'short', day: 'numeric' }).format(new Date(t)), label: new Intl.DateTimeFormat('en-US', { timeZone: v.h.tz, month: 'short', day: 'numeric' }).format(new Date(t)), v: { call: 0, directions: 0, website: 0, view: 0 } as Record<string, number> };
  });
  const byKey = new Map(days.map((d) => [d.key, d]));
  cur.forEach((x) => { const d = byKey.get(dayKey(Date.parse(x.hour_utc))); if (d && x.event in d.v) d.v[x.event]++; });
  drawChart(days);
  addEventListener('resize', () => drawChart(days));

  const bySrc = new Map<string, number>();
  cur.forEach((x) => bySrc.set(x.source, (bySrc.get(x.source) || 0) + 1));
  const total = cur.length;
  const top = Math.max(1, ...bySrc.values());
  $('[data-sources]').replaceChildren(...[...bySrc.entries()].sort((a, b) => b[1] - a[1]).map(([s, k]) => {
    const li = document.createElement('li');
    li.innerHTML = '<span class="src-name"></span><span class="src-bar"><i></i></span><span class="src-n"></span>';
    li.querySelector('.src-name')!.textContent = SOURCES[s] || s;
    li.querySelector<HTMLElement>('i')!.style.width = `${(k / top) * 100}%`;
    li.querySelector('.src-n')!.textContent = `${k} · ${Math.round((k / total) * 100)}%`;
    return li;
  }));
  if (!total) $('[data-sources]').innerHTML = '<li class="muted">No visits counted yet in the last 30 days.</li>';
  const phones = cur.filter((x) => x.device === 'mobile').length;
  $('[data-devices]').textContent = total ? `${Math.round((phones / total) * 100)}% on phones, ${100 - Math.round((phones / total) * 100)}% on computers.` : '';
}

(async () => {
  const me = savedUser();
  if (!me) { location.replace('/account/?next=/dashboard/'); return; }
  const c = await sb();
  const [{ data: admin }, { data: mine }, all] = await Promise.all([c.rpc('is_admin'), c.rpc('my_venues'), fetch('/data/venues.json').then((r) => r.json() as Promise<V[]>)]);
  const owned: string[] = (mine || []).map((x: any) => (typeof x === 'string' ? x : x.my_venues));
  const choices = admin ? all : all.filter((v) => owned.includes(v.i));
  if (!choices.length) {
    msg.innerHTML = 'The dashboard is for venue owners. <a href="/list-your-venue/">Claim your venue</a> and we\'ll set you up.';
    return;
  }
  const list = $('[data-dash-list]');
  list.replaceChildren(...choices.map((v) => { const o = document.createElement('option'); o.value = `${v.n} · ${v.c}`; return o; }));
  const pickIn = $<HTMLInputElement>('#dash-venue');
  const asked = new URLSearchParams(location.search).get('v');
  let current = choices.find((v) => v.i === asked) || choices.find((v) => owned.includes(v.i)) || choices[0];
  pickIn.value = `${current.n} · ${current.c}`;
  pickIn.addEventListener('change', () => {
    const v = choices.find((x) => `${x.n} · ${x.c}` === pickIn.value);
    if (v && v !== current) { current = v; load(c, v); }
  });
  const toggle = $('[data-chart-toggle]');
  toggle.addEventListener('click', () => {
    const on = toggle.getAttribute('aria-pressed') !== 'true';
    toggle.setAttribute('aria-pressed', String(on));
    toggle.textContent = on ? 'Show as chart' : 'Show as table';
    $('[data-chart]').hidden = on;
    $('[data-chart-table]').hidden = !on;
  });
  root.hidden = false;
  msg.hidden = true;
  await load(c, current);
})().catch(() => { msg.textContent = 'The dashboard could not load right now. Try again in a minute.'; });
