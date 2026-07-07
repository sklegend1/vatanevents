/**
 * Vatan Event — entry animations.
 *
 * Two opt-in attributes wire elements up:
 *   • [data-vatan-anim]            — the element itself animates in.
 *   • [data-vatan-anim-children]   — direct children animate in with stagger
 *                                    (each gets a --anim-i index variable).
 *
 * Trigger: IntersectionObserver fires once when the element enters the
 * viewport (40px below). prefers-reduced-motion users skip the transition
 * and just see the final state immediately.
 *
 * No external deps. Loaded site-wide; the observer is a no-op when no
 * matching elements exist on the page.
 */
( function () {
	'use strict';

	if ( ! ( 'IntersectionObserver' in window ) ) {
		// Old browser — just reveal everything immediately.
		document.querySelectorAll( '[data-vatan-anim], [data-vatan-anim-children]' ).forEach( function ( el ) {
			el.classList.add( 'is-in' );
		} );
		return;
	}

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	var observer = new IntersectionObserver( function ( entries, obs ) {
		entries.forEach( function ( entry ) {
			if ( ! entry.isIntersecting ) return;
			entry.target.classList.add( 'is-in' );
			obs.unobserve( entry.target );
		} );
	}, {
		root: null,
		rootMargin: '0px 0px -40px 0px', // trigger slightly before the element fully enters
		threshold: 0.05,
	} );

	function staggerChildren( parent ) {
		var children = parent.children;
		for ( var i = 0; i < children.length; i++ ) {
			var c = children[i];
			c.style.setProperty( '--anim-i', String( i ) );
			c.classList.add( 'vatan-anim-item' );
		}
	}

	function init() {
		document.querySelectorAll( '[data-vatan-anim]' ).forEach( function ( el ) {
			el.classList.add( 'vatan-anim' );
			if ( reduceMotion ) {
				el.classList.add( 'is-in' );
			} else {
				observer.observe( el );
			}
		} );

		document.querySelectorAll( '[data-vatan-anim-children]' ).forEach( function ( el ) {
			staggerChildren( el );
			if ( reduceMotion ) {
				el.classList.add( 'is-in' );
				for ( var i = 0; i < el.children.length; i++ ) {
					el.children[i].classList.add( 'is-in' );
				}
			} else {
				// Observe each child individually so off-screen items can
				// animate when later scrolled into view (e.g. a long grid).
				for ( var j = 0; j < el.children.length; j++ ) {
					observer.observe( el.children[j] );
				}
			}
		} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
