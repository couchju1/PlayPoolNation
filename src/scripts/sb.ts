/**
 * Supabase in the browser, for accounts and reviews. The publishable key is meant to be
 * public: what it can read and write is limited by the row-level security in db/schema.sql.
 * The library (about 45 KB gzipped) loads only when a page needs it.
 */
export const SUPABASE_URL = import.meta.env.PUBLIC_SUPABASE_URL || 'https://fynwiiivlhpbsvclecmk.supabase.co';
export const SUPABASE_KEY = import.meta.env.PUBLIC_SUPABASE_KEY || 'sb_publishable_emix8hMEar_ZIOUoLADnMA_MLY1dZfy';
const STORAGE_KEY = 'sb-' + new URL(SUPABASE_URL).hostname.split('.')[0] + '-auth-token';

let client: Promise<any> | null = null;
export function sb(): Promise<any> {
  client ??= import('@supabase/supabase-js').then(({ createClient }) =>
    createClient(SUPABASE_URL, SUPABASE_KEY, { auth: { persistSession: true, autoRefreshToken: true, detectSessionInUrl: true, flowType: 'pkce' } }),
  );
  return client;
}

/** Who's signed in, read from the saved session without loading the library. Null if nobody. */
export function savedUser(): { id: string; email: string; name: string } | null {
  try {
    const raw = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
    const u = raw?.user;
    if (!u?.id) return null;
    return { id: u.id, email: u.email || '', name: u.user_metadata?.display_name || (u.email || '').split('@')[0] };
  } catch {
    return null;
  }
}
