/**
 * Everything the browser adds to the static pages. Pages are complete without it.
 *  - open/closed chips from each venue's hours, in the venue's own time zone
 *  - first-party click counts for Call, Directions, Website, Claim, Suggest an edit
 *  - city page filters and table sort
 *  - the map, loaded only when "Show map" is pressed
 *  - the homepage "Play tonight" panel
 */
import { WEEK, miles, openStatus, venueWeekday, weekMinute } from '../lib/open-status.js';

const now = new Date();

/* ------------------------------------------------------------ open status */
function paintStatus(root: ParentNode = document) {
  root.querySelectorAll<HTMLElement>('[data-hours]').forEach((el) => {
    try {
      const s = openStatus(JSON.parse(el.dataset.hours!), now);
      el.textContent = s.label;
      el.classList.remove('status--open', 'status--closed');
      if (s.state !== 'unknown') el.classList.add(`status--${s.state}`);
      el.dataset.state = s.state;
    } catch {
      /* leave it empty */
    }
  });
}
paintStatus();

/* -------------------------------------------------------- click tracking */
function sourceBucket(): string {
  let landing = '';
  try {
    landing = sessionStorage.getItem('ppn_src') || '';
  } catch {
    /* storage blocked */
  }
  if (landing) return landing;
  const ref = document.referrer;
  let bucket = 'direct';
  if (ref && !ref.startsWith(location.origin)) {
    const h = (() => { try { return new URL(ref).hostname; } catch { return ''; } })();
    bucket = /google\./.test(h) ? 'google' : /bing\.|duckduckgo|yahoo/.test(h) ? 'bing' : /facebook|fb\./.test(h) ? 'facebook' : /reddit/.test(h) ? 'reddit' : /chatgpt|openai|perplexity|claude\.ai|gemini/.test(h) ? 'ai' : 'other';
  }
  try {
    sessionStorage.setItem('ppn_src', bucket);
  } catch {
    /* fine */
  }
  return bucket;
}
const src = sourceBucket();

export function track(event: string, venue: string, where: string) {
  if ((navigator as any).webdriver) return;
  const body = JSON.stringify({
    e: event,
    v: venue,
    w: where || document.body.dataset.pageType || '',
    p: location.pathname,
    c: document.body.dataset.city || '',
    d: matchMedia('(max-width: 719px)').matches ? 'mobile' : 'desktop',
    s: src,
  });
  try {
    if (!navigator.sendBeacon?.('/api/event', new Blob([body], { type: 'application/json' }))) {
      fetch('/api/event', { method: 'POST', body, keepalive: true, headers: { 'Content-Type': 'application/json' } }).catch(() => {});
    }
  } catch {
    /* never block the click */
  }
}

document.addEventListener('click', (e) => {
  const a = (e.target as HTMLElement).closest<HTMLElement>('[data-ppn-event]');
  if (a) track(a.dataset.ppnEvent!, a.dataset.venue || '', a.dataset.where || '');
});

// One page view per venue page, for the owner report.
if (document.body.dataset.pageType === 'venue_page') {
  const slug = location.pathname.split('/')[2];
  if (slug) track('view', slug, 'venue_page');
}

/* ----------------------------------------------------- city page filters */
const filters = document.querySelector<HTMLElement>('[data-filters]');
if (filters) {
  filters.hidden = false;
  const active = new Set<string>();
  const count = filters.querySelector<HTMLElement>('[data-filter-count]')!;
  const today = (el: HTMLElement) => {
    const hours = el.querySelector<HTMLElement>('[data-hours]');
    const tz = hours ? JSON.parse(hours.dataset.hours!).tz : 'America/Chicago';
    return String(venueWeekday(tz, now));
  };
  const blocks = [...document.querySelectorAll<HTMLElement>('.venue-block[data-venue]')];
  const apply = () => {
    let shown = 0;
    for (const b of blocks) {
      const tags = (b.dataset.tags || '').split(' ');
      const ok = [...active].every((f) => {
        if (f === 'open') return b.querySelector<HTMLElement>('[data-hours]')?.dataset.state === 'open';
        if (f === 'tonight') return (b.dataset.leagueDays || '').split(' ').includes(today(b));
        return tags.includes(f);
      });
      b.hidden = !ok;
      document.querySelectorAll<HTMLElement>(`[data-summary] tr[data-venue="${b.dataset.venue}"], .jump li[data-venue="${b.dataset.venue}"]`).forEach((el) => (el.hidden = !ok));
      if (ok) shown++;
    }
    count.textContent = active.size ? `${shown} of ${blocks.length} places match.` : '';
  };
  filters.querySelectorAll<HTMLButtonElement>('[data-filter]').forEach((btn) =>
    btn.addEventListener('click', () => {
      const f = btn.dataset.filter!;
      active.has(f) ? active.delete(f) : active.add(f);
      btn.setAttribute('aria-pressed', String(active.has(f)));
      apply();
    }),
  );
}

/* ------------------------------------------------- city page: Tonight bars */
const timeline = document.querySelector<HTMLElement>('[data-timeline]');
if (timeline) {
  const START = 600; // 10 AM
  const SPAN = 1200; // to 6 AM
  const rows = [...timeline.querySelectorAll<HTMLElement>('[data-tl-row]')];
  const list = rows[0].parentElement!;
  const axis = timeline.querySelector<HTMLElement>('[data-tl-axis]')!;
  const sum = timeline.querySelector<HTMLElement>('[data-tl-sum]')!;
  const clockEl = timeline.querySelector<HTMLElement>('[data-tl-clock]')!;
  const pct = (x: number) => `${(Math.min(Math.max(x, 0), 1) * 100).toFixed(2)}%`;
  const ticks: [number, string][] = [[720, 'Noon'], [900, '3 PM'], [1080, '6 PM'], [1260, '9 PM'], [1440, 'Midnight'], [1620, '3 AM'], [1800, '6 AM']];
  axis.innerHTML = ticks.map(([t, l]) => `<span style="left:${pct((t - START) / SPAN)}">${l}</span>`).join('') + '<b class="tl-now-label" data-tl-nowlabel>Now</b>';
  const nowLabel = axis.querySelector<HTMLElement>('[data-tl-nowlabel]')!;

  const draw = () => {
    const at = new Date();
    const facts = rows.map((row) => {
      const h = JSON.parse(row.dataset.tlHours!);
      let m = weekMinute(h.tz, at);
      let night = Math.floor(m / 1440);
      if (m % 1440 < 360) night = (night + 6) % 7;
      const w0 = night * 1440 + START;
      if (m < w0) m += WEEK;
      const segs: [number, number][] = [];
      for (const [s, e] of h.r as number[][]) {
        for (const shift of [0, WEEK, -WEEK]) {
          const a = Math.max(s + shift, w0), b = Math.min(e + shift, w0 + SPAN);
          if (b > a) segs.push([a - w0, b - w0]);
        }
      }
      // A range ending where the next starts (hours split at midnight) is one stretch.
      segs.sort((x, y) => x[0] - y[0]);
      for (let i = segs.length - 1; i > 0; i--) {
        if (segs[i][0] <= segs[i - 1][1]) { segs[i - 1][1] = Math.max(segs[i - 1][1], segs[i][1]); segs.splice(i, 1); }
      }
      const now = m - w0;
      const cur = segs.find(([a, b]) => now >= a && now < b);
      const next = segs.filter(([a]) => a > now).sort((x, y) => x[0] - y[0])[0];
      return { row, h, now, segs, cur, next, night, league: (row.dataset.league || '').split(' ').includes(String(night)) };
    });

    for (const f of facts) {
      const track = f.row.querySelector<HTMLElement>('[data-tl-track]')!;
      const when = f.row.querySelector<HTMLElement>('[data-tl-when]')!;
      track.replaceChildren();
      for (const [t] of ticks) track.insertAdjacentHTML('beforeend', `<s class="tl-grid-line" style="left:${pct((t - START) / SPAN)}"></s>`);
      for (const [a, b] of f.segs) {
        const bar = document.createElement('i');
        bar.className = 'tl-bar' + (f.cur && f.cur[0] === a ? ' is-open' : b <= f.now ? ' is-past' : '');
        bar.style.left = pct(a / SPAN);
        bar.style.width = pct((b - a) / SPAN);
        if (f.cur && f.cur[0] === a && f.now > a) bar.style.setProperty('--done', pct((f.now - a) / (b - a)));
        track.append(bar);
      }
      const tick = document.createElement('b');
      tick.className = 'tl-tick';
      tick.style.left = pct(f.now / SPAN);
      track.append(tick);
      if (f.league) track.insertAdjacentHTML('beforeend', '<em class="tl-league" title="League night tonight">League</em>');
      const st = openStatus(f.h, at);
      when.textContent = f.cur ? st.label.replace('Open until', 'Until') : f.next ? st.label : 'Closed tonight';
      f.row.classList.toggle('is-open', !!f.cur);
      f.row.classList.toggle('is-done', !f.cur && !f.next);
    }

    // Open now first (latest close first), then opening later tonight (soonest first), then done for the night.
    const rank = (f: (typeof facts)[number]) => (f.cur ? [0, -f.cur[1]] : f.next ? [1, f.next[0]] : [2, 0]);
    facts.sort((x, y) => rank(x)[0] - rank(y)[0] || rank(x)[1] - rank(y)[1]);
    list.append(...facts.map((f) => f.row));

    const open = facts.filter((f) => f.cur);
    const nowPos = facts[0].now / SPAN;
    nowLabel.style.left = pct(nowPos);
    nowLabel.hidden = nowPos < 0 || nowPos > 1;
    const room = matchMedia('(max-width: 719px)').matches ? 0.12 : 0.06;
    axis.querySelectorAll<HTMLElement>('span').forEach((el, i) => {
      el.style.visibility = !nowLabel.hidden && Math.abs((ticks[i][0] - START) / SPAN - nowPos) < room ? 'hidden' : '';
    });
    const latest = open[0];
    sum.innerHTML = open.length
      ? `<b>${open.length} of ${facts.length}</b> open now${latest ? ` · <span>latest: ${latest.row.querySelector('.tl-name')!.childNodes[0].textContent} ${latest.row.querySelector('[data-tl-when]')!.textContent!.replace('Until', 'until')}</span>` : ''}`
      : facts.some((f) => f.next) ? `<b>Nothing open right now.</b> First to open: ${facts[0].row.querySelector('.tl-name')!.childNodes[0].textContent}, ${facts[0].row.querySelector('[data-tl-when]')!.textContent!.toLowerCase()}.` : '<b>Everything is closed for the night.</b>';
    clockEl.textContent = new Intl.DateTimeFormat('en-US', { timeZone: facts[0].h.tz, weekday: 'long', hour: 'numeric', minute: '2-digit' }).format(at).replace(' ', ' · ') + ' local time';
  };
  timeline.hidden = false;
  draw();
  setInterval(draw, 60000);
}

/* ---------------------------------------------------------- table sorting */
document.querySelectorAll<HTMLTableElement>('[data-summary]').forEach((table) => {
  const btn = table.querySelector<HTMLButtonElement>('[data-sort]');
  const label = table.querySelector<HTMLElement>('[data-sort-label]');
  if (!btn || !label) return;
  btn.hidden = false;
  label.hidden = true;
  const th = btn.closest('th')!;
  btn.addEventListener('click', () => {
    const desc = th.getAttribute('aria-sort') !== 'descending';
    th.setAttribute('aria-sort', desc ? 'descending' : 'ascending');
    const body = table.tBodies[0];
    [...body.rows]
      .sort((a, b) => (desc ? 1 : -1) * (Number(b.dataset.tables) - Number(a.dataset.tables)) || Number(a.dataset.order) - Number(b.dataset.order))
      .forEach((r) => body.append(r));
  });
});

/* -------------------------------------------------------- map on request */
const STYLE = import.meta.env.PUBLIC_MAP_STYLE || 'https://tiles.openfreemap.org/styles/liberty';
// Split into its own chunk by the bundler: nothing map-related downloads until "Show map".
// Its web worker is copied to /vendor/ by scripts/copy-vendor.mjs (MapLibre 6 loads it as a separate file).
const loadMaplibre = () =>
  Promise.all([import('maplibre-gl'), import('maplibre-gl/dist/maplibre-gl.css')]).then(([m]) => {
    const gl = (m as any).default ?? m;
    gl.setWorkerUrl?.('/vendor/maplibre-gl-worker.mjs?v=' + gl.getVersion?.());
    return gl;
  });

document.querySelectorAll<HTMLButtonElement>('[data-show-map]').forEach((btn) => {
  btn.addEventListener('click', async () => {
    const box = btn.nextElementSibling as HTMLElement;
    if (!box.hidden) {
      box.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
      btn.textContent = 'Show map';
      return;
    }
    box.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    btn.textContent = 'Hide map';
    if (box.dataset.ready) return;
    box.dataset.ready = '1';
    const points: { n: string; u: string; lat: number; lng: number }[] = JSON.parse(box.dataset.points || '[]');
    try {
      const gl = await loadMaplibre();
      const map = new gl.Map({ container: box, style: STYLE, center: [points[0].lng, points[0].lat], zoom: 13, attributionControl: { compact: true } });
      map.addControl(new gl.NavigationControl({ showCompass: false }));
      const bounds = new gl.LngLatBounds();
      for (const p of points) {
        const link = document.createElement('a');
        link.href = p.u;
        link.textContent = p.n;
        new gl.Marker({ color: '#0e3b2d' }).setLngLat([p.lng, p.lat]).setPopup(new gl.Popup({ offset: 24 }).setDOMContent(link)).addTo(map);
        bounds.extend([p.lng, p.lat]);
      }
      if (points.length > 1) map.fitBounds(bounds, { padding: 48, maxZoom: 14, duration: 0 });
    } catch (err) {
      console.error(err);
      box.textContent = 'The map could not load. Use Directions on any place instead.';
    }
  });
});

/* ----------------------------------------------------------- Play tonight */
interface ClientVenue { i: string; n: string; u: string; k: string; c: string; a: number | null; o: number | null; h: { tz: string; r: number[][]; s: string }; t: number | null; z: string[]; p: string; d: string; l: number[]; y: string; f: string[] }

export async function loadVenues(): Promise<ClientVenue[]> {
  const res = await fetch('/data/venues.json');
  return res.json();
}

export function rank(list: ClientVenue[], opts: { lat?: number; lng?: number; city?: string; radius?: number }) {
  const order = { open: 0, unknown: 1, closed: 2 } as Record<string, number>;
  return list
    .map((v) => ({
      v,
      st: openStatus(v.h, now),
      dist: opts.lat !== undefined && v.a !== null ? miles(opts.lat, opts.lng!, v.a, v.o!) : null,
      leagueToday: v.l.includes(venueWeekday(v.h.tz, now)),
    }))
    .filter((x) => (opts.city ? x.v.k === opts.city : opts.lat === undefined || (x.dist !== null && x.dist <= (opts.radius ?? 30))))
    .sort((a, b) => order[a.st.state] - order[b.st.state] || (a.dist !== null && b.dist !== null ? a.dist - b.dist : (b.v.t ?? -1) - (a.v.t ?? -1)));
}

/** Open at some point past midnight tonight, in the venue's own time zone. */
export function lateTonight(v: ClientVenue, at: Date = now) {
  if (v.h.s === 'temporarily-closed' || v.h.s === 'permanently-closed' || !v.h.r.length) return false;
  const minute = weekMinute(v.h.tz, at);
  const day = Math.floor(minute / 1440);
  // "Tonight" before 5 AM still means the night that started yesterday.
  const night = minute % 1440 < 300 ? (day + 6) % 7 : day;
  const midnight = ((night + 1) % 7) * 1440;
  return v.h.r.some(([s, e]) => {
    if (e - s >= WEEK) return true;
    for (const shift of [0, WEEK, -WEEK]) if (s + shift < midnight + 30 && e + shift > midnight + 30) return true;
    return false;
  });
}

/* ------------------------------------------------- Ask it (homepage hero) */
const ask = document.querySelector<HTMLFormElement>('[data-ask]');
if (ask) {
  const slots = [...ask.querySelectorAll<HTMLSelectElement>('[data-slot]')];
  const [table, kind, where, when] = slots;
  const nearOpt = where.querySelector<HTMLOptionElement>('[data-near]')!;
  const count = ask.querySelector<HTMLElement>('[data-ask-count]')!;
  const picks = ask.querySelector<HTMLOListElement>('[data-ask-picks]')!;
  const more = ask.querySelector<HTMLButtonElement>('[data-ask-more]')!;
  const fallback = ask.querySelector<HTMLAnchorElement>('[data-ask-fallback]')!;
  let here: { lat: number; lng: number } | null = null;
  let shown = 3;

  // Selects sized to their chosen words, so the sentence reads like a sentence.
  const ruler = document.createElement('span');
  ruler.className = 'ask-ruler';
  ruler.setAttribute('aria-hidden', 'true');
  ask.querySelector('.ask-line')!.append(ruler);
  const fit = () => slots.forEach((s) => {
    ruler.textContent = s.selectedOptions[0]?.textContent || '';
    s.style.width = `${Math.ceil(ruler.getBoundingClientRect().width) + 40}px`;
  });
  fit();
  document.fonts?.ready.then(fit);
  addEventListener('resize', fit);

  if ('geolocation' in navigator) nearOpt.hidden = false;

  loadVenues().then((list) => {
    const card = ({ v, st, dist, leagueToday }: ReturnType<typeof rank>[number]) => {
      const li = document.createElement('li');
      li.innerHTML = `<a class="name"></a><span class="status"></span><p class="meta"></p><p class="actions"></p>`;
      const name = li.querySelector('a')!;
      name.href = v.u;
      name.textContent = v.n;
      const chip = li.querySelector<HTMLElement>('.status')!;
      chip.textContent = st.label;
      if (st.state !== 'unknown') chip.classList.add(`status--${st.state}`);
      li.querySelector('.meta')!.textContent = [v.c, dist !== null ? `${dist.toFixed(1)} mi` : '', v.t ? `${v.t} tables` : '', v.z.includes('9ft') ? '9-foot' : '', leagueToday ? 'League tonight' : ''].filter(Boolean).join(' · ');
      const actions = li.querySelector('.actions')!;
      const tel = v.p.replace(/\D/g, '');
      if (tel.length >= 10) actions.insertAdjacentHTML('beforeend', `<a class="btn" href="tel:+1${tel.slice(-10)}" data-ppn-event="call" data-venue="${v.i}" data-where="home_ask">Call</a>`);
      if (v.d) actions.insertAdjacentHTML('beforeend', `<a class="btn btn--ghost" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(v.d)}" data-ppn-event="directions" data-venue="${v.i}" data-where="home_ask">Directions</a>`);
      return li;
    };

    const answer = () => {
      const w = where.value;
      let rows = w === 'near' && here
        ? rank(list, { lat: here.lat, lng: here.lng, radius: 30 })
        : w && w !== 'near'
          ? rank(list, { city: w })
          : rank(list, {});
      rows = rows.filter(({ v, st, leagueToday }) => {
        if (table.value === 'nine' && !v.z.includes('9ft')) return false;
        if (table.value === 'many' && (v.t ?? 0) < 6) return false;
        if (kind.value === 'hall' && v.y !== 'pool-halls') return false;
        if (kind.value === 'bar' && v.y === 'pool-halls') return false;
        if (when.value === 'open' && st.state !== 'open') return false;
        if (when.value === 'late' && !lateTonight(v)) return false;
        if (when.value === 'league' && !leagueToday) return false;
        if ((when.value === 'free' || when.value === 'food') && !v.f.includes(when.value)) return false;
        return true;
      });

      const area = w === 'near' ? 'within 30 miles of you' : w ? where.selectedOptions[0].textContent!.trim() : 'across the country';
      if (!rows.length) {
        count.innerHTML = `<b>No match</b> ${area} yet. Loosen one of the blanks above, or <a href="/places/">browse every city</a>.`;
        picks.replaceChildren();
        more.hidden = true;
        return;
      }
      count.innerHTML = `<b>${rows.length} ${rows.length === 1 ? 'place' : 'places'}</b> ${area}.${rows.length > 1 ? ' Best first.' : ''}`;
      picks.replaceChildren(...rows.slice(0, shown).map(card));
      more.hidden = rows.length <= shown;
      more.textContent = `Show all ${rows.length}`;
    };

    const locate = () => {
      count.textContent = 'Finding you…';
      navigator.geolocation.getCurrentPosition(
        (pos) => { here = { lat: pos.coords.latitude, lng: pos.coords.longitude }; answer(); },
        () => { where.value = ''; fit(); count.textContent = "We couldn't get your location, so here's everywhere. Pick a city instead."; setTimeout(answer, 1800); },
        { timeout: 10000, maximumAge: 600000 },
      );
    };

    slots.forEach((s) => s.addEventListener('change', () => {
      fit();
      shown = 3;
      if (s === where && where.value === 'near' && !here) return locate();
      answer();
    }));
    more.addEventListener('click', () => { shown = 60; answer(); });
    fallback.hidden = true;
    answer();
  }).catch(() => {
    count.textContent = 'Live answers could not load.';
  });
}
