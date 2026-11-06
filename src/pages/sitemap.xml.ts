// /sitemap.xml is the URL Search Console already knows from the old site; it's now the index of the split sitemaps.
import { index } from '../lib/sitemap.ts';
export function GET() {
  return new Response(index(), { headers: { 'Content-Type': 'application/xml' } });
}
