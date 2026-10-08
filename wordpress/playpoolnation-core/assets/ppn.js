/* PlayPoolNation Core front-end: form tokens, open/closed status and "Near me". No dependencies. */
( function () {
	'use strict';

	/* Open/closed status. Mirrors Format::listing_open_status() in PHP; tests/fixtures/open-status.json covers both. */
	var WEEK = 10080;
	var DAYS = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];
	var CLOSED_STATUSES = { 'temporarily-closed': 'Temporarily closed', 'permanently-closed': 'Permanently closed' };

	function clock( minutes ) {
		minutes = ( ( minutes % 1440 ) + 1440 ) % 1440;
		if ( minutes === 0 ) {
			return 'Midnight';
		}
		if ( minutes === 720 ) {
			return 'Noon';
		}
		var h = Math.floor( minutes / 60 );
		var m = minutes % 60;
		var h12 = h % 12 || 12;
		return h12 + ( m ? ':' + ( m < 10 ? '0' : '' ) + m : '' ) + ' ' + ( h >= 12 ? 'PM' : 'AM' );
	}

	/* Minutes since Monday 00:00 in the venue's timezone, never the visitor's. */
	function weekMinute( tz, now ) {
		var parts = {};
		new Intl.DateTimeFormat( 'en-US', { timeZone: tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' } )
			.formatToParts( now )
			.forEach( function ( p ) { parts[ p.type ] = p.value; } );
		return DAYS.indexOf( parts.weekday ) * 1440 + ( parseInt( parts.hour, 10 ) % 24 ) * 60 + parseInt( parts.minute, 10 );
	}

	function normalize( ranges ) {
		var norm = [];
		for ( var i = 0; i < ranges.length; i++ ) {
			var s = ranges[ i ][ 0 ], e = ranges[ i ][ 1 ];
			if ( e <= s ) {
				continue;
			}
			if ( e - s >= WEEK ) {
				return null;
			}
			norm.push( [ s, e ] );
		}
		return norm.sort( function ( a, b ) { return a[ 0 ] - b[ 0 ]; } );
	}

	function ppnOpenStatus( data, now ) {
		if ( CLOSED_STATUSES[ data.s ] ) {
			return { state: 'closed', label: CLOSED_STATUSES[ data.s ] };
		}
		var ranges = data.r || [];
		if ( ! ranges.length ) {
			return { state: 'unknown', label: '' };
		}
		var minute = weekMinute( data.tz, now );
		var norm = normalize( ranges );
		if ( norm === null ) {
			return { state: 'open', label: 'Open 24 hours' };
		}
		if ( ! norm.length ) {
			return { state: 'unknown', label: '' };
		}
		var total = norm.reduce( function ( sum, r ) { return sum + r[ 1 ] - r[ 0 ]; }, 0 );
		if ( total >= WEEK - 1 ) {
			return { state: 'open', label: 'Open 24 hours' };
		}

		var shifts = [ 0, WEEK, -WEEK ];
		for ( var i = 0; i < norm.length; i++ ) {
			for ( var k = 0; k < shifts.length; k++ ) {
				if ( minute >= norm[ i ][ 0 ] + shifts[ k ] && minute < norm[ i ][ 1 ] + shifts[ k ] ) {
					var close = norm[ i ][ 1 ];
					for ( var j = 0; j < norm.length; j++ ) {
						if ( norm[ j ][ 0 ] === close % WEEK && norm[ j ][ 1 ] > norm[ j ][ 0 ] ) {
							close += norm[ j ][ 1 ] - norm[ j ][ 0 ];
						}
					}
					return { state: 'open', label: 'Open until ' + clock( close % 1440 ) };
				}
			}
		}

		var best = null;
		norm.forEach( function ( r ) {
			var delta = ( r[ 0 ] - minute + WEEK ) % WEEK;
			if ( best === null || delta < best[ 0 ] ) {
				best = [ delta, r[ 0 ] ];
			}
		} );
		var openDay = Math.floor( ( best[ 1 ] % WEEK ) / 1440 );
		var time = clock( best[ 1 ] % 1440 );
		var today = Math.floor( minute / 1440 );
		return { state: 'closed', label: ( openDay === today && best[ 0 ] < 1440 ) ? 'Opens ' + time : 'Opens ' + DAYS[ openDay ] + ' ' + time };
	}

	/* "Open hours today: 2 PM - 2 AM" for the venue's current day. */
	function todaysHours( data, now ) {
		var today = Math.floor( weekMinute( data.tz, now ) / 1440 );
		var spans = ( data.r || [] ).filter( function ( r ) { return Math.floor( r[ 0 ] / 1440 ) === today && r[ 1 ] > r[ 0 ]; } );
		if ( ! spans.length ) {
			return ( data.r || [] ).length ? 'Closed today' : '';
		}
		return 'Open hours today: ' + spans.map( function ( r ) {
			return r[ 1 ] - r[ 0 ] >= 1440 ? 'Open 24h' : clock( r[ 0 ] ) + ' - ' + clock( r[ 1 ] );
		} ).join( ', ' );
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { ppnOpenStatus: ppnOpenStatus, todaysHours: todaysHours };
	}
	if ( typeof document === 'undefined' ) {
		return;
	}

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

	/* Keep every open/closed status current; the HTML may have been cached days ago. */
	( function () {
		var mapEl = document.getElementById( 'ppn-hours-map' );
		var hours = {};
		var requested = {};
		var timer;
		try {
			hours = mapEl ? JSON.parse( mapEl.textContent ) || {} : {};
		} catch ( e ) {
			hours = {};
		}

		function parse( el ) {
			try {
				return JSON.parse( el.getAttribute( 'data-ppn-hours' ) );
			} catch ( e ) {
				return null;
			}
		}

		function statusFor( data ) {
			try {
				return ppnOpenStatus( data, new Date() );
			} catch ( e ) {
				return null;
			}
		}

		function setState( el, prefix, state ) {
			[ 'open', 'closed', 'unknown' ].forEach( function ( s ) { el.classList.remove( prefix + s ); } );
			el.classList.add( prefix + state );
		}

		function updateChips() {
			document.querySelectorAll( '.ppn-fact[data-ppn-hours]' ).forEach( function ( el ) {
				var data = parse( el );
				var st = data && statusFor( data );
				if ( st && st.label ) {
					setState( el, 'ppn-fact--', st.state );
					el.textContent = st.label;
				}
			} );
		}

		function updateHoursBlock() {
			document.querySelectorAll( '.open-now[data-ppn-hours]' ).forEach( function ( block ) {
				var data = parse( block );
				var st = data && statusFor( data );
				if ( ! st || ! st.label ) {
					return;
				}
				var status = block.querySelector( '.work-hours-status' );
				if ( status ) {
					status.className = st.state + ' work-hours-status';
					status.textContent = st.label;
				}
				var today = block.querySelector( '.timing-today' );
				var text = todaysHours( data, new Date() );
				if ( today && text ) {
					var line = today.querySelector( '.ppn-today' );
					if ( ! line ) {
						line = document.createElement( 'span' );
						line.className = 'ppn-today';
						while ( today.firstChild && ! ( today.firstChild.classList && today.firstChild.classList.contains( 'tooltip-element' ) ) ) {
							today.removeChild( today.firstChild );
						}
						today.insertBefore( line, today.firstChild );
					}
					line.textContent = text;
				}
				var local = block.querySelector( '[data-ppn-local-time]' );
				if ( local ) {
					try {
						local.querySelector( 'em' ).textContent = new Intl.DateTimeFormat( 'en-US', { timeZone: data.tz, weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' } ).format( new Date() ) + ' local time';
						local.hidden = false;
					} catch ( e ) {
						local.hidden = true;
					}
				}
			} );
		}

		function cardId( card ) {
			var m = /^listing-id-(\d+)$/.exec( card.getAttribute( 'data-id' ) || '' );
			return m ? m[ 1 ] : '';
		}

		function updateCards() {
			var missing = [];
			document.querySelectorAll( '.lf-item-container[data-id]' ).forEach( function ( card ) {
				var badge = card.querySelector( '.lf-head-btn.open-status' );
				var id = cardId( card );
				if ( ! badge || ! id ) {
					return;
				}
				if ( ! hours[ id ] ) {
					if ( ! requested[ id ] ) {
						missing.push( id );
					}
					return;
				}
				var st = statusFor( hours[ id ] );
				if ( ! st || st.state === 'unknown' ) {
					return;
				}
				[ 'open', 'closed', 'closing', 'opening', 'open-all-day', 'not-available' ].forEach( function ( s ) { badge.classList.remove( 'listing-status-' + s ); } );
				badge.classList.add( 'listing-status-' + st.state );
				badge.textContent = st.state === 'open' ? 'OPEN' : 'CLOSED';
				badge.title = st.label;
			} );
			if ( missing.length && cfg.hoursUrl && window.fetch ) {
				missing.forEach( function ( id ) { requested[ id ] = true; } );
				fetch( cfg.hoursUrl + '?ids=' + missing.slice( 0, 100 ).join( ',' ), { credentials: 'omit' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( more ) {
						Object.keys( more || {} ).forEach( function ( id ) { hours[ id ] = more[ id ]; } );
						updateCards();
					} )
					.catch( function () {} );
			}
		}

		function updateAll() {
			updateChips();
			updateHoursBlock();
			updateCards();
		}

		if ( ! window.Intl || ! Intl.DateTimeFormat ) {
			return;
		}
		updateAll();
		setInterval( updateAll, 60000 );
		if ( window.MutationObserver ) {
			new MutationObserver( function ( records ) {
				var added = records.some( function ( r ) {
					return Array.prototype.some.call( r.addedNodes, function ( n ) {
						return n.nodeType === 1 && ( n.matches( '.lf-item-container' ) || n.querySelector( '.lf-item-container' ) );
					} );
				} );
				if ( added ) {
					clearTimeout( timer );
					timer = setTimeout( updateCards, 50 );
				}
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}() );

	/* Sign-in prompt: only when a signed-out visitor tries to save a place or write a review. */
	var signin = document.querySelector( '[data-ppn-signin]' );
	if ( signin && ! document.body.classList.contains( 'logged-in' ) ) {
		var TRIGGERS = { save: '.c27-bookmark-button, .mylisting-bookmark-item', review: '.show-review-form' };
		var opener = null;
		var title = signin.querySelector( '#ppn-signin-title' );
		var text = signin.querySelector( '#ppn-signin-text' );
		var withReturn = function ( href ) {
			var url = new URL( href, location.href );
			url.searchParams.set( 'redirect_to', location.href.split( '#' )[ 0 ] );
			return url.toString();
		};
		signin.querySelectorAll( '[data-ppn-signin-go]' ).forEach( function ( a ) {
			a.href = withReturn( a.getAttribute( 'href' ) );
		} );
		var focusable = function () {
			return signin.querySelectorAll( 'a[href], button' );
		};
		var onKey = function ( e ) {
			if ( e.key === 'Escape' ) {
				closeSignin();
				return;
			}
			if ( e.key === 'Tab' ) {
				var f = focusable();
				var first = f[ 0 ], last = f[ f.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				} else if ( ! signin.contains( document.activeElement ) ) {
					e.preventDefault();
					first.focus();
				}
			}
		};
		var closeSignin = function () {
			signin.hidden = true;
			document.removeEventListener( 'keydown', onKey );
			if ( opener && opener.focus ) {
				opener.focus();
			}
			opener = null;
		};
		var openSignin = function ( reason, trigger ) {
			opener = trigger;
			title.textContent = signin.getAttribute( 'data-' + reason + '-title' );
			text.textContent = signin.getAttribute( 'data-' + reason + '-text' );
			signin.hidden = false;
			document.addEventListener( 'keydown', onKey );
			var go = signin.querySelector( '[data-ppn-signin-go]' );
			if ( go ) {
				go.focus();
			}
		};
		signin.querySelectorAll( '[data-ppn-signin-close]' ).forEach( function ( el ) {
			el.addEventListener( 'click', closeSignin );
		} );
		// Capture phase, so the theme's own save and review handlers never run for guests.
		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest && e.target.closest( TRIGGERS.save + ', ' + TRIGGERS.review );
			if ( ! trigger ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			openSignin( trigger.matches( TRIGGERS.review ) ? 'review' : 'save', trigger );
		}, true );
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
