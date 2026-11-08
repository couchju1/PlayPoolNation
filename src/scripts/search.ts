/**
 * The search explorer: results beside a live map. Text search over name and city, one-tap
 * filters, sort (best match, nearest, most tables, top rated), near me, and "Search this area"
 * after the map moves. The URL keeps q, filters and sort, so a search can be shared. Hovering a
 * result lights its pin; clicking a pin brings its card into view.
 * Fine at this size in the browser; move to Typesense or Meilisearch past a few thousand venues.
 */
import { STYLE, loadMaplibre, loadVenues } from './site.ts';
import { miles, openStatus } from '../lib/open-status.js';
import { SUPABASE_KEY, SUPABASE_URL } from './sb.ts';

type V = Awaited<ReturnType<typeof loadVenues>>[number];
interface Row { v: V; open: boolean; label: string; state: string; dist: number | null; rating: number | null; reviews: number; score: number }

const root = document.querySelector<HTMLElement>('[data-explore]')!;
const qIn = root.querySelector<HTMLInputElement>('[data-ex-q]')!;
const list = root.querySelector<HTMLOListElement>('[data-ex-results]')!;
const count = root.querySelector<HTMLElement>('[data-ex-count]')!;
const sortSel = root.querySelector<HTMLSelectElement>('[data-ex-sort]')!;
const areaBtn = root.querySelector<HTMLButtonElement>('[data-ex-area]')!;
const toggle = root.querySelector<HTMLButtonElement>('[data-ex-toggle]')!;
const mapBox = root.querySelector<HTMLElement>('[data-ex-map]')!;

const params = new URLSearchParams(location.search);
const active = new Set((params.get('f') || '').split(',').filter(Boolean));
qIn.value = params.get('q') || params.get('near') || '';
if (params.get('sort')) sortSel.value = params.get('sort')!;
let here: { lat: number; lng: number } | null = null;
let bounds: [number, number, number, number] | null = null; // west, south, east, north
let ratings = new Map<string, { avg: number; n: number }>();
let map: any = null, gl: any = null;
const markers = new Map<string, { m: any; el: HTMLElement }>();
let shown: Row[] = [];
let fitNext = true;

const norm = (s: string) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
const STATES: Record<string, string> = { al: 'alabama', ak: 'alaska', az: 'arizona', ar: 'arkansas', ca: 'california', co: 'colorado', ct: 'connecticut', dc: 'district of columbia washington dc', de: 'delaware', fl: 'florida', ga: 'georgia', hi: 'hawaii', id: 'idaho', il: 'illinois', in: 'indiana', ia: 'iowa', ks: 'kansas', ky: 'kentucky', la: 'louisiana', me: 'maine', md: 'maryland', ma: 'massachusetts', mi: 'michigan', mn: 'minnesota', ms: 'mississippi', mo: 'missouri', mt: 'montana', ne: 'nebraska', nv: 'nevada', nh: 'new hampshire', nj: 'new jersey', nm: 'new mexico', ny: 'new york', nc: 'north carolina', nd: 'north dakota', oh: 'ohio', ok: 'oklahoma', or: 'oregon', pa: 'pennsylvania', ri: 'rhode island', sc: 'south carolina', sd: 'south dakota', tn: 'tennessee', tx: 'texas', ut: 'utah', vt: 'vermont', va: 'virginia', wa: 'washington', wv: 'west virginia', wi: 'wisconsin', wy: 'wyoming' };
const haystack = (v: V) => { const st = (v.c.split(', ').pop() || '').toLowerCase(); return norm(`${v.n} ${v.c} ${STATES[st] || ''}`); };

function syncUrl() {
  const p = new URLSearchParams();
  if (qIn.value.trim()) p.set('q', qIn.value.trim());
  if (active.size) p.set('f', [...active].join(','));
  if (sortSel.value !== 'best') p.set('sort', sortSel.value);
  history.replaceState(null, '', `/search/${p.toString() ? '?' + p : ''}`);
}

function starsHtml(r: number) { return `<span class="stars" style="--pct:${(r / 5) * 100}%" aria-hidden="true"></span>`; }

loadVenues().then((all) => {
  const now = new Date();
  const facts = (v: V) => {
    const st = openStatus(v.h, now);
    const r = ratings.get(v.i);
    return { open: st.state === 'open', label: st.label, state: st.state, rating: r ? r.avg : null, reviews: r ? r.n : 0 };
  };

  const compute = (): Row[] => {
    const q = norm(qIn.value);
    const words = q ? q.split(' ') : [];
    let rows: Row[] = all.map((v) => {
      const f = facts(v);
      const hay = haystack(v);
      // Best match: name starts with the query, then words in the name, then anywhere; open and bigger rooms nudge up.
      const nm = norm(v.n);
      const score = (q && nm.startsWith(q) ? 30 : 0) + words.filter((w) => nm.includes(w)).length * 10 + (f.open ? 4 : 0) + Math.min(v.t ?? 0, 20) / 5 + (f.rating ?? 0);
      return { v, ...f, dist: here && v.a !== null ? miles(here.lat, here.lng, v.a, v.o!) : null, score, _hay: hay } as Row & { _hay: string };
    }).filter((r: any) => !words.length || words.every((w) => r._hay.includes(w)));
    rows = rows.filter(({ v, open }) => [...active].every((f) =>
      f === 'open' ? open : f === 'hall' ? v.y === 'pool-halls' : v.f.includes(f)));
    if (bounds) rows = rows.filter(({ v }) => v.a !== null && v.o! >= bounds![0] && v.o! <= bounds![2] && v.a >= bounds![1] && v.a <= bounds![3]);
    if (here && !qIn.value.trim()) rows = rows.filter((r) => r.dist !== null && r.dist <= 100);
    const by: Record<string, (a: Row, b: Row) => number> = {
      best: (a, b) => (here && a.dist !== null && b.dist !== null && !qIn.value.trim() ? a.dist - b.dist : b.score - a.score),
      near: (a, b) => (a.dist ?? 1e9) - (b.dist ?? 1e9) || b.score - a.score,
      tables: (a, b) => (b.v.t ?? -1) - (a.v.t ?? -1),
      rating: (a, b) => (b.rating ?? 0) - (a.rating ?? 0) || b.reviews - a.reviews || b.score - a.score,
    };
    return rows.sort(by[sortSel.value] || by.best);
  };

  const card = (r: Row) => {
    const { v } = r;
    const li = document.createElement('li');
    li.className = 'ex-card';
    li.dataset.i = v.i;
    const meta = [v.c, r.dist !== null ? `${r.dist.toFixed(1)} mi` : '', v.t ? `${v.t} tables` : '', v.z.includes('9ft') ? '9-foot' : '', v.y === 'pool-halls' ? 'Pool hall' : 'Bar'].filter(Boolean).join(' · ');
    li.innerHTML = `<a class="ex-name"></a><p class="ex-meta"></p><p class="ex-line"><span class="status"></span><span class="ex-rate"></span></p>`;
    const a = li.querySelector<HTMLAnchorElement>('.ex-name')!;
    a.href = v.u;
    a.textContent = v.n;
    li.querySelector('.ex-meta')!.textContent = meta;
    const chip = li.querySelector<HTMLElement>('.status')!;
    chip.textContent = r.label;
    if (r.state !== 'unknown') chip.classList.add(`status--${r.state}`);
    const rate = li.querySelector<HTMLElement>('.ex-rate')!;
    if (r.rating !== null) { rate.innerHTML = `${starsHtml(r.rating)}<b></b><span></span>`; rate.querySelector('b')!.textContent = r.rating.toFixed(1); rate.querySelector('span:last-child')!.textContent = `(${r.reviews})`; }
    li.addEventListener('mouseenter', () => highlight(v.i, true));
    li.addEventListener('mouseleave', () => highlight(v.i, false));
    li.addEventListener('focusin', () => highlight(v.i, true));
    li.addEventListener('focusout', () => highlight(v.i, false));
    return li;
  };

  const render = () => {
    shown = compute();
    list.replaceChildren(...shown.slice(0, 80).map(card));
    if (!shown.length) list.innerHTML = `<li class="ex-empty">No places match${bounds ? ' in this part of the map' : ''}. ${active.size ? 'Try turning off a filter' : 'Try another city or name'}${bounds ? ', or zoom out' : ''}.</li>`;
    const n = shown.length;
    const where = bounds ? ' in this area' : here && !qIn.value.trim() ? ' within 100 miles' : qIn.value.trim() ? ` for “${qIn.value.trim()}”` : '';
    const open = shown.filter((r) => r.open).length;
    count.textContent = `${n} ${n === 1 ? 'place' : 'places'}${where}${n ? ` · ${open} open now` : ''}`;
    syncUrl();
    drawPins();
  };

  /* ---- map ---- */
  function highlight(slug: string, on: boolean) {
    const mk = markers.get(slug);
    if (!mk) return;
    mk.el.classList.toggle('is-hot', on);
    mk.el.parentElement && (mk.el.style.zIndex = on ? '5' : '');
  }
  function drawPins() {
    if (!map) return;
    const ids = new Set(shown.map((r) => r.v.i));
    for (const [slug, { el }] of markers) el.hidden = !ids.has(slug);
    for (const r of shown) markers.get(r.v.i)?.el.classList.toggle('is-open', r.open);
    if (fitNext && shown.length) {
      const b = new gl.LngLatBounds();
      shown.forEach((r) => r.v.a !== null && b.extend([r.v.o, r.v.a]));
      map.fitBounds(b, { padding: 60, maxZoom: 13, duration: 600 });
      fitNext = false;
    }
  }
  async function startMap() {
    if (map) return;
    gl = await loadMaplibre();
    map = new gl.Map({ container: mapBox, style: STYLE, center: [-98.6, 39.8], zoom: 3.2, attributionControl: { compact: true } });
    map.addControl(new gl.NavigationControl({ showCompass: false }), 'top-right');
    for (const v of all) {
      if (v.a === null) continue;
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'ex-pin';
      el.setAttribute('aria-label', v.n);
      el.innerHTML = '<span></span>';
      el.addEventListener('click', () => {
        const li = list.querySelector<HTMLElement>(`[data-i="${v.i}"]`);
        list.querySelectorAll('.is-picked').forEach((x) => x.classList.remove('is-picked'));
        if (li) { li.classList.add('is-picked'); li.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
        if (root.classList.contains('show-map') && li) { root.classList.remove('show-map'); toggle.textContent = 'Map'; toggle.setAttribute('aria-pressed', 'false'); li.scrollIntoView({ block: 'center' }); }
      });
      el.addEventListener('mouseenter', () => list.querySelector(`[data-i="${v.i}"]`)?.classList.add('is-picked'));
      el.addEventListener('mouseleave', () => list.querySelector(`[data-i="${v.i}"]`)?.classList.remove('is-picked'));
      const m = new gl.Marker({ element: el }).setLngLat([v.o, v.a]).addTo(map);
      markers.set(v.i, { m, el });
    }
    // Once someone pans or zooms, offer to search what they're looking at.
    // (dragend / zoomend fire only for the person's own moves, not for our fitBounds.)
    map.on('dragend', () => (areaBtn.hidden = false));
    map.on('zoomend', (e: any) => { if (e.originalEvent) areaBtn.hidden = false; });
    map.on('load', () => { fitNext = true; drawPins(); });
    drawPins();
  }
  areaBtn.addEventListener('click', () => {
    const b = map.getBounds();
    bounds = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()];
    areaBtn.hidden = true;
    qIn.value = '';
    render();
  });

  /* ---- controls ---- */
  let t: number | undefined;
  qIn.addEventListener('input', () => { clearTimeout(t); t = window.setTimeout(() => { bounds = null; fitNext = true; render(); }, 150); });
  root.querySelector('[data-ex-form]')!.addEventListener('submit', (e) => { e.preventDefault(); bounds = null; fitNext = true; render(); });
  root.querySelectorAll<HTMLButtonElement>('[data-f]').forEach((b) => {
    b.setAttribute('aria-pressed', String(active.has(b.dataset.f!)));
    b.addEventListener('click', () => {
      const f = b.dataset.f!;
      active.has(f) ? active.delete(f) : active.add(f);
      b.setAttribute('aria-pressed', String(active.has(f)));
      fitNext = !bounds;
      render();
    });
  });
  sortSel.addEventListener('change', () => {
    if (sortSel.value === 'near' && !here) { locate(); return; }
    render();
  });
  const nearBtn = root.querySelector<HTMLButtonElement>('[data-ex-near]')!;
  function locate() {
    if (!navigator.geolocation) { count.textContent = 'Your browser can’t share its location. Search by city instead.'; return; }
    nearBtn.disabled = true;
    nearBtn.textContent = 'Finding you…';
    navigator.geolocation.getCurrentPosition((p) => {
      here = { lat: p.coords.latitude, lng: p.coords.longitude };
      nearBtn.disabled = false;
      nearBtn.textContent = 'Near me';
      nearBtn.setAttribute('aria-pressed', 'true');
      qIn.value = '';
      bounds = null;
      sortSel.value = 'near';
      fitNext = true;
      render();
    }, () => {
      nearBtn.disabled = false;
      nearBtn.textContent = 'Near me';
      count.textContent = 'We couldn’t get your location. Search by city instead.';
    }, { timeout: 10000, maximumAge: 600000 });
  }
  nearBtn.addEventListener('click', locate);
  toggle.addEventListener('click', () => {
    const on = !root.classList.contains('show-map');
    root.classList.toggle('show-map', on);
    toggle.textContent = on ? 'List' : 'Map';
    toggle.setAttribute('aria-pressed', String(on));
    // The map was hidden, so size it and frame the current results once it's on screen.
    if (on) startMap().then(() => setTimeout(() => { map.resize(); fitNext = true; drawPins(); }, 50));
  });

  render();
  // Ratings arrive a moment later and re-sort; the map loads when the browser is idle (desktop only).
  fetch(`${SUPABASE_URL}/rest/v1/reviews?select=venue_slug,rating&limit=10000`, { headers: { apikey: SUPABASE_KEY } })
    .then((r) => (r.ok ? r.json() : []))
    .then((rows: { venue_slug: string; rating: number }[]) => {
      const agg = new Map<string, { sum: number; n: number }>();
      rows.forEach((x) => { const a = agg.get(x.venue_slug) || { sum: 0, n: 0 }; a.sum += x.rating; a.n++; agg.set(x.venue_slug, a); });
      ratings = new Map([...agg].map(([k, a]) => [k, { avg: a.sum / a.n, n: a.n }]));
      if (ratings.size) render();
    }).catch(() => {});
  if (matchMedia('(min-width: 900px)').matches) ('requestIdleCallback' in window ? (window as any).requestIdleCallback : setTimeout)(() => startMap());
  if (params.get('near') === 'me') locate();
});
