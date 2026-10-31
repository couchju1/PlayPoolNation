/* Homepage "Play tonight" picks (ppnTonightRank). Usage: node tests/js/tonight.test.js */
'use strict';

const assert = require( 'node:assert/strict' );
const path = require( 'node:path' );
const { ppnTonightRank, ppnOpenCount, miles } = require( path.join( __dirname, '../../assets/ppn.js' ) );

let failed = 0;
function check( name, fn ) {
	try {
		fn();
	} catch ( e ) {
		failed++;
		console.error( 'FAIL ' + name + '\n' + e.message );
	}
}

// 2 PM to 2 AM every day; 4 PM to 11 PM every day.
const late = [ 0, 1, 2, 3, 4, 5, 6 ].map( ( d ) => [ d * 1440 + 840, d * 1440 + 1560 ] );
const evening = [ 0, 1, 2, 3, 4, 5, 6 ].map( ( d ) => [ d * 1440 + 960, d * 1440 + 1380 ] );
const CT = 'America/Chicago';
const venues = [
	{ i: 1, n: 'Rack City Billiards', k: 10, a: 43.5446, o: -96.6731, t: 18, h: { tz: CT, r: late, s: 'open' }, l: [ 2 ] },
	{ i: 2, n: 'Corner Bar', k: 10, a: 43.5500, o: -96.7000, t: 2, h: { tz: CT, r: evening, s: 'open' } },
	{ i: 3, n: 'No Hours Pub', k: 10, a: 43.5460, o: -96.6740, h: { tz: CT, r: [], s: 'open' } },
	{ i: 4, n: 'Shut Hall', k: 10, a: 43.5447, o: -96.6732, t: 30, h: { tz: CT, r: late, s: 'temporarily-closed' } },
	{ i: 5, n: 'Omaha Room', k: 20, a: 41.2565, o: -95.9345, t: 12, h: { tz: CT, r: late, s: 'open' } },
];
// Wednesday 3 PM in Chicago: Rack City open (league night, Wednesday = 2), Corner Bar opens at 4.
const wed3pm = new Date( '2026-10-07T20:00:00Z' );

check( 'distance is about right', () => {
	const m = miles( 43.5446, -96.6731, 41.2565, -95.9345 );
	assert.ok( m > 155 && m < 170, String( m ) );
} );

check( 'near me: open first, then unknown hours, then closed; far venues left out', () => {
	const picks = ppnTonightRank( venues, { lat: 43.5446, lng: -96.6731, now: wed3pm, radius: 50, limit: 10 } );
	assert.deepEqual( picks.map( ( p ) => p.v.i ), [ 1, 3, 4, 2 ] );
	assert.equal( picks[ 0 ].status.label, 'Open until 2 AM' );
	assert.equal( picks[ 0 ].league, true );
	assert.equal( picks[ 3 ].status.label, 'Opens 4 PM' );
	assert.equal( picks[ 2 ].league, false );
} );

check( 'near me: closer open venue wins', () => {
	const pm6 = new Date( '2026-10-07T23:00:00Z' );
	const picks = ppnTonightRank( venues, { lat: 43.5500, lng: -96.7000, now: pm6, radius: 50 } );
	assert.deepEqual( picks.map( ( p ) => p.v.i ), [ 2, 1, 3 ] );
} );

check( 'city: most tables first among open rooms, limited to three', () => {
	const pm6 = new Date( '2026-10-07T23:00:00Z' );
	const picks = ppnTonightRank( venues, { city: 10, now: pm6 } );
	assert.deepEqual( picks.map( ( p ) => p.v.i ), [ 1, 2, 3 ] );
	assert.equal( picks[ 0 ].miles, null );
} );

check( 'nothing in range', () => {
	assert.deepEqual( ppnTonightRank( venues, { lat: 40.7128, lng: -74.006, now: wed3pm, radius: 50 } ), [] );
} );

check( 'open count', () => {
	assert.equal( ppnOpenCount( venues, wed3pm ), 2 );
} );

if ( failed ) {
	console.error( failed + ' failed' );
	process.exit( 1 );
}
console.log( 'tonight: all passed' );
