<?php
/**
 * Music module — CPTs, taxonomy, Polylang integration.
 *
 * Registers three post types that model a small on-demand catalog:
 *
 *   - `track`   — a single song. Not publicly browsable on its own URL
 *                 (no archive, no single page) — tracks are surfaced
 *                 through albums / playlists / search and played by the
 *                 JS player. Exposed in REST for the app + future web UI.
 *   - `album`   — a collection of tracks. Doubles as the "playlist" type
 *                 via an ACF `album_type` field (album / EP / single /
 *                 playlist / compilation). Public single pages.
 *   - `artist`  — performer profile (bio + photo + socials). Public.
 *
 * One taxonomy, `music_genre`, applies to tracks (and is also surfaced
 * on albums via meta-query so the same genre tree describes both). Auto-
 * emoji mirrors the `event_category` pattern in custom-post-types.php.
 *
 * Polylang: types and taxonomy are registered translatable, and the
 * `vatan_ensure_post_language` helper from inc/i18n.php is reused so
 * tracks/albums/artists created programmatically (importers, REST,
 * future seeders) always land in a language bucket.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   Post types
   ============================================================ */

function vatan_register_track_post_type() {
	$labels = array(
		'name'                  => _x( 'Tracks', 'post type general name', 'vatan-event' ),
		'singular_name'         => _x( 'Track', 'post type singular name', 'vatan-event' ),
		'menu_name'             => _x( 'Tracks', 'admin menu', 'vatan-event' ),
		'name_admin_bar'        => _x( 'Track', 'add new on admin bar', 'vatan-event' ),
		'add_new'               => _x( 'Add Track', 'track', 'vatan-event' ),
		'add_new_item'          => __( 'Add New Track', 'vatan-event' ),
		'new_item'              => __( 'New Track', 'vatan-event' ),
		'edit_item'             => __( 'Edit Track', 'vatan-event' ),
		'view_item'             => __( 'View Track', 'vatan-event' ),
		'all_items'             => __( 'All Tracks', 'vatan-event' ),
		'search_items'          => __( 'Search Tracks', 'vatan-event' ),
		'not_found'             => __( 'No tracks found.', 'vatan-event' ),
		'not_found_in_trash'    => __( 'No tracks found in Trash.', 'vatan-event' ),
		'featured_image'        => __( 'Cover Art', 'vatan-event' ),
		'set_featured_image'    => __( 'Set cover art', 'vatan-event' ),
		'remove_featured_image' => __( 'Remove cover art', 'vatan-event' ),
		'use_featured_image'    => __( 'Use as cover art', 'vatan-event' ),
	);

	// Tracks are addressable through REST + admin but don't get standalone
	// front-end pages — they're played inside album/artist/playlist views.
	register_post_type( 'track', array(
		'labels'             => $labels,
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_rest'       => true,
		'show_in_admin_bar'  => true,
		'menu_icon'          => 'dashicons-format-audio',
		'menu_position'      => 20,
		'supports'           => array( 'title', 'thumbnail', 'excerpt', 'editor' ),
		'taxonomies'         => array( 'music_genre' ),
		'has_archive'        => false,
		'rewrite'            => false,
		'publicly_queryable' => false,
	) );
}
add_action( 'init', 'vatan_register_track_post_type' );

function vatan_register_album_post_type() {
	$labels = array(
		'name'                  => _x( 'Albums', 'post type general name', 'vatan-event' ),
		'singular_name'         => _x( 'Album', 'post type singular name', 'vatan-event' ),
		'menu_name'             => _x( 'Albums', 'admin menu', 'vatan-event' ),
		'name_admin_bar'        => _x( 'Album', 'add new on admin bar', 'vatan-event' ),
		'add_new'               => _x( 'Add Album', 'album', 'vatan-event' ),
		'add_new_item'          => __( 'Add New Album', 'vatan-event' ),
		'new_item'              => __( 'New Album', 'vatan-event' ),
		'edit_item'             => __( 'Edit Album', 'vatan-event' ),
		'view_item'             => __( 'View Album', 'vatan-event' ),
		'all_items'             => __( 'All Albums', 'vatan-event' ),
		'search_items'          => __( 'Search Albums', 'vatan-event' ),
		'not_found'             => __( 'No albums found.', 'vatan-event' ),
		'not_found_in_trash'    => __( 'No albums found in Trash.', 'vatan-event' ),
		'featured_image'        => __( 'Cover Art', 'vatan-event' ),
		'set_featured_image'    => __( 'Set cover art', 'vatan-event' ),
		'remove_featured_image' => __( 'Remove cover art', 'vatan-event' ),
		'use_featured_image'    => __( 'Use as cover art', 'vatan-event' ),
	);

	register_post_type( 'album', array(
		'labels'        => $labels,
		'public'        => true,
		'has_archive'   => true,
		'rewrite'       => array(
			'slug'       => 'music/album',
			'with_front' => false,
		),
		'menu_icon'     => 'dashicons-album',
		'menu_position' => 21,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest'  => true,
	) );
}
add_action( 'init', 'vatan_register_album_post_type' );

function vatan_register_artist_post_type() {
	$labels = array(
		'name'                  => _x( 'Artists', 'post type general name', 'vatan-event' ),
		'singular_name'         => _x( 'Artist', 'post type singular name', 'vatan-event' ),
		'menu_name'             => _x( 'Artists', 'admin menu', 'vatan-event' ),
		'name_admin_bar'        => _x( 'Artist', 'add new on admin bar', 'vatan-event' ),
		'add_new'               => _x( 'Add Artist', 'artist', 'vatan-event' ),
		'add_new_item'          => __( 'Add New Artist', 'vatan-event' ),
		'new_item'              => __( 'New Artist', 'vatan-event' ),
		'edit_item'             => __( 'Edit Artist', 'vatan-event' ),
		'view_item'             => __( 'View Artist', 'vatan-event' ),
		'all_items'             => __( 'All Artists', 'vatan-event' ),
		'search_items'          => __( 'Search Artists', 'vatan-event' ),
		'not_found'             => __( 'No artists found.', 'vatan-event' ),
		'not_found_in_trash'    => __( 'No artists found in Trash.', 'vatan-event' ),
		'featured_image'        => __( 'Artist Photo', 'vatan-event' ),
		'set_featured_image'    => __( 'Set artist photo', 'vatan-event' ),
		'remove_featured_image' => __( 'Remove artist photo', 'vatan-event' ),
		'use_featured_image'    => __( 'Use as artist photo', 'vatan-event' ),
	);

	register_post_type( 'artist', array(
		'labels'        => $labels,
		'public'        => true,
		'has_archive'   => false,
		'rewrite'       => array(
			'slug'       => 'music/artist',
			'with_front' => false,
		),
		'menu_icon'     => 'dashicons-microphone',
		'menu_position' => 22,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest'  => true,
	) );
}
add_action( 'init', 'vatan_register_artist_post_type' );

/* ============================================================
   Taxonomy: music_genre
   ============================================================ */

function vatan_register_music_genre_taxonomy() {
	register_taxonomy( 'music_genre', array( 'track', 'album' ), array(
		'labels'            => array(
			'name'                       => _x( 'Genres', 'taxonomy general name', 'vatan-event' ),
			'singular_name'              => _x( 'Genre', 'taxonomy singular name', 'vatan-event' ),
			'menu_name'                  => __( 'Genres', 'vatan-event' ),
			'all_items'                  => __( 'All Genres', 'vatan-event' ),
			'edit_item'                  => __( 'Edit Genre', 'vatan-event' ),
			'update_item'                => __( 'Update Genre', 'vatan-event' ),
			'add_new_item'               => __( 'Add New Genre', 'vatan-event' ),
			'new_item_name'              => __( 'New Genre Name', 'vatan-event' ),
			'search_items'               => __( 'Search Genres', 'vatan-event' ),
			'not_found'                  => __( 'No genres found.', 'vatan-event' ),
			'choose_from_most_used'      => __( 'Choose from the most used genres', 'vatan-event' ),
			'separate_items_with_commas' => __( 'Separate genres with commas', 'vatan-event' ),
		),
		// Flat (tag-style) — genres don't nest cleanly.
		'hierarchical'      => false,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array(
			'slug'         => 'music/genre',
			'with_front'   => false,
		),
	) );
}
add_action( 'init', 'vatan_register_music_genre_taxonomy' );

/**
 * Seed a starter set of music genres on theme activation.
 *
 * Stable English slugs paired with Persian display names — mirrors the
 * pattern used for event_category. Existing terms are skipped.
 */
function vatan_seed_default_music_genres() {
	if ( ! taxonomy_exists( 'music_genre' ) ) {
		return;
	}

	$defaults = array(
		'pop'         => 'پاپ',
		'rock'        => 'راک',
		'traditional' => 'سنتی',
		'classical'   => 'کلاسیک',
		'electronic'  => 'الکترونیک',
		'hip-hop'     => 'هیپ‌هاپ',
		'folk'        => 'فولک',
		'soundtrack'  => 'موسیقی فیلم',
	);

	foreach ( $defaults as $slug => $name ) {
		if ( term_exists( $slug, 'music_genre' ) ) {
			continue;
		}
		wp_insert_term( $name, 'music_genre', array( 'slug' => $slug ) );
	}
}
add_action( 'after_switch_theme', 'vatan_seed_default_music_genres' );

/* ============================================================
   Auto-emoji for music_genre terms

   Same pattern as event_category's auto-emoji in
   custom-post-types.php — when an admin creates a genre term, fill
   `vatan_emoji` meta from a slug/name map. Admins can still override.
   ============================================================ */

function vatan_music_genre_slug_to_emoji(): array {
	return array(
		// Canonical English slugs
		'pop'              => '🎤',
		'rock'             => '🎸',
		'indie'            => '🎸',
		'metal'            => '🤘',
		'punk'             => '🤘',
		'electronic'       => '🎛️',
		'edm'              => '🎛️',
		'house'            => '🎛️',
		'techno'           => '🎛️',
		'trance'           => '🎛️',
		'hip-hop'          => '🎤',
		'hiphop'           => '🎤',
		'rap'              => '🎤',
		'rnb'              => '🎤',
		'soul'             => '🎤',
		'jazz'             => '🎷',
		'blues'            => '🎷',
		'classical'        => '🎼',
		'symphony'         => '🎼',
		'orchestral'       => '🎼',
		'traditional'      => '🪕',
		'folk'             => '🪕',
		'country'          => '🤠',
		'soundtrack'       => '🎬',
		'film'             => '🎬',
		'world'            => '🌍',
		'ambient'          => '🌊',
		'chill'            => '🌊',
		'lounge'           => '🍸',
		'dance'            => '💃',
		'reggae'           => '🌴',
		'kids'             => '🧸',
		'podcast'          => '🎙️',
		'spoken-word'      => '🎙️',
		// Persian names — lowercase, matched case-insensitively
		'پاپ'              => '🎤',
		'راک'              => '🎸',
		'متال'             => '🤘',
		'الکترونیک'        => '🎛️',
		'هیپ‌هاپ'          => '🎤',
		'هیپ هاپ'          => '🎤',
		'رپ'               => '🎤',
		'جاز'              => '🎷',
		'بلوز'             => '🎷',
		'کلاسیک'           => '🎼',
		'سمفونی'           => '🎼',
		'سنتی'             => '🪕',
		'فولک'             => '🪕',
		'محلی'             => '🪕',
		'موسیقی فیلم'      => '🎬',
		'موسیقی-فیلم'      => '🎬',
		'محیطی'            => '🌊',
		'رقص'              => '💃',
		'پادکست'           => '🎙️',
	);
}

function vatan_resolve_music_genre_emoji( $term ): string {
	$map      = vatan_music_genre_slug_to_emoji();
	$slug     = (string) $term->slug;
	$slug_alt = preg_replace( '/-fa$/', '', $slug );
	$name     = mb_strtolower( trim( (string) $term->name ) );

	foreach ( array( $slug, $slug_alt, $name ) as $key ) {
		if ( '' !== $key && isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
	}
	return '';
}

function vatan_autoemoji_music_genre( $term_id ) {
	$term = get_term( $term_id, 'music_genre' );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}
	$existing = (string) get_term_meta( $term_id, 'vatan_emoji', true );
	if ( $existing ) {
		return;
	}
	$emoji = vatan_resolve_music_genre_emoji( $term );
	if ( $emoji ) {
		update_term_meta( $term_id, 'vatan_emoji', $emoji );
	}
}
// Priority 50 so we run after Polylang's term-clone hooks (10) and the
// `-fa` clones also get their emoji filled on save.
add_action( 'created_music_genre', 'vatan_autoemoji_music_genre', 50, 1 );
add_action( 'edited_music_genre',  'vatan_autoemoji_music_genre', 50, 1 );

/* ============================================================
   Polylang integration
   ============================================================ */

/**
 * Append music types to Polylang's translatable list. Runs alongside
 * inc/i18n.php::vatan_polylang_translatable_post_types — both filters
 * chain, no conflict.
 *
 * @param string[] $types
 * @return string[]
 */
function vatan_polylang_translatable_music_post_types( $types ) {
	$add = array( 'track', 'album', 'artist' );
	foreach ( $add as $type ) {
		if ( post_type_exists( $type ) && ! in_array( $type, $types, true ) ) {
			$types[] = $type;
		}
	}
	return $types;
}
add_filter( 'pll_get_post_types', 'vatan_polylang_translatable_music_post_types', 10, 1 );

function vatan_polylang_translatable_music_taxonomies( $taxonomies ) {
	if ( taxonomy_exists( 'music_genre' ) && ! in_array( 'music_genre', $taxonomies, true ) ) {
		$taxonomies[] = 'music_genre';
	}
	return $taxonomies;
}
add_filter( 'pll_get_taxonomies', 'vatan_polylang_translatable_music_taxonomies', 10, 1 );

// Reuse the language-fallback helper from inc/i18n.php so music posts
// created programmatically always land in a language bucket. The function
// is global; we just bind it to the music save_post_{type} actions.
add_action( 'save_post_track',  'vatan_ensure_post_language', 99, 1 );
add_action( 'save_post_album',  'vatan_ensure_post_language', 99, 1 );
add_action( 'save_post_artist', 'vatan_ensure_post_language', 99, 1 );

/**
 * Mirror of inc/i18n.php::vatan_ensure_term_language scoped to
 * music_genre — that function hardcodes the event_* taxonomies, so we
 * add a parallel handler here.
 */
function vatan_ensure_music_genre_language( $term_id, $tt_id, $taxonomy ) {
	if ( 'music_genre' !== $taxonomy ) {
		return;
	}
	if ( ! function_exists( 'pll_set_term_language' ) || ! function_exists( 'pll_get_term_language' ) ) {
		return;
	}
	$existing = (string) pll_get_term_language( $term_id );
	if ( $existing ) {
		return;
	}
	$default = function_exists( 'vatan_default_post_language' ) ? vatan_default_post_language() : 'fa';
	pll_set_term_language( $term_id, $default );
}
add_action( 'created_term', 'vatan_ensure_music_genre_language', 99, 3 );
