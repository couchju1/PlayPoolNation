// Static site: every page is plain HTML built from data/, served from a CDN.
import { defineConfig } from 'astro/config';

export default defineConfig({
  site: 'https://playpoolnation.com',
  trailingSlash: 'always',
  build: { format: 'directory', inlineStylesheets: 'auto' },
  compressHTML: true,
  prefetch: false,
});
