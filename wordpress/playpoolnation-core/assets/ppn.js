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

	/* Post an event: venue search, "a different venue", and the repeat end date. */
	document.querySelectorAll( '[data-ppn-event-form]' ).forEach( function ( form ) {
		var search = form.querySelector( '[data-ppn-venue-search]' );
		var hiddenId = form.querySelector( '[data-ppn-venue-id]' );
		var list = search ? document.getElementById( search.getAttribute( 'list' ) ) : null;
		var owned = form.querySelector( 'select[name="venue_id"]' );
		var other = form.querySelector( '[data-ppn-other-venue]' );
		var repeat = form.querySelector( 'select[name="repeat"]' );
		var until = form.querySelector( '[data-ppn-until]' );
		var found = {};
		var timer;

		if ( owned && other ) {
			var syncOther = function () {
				var isOther = owned.value === 'other';
				other.hidden = ! isOther;
				search.required = isOther;
			};
			owned.addEventListener( 'change', syncOther );
			syncOther();
		}
		if ( repeat && until ) {
			var syncRepeat = function () {
				var repeats = repeat.value && repeat.value !== 'none';
				until.hidden = ! repeats;
				until.querySelector( 'input' ).required = repeats;
			};
			repeat.addEventListener( 'change', syncRepeat );
			syncRepeat();
		}
		if ( search && list && hiddenId && window.fetch ) {
			search.addEventListener( 'input', function () {
				var q = search.value.trim();
				hiddenId.value = found[ q ] || '';
				clearTimeout( timer );
				if ( q.length < 2 || found[ q ] ) {
					return;
				}
				timer = setTimeout( function () {
					fetch( cfg.venuesUrl + '?q=' + encodeURIComponent( q ), { credentials: 'same-origin' } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( rows ) {
							list.innerHTML = '';
							( Array.isArray( rows ) ? rows : [] ).forEach( function ( row ) {
								found[ row.label ] = row.id;
								var opt = document.createElement( 'option' );
								opt.value = row.label;
								list.appendChild( opt );
							} );
							hiddenId.value = found[ search.value.trim() ] || '';
						} )
						.catch( function () {} );
				}, 250 );
			} );
		}
	} );

	/* My Pool: fill the area from the browser's location, then save. */
	document.querySelectorAll( '[data-ppn-area-form]' ).forEach( function ( form ) {
		var btn = form.querySelector( '[data-ppn-locate]' );
		if ( ! btn ) {
			return;
		}
		var msg = form.querySelector( '.ppn-near-me-msg' );
		var say = function ( t ) { if ( msg ) { msg.textContent = t; } };
		btn.addEventListener( 'click', function () {
			if ( ! navigator.geolocation ) {
				say( 'Your browser cannot share a location. Type a city or ZIP instead.' );
				return;
			}
			btn.setAttribute( 'aria-busy', 'true' );
			say( 'Finding your location…' );
			navigator.geolocation.getCurrentPosition( function ( pos ) {
				form.querySelector( '[data-ppn-lat]' ).value = pos.coords.latitude.toFixed( 4 );
				form.querySelector( '[data-ppn-lng]' ).value = pos.coords.longitude.toFixed( 4 );
				var area = form.querySelector( 'input[name="area"]' );
				if ( area && ! area.value ) {
					area.value = 'My location';
				}
				form.submit();
			}, function ( err ) {
				btn.removeAttribute( 'aria-busy' );
				say( err && err.code === 1 ? 'Location sharing is off. Type a city or ZIP instead.' : 'We could not get your location. Type a city or ZIP instead.' );
			}, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 } );
		} );
		// Typing a new area clears any earlier browser location.
		var typed = form.querySelector( 'input[name="area"]' );
		if ( typed ) {
			typed.addEventListener( 'input', function () {
				form.querySelector( '[data-ppn-lat]' ).value = '';
				form.querySelector( '[data-ppn-lng]' ).value = '';
			} );
		}
	} );

	/* /join/ opens on the Register tab of the theme's sign-in form. */
	var wantsRegister = document.querySelector( '[data-ppn-tab="register"]' );
	if ( wantsRegister ) {
		var regTab = wantsRegister.querySelector( 'a[data-form="register"]' );
		var loginBox = wantsRegister.querySelector( '.login-form-wrap' );
		var regBox = wantsRegister.querySelector( '.register-form-wrap' );
		if ( regTab && loginBox && regBox ) {
			wantsRegister.querySelectorAll( '.login-tabs li' ).forEach( function ( li ) { li.classList.remove( 'active' ); } );
			regTab.closest( 'li' ).classList.add( 'active' );
			loginBox.classList.add( 'hide' );
			regBox.classList.remove( 'hide' );
		}
	}

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
