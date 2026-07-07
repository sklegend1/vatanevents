<?php
/**
 * Plugin Name: Vatan City Photos Seeder
 * Description: Replaces the generic city tiles with actual landmark photos
 *              sourced from Wikipedia's REST API (Museumsinsel for Berlin,
 *              Eiffel Tower for Paris, etc.). Visit
 *              /wp-admin/?vatan_seed_city_photos=1 as an administrator.
 *              Photos are downloaded into the Media Library and assigned
 *              as `vatan_city_image_id` term meta on each event_city term.
 *
 * Source: en.wikipedia.org REST API → /page/summary/<City>. Returns the
 * page's "originalimage" — typically the main infobox photo, which is the
 * iconic landmark image for major cities.
 *
 * Wikimedia is rarely blocked by restrictive networks, so this works
 * where Pexels / Google Fonts don't.
 */

defined( 'ABSPATH' ) || exit;

const VATAN_CITY_PHOTOS_OPTION = 'vatan_city_photos_seeded';

/**
 * Map of city slugs → Wikipedia page titles. The Wikipedia API call
 * resolves these to specific image URLs at seed time, so we don't
 * have to hard-code unstable upload.wikimedia.org URLs.
 */
function vatan_city_photo_map(): array {
	return array(
		'berlin'     => 'Berlin',
		'frankfurt'  => 'Frankfurt',
		'hamburg'    => 'Hamburg',
		'cologne'    => 'Cologne',
		'london'     => 'London',
		'manchester' => 'Manchester',
		'stockholm'  => 'Stockholm',
		'amsterdam'  => 'Amsterdam',
		'paris'      => 'Paris',
		'vienna'     => 'Vienna',
	);
}

add_action( 'admin_init', function () {
	if ( empty( $_GET['vatan_seed_city_photos'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}

	$force = ! empty( $_GET['force'] );
	$log   = vatan_seed_city_photos( $force );

	wp_die(
		'<h1>Vatan city photos seeder</h1><pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;white-space:pre-wrap">'
		. esc_html( $log )
		. '</pre><p><a href="' . esc_url( home_url( '/' ) ) . '">→ Homepage</a></p>',
		'Vatan city photos seeder',
		array( 'response' => 200 )
	);
} );

function vatan_seed_city_photos( bool $force = false ): string {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$state = get_option( VATAN_CITY_PHOTOS_OPTION, array() );
	if ( ! is_array( $state ) || $force ) {
		$state = array();
	}

	$out = array();
	$out[] = '[start] force=' . ( $force ? '1' : '0' );

	foreach ( vatan_city_photo_map() as $slug => $wiki_title ) {
		// 1. Find the matching event_city term.
		$term = get_term_by( 'slug', $slug, 'event_city' );
		if ( ! $term ) {
			$out[] = "[skip ] {$slug}: no event_city term with that slug.";
			continue;
		}

		// 2. Skip if we already imported this slug, unless force.
		if ( ! $force && isset( $state[ $slug ] ) && get_post( (int) $state[ $slug ] ) ) {
			$att_id = (int) $state[ $slug ];
			update_term_meta( $term->term_id, 'vatan_city_image_id', $att_id );
			$out[] = "[skip ] {$slug}: already imported as #{$att_id}.";
			continue;
		}

		// 3. Resolve image URL via Wikipedia REST API.
		$api_url = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode( $wiki_title );
		$resp    = wp_remote_get( $api_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $resp ) ) {
			$out[] = "[fail ] {$slug}: API error — " . $resp->get_error_message();
			continue;
		}
		$body   = (string) wp_remote_retrieve_body( $resp );
		$parsed = json_decode( $body, true );

		$image_url = '';
		if ( ! empty( $parsed['originalimage']['source'] ) ) {
			$image_url = (string) $parsed['originalimage']['source'];
		} elseif ( ! empty( $parsed['thumbnail']['source'] ) ) {
			$image_url = (string) $parsed['thumbnail']['source'];
		}
		if ( ! $image_url ) {
			$out[] = "[fail ] {$slug}: Wikipedia returned no image for '{$wiki_title}'.";
			continue;
		}

		// Force the URL into Wikimedia's `/thumb/.../1280px-<file>` form.
		// 1280px is a "blessed" cached width; arbitrary widths (e.g. 1600)
		// return 400 because Wikimedia only serves pre-cached sizes. If the
		// API returned an original-file URL (no `/thumb/`), we synthesize
		// the thumb path so big PNGs don't get downloaded full-size.
		if ( preg_match( '#/thumb/.+/(\d+)px-#', $image_url ) ) {
			$image_url = preg_replace( '#/(\d+)px-#', '/1280px-', $image_url );
		} elseif ( preg_match( '#^(https?://upload\.wikimedia\.org/wikipedia/commons/)([0-9a-f])/([0-9a-f]{2})/(.+)$#i', $image_url, $m ) ) {
			$image_url = $m[1] . 'thumb/' . $m[2] . '/' . $m[3] . '/' . $m[4] . '/1280px-' . $m[4];
		}

		// 4. Download into WP's Media Library.
		$out[] = "[fetch] {$slug} ← {$image_url}";
		$tmp = download_url( $image_url, 30 );
		if ( is_wp_error( $tmp ) ) {
			$out[] = "[fail ] {$slug}: download — " . $tmp->get_error_message();
			continue;
		}
		$file_array = array(
			'name'     => 'city-' . $slug . '.jpg',
			'tmp_name' => $tmp,
		);
		$att_id = media_handle_sideload( $file_array, 0, 'City photo for ' . $term->name );
		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp );
			$out[] = "[fail ] {$slug}: sideload — " . $att_id->get_error_message();
			continue;
		}

		update_post_meta( $att_id, '_wp_attachment_image_alt', $term->name );
		update_term_meta( $term->term_id, 'vatan_city_image_id', (int) $att_id );

		$state[ $slug ] = (int) $att_id;
		$out[] = "[ok   ] {$slug} → attachment #{$att_id}, attached to term #{$term->term_id}.";
	}

	update_option( VATAN_CITY_PHOTOS_OPTION, $state, false );
	$out[] = '[done ] ' . count( $state ) . ' cities tracked.';
	return implode( "\n", $out );
}
