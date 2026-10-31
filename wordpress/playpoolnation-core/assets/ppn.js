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

	/* Miles between two points (haversine). */
	function miles( lat1, lng1, lat2, lng2 ) {
		var rad = Math.PI / 180;
		var dLat = ( lat2 - lat1 ) * rad;
		var dLng = ( lng2 - lng1 ) * rad;
		var a = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) + Math.cos( lat1 * rad ) * Math.cos( lat2 * rad ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );
		return 3958.8 * 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
	}

	/*
	 * "Play tonight" picks (Tonight). Open rooms first, then rooms with unknown hours,
	 * then closed ones. Near a point: nearest first, within opts.radius miles. In a
	 * city: most tables first. Each pick carries its status, distance and whether a
	 * league plays there today (in the venue's own timezone).
	 */
	function ppnTonightRank( venues, opts ) {
		var now = opts.now || new Date();
		var geo = typeof opts.lat === 'number' && typeof opts.lng === 'number';
		var rank = { open: 0, unknown: 1, closed: 2 };
		var picks = [];
		venues.forEach( function ( v ) {
			if ( ! geo && v.k !== opts.city ) {
				return;
			}
			var dist = geo ? miles( opts.lat, opts.lng, v.a, v.o ) : null;
			if ( geo && dist > ( opts.radius || 50 ) ) {
				return;
			}
			var status = ppnOpenStatus( v.h || {}, now );
			var day = v.h && v.h.tz ? Math.floor( weekMinute( v.h.tz, now ) / 1440 ) : -1;
			picks.push( { v: v, miles: dist, status: status, league: ( v.l || [] ).indexOf( day ) !== -1 && status.state !== 'closed' } );
		} );
		picks.sort( function ( a, b ) {
			return ( rank[ a.status.state ] - rank[ b.status.state ] ) ||
				( geo ? a.miles - b.miles : ( b.v.t || 0 ) - ( a.v.t || 0 ) ) ||
				a.v.n.localeCompare( b.v.n );
		} );
		return picks.slice( 0, opts.limit || 3 );
	}

	/* How many of the venues are open at this moment. */
	function ppnOpenCount( venues, now ) {
		return venues.filter( function ( v ) { return ppnOpenStatus( v.h || {}, now ).state === 'open'; } ).length;
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { ppnOpenStatus: ppnOpenStatus, todaysHours: todaysHours, miles: miles, ppnTonightRank: ppnTonightRank, ppnOpenCount: ppnOpenCount };
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

	/*
	 * The theme's controls are <a href="#"> (quick view, save, share, tabs, back to top).
	 * Announce them as buttons, make Space work like Enter, and name the icon-only ones
	 * after the venue they act on.
	 */
	( function () {
		var LABELS = [
			[ '.c27-toggle-quick-view-modal', 'Quick view' ],
			[ '.c27-bookmark-button, .mylisting-bookmark-item', 'Save' ],
			[ '.c27-native-share', 'Share' ],
			[ '.back-to-top', 'Back to top' ],
			[ '.marker-icon', 'Show on map' ]
		];
		function venueName( el ) {
			var card = el.closest( '.lf-item-container' );
			var title = card ? card.querySelector( '.listing-preview-title' ) : document.querySelector( '.main-info-mobile .profile-name h1' );
			return title ? title.textContent.replace( /\s+/g, ' ' ).trim() : '';
		}
		function enhance( root ) {
			root.querySelectorAll( 'a[href="#"]:not([data-ppn-button])' ).forEach( function ( a ) {
				a.setAttribute( 'data-ppn-button', '' );
				a.setAttribute( 'role', 'button' );
				a.setAttribute( 'tabindex', '0' );
				a.removeAttribute( 'type' );
				var visible = a.textContent.replace( /\s+/g, '' ) !== '';
				for ( var i = 0; i < LABELS.length; i++ ) {
					if ( a.matches( LABELS[ i ][ 0 ] ) ) {
						var name = LABELS[ i ][ 1 ] === 'Back to top' ? '' : venueName( a );
						if ( ! visible || /button$/i.test( a.getAttribute( 'aria-label' ) || '' ) ) {
							a.setAttribute( 'aria-label', LABELS[ i ][ 1 ] + ( name ? ' ' + name : '' ) );
						}
						break;
					}
				}
			} );
		}
		document.addEventListener( 'keydown', function ( e ) {
			var a = ( e.key === ' ' || e.key === 'Spacebar' ) && e.target.closest && e.target.closest( 'a[data-ppn-button]' );
			if ( a ) {
				e.preventDefault();
				a.click();
			}
		} );
		enhance( document );
		if ( window.MutationObserver ) {
			new MutationObserver( function ( records ) {
				records.forEach( function ( r ) {
					r.addedNodes.forEach( function ( n ) {
						if ( n.nodeType === 1 ) {
							enhance( n.matches( 'a' ) ? n.parentNode || n : n );
						}
					} );
				} );
			} ).observe( document.body, { childList: true, subtree: true } );
		}
	}() );

	/* "Tell us" links open Suggest an edit with the right reason chosen. */
	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest && e.target.closest( '[data-ppn-suggest]' );
		var panel = link && document.getElementById( 'suggest-edit' );
		if ( ! panel ) {
			return;
		}
		e.preventDefault();
		panel.open = true;
		var reason = panel.querySelector( 'select[name="reason"]' );
		if ( reason ) {
			reason.value = link.getAttribute( 'data-ppn-suggest' );
		}
		panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		( reason || panel.querySelector( 'summary' ) ).focus( { preventScroll: true } );
	} );

	/* Open the suggest-an-edit panel when linked to directly. */
	if ( location.hash === '#suggest-edit' ) {
		var panel = document.getElementById( 'suggest-edit' );
		if ( panel ) {
			panel.open = true;
		}
	}

	/*
	 * Pool game: the iframe address is held in data-src (Helpers\Html_Fix::pool_game_on_click)
	 * and loaded when the visitor presses Play. Until then, game and table changes only
	 * update that address, so choosing them loads nothing either.
	 */
	document.querySelectorAll( '.ppn-pg' ).forEach( function ( root ) {
		var wrap = root.querySelector( '.ppn-pg__frame[data-ppn-idle]' );
		var frame = wrap && wrap.querySelector( 'iframe[data-src]' );
		var play = wrap && wrap.querySelector( '[data-ppn-play]' );
		if ( ! frame || ! play ) {
			return;
		}
		var base = root.getAttribute( 'data-base' ) || '';
		var game = root.querySelector( '[data-ppn="game"]' );
		var table = root.querySelector( '[data-ppn="table"]' );
		var full = root.querySelector( '[data-ppn="full"]' );
		function address() {
			if ( ! game || ! table ) {
				return frame.getAttribute( 'data-src' );
			}
			var size = table.options[ table.selectedIndex ].getAttribute( 'data-size' );
			return base + 'index.html?ruletype=' + encodeURIComponent( game.value ) + '&tableSize=' + encodeURIComponent( size ) + '&v=' + encodeURIComponent( root.getAttribute( 'data-ver' ) || '' );
		}
		root.addEventListener( 'change', function ( e ) {
			if ( wrap.hasAttribute( 'data-ppn-idle' ) && ( e.target === game || e.target === table ) ) {
				e.stopPropagation();
				frame.setAttribute( 'data-src', address() );
				if ( full ) {
					full.href = address();
				}
			}
		}, true );
		play.addEventListener( 'click', function () {
			wrap.removeAttribute( 'data-ppn-idle' );
			frame.src = frame.getAttribute( 'data-src' );
			play.remove();
			frame.focus();
		} );
	} );

	/*
	 * Google Maps, where the page held it back (Performance::hold_maps): loaded when a
	 * map comes into view, a location field is used, or something that needs it is
	 * clicked (quick view shows a map; the location icon uses the geocoder). Those
	 * clicks are replayed once the theme reports the maps ready.
	 */
	( function () {
		var data = document.getElementById( 'ppn-maps' );
		if ( ! data ) {
			return;
		}
		var srcs = JSON.parse( data.textContent || '[]' );
		var LOCATION = '.location-wrapper input, .ehs-location input, .form-location-autocomplete, input[name="job_location"], input[name="_job_location"]';
		var NEEDS_MAPS = '.c27-toggle-quick-view-modal, .geocode-location';
		var started = false;
		var waiting = [];
		function ready() {
			return window.MyListing && MyListing.Maps && MyListing.Maps.loaded;
		}
		function load() {
			if ( started ) {
				return;
			}
			started = true;
			srcs.forEach( function ( src ) {
				var s = document.createElement( 'script' );
				s.src = src;
				s.async = false;
				document.head.appendChild( s );
			} );
		}
		if ( window.jQuery ) {
			jQuery( document ).on( 'maps:loaded', function () {
				waiting.splice( 0 ).forEach( function ( el ) {
					el.click();
				} );
			} );
		}
		window.addEventListener( 'click', function ( e ) {
			var el = e.target.closest && e.target.closest( NEEDS_MAPS );
			if ( ! el || ready() ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			if ( waiting.indexOf( el ) < 0 ) {
				waiting.push( el );
			}
			load();
		}, true );
		document.addEventListener( 'focusin', function ( e ) {
			if ( e.target.matches && e.target.matches( LOCATION ) ) {
				load();
			}
		} );
		var maps = document.querySelectorAll( '.c27-map' );
		if ( ! maps.length ) {
			return;
		}
		if ( ! ( 'IntersectionObserver' in window ) ) {
			load();
			return;
		}
		var observer = new IntersectionObserver( function ( entries ) {
			if ( entries.some( function ( entry ) { return entry.isIntersecting; } ) ) {
				observer.disconnect();
				load();
			}
		}, { rootMargin: '100px 0px' } );
		maps.forEach( function ( map ) {
			observer.observe( map );
		} );
	}() );

	/*
	 * City pages (City_Page): filter chips, sorting the summary table by tables, and
	 * the map. The page is complete without this: the chips stay hidden, the jump
	 * links are plain anchors and "Show map" opens the explore map instead.
	 */
	( function () {
		var root = document.querySelector( '[data-ppn-city]' );
		if ( ! root ) {
			return;
		}
		var sections = Array.prototype.slice.call( root.querySelectorAll( '.ppn-city-venue[data-ppn-venue]' ) );
		var filters = root.querySelector( '[data-ppn-city-filters]' );
		var active = {};

		function hoursOf( el ) {
			var chip = el.querySelector( '[data-ppn-hours]' );
			try {
				return chip ? JSON.parse( chip.getAttribute( 'data-ppn-hours' ) ) : null;
			} catch ( err ) {
				return null;
			}
		}
		function weekday( tz ) {
			var name = '';
			try {
				name = new Intl.DateTimeFormat( 'en-US', { timeZone: tz || undefined, weekday: 'short' } ).format( new Date() );
			} catch ( err ) {
				name = '';
			}
			var i = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ].indexOf( name );
			return i < 0 ? ( new Date().getDay() + 6 ) % 7 : i;
		}
		function matches( el ) {
			var tags = ( el.getAttribute( 'data-ppn-tags' ) || '' ).split( ' ' );
			return Object.keys( active ).every( function ( key ) {
				if ( key === 'open' ) {
					var data = hoursOf( el );
					return !! data && ppnOpenStatus( data, new Date() ).state === 'open';
				}
				if ( key === 'tonight' ) {
					var days = ( el.getAttribute( 'data-ppn-league-days' ) || '' ).split( ' ' ).filter( Boolean ).map( Number );
					var data2 = hoursOf( el );
					return days.indexOf( weekday( data2 && data2.tz ) ) >= 0;
				}
				return tags.indexOf( key ) >= 0;
			} );
		}
		function apply() {
			var shown = 0;
			sections.forEach( function ( section ) {
				var ok = matches( section );
				shown += ok ? 1 : 0;
				root.querySelectorAll( '[data-ppn-venue="' + section.getAttribute( 'data-ppn-venue' ) + '"]' ).forEach( function ( el ) {
					el.hidden = ! ok;
				} );
			} );
			var count = filters && filters.querySelector( '.ppn-city-count' );
			if ( count ) {
				count.textContent = Object.keys( active ).length ? 'Showing ' + shown + ' of ' + sections.length + ' places.' : '';
			}
		}
		if ( filters ) {
			filters.hidden = false;
			filters.addEventListener( 'click', function ( e ) {
				var chip = e.target.closest( '[data-ppn-filter]' );
				if ( ! chip ) {
					return;
				}
				var key = chip.getAttribute( 'data-ppn-filter' );
				var on = chip.getAttribute( 'aria-pressed' ) !== 'true';
				chip.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				if ( on ) {
					active[ key ] = true;
				} else {
					delete active[ key ];
				}
				apply();
			} );
		}

		var sortCol = root.querySelector( '[data-ppn-sort-col]' );
		var sortBtn = sortCol && sortCol.querySelector( '[data-ppn-sort]' );
		if ( sortBtn ) {
			var label = sortCol.querySelector( '[data-ppn-sort-label]' );
			if ( label ) {
				label.hidden = true;
			}
			sortBtn.hidden = false;
			sortBtn.addEventListener( 'click', function () {
				var asc = sortCol.getAttribute( 'aria-sort' ) === 'descending';
				var body = root.querySelector( '.ppn-city-table tbody' );
				var rows = Array.prototype.slice.call( body.rows );
				rows.sort( function ( a, b ) {
					var ta = parseInt( a.getAttribute( 'data-ppn-tables' ), 10 );
					var tb = parseInt( b.getAttribute( 'data-ppn-tables' ), 10 );
					if ( ta < 0 || tb < 0 ) {
						return ta < 0 && tb < 0 ? a.getAttribute( 'data-ppn-order' ) - b.getAttribute( 'data-ppn-order' ) : ( ta < 0 ? 1 : -1 );
					}
					return asc ? ta - tb : tb - ta;
				} );
				rows.forEach( function ( row ) {
					body.appendChild( row );
				} );
				sortCol.setAttribute( 'aria-sort', asc ? 'ascending' : 'descending' );
			} );
		}

		var show = root.querySelector( '[data-ppn-show-map]' );
		var box = root.querySelector( '[data-ppn-city-map]' );
		var held = document.getElementById( 'ppn-maps' );
		if ( ! show || ! box || ! held ) {
			return;
		}
		show.addEventListener( 'click', function ( e ) {
			var srcs = [];
			try {
				srcs = JSON.parse( held.textContent || '[]' );
			} catch ( err ) {
				srcs = [];
			}
			if ( ! srcs.length ) {
				return;
			}
			e.preventDefault();
			show.hidden = true;
			box.hidden = false;
			if ( ! ( window.google && google.maps && google.maps.Map ) ) {
				srcs.forEach( function ( src ) {
					var s = document.createElement( 'script' );
					s.src = src;
					s.async = false;
					document.head.appendChild( s );
				} );
			}
			var tries = 0;
			var timer = setInterval( function () {
				tries++;
				if ( ! ( window.google && google.maps && google.maps.Map ) ) {
					if ( tries > 150 ) {
						clearInterval( timer );
						location.href = show.href;
					}
					return;
				}
				clearInterval( timer );
				var points = JSON.parse( box.getAttribute( 'data-points' ) || '[]' );
				var map = new google.maps.Map( box, { center: { lat: points[ 0 ].lat, lng: points[ 0 ].lng }, zoom: 12, mapTypeControl: false, streetViewControl: false } );
				var bounds = new google.maps.LatLngBounds();
				points.forEach( function ( p ) {
					var marker = new google.maps.Marker( { position: { lat: p.lat, lng: p.lng }, map: map, title: p.n } );
					bounds.extend( marker.getPosition() );
					marker.addListener( 'click', function () {
						location.hash = p.a;
					} );
				} );
				if ( points.length > 1 ) {
					map.fitBounds( bounds );
				}
			}, 100 );
		} );
	}() );

	/*
	 * Homepage "Play tonight" panel (Tonight). Loads every venue's hours and place in
	 * one cached request, shows how many rooms are open now, and lists the best three
	 * picks near the visitor or in a chosen city. Once it works, the theme's search
	 * form in the hero is hidden; without JavaScript that form stays.
	 */
	( function () {
		var panel = document.querySelector( '[data-ppn-tonight]' );
		if ( ! panel || ! window.fetch || ! window.Intl ) {
			return;
		}
		var RADIUS = 50;
		var BALLS = 3;
		var $ = function ( sel ) { return panel.querySelector( sel ); };
		var live = $( '[data-ppn-tn-live]' );
		var count = $( '[data-ppn-tn-count]' );
		var controls = $( '[data-ppn-tn-controls]' );
		var locate = $( '[data-ppn-tn-locate]' );
		var select = $( '[data-ppn-tn-city]' );
		var msg = $( '[data-ppn-tn-msg]' );
		var list = $( '[data-ppn-tn-list]' );
		var more = $( '[data-ppn-tn-more]' );
		var data = null;
		var last = null;

		function el( tag, cls, text ) {
			var n = document.createElement( tag );
			if ( cls ) {
				n.className = cls;
			}
			if ( text ) {
				n.textContent = text;
			}
			return n;
		}
		function link( href, cls, text, event ) {
			var a = el( 'a', cls, text );
			a.href = href;
			if ( event ) {
				a.setAttribute( 'data-ppn-event', event );
			}
			if ( event === 'directions' ) {
				a.target = '_blank';
				a.rel = 'noopener';
			}
			return a;
		}
		function say( text ) {
			msg.textContent = text || '';
		}
		function cityOf( key ) {
			for ( var i = 0; i < data.c.length; i++ ) {
				if ( data.c[ i ].k === key ) {
					return data.c[ i ];
				}
			}
			return null;
		}
		function distance( m ) {
			return m < 0.95 ? 'Under a mile' : ( m < 10 ? m.toFixed( 1 ) : Math.round( m ) ) + ' mi';
		}

		function updateCount() {
			var n = ppnOpenCount( data.v, new Date() );
			count.innerHTML = '';
			count.appendChild( el( 'strong', '', String( n ) ) );
			count.appendChild( document.createTextNode( ' of ' + data.v.length + ( n === 1 ? ' room is' : ' rooms are' ) + ' open right now' ) );
			live.hidden = false;
		}

		function row( pick, i ) {
			var v = pick.v;
			var li = el( 'li', 'ppn-tn-row' );
			li.setAttribute( 'data-listing-id', String( v.i ) );
			li.appendChild( el( 'span', 'ppn-tn-ball ppn-tn-ball--' + ( i + 1 ), String( i + 1 ) ) ).setAttribute( 'aria-hidden', 'true' );
			var body = el( 'div', 'ppn-tn-body' );
			body.appendChild( link( v.u, 'ppn-tn-name', v.n ) );
			var meta = el( 'p', 'ppn-tn-meta' );
			if ( pick.status.label ) {
				meta.appendChild( el( 'span', 'ppn-tn-status ppn-tn-status--' + pick.status.state, pick.status.label ) );
			}
			if ( pick.miles !== null ) {
				meta.appendChild( el( 'span', 'ppn-tn-dist', distance( pick.miles ) ) );
			}
			body.appendChild( meta );
			var facts = [];
			if ( v.t ) {
				facts.push( v.t + ( v.t === 1 ? ' table' : ' tables' ) );
			}
			if ( v.z ) {
				facts.push( v.z );
			}
			if ( facts.length || pick.league ) {
				var f = el( 'p', 'ppn-tn-facts', facts.join( ', ' ) );
				if ( pick.league ) {
					f.appendChild( el( 'span', 'ppn-tn-league', 'League night tonight' ) );
				}
				body.appendChild( f );
			}
			li.appendChild( body );
			var actions = el( 'p', 'ppn-tn-actions' );
			if ( v.p ) {
				actions.appendChild( link( 'tel:' + v.p, 'ppn-tn-btn', 'Call', 'call' ) ).setAttribute( 'aria-label', 'Call ' + v.n );
			}
			if ( v.d ) {
				actions.appendChild( link( v.d, 'ppn-tn-btn ppn-tn-btn--ghost', 'Directions', 'directions' ) ).setAttribute( 'aria-label', 'Directions to ' + v.n );
			}
			if ( actions.childNodes.length ) {
				li.appendChild( actions );
			}
			return li;
		}

		function show( picks, footer ) {
			list.innerHTML = '';
			picks.forEach( function ( p, i ) { list.appendChild( row( p, i ) ); } );
			list.hidden = ! picks.length;
			more.innerHTML = '';
			if ( footer ) {
				more.appendChild( footer );
			}
			more.hidden = ! footer;
		}

		function render() {
			if ( ! last ) {
				return;
			}
			var now = new Date();
			if ( last.city ) {
				var c = cityOf( last.city );
				var picks = ppnTonightRank( data.v, { city: last.city, now: now, limit: BALLS } );
				var total = data.v.filter( function ( v ) { return v.k === last.city; } ).length;
				say( picks.length && picks[ 0 ].status.state === 'closed' ? 'Everything in ' + c.n + ' is closed right now. Here is who opens first.' : '' );
				show( picks, c && c.u ? link( c.u, 'ppn-tn-all', total > picks.length ? 'See all ' + total + ' places in ' + c.n : 'More about playing pool in ' + c.n ) : null );
				return;
			}
			var near = ppnTonightRank( data.v, { lat: last.lat, lng: last.lng, now: now, radius: RADIUS, limit: BALLS } );
			if ( ! near.length ) {
				var closest = null;
				data.v.forEach( function ( v ) {
					var m = miles( last.lat, last.lng, v.a, v.o );
					if ( ! closest || m < closest.m ) {
						closest = { v: v, m: m };
					}
				} );
				var where = closest ? cityOf( closest.v.k ) : null;
				say( 'Nothing on our map within ' + RADIUS + ' miles of you yet.' + ( closest ? ' The closest is ' + closest.v.n + ', ' + Math.round( closest.m ) + ' miles away' + ( where ? ' in ' + where.n : '' ) + '.' : '' ) );
				show( [], link( '/add-a-venue/', 'ppn-tn-all', 'Know a place near you? Add it' ) );
				return;
			}
			say( near[ 0 ].status.state === 'closed' ? 'Nothing near you is open right now. Here is who opens first.' : '' );
			var map = new URL( cfg.exploreUrl || '/', location.href );
			map.searchParams.set( 'type', 'place' );
			map.searchParams.set( 'search_location', 'Current location' );
			map.searchParams.set( 'lat', last.lat.toFixed( 5 ) );
			map.searchParams.set( 'lng', last.lng.toFixed( 5 ) );
			map.searchParams.set( 'proximity', String( RADIUS ) );
			map.searchParams.set( 'sort', 'nearby' );
			show( near, link( map.toString(), 'ppn-tn-all', 'See everything near you on the map' ) );
		}

		function findMe( quiet ) {
			if ( ! navigator.geolocation ) {
				say( 'Your browser can\'t share a location. Pick a city instead.' );
				return;
			}
			locate.setAttribute( 'aria-busy', 'true' );
			if ( ! quiet ) {
				say( 'Finding tables near you…' );
			}
			navigator.geolocation.getCurrentPosition( function ( pos ) {
				locate.removeAttribute( 'aria-busy' );
				select.value = '';
				last = { lat: pos.coords.latitude, lng: pos.coords.longitude };
				render();
			}, function ( err ) {
				locate.removeAttribute( 'aria-busy' );
				if ( ! quiet ) {
					say( err && err.code === 1 ? 'Location sharing is off. Pick a city instead.' : 'We couldn\'t get your location. Pick a city instead.' );
				}
			}, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 } );
		}

		function start( json ) {
			if ( ! json || ! Array.isArray( json.v ) || ! json.v.length ) {
				return;
			}
			data = json;
			var counts = {};
			data.v.forEach( function ( v ) { counts[ v.k ] = ( counts[ v.k ] || 0 ) + 1; } );
			data.c.forEach( function ( c ) {
				if ( counts[ c.k ] ) {
					var opt = el( 'option', '', c.n + ' (' + counts[ c.k ] + ')' );
					opt.value = String( c.k );
					select.appendChild( opt );
				}
			} );
			controls.hidden = false;
			var hero = panel.closest( '.ppn-home-hero' );
			if ( hero ) {
				hero.classList.add( 'ppn-tn-ready' );
			}
			updateCount();
			setInterval( function () {
				if ( ! document.hidden ) {
					updateCount();
					render();
				}
			}, 60000 );
			locate.addEventListener( 'click', function () { findMe( false ); } );
			select.addEventListener( 'change', function () {
				var key = parseInt( select.value, 10 );
				if ( key ) {
					last = { city: key };
					render();
				}
			} );
			if ( navigator.permissions && navigator.permissions.query ) {
				navigator.permissions.query( { name: 'geolocation' } ).then( function ( p ) {
					if ( p.state === 'granted' && ! last ) {
						findMe( true );
					}
				} ).catch( function () {} );
			}
		}

		panel.setAttribute( 'data-ppn-where', 'tonight' );
		fetch( panel.getAttribute( 'data-ppn-tonight' ), { credentials: 'omit' } )
			.then( function ( r ) { return r.ok ? r.json() : null; } )
			.then( start )
			.catch( function () {} );
	}() );

	/*
	 * Listing clicks and venue page views (Tracking). Links carry data-ppn-event; the
	 * theme's Save buttons are matched by class because cards from "Load more" are
	 * built outside the page markup. One beacon per click, no cookies. The landing
	 * referrer's host is kept for this tab only, so later pages keep their source.
	 * Browsers under automation (navigator.webdriver: PageSpeed, Lighthouse, test
	 * scripts) send nothing; they present ordinary phone and desktop user agents.
	 */
	( function () {
		var track = cfg.track;
		if ( ! track || ! track.url || navigator.webdriver ) {
			return;
		}
		var SAVE = '.c27-bookmark-button, .mylisting-bookmark-item';
		var CARD = '.lf-item-container';
		var ref = '';
		try {
			var host = document.referrer ? new URL( document.referrer ).hostname : '';
			if ( host && host !== location.hostname ) {
				sessionStorage.setItem( 'ppnRef', host );
			}
			ref = sessionStorage.getItem( 'ppnRef' ) || '';
		} catch ( err ) {
			ref = '';
		}
		function send( type, venue, where ) {
			var body = new URLSearchParams( {
				venue: String( venue ),
				type: type,
				where: where,
				path: location.pathname,
				device: window.innerWidth < 768 ? 'mobile' : 'desktop',
				ref: ref
			} );
			if ( navigator.sendBeacon && navigator.sendBeacon( track.url, body ) ) {
				return;
			}
			fetch( track.url, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' } ).catch( function () {} );
		}
		function venueOf( el ) {
			var holder = el.closest( '[data-listing-id]' );
			if ( holder ) {
				return parseInt( holder.getAttribute( 'data-listing-id' ), 10 ) || 0;
			}
			var card = el.closest( CARD );
			if ( card ) {
				return parseInt( ( card.getAttribute( 'data-id' ) || '' ).replace( 'listing-id-', '' ), 10 ) || 0;
			}
			var claim = /[?&]listing_id=(\d+)/.exec( el.getAttribute( 'href' ) || '' );
			return claim ? parseInt( claim[ 1 ], 10 ) : track.venue;
		}
		function whereOf( el, venue ) {
			var zone = el.closest( '[data-ppn-where]' );
			if ( zone ) {
				return zone.getAttribute( 'data-ppn-where' );
			}
			if ( track.page === 'my_pool' ) {
				return 'my_pool';
			}
			if ( track.page === 'city' ) {
				return 'city_page';
			}
			if ( track.page === 'hub' ) {
				return 'hub_page';
			}
			return track.page === 'venue' && venue === track.venue && ! el.closest( CARD ) ? 'venue_page' : 'card';
		}
		function onClick( e ) {
			if ( e.type === 'auxclick' && e.button !== 1 ) {
				return;
			}
			var el = e.target.closest && e.target.closest( '[data-ppn-event], ' + SAVE );
			if ( ! el ) {
				return;
			}
			var type = el.getAttribute( 'data-ppn-event' ) || 'save';
			if ( el.tagName === 'SUMMARY' && el.parentNode.open ) {
				return;
			}
			var venue = venueOf( el );
			if ( venue ) {
				send( type, venue, whereOf( el, venue ) );
			}
		}
		document.addEventListener( 'click', onClick, true );
		document.addEventListener( 'auxclick', onClick, true );
		if ( track.page === 'venue' && track.venue ) {
			var view = function () {
				send( 'view', track.venue, 'venue_page' );
			};
			if ( document.readyState === 'complete' ) {
				setTimeout( view, 0 );
			} else {
				window.addEventListener( 'load', function () {
					setTimeout( view, 0 );
				} );
			}
		}
	}() );

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
