/** /admin/: the photo approval queue and bar owners (claim requests, make owner, remove). The database lets only admins do any of it. */
import { savedUser, sb } from './sb.ts';
import { summarize } from '../lib/player-reports.js';

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

  /* Bar owners: claim requests, make owner, current owners. */
  const box = document.querySelector<HTMLElement>('[data-owners]')!;
  const form = box.querySelector<HTMLFormElement>('[data-owner-form]')!;
  const omsg = box.querySelector<HTMLElement>('[data-owner-msg]')!;
  const label = (v: { n: string; c: string }) => `${v.n} · ${v.c}`;
  box.querySelector('[data-admin-venues]')!.replaceChildren(...venues.map((v) => { const o = document.createElement('option'); o.value = label(v); return o; }));
  const venueIn = form.elements.namedItem('venue') as HTMLInputElement;
  const emailIn = form.elements.namedItem('email') as HTMLInputElement;
  const owners = async () => {
    const { data } = await c.rpc('admin_owners');
    const ol = box.querySelector<HTMLOListElement>('[data-owner-list]')!;
    ol.replaceChildren(...(data || []).map((o: any) => {
      const li = document.createElement('li');
      li.innerHTML = '<div><b></b> <span class="muted"></span><br /><a></a></div><button type="button" class="btn btn--ghost acct-danger">Remove</button>';
      const v = byId.get(o.venue_slug);
      li.querySelector('b')!.textContent = o.display_name || o.email;
      li.querySelector('span')!.textContent = o.email;
      const a = li.querySelector('a')!;
      a.href = `/dashboard/?v=${o.venue_slug}`;
      a.textContent = v ? label(v) : o.venue_slug;
      li.querySelector('button')!.addEventListener('click', async () => {
        if (!confirm(`Remove ${o.email} as owner of ${a.textContent}?`)) return;
        await c.rpc('admin_remove_owner', { p_email: o.email, p_slug: o.venue_slug });
        owners();
      });
      return li;
    }));
    if (!data?.length) ol.innerHTML = '<li class="muted">No owners yet.</li>';
  };
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const v = venues.find((x) => label(x) === venueIn.value.trim()) || venues.find((x) => x.i === venueIn.value.trim());
    if (!v) { omsg.textContent = 'Pick the venue from the list.'; return; }
    const { data, error } = await c.rpc('admin_set_owner', { p_email: emailIn.value.trim(), p_slug: v.i });
    omsg.textContent = error ? 'Not saved. Try again.' : data === 'no-account' ? `There's no account for ${emailIn.value.trim()} yet. Ask them to create one at playpoolnation.com/account/, then try again.` : `${emailIn.value.trim()} now owns ${v.n}.`;
    if (data === 'ok') { form.reset(); owners(); }
  });
  const { data: claims } = await c.from('submissions').select('id, venue_slug, payload, email, status, created_at').eq('kind', 'claim').order('created_at', { ascending: false }).limit(50);
  const cl = box.querySelector<HTMLOListElement>('[data-claims]')!;
  cl.replaceChildren(...(claims || []).map((r: any) => {
    const v = r.venue_slug ? byId.get(r.venue_slug) : null;
    const li = document.createElement('li');
    li.innerHTML = '<div><b></b> <span class="muted"></span><br /><span class="claim-venue"></span><p class="claim-details"></p></div><button type="button" class="btn btn--ghost">Fill in form</button>';
    li.querySelector('b')!.textContent = r.payload?.person || r.email || 'Someone';
    li.querySelector('.muted')!.textContent = [r.email, r.payload?.role, new Date(r.created_at).toLocaleDateString()].filter(Boolean).join(' · ');
    li.querySelector('.claim-venue')!.textContent = v ? label(v) : r.payload?.name || r.venue_slug || '';
    li.querySelector('.claim-details')!.textContent = r.payload?.details || r.payload?.contact || '';
    li.querySelector('button')!.addEventListener('click', () => {
      emailIn.value = r.email || '';
      if (v) venueIn.value = label(v);
      form.scrollIntoView({ block: 'center' });
      emailIn.focus();
    });
    return li;
  }));
  if (!claims?.length) cl.innerHTML = '<li class="muted">No claim requests yet. They come from the "Own a venue? Claim it" form.</li>';
  owners();
  box.hidden = false;

  /* Players' answers to "How many tables?", grouped per venue. */
  const rep = document.querySelector<HTMLElement>('[data-reports]')!;
  const { data: answers } = await c.from('submissions').select('venue_slug, payload, created_at').eq('kind', 'edit').eq('payload->>type', 'tables').order('created_at', { ascending: false }).limit(1000);
  const groups = new Map<string, any[]>();
  for (const r of answers || []) groups.set(r.venue_slug, [...(groups.get(r.venue_slug) || []), { ...r.payload, created_at: r.created_at }]);
  const SZ: Record<string, string> = { '7ft': '7-ft', '8ft': '8-ft', '9ft': '9-ft', snooker: 'Snooker', carom: 'Carom' };
  const WHEN: Record<string, string> = { tonight: 'that day', recently: 'this month', while: 'a while ago' };
  rep.querySelector('[data-report-list]')!.replaceChildren(...[...groups].map(([slug, list]) => {
    const s = summarize(list);
    const v = byId.get(slug);
    const li = document.createElement('li');
    li.innerHTML = '<div class="rep-head"><a></a><span class="rep-agree"></span><span class="muted"></span></div><p class="rep-sum"></p><ul class="rep-list"></ul>';
    const a = li.querySelector('a')!;
    a.href = (v?.u || `/place/${slug}/`) + '#tables';
    a.textContent = v ? label(v) : slug;
    li.querySelector('.rep-agree')!.textContent = s.count ? `${s.count.value} tables · ${s.count.agree} of ${s.count.of} agree` : 'No count yet';
    li.querySelector('.muted')!.textContent = `${s.players} ${s.players === 1 ? 'player' : 'players'}`;
    li.querySelector('.rep-sum')!.textContent = [
      s.sizes.length ? 'Sizes: ' + s.sizes.map((z: any) => `${SZ[z.size]} (${z.players})`).join(', ') : '',
      s.brands.length ? 'Brands: ' + s.brands.map((b: any) => `${b.brand} (${b.players})`).join(', ') : '',
    ].filter(Boolean).join(' · ');
    li.querySelector('.rep-list')!.replaceChildren(...list.map((x: any) => {
      const r = document.createElement('li');
      r.textContent = `${new Date(x.created_at).toLocaleDateString()}: ${x.count ?? 'not sure'}${x.sizes?.length ? ', ' + x.sizes.map((z: string) => SZ[z]).join('/') : ''}${x.brands?.length ? ', ' + x.brands.join('/') : ''}, seen ${WHEN[x.when] || x.when}${x.user ? ', signed in' : ''}${x.note ? `. "${x.note}"` : ''}`;
      return r;
    }));
    return li;
  }));
  if (!groups.size) rep.querySelector('[data-report-list]')!.innerHTML = '<li class="muted">No answers yet. They come from the "How many tables?" card on each venue page.</li>';
  rep.hidden = false;
})().catch(() => { msg.textContent = 'The queue could not load right now.'; });
