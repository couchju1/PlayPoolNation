/** Submission forms: post as JSON to /api/submit and say plainly what happened. */
const params = new URLSearchParams(location.search);
document.querySelectorAll<HTMLFormElement>('[data-submit]').forEach((form) => {
  const slug = params.get('v') || '';
  const field = form.querySelector<HTMLInputElement>('[data-venue-field]')!;
  const label = form.querySelector<HTMLElement>('[data-venue-name]')!;
  if (/^[a-z0-9-]{3,120}$/.test(slug)) {
    field.value = slug;
    label.hidden = false;
    label.textContent = `About: ${slug.replace(/-/g, ' ')}`;
  }
  const started = Date.now();
  const msg = form.querySelector<HTMLElement>('[data-form-msg]')!;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    (data as any).elapsed_ms = Date.now() - started;
    const button = form.querySelector<HTMLButtonElement>('button[type=submit]')!;
    button.disabled = true;
    msg.hidden = false;
    msg.textContent = 'Sending…';
    try {
      const res = await fetch('/api/submit', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(body.error || 'failed');
      form.reset();
      msg.textContent = "Thanks. A person will review it, usually within a few days.";
    } catch (err) {
      msg.textContent = `That didn't go through (${(err as Error).message}). Please email info@playpoolnation.com instead.`;
    } finally {
      button.disabled = false;
    }
  });
});
