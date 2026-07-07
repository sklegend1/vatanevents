/**
 * Vatan Event — seat map.
 *
 * The `SeatMap` class hydrates a [data-vatan-seat-map] container on page load
 * by fetching its config from GET /wp-json/vatan/v1/seats/{event_id}.
 *
 * Public API (also exposed as window.Vatan.SeatMap):
 *   const map = new SeatMap( containerEl );
 *   map.load();          // fetch + render via REST
 *   map.render( config ); // render directly from a config object
 *
 * Config shape:
 *   {
 *     rows: 7,
 *     cols: 10,
 *     sections: [
 *       { rows: [1,2], type: "economy", price: 850000, color: "#06B6D4" },
 *       { seats: ["1-5","T1-3"], type: "vip", price: 3500000, color: "#FF2D78" },
 *       …
 *     ],
 *     reserved: ["1-5", "2-3", "T1-1", …],
 *     hallways: ["4-6", "4-7"],                    // grid cells that are empty space
 *     tables: [
 *       { id: "T1", seats: 8, label: "Table 1",
 *         type: "vip", price: 3500000, color: "#FF2D78" }
 *     ]
 *   }
 *
 * Security: every piece of API-returned text goes through `textContent`. The
 * only attribute set from server data is `style.background` for color dots,
 * which gets a hex color (already sanitized server-side via sanitize_hex_color).
 */
( function () {
	'use strict';

	const data = window.vatanData || {};
	const i18n = Object.assign(
		{
			seatReserved:    'Reserved',
			seatPanelEmpty:  'No seats selected yet.',
			seatRow:         'Row',
			seatRemove:      'Remove',
			seatLoadFailed:  'Could not load seat map.',
			seatNone:        'No seat map configured for this event.',
			seatMaxReached:  'You can select up to %d seats.',
			seatEconomy:     'Economy',
			seatSpecial:     'Special (CIP)',
			addToCartFailed: 'Could not add tickets to cart.',
			addToCartOk:     'Tickets reserved.',
			working:         'Working…',
		},
		data.i18n || {}
	);

	const localeIsFa = typeof data.locale === 'string' && data.locale.indexOf( 'fa' ) === 0;

	function toPersianDigits( value ) {
		if ( ! localeIsFa ) return String( value );
		const fa = '۰۱۲۳۴۵۶۷۸۹';
		return String( value ).replace( /[0-9]/g, ( d ) => fa[ +d ] );
	}

	function formatPrice( amount ) {
		const localeForIntl = ( data.locale || 'en-US' ).replace( '_', '-' );
		const decimals = Number.isFinite( data.priceDecimals ) ? data.priceDecimals : 2;
		const value = Number( amount ) || 0;
		let num;
		try {
			num = new Intl.NumberFormat( localeForIntl, {
				minimumFractionDigits: decimals,
				maximumFractionDigits: decimals,
			} ).format( value );
		} catch ( e ) {
			num = value.toFixed( decimals );
		}
		num = toPersianDigits( num );
		const symbol = data.currencySymbol || '';
		switch ( data.currencyPosition ) {
			case 'right':       return num + symbol;
			case 'left_space':  return symbol + ' ' + num;
			case 'right_space': return num + ' ' + symbol;
			default:            return symbol + num; // 'left'
		}
	}

	function clearChildren( el ) {
		while ( el && el.firstChild ) el.removeChild( el.firstChild );
	}

	function translateSectionType( type ) {
		switch ( type ) {
			case 'economy': return i18n.seatEconomy;
			case 'special': return i18n.seatSpecial;
			case 'vip':     return 'VIP';
			default:        return type || '';
		}
	}

	class SeatMap {
		constructor( container ) {
			this.root      = container;
			this.eventId   = parseInt( container.getAttribute( 'data-event-id' ), 10 ) || 0;
			this.maxSeats  = parseInt( container.getAttribute( 'data-max-seats' ) || data.maxSelectableSeats || '10', 10 );
			this.taxRate   = parseFloat( container.getAttribute( 'data-tax-rate' ) || data.taxRate || '0.09' );

			this.legendEl    = container.querySelector( '[data-vatan-seat-legend]' );
			this.gridEl      = container.querySelector( '[data-vatan-seat-grid]' );
			this.selectionEl = container.querySelector( '[data-vatan-seat-selection]' );
			this.basePriceEl = container.querySelector( '[data-vatan-seat-base]' );
			this.taxEl       = container.querySelector( '[data-vatan-seat-tax]' );
			this.totalEl     = container.querySelector( '[data-vatan-seat-total]' );
			this.cartBtn     = container.querySelector( '[data-vatan-add-to-cart]' );

			this.config   = null;
			this.selected = new Map(); // 'r-c' -> { row, col, section, price }

			if ( this.cartBtn ) {
				this.cartBtn.addEventListener( 'click', () => this.addToCart() );
			}
		}

		async load() {
			if ( ! this.eventId ) {
				this.showStatus( i18n.seatNone, 'empty' );
				this.updatePanel();
				return;
			}
			const url = ( data.restUrl || '/wp-json/vatan/v1/' ) + 'seats/' + encodeURIComponent( this.eventId );
			try {
				const res = await fetch( url, {
					headers: { Accept: 'application/json' },
					credentials: 'same-origin',
				} );
				if ( ! res.ok ) {
					if ( res.status === 404 ) {
						this.showStatus( i18n.seatNone, 'empty' );
					} else {
						this.showStatus( i18n.seatLoadFailed, 'error' );
					}
					this.updatePanel();
					return;
				}
				const config = await res.json();
				this.render( config );
			} catch ( e ) {
				this.showStatus( i18n.seatLoadFailed, 'error' );
				this.updatePanel();
			}
		}

		showStatus( message, type ) {
			if ( ! this.gridEl ) return;
			clearChildren( this.gridEl );
			const p = document.createElement( 'p' );
			p.className = 'seat-map__' + ( type || 'loading' );
			p.textContent = message;
			this.gridEl.appendChild( p );

			if ( this.legendEl ) {
				clearChildren( this.legendEl );
			}
		}

		render( raw ) {
			this.config = this.normalizeConfig( raw );
			this.selected.clear();

			const hasGrid   = this.config.rows && this.config.cols;
			const hasTables = this.config.tables && this.config.tables.length;
			if ( ! hasGrid && ! hasTables ) {
				this.showStatus( i18n.seatNone, 'empty' );
				this.updatePanel();
				return;
			}

			this.drawLegend();
			if ( this.gridEl ) {
				clearChildren( this.gridEl );
				this.gridEl.classList.remove( 'seat-map__grid--has-tables' );
			}
			if ( hasGrid )   this.drawGrid();
			if ( hasTables ) this.drawTables();
			this.updatePanel();
		}

		normalizeConfig( raw ) {
			raw = raw || {};
			const rows     = parseInt( raw.rows, 10 ) || 0;
			const cols     = parseInt( raw.cols, 10 ) || 0;
			const sections = Array.isArray( raw.sections ) ? raw.sections : [];

			// A valid seat key is either grid ("5-3") or table ("T1-5").
			const SEAT_KEY_RE = /^(\d+-\d+|T[A-Za-z0-9_-]*-\d+)$/;

			const reserved = new Set();
			( Array.isArray( raw.reserved ) ? raw.reserved : [] ).forEach( ( k ) => {
				if ( typeof k === 'string' && SEAT_KEY_RE.test( k ) ) {
					reserved.add( k );
				}
			} );

			// Seats currently held by THIS shopper (in their cart). These
			// are excluded from `reserved` server-side so the user can see
			// them as "their own" — we render them with a distinct visual
			// state so the buyer knows the seat is in their cart.
			const mine = new Set();
			( Array.isArray( raw.mine ) ? raw.mine : [] ).forEach( ( k ) => {
				if ( typeof k === 'string' && SEAT_KEY_RE.test( k ) ) {
					mine.add( k );
				}
			} );

			const hallways = new Set();
			( Array.isArray( raw.hallways ) ? raw.hallways : [] ).forEach( ( k ) => {
				if ( typeof k === 'string' && /^\d+-\d+$/.test( k ) ) {
					hallways.add( k );
				}
			} );

			// Two indexes — per-seat (preferred, written by the GUI editor) and
			// per-row (legacy schema). createSeat checks per-seat first.
			const seatSection = new Map();
			const rowSection  = new Map();
			sections.forEach( ( section ) => {
				if ( ! section ) return;
				if ( Array.isArray( section.seats ) ) {
					section.seats.forEach( ( key ) => {
						if ( typeof key === 'string' && SEAT_KEY_RE.test( key ) ) {
							seatSection.set( key, section );
						}
					} );
				}
				if ( Array.isArray( section.rows ) ) {
					section.rows.forEach( ( r ) => {
						rowSection.set( parseInt( r, 10 ), section );
					} );
				}
			} );

			// Tables — each one has its own default section (so a seat that's
			// not explicitly listed in any `sections` entry still gets a
			// price/color from the table itself). Tables are organised by
			// `row` (1, 2, 3…) into lanes that sit below the seat grid.
			const tables = ( Array.isArray( raw.tables ) ? raw.tables : [] )
				.map( ( t ) => {
					if ( ! t || typeof t.id !== 'string' || ! /^T[A-Za-z0-9_-]*$/.test( t.id ) ) return null;
					const seatCount = Math.max( 2, Math.min( 20, parseInt( t.seats, 10 ) || 0 ) );
					if ( seatCount < 2 ) return null;
					const row = Math.max( 1, Math.min( 10, parseInt( t.row, 10 ) || 1 ) );
					return {
						id:    t.id,
						seats: seatCount,
						label: t.label || t.id,
						type:  t.type || '',
						price: parseFloat( t.price ) || 0,
						color: t.color || '',
						row:   row,
					};
				} )
				.filter( Boolean );

			return { rows, cols, sections, reserved, mine, hallways, seatSection, rowSection, tables };
		}

		drawLegend() {
			if ( ! this.legendEl ) return;
			clearChildren( this.legendEl );

			// Reserved entry — always shown, regardless of sections.
			const reservedItem = document.createElement( 'span' );
			reservedItem.className = 'seat-map__legend-item seat-map__legend-item--reserved';
			const xMark = document.createElement( 'i' );
			xMark.className = 'seat-map__legend-x';
			xMark.setAttribute( 'aria-hidden', 'true' );
			xMark.textContent = '×';
			reservedItem.appendChild( xMark );
			reservedItem.appendChild( document.createTextNode( ' ' + i18n.seatReserved ) );
			this.legendEl.appendChild( reservedItem );

			// Sections — color dot + type + price.
			this.config.sections.forEach( ( section ) => {
				const item = document.createElement( 'span' );
				item.className = 'seat-map__legend-item';

				const dot = document.createElement( 'i' );
				dot.className = 'seat-map__legend-dot';
				dot.setAttribute( 'aria-hidden', 'true' );
				if ( section.color ) {
					dot.style.background = section.color;
				}
				item.appendChild( dot );

				const label = document.createElement( 'span' );
				const typeName = translateSectionType( section.type );
				const priceText = formatPrice( section.price );
				label.textContent = typeName + ' (' + priceText + ')';
				item.appendChild( label );

				this.legendEl.appendChild( item );
			} );
		}

		drawGrid() {
			if ( ! this.gridEl ) return;

			const inner = document.createElement( 'div' );
			inner.className = 'seat-map__grid-inner';
			inner.style.setProperty( '--seat-cols', String( this.config.cols ) );

			for ( let r = 1; r <= this.config.rows; r++ ) {
				for ( let c = 1; c <= this.config.cols; c++ ) {
					inner.appendChild( this.createSeat( r, c ) );
				}
			}
			this.gridEl.appendChild( inner );
		}

		createSeat( row, col ) {
			const key      = row + '-' + col;

			// Hallway: render as an invisible spacer so the grid keeps its
			// shape but there's no seat (no count, no click target).
			if ( this.config.hallways.has( key ) ) {
				const spacer = document.createElement( 'span' );
				spacer.className = 'seat seat--hallway';
				spacer.setAttribute( 'aria-hidden', 'true' );
				return spacer;
			}

			// Per-seat assignment wins over per-row.
			const section  = this.config.seatSection.get( key ) || this.config.rowSection.get( row );
			const reserved = this.config.reserved.has( key );
			const mine     = this.config.mine && this.config.mine.has( key );

			const btn = document.createElement( 'button' );
			btn.type            = 'button';
			btn.className       = 'seat';
			btn.dataset.seat    = key;
			btn.dataset.row     = String( row );
			btn.dataset.col     = String( col );
			btn.textContent     = toPersianDigits( row + '.' + col );
			btn.setAttribute( 'aria-label', i18n.seatRow + ' ' + row + ' / ' + col );

			if ( mine ) {
				// Seat is in this shopper's cart — visually confirm + lock.
				btn.classList.add( 'seat--mine' );
				if ( section && section.color ) {
					btn.style.setProperty( '--seat-color', section.color );
				}
				btn.disabled = true;
				btn.setAttribute( 'aria-disabled', 'true' );
				btn.setAttribute( 'aria-label', ( i18n.seatInCart || 'In your cart' ) + ' — ' + i18n.seatRow + ' ' + row + ' / ' + col );
				btn.title = i18n.seatInCart || 'In your cart';
			} else if ( reserved ) {
				btn.classList.add( 'seat--reserved' );
				btn.disabled = true;
				btn.setAttribute( 'aria-disabled', 'true' );
				btn.setAttribute( 'aria-label', i18n.seatReserved + ' — ' + i18n.seatRow + ' ' + row + ' / ' + col );
			} else if ( section ) {
				btn.classList.add( 'seat--' + ( section.type || 'unassigned' ) );
				if ( section.color ) {
					btn.style.setProperty( '--seat-color', section.color );
				}
				btn.addEventListener( 'click', () => this.toggleSeat( row, col, section ) );
			} else {
				btn.classList.add( 'seat--unassigned' );
				btn.disabled = true;
			}

			if ( this.selected.has( key ) ) {
				btn.classList.add( 'seat--selected' );
			}

			return btn;
		}

		drawTables() {
			if ( ! this.gridEl ) return;

			// Group tables by their `row` lane (1, 2, 3…). Each lane gets
			// its own flex container below the seat grid — overlap with the
			// grid is impossible because lanes are appended after it.
			const byRow = new Map();
			this.config.tables.forEach( ( table ) => {
				const r = table.row || 1;
				if ( ! byRow.has( r ) ) byRow.set( r, [] );
				byRow.get( r ).push( table );
			} );

			// Render rows in numeric order — lane 1 nearest the grid.
			const sortedRowKeys = Array.from( byRow.keys() ).sort( ( a, b ) => a - b );

			const lanes = document.createElement( 'div' );
			lanes.className = 'seat-tables';

			sortedRowKeys.forEach( ( r ) => {
				const lane = document.createElement( 'div' );
				lane.className = 'seat-tables__lane';
				lane.dataset.row = String( r );
				byRow.get( r ).forEach( ( table ) => {
					lane.appendChild( this.createTable( table ) );
				} );
				lanes.appendChild( lane );
			} );

			this.gridEl.appendChild( lanes );
			this.gridEl.classList.add( 'seat-map__grid--has-tables' );
		}

		createTable( table ) {
			const card = document.createElement( 'div' );
			card.className = 'seat-table';
			card.dataset.tableId = table.id;

			const inner = document.createElement( 'div' );
			inner.className = 'seat-table__circle';
			if ( table.color ) {
				inner.style.setProperty( '--seat-color', table.color );
			}

			// Position N seats evenly around the circle, starting at the top.
			// Radius 42% (instead of 50%) keeps each seat just inside the
			// table outline so the gap between table edge and seat shrinks
			// and the whole layout reads tighter.
			const radius = 42;
			for ( let i = 1; i <= table.seats; i++ ) {
				const angle    = ( ( 2 * Math.PI * ( i - 1 ) ) / table.seats ) - Math.PI / 2;
				const xPercent = 50 + radius * Math.cos( angle );
				const yPercent = 50 + radius * Math.sin( angle );
				inner.appendChild( this.createTableSeat( table, i, xPercent, yPercent ) );
			}

			const label = document.createElement( 'span' );
			label.className = 'seat-table__label';
			label.textContent = table.label || table.id;
			inner.appendChild( label );

			card.appendChild( inner );
			return card;
		}

		createTableSeat( table, seatN, xPct, yPct ) {
			const key      = table.id + '-' + seatN;
			const explicit = this.config.seatSection.get( key );
			// Use the explicit section if one is assigned; otherwise fall back
			// to the table's own price/color as a "virtual section".
			const section  = explicit || {
				type:  table.type,
				price: table.price,
				color: table.color,
			};
			const reserved = this.config.reserved.has( key );
			const mine     = this.config.mine && this.config.mine.has( key );

			const btn = document.createElement( 'button' );
			btn.type           = 'button';
			btn.className      = 'seat seat-table__seat';
			btn.dataset.seat   = key;
			btn.dataset.table  = table.id;
			btn.dataset.seatN  = String( seatN );
			btn.style.left     = xPct + '%';
			btn.style.top      = yPct + '%';
			btn.textContent    = toPersianDigits( seatN );
			btn.setAttribute( 'aria-label', ( table.label || table.id ) + ' / ' + seatN );

			if ( mine ) {
				btn.classList.add( 'seat--mine' );
				if ( section && section.color ) {
					btn.style.setProperty( '--seat-color', section.color );
				}
				btn.disabled = true;
				btn.setAttribute( 'aria-disabled', 'true' );
				btn.title = i18n.seatInCart || 'In your cart';
			} else if ( reserved ) {
				btn.classList.add( 'seat--reserved' );
				btn.disabled = true;
				btn.setAttribute( 'aria-disabled', 'true' );
			} else if ( section && section.price > 0 ) {
				btn.classList.add( 'seat--' + ( section.type || 'unassigned' ) );
				if ( section.color ) {
					btn.style.setProperty( '--seat-color', section.color );
				}
				btn.addEventListener( 'click', () => this.toggleTableSeat( table, seatN, section ) );
			} else {
				btn.classList.add( 'seat--unassigned' );
				btn.disabled = true;
			}

			if ( this.selected.has( key ) ) {
				btn.classList.add( 'seat--selected' );
			}

			return btn;
		}

		toggleSeat( row, col, section ) {
			const key = row + '-' + col;

			if ( this.selected.has( key ) ) {
				this.selected.delete( key );
			} else {
				if ( this.selected.size >= this.maxSeats ) {
					window.alert( i18n.seatMaxReached.replace( '%d', String( this.maxSeats ) ) );
					return;
				}
				this.selected.set( key, {
					row:     row,
					col:     col,
					section: section,
					price:   parseFloat( section.price ) || 0,
				} );
			}

			this.refreshSeatStyles();
			this.updatePanel();
		}

		toggleTableSeat( table, seatN, section ) {
			const key = table.id + '-' + seatN;

			if ( this.selected.has( key ) ) {
				this.selected.delete( key );
			} else {
				if ( this.selected.size >= this.maxSeats ) {
					window.alert( i18n.seatMaxReached.replace( '%d', String( this.maxSeats ) ) );
					return;
				}
				this.selected.set( key, {
					table:      table.id,
					tableLabel: table.label || table.id,
					seat:       seatN,
					section:    section,
					price:      parseFloat( section.price ) || 0,
				} );
			}

			this.refreshSeatStyles();
			this.updatePanel();
		}

		refreshSeatStyles() {
			if ( ! this.gridEl ) return;
			this.gridEl.querySelectorAll( '.seat' ).forEach( ( seat ) => {
				const key = seat.dataset.seat;
				seat.classList.toggle( 'seat--selected', this.selected.has( key ) );
			} );
		}

		updatePanel() {
			// Selection list.
			if ( this.selectionEl ) {
				clearChildren( this.selectionEl );
				if ( this.selected.size === 0 ) {
					const p = document.createElement( 'p' );
					p.className = 'seat-map__panel-empty';
					p.textContent = i18n.seatPanelEmpty;
					this.selectionEl.appendChild( p );
				} else {
					const list = document.createElement( 'ul' );
					list.className = 'seat-map__selection-list';
					this.selected.forEach( ( info, key ) => {
						list.appendChild( this.renderSelectionItem( key, info ) );
					} );
					this.selectionEl.appendChild( list );
				}
			}

			// Totals.
			let base = 0;
			this.selected.forEach( ( info ) => { base += info.price; } );
			const tax   = base * this.taxRate;
			const total = base + tax;

			if ( this.basePriceEl ) this.basePriceEl.textContent = formatPrice( base );
			if ( this.taxEl )       this.taxEl.textContent       = formatPrice( tax );
			if ( this.totalEl )     this.totalEl.textContent     = formatPrice( total );

			if ( this.cartBtn ) {
				this.cartBtn.disabled = this.selected.size === 0;
			}
		}

		renderSelectionItem( key, info ) {
			const li = document.createElement( 'li' );
			li.className = 'seat-map__selection-item';
			li.dataset.seat = key;

			const dot = document.createElement( 'span' );
			dot.className = 'seat-map__selection-dot';
			if ( info.section && info.section.color ) {
				dot.style.background = info.section.color;
			}
			li.appendChild( dot );

			const meta = document.createElement( 'div' );
			meta.className = 'seat-map__selection-meta';

			const name = document.createElement( 'span' );
			name.className = 'seat-map__selection-name';
			name.textContent = translateSectionType( info.section && info.section.type );
			meta.appendChild( name );

			const seatLabel = document.createElement( 'span' );
			seatLabel.className = 'seat-map__selection-seat';
			if ( info.table ) {
				// Table seat: "Table 1 / seat 5"
				seatLabel.textContent = info.tableLabel + ' / ' + toPersianDigits( info.seat );
			} else {
				seatLabel.textContent = i18n.seatRow + ' ' + toPersianDigits( info.row ) + ' / ' + toPersianDigits( info.row + '.' + info.col );
			}
			meta.appendChild( seatLabel );

			li.appendChild( meta );

			const price = document.createElement( 'span' );
			price.className = 'seat-map__selection-price';
			price.textContent = formatPrice( info.price );
			li.appendChild( price );

			const remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'seat-map__selection-remove';
			remove.setAttribute( 'aria-label', i18n.seatRemove );
			remove.textContent = '×';
			remove.addEventListener( 'click', () => {
				this.selected.delete( key );
				this.refreshSeatStyles();
				this.updatePanel();
			} );
			li.appendChild( remove );

			return li;
		}

		async addToCart() {
			if ( this.selected.size === 0 || ! this.cartBtn ) return;

			const originalLabel = this.cartBtn.textContent;
			this.cartBtn.disabled = true;
			this.cartBtn.textContent = i18n.working;

			const seats = [];
			this.selected.forEach( ( info ) => {
				const base = {
					type:  info.section && info.section.type ? info.section.type : '',
					price: info.price,
				};
				if ( info.table ) {
					seats.push( Object.assign( { table: info.table, seat: info.seat }, base ) );
				} else {
					seats.push( Object.assign( { row: info.row, col: info.col }, base ) );
				}
			} );

			try {
				const res = await fetch( ( data.restUrl || '/wp-json/vatan/v1/' ) + 'add-ticket', {
					method:      'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'Accept':       'application/json',
						'X-WP-Nonce':   data.restNonce || '',
					},
					body: JSON.stringify( {
						event_id: this.eventId,
						seats:    seats,
					} ),
				} );

				const result = await res.json().catch( () => ( {} ) );

				if ( ! res.ok || ! result.success ) {
					throw new Error( ( result && result.message ) || i18n.addToCartFailed );
				}

				if ( result.cart_url ) {
					window.location.href = result.cart_url;
					return; // navigation in progress
				}
				window.alert( i18n.addToCartOk );
			} catch ( err ) {
				window.alert( ( err && err.message ) ? err.message : i18n.addToCartFailed );
			} finally {
				this.cartBtn.disabled = this.selected.size === 0;
				this.cartBtn.textContent = originalLabel;
			}
		}
	}

	// Auto-hydrate on DOMContentLoaded.
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-vatan-seat-map]' ).forEach( function ( container ) {
			const map = new SeatMap( container );
			map.load();
			container.__vatanSeatMap = map;
		} );
	} );

	// Public namespace export.
	window.Vatan = window.Vatan || {};
	window.Vatan.SeatMap = SeatMap;
} )();
