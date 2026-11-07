/**
 * Everything the browser adds to the static pages. Pages are complete without it.
 *  - open/closed chips from each venue's hours, in the venue's own time zone
 *  - first-party click counts for Call, Directions, Website, Claim, Suggest an edit
 *  - city page filters and table sort
 *  - the map, loaded only when "Show map" is pressed
 *  - the homepage "Play tonight" panel
 */
import { miles, openStatus, venueWeekday } from '../lib/open-status.js';

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
    .filter((x) => (opts.city ? x.v.k === opts.city : x.dist !== null && x.dist <= (opts.radius ?? 30)))
    .sort((a, b) => order[a.st.state] - order[b.st.state] || (a.dist !== null && b.dist !== null ? a.dist - b.dist : (b.v.t ?? -1) - (a.v.t ?? -1)));
}

const tonight = document.querySelector<HTMLElement>('[data-tonight]');
if (tonight) {
  const near = tonight.querySelector<HTMLButtonElement>('[data-tonight-near]')!;
  const city = tonight.querySelector<HTMLSelectElement>('[data-tonight-city]')!;
  const picks = tonight.querySelector<HTMLOListElement>('[data-tonight-picks]')!;
  const count = tonight.querySelector<HTMLElement>('[data-tonight-count]')!;
  const fallback = tonight.querySelector<HTMLElement>('[data-tonight-fallback]')!;
  loadVenues().then((list) => {
    const open = list.filter((v) => openStatus(v.h, now).state === 'open').length;
    count.textContent = `${open} of ${list.length} places are open right now.`;
    near.hidden = !('geolocation' in navigator);
    city.hidden = false;
    fallback.hidden = true;
    const show = (ranked: ReturnType<typeof rank>, empty: string) => {
      picks.replaceChildren();
      if (!ranked.length) {
        picks.innerHTML = `<li>${empty} <a href="/places/">Browse every city</a>.</li>`;
        return;
      }
      for (const { v, st, dist, leagueToday } of ranked.slice(0, 3)) {
        const li = document.createElement('li');
        const meta = [v.c, dist !== null ? `${dist.toFixed(1)} mi` : '', v.t ? `${v.t} tables` : '', leagueToday ? 'League night tonight' : ''].filter(Boolean).join(' · ');
        li.innerHTML = `<a class="name"></a> <span class="status"></span><p class="meta"></p><p class="actions"></p>`;
        const name = li.querySelector('a')!;
        name.href = v.u;
        name.textContent = v.n;
        const chip = li.querySelector<HTMLElement>('.status')!;
        chip.textContent = st.label;
        if (st.state !== 'unknown') chip.classList.add(`status--${st.state}`);
        li.querySelector('.meta')!.textContent = meta;
        const actions = li.querySelector('.actions')!;
        const tel = v.p.replace(/\D/g, '');
        if (tel.length >= 10) actions.insertAdjacentHTML('beforeend', `<a class="btn" href="tel:+1${tel.slice(-10)}" data-ppn-event="call" data-venue="${v.i}" data-where="home_tonight">Call</a>`);
        if (v.d) actions.insertAdjacentHTML('beforeend', `<a class="btn btn--ghost" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(v.d)}" data-ppn-event="directions" data-venue="${v.i}" data-where="home_tonight">Directions</a>`);
        picks.append(li);
      }
    };
    city.addEventListener('change', () => city.value && show(rank(list, { city: city.value }), 'No places in that city yet.'));
    near.addEventListener('click', () => {
      near.disabled = true;
      near.textContent = 'Finding you…';
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          near.disabled = false;
          near.textContent = 'Find an open table near me';
          show(rank(list, { lat: pos.coords.latitude, lng: pos.coords.longitude, radius: 30 }), "We don't list a place within 30 miles of you yet.");
        },
        () => {
          near.disabled = false;
          near.textContent = 'Find an open table near me';
          count.textContent = "We couldn't get your location. Pick a city instead.";
        },
        { timeout: 10000, maximumAge: 600000 },
      );
    });
  }).catch(() => {});
}
