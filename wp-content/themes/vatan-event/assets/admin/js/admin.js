/**
 * Vatan Event — admin JS.
 *
 * Wires up: WP color picker, media library uploader, social-links repeater,
 * and the Chart.js daily-revenue chart on the analytics page.
 */
( function ( $ ) {
	'use strict';

	const data = window.vatanAdmin || {};
	const i18n = data.i18n || {};

	$( function () {

		/* -------- Color pickers -------- */
		if ( $.fn.wpColorPicker ) {
			$( '.vatan-color-picker' ).wpColorPicker();
		}

		/* -------- Media uploader -------- */
		$( document ).on( 'click', '[data-vatan-media-pick]', function ( e ) {
			e.preventDefault();
			const $btn     = $( this );
			const $field   = $btn.closest( '[data-vatan-media]' );
			const $input   = $field.find( '[data-vatan-media-input]' );
			const $preview = $field.find( '[data-vatan-media-preview]' );
			const $clear   = $field.find( '[data-vatan-media-clear]' );

			if ( ! window.wp || ! window.wp.media ) {
				return;
			}

			const frame = window.wp.media( {
				title:    i18n.mediaTitle || 'Choose media',
				button:   { text: i18n.mediaButton || 'Use this' },
				multiple: false,
			} );

			frame.on( 'select', function () {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				$input.val( attachment.id );
				const url = attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;
				$preview.html( $( '<img alt="" />' ).attr( 'src', url ) );
				$clear.show();
			} );

			frame.open();
		} );

		$( document ).on( 'click', '[data-vatan-media-clear]', function ( e ) {
			e.preventDefault();
			const $field = $( this ).closest( '[data-vatan-media]' );
			$field.find( '[data-vatan-media-input]' ).val( '' );
			$field.find( '[data-vatan-media-preview]' ).empty();
			$( this ).hide();
		} );

		/* -------- Repeater (social links) -------- */
		$( '[data-vatan-repeater]' ).each( function () {
			const $repeater = $( this );
			const $rows     = $repeater.find( '[data-vatan-repeater-rows]' );
			const $template = $repeater.find( '[data-vatan-repeater-template]' );
			const $add      = $repeater.find( '[data-vatan-repeater-add]' );

			$add.on( 'click', function ( e ) {
				e.preventDefault();
				const nextIndex = $rows.children( '.vatan-repeater__row' ).length;
				const html      = $template.html().replace( /__INDEX__/g, nextIndex );
				$rows.append( html );
			} );

			$rows.on( 'click', '[data-vatan-repeater-remove]', function ( e ) {
				e.preventDefault();
				$( this ).closest( '.vatan-repeater__row' ).remove();
			} );
		} );

		/* -------- Sales analytics — Chart.js -------- */
		const $chartData = $( '#vatan-chart-data' );
		const canvas     = document.getElementById( 'vatan-daily-chart' );
		if ( $chartData.length && canvas && window.Chart ) {
			let payload = null;
			try {
				payload = JSON.parse( $chartData.text() );
			} catch ( e ) {
				return;
			}
			if ( ! payload ) {
				return;
			}

			new window.Chart( canvas, {
				type: 'line',
				data: {
					labels: payload.labels || [],
					datasets: [
						{
							label:           ( payload.i18n && payload.i18n.currentLabel ) || 'This period',
							data:            payload.current || [],
							borderColor:     '#FF2D78',
							backgroundColor: 'rgba(255,45,120,0.18)',
							tension:         0.3,
							fill:            true,
							pointRadius:     3,
							pointBackgroundColor: '#FF2D78',
						},
						{
							label:           ( payload.i18n && payload.i18n.previousLabel ) || 'Previous period',
							data:            payload.previous || [],
							borderColor:     '#7C3AED',
							backgroundColor: 'rgba(124,58,237,0.10)',
							tension:         0.3,
							fill:            false,
							borderDash:      [ 4, 4 ],
							pointRadius:     2,
							pointBackgroundColor: '#7C3AED',
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: { intersect: false, mode: 'index' },
					plugins: {
						legend: { position: 'top' },
						tooltip: {
							callbacks: {
								label: function ( ctx ) {
									return ctx.dataset.label + ': ' + new Intl.NumberFormat().format( ctx.parsed.y );
								},
							},
						},
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function ( value ) {
									return new Intl.NumberFormat().format( value );
								},
							},
						},
						x: {
							ticks: { maxRotation: 0, autoSkipPadding: 16 },
						},
					},
				},
			} );
		}
	} );
} )( jQuery );
