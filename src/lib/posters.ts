/**
 * City posters: one illustrated poster per city guide, in public/img/cities/<key>.webp
 * (900x1125), <key>-sm.webp (480x600) and <key>-og.jpg (1200x630 social card).
 * <key> is the guide's path under /places/ with slashes as dashes:
 * /places/texas/austin/ -> texas-austin, /places/boston/ -> boston.
 * Hub pages (/places/texas/austin/9-foot-tables/) use their city's poster.
 * Made with Higgsfield (GPT Image 2.6); scripts/posters.mjs resizes the originals.
 */
import { existsSync } from 'node:fs';

export interface Poster { src: string; srcSm: string; og: string }

const has = (key: string) => existsSync(`public/img/cities/${key}.webp`);

export function posterFor(path: string): Poster | null {
  const parts = path.replace(/^\/places\//, '').replace(/\/$/, '').split('/').filter(Boolean);
  for (let n = parts.length; n > 0; n--) {
    const key = parts.slice(0, n).join('-');
    if (key && has(key)) return { src: `/img/cities/${key}.webp`, srcSm: `/img/cities/${key}-sm.webp`, og: `/img/cities/${key}-og.jpg` };
  }
  return null;
}
