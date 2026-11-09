/**
 * Venue cover photo and profile picture (src/components/VenueCover.astro).
 * Everyone: swaps in the pictures the venue uploaded, if any (table venue_profiles).
 * The venue's owner or an admin: "Edit cover photo" and a camera button on the profile picture.
 * Uploads are re-drawn as JPEGs in the browser (no GPS or camera data): covers up to 1600 px
 * wide, profile pictures cropped to a 400 px square from the middle.
 */
import { SUPABASE_KEY, SUPABASE_URL, savedUser, sb } from './sb.ts';
import { photoUrl } from './wall.ts';

const box = document.querySelector<HTMLElement>('[data-venue-profile]');

async function draw(file: File, kind: 'cover' | 'avatar'): Promise<Blob> {
  const bmp = await createImageBitmap(file);
  const c = document.createElement('canvas');
  const g = c.getContext('2d')!;
  if (kind === 'avatar') {
    const side = Math.min(bmp.width, bmp.height);
    c.width = c.height = Math.min(400, side);
    g.drawImage(bmp, (bmp.width - side) / 2, (bmp.height - side) / 2, side, side, 0, 0, c.width, c.height);
  } else {
    const scale = Math.min(1, 1600 / bmp.width);
    c.width = Math.round(bmp.width * scale);
    c.height = Math.round(bmp.height * scale);
    g.drawImage(bmp, 0, 0, c.width, c.height);
  }
  return new Promise((ok, bad) => c.toBlob((b) => (b ? ok(b) : bad(new Error('image'))), 'image/jpeg', 0.86));
}

if (box) {
  const slug = box.dataset.venueProfile!;
  const coverImg = box.querySelector<HTMLImageElement>('[data-cover-img]')!;
  const avatarImg = box.querySelector<HTMLImageElement>('[data-avatar-img]')!;
  const ball = box.querySelector<SVGElement>('[data-avatar-ball]')!;
  const msg = box.querySelector<HTMLElement>('[data-profile-msg]')!;
  let row: { avatar_path: string | null; cover_path: string | null } = { avatar_path: null, cover_path: null };
  let saved = false; // is there a venue_profiles row yet? Insert the first time, then update only the picture columns.

  const paint = () => {
    if (row.cover_path) {
      coverImg.src = photoUrl('venue-photos', row.cover_path);
      coverImg.removeAttribute('data-default');
      coverImg.removeAttribute('width');
      coverImg.removeAttribute('height');
      coverImg.hidden = false;
    }
    if (row.avatar_path) {
      avatarImg.src = photoUrl('venue-photos', row.avatar_path);
      avatarImg.hidden = false;
      ball.style.display = 'none';
    }
  };

  fetch(`${SUPABASE_URL}/rest/v1/venue_profiles?select=avatar_path,cover_path&venue_slug=eq.${slug}`, { headers: { apikey: SUPABASE_KEY } })
    .then((r) => (r.ok ? r.json() : []))
    .then((rows) => { if (rows[0]) { row = rows[0]; saved = true; paint(); } })
    .catch(() => {});

  const me = savedUser();
  if (me) {
    sb().then(async (c) => {
      const { data } = await c.rpc('can_manage_venue', { p_slug: slug });
      if (!data) return;
      const wire = (kind: 'cover' | 'avatar') => {
        const label = box.querySelector<HTMLElement>(kind === 'cover' ? '[data-cover-edit]' : '[data-avatar-edit]')!;
        const input = label.querySelector('input')!;
        label.hidden = false;
        input.addEventListener('change', async () => {
          const file = input.files?.[0];
          input.value = '';
          if (!file) return;
          msg.textContent = kind === 'cover' ? 'Uploading cover photo…' : 'Uploading profile picture…';
          try {
            const blob = await draw(file, kind);
            const path = `${slug}/${kind}-${Date.now()}.jpg`;
            const up = await c.storage.from('venue-photos').upload(path, blob, { contentType: 'image/jpeg' });
            if (up.error) throw up.error;
            const old = kind === 'cover' ? row.cover_path : row.avatar_path;
            const next = { ...row, [kind === 'cover' ? 'cover_path' : 'avatar_path']: path };
            const fields = { avatar_path: next.avatar_path, cover_path: next.cover_path, updated_by: me.id, updated_at: new Date().toISOString() };
            if (!saved) {
              const { data: had } = await c.from('venue_profiles').select('venue_slug').eq('venue_slug', slug);
              saved = !!had?.length;
            }
            const res = saved
              ? await c.from('venue_profiles').update(fields).eq('venue_slug', slug).select('venue_slug')
              : await c.from('venue_profiles').insert({ venue_slug: slug, ...fields }).select('venue_slug');
            if (res.error || !res.data?.length) { await c.storage.from('venue-photos').remove([path]); throw res.error || new Error('not saved'); }
            saved = true;
            row = next;
            paint();
            if (old) c.storage.from('venue-photos').remove([old]);
            msg.textContent = kind === 'cover' ? 'Cover photo updated.' : 'Profile picture updated.';
          } catch {
            msg.textContent = "That picture couldn't be saved. Try again.";
          }
        });
      };
      wire('cover');
      wire('avatar');
    }).catch(() => {});
  }
}
