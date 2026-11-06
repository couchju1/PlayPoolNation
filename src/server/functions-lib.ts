// Shared by the Pages Functions: Supabase REST insert with the service key (never sent to browsers).
export interface Env {
  SUPABASE_URL?: string;
  SUPABASE_SERVICE_KEY?: string;
}

export async function insert(env: Env, table: string, row: Record<string, unknown>): Promise<boolean> {
  if (!env.SUPABASE_URL || !env.SUPABASE_SERVICE_KEY) return false;
  const res = await fetch(`${env.SUPABASE_URL}/rest/v1/${table}`, {
    method: 'POST',
    headers: {
      apikey: env.SUPABASE_SERVICE_KEY,
      Authorization: `Bearer ${env.SUPABASE_SERVICE_KEY}`,
      'Content-Type': 'application/json',
      Prefer: 'return=minimal',
    },
    body: JSON.stringify(row),
  });
  return res.ok;
}

export const json = (body: unknown, status = 200) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' } });

const BOTS = /bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed|facebookexternalhit|curl|wget|python|axios|httpclient/i;
export const isBot = (req: Request) => BOTS.test(req.headers.get('user-agent') || '');

export const sameOrigin = (req: Request) => {
  const origin = req.headers.get('origin');
  return !origin || origin === new URL(req.url).origin;
};
