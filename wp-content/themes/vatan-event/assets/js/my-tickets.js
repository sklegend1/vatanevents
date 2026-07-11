/**
 * Vatan Event — My Tickets page.
 *
 * Responsibilities:
 *   1. Render a QR code for each [data-vatan-qr] using the self-hosted
 *      `qrcode-generator` library (window.qrcode). No external service is
 *      contacted — the QR is drawn into a <canvas> at runtime, so it works
 *      offline / behind restrictive networks.
 *   2. "Download PDF" button — uses html2pdf.js (jsPDF + html2canvas under
 *      the hood) to render the targeted ticket to a real PDF file. Falls
 *      back to window.print() if html2pdf.js failed to load.
 *   3. "Print" button — opens the browser print dialog; @media print CSS
 *      hides site chrome so only the targeted ticket is printed.
 */
( function () {
	'use strict';

	var QR_SIZE = 220;

	// Pick a QR "type number" big enough for the payload. The qrcode-generator
	// library uses type numbers 1..40; we let it pick by iterating until the
	// data fits (cheaper than hand-tuning per payload size).
	function buildQr( payload ) {
		if ( typeof window.qrcode !== 'function' ) {
			return null;
		}
		for ( var typeNumber = 4; typeNumber <= 40; typeNumber++ ) {
			try {
				var qr = window.qrcode( typeNumber, 'M' ); // ECC level M
				qr.addData( payload );
				qr.make();
				return qr;
			} catch ( e ) {
				// Payload too long for this type — try the next size up.
			}
		}
		return null;
	}

	function renderQrCodes() {
		document.querySelectorAll( '[data-vatan-qr]' ).forEach( function ( el ) {
			var payload = el.getAttribute( 'data-vatan-qr' );
			if ( ! payload || el.dataset.vatanQrRendered === '1' ) {
				return;
			}

			var qr = buildQr( payload );
			if ( ! qr ) {
				el.textContent = 'QR unavailable';
				return;
			}

			// Draw the QR module grid onto a high-DPI canvas so it stays sharp
			// when printed. Modules are flat black on white — best contrast
			// for camera scanning.
			var modules = qr.getModuleCount();
			var quietZone = 2;
			var totalModules = modules + quietZone * 2;
			var moduleSize = Math.floor( QR_SIZE / totalModules );
			var canvasPx = moduleSize * totalModules;
			var dpr = Math.max( 1, window.devicePixelRatio || 1 );

			var canvas = document.createElement( 'canvas' );
			canvas.width  = canvasPx * dpr;
			canvas.height = canvasPx * dpr;
			canvas.style.width  = canvasPx + 'px';
			canvas.style.height = canvasPx + 'px';

			var ctx = canvas.getContext( '2d' );
			ctx.scale( dpr, dpr );
			ctx.fillStyle = '#FFFFFF';
			ctx.fillRect( 0, 0, canvasPx, canvasPx );
			ctx.fillStyle = '#000000';
			for ( var r = 0; r < modules; r++ ) {
				for ( var c = 0; c < modules; c++ ) {
					if ( qr.isDark( r, c ) ) {
						ctx.fillRect(
							( c + quietZone ) * moduleSize,
							( r + quietZone ) * moduleSize,
							moduleSize,
							moduleSize
						);
					}
				}
			}

			el.innerHTML = ''; // clear <noscript> fallback
			el.appendChild( canvas );
			el.dataset.vatanQrRendered = '1';
		} );
	}

	function findTicket( itemId ) {
		return document.getElementById( 'ticket-' + itemId );
	}

	function downloadPdf( target, itemId ) {
		if ( ! target ) return;
		if ( typeof window.html2pdf !== 'function' ) {
			// Library missing — fall back to print so the user still gets
			// a way to save as PDF via the browser dialog.
			window.print();
			return;
		}

		// Build a filename: vatan-ticket-<itemId>.pdf
		var filename = 'vatan-ticket-' + ( itemId || 'all' ) + '.pdf';

		// Light visual tweaks for the captured snapshot: drop the dark backdrop,
		// remove the action buttons from the snapshot itself.
		target.classList.add( 'is-capturing' );

		var opts = {
			margin:       [ 10, 10, 10, 10 ],
			filename:     filename,
			image:        { type: 'jpeg', quality: 0.95 },
			html2canvas:  {
				scale: 2,
				useCORS: true,
				allowTaint: true,
				backgroundColor: '#ffffff',
				fontFaces: [
					{ family: 'Vazirmatn', weight: '100 900' },
				],
			},
			jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
		};

		// Wait for fonts to load before capturing
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( function () {
				window.html2pdf().set( opts ).from( target ).save().then( function () {
					target.classList.remove( 'is-capturing' );
				} ).catch( function () {
					target.classList.remove( 'is-capturing' );
					window.print();
				} );
			} );
		} else {
			window.html2pdf().set( opts ).from( target ).save().then( function () {
				target.classList.remove( 'is-capturing' );
			} ).catch( function () {
				target.classList.remove( 'is-capturing' );
				window.print();
			} );
		}
	}

	function printTicket( target, itemId ) {
		if ( ! target ) {
			window.print();
			return;
		}
		document.body.setAttribute( 'data-vatan-printing-ticket', itemId );
		target.classList.add( 'is-printing-target' );

		// Defer so the new class repaints before the print dialog opens.
		setTimeout( function () {
			window.print();
			var clear = function () {
				document.body.removeAttribute( 'data-vatan-printing-ticket' );
				target.classList.remove( 'is-printing-target' );
				window.removeEventListener( 'afterprint', clear );
			};
			window.addEventListener( 'afterprint', clear );
			setTimeout( clear, 5000 ); // fallback for older browsers
		}, 50 );
	}

	function attachButtons() {
		document.querySelectorAll( '[data-vatan-pdf-ticket]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var itemId = btn.getAttribute( 'data-vatan-pdf-ticket' );
				downloadPdf( findTicket( itemId ), itemId );
			} );
		} );

		document.querySelectorAll( '[data-vatan-print-ticket]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var itemId = btn.getAttribute( 'data-vatan-print-ticket' );
				printTicket( findTicket( itemId ), itemId );
			} );
		} );
	}

	if ( document.readyState !== 'loading' ) {
		renderQrCodes();
		attachButtons();
	} else {
		document.addEventListener( 'DOMContentLoaded', function () {
			renderQrCodes();
			attachButtons();
		} );
	}
} )();
