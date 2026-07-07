/**
 * Vatan Event — seat planner adapter for the create-event form.
 *
 * Glue between the create-event form's ticket-types section and the visual
 * seat editor (assets/admin/js/seat-editor.js, exposed as
 * window.Vatan.SeatMapEditor).
 *
 * Responsibilities:
 *   1. Show / hide the planner panel when the "Enable seat map" toggle flips.
 *   2. Build the editor's tier list from the form's live <input>s (so a brand
 *      new event without any saved ticket types still works the moment the
 *      user has typed in at least one row).
 *   3. Re-instantiate the editor when the user clicks "Refresh tiers" so
 *      tier name/price/color changes pick up without a full page reload.
 *
 * Why a separate adapter instead of patching seat-editor.js: the existing
 * editor reads its initial state from a `<script data-vatan-editor-payload>`
 * blob and never re-reads it. We just rewrite that blob and recreate the
 * editor when the form's tickets change — keeps the editor module unchanged
 * and reusable for the wp-admin Seat Manager.
 */
( function () {
	'use strict';

	const FALLBACK_PALETTE = [ '#06B6D4', '#8B5CF6', '#F59E0B', '#10B981', '#FF2D78', '#EF4444' ];

	function readTicketsFromForm( form ) {
		// Each ticket row is one `[data-vatan-ticket-row]` div containing four
		// inputs (name / price / capacity / color). Read them in document order
		// so the tier index matches what the user sees on screen.
		const rows = Array.from( form.querySelectorAll( '[data-vatan-ticket-row]' ) );
		const tiers = [];
		rows.forEach( ( row, idx ) => {
			const name  = ( row.querySelector( 'input[name$="[name]"]'  ) || {} ).value || '';
			const price = parseFloat( ( row.querySelector( 'input[name$="[price]"]' ) || {} ).value ) || 0;
			let color = ( row.querySelector( 'input[name$="[color]"]' ) || {} ).value || '';
			if ( ! /^#[0-9a-fA-F]{6}$/.test( color ) ) {
				color = FALLBACK_PALETTE[ idx % FALLBACK_PALETTE.length ];
			}
			if ( name.trim() === '' ) return;
			tiers.push( { name: name.trim(), price: price, color: color } );
		} );
		return tiers;
	}

	function getEditorRoot( panel ) {
		return panel.querySelector( '[data-vatan-seat-editor]' );
	}

	function getPayloadEl( root ) {
		return root && root.querySelector( '[data-vatan-editor-payload]' );
	}

	function readPayload( root ) {
		const el = getPayloadEl( root );
		if ( ! el ) return null;
		try {
			return JSON.parse( el.textContent );
		} catch ( e ) {
			return null;
		}
	}

	function writePayload( root, payload ) {
		const el = getPayloadEl( root );
		if ( el ) el.textContent = JSON.stringify( payload );
	}

	// Replace the entire editor root with a clone of itself. cloneNode does
	// not carry event listeners, so this is the simplest way to drop every
	// listener the previous editor instance attached (root-level delegate,
	// rows/cols change handlers, reset confirm, add-table, etc.). The new
	// root has the same markup, including the updated payload <script>, so
	// the next `new SeatMapEditor(...)` finds a clean DOM to bind onto.
	// Returns the new root (caller must re-look it up via the panel).
	function destroyEditor( panel ) {
		const oldRoot = getEditorRoot( panel );
		if ( ! oldRoot ) return null;
		const fresh = oldRoot.cloneNode( true );
		oldRoot.parentNode.replaceChild( fresh, oldRoot );
		return fresh;
	}

	function instantiateEditor( root ) {
		if ( ! window.Vatan || ! window.Vatan.SeatMapEditor ) return;
		root.__vatanSeatEditor = new window.Vatan.SeatMapEditor( root );
	}

	function rebuildEditor( panel, form ) {
		const oldRoot = getEditorRoot( panel );
		if ( ! oldRoot ) return;

		const payload = readPayload( oldRoot ) || {};
		const tiers   = readTicketsFromForm( form );

		// Capture the editor's CURRENT in-memory state before destroying it,
		// so a tier refresh mid-edit doesn't wipe seats the user just painted.
		// (The hidden `seat_map_config` input only gets written on submit, so
		// we can't rely on it for live state.) Fall back to the saved server
		// snapshot when there's no live instance yet (first init).
		let saved = payload.config || {};
		let rows  = payload.rows || 5;
		let cols  = payload.cols || 8;

		const instance = oldRoot.__vatanSeatEditor;
		if ( instance && typeof instance.serialize === 'function' ) {
			try {
				saved = instance.serialize();
				rows  = instance.rows;
				cols  = instance.cols;
			} catch ( e ) {
				/* fall through to payload defaults */
			}
		}

		payload.rows   = rows;
		payload.cols   = cols;
		payload.tiers  = tiers;
		payload.config = saved;
		// Write the updated payload onto the OLD root before cloning so the
		// clone inherits the new state.
		writePayload( oldRoot, payload );

		const freshRoot = destroyEditor( panel );
		if ( freshRoot ) instantiateEditor( freshRoot );
	}

	function init() {
		const panel = document.querySelector( '[data-vatan-seat-planner]' );
		if ( ! panel ) return;
		const form = panel.closest( 'form' );
		if ( ! form ) return;

		const toggle = form.querySelector( '[data-vatan-seat-toggle]' );

		// Editor auto-hydration ran on DOMContentLoaded — but the server-side
		// tier list reflects only saved ticket types. If the user is in
		// create-mode and just typed tickets into the form (or is in edit-mode
		// with a stale snapshot), do one immediate rebuild so the tier buttons
		// reflect what's actually in the form.
		rebuildEditor( panel, form );

		// Toggle: show / hide the planner. We do NOT re-render the editor on
		// toggle off — it stays alive in the DOM so the user can re-enable
		// without losing their work.
		if ( toggle ) {
			toggle.addEventListener( 'change', function () {
				if ( toggle.checked ) {
					panel.hidden = false;
					rebuildEditor( panel, form );
				} else {
					panel.hidden = true;
				}
			} );
		}

		// Refresh-tiers button lives INSIDE the editor root, which we
		// clone-replace on each rebuild — a direct listener would only fire
		// once and then get dropped with the old DOM. Delegate on the panel
		// (outside the clone) so the listener persists.
		panel.addEventListener( 'click', function ( e ) {
			const target = e.target && e.target.closest( '[data-vatan-refresh-tiers]' );
			if ( ! target || ! panel.contains( target ) ) return;
			e.preventDefault();
			rebuildEditor( panel, form );
		} );

		// Submit handler — the embedded editor doesn't have its own <form>, so
		// it never gets a chance to write its serialized state into the hidden
		// inputs (that step normally happens in its own form's submit
		// listener). Do it here on the parent form's submit instead.
		form.addEventListener( 'submit', function () {
			const root = getEditorRoot( panel );
			if ( ! root ) return;

			if ( toggle && ! toggle.checked ) {
				// Toggle off — blank the config so we don't carry over a stale
				// layout the user disabled. Keep rows/cols at their saved
				// values; the server ignores them when seat_map_enabled is 0.
				const cfg = root.querySelector( '[data-vatan-config-input]' );
				if ( cfg ) cfg.value = '';
				return;
			}

			const instance = root.__vatanSeatEditor;
			if ( ! instance || typeof instance.serialize !== 'function' ) return;

			const rowsInput = root.querySelector( '[data-vatan-rows-input]' );
			const colsInput = root.querySelector( '[data-vatan-cols-input]' );
			const cfgInput  = root.querySelector( '[data-vatan-config-input]' );

			if ( rowsInput ) rowsInput.value = String( instance.rows );
			if ( colsInput ) colsInput.value = String( instance.cols );
			if ( cfgInput )  cfgInput.value  = JSON.stringify( instance.serialize() );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
