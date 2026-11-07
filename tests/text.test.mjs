// Ported from the plugin's AboutTextTest and CityTextTest: words come from facts only.
import test from 'node:test';
import assert from 'node:assert/strict';
import * as T from '../src/lib/text.ts';

const base = { tables: null, sizeCounts: {}, sizes: [], brands: [], pricing: [], hourlyRate: '', gamePrice: '', food: false, games: [], leagues: [], status: 'open', hours: [] };
const v = (o) => ({ ...base, ...o });
const text = (f) => T.about(v(f)).sentences.join(' ');
const claim = (f) => { const c = T.about(v(f)).claim; return c ? `${c.lead} Claim it${c.rest}` : ''; };
const plain = (s) => assert.doesNotMatch(s, /[–—;]| - /);

test('full data', () => {
  const f = { name: 'Rack City Billiards', type: 'pool-halls', street: '309 S Bahnson Ave', cityState: 'Sioux Falls, SD', tables: 18, sizeCounts: { '7ft': 16, '9ft': 2 }, sizes: ['7ft', '9ft'], brands: ['Diamond', 'Valley'], games: ['Darts'], pricing: ['hourly'], hourlyRate: '$12', leagues: [{ day: 1, time: '', orgs: ['APA'], name: 'APA' }], photo: true };
  const t = text(f);
  assert.equal(t, "Rack City Billiards is a pool hall at 309 S Bahnson Ave in Sioux Falls, SD. It has 18 tables. Most are 7-foot, plus two 9-foot tables. They're Diamond and Valley tables. Pool is $12 an hour. There's darts too. League nights are listed below.");
  plain(t);
  assert.equal(claim(f), 'Own Rack City Billiards? Claim it to keep it current.');
});

test('partial data lists only unknowns', () => {
  const f = { name: 'Rack City Billiards', type: 'pool-halls', cityState: 'Sioux Falls, SD', tables: 18, sizeCounts: { '7ft': 16, '9ft': 2 }, brands: ['Diamond'] };
  assert.equal(text(f), "Rack City Billiards is a pool hall in Sioux Falls, SD. It has 18 tables. Most are 7-foot, plus two 9-foot tables. They're Diamond tables.");
  assert.equal(claim(f), 'Own Rack City Billiards? Claim it to add hourly rates, league nights and photos.');
  assert.equal(text({ name: 'Q Lounge', type: 'billiards-lounge', sizes: ['9ft', '7ft'] }), "Q Lounge is a billiards lounge. The tables are 7-foot and 9-foot. We don't know how many there are yet.");
  assert.match(text({ name: 'Cue Club', type: 'pool-halls', tables: 8, sizeCounts: { '8ft': 4, '9ft': 4 } }), /It has eight tables\. That's four 8-foot and four 9-foot\./);
  assert.equal(text({ name: 'Bigs Bar', type: 'bars-with-pool-tables', tables: 4, pricing: ['hourly'] }), "Bigs Bar is a bar with pool tables. It has four tables. We don't know the sizes yet. You pay for pool by the hour.");
});

test('nothing known', () => {
  const f = { name: 'Amsterdam Billiards', type: 'pool-halls', street: '110 E 11th St', cityState: 'New York, NY' };
  assert.equal(text(f), "Amsterdam Billiards is a pool hall at 110 E 11th St in New York, NY. We don't know the table count or sizes yet.");
  assert.equal(claim(f), 'Own Amsterdam Billiards? Claim it to add the table count, table sizes, hourly rates, league nights and photos.');
  assert.equal(T.about(v({ ...f, claimed: true })).claim, null);
});

test('bar versus hall', () => {
  assert.equal(T.identity(v({ name: 'The Dugout', type: 'sports-bar', street: 'Main Street', cityState: 'Fargo, ND' })), 'The Dugout is a sports bar on Main Street in Fargo, ND with pool tables.');
  assert.equal(T.identity(v({ name: 'Somewhere', type: 'other-venue' })), 'Somewhere is a place to play pool.');
});

test('hours', () => {
  const day = (d, o, c) => [d * 1440 + o, d * 1440 + c];
  const week = [day(1, 960, 1440), day(2, 960, 1440), day(3, 960, 1440), day(4, 960, 1560), day(5, 960, 1560), day(6, 960, 1440)];
  assert.deepEqual(T.hoursSentences({ hours: week }), ["It's closed on Mondays.", 'It stays open until 2 AM on Fridays and Saturdays.']);
  const most = [...[0, 1, 2, 3, 4, 5].map((d) => day(d, 660, 1440)), day(6, 660, 1320)];
  assert.deepEqual(T.hoursSentences({ hours: most }), ["It's open every day.", 'It stays open until midnight every night except Sundays.']);
  const daily = [0, 1, 2, 3, 4, 5, 6].map((d) => day(d, 840, 1560));
  assert.deepEqual(T.hoursSentences({ hours: daily }), ["It's open every day until 2 AM."]);
  const split = [[0, 120], [660, 1560], [2100, 3000], [3540, 4440], [4980, 5880], [6420, 7320], [7860, 8760], [9300, 10080]];
  assert.deepEqual(T.hoursSentences({ hours: split }), ["It's open every day until 2 AM."]);
  assert.deepEqual(T.hoursSentences({ hours: [[0, 10080]] }), ["It's open 24 hours a day."]);
  assert.deepEqual(T.hoursSentences({ hours: daily, status: 'temporarily-closed' }), ["It's temporarily closed."]);
  assert.deepEqual(T.hoursSentences({}), []);
});

test('city intro compares only what is known', () => {
  const list = [
    v({ name: 'Rack City Billiards', type: 'pool-halls', tables: 19, sizes: ['7ft', '9ft'], sizeCounts: { '9ft': 2 } }),
    v({ name: 'Bigs Bar', type: 'pool-halls', tables: 12 }),
    v({ name: 'Nickel Spot', type: 'bars-with-pool-tables' }),
  ];
  assert.equal(T.intro('Sioux Falls', list), "Sioux Falls has 3 places to play pool on our map. That's 2 pool halls and 1 bar. Of the 2 places where we know the count, Rack City Billiards has the most tables, with 19. Rack City Billiards has 9-foot tables.");
  const faq = T.faq('Sioux Falls', list);
  assert.equal(faq[1].a, 'Rack City Billiards has two 9-foot tables.');
  assert.ok(T.cityTitle('Sioux Falls, SD', 3, 'PlayPoolNation').length <= 59);
  assert.ok(T.cityDescription('Sioux Falls, SD', list).length <= 154);
});

test('one count compares with nothing', () => {
  assert.equal(T.mostTables([v({ name: 'A', tables: 5 }), v({ name: 'B' })]), null);
});

test('week hours keep a late night on the day it started', () => {
  const split = [[0, 120], [9300, 10080]];
  assert.deepEqual(T.weekHours(split).at(-1), { day: 'Sunday', hours: '11 AM to 2 AM' });
  assert.equal(T.weekHours(split)[0].hours, 'Closed');
});
