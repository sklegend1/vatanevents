<?php
/**
 * Music REST endpoints — namespace `vatan/v1/music/*`.
 *
 * All endpoints are PUBLIC (no auth) by design — anyone on the web or
 * inside the Capacitor app can list and play tracks. Web-vs-app *UI*
 * gating happens at the rendering layer (page-builder block, mini-bar),
 * not here.
 *
 * Routes:
 *   GET /music/feed                — landing-page payload (rails)
 *   GET /music/tracks              — list with filters (q, genre, artist, album)
 *   GET /music/tracks/{id}         — single track
 *   GET /music/albums              — list with filters (q, artist, type, genre, featured)
 *   GET /music/albums/{id}         — single album + ordered track list
 *   GET /music/artists             — list with filters (q, featured)
 *   GET /music/artists/{id}        — single artist + albums + top tracks
 *   GET /music/genres              — flat list with track counts
 *   GET /music/search?q=…          — global search across all three types
 *
 * Mirrors the conventions in inc/rest-api.php: `permission_callback =>
 * __return_true`, X-WP-Total / X-WP-TotalPages headers on paged lists,
 * formatter functions (`vatan_format_<type>_for_rest`) that return arrays.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_MUSIC_REST_NAMESPACE = 'vatan/v1';

function vatan_register_music_rest_routes() {

	$pagination_args = array(
		'per_page' => array(
			'type'              => 'integer',
			'default'           => 20,
			'minimum'           => 1,
			'maximum'           => 100,
			'sanitize_callback' => 'absint',
		),
		'page'     => array(
			'type'              => 'integer',
			'default'           => 1,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
		),
		'lang'     => array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'required'          => false,
		),
	);

	// ---------- Feed (landing-page payload) ----------
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/feed', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_feed',
		'args'                => array(
			'lang' => $pagination_args['lang'],
		),
	) );

	// ---------- Tracks ----------
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/tracks', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_tracks',
		'args'                => array_merge( $pagination_args, array(
			'q'      => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'genre'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'artist' => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
			'album'  => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
		) ),
	) );
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/tracks/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_track',
		'args'                => array(
			'id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
		),
	) );

	// ---------- Albums ----------
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/albums', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_albums',
		'args'                => array_merge( $pagination_args, array(
			'q'        => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'artist'   => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
			'genre'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'type'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
			'featured' => array( 'type' => 'boolean' ),
		) ),
	) );
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/albums/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_album',
		'args'                => array(
			'id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
		),
	) );

	// ---------- Artists ----------
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/artists', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_artists',
		'args'                => array_merge( $pagination_args, array(
			'q'        => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'featured' => array( 'type' => 'boolean' ),
		) ),
	) );
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/artists/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_artist',
		'args'                => array(
			'id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
		),
	) );

	// ---------- Genres + Search ----------
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/genres', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_genres',
	) );
	register_rest_route( VATAN_MUSIC_REST_NAMESPACE, '/music/search', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_music_search',
		'args'                => array(
			'q'     => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			'limit' => array( 'type' => 'integer', 'default' => 8, 'minimum' => 1, 'maximum' => 30, 'sanitize_callback' => 'absint' ),
			'lang'  => $pagination_args['lang'],
		),
	) );
}
add_action( 'rest_api_init', 'vatan_register_music_rest_routes' );

/* ============================================================
   Formatters — shape posts into REST payloads
   ============================================================ */

/**
 * Resolve a track's playable audio URL plus its mime type and whether
 * it's a live stream. Returns [ 'url' => string, 'mime' => string, 'is_live' => bool ].
 */
function vatan_track_audio_source( $track_id ): array {
	$is_live = false;
	if ( function_exists( 'get_field' ) ) {
		$is_live = (bool) get_field( 'track_is_live_stream', $track_id );
	}

	if ( $is_live ) {
		$url = '';
		if ( function_exists( 'get_field' ) ) {
			$url = (string) get_field( 'track_external_url', $track_id );
		}
		return array(
			'url'     => esc_url_raw( $url ),
			'mime'    => 'audio/mpeg', // sensible default for icecast/shoutcast
			'is_live' => true,
		);
	}

	// Uploaded file.
	$file = function_exists( 'get_field' ) ? get_field( 'track_audio_file', $track_id ) : null;
	if ( is_array( $file ) && ! empty( $file['url'] ) ) {
		return array(
			'url'     => esc_url_raw( $file['url'] ),
			'mime'    => (string) ( $file['mime_type'] ?? 'audio/mpeg' ),
			'is_live' => false,
		);
	}
	if ( is_numeric( $file ) ) {
		$url  = wp_get_attachment_url( (int) $file );
		$mime = get_post_mime_type( (int) $file ) ?: 'audio/mpeg';
		return array(
			'url'     => $url ? esc_url_raw( $url ) : '',
			'mime'    => $mime,
			'is_live' => false,
		);
	}
	return array( 'url' => '', 'mime' => '', 'is_live' => false );
}

/**
 * Cover-art URL fallback chain: track image → album image → artist image.
 *
 * @param int|null $album_id  Album post ID, or null/0 to skip the album step.
 * @param int|null $artist_id Artist post ID, or null/0 to skip the artist step.
 */
function vatan_track_cover_url( int $track_id, $album_id = 0, $artist_id = 0, string $size = 'medium' ): string {
	foreach ( array( $track_id, (int) $album_id, (int) $artist_id ) as $id ) {
		if ( $id > 0 && has_post_thumbnail( $id ) ) {
			$url = (string) get_the_post_thumbnail_url( $id, $size );
			if ( $url ) {
				return $url;
			}
		}
	}
	return '';
}

function vatan_format_track_for_rest( $post, array $opts = array() ): array {
	$id        = (int) $post->ID;
	$album_id  = 0;
	$artist_id = 0;
	$track_no  = null;
	$duration  = null;
	$explicit  = false;
	$lyrics    = '';

	if ( function_exists( 'get_field' ) ) {
		$album_id  = (int) get_field( 'track_album', $id );
		$artist_id = (int) get_field( 'track_artist', $id );
		$track_no  = get_field( 'track_track_number', $id );
		$duration  = get_field( 'track_duration_seconds', $id );
		$explicit  = (bool) get_field( 'track_explicit', $id );
		$lyrics    = (string) get_field( 'track_lyrics', $id );
	}

	$audio = vatan_track_audio_source( $id );
	$cover = vatan_track_cover_url( $id, $album_id, $artist_id );

	$genres = array();
	foreach ( get_the_terms( $id, 'music_genre' ) ?: array() as $term ) {
		$genres[] = array(
			'slug'  => $term->slug,
			'name'  => $term->name,
			'emoji' => (string) get_term_meta( $term->term_id, 'vatan_emoji', true ),
		);
	}

	$out = array(
		'id'           => $id,
		'title'        => get_the_title( $post ),
		'slug'         => $post->post_name,
		'audio_url'    => $audio['url'],
		'mime_type'    => $audio['mime'],
		'is_live'      => $audio['is_live'],
		'duration'     => is_numeric( $duration ) ? (int) $duration : null,
		'track_number' => is_numeric( $track_no ) ? (int) $track_no : null,
		'explicit'     => $explicit,
		'cover_url'    => $cover,
		'genres'       => $genres,
		'album'        => $album_id ? array(
			'id'    => $album_id,
			'title' => get_the_title( $album_id ),
			'slug'  => get_post_field( 'post_name', $album_id ),
		) : null,
		'artist'       => $artist_id ? array(
			'id'    => $artist_id,
			'title' => get_the_title( $artist_id ),
			'slug'  => get_post_field( 'post_name', $artist_id ),
		) : null,
	);

	if ( ! empty( $opts['include_lyrics'] ) ) {
		$out['lyrics'] = $lyrics;
	}

	return $out;
}

function vatan_format_album_for_rest( $post ): array {
	$id        = (int) $post->ID;
	$artist_id = 0;
	$type      = 'album';
	$release   = '';
	$featured  = false;
	if ( function_exists( 'get_field' ) ) {
		$artist_id = (int) get_field( 'album_artist', $id );
		$type      = (string) get_field( 'album_type', $id ) ?: 'album';
		$release   = (string) get_field( 'album_release_date', $id );
		$featured  = (bool) get_field( 'album_is_featured', $id );
	}

	$track_count = (int) ( new WP_Query( array(
		'post_type'      => 'track',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array( 'key' => 'track_album', 'value' => $id, 'compare' => '=' ),
		),
	) ) )->found_posts;

	return array(
		'id'           => $id,
		'title'        => get_the_title( $post ),
		'slug'         => $post->post_name,
		'permalink'    => get_permalink( $post ),
		'cover_url'    => has_post_thumbnail( $id ) ? get_the_post_thumbnail_url( $id, 'medium' ) : '',
		'type'         => $type,
		'release_date' => $release,
		'featured'     => $featured,
		'track_count'  => $track_count,
		'artist'       => $artist_id ? array(
			'id'    => $artist_id,
			'title' => get_the_title( $artist_id ),
			'slug'  => get_post_field( 'post_name', $artist_id ),
		) : null,
	);
}

function vatan_format_artist_for_rest( $post ): array {
	$id       = (int) $post->ID;
	$country  = '';
	$featured = false;
	$links    = array();
	if ( function_exists( 'get_field' ) ) {
		$country  = (string) get_field( 'artist_country', $id );
		$featured = (bool) get_field( 'artist_is_featured', $id );
		$raw      = (array) ( get_field( 'artist_links', $id ) ?: array() );
		foreach ( $raw as $row ) {
			if ( empty( $row['url'] ) ) {
				continue;
			}
			$links[] = array(
				'platform' => (string) ( $row['platform'] ?? '' ),
				'url'      => esc_url_raw( (string) $row['url'] ),
			);
		}
	}

	return array(
		'id'        => $id,
		'title'     => get_the_title( $post ),
		'slug'      => $post->post_name,
		'permalink' => get_permalink( $post ),
		'photo_url' => has_post_thumbnail( $id ) ? get_the_post_thumbnail_url( $id, 'medium' ) : '',
		'country'   => $country,
		'featured'  => $featured,
		'links'     => $links,
	);
}

/* ============================================================
   Endpoint callbacks
   ============================================================ */

function vatan_rest_music_feed( WP_REST_Request $request ) {
	$lang = (string) $request->get_param( 'lang' );

	$base = array(
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'posts_per_page' => 8,
	);
	if ( $lang ) {
		$base['lang'] = $lang;
	}

	// Featured albums (fallback: most recent albums)
	$featured_albums = ( new WP_Query( array_merge( $base, array(
		'post_type'  => 'album',
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array( 'key' => 'album_is_featured', 'value' => '1', 'compare' => '=' ),
		),
	) ) ) )->posts;
	if ( count( $featured_albums ) < 4 ) {
		$featured_albums = ( new WP_Query( array_merge( $base, array(
			'post_type' => 'album',
			'orderby'   => 'date',
			'order'     => 'DESC',
		) ) ) )->posts;
	}

	// Featured artists (same fallback pattern)
	$featured_artists = ( new WP_Query( array_merge( $base, array(
		'post_type'  => 'artist',
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array( 'key' => 'artist_is_featured', 'value' => '1', 'compare' => '=' ),
		),
	) ) ) )->posts;
	if ( count( $featured_artists ) < 4 ) {
		$featured_artists = ( new WP_Query( array_merge( $base, array(
			'post_type' => 'artist',
			'orderby'   => 'date',
			'order'     => 'DESC',
		) ) ) )->posts;
	}

	$recent_tracks = ( new WP_Query( array_merge( $base, array(
		'post_type'      => 'track',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'posts_per_page' => 12,
	) ) ) )->posts;

	$recent_albums = ( new WP_Query( array_merge( $base, array(
		'post_type'      => 'album',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'posts_per_page' => 12,
	) ) ) )->posts;

	$genres = get_terms( array(
		'taxonomy'   => 'music_genre',
		'hide_empty' => false,
	) );
	$genres_out = array();
	if ( ! is_wp_error( $genres ) ) {
		foreach ( $genres as $term ) {
			$genres_out[] = array(
				'slug'        => $term->slug,
				'name'        => $term->name,
				'emoji'       => (string) get_term_meta( $term->term_id, 'vatan_emoji', true ),
				'track_count' => (int) $term->count,
			);
		}
	}

	return rest_ensure_response( array(
		'featured_albums'  => array_map( 'vatan_format_album_for_rest',  $featured_albums ),
		'featured_artists' => array_map( 'vatan_format_artist_for_rest', $featured_artists ),
		'recent_tracks'    => array_map( 'vatan_format_track_for_rest',  $recent_tracks ),
		'recent_albums'    => array_map( 'vatan_format_album_for_rest',  $recent_albums ),
		'genres'           => $genres_out,
	) );
}

/**
 * Shared WP_Query helper for paged music list endpoints. Reads
 * pagination + `lang` + `q` from the request and returns a configured
 * WP_Query object plus the paged headers.
 */
function vatan_music_list_query( WP_REST_Request $request, array $args ): array {
	$args['post_status']    = 'publish';
	$args['posts_per_page'] = (int) $request->get_param( 'per_page' );
	$args['paged']          = (int) $request->get_param( 'page' );
	$args['no_found_rows']  = false;

	$q = (string) $request->get_param( 'q' );
	if ( '' !== $q ) {
		$args['s'] = $q;
	}
	$lang = (string) $request->get_param( 'lang' );
	if ( '' !== $lang ) {
		$args['lang'] = $lang;
	}

	$query    = new WP_Query( $args );
	$response = rest_ensure_response( array() );
	$response->header( 'X-WP-Total', (string) $query->found_posts );
	$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

	return array( $query, $response );
}

function vatan_rest_music_tracks( WP_REST_Request $request ) {
	$args = array( 'post_type' => 'track' );

	$meta = array();
	if ( $artist = (int) $request->get_param( 'artist' ) ) {
		$meta[] = array( 'key' => 'track_artist', 'value' => $artist, 'compare' => '=' );
	}
	if ( $album = (int) $request->get_param( 'album' ) ) {
		$meta[] = array( 'key' => 'track_album', 'value' => $album, 'compare' => '=' );
		// When filtering by album, order by track number rather than date.
		$args['meta_key'] = 'track_track_number'; // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['orderby']  = 'meta_value_num title';
		$args['order']    = 'ASC';
	}
	if ( $meta ) {
		$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	$genre = (string) $request->get_param( 'genre' );
	if ( '' !== $genre ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'music_genre',
				'field'    => is_numeric( $genre ) ? 'term_id' : 'slug',
				'terms'    => $genre,
			),
		);
	}

	list( $query, $response ) = vatan_music_list_query( $request, $args );
	$response->set_data( array_map( 'vatan_format_track_for_rest', $query->posts ) );
	return $response;
}

function vatan_rest_music_track( WP_REST_Request $request ) {
	$post = get_post( (int) $request['id'] );
	if ( ! $post || 'track' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'not_found', __( 'Track not found.', 'vatan-event' ), array( 'status' => 404 ) );
	}
	return rest_ensure_response( vatan_format_track_for_rest( $post, array( 'include_lyrics' => true ) ) );
}

function vatan_rest_music_albums( WP_REST_Request $request ) {
	$args = array( 'post_type' => 'album' );

	$meta = array();
	if ( $artist = (int) $request->get_param( 'artist' ) ) {
		$meta[] = array( 'key' => 'album_artist', 'value' => $artist, 'compare' => '=' );
	}
	if ( null !== $request->get_param( 'featured' ) && $request->get_param( 'featured' ) ) {
		$meta[] = array( 'key' => 'album_is_featured', 'value' => '1', 'compare' => '=' );
	}
	if ( $type = (string) $request->get_param( 'type' ) ) {
		$meta[] = array( 'key' => 'album_type', 'value' => $type, 'compare' => '=' );
	}
	if ( $meta ) {
		$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	$genre = (string) $request->get_param( 'genre' );
	if ( '' !== $genre ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'music_genre',
				'field'    => is_numeric( $genre ) ? 'term_id' : 'slug',
				'terms'    => $genre,
			),
		);
	}

	list( $query, $response ) = vatan_music_list_query( $request, $args );
	$response->set_data( array_map( 'vatan_format_album_for_rest', $query->posts ) );
	return $response;
}

function vatan_rest_music_album( WP_REST_Request $request ) {
	$post = get_post( (int) $request['id'] );
	if ( ! $post || 'album' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'not_found', __( 'Album not found.', 'vatan-event' ), array( 'status' => 404 ) );
	}

	$tracks = ( new WP_Query( array(
		'post_type'      => 'track',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'meta_key'       => 'track_track_number', // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'        => 'meta_value_num title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'track_album', 'value' => $post->ID, 'compare' => '=' ),
		),
	) ) )->posts;

	return rest_ensure_response( array_merge(
		vatan_format_album_for_rest( $post ),
		array( 'tracks' => array_map( 'vatan_format_track_for_rest', $tracks ) )
	) );
}

function vatan_rest_music_artists( WP_REST_Request $request ) {
	$args = array( 'post_type' => 'artist' );
	if ( null !== $request->get_param( 'featured' ) && $request->get_param( 'featured' ) ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'artist_is_featured', 'value' => '1', 'compare' => '=' ),
		);
	}

	list( $query, $response ) = vatan_music_list_query( $request, $args );
	$response->set_data( array_map( 'vatan_format_artist_for_rest', $query->posts ) );
	return $response;
}

function vatan_rest_music_artist( WP_REST_Request $request ) {
	$post = get_post( (int) $request['id'] );
	if ( ! $post || 'artist' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'not_found', __( 'Artist not found.', 'vatan-event' ), array( 'status' => 404 ) );
	}

	// Albums where album_artist = this artist.
	$albums = ( new WP_Query( array(
		'post_type'      => 'album',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'album_artist', 'value' => $post->ID, 'compare' => '=' ),
		),
	) ) )->posts;

	// Top tracks: most-recent 20 by this artist (no popularity metric yet).
	$tracks = ( new WP_Query( array(
		'post_type'      => 'track',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'no_found_rows'  => true,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'track_artist', 'value' => $post->ID, 'compare' => '=' ),
		),
	) ) )->posts;

	return rest_ensure_response( array_merge(
		vatan_format_artist_for_rest( $post ),
		array(
			'albums'     => array_map( 'vatan_format_album_for_rest', $albums ),
			'top_tracks' => array_map( 'vatan_format_track_for_rest', $tracks ),
		)
	) );
}

function vatan_rest_music_genres( WP_REST_Request $request ) {
	$terms = get_terms( array(
		'taxonomy'   => 'music_genre',
		'hide_empty' => false,
	) );
	if ( is_wp_error( $terms ) ) {
		return rest_ensure_response( array() );
	}
	$out = array();
	foreach ( $terms as $term ) {
		$out[] = array(
			'slug'        => $term->slug,
			'name'        => $term->name,
			'emoji'       => (string) get_term_meta( $term->term_id, 'vatan_emoji', true ),
			'track_count' => (int) $term->count,
		);
	}
	return rest_ensure_response( $out );
}

function vatan_rest_music_search( WP_REST_Request $request ) {
	$q     = (string) $request->get_param( 'q' );
	$limit = (int) $request->get_param( 'limit' );
	$lang  = (string) $request->get_param( 'lang' );

	$base = array(
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		's'              => $q,
	);
	if ( $lang ) {
		$base['lang'] = $lang;
	}

	$tracks  = ( new WP_Query( array_merge( $base, array( 'post_type' => 'track' ) ) ) )->posts;
	$albums  = ( new WP_Query( array_merge( $base, array( 'post_type' => 'album' ) ) ) )->posts;
	$artists = ( new WP_Query( array_merge( $base, array( 'post_type' => 'artist' ) ) ) )->posts;

	return rest_ensure_response( array(
		'q'       => $q,
		'tracks'  => array_map( 'vatan_format_track_for_rest', $tracks ),
		'albums'  => array_map( 'vatan_format_album_for_rest', $albums ),
		'artists' => array_map( 'vatan_format_artist_for_rest', $artists ),
	) );
}
