/* PlayPoolNation Core front-end: form tokens and "Near me". No dependencies. */
( function () {
	'use strict';
	var cfg = window.ppnCore || {};

	/* Signed form tokens: pages are cached, so fetch a fresh token when a form is used. */
	function fetchToken( input ) {
		if ( input.value || input.dataset.loading ) {
			return Promise.resolve( input.value );
		}
		input.dataset.loading = '1';
		return fetch( cfg.tokenUrl, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) { input.value = d && d.token ? d.token : ''; return input.value; } )
			.catch( function () { return ''; } )
			.finally( function () { delete input.dataset.loading; } );
	}

	document.querySelectorAll( 'form.ppn-form' ).forEach( function ( form ) {
		var input = form.querySelector( '[data-ppn-token]' );
		if ( ! input ) {
			return;
		}
		form.addEventListener( 'focusin', function () { fetchToken( input ); }, { once: true } );
		form.addEventListener( 'submit', function ( e ) {
			if ( input.value ) {
				return;
			}
			e.preventDefault();
			fetchToken( input ).then( function () {
				// The server rejects submissions faster than a few seconds; wait briefly if needed.
				setTimeout( function () { form.submit(); }, 3200 );
			} );
		} );
	} );

	/* Open the suggest-an-edit panel when linked to directly. */
	if ( location.hash === '#suggest-edit' ) {
		var panel = document.getElementById( 'suggest-edit' );
		if ( panel ) {
			panel.open = true;
		}
	}

	/* Near me: use the browser location, then open the map sorted by distance. */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-ppn-near-me]' );
		if ( ! btn ) {
			return;
		}
		var msg = btn.nextElementSibling && btn.nextElementSibling.classList.contains( 'ppn-near-me-msg' ) ? btn.nextElementSibling : null;
		var say = function ( t ) { if ( msg ) { msg.textContent = t; } };
		if ( ! navigator.geolocation ) {
			say( 'Your browser cannot share a location. Search by city or ZIP instead.' );
			return;
		}
		btn.setAttribute( 'aria-busy', 'true' );
		say( 'Finding places near you…' );
		navigator.geolocation.getCurrentPosition( function ( pos ) {
			var url = new URL( cfg.exploreUrl || '/', location.href );
			url.searchParams.set( 'type', 'place' );
			url.searchParams.set( 'search_location', 'Current location' );
			url.searchParams.set( 'lat', pos.coords.latitude.toFixed( 5 ) );
			url.searchParams.set( 'lng', pos.coords.longitude.toFixed( 5 ) );
			url.searchParams.set( 'proximity', '15' );
			url.searchParams.set( 'sort', 'nearby' );
			location.href = url.toString();
		}, function ( err ) {
			btn.removeAttribute( 'aria-busy' );
			say( err && err.code === 1 ? 'Location sharing is off. Search by city or ZIP instead.' : 'We could not get your location. Search by city or ZIP instead.' );
		}, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 } );
	} );
}() );
