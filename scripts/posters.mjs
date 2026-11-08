/**
 * Resize Higgsfield city poster originals into the sizes the site serves.
 *   node scripts/posters.mjs <folder of originals named <key>.webp>
 * Writes public/img/cities/<key>.webp (900x1125), <key>-sm.webp (480x600), <key>-og.jpg (1200x630).
 */
import { readdirSync } from 'node:fs';
import { join, basename } from 'node:path';
import sharp from 'sharp';

const dir = process.argv[2];
if (!dir) throw new Error('Pass the folder of poster originals.');
const out = 'public/img/cities';
for (const file of readdirSync(dir).filter((f) => f.endsWith('.webp'))) {
  const key = basename(file, '.webp');
  const src = join(dir, file);
  await sharp(src).resize(900, 1125).webp({ quality: 78 }).toFile(join(out, `${key}.webp`));
  await sharp(src).resize(480, 600).webp({ quality: 74 }).toFile(join(out, `${key}-sm.webp`));
  // Social card: the skyline band, where the landmarks are.
  await sharp(src).resize(1200, 1500).extract({ left: 0, top: 480, width: 1200, height: 630 }).jpeg({ quality: 80, mozjpeg: true }).toFile(join(out, `${key}-og.jpg`));
  console.log(key);
}
