/**
 * Reviews and accounts on public pages: the header's account link, the ratings card and
 * reviews on venue pages, and star ratings on city pages. Reading uses plain REST calls with
 * the publishable key; the Supabase library loads only for signed-in actions.
 */
import { SUPABASE_KEY, SUPABASE_URL, savedUser, sb } from './sb.ts';

interface Review { id: number; venue_slug: string; user_id: string; rating: number; body: string; created_at: string; updated_at: string; share_email?: boolean }

async function rest(path: string): Promise<any[]> {
  const res = await fetch(`${SUPABASE_URL}/rest/v1/${path}`, { headers: { apikey: SUPABASE_KEY } });
  if (!res.ok) throw new Error(String(res.status));
  return res.json();
}

export function starsText(r: number) { return '★★★★★'.slice(0, Math.round(r)) + '☆☆☆☆☆'.slice(0, 5 - Math.round(r)); }
function paintStars(el: HTMLElement, r: number) {
  el.style.setProperty('--pct', `${(r / 5) * 100}%`);
  el.setAttribute('data-r', r.toFixed(1));
}
const date = (iso: string) => new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

/* Header: "Sign in", or the signed-in name opening a menu with Sign out */
const me = savedUser();
const menu = document.querySelector<HTMLElement>('[data-account-menu]');
if (menu && me) {
  const btn = menu.querySelector<HTMLButtonElement>('[data-account-btn]')!;
  const pop = menu.querySelector<HTMLElement>('[data-account-pop]')!;
  menu.querySelector<HTMLElement>('[data-account-link]')!.hidden = true;
  menu.querySelector('[data-account-name]')!.textContent = me.name;
  btn.hidden = false;
  const setOpen = (open: boolean) => { pop.hidden = !open; btn.setAttribute('aria-expanded', String(open)); };
  let checked = false;
  btn.addEventListener('click', () => {
    setOpen(pop.hidden);
    if (!pop.hidden) {
      pop.querySelector<HTMLElement>('a, button')?.focus();
      // Show Bar dashboard and Admin only to the people who have them (asked once).
      if (!checked) {
        checked = true;
        sb().then((c) => Promise.all([c.rpc('is_admin'), c.rpc('my_venues')])).then(([a, o]: any[]) => {
          pop.querySelector<HTMLElement>('[data-account-admin]')!.hidden = !a.data;
          pop.querySelector<HTMLElement>('[data-account-dash]')!.hidden = !(a.data || o.data?.length);
        }).catch(() => {});
      }
    }
  });
  document.addEventListener('click', (e) => { if (!menu.contains(e.target as Node)) setOpen(false); });
  menu.addEventListener('keydown', (e) => { if (e.key === 'Escape') { setOpen(false); btn.focus(); } });
  pop.querySelector('[data-account-signout]')!.addEventListener('click', async () => {
    try { await (await sb()).auth.signOut(); } catch { /* signed out locally below either way */ }
    try { Object.keys(localStorage).filter((k) => /^sb-.*-auth-token/.test(k)).forEach((k) => localStorage.removeItem(k)); } catch { /* private mode */ }
    location.assign('/');
  });
}

/* Venue page */
const box = document.querySelector<HTMLElement>('[data-reviews]');
const card = document.querySelector<HTMLElement>('[data-ratings]');
if (box) {
  const venue = box.dataset.venue!;
  const list = box.querySelector<HTMLOListElement>('[data-review-list]')!;
  const form = box.querySelector<HTMLFormElement>('[data-review-form]')!;
  const gate = box.querySelector<HTMLElement>('[data-review-gate]')!;
  const msg = form.querySelector<HTMLElement>('[data-review-msg]')!;
  const del = form.querySelector<HTMLButtonElement>('[data-review-delete]')!;
  let mine: Review | null = null;

  const summary = (rs: Review[]) => {
    if (!card) return;
    const n = rs.length;
    const avg = n ? rs.reduce((a, r) => a + r.rating, 0) / n : 0;
    card.querySelector('[data-ratings-avg]')!.textContent = n ? avg.toFixed(1) : '–';
    paintStars(card.querySelector<HTMLElement>('[data-ratings-stars]')!, avg);
    card.querySelector('[data-ratings-count]')!.textContent = n ? `${n} ${n === 1 ? 'review' : 'reviews'}` : 'No reviews yet. Be the first.';
    for (let s = 1; s <= 5; s++) {
      const c = rs.filter((r) => r.rating === s).length;
      card.querySelector<HTMLElement>(`[data-bar="${s}"]`)!.style.width = n ? `${(c / n) * 100}%` : '0';
      card.querySelector(`[data-bar-n="${s}"]`)!.textContent = String(c);
    }
    card.classList.toggle('has-reviews', n > 0);
  };

  const render = (rs: Review[], names: Map<string, string>) => {
    summary(rs);
    list.replaceChildren(...rs.map((r) => {
      const li = document.createElement('li');
      li.className = 'review' + (me && r.user_id === me.id ? ' is-mine' : '');
      li.innerHTML = '<p class="review-top"><span class="stars" aria-hidden="true"></span><b class="review-name"></b><span class="review-date"></span></p><p class="review-body"></p>';
      paintStars(li.querySelector<HTMLElement>('.stars')!, r.rating);
      li.querySelector('.review-top')!.setAttribute('aria-label', `${r.rating} out of 5`);
      li.querySelector('.review-name')!.textContent = names.get(r.user_id) || 'A player';
      li.querySelector('.review-date')!.textContent = date(r.created_at) + (r.updated_at && Date.parse(r.updated_at) - Date.parse(r.created_at) > 60000 ? ' · edited' : '');
      const body = li.querySelector('.review-body')!;
      if (r.body) body.textContent = r.body; else body.remove();
      return li;
    }));
    if (!rs.length) list.innerHTML = '<li class="feed-empty">No reviews yet.</li>';
  };

  const load = async () => {
    const rs: Review[] = await rest(`reviews?select=id,venue_slug,user_id,rating,body,created_at,updated_at&venue_slug=eq.${venue}&order=created_at.desc&limit=200`);
    const ids = [...new Set(rs.map((r) => r.user_id))];
    const names = new Map<string, string>();
    if (ids.length) (await rest(`profiles?select=id,display_name&id=in.(${ids.join(',')})`)).forEach((p: any) => names.set(p.id, p.display_name));
    render(rs, names);
    return rs;
  };
  load().catch(() => { list.innerHTML = '<li class="feed-empty">Reviews could not load right now.</li>'; });

  if (me) {
    gate.hidden = true;
    form.hidden = false;
    form.querySelector('[data-review-name]')!.textContent = me.name;
    form.querySelector('[data-review-email]')!.textContent = me.email;
    sb().then(async (c) => {
      const { data } = await c.from('reviews').select('*').eq('venue_slug', venue).eq('user_id', me.id).maybeSingle();
      if (!data) return;
      mine = data;
      (form.querySelector(`input[name=rating][value="${data.rating}"]`) as HTMLInputElement).checked = true;
      (form.elements.namedItem('body') as HTMLTextAreaElement).value = data.body;
      (form.elements.namedItem('share') as HTMLInputElement).checked = data.share_email;
      form.querySelector('[data-review-submit]')!.textContent = 'Update review';
      del.hidden = false;
    }).catch(() => {});
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const rating = Number(fd.get('rating'));
      if (!rating) { msg.textContent = 'Pick a star rating.'; return; }
      const row = { rating, body: String(fd.get('body') || '').trim().slice(0, 2000), share_email: fd.get('share') === 'on' };
      msg.textContent = 'Saving…';
      try {
        const c = await sb();
        const q = mine
          ? c.from('reviews').update({ ...row, updated_at: new Date().toISOString() }).eq('id', mine.id).select().single()
          : c.from('reviews').insert({ ...row, venue_slug: venue, user_id: me.id }).select().single();
        const { data, error } = await q;
        if (error) throw error;
        mine = data;
        del.hidden = false;
        form.querySelector('[data-review-submit]')!.textContent = 'Update review';
        msg.textContent = row.share_email ? `Saved. ${box.dataset.name} can see your email.` : 'Saved. Thanks for the review.';
        await load();
      } catch (err: any) {
        msg.textContent = /JWT|session|auth/i.test(err?.message || '') ? 'Your sign-in expired. Sign in again to post.' : 'Not saved. Try again.';
      }
    });
    del.addEventListener('click', async () => {
      if (!mine || !confirm('Delete your review?')) return;
      const c = await sb();
      const { error } = await c.from('reviews').delete().eq('id', mine.id);
      if (error) { msg.textContent = 'Not deleted. Try again.'; return; }
      mine = null;
      form.reset();
      del.hidden = true;
      form.querySelector('[data-review-submit]')!.textContent = 'Post review';
      msg.textContent = 'Deleted.';
      await load();
    });
  }
}

/* City pages: average rating next to each venue */
const spots = [...document.querySelectorAll<HTMLElement>('[data-rating-for]')];
if (spots.length) {
  const slugs = spots.map((s) => s.dataset.ratingFor!);
  rest(`reviews?select=venue_slug,rating&venue_slug=in.(${slugs.join(',')})&limit=5000`).then((rs: { venue_slug: string; rating: number }[]) => {
    for (const s of spots) {
      const mineR = rs.filter((r) => r.venue_slug === s.dataset.ratingFor);
      if (!mineR.length) continue;
      const avg = mineR.reduce((a, r) => a + r.rating, 0) / mineR.length;
      s.innerHTML = '<span class="stars" aria-hidden="true"></span><b></b><span></span>';
      paintStars(s.querySelector<HTMLElement>('.stars')!, avg);
      s.querySelector('b')!.textContent = avg.toFixed(1);
      s.querySelector('span:last-child')!.textContent = `(${mineR.length})`;
      s.setAttribute('aria-label', `Rated ${avg.toFixed(1)} out of 5 by ${mineR.length} ${mineR.length === 1 ? 'player' : 'players'}`);
      s.hidden = false;
    }
  }).catch(() => {});
}
