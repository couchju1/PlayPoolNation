/**
 * The "How many tables?" card on venue pages (src/components/TablesAsk.astro).
 * Posts to /api/tables. Remembers in this browser that you answered, so the card
 * shows a thank-you instead of asking again (you can still send another).
 */
import { savedUser } from './sb.ts';

const box = document.querySelector<HTMLElement>('[data-tables-ask]');
if (box) {
  const venue = box.dataset.venue!;
  const form = box.querySelector<HTMLFormElement>('[data-ask-form]')!;
  const done = box.querySelector<HTMLElement>('[data-ask-done]')!;
  const msg = box.querySelector<HTMLElement>('[data-ask-msg]')!;
  const count = box.querySelector<HTMLInputElement>('[data-ask-count]')!;
  const unsure = box.querySelector<HTMLInputElement>('[data-ask-unsure]')!;
  const KEY = `ppn_tables_reported:${venue}`;
  const opened = Date.now();
  let answered = false;
  try { answered = !!localStorage.getItem(KEY); } catch { /* storage blocked */ }
  const show = (thanks: boolean) => { form.hidden = thanks; done.hidden = !thanks; };
  show(answered);
  box.hidden = false;

  box.querySelectorAll<HTMLButtonElement>('[data-step]').forEach((b) => b.addEventListener('click', () => {
    unsure.checked = false;
    count.disabled = false;
    count.value = String(Math.min(200, Math.max(1, (parseInt(count.value, 10) || 0) + Number(b.dataset.step))));
  }));
  unsure.addEventListener('change', () => { count.disabled = unsure.checked; });
  box.querySelector('[data-ask-again]')!.addEventListener('click', () => { show(false); msg.textContent = ''; });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const n = unsure.checked ? null : parseInt(String(fd.get('count') || ''), 10);
    if (n !== null && !(n >= 1 && n <= 200)) { msg.textContent = 'Pick a number from 1 to 200, or tick "Not sure".'; return; }
    const brandsPicked = fd.getAll('brands').map(String);
    const other = String(fd.get('other') || '').trim();
    if (other) brandsPicked.push(other);
    const body = {
      venue,
      count: n,
      sizes: fd.getAll('sizes').map(String),
      brands: brandsPicked,
      when: String(fd.get('when') || 'recently'),
      note: String(fd.get('note') || ''),
      user: savedUser()?.id || '',
      website_url: String(fd.get('website_url') || ''),
      elapsed_ms: Date.now() - opened,
    };
    if (body.count === null && !body.sizes.length && !body.brands.length) { msg.textContent = 'Tell us at least one thing: a number, a size or a brand.'; return; }
    const btn = form.querySelector<HTMLButtonElement>('button[type=submit]')!;
    btn.disabled = true;
    msg.textContent = 'Sending…';
    try {
      const r = await fetch('/api/tables', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      const j = await r.json().catch(() => ({}));
      if (!r.ok || !j.ok) throw new Error(j.error || 'not saved');
      try { localStorage.setItem(KEY, new Date().toISOString()); } catch { /* storage blocked */ }
      show(true);
      (window as any).plausible?.('Tables answer', { props: { venue } });
    } catch (err) {
      msg.textContent = `Not sent: ${(err as Error).message}. Try again in a minute.`;
    } finally {
      btn.disabled = false;
    }
  });
}
