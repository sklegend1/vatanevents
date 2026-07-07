/**
 * Vatan Event — AJAX search.
 *
 * Listens for input on `[data-vatan-search]` forms, debounces 300ms, then
 * calls GET /wp-json/vatan/v1/events with q/city/category/date params.
 * Results render into the sibling `[data-vatan-search-results]` element
 * as a dropdown. All user-supplied content goes through textContent — no
 * innerHTML interpolation, so titles/venues from the API can't inject HTML.
 */
( function () {
	'use strict';

	const data = window.vatanData || {};
	const i18n = data.i18n || {
		searching:    'Searching…',
		noResults:    'No events found.',
		searchFailed: 'Search failed. Please try again.',
	};

	const DEBOUNCE_MS = 300;
	const MIN_CHARS   = 2;

	function debounce( fn, wait ) {
		let t;
		return function ( ...args ) {
			clearTimeout( t );
			t = setTimeout( () => fn.apply( this, args ), wait );
		};
	}

	function buildQuery( form ) {
		const fd = new FormData( form );
		const params = {};
		for ( const [ key, value ] of fd.entries() ) {
			// Skip empty values and the wp_dropdown_categories "0" sentinel for "All".
			if ( value !== '' && value !== '0' && value != null ) {
				params[ key ] = value;
			}
		}
		// Send the current page's Polylang language so REST results are
		// scoped to it. The REST endpoint won't auto-filter without this —
		// language detection in REST context isn't reliable.
		if ( data.lang ) {
			params.lang = data.lang;
		}
		return params;
	}

	function buildUrl( params ) {
		const base = data.restUrl || ( window.location.origin + '/wp-json/vatan/v1/' );
		const url  = new URL( 'events', base.endsWith( '/' ) ? base : base + '/' );
		Object.entries( params ).forEach( ( [ k, v ] ) => url.searchParams.set( k, v ) );
		url.searchParams.set( 'per_page', '8' );
		return url.toString();
	}

	async function fetchEvents( params, signal ) {
		const res = await fetch( buildUrl( params ), {
			headers: { Accept: 'application/json' },
			signal,
			credentials: 'same-origin',
		} );
		if ( ! res.ok ) {
			throw new Error( 'HTTP ' + res.status );
		}
		return res.json();
	}

	function clearChildren( el ) {
		while ( el.firstChild ) el.removeChild( el.firstChild );
	}

	function renderState( container, type, text ) {
		clearChildren( container );
		const div = document.createElement( 'div' );
		div.className = 'search-results__' + type;
		div.textContent = text;
		container.appendChild( div );
		container.classList.add( 'search-results--open' );
	}

	function renderResults( container, items ) {
		clearChildren( container );

		if ( ! items.length ) {
			renderState( container, 'empty', i18n.noResults );
			return;
		}

		items.forEach( ( item ) => {
			const a = document.createElement( 'a' );
			a.className = 'search-result-item';
			a.href = item.permalink || '#';

			if ( item.thumbnail ) {
				const img = document.createElement( 'img' );
				img.className = 'search-result-item__thumb';
				img.src = item.thumbnail;
				img.alt = '';
				img.loading = 'lazy';
				a.appendChild( img );
			}

			const body = document.createElement( 'div' );
			body.className = 'search-result-item__body';

			const title = document.createElement( 'span' );
			title.className = 'search-result-item__title';
			title.textContent = item.title || '';
			body.appendChild( title );

			const metaParts = [ item.venue, item.city, item.date ].filter( Boolean );
			if ( metaParts.length ) {
				const meta = document.createElement( 'span' );
				meta.className = 'search-result-item__meta';
				meta.textContent = metaParts.join( ' · ' );
				body.appendChild( meta );
			}

			a.appendChild( body );
			container.appendChild( a );
		} );

		container.classList.add( 'search-results--open' );
	}

	function close( container ) {
		container.classList.remove( 'search-results--open' );
	}

	function attach( form ) {
		const queryInput = form.querySelector( '[name="q"]' );
		const results    = form.parentElement && form.parentElement.querySelector( '[data-vatan-search-results]' );
		if ( ! queryInput || ! results ) return;

		let abortCtrl = null;

		async function run() {
			const params    = buildQuery( form );
			const q         = ( params.q || '' ).trim();
			const hasFilter = Boolean( params.city || params.country || params.category || params.date );

			// Nothing to search on yet.
			if ( ! q && ! hasFilter ) {
				close( results );
				return;
			}

			// Min-char gate, but allow filter-only queries through.
			if ( q && q.length < MIN_CHARS && ! hasFilter ) {
				close( results );
				return;
			}

			// Cancel an in-flight request if the user keeps typing.
			if ( abortCtrl ) {
				abortCtrl.abort();
			}
			abortCtrl = new AbortController();

			renderState( results, 'loading', i18n.searching );

			try {
				const items = await fetchEvents( params, abortCtrl.signal );
				renderResults( results, items );
			} catch ( err ) {
				if ( err && err.name === 'AbortError' ) return;
				renderState( results, 'error', i18n.searchFailed );
			}
		}

		const debouncedRun = debounce( run, DEBOUNCE_MS );

		// Text input → debounced.
		queryInput.addEventListener( 'input', debouncedRun );

		// Filter changes (city / category / date) → fire immediately.
		form.querySelectorAll( 'select, input[type="date"]' ).forEach( ( el ) => {
			el.addEventListener( 'change', run );
		} );

		// Submit → fire immediately, no full-page reload.
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			run();
		} );

		// Click outside → close the dropdown.
		document.addEventListener( 'click', function ( e ) {
			if ( ! form.contains( e.target ) && ! results.contains( e.target ) ) {
				close( results );
			}
		} );

		// Escape key → close.
		queryInput.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) close( results );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-vatan-search]' ).forEach( attach );
	} );
} )();
