import { SITEMAPS, urlset } from '../lib/sitemap.ts';

export function getStaticPaths() {
  return Object.keys(SITEMAPS).map((type) => ({ params: { type } }));
}
export function GET({ params }: { params: { type: string } }) {
  return new Response(urlset(SITEMAPS[params.type]()), { headers: { 'Content-Type': 'application/xml' } });
}
