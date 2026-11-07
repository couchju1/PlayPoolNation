#!/usr/bin/env node
// Copies MapLibre's web worker into public/vendor/ (it can't be bundled with the page script). Runs before each build.
import fs from 'node:fs';
const src = 'node_modules/maplibre-gl/dist/maplibre-gl-worker.mjs';
fs.mkdirSync('public/vendor', { recursive: true });
fs.copyFileSync(src, 'public/vendor/maplibre-gl-worker.mjs');
