/**
 * Vatan Event — main JS.
 * Vanilla JS, no jQuery. Bootstraps small interactive behaviors:
 *   • Skip-link focus.
 *   • Sticky-header shadow on scroll.
 *   • Hero carousel (fade + autoplay + swipe + dots + arrows).
 *   • Mobile drawer (hamburger + slide-in + focus trap).
 */
( function () {
	'use strict';

	const data = window.vatanData || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	/* ---------- Skip link ---------- */

	function initSkipLink() {
		const link = document.querySelector( '.skip-link' );
		const main = document.getElementById( 'main' );
		if ( ! link || ! main ) return;
		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			main.setAttribute( 'tabindex', '-1' );
			main.focus();
		} );
	}

	/* ---------- Sticky header shadow ---------- */

	function initHeaderShadow() {
		const header = document.querySelector( '.site-header' );
		if ( ! header ) return;
		const onScroll = () => {
			header.classList.toggle( 'site-header--scrolled', window.scrollY > 8 );
		};
		onScroll();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
	}

	/* ---------- Hero carousel ---------- */

	class HeroCarousel {
		constructor( root ) {
			this.root = root;
			this.slides = Array.from( root.querySelectorAll( '.hero__slide' ) );
			if ( this.slides.length < 2 ) return; // single slide — no carousel behavior

			this.dots = Array.from( root.querySelectorAll( '.hero__dot' ) );
			this.prevBtn = root.querySelector( '[data-vatan-hero-prev]' );
			this.nextBtn = root.querySelector( '[data-vatan-hero-next]' );

			this.current = 0;
			this.timer   = null;
			this.delay   = parseInt( root.getAttribute( 'data-autoplay' ), 10 ) || 6000;

			this.bindEvents();
			this.startAutoplay();
		}

		goto( index ) {
			const len = this.slides.length;
			if ( ! len ) return;
			const next = ( ( index % len ) + len ) % len;
			if ( next === this.current ) return;

			this.slides[ this.current ].classList.remove( 'is-active' );
			this.slides[ this.current ].setAttribute( 'aria-hidden', 'true' );
			if ( this.dots[ this.current ] ) {
				this.dots[ this.current ].classList.remove( 'is-active' );
				this.dots[ this.current ].setAttribute( 'aria-selected', 'false' );
			}

			this.current = next;

			this.slides[ next ].classList.add( 'is-active' );
			this.slides[ next ].setAttribute( 'aria-hidden', 'false' );
			if ( this.dots[ next ] ) {
				this.dots[ next ].classList.add( 'is-active' );
				this.dots[ next ].setAttribute( 'aria-selected', 'true' );
			}
		}

		next() { this.goto( this.current + 1 ); }
		prev() { this.goto( this.current - 1 ); }

		startAutoplay() {
			if ( this.delay <= 0 ) return;
			this.stopAutoplay();
			this.timer = setInterval( () => this.next(), this.delay );
		}

		stopAutoplay() {
			if ( this.timer ) {
				clearInterval( this.timer );
				this.timer = null;
			}
		}

		restartAutoplay() {
			this.stopAutoplay();
			this.startAutoplay();
		}

		bindEvents() {
			if ( this.prevBtn ) {
				this.prevBtn.addEventListener( 'click', () => { this.prev(); this.restartAutoplay(); } );
			}
			if ( this.nextBtn ) {
				this.nextBtn.addEventListener( 'click', () => { this.next(); this.restartAutoplay(); } );
			}
			this.dots.forEach( ( dot, i ) => {
				dot.addEventListener( 'click', () => { this.goto( i ); this.restartAutoplay(); } );
			} );

			// Pause on hover / focus.
			this.root.addEventListener( 'mouseenter', () => this.stopAutoplay() );
			this.root.addEventListener( 'mouseleave', () => this.startAutoplay() );
			this.root.addEventListener( 'focusin',  () => this.stopAutoplay() );
			this.root.addEventListener( 'focusout', () => this.startAutoplay() );

			// Pause when the tab is hidden.
			document.addEventListener( 'visibilitychange', () => {
				if ( document.hidden ) this.stopAutoplay(); else this.startAutoplay();
			} );

			// Keyboard nav when focused.
			this.root.setAttribute( 'tabindex', this.root.hasAttribute( 'tabindex' ) ? this.root.getAttribute( 'tabindex' ) : '0' );
			this.root.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'ArrowLeft' )  { this.prev(); this.restartAutoplay(); }
				if ( e.key === 'ArrowRight' ) { this.next(); this.restartAutoplay(); }
			} );

			// Touch swipe.
			let startX = 0;
			let startY = 0;
			this.root.addEventListener( 'touchstart', ( e ) => {
				if ( ! e.touches.length ) return;
				startX = e.touches[ 0 ].clientX;
				startY = e.touches[ 0 ].clientY;
				this.stopAutoplay();
			}, { passive: true } );
			this.root.addEventListener( 'touchend', ( e ) => {
				const touch = e.changedTouches[ 0 ];
				if ( ! touch ) { this.startAutoplay(); return; }
				const dx = touch.clientX - startX;
				const dy = touch.clientY - startY;
				// Only register horizontal swipes > 40px and mostly-horizontal.
				if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
					// In RTL, swipe-left = next-visually = prev-logical. Stick to direction-agnostic.
					if ( dx < 0 ) this.next(); else this.prev();
				}
				this.startAutoplay();
			}, { passive: true } );
		}
	}

	function initHeroCarousel() {
		document.querySelectorAll( '[data-vatan-hero-carousel]' ).forEach( ( root ) => {
			root.__vatanHeroCarousel = new HeroCarousel( root );
		} );
	}

	/* ---------- Mobile drawer ---------- */

	class NavDrawer {
		constructor() {
			// There can be multiple drawer triggers (e.g. the header hamburger
			// AND the bottom-nav Menu tab). Treat them all as equal toggles.
			this.toggles = Array.from( document.querySelectorAll( '[data-vatan-drawer-toggle]' ) );
			this.drawer   = document.querySelector( '[data-vatan-drawer]' );
			this.backdrop = document.querySelector( '[data-vatan-drawer-backdrop]' );
			this.closeBtn = document.querySelector( '[data-vatan-drawer-close]' );

			if ( this.toggles.length === 0 || ! this.drawer ) return;

			this.lastFocus = null;
			this.bindEvents();
		}

		setExpanded( expanded ) {
			this.toggles.forEach( ( t ) => t.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' ) );
		}

		open() {
			document.body.classList.add( 'vatan-drawer-open' );
			this.drawer.setAttribute( 'aria-hidden', 'false' );
			this.setExpanded( true );
			this.lastFocus = document.activeElement;
			// Defer focus until after the slide-in starts.
			setTimeout( () => {
				const first = this.drawer.querySelector( 'a, button, input, [tabindex]:not([tabindex="-1"])' );
				if ( first ) first.focus(); else this.drawer.focus();
			}, 50 );
		}

		close() {
			document.body.classList.remove( 'vatan-drawer-open' );
			this.drawer.setAttribute( 'aria-hidden', 'true' );
			this.setExpanded( false );
			if ( this.lastFocus && typeof this.lastFocus.focus === 'function' ) {
				this.lastFocus.focus();
			}
		}

		toggleDrawer() {
			if ( document.body.classList.contains( 'vatan-drawer-open' ) ) {
				this.close();
			} else {
				this.open();
			}
		}

		bindEvents() {
			this.toggles.forEach( ( toggle ) => {
				toggle.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					this.toggleDrawer();
				} );
			} );

			if ( this.closeBtn ) {
				this.closeBtn.addEventListener( 'click', () => this.close() );
			}
			if ( this.backdrop ) {
				this.backdrop.addEventListener( 'click', () => this.close() );
			}

			// ESC closes.
			document.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' && document.body.classList.contains( 'vatan-drawer-open' ) ) {
					this.close();
				}
			} );

			// Close when any drawer link is followed (so SPA-ish back-button feels right).
			this.drawer.addEventListener( 'click', ( e ) => {
				const link = e.target.closest( 'a' );
				if ( link && link.href && ! link.href.startsWith( '#' ) ) {
					// Let navigation happen, then close on the way out.
					setTimeout( () => this.close(), 100 );
				}
			} );

			// Auto-close when viewport grows past mobile breakpoint.
			const mq = window.matchMedia( '(min-width: 992px)' );
			const onChange = ( e ) => { if ( e.matches ) this.close(); };
			if ( mq.addEventListener ) {
				mq.addEventListener( 'change', onChange );
			} else if ( mq.addListener ) {
				mq.addListener( onChange ); // older Safari
			}
		}
	}

	function initNavDrawer() {
		const drawer = new NavDrawer();
		window.Vatan = window.Vatan || {};
		window.Vatan.NavDrawer = drawer;
	}

	/* ---------- Share — copy-link button ---------- */

	function initShareCopy() {
		document.querySelectorAll( '[data-vatan-share-copy]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var url = btn.getAttribute( 'data-share-url' ) || window.location.href;
				var feedback = btn.querySelector( '[data-vatan-share-feedback]' );

				var done = function () {
					if ( feedback ) {
						feedback.hidden = false;
						btn.classList.add( 'is-copied' );
						setTimeout( function () {
							feedback.hidden = true;
							btn.classList.remove( 'is-copied' );
						}, 1800 );
					}
				};

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( url ).then( done, function () {
						// Permission denied — fall through to legacy path.
						legacyCopy( url, done );
					} );
				} else {
					legacyCopy( url, done );
				}
			} );
		} );
	}

	function legacyCopy( text, onDone ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild( ta );
		ta.select();
		try { document.execCommand( 'copy' ); onDone(); }
		catch ( e ) { /* silent */ }
		document.body.removeChild( ta );
	}

	/* ---------- Newsletter signup form ---------- */

	function initNewsletterForm() {
		var forms = document.querySelectorAll( '[data-vatan-newsletter]' );
		if ( ! forms.length ) return;

		var restUrl = ( data.restUrl || '/wp-json/vatan/v1/' ).replace( /\/$/, '' ) + '/newsletter';

		forms.forEach( function ( form ) {
			var statusEl = form.parentElement && form.parentElement.querySelector( '[data-vatan-newsletter-status]' );
			var submitBtn = form.querySelector( '[type="submit"]' );
			var submitLabel = submitBtn ? submitBtn.querySelector( '.newsletter__submit-label' ) : null;
			var originalLabel = submitLabel ? submitLabel.textContent : '';

			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				if ( submitBtn && submitBtn.disabled ) return;

				var emailInput = form.querySelector( 'input[name="email"]' );
				var email = emailInput ? emailInput.value.trim() : '';
				if ( ! email ) {
					showStatus( 'error', ( data.i18n && data.i18n.newsletterBadEmail ) || 'Please enter your email address.' );
					return;
				}

				var honey = form.querySelector( 'input[name="website"]' );
				var nonce = form.querySelector( 'input[name="vatan_newsletter_nonce"]' );
				var source = form.querySelector( 'input[name="source"]' );

				var body = new FormData();
				body.append( 'email', email );
				if ( honey ) body.append( 'website', honey.value );
				if ( nonce ) body.append( '_wpnonce', nonce.value );
				if ( source ) body.append( 'source', source.value );

				if ( submitBtn ) {
					submitBtn.disabled = true;
					if ( submitLabel ) submitLabel.textContent = ( data.i18n && data.i18n.working ) || 'Working…';
				}
				hideStatus();

				fetch( restUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: nonce ? { 'X-WP-Nonce': nonce.value } : {},
					body: body,
				} ).then( function ( res ) {
					return res.json().then( function ( json ) {
						return { ok: res.ok, json: json };
					} );
				} ).then( function ( r ) {
					if ( r.ok && r.json && r.json.success ) {
						showStatus( 'success', r.json.message || 'Thanks for subscribing.' );
						form.reset();
					} else {
						var msg = ( r.json && r.json.message ) || 'Could not subscribe — please try again.';
						showStatus( 'error', msg );
					}
				} ).catch( function () {
					showStatus( 'error', 'Network error. Please try again.' );
				} ).finally( function () {
					if ( submitBtn ) {
						submitBtn.disabled = false;
						if ( submitLabel ) submitLabel.textContent = originalLabel;
					}
				} );
			} );

			function showStatus( type, msg ) {
				if ( ! statusEl ) return;
				statusEl.hidden = false;
				statusEl.textContent = msg;
				statusEl.setAttribute( 'data-state', type );
			}
			function hideStatus() {
				if ( ! statusEl ) return;
				statusEl.hidden = true;
				statusEl.textContent = '';
				statusEl.removeAttribute( 'data-state' );
			}
		} );
	}

	/* ---------- Boot ---------- */

	ready( function () {
		initSkipLink();
		initHeaderShadow();
		initHeroCarousel();
		initNavDrawer();
		initShareCopy();
		initNewsletterForm();
	} );

	window.Vatan = window.Vatan || {};
	window.Vatan.data = data;
	window.Vatan.HeroCarousel = HeroCarousel;
} )();
