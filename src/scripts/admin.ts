/** /admin/: the photo approval queue. The database lets only admins see pending photos or move them. */
import { savedUser, sb } from './sb.ts';

const list = document.querySelector<HTMLOListElement>('[data-queue]')!;
const msg = document.querySelector<HTMLElement>('[data-admin-msg]')!;
const me = savedUser();

(async () => {
  if (!me) { location.replace('/account/?next=/admin/'); return; }
  const c = await sb();
  const { data: admin } = await c.rpc('is_admin');
  if (!admin) { msg.textContent = 'This page is for PlayPoolNation admins.'; return; }
  const venues: { i: string; n: string; u: string; c: string }[] = await (await fetch('/data/venues.json')).json();
  const byId = new Map(venues.map((v) => [v.i, v]));

  const load = async () => {
    const { data: rows, error } = await c.from('posts').select('id, venue_slug, name, body, kind, created_at, photo_path').eq('photo_status', 'pending').order('created_at');
    if (error) { msg.textContent = 'The queue could not load.'; return; }
    msg.textContent = rows.length ? `${rows.length} ${rows.length === 1 ? 'photo' : 'photos'} waiting.` : 'Nothing waiting. New photos show up here, and you get an email for each one.';
    const urls = rows.length ? (await c.storage.from('wall-pending').createSignedUrls(rows.map((r: any) => r.photo_path), 3600)).data || [] : [];
    list.replaceChildren(...rows.map((r: any, i: number) => {
      const v = byId.get(r.venue_slug);
      const li = document.createElement('li');
      li.className = 'queue-item';
      li.innerHTML = '<a class="queue-photo" target="_blank" rel="noopener"><img alt="" /></a><div><p class="queue-where"><b></b> at <a></a> · <span></span></p><p class="queue-body"></p><p class="actions"><button type="button" class="btn">Approve</button><button type="button" class="btn btn--ghost acct-danger">Reject</button></p></div>';
      const url = urls[i]?.signedUrl || '';
      li.querySelector<HTMLAnchorElement>('.queue-photo')!.href = url;
      li.querySelector('img')!.src = url;
      li.querySelector('.queue-where b')!.textContent = r.name;
      const a = li.querySelector<HTMLAnchorElement>('.queue-where a')!;
      a.href = (v?.u || `/place/${r.venue_slug}/`) + '#wall-title';
      a.textContent = v?.n || r.venue_slug;
      li.querySelector('.queue-where span')!.textContent = new Date(r.created_at).toLocaleString();
      li.querySelector('.queue-body')!.textContent = r.body || '(no message)';
      const [ok, no] = li.querySelectorAll('button');
      ok.addEventListener('click', async () => {
        ok.disabled = no.disabled = true;
        try {
          const { data: blob, error: e1 } = await c.storage.from('wall-pending').download(r.photo_path);
          if (e1) throw e1;
          const up = await c.storage.from('wall-photos').upload(r.photo_path, blob, { contentType: 'image/jpeg', upsert: true });
          if (up.error) throw up.error;
          const done = await c.from('posts').update({ photo_status: 'approved' }).eq('id', r.id);
          if (done.error) throw done.error;
          await c.storage.from('wall-pending').remove([r.photo_path]);
          li.remove();
        } catch {
          ok.disabled = no.disabled = false;
          msg.textContent = 'That one could not be approved. Try again.';
        }
      });
      no.addEventListener('click', async () => {
        ok.disabled = no.disabled = true;
        await c.storage.from('wall-pending').remove([r.photo_path]);
        await c.from('posts').update({ photo_status: 'rejected', photo_path: null }).eq('id', r.id);
        li.remove();
      });
      return li;
    }));
  };
  load();
})().catch(() => { msg.textContent = 'The queue could not load right now.'; });
