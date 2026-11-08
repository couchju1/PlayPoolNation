/**
 * Venue photos: the bar's own gallery (its approved owner or an admin uploads, shown at once)
 * and approved player photos from the Wall. Uploads are re-drawn as JPEGs in the browser first
 * (smaller, and without GPS or camera data).
 */
import { SUPABASE_KEY, SUPABASE_URL, savedUser, sb } from './sb.ts';
import { photoUrl, shrink } from './wall.ts';

const box = document.querySelector<HTMLElement>('[data-photos]');
const lightbox = document.querySelector<HTMLDialogElement>('[data-lightbox]');

function open(src: string, caption: string) {
  if (!lightbox) { window.open(src, '_blank'); return; }
  lightbox.querySelector('img')!.src = src;
  lightbox.querySelector('img')!.alt = caption;
  lightbox.querySelector('p')!.textContent = caption;
  lightbox.showModal();
}
lightbox?.querySelector('[data-lightbox-close]')!.addEventListener('click', () => lightbox.close());
lightbox?.addEventListener('click', (e) => { if (e.target === lightbox) lightbox.close(); });

function tile(src: string, caption: string, onDelete?: () => void) {
  const li = document.createElement('li');
  li.innerHTML = '<button type="button" class="photo-tile"><img loading="lazy" decoding="async" alt="" /></button>';
  const img = li.querySelector('img')!;
  img.src = src;
  img.alt = caption;
  li.querySelector('button')!.addEventListener('click', () => open(src, caption));
  if (onDelete) {
    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'photo-del';
    del.textContent = 'Delete';
    del.addEventListener('click', onDelete);
    li.append(del);
  }
  return li;
}

if (box) {
  const slug = box.dataset.venue!;
  const name = box.dataset.name!;
  const bar = box.querySelector<HTMLUListElement>('[data-photos-bar]')!;
  const players = box.querySelector<HTMLUListElement>('[data-photos-players]')!;
  const msg = box.querySelector<HTMLElement>('[data-photo-msg]')!;
  const add = box.querySelector<HTMLElement>('[data-photo-add]')!;
  const input = box.querySelector<HTMLInputElement>('[data-photo-input]')!;
  let manage = false;

  const load = async () => {
    const [mine, wall] = await Promise.all([
      fetch(`${SUPABASE_URL}/rest/v1/venue_photos?select=id,path,caption&venue_slug=eq.${slug}&order=created_at.desc`, { headers: { apikey: SUPABASE_KEY } }).then((r) => (r.ok ? r.json() : [])).catch(() => []),
      fetch(`/api/feed?v=${slug}`, { cache: 'no-store' }).then((r) => r.json()).then((j) => (j.posts || []).filter((p: any) => p.photo)).catch(() => []),
    ]);
    bar.replaceChildren(...mine.map((p: any) => tile(photoUrl('venue-photos', p.path), p.caption || `${name}`, manage ? async () => {
      if (!confirm('Delete this photo?')) return;
      const c = await sb();
      await c.storage.from('venue-photos').remove([p.path]);
      await c.from('venue_photos').delete().eq('id', p.id);
      load();
    } : undefined)));
    players.replaceChildren(...wall.map((p: any) => tile(photoUrl('wall-photos', p.photo), `Photo from ${p.name}`)));
    box.querySelector<HTMLElement>('[data-photos-bar-title]')!.hidden = !mine.length || !wall.length;
    box.querySelector<HTMLElement>('[data-photos-players-title]')!.hidden = !wall.length;
    box.querySelector<HTMLElement>('[data-photos-empty]')!.hidden = !(manage && !mine.length && !wall.length);
    box.hidden = !(mine.length || wall.length || manage);
  };
  load();

  const me = savedUser();
  if (me) {
    sb().then(async (c) => {
      const { data } = await c.rpc('can_manage_venue', { p_slug: slug });
      if (!data) return;
      manage = true;
      add.hidden = false;
      load();
    }).catch(() => {});
  }
  input.addEventListener('change', async () => {
    const files = [...(input.files || [])].slice(0, 12);
    if (!files.length) return;
    const c = await sb();
    let done = 0;
    for (const f of files) {
      msg.textContent = `Uploading ${done + 1} of ${files.length}…`;
      try {
        const blob = await shrink(f);
        const path = `${slug}/${Date.now()}-${Math.random().toString(36).slice(2, 10)}.jpg`;
        const up = await c.storage.from('venue-photos').upload(path, blob, { contentType: 'image/jpeg' });
        if (up.error) throw up.error;
        const row = await c.from('venue_photos').insert({ venue_slug: slug, path, created_by: me!.id });
        if (row.error) throw row.error;
        done++;
      } catch {
        msg.textContent = `One photo couldn't be uploaded (${f.name}).`;
      }
    }
    input.value = '';
    if (done === files.length) msg.textContent = `${done} ${done === 1 ? 'photo' : 'photos'} added.`;
    load();
  });
}
