import { SITE } from '../lib/data.ts';
export function GET() {
  return new Response(`User-agent: *\nDisallow: /api/\n\nSitemap: ${SITE}/sitemap.xml\n`, { headers: { 'Content-Type': 'text/plain' } });
}
