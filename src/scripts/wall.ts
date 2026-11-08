/**
 * The Wall (venue pages) and Live feeds (city pages, homepage, /live/): players' check-ins
 * and updates from /api/feed, posting through /api/post, cheers and reports through /api/react.
 * No accounts: the name is kept in this browser only.
 */

export interface Post { id: number; venue_slug: string; city_key: string | null; kind: string; name: string; body: string; cheers: number; created_at: string; photo?: string | null }

import { stamp } from './passport.ts';
import { SUPABASE_URL } from './sb.ts';

export const photoUrl = (bucket: string, path: string) => `${SUPABASE_URL}/storage/v1/object/public/${bucket}/${path}`;

/** A picked image, re-drawn as a JPEG at most 1600 px wide: smaller, and without the GPS and camera data phones add. */
export async function shrink(file: File): Promise<Blob> {
  const bmp = await createImageBitmap(file);
  const scale = Math.min(1, 1600 / Math.max(bmp.width, bmp.height));
  const c = document.createElement('canvas');
  c.width = Math.round(bmp.width * scale);
  c.height = Math.round(bmp.height * scale);
  c.getContext('2d')!.drawImage(bmp, 0, 0, c.width, c.height);
  return new Promise((ok, bad) => c.toBlob((b) => (b ? ok(b) : bad(new Error('image'))), 'image/jpeg', 0.85));
}

const KIND: Record<string, string> = { checkin: 'Checked in', tables_open: 'Tables open', wait: 'Waiting for a table', league: 'League night', tournament: 'Tournament', note: 'Note' };
const store = {
  get(k: string) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k: string, v: string) { try { localStorage.setItem(k, v); } catch { /* private mode */ } },
};
const cheered = new Set<string>((store.get('ppn_cheered') || '').split(',').filter(Boolean));

/** A numbered pool ball for a name: 1-8 solids, 9-15 stripes, always the same for the same name. */
const COLORS = ['#f4c20d', '#1f4fbf', '#d12a2a', '#5b2a86', '#f07d1a', '#1d7a3e', '#7a1f1f', '#111111'];
export function paintBall(el: HTMLElement, name: string) {
  let h = 0;
  for (const ch of name.trim().toLowerCase()) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  const n = name.trim() ? (h % 15) + 1 : 8;
  const color = COLORS[(n - 1) % 8];
  el.textContent = String(n);
  el.style.setProperty('--ball', color);
  el.classList.toggle('ball--stripe', n > 8);
}

function ago(iso: string) {
  const s = Math.max(0, (Date.now() - Date.parse(iso)) / 1000);
  if (s < 60) return 'just now';
  if (s < 3600) return `${Math.floor(s / 60)} min ago`;
  if (s < 86400) return `${Math.floor(s / 3600)} hr ago`;
  const d = Math.floor(s / 86400);
  return d === 1 ? 'yesterday' : `${d} days ago`;
}

function send(url: string, body: object) {
  return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
}

let names: Map<string, { n: string; u: string; c: string }> | null = null;
async function venueNames() {
  if (!names) names = new Map(((await (await fetch('/data/venues.json')).json()) as { i: string; n: string; u: string; c: string }[]).map((v) => [v.i, { n: v.n, u: v.u, c: v.c }]));
  return names;
}

export function card(p: Post, showVenue: { n: string; u: string; c: string } | null) {
  const li = document.createElement('li');
  li.className = 'post';
  li.innerHTML = `<span class="ball" aria-hidden="true"></span><div class="post-main"><p class="post-top"><b class="post-name"></b><span class="post-where"></span> <span class="post-time"></span></p><p class="post-tag"></p><p class="post-body"></p><p class="post-foot"><button type="button" class="post-cheer"></button><button type="button" class="post-report">Report</button></p></div>`;
  paintBall(li.querySelector('.ball')!, p.name);
  li.querySelector('.post-name')!.textContent = p.name;
  const where = li.querySelector('.post-where')!;
  if (showVenue) {
    where.append(' at ');
    const a = document.createElement('a');
    a.href = showVenue.u + '#wall-title';
    a.textContent = showVenue.n;
    where.append(a, ` · ${showVenue.c}`);
  }
  const time = li.querySelector('.post-time')!;
  time.textContent = ago(p.created_at);
  time.setAttribute('title', new Date(p.created_at).toLocaleString());
  li.querySelector('.post-tag')!.textContent = KIND[p.kind] || 'Note';
  li.querySelector('.post-tag')!.className = `post-tag post-tag--${p.kind}`;
  const body = li.querySelector('.post-body')!;
  body.textContent = p.body;
  if (!p.body) body.remove();
  if (p.photo) {
    const a = document.createElement('a');
    a.className = 'post-photo';
    a.href = photoUrl('wall-photos', p.photo);
    a.target = '_blank';
    a.rel = 'noopener';
    a.innerHTML = '<img alt="" loading="lazy" decoding="async" />';
    a.querySelector('img')!.src = a.href;
    a.querySelector('img')!.alt = `Photo from ${p.name}`;
    li.querySelector('.post-tag')!.after(a);
  }
  const cheer = li.querySelector<HTMLButtonElement>('.post-cheer')!;
  let count = p.cheers;
  const draw = () => {
    const mine = cheered.has(String(p.id));
    cheer.textContent = `Cheers${count ? ` · ${count}` : ''}`;
    cheer.setAttribute('aria-pressed', String(mine));
    cheer.disabled = mine;
  };
  draw();
  cheer.addEventListener('click', () => {
    if (cheered.has(String(p.id))) return;
    cheered.add(String(p.id));
    store.set('ppn_cheered', [...cheered].slice(-300).join(','));
    count++;
    draw();
    send('/api/react', { id: p.id, a: 'cheer' }).catch(() => {});
  });
  li.querySelector('.post-report')!.addEventListener('click', (e) => {
    if (!confirm('Report this post? Three reports hide it.')) return;
    send('/api/react', { id: p.id, a: 'report' }).catch(() => {});
    (e.currentTarget as HTMLButtonElement).textContent = 'Reported';
    (e.currentTarget as HTMLButtonElement).disabled = true;
  });
  return li;
}

async function feed(query: string): Promise<Post[]> {
  const res = await fetch('/api/feed' + query, { cache: 'no-store' });
  if (!res.ok) throw new Error(String(res.status));
  return (await res.json()).posts || [];
}

/* Venue page: the Wall */
const wall = document.querySelector<HTMLElement>('[data-wall]');
if (wall) {
  const venue = wall.dataset.venue!;
  const list = wall.querySelector<HTMLOListElement>('[data-feed]')!;
  const form = wall.querySelector<HTMLFormElement>('[data-wall-form]')!;
  const nameIn = form.querySelector<HTMLInputElement>('[name=n]')!;
  const bodyIn = form.querySelector<HTMLTextAreaElement>('[name=b]')!;
  const left = form.querySelector<HTMLElement>('[data-wall-left]')!;
  const msg = form.querySelector<HTMLElement>('[data-wall-msg]')!;
  const ball = form.querySelector<HTMLElement>('[data-ball]')!;
  const today = document.querySelector<HTMLElement>('[data-wall-today]');
  const opened = Date.now();
  let posts: Post[] = [];

  const photoIn = form.querySelector<HTMLInputElement>('[data-wall-photo]')!;
  const preview = form.querySelector<HTMLElement>('[data-wall-preview]')!;
  let photo: Blob | null = null;
  photoIn.addEventListener('change', async () => {
    const f = photoIn.files?.[0];
    if (!f) return;
    try {
      photo = await shrink(f);
      const img = preview.querySelector('img')!;
      if (img.src) URL.revokeObjectURL(img.src);
      img.src = URL.createObjectURL(photo);
      preview.hidden = false;
    } catch {
      photo = null;
      msg.textContent = "That photo couldn't be opened. Try a JPEG or PNG.";
    }
    photoIn.value = '';
  });
  form.querySelector('[data-wall-photo-clear]')!.addEventListener('click', () => { photo = null; preview.hidden = true; });

  nameIn.value = store.get('ppn_name') || '';
  paintBall(ball, nameIn.value);
  nameIn.addEventListener('input', () => paintBall(ball, nameIn.value));
  bodyIn.addEventListener('input', () => (left.textContent = String(280 - bodyIn.value.length)));

  const render = () => {
    list.replaceChildren(...posts.map((p) => card(p, null)));
    if (!posts.length) list.innerHTML = `<li class="feed-empty">No posts yet. Be the first to check in at ${wall.dataset.name!.replace(/[<>&]/g, '')}.</li>`;
    if (today) {
      // Different people who posted in the last 24 hours (a note alone doesn't count as being there).
      const n = new Set(posts.filter((p) => Date.now() - Date.parse(p.created_at) < 86400000 && p.kind !== 'note').map((p) => p.name.trim().toLowerCase())).size;
      today.hidden = n === 0;
      today.textContent = `${n} ${n === 1 ? 'player' : 'players'} checked in today`;
    }
  };
  feed(`?v=${venue}`).then((p) => { posts = p; render(); wall.hidden = false; }).catch(() => { wall.hidden = false; render(); list.innerHTML = '<li class="feed-empty">The Wall could not load right now.</li>'; });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const btn = form.querySelector<HTMLButtonElement>('button[type=submit]')!;
    btn.disabled = true;
    msg.textContent = '';
    try {
      const fields = { v: venue, n: String(fd.get('n') || ''), k: String(fd.get('k') || ''), b: String(fd.get('b') || ''), hp: String(fd.get('hp') || ''), elapsed_ms: String(Date.now() - opened) };
      let res: Response;
      if (photo) {
        const mp = new FormData();
        Object.entries(fields).forEach(([k, v]) => mp.append(k, v));
        mp.append('photo', photo, 'photo.jpg');
        res = await fetch('/api/post', { method: 'POST', body: mp });
      } else {
        res = await send('/api/post', fields);
      }
      const out = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(out.error || 'Not posted. Try again.');
      store.set('ppn_name', String(fd.get('n')));
      if (out.post) posts = [out.post, ...posts];
      // Being there counts toward the Pool Passport (a note alone doesn't).
      if (out.post && fd.get('k') !== 'note') stamp(venue);
      bodyIn.value = '';
      left.textContent = '280';
      render();
      msg.textContent = out.post?.photo_pending ? 'Posted. Your photo will show once it’s approved.' : 'Posted.';
      photo = null;
      preview.hidden = true;
    } catch (err) {
      msg.textContent = (err as Error).message;
    } finally {
      btn.disabled = false;
    }
  });
}

/* City pages, homepage and /live/: read-only feeds */
document.querySelectorAll<HTMLElement>('[data-feedbox]').forEach(async (box) => {
  const list = box.querySelector<HTMLOListElement>('[data-feed]')!;
  const city = box.dataset.city || '';
  const only = box.dataset.slugs ? new Set(box.dataset.slugs.split(' ')) : null;
  try {
    const [posts, map] = await Promise.all([feed(city ? `?c=${encodeURIComponent(city)}` : ''), venueNames()]);
    const shown = posts.filter((p) => !only || only.has(p.venue_slug)).slice(0, Number(box.dataset.limit) || 6);
    if (!shown.length) {
      if (box.dataset.empty !== undefined) { list.innerHTML = '<li class="feed-empty">Nothing posted yet. Open any venue page and be the first to check in.</li>'; box.hidden = false; }
      return;
    }
    list.replaceChildren(...shown.map((p) => card(p, map.get(p.venue_slug) || null)));
    box.hidden = false;
  } catch {
    /* stay hidden */
  }
});
