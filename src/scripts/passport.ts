/**
 * Pool Passport: a stamp per venue a player has played, kept in this browser only
 * (localStorage 'ppn_passport' = { slug: ISO date }). Venue pages get an "I played here"
 * button; the Wall stamps a venue when someone checks in there; /passport/ shows them all.
 */
const KEY = 'ppn_passport';

export function stamps(): Record<string, string> {
  try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch { return {}; }
}
function save(s: Record<string, string>) {
  try { localStorage.setItem(KEY, JSON.stringify(s)); } catch { /* private mode */ }
}
export function stamp(slug: string) {
  const s = stamps();
  if (!s[slug]) { s[slug] = new Date().toISOString(); save(s); }
  document.dispatchEvent(new CustomEvent('ppn:stamp', { detail: slug }));
}
function unstamp(slug: string) {
  const s = stamps();
  delete s[slug];
  save(s);
  document.dispatchEvent(new CustomEvent('ppn:stamp', { detail: slug }));
}
const short = (iso: string) => new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

/* Venue page: I played here */
const box = document.querySelector<HTMLElement>('[data-played]');
if (box) {
  const slug = box.dataset.played!;
  const btn = box.querySelector<HTMLButtonElement>('[data-played-btn]')!;
  const mark = box.querySelector<HTMLElement>('[data-played-mark]')!;
  const undo = box.querySelector<HTMLButtonElement>('[data-played-undo]')!;
  const draw = (animate = false) => {
    const at = stamps()[slug];
    btn.hidden = !!at;
    mark.hidden = !at;
    undo.hidden = !at;
    if (at) {
      mark.querySelector('[data-played-date]')!.textContent = short(at);
      if (animate) { mark.classList.remove('is-new'); void mark.offsetWidth; mark.classList.add('is-new'); }
    }
  };
  btn.addEventListener('click', () => { stamp(slug); draw(true); });
  undo.addEventListener('click', () => { unstamp(slug); draw(); });
  document.addEventListener('ppn:stamp', (e) => { if ((e as CustomEvent).detail === slug) draw(true); });
  box.hidden = false;
  draw();
}

/* /passport/ */
const page = document.querySelector<HTMLElement>('[data-passport-page]');
if (page) {
  const s = stamps();
  const rows = [...document.querySelectorAll<HTMLElement>('[data-pp-venue]')];
  const mine = rows.filter((r) => s[r.dataset.ppVenue!]);
  const cities = new Set(mine.map((r) => r.dataset.city));
  const states = new Set(mine.map((r) => r.dataset.state));
  for (const r of rows) {
    const at = s[r.dataset.ppVenue!];
    r.classList.toggle('is-stamped', !!at);
    if (at) r.querySelector('[data-pp-when]')!.textContent = short(at);
  }
  const groups = [...document.querySelectorAll<HTMLElement>('[data-pp-group]')];
  for (const g of groups) {
    const total = Number(g.dataset.total);
    const got = g.querySelectorAll('.is-stamped').length;
    g.classList.toggle('is-started', got > 0);
    g.classList.toggle('is-complete', got === total);
    g.querySelector('[data-pp-count]')!.textContent = `${got} of ${total} ${total === 1 ? 'hall' : 'halls'}${got === total ? ' · complete' : ''}`;
  }
  // Cities you've started come first, most complete on top; the rest keep their order.
  const list = groups[0]?.parentElement;
  if (list) {
    const score = (g: HTMLElement) => g.querySelectorAll('.is-stamped').length / Number(g.dataset.total);
    groups.map((g, i) => ({ g, i, sc: score(g) })).sort((a, b) => (b.sc > 0 ? 1 : 0) - (a.sc > 0 ? 1 : 0) || (a.sc > 0 ? b.sc - a.sc : 0) || a.i - b.i).forEach(({ g }) => list.append(g));
  }
  page.querySelector('[data-pp-halls]')!.textContent = String(mine.length);
  page.querySelector('[data-pp-cities]')!.textContent = String(cities.size);
  page.querySelector('[data-pp-states]')!.textContent = String(states.size);
  page.querySelector('[data-pp-of]')!.textContent = `${mine.length} of ${rows.length} halls on PlayPoolNation`;
  page.querySelector<HTMLElement>('[data-pp-bar]')!.style.width = `${Math.max(mine.length ? 2 : 0, (mine.length / rows.length) * 100)}%`;
  document.querySelector<HTMLElement>('[data-pp-empty]')!.hidden = mine.length > 0;
  const share = page.querySelector<HTMLButtonElement>('[data-pp-share]')!;
  const copied = page.querySelector<HTMLElement>('[data-pp-copied]')!;
  share.hidden = mine.length === 0;
  share.addEventListener('click', async () => {
    const text = `I've played ${mine.length} pool ${mine.length === 1 ? 'hall' : 'halls'} in ${cities.size} ${cities.size === 1 ? 'city' : 'cities'} and ${states.size} ${states.size === 1 ? 'state' : 'states'}. Start your own Pool Passport:`;
    const url = `${location.origin}/passport/`;
    try {
      if (navigator.share) { await navigator.share({ title: 'My Pool Passport', text, url }); return; }
      await navigator.clipboard.writeText(`${text} ${url}`);
      copied.textContent = 'Copied. Paste it anywhere.';
    } catch { /* cancelled */ }
  });
}
