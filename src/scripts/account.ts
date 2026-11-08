/** /account/: sign in, create an account, reset a password, and manage your reviews. */
import { sb } from './sb.ts';
import { starsText } from './reviews.ts';

const root = document.querySelector<HTMLElement>('[data-account]')!;
const msg = root.querySelector<HTMLElement>('[data-account-msg]')!;
const q = new URLSearchParams(location.search);
// Only send people back to a page on this site.
const nextRaw = q.get('next') || '';
const next = nextRaw.startsWith('/') && !nextRaw.startsWith('//') ? nextRaw : '';
const back = (fallback = '/account/') => location.assign(next || fallback);

const show = (view: string) => {
  root.querySelectorAll<HTMLElement>('[data-view]').forEach((el) => (el.hidden = el.dataset.view !== view));
  msg.textContent = '';
};
root.querySelectorAll<HTMLAnchorElement>('[data-go]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); show(a.dataset.go!); }));
const form = (name: string) => root.querySelector<HTMLFormElement>(`[data-form="${name}"]`)!;
const val = (f: HTMLFormElement, n: string) => String(new FormData(f).get(n) || '').trim();
const busy = (f: HTMLFormElement, on: boolean) => { const b = f.querySelector<HTMLButtonElement>('button[type=submit]'); if (b) b.disabled = on; };
const friendly = (m: string) =>
  /invalid login/i.test(m) ? 'That email and password don\'t match.' :
  /not confirmed/i.test(m) ? 'Confirm your email first: open the link we sent you.' :
  /already registered|already exists/i.test(m) ? 'There\'s already an account with that email. Sign in instead.' :
  /rate limit|too many/i.test(m) ? 'Too many tries. Wait a few minutes and try again.' :
  /password/i.test(m) ? m : 'Something went wrong. Try again.';

async function venueNames() {
  const list: { i: string; n: string; u: string }[] = await (await fetch('/data/venues.json')).json();
  return new Map(list.map((v) => [v.i, v]));
}

async function showMe(c: any, user: any) {
  show('me');
  const { data: prof } = await c.from('profiles').select('display_name').eq('id', user.id).maybeSingle();
  const name = prof?.display_name || user.user_metadata?.display_name || user.email.split('@')[0];
  root.querySelector('[data-me-name]')!.textContent = name;
  root.querySelector('[data-me-email]')!.textContent = user.email;
  (form('name').elements.namedItem('name') as HTMLInputElement).value = name;
  const [{ data: reviews }, names] = await Promise.all([c.from('reviews').select('*').eq('user_id', user.id).order('created_at', { ascending: false }), venueNames()]);
  const ol = root.querySelector<HTMLOListElement>('[data-my-reviews]')!;
  ol.replaceChildren(...(reviews || []).map((r: any) => {
    const v = names.get(r.venue_slug);
    const li = document.createElement('li');
    li.innerHTML = '<a class="my-venue"></a><span class="my-stars"></span><label class="my-share"><input type="checkbox" /> Share email</label>';
    const a = li.querySelector('a')!;
    a.href = (v?.u || `/place/${r.venue_slug}/`) + '#reviews';
    a.textContent = v?.n || r.venue_slug;
    li.querySelector('.my-stars')!.textContent = starsText(r.rating);
    const box = li.querySelector('input')!;
    box.checked = r.share_email;
    box.addEventListener('change', async () => {
      const { error } = await c.from('reviews').update({ share_email: box.checked }).eq('id', r.id);
      msg.textContent = error ? 'Not saved. Try again.' : box.checked ? `${a.textContent} can now see your email.` : `${a.textContent} can no longer see your email.`;
      if (error) box.checked = !box.checked;
    });
    return li;
  }));
  if (!reviews?.length) ol.innerHTML = '<li class="muted">No reviews yet. Open any venue page and rate it.</li>';
}

sb().then(async (c) => {
  // A password-reset link lands here signed in for recovery.
  c.auth.onAuthStateChange((event: string) => { if (event === 'PASSWORD_RECOVERY') show('reset'); });
  const { data: { session } } = await c.auth.getSession();
  if (session && q.get('mode') === 'reset') show('reset');
  else if (session && next) back();
  else if (session) await showMe(c, session.user);
  else show(['join', 'forgot'].includes(q.get('mode') || '') ? q.get('mode')! : 'signin');

  form('signin').addEventListener('submit', async (e) => {
    e.preventDefault(); const f = e.currentTarget as HTMLFormElement; busy(f, true);
    const { error } = await c.auth.signInWithPassword({ email: val(f, 'email'), password: val(f, 'password') });
    busy(f, false);
    if (error) { msg.textContent = friendly(error.message); return; }
    back();
  });
  form('join').addEventListener('submit', async (e) => {
    e.preventDefault(); const f = e.currentTarget as HTMLFormElement; busy(f, true);
    const redirect = `${location.origin}/account/${next ? `?next=${encodeURIComponent(next)}` : ''}`;
    const { data, error } = await c.auth.signUp({ email: val(f, 'email'), password: val(f, 'password'), options: { data: { display_name: val(f, 'name').slice(0, 40) }, emailRedirectTo: redirect } });
    busy(f, false);
    if (error) { msg.textContent = friendly(error.message); return; }
    if (data.session) { back(); return; }
    root.querySelector('[data-sent-text]')!.textContent = `We sent a confirmation link to ${val(f, 'email')}. Open it to finish creating your account.`;
    show('sent');
  });
  form('forgot').addEventListener('submit', async (e) => {
    e.preventDefault(); const f = e.currentTarget as HTMLFormElement; busy(f, true);
    await c.auth.resetPasswordForEmail(val(f, 'email'), { redirectTo: `${location.origin}/account/?mode=reset` });
    busy(f, false);
    root.querySelector('[data-sent-text]')!.textContent = `If there's an account for ${val(f, 'email')}, a reset link is on its way.`;
    show('sent');
  });
  form('reset').addEventListener('submit', async (e) => {
    e.preventDefault(); const f = e.currentTarget as HTMLFormElement; busy(f, true);
    const { error } = await c.auth.updateUser({ password: val(f, 'password') });
    busy(f, false);
    if (error) { msg.textContent = friendly(error.message); return; }
    const { data: { user } } = await c.auth.getUser();
    await showMe(c, user);
    msg.textContent = 'Password saved.';
  });
  form('name').addEventListener('submit', async (e) => {
    e.preventDefault(); const f = e.currentTarget as HTMLFormElement;
    const name = val(f, 'name').slice(0, 40);
    if (!name) return;
    const { data: { user } } = await c.auth.getUser();
    const [{ error }] = await Promise.all([c.from('profiles').update({ display_name: name }).eq('id', user.id), c.auth.updateUser({ data: { display_name: name } })]);
    msg.textContent = error ? 'Not saved. Try again.' : 'Name saved.';
    if (!error) root.querySelector('[data-me-name]')!.textContent = name;
  });
  root.querySelector('[data-signout]')!.addEventListener('click', async () => { await c.auth.signOut(); location.assign('/account/'); });
  root.querySelector('[data-delete-account]')!.addEventListener('click', async () => {
    if (!confirm('Delete your account and all your reviews? This can\'t be undone.')) return;
    const { error } = await c.rpc('delete_my_account');
    if (error) { msg.textContent = 'Not deleted. Email info@playpoolnation.com and we\'ll do it.'; return; }
    await c.auth.signOut();
    location.assign('/');
  });
}).catch(() => { msg.textContent = 'Accounts could not load right now. Try again in a minute.'; });
