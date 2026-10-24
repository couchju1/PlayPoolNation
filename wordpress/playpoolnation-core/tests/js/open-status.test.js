/* Runs the PHP Format::listing_open_status fixtures against ppnOpenStatus. Usage: node tests/js/open-status.test.js */
'use strict';

const assert = require( 'node:assert/strict' );
const path = require( 'node:path' );
const { ppnOpenStatus, todaysHours } = require( path.join( __dirname, '../../assets/ppn.js' ) );
const cases = require( path.join( __dirname, '../fixtures/open-status.json' ) );

let failed = 0;
function check( name, fn ) {
	try {
		fn();
	} catch ( e ) {
		failed++;
		console.error( 'FAIL ' + name + '\n' + e.message );
	}
}

cases.forEach( ( c ) => {
	check( c.name, () => {
		assert.deepEqual( ppnOpenStatus( { tz: c.tz, r: c.r, s: c.s }, new Date( c.now ) ), { state: c.state, label: c.label } );
	} );
} );

const daily = [ 0, 1, 2, 3, 4, 5, 6 ].map( ( d ) => [ d * 1440 + 840, d * 1440 + 1560 ] );
check( 'today line uses the venue day, not the visitor day', () => {
	assert.equal( todaysHours( { tz: 'America/Chicago', r: daily }, new Date( '2026-10-08T03:00:00Z' ) ), 'Open hours today: 2 PM - 2 AM' );
} );
check( 'today line when closed all day', () => {
	assert.equal( todaysHours( { tz: 'America/Chicago', r: [ [ 1440 + 960, 1440 + 1380 ] ] }, new Date( '2026-10-05T17:00:00Z' ) ), 'Closed today' );
} );

process.stdout.write( ( cases.length + 2 - failed ) + '/' + ( cases.length + 2 ) + ' passed\n' );
process.exit( failed ? 1 : 0 );
