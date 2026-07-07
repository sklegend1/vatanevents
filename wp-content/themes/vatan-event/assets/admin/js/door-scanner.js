/**
 * Vatan Event — admin Door Scanner.
 *
 * Streams the device camera into a <video>, samples frames into a hidden
 * <canvas>, decodes each frame with jsQR, and POSTs any successful decode
 * to /wp-json/vatan/v1/checkin. Server is the source of truth — this script
 * only forwards payloads and renders the response.
 */
( function () {
	'use strict';

	var data = window.vatanDoor || {};
	var i18n = data.i18n || {};

	var videoEl    = document.getElementById( 'vatan-door-video' );
	var canvasEl   = document.getElementById( 'vatan-door-canvas' );
	var startBtn   = document.getElementById( 'vatan-door-start' );
	var eventSel   = document.getElementById( 'vatan-door-event' );
	var resultEl   = document.getElementById( 'vatan-door-result' );
	var historyEl  = document.getElementById( 'vatan-door-history' );
	var manualBtn  = document.getElementById( 'vatan-door-manual-submit' );
	var manualEl   = document.getElementById( 'vatan-door-manual-input' );

	if ( ! videoEl || ! canvasEl || ! startBtn || ! resultEl ) {
		return;
	}

	var stream     = null;
	var scanning   = false;
	var lastSent   = '';
	var lastSentAt = 0;
	var ctx        = canvasEl.getContext( '2d', { willReadFrequently: true } );

	startBtn.addEventListener( 'click', function () {
		if ( scanning ) {
			stopCamera();
		} else {
			startCamera();
		}
	} );

	manualBtn && manualBtn.addEventListener( 'click', function () {
		var v = ( manualEl && manualEl.value || '' ).trim();
		if ( v ) {
			submit( v );
		}
	} );

	function startCamera() {
		startBtn.disabled = true;
		startBtn.textContent = i18n.starting || 'Starting…';

		navigator.mediaDevices.getUserMedia( {
			video: { facingMode: 'environment' },
			audio: false,
		} ).then( function ( s ) {
			stream = s;
			videoEl.srcObject = s;
			videoEl.setAttribute( 'playsinline', '' );
			return videoEl.play();
		} ).then( function () {
			scanning = true;
			startBtn.disabled = false;
			startBtn.textContent = i18n.stop || 'Stop';
			requestAnimationFrame( scanFrame );
		} ).catch( function ( err ) {
			startBtn.disabled = false;
			startBtn.textContent = i18n.start || 'Start';
			renderError( ( i18n.cameraDenied || 'Camera access denied' ) + ' (' + err.message + ')' );
		} );
	}

	function stopCamera() {
		scanning = false;
		if ( stream ) {
			stream.getTracks().forEach( function ( t ) { t.stop(); } );
			stream = null;
		}
		videoEl.srcObject = null;
		startBtn.textContent = i18n.start || 'Start';
	}

	function scanFrame() {
		if ( ! scanning ) return;

		if ( videoEl.readyState === videoEl.HAVE_ENOUGH_DATA ) {
			canvasEl.width  = videoEl.videoWidth;
			canvasEl.height = videoEl.videoHeight;
			ctx.drawImage( videoEl, 0, 0, canvasEl.width, canvasEl.height );

			var img = ctx.getImageData( 0, 0, canvasEl.width, canvasEl.height );
			var code = window.jsQR && window.jsQR( img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' } );

			if ( code && code.data ) {
				// De-dupe: don't re-submit the same code within 4 seconds (the
				// scanner sees the same QR many times per second while it's
				// in frame).
				var now = Date.now();
				if ( code.data !== lastSent || now - lastSentAt > 4000 ) {
					lastSent   = code.data;
					lastSentAt = now;
					submit( code.data );
				}
			}
		}

		requestAnimationFrame( scanFrame );
	}

	function submit( payload ) {
		var body = { payload: payload };
		var ev   = eventSel && eventSel.value ? parseInt( eventSel.value, 10 ) : 0;
		if ( ev ) {
			body.event_id = ev;
		}

		fetch( data.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': data.restNonce || '',
			},
			body: JSON.stringify( body ),
		} ).then( function ( r ) { return r.json(); } )
		  .then( renderResult )
		  .catch( function () { renderError( i18n.networkError || 'Network error' ); } );
	}

	function renderResult( res ) {
		if ( ! res || ! res.status ) {
			renderError( 'Unexpected response' );
			return;
		}

		var statusColors = {
			valid:       { cls: 'is-valid',   icon: '✓', label: 'OK' },
			used:        { cls: 'is-used',    icon: '↩', label: 'استفاده شده' },
			wrong_event: { cls: 'is-wrong',   icon: '⚠', label: 'رویداد دیگر' },
			unpaid:      { cls: 'is-unpaid',  icon: '!', label: 'پرداخت نشده' },
			invalid:     { cls: 'is-invalid', icon: '✗', label: 'نامعتبر' },
		};
		var s = statusColors[ res.status ] || statusColors.invalid;

		resultEl.dataset.empty = 'false';
		resultEl.className = 'vatan-door__result ' + s.cls;
		resultEl.innerHTML = '';

		var ticket = res.ticket || {};
		var rows = [
			[ 'وضعیت', s.label + ' ' + s.icon ],
			[ 'پیام', res.message || '' ],
			[ 'رویداد', ticket.event_title || '—' ],
			[ 'نوع بلیت', ticket.ticket_type || '—' ],
			[ 'صندلی‌ها', ( ticket.seats || [] ).join( '، ' ) || '—' ],
			[ 'خریدار', ticket.customer || '—' ],
			[ 'سفارش', ticket.order_number ? '#' + ticket.order_number : '—' ],
		];

		var dl = document.createElement( 'dl' );
		dl.className = 'vatan-door__result-list';
		rows.forEach( function ( row ) {
			var dt = document.createElement( 'dt' );
			dt.textContent = row[0];
			var dd = document.createElement( 'dd' );
			dd.textContent = row[1];
			dl.appendChild( dt );
			dl.appendChild( dd );
		} );
		resultEl.appendChild( dl );

		// History
		var li = document.createElement( 'li' );
		li.className = 'vatan-door__history-item ' + s.cls;
		li.textContent = new Date().toLocaleTimeString() + ' — ' + ( ticket.event_title || '' ) + ' / ' + ( ticket.customer || '' ) + ' / ' + s.label;
		var empty = historyEl && historyEl.querySelector( '.vatan-door__history-empty' );
		if ( empty ) empty.remove();
		historyEl && historyEl.insertBefore( li, historyEl.firstChild );
	}

	function renderError( msg ) {
		resultEl.dataset.empty = 'false';
		resultEl.className = 'vatan-door__result is-invalid';
		resultEl.innerHTML = '';
		var p = document.createElement( 'p' );
		p.textContent = msg;
		resultEl.appendChild( p );
	}
} )();
