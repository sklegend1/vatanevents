/**
 * Vatan Event — Create Event page (organizer form).
 *
 * Tiny client-side bits:
 *   - Image preview: show the chosen cover image inline before submit.
 *   - Ticket-types repeater: add/remove rows, with auto-incrementing
 *     `name` array indices so each row posts cleanly.
 *
 * Validation is server-side (inc/create-event.php). The browser handles
 * `required` and `type=date` constraints natively — we don't duplicate.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) fn();
		else document.addEventListener( 'DOMContentLoaded', fn );
	}

	/* ---------- Image preview ---------- */

	function initImagePreview() {
		var input = document.querySelector( '[data-vatan-image-input]' );
		if ( ! input ) return;
		var preview = document.querySelector( '[data-vatan-image-preview]' );
		if ( ! preview ) return;

		input.addEventListener( 'change', function () {
			var file = input.files && input.files[ 0 ];
			if ( ! file ) {
				preview.hidden = true;
				preview.innerHTML = '';
				return;
			}
			var reader = new FileReader();
			reader.onload = function ( e ) {
				preview.innerHTML = '';
				var img = document.createElement( 'img' );
				img.src = e.target.result;
				img.alt = '';
				preview.appendChild( img );
				preview.hidden = false;
			};
			reader.readAsDataURL( file );
		} );
	}

	/* ---------- Ticket types repeater ---------- */

	function initTicketRepeater() {
		var container = document.querySelector( '[data-vatan-tickets]' );
		var tpl       = document.querySelector( '[data-vatan-ticket-template]' );
		var addBtn    = document.querySelector( '[data-vatan-ticket-add]' );
		if ( ! container || ! tpl || ! addBtn ) return;

		function rowCount() {
			return container.querySelectorAll( '[data-vatan-ticket-row]' ).length;
		}

		// Renumber the `name` attributes of every row so the server-side
		// array is dense and predictable. Cheap and avoids gaps after removal.
		function renumber() {
			var rows = container.querySelectorAll( '[data-vatan-ticket-row]' );
			rows.forEach( function ( row, idx ) {
				row.querySelectorAll( '[name^="ticket_types["]' ).forEach( function ( input ) {
					input.name = input.name.replace( /ticket_types\[\d+\]/, 'ticket_types[' + idx + ']' );
				} );
			} );
		}

		addBtn.addEventListener( 'click', function () {
			var clone = tpl.content.cloneNode( true );
			var newRow = clone.querySelector( '[data-vatan-ticket-row]' );
			if ( ! newRow ) return;

			// Substitute the placeholder index in `name` attrs with the
			// next slot. Server is forgiving of holes but we keep it clean.
			var next = rowCount();
			newRow.querySelectorAll( '[name*="__INDEX__"]' ).forEach( function ( input ) {
				input.name = input.name.replace( /__INDEX__/g, String( next ) );
			} );
			container.appendChild( newRow );
			renumber();
		} );

		// Remove rows via event delegation — works for both starter rows
		// and dynamically added ones.
		container.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-vatan-ticket-remove]' );
			if ( ! btn ) return;
			var row = btn.closest( '[data-vatan-ticket-row]' );
			if ( ! row ) return;

			// Don't let the user delete the last row — submit handler
			// requires at least one ticket type and the UX is clearer if
			// they see what they're supposed to fill in.
			if ( rowCount() <= 1 ) {
				// Just clear inputs instead.
				row.querySelectorAll( 'input' ).forEach( function ( input ) {
					if ( input.type === 'color' ) {
						input.value = '#7C3AED';
					} else {
						input.value = '';
					}
				} );
				return;
			}

			row.remove();
			renumber();
		} );
	}

	/* ---------- Boot ---------- */

	ready( function () {
		initImagePreview();
		initTicketRepeater();
	} );
} )();
