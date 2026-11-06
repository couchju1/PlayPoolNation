/** Search over every venue in the browser. Fine at this size; move to Typesense or Meilisearch past a few thousand venues. */
import { loadVenues, rank } from './site.ts';
import { openStatus } from '../lib/open-status.js';

const form = document.querySelector<HTMLFormElement>('[data-search]')!;
const input = form.querySelector<HTMLInputElement>('input[name=q]')!;
const out = document.querySelector<HTMLOListElement>('[data-search-results]')!;
const count = document.querySelector<HTMLElement>('[data-search-count]')!;
const active = new Set<string>();
let here: { lat: number; lng: number } | null = null;
const params = new URLSearchParams(location.search);
input.value = params.get('q') || params.get('near') || '';

const norm = (s: string) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();

loadVenues().then((list) => {
  const now = new Date();
  const render = () => {
    const q = norm(input.value);
    let rows = here ? rank(list, { lat: here.lat, lng: here.lng, radius: 100 }) : list.map((v) => ({ v, st: openStatus(v.h, now), dist: null as number | null, leagueToday: false }));
    rows = rows.filter(({ v, st }) => {
      if (q && !norm(`${v.n} ${v.c}`).includes(q) && !q.split(' ').every((w) => norm(`${v.n} ${v.c}`).includes(w))) return false;
      for (const f of active) {
        if (f === 'open' && st.state !== 'open') return false;
        if (f === 'hall' && v.y !== 'pool-halls') return false;
        if (!['open', 'hall'].includes(f) && !v.f.includes(f)) return false;
      }
      return true;
    });
    count.textContent = `${rows.length} ${rows.length === 1 ? 'place' : 'places'}${here ? ' within 100 miles, nearest first' : ''}.`;
    out.replaceChildren(
      ...rows.slice(0, 60).map(({ v, st, dist }) => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.className = 'name';
        a.href = v.u;
        a.textContent = v.n;
        const meta = document.createElement('p');
        meta.className = 'muted';
        meta.style.margin = '4px 0 0';
        meta.textContent = [v.c, dist !== null ? `${dist.toFixed(1)} mi` : '', v.t ? `${v.t} tables` : '', v.z.includes('9ft') ? '9-foot tables' : ''].filter(Boolean).join(' · ');
        const chip = document.createElement('span');
        chip.className = 'status' + (st.state !== 'unknown' ? ` status--${st.state}` : '');
        chip.textContent = st.label;
        li.append(a, ' ', chip, meta);
        return li;
      }),
    );
  };
  input.addEventListener('input', render);
  form.addEventListener('submit', (e) => { e.preventDefault(); render(); });
  document.querySelectorAll<HTMLButtonElement>('[data-f]').forEach((b) => b.addEventListener('click', () => {
    const f = b.dataset.f!;
    active.has(f) ? active.delete(f) : active.add(f);
    b.setAttribute('aria-pressed', String(active.has(f)));
    render();
  }));
  const near = form.querySelector<HTMLButtonElement>('[data-search-near]')!;
  near.addEventListener('click', () => navigator.geolocation?.getCurrentPosition((p) => { here = { lat: p.coords.latitude, lng: p.coords.longitude }; input.value = ''; render(); }, () => { count.textContent = "We couldn't get your location."; }));
  render();
});
