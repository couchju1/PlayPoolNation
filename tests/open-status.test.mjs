// The plugin's open/closed fixtures, run against the browser code the new site ships.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { openStatus, todaysHours } from '../src/lib/open-status.js';

const cases = JSON.parse(fs.readFileSync(new URL('./fixtures/open-status.json', import.meta.url), 'utf8'));

for (const c of cases) {
  test(c.name, () => {
    assert.deepEqual(openStatus({ tz: c.tz, r: c.r, s: c.s }, new Date(c.now)), { state: c.state, label: c.label });
  });
}

const daily = [0, 1, 2, 3, 4, 5, 6].map((d) => [d * 1440 + 840, d * 1440 + 1560]);
test('today line uses the venue day, not the visitor day', () => {
  assert.equal(todaysHours({ tz: 'America/Chicago', r: daily }, new Date('2026-10-08T03:00:00Z')), 'Open hours today: 2 PM - 2 AM');
});
test('today line when closed all day', () => {
  assert.equal(todaysHours({ tz: 'America/Chicago', r: [[1440 + 960, 1440 + 1380]] }, new Date('2026-10-05T17:00:00Z')), 'Closed today');
});
