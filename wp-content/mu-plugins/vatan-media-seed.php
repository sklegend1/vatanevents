<?php
/**
 * Plugin Name: Vatan Media Seeder
 * Description: One-shot demo-media populator. Visit /wp-admin/?vatan_seed_media=1
 *              as administrator (with VPN active in Iran) to download a curated
 *              set of Pexels images and wire them to events, hero slides,
 *              city terms, and partner logos. Idempotent — re-running skips
 *              already-imported attachments. Delete this file once demo media
 *              is in place.
 *
 * Notes:
 *   - All images are sourced from images.pexels.com — Pexels License is free
 *     for commercial use, no attribution required.
 *   - Re-run with &force=1 to overwrite the previously seeded mapping.
 */

defined( 'ABSPATH' ) || exit;

const VATAN_MEDIA_SEED_OPTION = 'vatan_media_seeded';

/* -----------------------------------------------------------------------------
 * Media catalogue
 *
 * Each entry: [ pexels_id, slug, w, h, purpose ]
 *   - slug becomes both the local filename and the alt text base.
 *   - purpose is a tag we route on below (hero|event|city|partner).
 * ---------------------------------------------------------------------------*/

function vatan_media_seed_catalogue(): array {
	return array(
		// --- Hero slides — wide concert / stage photography ---------------
		array( 1652361, 'hero-stage-lights',     1920, 1080, 'hero' ),
		array( 2167673, 'hero-concert-crowd',    1920, 1080, 'hero' ),
		array( 1267308, 'hero-festival-night',   1920, 1080, 'hero' ),
		array( 1644888, 'hero-music-silhouette', 1920, 1080, 'hero' ),

		// --- Event covers — concerts / music / theater --------------------
		array( 1763075, 'event-guitar-live',     1200,  800, 'event' ),
		array( 2240771, 'event-singer-stage',    1200,  800, 'event' ),
		array(  167636, 'event-drums-close',     1200,  800, 'event' ),
		array( 2670898, 'event-theater-curtain', 1200,  800, 'event' ),
		array(  210922, 'event-band-live',       1200,  800, 'event' ),
		array(  257904, 'event-piano-keys',      1200,  800, 'event' ),
		array(  196652, 'event-microphone',      1200,  800, 'event' ),
		array(  442576, 'event-classical-hall',  1200,  800, 'event' ),
		array( 3771074, 'event-festival-fans',   1200,  800, 'event' ),
		array(  167092, 'event-dj-deck',         1200,  800, 'event' ),

		// --- City tiles — diaspora-relevant cityscapes --------------------
		array( 1467300, 'city-berlin',     1200, 800, 'city' ),
		array(  109629, 'city-frankfurt',  1200, 800, 'city' ),
		array( 1796730, 'city-hamburg',    1200, 800, 'city' ),
		array(  462162, 'city-london',     1200, 800, 'city' ),
		array(  532826, 'city-stockholm',  1200, 800, 'city' ),
		array(  936722, 'city-toronto',    1200, 800, 'city' ),
		array( 2014422, 'city-los-angeles',1200, 800, 'city' ),
		array(  460740, 'city-vienna',     1200, 800, 'city' ),

		// --- Partner logos — abstract / shape photos (placeholder branding)
		array( 1144176, 'partner-logo-1', 600, 400, 'partner' ),
		array( 2901209, 'partner-logo-2', 600, 400, 'partner' ),
		array(  533923, 'partner-logo-3', 600, 400, 'partner' ),
		array( 1796731, 'partner-logo-4', 600, 400, 'partner' ),
		array( 2412603, 'partner-logo-5', 600, 400, 'partner' ),
	);
}

/* -----------------------------------------------------------------------------
 * Trigger — admin GET handler.
 * ---------------------------------------------------------------------------*/

add_action( 'admin_init', function () {
	if ( empty( $_GET['vatan_seed_media'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}

	$force = ! empty( $_GET['force'] );
	$report = vatan_run_media_seed( (bool) $force );

	wp_die(
		'<h1>Vatan media seed report</h1><pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;white-space:pre-wrap">'
		. esc_html( $report )
		. '</pre><p><a href="' . esc_url( admin_url() ) . '">← Back to dashboard</a></p>',
		'Vatan media seeder',
		array( 'response' => 200 )
	);
} );

/* -----------------------------------------------------------------------------
 * Core routine.
 * ---------------------------------------------------------------------------*/

function vatan_run_media_seed( bool $force = false ): string {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$state = get_option( VATAN_MEDIA_SEED_OPTION, array() );
	if ( ! is_array( $state ) ) {
		$state = array();
	}

	if ( $force ) {
		$state = array();
	}

	$log = array();
	$log[] = '[start] force=' . ( $force ? '1' : '0' );

	$catalogue = vatan_media_seed_catalogue();

	$by_purpose = array(
		'hero'    => array(),
		'event'   => array(),
		'city'    => array(),
		'partner' => array(),
	);

	foreach ( $catalogue as $entry ) {
		[ $pexels_id, $slug, $w, $h, $purpose ] = $entry;
		$cache_key = $purpose . ':' . $slug;

		if ( ! empty( $state['attachments'][ $cache_key ] ) && get_post( $state['attachments'][ $cache_key ] ) ) {
			$att_id = (int) $state['attachments'][ $cache_key ];
			$log[] = "[skip ] $cache_key (already imported as #$att_id)";
		} else {
			$url = "https://images.pexels.com/photos/{$pexels_id}/pexels-photo-{$pexels_id}.jpeg?w={$w}";
			$log[] = "[fetch] $cache_key from $url";

			$tmp = download_url( $url, 30 );
			if ( is_wp_error( $tmp ) ) {
				$log[] = '[fail ] download_url: ' . $tmp->get_error_message();
				continue;
			}

			$file_array = array(
				'name'     => $slug . '.jpg',
				'tmp_name' => $tmp,
			);

			$att_id = media_handle_sideload( $file_array, 0, $slug );
			if ( is_wp_error( $att_id ) ) {
				@unlink( $tmp );
				$log[] = '[fail ] media_handle_sideload: ' . $att_id->get_error_message();
				continue;
			}

			update_post_meta( $att_id, '_wp_attachment_image_alt', $slug );
			$state['attachments'][ $cache_key ] = (int) $att_id;
			$log[] = "[ok   ] $cache_key → attachment #$att_id";
		}

		$by_purpose[ $purpose ][] = array(
			'slug'     => $slug,
			'att_id'   => (int) ( $state['attachments'][ $cache_key ] ?? 0 ),
		);
	}

	update_option( VATAN_MEDIA_SEED_OPTION, $state, false );

	// --- Wire the attachments into the site -------------------------------
	$log[] = '';
	$log[] = '[wire ] attaching to demo content';

	vatan_media_seed_wire_hero( $by_purpose['hero'], $log );
	vatan_media_seed_wire_events( $by_purpose['event'], $log );
	vatan_media_seed_wire_cities( $by_purpose['city'], $log );
	vatan_media_seed_wire_partners( $by_purpose['partner'], $log );

	$log[] = '';
	$log[] = '[done ] ' . count( $state['attachments'] ?? array() ) . ' attachments tracked.';
	return implode( "\n", $log );
}

/* -----------------------------------------------------------------------------
 * Wiring — hero / events / cities / partners.
 * ---------------------------------------------------------------------------*/

function vatan_media_seed_wire_hero( array $items, array &$log ): void {
	if ( empty( $items ) ) {
		return;
	}

	$settings = (array) get_option( 'vatan_theme_settings', array() );
	$slides   = array();

	$copy = array(
		array( 'پژواک', 'موسیقی ایرانی در سراسر جهان', 'موسیقی ایرانی', 'تجربه‌ای زنده با محبوب‌ترین هنرمندان ایرانی، در هرکجای دنیا.' ),
		array( 'فستیوال‌ها', 'بهترین صحنه‌های جهانی', 'صحنه‌های جهانی', 'از برلین تا تورنتو — یک کلیک تا بلیط رویداد بعدی.' ),
		array( 'تئاتر و کلاسیک', 'یک شب فراموش‌نشدنی', 'فراموش‌نشدنی', 'ارکستر، نمایش، و موسیقی سنتی روی همان پلتفرم.' ),
		array( 'استندآپ', 'یک شب پر از خنده', 'پر از خنده', 'هنرمندان نسل جدید، بلیط‌ها به سرعت در حال فروش هستند.' ),
	);

	foreach ( $items as $idx => $item ) {
		$c = $copy[ $idx ] ?? $copy[0];
		$slides[] = array(
			'image_id'        => $item['att_id'],
			'eyebrow'         => $c[0],
			'title'           => $c[1],
			'title_highlight' => $c[2],
			'subtitle'        => $c[3],
			'primary_label'   => 'خرید سریع بلیت',
			'primary_url'     => home_url( '/events/' ),
			'secondary_label' => 'تماشای تیزرها',
			'secondary_url'   => '#trailers',
		);
	}

	$settings['hero_slides']   = $slides;
	$settings['hero_image_id'] = $items[0]['att_id']; // legacy fallback

	update_option( 'vatan_theme_settings', $settings, true );
	wp_cache_delete( 'vatan_theme_settings', 'options' );

	$log[] = '  hero: ' . count( $slides ) . ' slides wired';
}

function vatan_media_seed_wire_events( array $items, array &$log ): void {
	if ( empty( $items ) ) {
		return;
	}

	$events = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	if ( empty( $events ) ) {
		$log[] = '  events: no published events to attach to';
		return;
	}

	$count = 0;
	foreach ( $events as $i => $event ) {
		$item = $items[ $i % count( $items ) ];
		$existing = (int) get_post_thumbnail_id( $event->ID );

		if ( $existing && get_post( $existing ) ) {
			continue; // already has a cover
		}

		set_post_thumbnail( $event->ID, $item['att_id'] );
		$count++;
	}

	$log[] = "  events: featured-image set on $count event(s) (out of " . count( $events ) . ')';
}

function vatan_media_seed_wire_cities( array $items, array &$log ): void {
	if ( empty( $items ) ) {
		return;
	}

	$terms = get_terms( array(
		'taxonomy'   => 'event_city',
		'hide_empty' => false,
	) );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		$log[] = '  cities: no event_city terms found';
		return;
	}

	$count = 0;
	foreach ( $terms as $i => $term ) {
		$item     = $items[ $i % count( $items ) ];
		$existing = (int) get_term_meta( $term->term_id, 'vatan_city_image_id', true );
		if ( $existing && get_post( $existing ) ) {
			continue;
		}
		update_term_meta( $term->term_id, 'vatan_city_image_id', $item['att_id'] );
		$count++;
	}

	$log[] = "  cities: cover image set on $count term(s) (out of " . count( $terms ) . ')';
}

function vatan_media_seed_wire_partners( array $items, array &$log ): void {
	if ( empty( $items ) ) {
		return;
	}

	$settings = (array) get_option( 'vatan_theme_settings', array() );
	$ids      = array_map( fn( $it ) => (int) $it['att_id'], $items );

	$settings['partner_logos'] = $ids;
	update_option( 'vatan_theme_settings', $settings, true );
	wp_cache_delete( 'vatan_theme_settings', 'options' );

	$log[] = '  partners: ' . count( $ids ) . ' logo IDs stored in vatan_theme_settings.partner_logos';
}
