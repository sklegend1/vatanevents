/**
 * Vatan Event — visual seat-map editor (admin only).
 *
 * Hydrates [data-vatan-seat-editor]. Reads its initial state from the
 * sibling <script type="application/json" data-vatan-editor-payload>:
 *   { rows, cols, tiers: [{name, price, color}], config: { sections, reserved },
 *     i18n: {...} }
 *
 * Tools:
 *   • Tier buttons (one per ACF ticket_types tier) — paint that tier's color.
 *   • Reserved — toggle the seat as unsellable (× mark).
 *   • Erase — clear assignment + reserved status.
 *
 * Drag to paint multiple seats. Touch supported. Save serializes back to
 * the JSON schema the front-end SeatMap class consumes.
 */
( function () {
	'use strict';

	class SeatMapEditor {
		constructor( root ) {
			this.root = root;

			const payloadEl = root.querySelector( '[data-vatan-editor-payload]' );
			if ( ! payloadEl ) {
				return;
			}
			let payload;
			try {
				payload = JSON.parse( payloadEl.textContent );
			} catch ( e ) {
				return;
			}

			this.gridEl       = root.querySelector( '[data-vatan-editor-grid]' );
			this.countsEl     = root.querySelector( '[data-vatan-counts]' );
			this.tierBtnsEl   = root.querySelector( '[data-vatan-tier-buttons]' );
			this.rowsCtrl     = root.querySelector( '[data-vatan-rows-control]' );
			this.colsCtrl     = root.querySelector( '[data-vatan-cols-control]' );
			this.rowsInput    = root.querySelector( '[data-vatan-rows-input]' );
			this.colsInput    = root.querySelector( '[data-vatan-cols-input]' );
			this.configInput  = root.querySelector( '[data-vatan-config-input]' );
			this.saveForm     = root.querySelector( '.vatan-seat-editor__save' );
			this.resetBtn     = root.querySelector( '[data-vatan-editor-reset]' );
			this.tablesListEl  = root.querySelector( '[data-vatan-tables-list]' );
			this.tableAddBtn   = root.querySelector( '[data-vatan-table-add]' );
			this.previewEl     = root.querySelector( '[data-vatan-tables-preview]' );
			this.previewGrid   = root.querySelector( '[data-vatan-tables-preview-grid]' );
			this.previewLanes  = root.querySelector( '[data-vatan-tables-preview-lanes]' );

			this.rows  = parseInt( payload.rows, 10 ) || 5;
			this.cols  = parseInt( payload.cols, 10 ) || 8;
			this.tiers = Array.isArray( payload.tiers ) ? payload.tiers : [];
			this.i18n  = payload.i18n || {};

			// 'r-c' -> tier index
			this.seatStates  = new Map();
			this.reserved    = new Set();
			this.hallways    = new Set();    // Set<'r-c'>
			this.tables      = [];           // [{id, label, seats, tierIndex}]
			this.nextTableId = 1;            // for new tables: T1, T2, …

			this.tool        = this.tiers.length ? 'paint' : 'reserved';
			this.currentTier = 0;
			this.painting    = false;
			this.dirty       = false;

			this.hydrateFromConfig( payload.config || {} );
			this.renderToolbar();
			this.renderGrid();
			this.renderTables();
			this.renderPreview();
			this.bindEvents();
			this.refreshActiveButton();
			this.updateCounts();
		}

		/* -------- Initial state -------- */

		hydrateFromConfig( config ) {
			const tierIndexByName = new Map();
			this.tiers.forEach( ( t, i ) => tierIndexByName.set( ( t.name || '' ).toLowerCase(), i ) );

			( config.sections || [] ).forEach( ( section ) => {
				const lookup = ( section.type || '' ).toLowerCase();
				const idx    = tierIndexByName.has( lookup ) ? tierIndexByName.get( lookup ) : -1;
				if ( idx < 0 ) {
					return;
				}
				// Per-seat (preferred)
				if ( Array.isArray( section.seats ) ) {
					section.seats.forEach( ( key ) => {
						if ( typeof key === 'string' && /^\d+-\d+$/.test( key ) ) {
							this.seatStates.set( key, idx );
						}
					} );
				}
				// Legacy per-row support — paint every column on the listed rows.
				if ( Array.isArray( section.rows ) ) {
					section.rows.forEach( ( r ) => {
						const row = parseInt( r, 10 );
						if ( row < 1 || row > this.rows ) return;
						for ( let c = 1; c <= this.cols; c++ ) {
							this.seatStates.set( row + '-' + c, idx );
						}
					} );
				}
			} );

			( config.reserved || [] ).forEach( ( key ) => {
				if ( typeof key === 'string' && /^\d+-\d+$/.test( key ) ) {
					this.reserved.add( key );
				}
			} );

			( config.hallways || [] ).forEach( ( key ) => {
				if ( typeof key === 'string' && /^\d+-\d+$/.test( key ) ) {
					this.hallways.add( key );
				}
			} );

			// Tables — preserve incoming ids so future order/reservation
			// references (e.g. "T1-3") stay valid across re-saves. Reuse the
			// `tierIndexByName` lookup built above for the sections pass.
			( config.tables || [] ).forEach( ( raw ) => {
				if ( ! raw || typeof raw !== 'object' ) return;
				const id = typeof raw.id === 'string' ? raw.id : '';
				if ( ! /^T[A-Za-z0-9_-]*$/.test( id ) ) return;
				const seats = Math.max( 2, Math.min( 20, parseInt( raw.seats, 10 ) || 0 ) );
				if ( seats < 2 ) return;
				const tierLookup = ( raw.type || '' ).toLowerCase();
				const tierIndex = tierIndexByName.has( tierLookup ) ? tierIndexByName.get( tierLookup ) : 0;
				const row = Math.max( 1, Math.min( 10, parseInt( raw.row, 10 ) || 1 ) );
				this.tables.push( {
					id:        id,
					label:     raw.label || id,
					seats:     seats,
					tierIndex: tierIndex,
					row:       row,
				} );
				// Advance the next-id counter past any numeric T-id we saw.
				const m = id.match( /^T(\d+)$/ );
				if ( m ) {
					const n = parseInt( m[ 1 ], 10 );
					if ( n >= this.nextTableId ) this.nextTableId = n + 1;
				}
			} );
		}

		/* -------- Rendering -------- */

		renderToolbar() {
			this.tierBtnsEl.innerHTML = '';
			if ( this.tiers.length === 0 ) {
				const note = document.createElement( 'span' );
				note.className = 'description';
				note.textContent = this.i18n.pickPaintFirst || '';
				this.tierBtnsEl.appendChild( note );
				return;
			}
			this.tiers.forEach( ( tier, idx ) => {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'vatan-tool vatan-tool--tier';
				btn.dataset.tool = 'paint';
				btn.dataset.tierIndex = String( idx );

				const chip = document.createElement( 'span' );
				chip.className = 'vatan-tool__chip';
				chip.style.background = tier.color || '#888';
				chip.setAttribute( 'aria-hidden', 'true' );
				btn.appendChild( chip );

				btn.appendChild( document.createTextNode( ' ' + ( tier.name || '' ) ) );
				this.tierBtnsEl.appendChild( btn );
			} );
		}

		renderGrid() {
			this.gridEl.innerHTML = '';
			this.gridEl.style.setProperty( '--seat-cols', String( this.cols ) );
			for ( let r = 1; r <= this.rows; r++ ) {
				for ( let c = 1; c <= this.cols; c++ ) {
					this.gridEl.appendChild( this.createSeat( r, c ) );
				}
			}
		}

		createSeat( row, col ) {
			const key = row + '-' + col;
			const seat = document.createElement( 'button' );
			seat.type = 'button';
			seat.className = 'vatan-seat-editor__seat';
			seat.dataset.seatKey = key;
			seat.textContent = row + '.' + col;
			this.applySeatStyle( seat, key );
			return seat;
		}

		applySeatStyle( seat, key ) {
			seat.classList.remove(
				'vatan-seat-editor__seat--reserved',
				'vatan-seat-editor__seat--hallway',
				'vatan-seat-editor__seat--unassigned',
				'vatan-seat-editor__seat--painted'
			);
			seat.style.removeProperty( '--seat-color' );

			if ( this.hallways.has( key ) ) {
				seat.classList.add( 'vatan-seat-editor__seat--hallway' );
				return;
			}
			if ( this.reserved.has( key ) ) {
				seat.classList.add( 'vatan-seat-editor__seat--reserved' );
				return;
			}
			const idx = this.seatStates.get( key );
			if ( typeof idx === 'number' && this.tiers[ idx ] ) {
				seat.classList.add( 'vatan-seat-editor__seat--painted' );
				seat.style.setProperty( '--seat-color', this.tiers[ idx ].color || '#888' );
				return;
			}
			seat.classList.add( 'vatan-seat-editor__seat--unassigned' );
		}

		/* -------- Tables UI -------- */

		renderTables() {
			if ( ! this.tablesListEl ) return;
			this.tablesListEl.innerHTML = '';

			if ( this.tables.length === 0 ) {
				const empty = document.createElement( 'p' );
				empty.className = 'description vatan-seat-editor__tables-empty';
				empty.textContent = this.i18n.tableNone || '';
				this.tablesListEl.appendChild( empty );
				return;
			}

			this.tables.forEach( ( table, idx ) => {
				this.tablesListEl.appendChild( this.createTableRow( table, idx ) );
			} );
		}

		createTableRow( table, idx ) {
			const row = document.createElement( 'div' );
			row.className = 'vatan-seat-editor__table-row';
			row.dataset.tableIdx = String( idx );

			// Stable visual id badge — never editable.
			const badge = document.createElement( 'span' );
			badge.className = 'vatan-seat-editor__table-id';
			badge.textContent = table.id;
			row.appendChild( badge );

			// Label.
			const labelField = document.createElement( 'label' );
			labelField.className = 'vatan-seat-editor__table-field';
			const labelSpan = document.createElement( 'span' );
			labelSpan.textContent = this.i18n.tableLabel || 'Label';
			labelField.appendChild( labelSpan );
			const labelInput = document.createElement( 'input' );
			labelInput.type  = 'text';
			labelInput.value = table.label || '';
			labelInput.placeholder = table.id;
			labelInput.addEventListener( 'input', () => {
				table.label = labelInput.value;
				this.dirty = true;
				this.renderPreview();
			} );
			labelField.appendChild( labelInput );
			row.appendChild( labelField );

			// Seat count.
			const seatsField = document.createElement( 'label' );
			seatsField.className = 'vatan-seat-editor__table-field vatan-seat-editor__table-field--num';
			const seatsSpan = document.createElement( 'span' );
			seatsSpan.textContent = this.i18n.tableSeats || 'Seats';
			seatsField.appendChild( seatsSpan );
			const seatsInput = document.createElement( 'input' );
			seatsInput.type  = 'number';
			seatsInput.min   = '2';
			seatsInput.max   = '20';
			seatsInput.value = String( table.seats );
			seatsInput.addEventListener( 'change', () => {
				const n = Math.max( 2, Math.min( 20, parseInt( seatsInput.value, 10 ) || 2 ) );
				table.seats = n;
				seatsInput.value = String( n );
				this.dirty = true;
				this.renderPreview();
				this.updateCounts();
			} );
			seatsField.appendChild( seatsInput );
			row.appendChild( seatsField );

			// Tier picker.
			const tierField = document.createElement( 'label' );
			tierField.className = 'vatan-seat-editor__table-field';
			const tierSpan = document.createElement( 'span' );
			tierSpan.textContent = this.i18n.tableTier || 'Tier';
			tierField.appendChild( tierSpan );
			const tierSelect = document.createElement( 'select' );
			if ( this.tiers.length === 0 ) {
				const opt = document.createElement( 'option' );
				opt.textContent = this.i18n.tableNoTiers || '';
				opt.value = '';
				tierSelect.appendChild( opt );
				tierSelect.disabled = true;
			} else {
				this.tiers.forEach( ( tier, i ) => {
					const opt = document.createElement( 'option' );
					opt.value = String( i );
					opt.textContent = tier.name || ( 'Tier ' + ( i + 1 ) );
					if ( i === table.tierIndex ) opt.selected = true;
					tierSelect.appendChild( opt );
				} );
				tierSelect.addEventListener( 'change', () => {
					table.tierIndex = parseInt( tierSelect.value, 10 ) || 0;
					this.dirty = true;
					this.applyTableColorChip( row, table );
					this.renderPreview();
					this.updateCounts();
				} );
			}
			tierField.appendChild( tierSelect );
			row.appendChild( tierField );

			// Lane (row) picker — which numbered lane below the grid this
			// table belongs to. 1 = closest to the seat grid.
			const rowField = document.createElement( 'label' );
			rowField.className = 'vatan-seat-editor__table-field vatan-seat-editor__table-field--num';
			const rowSpan = document.createElement( 'span' );
			rowSpan.textContent = this.i18n.tableRow || 'Lane';
			rowField.appendChild( rowSpan );
			const rowSelect = document.createElement( 'select' );
			for ( let r = 1; r <= 5; r++ ) {
				const opt = document.createElement( 'option' );
				opt.value = String( r );
				opt.textContent = String( r );
				if ( r === table.row ) opt.selected = true;
				rowSelect.appendChild( opt );
			}
			rowSelect.addEventListener( 'change', () => {
				table.row = Math.max( 1, Math.min( 10, parseInt( rowSelect.value, 10 ) || 1 ) );
				this.dirty = true;
				this.renderPreview();
			} );
			rowField.appendChild( rowSelect );
			row.appendChild( rowField );

			// Color chip preview.
			const chip = document.createElement( 'span' );
			chip.className = 'vatan-seat-editor__table-chip';
			row.appendChild( chip );

			// Remove.
			const remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'button-link-delete';
			remove.textContent = '×';
			remove.setAttribute( 'aria-label', this.i18n.tableRemove || 'Remove' );
			remove.addEventListener( 'click', () => {
				if ( ! window.confirm( ( this.i18n.tableRemove || 'Remove' ) + ' ' + table.id + '?' ) ) return;
				this.tables.splice( idx, 1 );
				this.dirty = true;
				this.renderTables();
				this.renderPreview();
				this.updateCounts();
			} );
			row.appendChild( remove );

			this.applyTableColorChip( row, table );
			return row;
		}

		applyTableColorChip( row, table ) {
			const chip = row.querySelector( '.vatan-seat-editor__table-chip' );
			if ( ! chip ) return;
			const tier = this.tiers[ table.tierIndex ];
			chip.style.background = ( tier && tier.color ) ? tier.color : '#888';
		}

		addTable() {
			const id = 'T' + this.nextTableId++;
			this.tables.push( {
				id:        id,
				label:     id,
				seats:     8,
				tierIndex: 0,
				row:       1,
			} );
			this.dirty = true;
			this.renderTables();
			this.renderPreview();
			this.updateCounts();
		}

		/* -------- Live layout preview --------
		 * Renders a mini wireframe of the seat grid + each table lane below
		 * it, in the same vertical order guests see. Read-only — admins make
		 * changes via the rows below; the preview just visualises the state. */

		renderPreview() {
			if ( ! this.previewEl ) return;

			// 1. Grid wireframe — proportional to current rows×cols.
			if ( this.previewGrid ) {
				this.previewGrid.innerHTML = '';
				if ( this.rows && this.cols ) {
					this.previewGrid.style.gridTemplateColumns = 'repeat(' + this.cols + ', 1fr)';
					const total = this.rows * this.cols;
					for ( let i = 0; i < total; i++ ) {
						const c = document.createElement( 'i' );
						this.previewGrid.appendChild( c );
					}
					this.previewGrid.hidden = false;
				} else {
					this.previewGrid.hidden = true;
				}
			}

			// 2. Lanes — group tables by row, render one lane per group.
			if ( ! this.previewLanes ) return;
			this.previewLanes.innerHTML = '';

			const byRow = new Map();
			this.tables.forEach( ( table ) => {
				const r = table.row || 1;
				if ( ! byRow.has( r ) ) byRow.set( r, [] );
				byRow.get( r ).push( table );
			} );
			const sortedRows = Array.from( byRow.keys() ).sort( ( a, b ) => a - b );

			sortedRows.forEach( ( r ) => {
				const lane = document.createElement( 'div' );
				lane.className = 'vatan-seat-editor__preview-lane';

				const laneLabel = document.createElement( 'span' );
				laneLabel.className = 'vatan-seat-editor__preview-lane-label';
				laneLabel.textContent = ( this.i18n.tableRow || 'Lane' ) + ' ' + r;
				lane.appendChild( laneLabel );

				byRow.get( r ).forEach( ( table ) => {
					const dot = document.createElement( 'span' );
					dot.className = 'vatan-seat-editor__preview-table';
					const tier = this.tiers[ table.tierIndex ];
					if ( tier && tier.color ) {
						dot.style.background = tier.color;
					}
					dot.title = ( table.label || table.id ) + ' (×' + table.seats + ')';
					dot.textContent = table.label || table.id;
					lane.appendChild( dot );
				} );

				this.previewLanes.appendChild( lane );
			} );
		}

		refreshActiveButton() {
			this.root.querySelectorAll( '.vatan-tool' ).forEach( ( b ) => b.classList.remove( 'is-active' ) );
			let selector;
			if ( this.tool === 'paint' ) {
				selector = '.vatan-tool--tier[data-tier-index="' + this.currentTier + '"]';
			} else {
				selector = '.vatan-tool[data-tool="' + this.tool + '"]:not(.vatan-tool--tier)';
			}
			const btn = this.root.querySelector( selector );
			if ( btn ) {
				btn.classList.add( 'is-active' );
			}
		}

		/* -------- Painting -------- */

		applyTool( key ) {
			switch ( this.tool ) {
				case 'paint':
					if ( ! this.tiers[ this.currentTier ] ) return;
					this.reserved.delete( key );
					this.hallways.delete( key );
					this.seatStates.set( key, this.currentTier );
					break;

				case 'reserved':
					this.seatStates.delete( key );
					this.hallways.delete( key );
					if ( this.reserved.has( key ) ) {
						this.reserved.delete( key );
					} else {
						this.reserved.add( key );
					}
					break;

				case 'hallway':
					this.seatStates.delete( key );
					this.reserved.delete( key );
					if ( this.hallways.has( key ) ) {
						this.hallways.delete( key );
					} else {
						this.hallways.add( key );
					}
					break;

				case 'erase':
					this.seatStates.delete( key );
					this.reserved.delete( key );
					this.hallways.delete( key );
					break;
			}
			this.dirty = true;
			const seat = this.gridEl.querySelector( '[data-seat-key="' + key + '"]' );
			if ( seat ) {
				this.applySeatStyle( seat, key );
			}
			this.updateCounts();
		}

		/* -------- Events -------- */

		bindEvents() {
			// Tool buttons (event-delegated so re-renders work).
			this.root.addEventListener( 'click', ( e ) => {
				const tierBtn = e.target.closest( '.vatan-tool--tier' );
				if ( tierBtn ) {
					e.preventDefault();
					this.tool        = 'paint';
					this.currentTier = parseInt( tierBtn.dataset.tierIndex, 10 );
					this.refreshActiveButton();
					return;
				}
				const toolBtn = e.target.closest( '.vatan-tool[data-tool]' );
				if ( toolBtn && ! toolBtn.classList.contains( 'vatan-tool--tier' ) ) {
					e.preventDefault();
					this.tool = toolBtn.dataset.tool;
					this.refreshActiveButton();
				}
			} );

			// Drag-to-paint (mouse).
			this.gridEl.addEventListener( 'mousedown', ( e ) => {
				const seat = e.target.closest( '[data-seat-key]' );
				if ( ! seat ) return;
				e.preventDefault();
				this.painting = true;
				this.applyTool( seat.dataset.seatKey );
			} );
			this.gridEl.addEventListener( 'mouseover', ( e ) => {
				if ( ! this.painting ) return;
				const seat = e.target.closest( '[data-seat-key]' );
				if ( ! seat ) return;
				this.applyTool( seat.dataset.seatKey );
			} );
			document.addEventListener( 'mouseup', () => { this.painting = false; } );

			// Touch.
			this.gridEl.addEventListener( 'touchstart', ( e ) => {
				const seat = e.target.closest( '[data-seat-key]' );
				if ( ! seat ) return;
				e.preventDefault();
				this.applyTool( seat.dataset.seatKey );
			}, { passive: false } );
			this.gridEl.addEventListener( 'touchmove', ( e ) => {
				const touch = e.touches[ 0 ];
				if ( ! touch ) return;
				const el = document.elementFromPoint( touch.clientX, touch.clientY );
				const seat = el && el.closest( '[data-seat-key]' );
				if ( seat ) {
					e.preventDefault();
					this.applyTool( seat.dataset.seatKey );
				}
			}, { passive: false } );

			// Rows / cols controls.
			this.rowsCtrl.addEventListener( 'change', () => {
				const v = Math.max( 1, Math.min( 50, parseInt( this.rowsCtrl.value, 10 ) || 1 ) );
				this.rowsCtrl.value = String( v );
				this.resize( v, this.cols );
			} );
			this.colsCtrl.addEventListener( 'change', () => {
				const v = Math.max( 1, Math.min( 50, parseInt( this.colsCtrl.value, 10 ) || 1 ) );
				this.colsCtrl.value = String( v );
				this.resize( this.rows, v );
			} );

			// Reset.
			this.resetBtn.addEventListener( 'click', () => {
				if ( ! window.confirm( this.i18n.resetConfirm || 'Reset?' ) ) {
					return;
				}
				this.seatStates.clear();
				this.reserved.clear();
				this.hallways.clear();
				this.dirty = true;
				this.renderGrid();
				this.updateCounts();
			} );

			// Add round table.
			if ( this.tableAddBtn ) {
				this.tableAddBtn.addEventListener( 'click', () => this.addTable() );
			}

			// Save: serialize before submit.
			this.saveForm.addEventListener( 'submit', () => {
				this.rowsInput.value   = String( this.rows );
				this.colsInput.value   = String( this.cols );
				this.configInput.value = JSON.stringify( this.serialize() );
				this.dirty = false;
			} );

			// beforeunload — guard unsaved changes.
			window.addEventListener( 'beforeunload', ( e ) => {
				if ( ! this.dirty ) return;
				e.preventDefault();
				e.returnValue = this.i18n.unsavedChanges || '';
				return e.returnValue;
			} );
		}

		/* -------- Resize / counts / serialize -------- */

		resize( newRows, newCols ) {
			const isOutside = ( key ) => {
				const parts = key.split( '-' );
				return parseInt( parts[ 0 ], 10 ) > newRows || parseInt( parts[ 1 ], 10 ) > newCols;
			};
			[ this.seatStates, this.reserved, this.hallways ].forEach( ( store ) => {
				Array.from( store.keys ? store.keys() : store ).forEach( ( key ) => {
					if ( isOutside( key ) ) {
						store.delete( key );
					}
				} );
			} );
			this.rows  = newRows;
			this.cols  = newCols;
			this.dirty = true;
			this.renderGrid();
			this.renderPreview();
			this.updateCounts();
		}

		updateCounts() {
			if ( ! this.countsEl ) return;
			this.countsEl.innerHTML = '';

			const counts = new Array( this.tiers.length ).fill( 0 );
			this.seatStates.forEach( ( idx ) => {
				if ( idx >= 0 && idx < counts.length ) counts[ idx ]++;
			} );
			// Add table seats into the tier counts since they sell too.
			this.tables.forEach( ( t ) => {
				if ( t.tierIndex >= 0 && t.tierIndex < counts.length ) {
					counts[ t.tierIndex ] += t.seats;
				}
			} );

			const reservedCount = this.reserved.size;
			const hallwayCount  = this.hallways.size;
			const gridTotal     = this.rows * this.cols;
			const gridAssigned  = Array.from( this.seatStates.keys() ).length;
			const unassigned    = Math.max( 0, gridTotal - gridAssigned - reservedCount - hallwayCount );

			this.tiers.forEach( ( tier, idx ) => {
				const span = document.createElement( 'span' );
				span.className = 'vatan-seat-editor__count';
				const dot = document.createElement( 'i' );
				dot.style.background = tier.color || '#888';
				span.appendChild( dot );
				span.appendChild( document.createTextNode( ' ' + ( tier.name || '' ) + ': ' + counts[ idx ] ) );
				this.countsEl.appendChild( span );
			} );

			const resSpan = document.createElement( 'span' );
			resSpan.className = 'vatan-seat-editor__count vatan-seat-editor__count--reserved';
			resSpan.textContent = '× ' + ( this.i18n.reserved || 'Reserved' ) + ': ' + reservedCount;
			this.countsEl.appendChild( resSpan );

			if ( hallwayCount > 0 ) {
				const hallSpan = document.createElement( 'span' );
				hallSpan.className = 'vatan-seat-editor__count vatan-seat-editor__count--hallway';
				hallSpan.textContent = '⇕ ' + ( this.i18n.hallway || 'Hallway' ) + ': ' + hallwayCount;
				this.countsEl.appendChild( hallSpan );
			}

			const unSpan = document.createElement( 'span' );
			unSpan.className = 'vatan-seat-editor__count vatan-seat-editor__count--unassigned';
			unSpan.textContent = ( this.i18n.unassigned || 'Unassigned' ) + ': ' + unassigned;
			this.countsEl.appendChild( unSpan );
		}

		serialize() {
			const seatsByTier = this.tiers.map( () => [] );
			this.seatStates.forEach( ( idx, key ) => {
				if ( idx >= 0 && idx < seatsByTier.length ) {
					seatsByTier[ idx ].push( key );
				}
			} );

			const sections = [];
			this.tiers.forEach( ( tier, idx ) => {
				if ( seatsByTier[ idx ].length === 0 ) return;
				sections.push( {
					type:  tier.name,
					price: tier.price,
					color: tier.color,
					seats: seatsByTier[ idx ],
				} );
			} );

			// Tables — emit one entry per defined table, picking up the
			// price/color from its currently-selected tier. (x, y) capture
			// the admin's drag-to-position output on the canvas.
			const tables = this.tables.map( ( t ) => {
				const tier = this.tiers[ t.tierIndex ] || { name: '', price: 0, color: '' };
				return {
					id:    t.id,
					seats: t.seats,
					label: t.label || t.id,
					type:  tier.name,
					price: tier.price,
					color: tier.color,
					row:   t.row,
				};
			} );

			return {
				rows:     this.rows,
				cols:     this.cols,
				sections: sections,
				reserved: Array.from( this.reserved ),
				hallways: Array.from( this.hallways ),
				tables:   tables,
			};
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-vatan-seat-editor]' ).forEach( function ( el ) {
			el.__vatanSeatEditor = new SeatMapEditor( el );
		} );
	} );

	window.Vatan = window.Vatan || {};
	window.Vatan.SeatMapEditor = SeatMapEditor;
} )();
