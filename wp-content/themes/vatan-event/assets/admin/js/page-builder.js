/**
 * Vatan Event — Page Builder editor.
 *
 * Hydrates the canvas from #vatan-pb-initial, supports adding components
 * from the library, drag-reorder via Sortable.js, inline prop editing,
 * block removal, and serialises the layout into a hidden input on form
 * submit.
 *
 * No jQuery. Reads schema/state from inline JSON to avoid wp_localize_script
 * size issues with large registries.
 */
( function () {
	'use strict';

	let SCHEMA = {};
	let blocks = []; // [{id, type, props}]
	let savedSnapshot = '[]'; // JSON of `blocks` at last successful save (or initial load)

	const els = {};

	function uid() {
		if ( window.crypto && window.crypto.randomUUID ) {
			return window.crypto.randomUUID();
		}
		return 'b-' + Math.random().toString( 36 ).slice( 2, 10 ) + '-' + Date.now().toString( 36 );
	}

	function readJson( id ) {
		const node = document.getElementById( id );
		if ( ! node ) return null;
		try {
			return JSON.parse( node.textContent || 'null' );
		} catch ( e ) {
			return null;
		}
	}

	function defaultsFor( type ) {
		const cmp = SCHEMA[ type ];
		if ( ! cmp ) return {};
		const out = {};
		( cmp.props || [] ).forEach( function ( p ) {
			out[ p.key ] = ( 'default' in p ) ? p.default : '';
		} );
		return out;
	}

	function findBlock( id ) {
		return blocks.find( function ( b ) { return b.id === id; } );
	}

	function removeBlock( id ) {
		blocks = blocks.filter( function ( b ) { return b.id !== id; } );
	}

	/**
	 * Serialise the current blocks into the hidden input and refresh the
	 * status indicator. Called on every change so the form is always ready
	 * to post even if the submit listener doesn't fire for some reason.
	 */
	function syncState() {
		const json = JSON.stringify( blocks );
		if ( els.hidden ) {
			els.hidden.value = json;
		}
		updateStatus( json );
	}

	function updateStatus( currentJson ) {
		if ( ! els.status ) return;
		const dirty = currentJson !== savedSnapshot;
		const count = blocks.length;

		if ( dirty ) {
			els.status.setAttribute( 'data-state', 'dirty' );
			els.status.textContent = count > 0
				? count + ' component(s) — unsaved changes. Click Save to keep them.'
				: 'Unsaved changes. Click Save to keep them.';
		} else if ( count > 0 ) {
			els.status.setAttribute( 'data-state', 'loaded' );
			els.status.textContent = count + ' saved component(s) loaded.';
		} else {
			els.status.setAttribute( 'data-state', 'empty' );
			els.status.textContent = 'No layout saved yet. Add components below, then click Save.';
		}
	}

	/* ---------- Rendering ---------- */

	function renderEmpty() {
		els.empty.hidden = blocks.length > 0;
	}

	function renderAll() {
		els.canvas.innerHTML = '';
		blocks.forEach( function ( b ) {
			els.canvas.appendChild( buildBlockNode( b ) );
		} );
		renderEmpty();
	}

	function buildBlockNode( block ) {
		const tpl = document.getElementById( 'vatan-pb-block-tpl' );
		const frag = tpl.content.cloneNode( true );
		const li   = frag.querySelector( '.vatan-pb__block' );

		const cmp = SCHEMA[ block.type ] || { label: block.type, icon: '?', props: [] };

		li.dataset.blockId = block.id;
		li.dataset.type    = block.type;

		li.querySelector( '.vatan-pb__block-icon' ).textContent  = cmp.icon || '';
		li.querySelector( '.vatan-pb__block-title' ).textContent = cmp.label || block.type;
		li.querySelector( '.vatan-pb__block-type' ).textContent  = block.type;

		const body   = li.querySelector( '.vatan-pb__block-body' );
		const toggle = li.querySelector( '.vatan-pb__block-toggle' );
		const remove = li.querySelector( '.vatan-pb__block-remove' );
		const fields = li.querySelector( '.vatan-pb__fields' );
		const noProps = li.querySelector( '.vatan-pb__no-props' );

		if ( ! cmp.props || cmp.props.length === 0 ) {
			noProps.hidden = false;
		} else {
			cmp.props.forEach( function ( spec ) {
				fields.appendChild( buildField( block, spec ) );
			} );
		}

		toggle.addEventListener( 'click', function () {
			const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			body.hidden = expanded;
			li.classList.toggle( 'is-open', ! expanded );
		} );

		remove.addEventListener( 'click', function () {
			if ( ! window.confirm( ( cmp.label || block.type ) + ' — remove?' ) ) {
				return;
			}
			removeBlock( block.id );
			li.remove();
			renderEmpty();
			syncState();
		} );

		return li;
	}

	function buildField( block, spec ) {
		const wrap = document.createElement( 'label' );
		wrap.className = 'vatan-pb__field vatan-pb__field--' + ( spec.type || 'text' );

		const labelText = document.createElement( 'span' );
		labelText.className = 'vatan-pb__field-label';
		labelText.textContent = spec.label || spec.key;
		wrap.appendChild( labelText );

		const value = ( block.props && spec.key in block.props ) ? block.props[ spec.key ] : ( 'default' in spec ? spec.default : '' );
		let input;

		switch ( spec.type ) {
			case 'textarea':
				input = document.createElement( 'textarea' );
				input.rows = 3;
				input.value = value == null ? '' : String( value );
				break;

			case 'media_gallery':
				// Textarea with one URL per line, plus a "Pick from media" button
				// that opens wp.media in multi-select mode and appends URLs.
				input = document.createElement( 'textarea' );
				input.rows = 4;
				input.placeholder = 'https://example.com/logo-1.png\nhttps://example.com/logo-2.png';
				input.value = value == null ? '' : String( value );
				break;

			case 'number':
				input = document.createElement( 'input' );
				input.type = 'number';
				if ( 'min' in spec ) input.min = spec.min;
				if ( 'max' in spec ) input.max = spec.max;
				input.value = value == null || value === '' ? '' : String( value );
				break;

			case 'url':
				input = document.createElement( 'input' );
				input.type = 'url';
				input.placeholder = 'https://…';
				input.value = value == null ? '' : String( value );
				break;

			case 'checkbox':
				// Re-layout: checkbox sits before the label text for clarity.
				wrap.classList.add( 'vatan-pb__field--inline' );
				input = document.createElement( 'input' );
				input.type = 'checkbox';
				input.checked = !! value;
				wrap.insertBefore( input, labelText );
				break;

			case 'select':
				input = document.createElement( 'select' );
				( spec.options || [] ).forEach( function ( opt ) {
					const o = document.createElement( 'option' );
					o.value = opt.value;
					o.textContent = opt.label;
					if ( String( value ) === String( opt.value ) ) {
						o.selected = true;
					}
					input.appendChild( o );
				} );
				break;

			case 'text':
			default:
				input = document.createElement( 'input' );
				input.type = 'text';
				input.value = value == null ? '' : String( value );
				break;
		}

		input.addEventListener( spec.type === 'checkbox' || spec.type === 'select' ? 'change' : 'input', function () {
			const b = findBlock( block.id );
			if ( ! b ) return;
			if ( spec.type === 'checkbox' ) {
				b.props[ spec.key ] = !! input.checked;
			} else if ( spec.type === 'number' ) {
				b.props[ spec.key ] = input.value === '' ? '' : parseInt( input.value, 10 );
			} else {
				b.props[ spec.key ] = input.value;
			}
			syncState();
		} );

		if ( spec.type !== 'checkbox' ) {
			wrap.appendChild( input );
		}

		// Media gallery picker — adds a "Pick from media" button under the
		// textarea that opens wp.media in multi-select mode and appends URLs.
		if ( spec.type === 'media_gallery' && window.wp && window.wp.media ) {
			const picker = document.createElement( 'button' );
			picker.type = 'button';
			picker.className = 'button button-small vatan-pb__media-picker';
			picker.textContent = 'Pick from media library';
			let frame;
			picker.addEventListener( 'click', function () {
				if ( ! frame ) {
					frame = window.wp.media( {
						title: 'Choose images',
						button: { text: 'Use these images' },
						library: { type: 'image' },
						multiple: true,
					} );
					frame.on( 'select', function () {
						const sel = frame.state().get( 'selection' );
						const urls = [];
						sel.each( function ( att ) {
							const a = att.toJSON();
							const url = ( a.sizes && a.sizes.large && a.sizes.large.url )
								|| ( a.sizes && a.sizes.medium && a.sizes.medium.url )
								|| a.url;
							if ( url ) urls.push( url );
						} );
						if ( ! urls.length ) return;
						const existing = input.value.trim();
						input.value = ( existing ? existing + '\n' : '' ) + urls.join( '\n' );
						input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
					} );
				}
				frame.open();
			} );
			wrap.appendChild( picker );
		}

		return wrap;
	}

	/* ---------- Adding from library ---------- */

	function addBlock( type ) {
		if ( ! SCHEMA[ type ] ) return;
		const block = {
			id:    uid(),
			type:  type,
			props: defaultsFor( type ),
		};
		blocks.push( block );
		const node = buildBlockNode( block );
		els.canvas.appendChild( node );
		renderEmpty();
		syncState();

		// Auto-open the newly added block so the user sees its fields right away.
		const toggle = node.querySelector( '.vatan-pb__block-toggle' );
		if ( toggle ) toggle.click();

		// Scroll it into view.
		node.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	function bindLibrary() {
		els.library.querySelectorAll( '.vatan-pb__lib-item' ).forEach( function ( item ) {
			const type = item.dataset.type;
			item.addEventListener( 'click', function () { addBlock( type ); } );
			item.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					addBlock( type );
				}
			} );
		} );
	}

	/* ---------- Drag reorder ---------- */

	function bindSortable() {
		if ( typeof window.Sortable !== 'function' ) {
			// Sortable.js failed to load (offline?). Reorder will be unavailable;
			// the rest of the editor still works.
			return;
		}
		window.Sortable.create( els.canvas, {
			handle: '.vatan-pb__handle',
			animation: 150,
			ghostClass: 'vatan-pb__block--ghost',
			onEnd: function () {
				// Re-derive `blocks` order from the DOM so saving reflects the visual order.
				const order = Array.from( els.canvas.querySelectorAll( '.vatan-pb__block' ) )
					.map( function ( n ) { return n.dataset.blockId; } );
				blocks.sort( function ( a, b ) {
					return order.indexOf( a.id ) - order.indexOf( b.id );
				} );
				syncState();
			},
		} );
	}

	/* ---------- Submit / reset ---------- */

	function bindForm() {
		els.form.addEventListener( 'submit', function () {
			// Final belt-and-braces sync — `syncState()` already keeps the
			// hidden input in step with every edit, but if some edge case
			// missed a path this is the last chance to capture state.
			els.hidden.value = JSON.stringify( blocks );
			// Treat the submit as the new "saved" state so beforeunload
			// doesn't fire during the redirect.
			savedSnapshot = els.hidden.value;
		} );

		els.reset.addEventListener( 'click', function () {
			if ( ! window.confirm( 'Discard unsaved changes and revert to the last saved layout?' ) ) {
				return;
			}
			const initial = readJson( 'vatan-pb-initial' ) || [];
			blocks = initial.map( function ( b ) {
				return {
					id:    b.id || uid(),
					type:  b.type,
					props: Object.assign( {}, defaultsFor( b.type ), b.props || {} ),
				};
			} );
			renderAll();
			syncState();
		} );

		// Warn before navigating away with unsaved changes.
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( JSON.stringify( blocks ) !== savedSnapshot ) {
				e.preventDefault();
				e.returnValue = '';
				return '';
			}
		} );
	}

	/* ---------- Boot ---------- */

	function init() {
		els.library = document.getElementById( 'vatan-pb-library' );
		els.canvas  = document.getElementById( 'vatan-pb-canvas' );
		els.empty   = document.getElementById( 'vatan-pb-empty' );
		els.form    = document.getElementById( 'vatan-pb-form' );
		els.hidden  = document.getElementById( 'vatan-pb-layout' );
		els.reset   = document.getElementById( 'vatan-pb-reset' );
		els.status  = document.querySelector( '[data-vatan-pb-status]' );
		if ( ! els.canvas || ! els.form ) return;

		SCHEMA = readJson( 'vatan-pb-schema' ) || {};
		const initial = readJson( 'vatan-pb-initial' ) || [];
		blocks = initial.map( function ( b ) {
			return {
				id:    b.id || uid(),
				type:  b.type,
				props: Object.assign( {}, defaultsFor( b.type ), b.props || {} ),
			};
		} );

		renderAll();
		bindLibrary();
		bindSortable();
		bindForm();

		// Seed the hidden input + snapshot so the very first submit (with
		// no edits) still posts the loaded layout — otherwise the field
		// would be empty and the server would treat it as "delete all".
		savedSnapshot = JSON.stringify( blocks );
		if ( els.hidden ) {
			els.hidden.value = savedSnapshot;
		}
		updateStatus( savedSnapshot );

		// Expand every loaded block so the user can see what's there at a glance.
		els.canvas.querySelectorAll( '.vatan-pb__block-toggle' ).forEach( function ( t ) {
			t.click();
		} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
