<?php
/**
 * Music admin — frontend dashboard handlers + URL/sub-route helpers.
 *
 * Phase 1: list views with delete + featured-toggle. POST submissions land
 * on `template_redirect` priority 5 (before HTML prints) so we can
 * `wp_safe_redirect` after writes — same pattern as inc/create-event.php.
 *
 * The actual list/table HTML lives under templates/admin/views/music/.
 *
 * Editing tracks/albums/artists from the dashboard arrives in phase 2-3.
 * Until then, "Edit" buttons fall back to wp-admin and the helper below
 * (`vatan_music_admin_edit_url`) is the single place to swap that out.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_MUSIC_ADMIN_TYPES = array( 'overview', 'tracks', 'albums', 'artists', 'genres', 'batch' );

/**
 * URL into the music admin dashboard for a given sub-type, with
 * optional extra query args merged in.
 */
function vatan_music_admin_url( string $type = 'overview', array $extra = array() ): string {
	$type = in_array( $type, VATAN_MUSIC_ADMIN_TYPES, true ) ? $type : 'overview';
	$args = array();
	if ( 'overview' !== $type ) {
		$args['type'] = $type;
	}
	if ( $extra ) {
		$args = array_merge( $args, $extra );
	}
	return vatan_admin_url( 'music', $args );
}

/**
 * Resolve the active sub-type from `?type=`, defaulting to overview.
 */
function vatan_music_admin_current_type(): string {
	$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return in_array( $type, VATAN_MUSIC_ADMIN_TYPES, true ) ? $type : 'overview';
}

/**
 * Build the edit URL for a music post. All three CPTs now route to the
 * in-dashboard editor.
 */
function vatan_music_admin_edit_url( int $post_id ): string {
	$type = get_post_type( $post_id );
	$map  = array(
		'track'  => 'tracks',
		'album'  => 'albums',
		'artist' => 'artists',
	);
	if ( isset( $map[ $type ] ) ) {
		return vatan_music_admin_url( $map[ $type ], array( 'vatan_action' => 'edit', 'id' => $post_id ) );
	}
	return (string) get_edit_post_link( $post_id, '' );
}

/** Convenience URL helpers — used by list-view "+ Add" buttons. */
function vatan_music_admin_new_track_url(): string {
	return vatan_music_admin_url( 'tracks', array( 'vatan_action' => 'new' ) );
}
function vatan_music_admin_new_album_url(): string {
	return vatan_music_admin_url( 'albums', array( 'vatan_action' => 'new' ) );
}
function vatan_music_admin_new_artist_url(): string {
	return vatan_music_admin_url( 'artists', array( 'vatan_action' => 'new' ) );
}
function vatan_music_admin_new_genre_url(): string {
	return vatan_music_admin_url( 'genres', array( 'vatan_action' => 'new' ) );
}
function vatan_music_admin_edit_genre_url( int $term_id ): string {
	return vatan_music_admin_url( 'genres', array( 'vatan_action' => 'edit', 'id' => $term_id ) );
}

/* =============================================================================
 *  Asset enqueue — music-admin stylesheet, only on /admin/music/
 * ===========================================================================*/

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'vatan_is_admin_request' ) || ! vatan_is_admin_request() ) {
		return;
	}
	if ( 'music' !== vatan_admin_current_view() ) {
		return;
	}
	if ( ! defined( 'VATAN_EVENT_URI' ) || ! defined( 'VATAN_EVENT_VERSION' ) ) {
		return;
	}
	wp_enqueue_style(
		'vatan-admin-music',
		VATAN_EVENT_URI . '/assets/css/admin-music.css',
		array( 'vatan-admin-dashboard' ),
		VATAN_EVENT_VERSION
	);
	wp_enqueue_script(
		'vatan-admin-music',
		VATAN_EVENT_URI . '/assets/js/admin-music.js',
		array(),
		VATAN_EVENT_VERSION,
		true
	);
}, 31 );

/* =============================================================================
 *  POST handlers — delete + toggle-featured
 * ===========================================================================*/

add_action( 'template_redirect', 'vatan_music_admin_handle_post', 5 );

function vatan_music_admin_handle_post(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( empty( $_POST['vatan_music_action'] ) ) {
		return;
	}
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) {
		return;
	}

	$action = sanitize_key( wp_unslash( (string) $_POST['vatan_music_action'] ) );
	$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$nonce  = isset( $_POST['_vatan_music_nonce'] ) ? (string) wp_unslash( $_POST['_vatan_music_nonce'] ) : '';

	if ( ! $id || ! wp_verify_nonce( $nonce, 'vatan_music_admin_' . $action . '_' . $id ) ) {
		return;
	}

	$post = get_post( $id );
	if ( ! $post || ! in_array( $post->post_type, array( 'track', 'album', 'artist' ), true ) ) {
		return;
	}

	$type_for_url = array(
		'track'  => 'tracks',
		'album'  => 'albums',
		'artist' => 'artists',
	)[ $post->post_type ];

	switch ( $action ) {
		case 'delete':
			// Trash (recoverable). Permanent-delete is one more click in wp-admin
			// — safer default for a frontend dashboard.
			wp_trash_post( $id );
			wp_safe_redirect( vatan_music_admin_url( $type_for_url, array( 'msg' => 'deleted' ) ) );
			exit;

		case 'toggle-featured':
			if ( ! in_array( $post->post_type, array( 'album', 'artist' ), true ) ) {
				return;
			}
			$meta_key = ( 'album' === $post->post_type ) ? 'album_is_featured' : 'artist_is_featured';
			$current  = (bool) get_post_meta( $id, $meta_key, true );
			update_post_meta( $id, $meta_key, $current ? '0' : '1' );
			wp_safe_redirect( vatan_music_admin_url( $type_for_url, array( 'msg' => $current ? 'unfeatured' : 'featured' ) ) );
			exit;

		case 'untrash':
			wp_untrash_post( $id );
			wp_safe_redirect( vatan_music_admin_url( $type_for_url, array( 'msg' => 'restored' ) ) );
			exit;
	}
}

/* =============================================================================
 *  Track save handler — covers both "new" and "edit" submissions.
 *
 *  Form posts to /admin/music/?type=tracks&vatan_action=save with all the
 *  track fields. We validate, sanitize, handle file uploads (audio +
 *  cover), persist via wp_update_post / wp_insert_post, then redirect.
 * ===========================================================================*/

add_action( 'template_redirect', 'vatan_music_admin_handle_track_save', 5 );

function vatan_music_admin_handle_track_save(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( empty( $_POST['vatan_music_save_track'] ) ) {
		return;
	}
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) {
		return;
	}

	$nonce = isset( $_POST['_vatan_music_track_nonce'] ) ? (string) wp_unslash( $_POST['_vatan_music_track_nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'vatan_music_save_track' ) ) {
		return;
	}

	// ---------- Sanitize inputs ----------
	$post_id     = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$status      = isset( $_POST['post_status'] ) && 'draft' === $_POST['post_status'] ? 'draft' : 'publish';
	$artist_id   = isset( $_POST['track_artist'] ) ? (int) $_POST['track_artist'] : 0;
	$album_id    = isset( $_POST['track_album'] ) ? (int) $_POST['track_album'] : 0;
	$track_no    = isset( $_POST['track_track_number'] ) && '' !== $_POST['track_track_number'] ? (int) $_POST['track_track_number'] : null;
	$duration    = isset( $_POST['track_duration_seconds'] ) && '' !== $_POST['track_duration_seconds'] ? (int) $_POST['track_duration_seconds'] : null;
	$is_live     = ! empty( $_POST['track_is_live_stream'] );
	$external    = isset( $_POST['track_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['track_external_url'] ) ) : '';
	$lyrics      = isset( $_POST['track_lyrics'] ) ? sanitize_textarea_field( wp_unslash( $_POST['track_lyrics'] ) ) : '';
	$explicit    = ! empty( $_POST['track_explicit'] );
	$genre_ids   = isset( $_POST['music_genre'] ) && is_array( $_POST['music_genre'] ) ? array_map( 'absint', $_POST['music_genre'] ) : array();
	$remove_audio = ! empty( $_POST['remove_audio'] );
	$remove_cover = ! empty( $_POST['remove_cover'] );
	$use_detected = ! empty( $_POST['use_detected'] );

	if ( '' === $title ) {
		// Bounce back to the form with an error flag.
		$back = vatan_music_admin_url( 'tracks', array( 'vatan_action' => $post_id ? 'edit' : 'new', 'id' => $post_id, 'err' => 'title' ) );
		wp_safe_redirect( $back );
		exit;
	}

	// ---------- Upload audio / cover if files were attached ----------
	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	if ( ! function_exists( 'wp_read_audio_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	$audio_attachment_id = 0;
	$cover_attachment_id = 0;

	if ( ! empty( $_FILES['track_audio_file']['name'] ) && UPLOAD_ERR_OK === (int) $_FILES['track_audio_file']['error'] ) {
		// Read ID3 BEFORE uploading — wp_handle_upload moves the tmp file.
		$id3 = vatan_music_admin_read_id3( $_FILES['track_audio_file']['tmp_name'] );

		$audio_attachment_id = vatan_music_admin_handle_upload( $_FILES['track_audio_file'], array( 'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/aac', 'audio/x-m4a', 'audio/ogg', 'audio/wav' ) );

		// Auto-fill fields from ID3 if not already set.
		if ( $use_detected ) {
			if ( empty( $title ) && ! empty( $id3['title'] ) ) {
				$title = $id3['title'];
			}
			if ( ! $artist_id && ! empty( $id3['artist'] ) ) {
				$existing = get_posts( array(
					'post_type'      => 'artist',
					'post_status'    => 'publish',
					'title'          => $id3['artist'],
					'posts_per_page' => 1,
					'fields'         => 'ids',
				) );
				$artist_id = $existing ? (int) $existing[0] : 0;
				if ( ! $artist_id && ! empty( $id3['artist'] ) ) {
					$new_artist = wp_insert_post( array( 'post_type' => 'artist', 'post_status' => 'publish', 'post_title' => $id3['artist'] ) );
					if ( ! is_wp_error( $new_artist ) ) {
						$artist_id = (int) $new_artist;
					}
				}
			}
			if ( ! $album_id && ! empty( $id3['album'] ) ) {
				$existing = get_posts( array(
					'post_type'      => 'album',
					'post_status'    => 'publish',
					'title'          => $id3['album'],
					'posts_per_page' => 1,
					'fields'         => 'ids',
				) );
				$album_id = $existing ? (int) $existing[0] : 0;
				if ( ! $album_id && ! empty( $id3['album'] ) ) {
					$new_album = wp_insert_post( array( 'post_type' => 'album', 'post_status' => 'publish', 'post_title' => $id3['album'] ) );
					if ( ! is_wp_error( $new_album ) ) {
						$album_id = (int) $new_album;
						if ( $artist_id ) {
							update_post_meta( $album_id, 'album_artist', $artist_id );
						}
					}
				}
			}
			if ( null === $track_no && ! empty( $id3['track_number'] ) ) {
				$track_no = $id3['track_number'];
			}
			if ( null === $duration && ! empty( $id3['duration'] ) ) {
				$duration = $id3['duration'];
			}
		}
	}
	if ( ! empty( $_FILES['cover_file']['name'] ) && UPLOAD_ERR_OK === (int) $_FILES['cover_file']['error'] ) {
		error_log( 'VATAN TRACK SAVE: cover_file=' . $_FILES['cover_file']['name'] . ' type=' . $_FILES['cover_file']['type'] . ' size=' . $_FILES['cover_file']['size'] );
		$cover_attachment_id = vatan_music_admin_handle_upload( $_FILES['cover_file'], array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ) );
		error_log( 'VATAN TRACK SAVE: cover_attachment_id=' . $cover_attachment_id );
	}

	// Auto-detect duration if we just uploaded an audio file and no manual value.
	if ( $audio_attachment_id && null === $duration ) {
		$meta = wp_get_attachment_metadata( $audio_attachment_id );
		if ( is_array( $meta ) && isset( $meta['length'] ) ) {
			$duration = (int) $meta['length'];
		}
	}

	// ---------- Insert or update the track post ----------
	$post_data = array(
		'post_type'   => 'track',
		'post_status' => $status,
		'post_title'  => $title,
	);
	if ( $post_id ) {
		$post_data['ID'] = $post_id;
		wp_update_post( $post_data );
	} else {
		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_safe_redirect( vatan_music_admin_url( 'tracks', array( 'err' => 'save' ) ) );
			exit;
		}
	}

	// ---------- Persist meta fields ----------
	// ACF post_object fields store the linked post ID directly.
	if ( $artist_id ) {
		update_post_meta( $post_id, 'track_artist', $artist_id );
		update_post_meta( $post_id, '_track_artist', 'field_vatan_track_artist' );
	} else {
		delete_post_meta( $post_id, 'track_artist' );
	}
	if ( $album_id ) {
		update_post_meta( $post_id, 'track_album', $album_id );
		update_post_meta( $post_id, '_track_album', 'field_vatan_track_album' );
	} else {
		delete_post_meta( $post_id, 'track_album' );
	}

	if ( null !== $track_no ) {
		update_post_meta( $post_id, 'track_track_number', max( 1, $track_no ) );
		update_post_meta( $post_id, '_track_track_number', 'field_vatan_track_track_number' );
	} else {
		delete_post_meta( $post_id, 'track_track_number' );
	}
	if ( null !== $duration ) {
		update_post_meta( $post_id, 'track_duration_seconds', max( 0, $duration ) );
		update_post_meta( $post_id, '_track_duration_seconds', 'field_vatan_track_duration_seconds' );
	}

	update_post_meta( $post_id, 'track_is_live_stream', $is_live ? '1' : '0' );
	update_post_meta( $post_id, '_track_is_live_stream', 'field_vatan_track_is_live_stream' );

	update_post_meta( $post_id, 'track_explicit', $explicit ? '1' : '0' );
	update_post_meta( $post_id, '_track_explicit', 'field_vatan_track_explicit' );

	update_post_meta( $post_id, 'track_lyrics', $lyrics );
	update_post_meta( $post_id, '_track_lyrics', 'field_vatan_track_lyrics' );

	if ( $is_live ) {
		update_post_meta( $post_id, 'track_external_url', $external );
		update_post_meta( $post_id, '_track_external_url', 'field_vatan_track_external_url' );
	} else {
		delete_post_meta( $post_id, 'track_external_url' );
	}

	// Audio file handling: only update if a new upload came in OR explicit removal.
	if ( $audio_attachment_id ) {
		update_post_meta( $post_id, 'track_audio_file', $audio_attachment_id );
		update_post_meta( $post_id, '_track_audio_file', 'field_vatan_track_audio_file' );
	} elseif ( $remove_audio ) {
		delete_post_meta( $post_id, 'track_audio_file' );
	}

	// Cover (featured image) — manual upload first, then ID3 fallback.
	if ( $cover_attachment_id ) {
		$r = set_post_thumbnail( $post_id, $cover_attachment_id );
		error_log( 'VATAN TRACK COVER: set_post_thumbnail(' . $post_id . ', ' . $cover_attachment_id . ') = ' . var_export( $r, true ) );
	} elseif ( ! empty( $id3['album_art'] ) && file_exists( $id3['album_art'] ) ) {
		vatan_music_admin_set_cover_from_file( $post_id, $id3['album_art'] );
	} elseif ( $remove_cover ) {
		delete_post_thumbnail( $post_id );
	}

	// Genres.
	if ( $genre_ids ) {
		wp_set_object_terms( $post_id, $genre_ids, 'music_genre', false );
	} else {
		wp_set_object_terms( $post_id, array(), 'music_genre', false );
	}

	// Redirect to the edit screen with a success flash.
	wp_safe_redirect( vatan_music_admin_url( 'tracks', array( 'vatan_action' => 'edit', 'id' => $post_id, 'msg' => 'saved' ) ) );
	exit;
}

/* =============================================================================
 *  Bulk-action handler — used by all four list views.
 *
 *  Form posts: vatan_music_bulk_action=trash|untrash|feature|unfeature,
 *  bulk_ids[]=N,N,N. Returns to the list with a flash banner.
 * ===========================================================================*/

add_action( 'template_redirect', 'vatan_music_admin_handle_bulk', 5 );

function vatan_music_admin_handle_bulk(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
	if ( empty( $_POST['vatan_music_bulk_action'] ) ) return;
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) return;

	$nonce = isset( $_POST['_vatan_music_bulk_nonce'] ) ? (string) wp_unslash( $_POST['_vatan_music_bulk_nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'vatan_music_bulk' ) ) return;

	$action  = sanitize_key( wp_unslash( (string) $_POST['vatan_music_bulk_action'] ) );
	$ids     = isset( $_POST['bulk_ids'] ) && is_array( $_POST['bulk_ids'] ) ? array_map( 'absint', $_POST['bulk_ids'] ) : array();
	$ids     = array_values( array_filter( $ids ) );
	$type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'tracks';

	$valid_types = array( 'tracks' => 'track', 'albums' => 'album', 'artists' => 'artist' );
	if ( ! isset( $valid_types[ $type ] ) || ! $ids ) {
		wp_safe_redirect( vatan_music_admin_url( $type ) );
		exit;
	}
	$post_type = $valid_types[ $type ];

	$count = 0;
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $post_type ) continue;

		switch ( $action ) {
			case 'trash':
				if ( 'trash' !== $post->post_status ) {
					wp_trash_post( $id );
					$count++;
				}
				break;
			case 'untrash':
				if ( 'trash' === $post->post_status ) {
					wp_untrash_post( $id );
					$count++;
				}
				break;
			case 'feature':
				if ( in_array( $post_type, array( 'album', 'artist' ), true ) ) {
					update_post_meta( $id, $post_type . '_is_featured', '1' );
					$count++;
				}
				break;
			case 'unfeature':
				if ( in_array( $post_type, array( 'album', 'artist' ), true ) ) {
					update_post_meta( $id, $post_type . '_is_featured', '0' );
					$count++;
				}
				break;
		}
	}

	$msg = 'bulk_' . $action;
	wp_safe_redirect( vatan_music_admin_url( $type, array( 'msg' => $msg, 'n' => $count ) ) );
	exit;
}

/* =============================================================================
 *  Album save handler — covers both "new" and "edit".
 * ===========================================================================*/

add_action( 'template_redirect', 'vatan_music_admin_handle_album_save', 5 );

function vatan_music_admin_handle_album_save(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
	if ( empty( $_POST['vatan_music_save_album'] ) ) return;
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) return;

	$nonce = isset( $_POST['_vatan_music_album_nonce'] ) ? (string) wp_unslash( $_POST['_vatan_music_album_nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'vatan_music_save_album' ) ) return;

	$post_id     = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$status      = isset( $_POST['post_status'] ) && 'draft' === $_POST['post_status'] ? 'draft' : 'publish';
	$artist_id   = isset( $_POST['album_artist'] ) ? (int) $_POST['album_artist'] : 0;
	$type        = isset( $_POST['album_type'] ) ? sanitize_key( wp_unslash( $_POST['album_type'] ) ) : 'album';
	$release     = isset( $_POST['album_release_date'] ) ? sanitize_text_field( wp_unslash( $_POST['album_release_date'] ) ) : '';
	$featured    = ! empty( $_POST['album_is_featured'] );
	$description = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
	$remove_cover = ! empty( $_POST['remove_cover'] );

	if ( ! in_array( $type, array( 'album', 'ep', 'single', 'playlist', 'compilation' ), true ) ) {
		$type = 'album';
	}

	if ( '' === $title ) {
		wp_safe_redirect( vatan_music_admin_url( 'albums', array( 'vatan_action' => $post_id ? 'edit' : 'new', 'id' => $post_id, 'err' => 'title' ) ) );
		exit;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) require_once ABSPATH . 'wp-admin/includes/image.php';

	$cover_attachment_id = 0;
	if ( ! empty( $_FILES['cover_file']['name'] ) && UPLOAD_ERR_OK === (int) $_FILES['cover_file']['error'] ) {
		error_log( 'VATAN ALBUM SAVE: cover_file=' . $_FILES['cover_file']['name'] . ' type=' . $_FILES['cover_file']['type'] . ' size=' . $_FILES['cover_file']['size'] );
		$cover_attachment_id = vatan_music_admin_handle_upload( $_FILES['cover_file'], array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ) );
		error_log( 'VATAN ALBUM SAVE: cover_attachment_id=' . $cover_attachment_id );
	}

	$post_data = array(
		'post_type'    => 'album',
		'post_status'  => $status,
		'post_title'   => $title,
		'post_content' => $description,
	);
	if ( $post_id ) {
		$post_data['ID'] = $post_id;
		wp_update_post( $post_data );
	} else {
		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_safe_redirect( vatan_music_admin_url( 'albums', array( 'err' => 'save' ) ) );
			exit;
		}
	}

	if ( $artist_id ) {
		update_post_meta( $post_id, 'album_artist', $artist_id );
		update_post_meta( $post_id, '_album_artist', 'field_vatan_album_artist' );
	} else {
		delete_post_meta( $post_id, 'album_artist' );
	}
	update_post_meta( $post_id, 'album_type', $type );
	update_post_meta( $post_id, '_album_type', 'field_vatan_album_type' );
	if ( $release ) {
		update_post_meta( $post_id, 'album_release_date', $release );
		update_post_meta( $post_id, '_album_release_date', 'field_vatan_album_release_date' );
	} else {
		delete_post_meta( $post_id, 'album_release_date' );
	}
	update_post_meta( $post_id, 'album_is_featured', $featured ? '1' : '0' );
	update_post_meta( $post_id, '_album_is_featured', 'field_vatan_album_is_featured' );

	if ( $cover_attachment_id ) {
		$r = set_post_thumbnail( $post_id, $cover_attachment_id );
		error_log( 'VATAN ALBUM COVER: set_post_thumbnail(' . $post_id . ', ' . $cover_attachment_id . ') = ' . var_export( $r, true ) );
	} elseif ( $remove_cover ) {
		delete_post_thumbnail( $post_id );
	}

	// Track manager — re-order or detach existing tracks linked to this album.
	if ( isset( $_POST['album_tracks'] ) && is_array( $_POST['album_tracks'] ) ) {
		foreach ( $_POST['album_tracks'] as $track_id_raw => $row ) {
			$track_id = (int) $track_id_raw;
			if ( ! $track_id || ! is_array( $row ) ) continue;
			// Confirm track really belongs (or belonged) to this album to avoid drive-by writes.
			$track_post = get_post( $track_id );
			if ( ! $track_post || 'track' !== $track_post->post_type ) continue;
			$current_album = (int) get_post_meta( $track_id, 'track_album', true );
			if ( $current_album && $current_album !== (int) $post_id ) continue;

			if ( ! empty( $row['remove'] ) ) {
				delete_post_meta( $track_id, 'track_album' );
			} else {
				if ( ! $current_album ) {
					// freshly attached during this save (not reachable from current UI but harmless).
					update_post_meta( $track_id, 'track_album', $post_id );
					update_post_meta( $track_id, '_track_album', 'field_vatan_track_album' );
				}
				if ( isset( $row['position'] ) && '' !== $row['position'] ) {
					update_post_meta( $track_id, 'track_track_number', max( 1, (int) $row['position'] ) );
					update_post_meta( $track_id, '_track_track_number', 'field_vatan_track_track_number' );
				}
			}
		}
	}

	wp_safe_redirect( vatan_music_admin_url( 'albums', array( 'vatan_action' => 'edit', 'id' => $post_id, 'msg' => 'saved' ) ) );
	exit;
}

/* =============================================================================
 *  Artist save handler — covers both "new" and "edit".
 * ===========================================================================*/

add_action( 'template_redirect', 'vatan_music_admin_handle_artist_save', 5 );

function vatan_music_admin_handle_artist_save(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
	if ( empty( $_POST['vatan_music_save_artist'] ) ) return;
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) return;

	$nonce = isset( $_POST['_vatan_music_artist_nonce'] ) ? (string) wp_unslash( $_POST['_vatan_music_artist_nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'vatan_music_save_artist' ) ) return;

	$post_id     = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$status      = isset( $_POST['post_status'] ) && 'draft' === $_POST['post_status'] ? 'draft' : 'publish';
	$country     = isset( $_POST['artist_country'] ) ? sanitize_text_field( wp_unslash( $_POST['artist_country'] ) ) : '';
	$featured    = ! empty( $_POST['artist_is_featured'] );
	$description = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
	$remove_photo = ! empty( $_POST['remove_photo'] );

	if ( '' === $title ) {
		wp_safe_redirect( vatan_music_admin_url( 'artists', array( 'vatan_action' => $post_id ? 'edit' : 'new', 'id' => $post_id, 'err' => 'title' ) ) );
		exit;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) require_once ABSPATH . 'wp-admin/includes/image.php';

	$photo_attachment_id = 0;
	if ( ! empty( $_FILES['photo_file']['name'] ) && UPLOAD_ERR_OK === (int) $_FILES['photo_file']['error'] ) {
		$photo_attachment_id = vatan_music_admin_handle_upload( $_FILES['photo_file'], array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ) );
	}

	$post_data = array(
		'post_type'    => 'artist',
		'post_status'  => $status,
		'post_title'   => $title,
		'post_content' => $description,
	);
	if ( $post_id ) {
		$post_data['ID'] = $post_id;
		wp_update_post( $post_data );
	} else {
		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_safe_redirect( vatan_music_admin_url( 'artists', array( 'err' => 'save' ) ) );
			exit;
		}
	}

	if ( $country ) {
		update_post_meta( $post_id, 'artist_country', $country );
		update_post_meta( $post_id, '_artist_country', 'field_vatan_artist_country' );
	} else {
		delete_post_meta( $post_id, 'artist_country' );
	}
	update_post_meta( $post_id, 'artist_is_featured', $featured ? '1' : '0' );
	update_post_meta( $post_id, '_artist_is_featured', 'field_vatan_artist_is_featured' );

	// Social links repeater.
	$links = array();
	$valid_platforms = array( 'website', 'instagram', 'youtube', 'spotify', 'apple', 'soundcloud', 'twitter', 'telegram' );
	if ( isset( $_POST['artist_links'] ) && is_array( $_POST['artist_links'] ) ) {
		foreach ( $_POST['artist_links'] as $row ) {
			if ( ! is_array( $row ) ) continue;
			$platform = isset( $row['platform'] ) ? sanitize_key( $row['platform'] ) : '';
			$url      = isset( $row['url'] ) ? esc_url_raw( wp_unslash( $row['url'] ) ) : '';
			if ( ! $url || ! in_array( $platform, $valid_platforms, true ) ) continue;
			$links[] = array( 'platform' => $platform, 'url' => $url );
		}
	}
	if ( $links ) {
		update_post_meta( $post_id, 'artist_links', $links );
		update_post_meta( $post_id, '_artist_links', 'field_vatan_artist_links' );
	} else {
		delete_post_meta( $post_id, 'artist_links' );
	}

	if ( $photo_attachment_id ) {
		set_post_thumbnail( $post_id, $photo_attachment_id );
	} elseif ( $remove_photo ) {
		delete_post_thumbnail( $post_id );
	}

	wp_safe_redirect( vatan_music_admin_url( 'artists', array( 'vatan_action' => 'edit', 'id' => $post_id, 'msg' => 'saved' ) ) );
	exit;
}

/* =============================================================================
 *  Genre CRUD — save (create or update) + delete.
 * ===========================================================================*/

add_action( 'template_redirect', 'vatan_music_admin_handle_genre_save', 5 );

function vatan_music_admin_handle_genre_save(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
	if ( empty( $_POST['vatan_music_save_genre'] ) ) return;
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) return;

	$nonce = isset( $_POST['_vatan_music_genre_nonce'] ) ? (string) wp_unslash( $_POST['_vatan_music_genre_nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'vatan_music_save_genre' ) ) return;

	$term_id = isset( $_POST['term_id'] ) ? (int) $_POST['term_id'] : 0;
	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$slug    = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
	$emoji   = isset( $_POST['vatan_emoji'] ) ? sanitize_text_field( wp_unslash( $_POST['vatan_emoji'] ) ) : '';

	if ( '' === $name ) {
		wp_safe_redirect( vatan_music_admin_url( 'genres', array( 'vatan_action' => $term_id ? 'edit' : 'new', 'id' => $term_id, 'err' => 'title' ) ) );
		exit;
	}

	$args = array();
	if ( $slug ) $args['slug'] = $slug;

	if ( $term_id ) {
		$args['name'] = $name;
		$result = wp_update_term( $term_id, 'music_genre', $args );
	} else {
		$result = wp_insert_term( $name, 'music_genre', $args );
	}

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( vatan_music_admin_url( 'genres', array( 'err' => 'save' ) ) );
		exit;
	}

	$saved_term_id = $term_id ? $term_id : (int) $result['term_id'];

	if ( '' !== $emoji ) {
		update_term_meta( $saved_term_id, 'vatan_emoji', $emoji );
	} else {
		delete_term_meta( $saved_term_id, 'vatan_emoji' );
	}

	wp_safe_redirect( vatan_music_admin_url( 'genres', array( 'msg' => 'saved' ) ) );
	exit;
}

add_action( 'template_redirect', 'vatan_music_admin_handle_genre_delete', 5 );

function vatan_music_admin_handle_genre_delete(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
	if ( empty( $_POST['vatan_music_delete_genre'] ) ) return;
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) return;

	$term_id = isset( $_POST['term_id'] ) ? (int) $_POST['term_id'] : 0;
	$nonce   = isset( $_POST['_vatan_music_genre_nonce'] ) ? (string) wp_unslash( $_POST['_vatan_music_genre_nonce'] ) : '';
	if ( ! $term_id || ! wp_verify_nonce( $nonce, 'vatan_music_delete_genre_' . $term_id ) ) return;

	wp_delete_term( $term_id, 'music_genre' );
	wp_safe_redirect( vatan_music_admin_url( 'genres', array( 'msg' => 'deleted' ) ) );
	exit;
}

/**
 * Read ID3 metadata from an audio file.
 *
 * Uses getID3 library if available, falls back to wp_read_audio_metadata.
 *
 * @param string $file_path Path to the audio file.
 * @return array { title, artist, album, album_art, duration, track_number, year, genre }
 */
function vatan_music_admin_read_id3( string $file_path ): array {
	$result = array(
		'title'        => '',
		'artist'       => '',
		'album'        => '',
		'album_art'    => '',
		'duration'     => 0,
		'track_number' => 0,
		'year'         => '',
		'genre'        => '',
	);

	if ( ! file_exists( $file_path ) ) {
		return $result;
	}

	// Read raw ID3v1 tag (last 128 bytes of file).
	$fp = fopen( $file_path, 'rb' );
	if ( ! $fp ) {
		return $result;
	}

	// Get file size.
	fseek( $fp, 0, SEEK_END );
	$file_size = ftell( $fp );

	// Try ID3v2 first (at beginning of file).
	$has_id3v2 = false;
	fseek( $fp, 0, SEEK_SET );
	$header = fread( $fp, 10 );
	if ( strlen( $header ) >= 10 && substr( $header, 0, 3 ) === 'ID3' ) {
		$has_id3v2 = true;
		$version_major = ord( $header[3] );

		// Calculate ID3v2 tag size (synchsafe integer).
		$size_bytes = substr( $header, 6, 4 );
		$tag_size = ( ord( $size_bytes[0] ) << 21 )
			| ( ord( $size_bytes[1] ) << 14 )
			| ( ord( $size_bytes[2] ) << 7 )
			| ord( $size_bytes[3] );
		$tag_size += 10; // Header size.

		// Read entire ID3v2 tag.
		fseek( $fp, 0, SEEK_SET );
		$tag_data = fread( $fp, $tag_size );

		// Parse ID3v2 frames.
		$offset = 10;
		while ( $offset < $tag_size - 10 ) {
			$frame_id = substr( $tag_data, $offset, 4 );
			if ( $frame_id === "\0\0\0\0" ) {
				break;
			}

			if ( $version_major >= 4 ) {
				// ID3v2.4: synchsafe size.
				$frame_size = ( ord( $tag_data[ $offset + 4 ] ) << 21 )
					| ( ord( $tag_data[ $offset + 5 ] ) << 14 )
					| ( ord( $tag_data[ $offset + 6 ] ) << 7 )
					| ord( $tag_data[ $offset + 7 ] );
			} else {
				// ID3v2.3 and earlier: normal size.
				$frame_size = ( ord( $tag_data[ $offset + 4 ] ) << 24 )
					| ( ord( $tag_data[ $offset + 5 ] ) << 16 )
					| ( ord( $tag_data[ $offset + 6 ] ) << 8 )
					| ord( $tag_data[ $offset + 7 ] );
			}

			$frame_data = substr( $tag_data, $offset + 10, $frame_size );
			$offset += 10 + $frame_size;

			if ( strlen( $frame_data ) < 2 ) {
				continue;
			}

			// Detect encoding: 0=ISO-8859-1, 1=UTF-16, 2=UTF-16BE, 3=UTF-8.
			$encoding = ord( $frame_data[0] );
			$text = substr( $frame_data, 1 );

			// Remove BOM if present.
			if ( $encoding === 1 && substr( $text, 0, 2 ) === "\xFF\xFE" ) {
				$text = substr( $text, 2 );
			} elseif ( $encoding === 1 && substr( $text, 0, 2 ) === "\xFE\xFF" ) {
				$text = substr( $text, 2 );
			}

			// Decode text.
			if ( $encoding === 0 ) {
				$text = trim( $text, "\0" );
			} elseif ( $encoding === 1 || $encoding === 2 ) {
				$text = @mb_convert_encoding( $text, 'UTF-8', 'UTF-16LE' );
				$text = trim( $text, "\0" );
			} elseif ( $encoding === 3 ) {
				$text = trim( $text, "\0" );
			}

			// Remove trailing null bytes and description.
			$null_pos = strpos( $text, "\0" );
			if ( $null_pos !== false ) {
				$text = substr( $text, 0, $null_pos );
			}

			switch ( $frame_id ) {
				case 'TIT2':
					$result['title'] = trim( $text );
					break;
				case 'TPE1':
					$result['artist'] = trim( $text );
					break;
				case 'TALB':
					$result['album'] = trim( $text );
					break;
				case 'TDRC':
				case 'TYER':
					$result['year'] = trim( $text );
					break;
				case 'TRCK':
					$parts = explode( '/', $text );
					$result['track_number'] = (int) trim( $parts[0] );
					break;
				case 'TCON':
					$result['genre'] = trim( $text );
					break;
				case 'APIC':
					// Album art: encoding(1) + mime(null-term) + type(1) + desc(null-term) + data.
					$apic_parts = explode( "\0", substr( $frame_data, 1 ), 3 );
					if ( count( $apic_parts ) >= 3 ) {
						$apic_mime = $apic_parts[0];
						$apic_data_start = strlen( $apic_parts[0] ) + 1 + 1 + strlen( $apic_parts[1] ) + 1;
						$apic_data = substr( $frame_data, 1 + $apic_data_start );
						if ( ! empty( $apic_data ) ) {
							$ext = 'jpg';
							if ( str_contains( $apic_mime, 'png' ) ) {
								$ext = 'png';
							}
							$tmp = wp_tempnam( 'albumart' );
							if ( $tmp ) {
								$new_tmp = $tmp . '.' . $ext;
								file_put_contents( $new_tmp, $apic_data );
								unlink( $tmp );
								$result['album_art'] = $new_tmp;
							}
						}
					}
					break;
			}
		}
	}

	// Try ID3v1 (last 128 bytes) as fallback for missing fields.
	if ( $file_size >= 128 ) {
		fseek( $fp, $file_size - 128, SEEK_SET );
		$tag = fread( $fp, 128 );
		if ( substr( $tag, 0, 3 ) === 'TAG' ) {
			if ( empty( $result['title'] ) ) {
				$result['title'] = trim( substr( $tag, 3, 30 ) );
			}
			if ( empty( $result['artist'] ) ) {
				$result['artist'] = trim( substr( $tag, 33, 30 ) );
			}
			if ( empty( $result['album'] ) ) {
				$result['album'] = trim( substr( $tag, 63, 30 ) );
			}
			if ( empty( $result['year'] ) ) {
				$result['year'] = trim( substr( $tag, 93, 4 ) );
			}
			$track_byte = ord( $tag[126] );
			if ( empty( $result['track_number'] ) && $track_byte > 0 ) {
				$result['track_number'] = $track_byte;
			}
			if ( empty( $result['genre'] ) && ord( $tag[127] ) < 255 ) {
				$genres = array(
					'Blues','Classic Rock','Country','Dance','Disco','Funk',
					'Grunge','Hip-Hop','Jazz','Metal','New Age','Oldies',
					'Other','Pop','R&B','Rap','Reggae','Rock','Techno',
					'Industrial','Alternative','Ska','Death Metal','Pranks',
					'Soundtrack','Euro-Techno','Ambient','Trip-Hop','Vocal',
					'Jazz+Funk','Fusion','Trance','Classical','Instrumental',
					'Acid','House','Game','Sound Clip','Gospel','Noise',
					'AlternRock','Bass','Soul','Punk','Space','Meditative',
					'Instrumental Pop','Instrumental Rock','Ethnic','Gothic',
					'Darkwave','Techno-Industrial','Electronic','Pop-Folk',
					'Eurodance','Dream','Southern Rock','Comedy','Cult',
					'Gangsta','Top 40','Christian Rap','Pop/Punk','Jungle',
					'Native American','Cabaret','New Wave','Psychadelic',
					'Revival','Bhaajan','Avantgarde','Punk Rock','Drum & Bass',
					'Club-House','Hardcore','Terror','Indie','BritPop',
					'Negerpunk','Polsk Punk','Beat','Christian Gangsta Rap',
					'Heavy Metal','Black Metal','Crossover','Contemporary Christian',
					'Christian Rock','Merengue','Salsa','Thrash Metal','Anime',
					'Jpop','Synthpop','Abstract','Art Rock','Baroque',
					'Bhangra','Big Beat','Breakbeat','Chillout','Downtempo',
					'Dub','EBM','Eclectic','Electro','Electroclash',
					'Emo','Experimental','Garage','Global','IDM',
					'Industrial Breakbeat','Acid House','Prank','Psychadelic Rock',
					'Slowrave','Tribal','Vocal Harmony','Worldbeat',
				);
				$genre_id = ord( $tag[127] );
				if ( $genre_id < count( $genres ) ) {
					$result['genre'] = $genres[ $genre_id ];
				}
			}
		}
	}

	fclose( $fp );

	// Get duration from audio stream info.
	if ( empty( $result['duration'] ) && function_exists( 'finfo_open' ) ) {
		// Estimate duration from file size and bitrate.
		$fp2 = fopen( $file_path, 'rb' );
		if ( $fp2 ) {
			// Skip ID3v2 header if present.
			$skip = 0;
			$h = fread( $fp2, 10 );
			if ( strlen( $h ) >= 10 && substr( $h, 0, 3 ) === 'ID3' ) {
				$skip = ( ( ord( $h[6] ) << 21 ) | ( ord( $h[7] ) << 14 ) | ( ord( $h[8] ) << 7 ) | ord( $h[9] ) ) + 10;
			}
			fseek( $fp2, $skip, SEEK_SET );
			$probe = fread( $fp2, 8192 );
			fclose( $fp2 );

			// Try to detect bitrate from MPEG frame header.
			if ( strlen( $probe ) > 4 ) {
				for ( $i = 0; $i < strlen( $probe ) - 4; $i++ ) {
					if ( ord( $probe[ $i ] ) === 0xFF && ( ord( $probe[ $i + 1 ] ) & 0xE0 ) === 0xE0 ) {
						$bits = ord( $probe[ $i + 1 ] );
						$layer = ( $bits >> 1 ) & 3;
						$bitrate_idx = ( ord( $probe[ $i + 2 ] ) >> 4 ) & 0x0F;
						$bitrates = array(
							1 => array( 0,32,40,48,56,64,80,96,112,128,160,192,224,256,320,0 ),
							2 => array( 0,8,16,24,32,40,48,56,64,80,96,112,128,144,160,0 ),
							3 => array( 0,8,16,24,32,40,48,56,64,80,96,112,128,144,160,0 ),
						);
						$bitrate = $bitrates[ $layer ][ $bitrate_idx ] ?? 128;
						if ( $bitrate > 0 ) {
							$effective_size = $file_size - $skip;
							$result['duration'] = (int) round( ( $effective_size * 8 ) / ( $bitrate * 1000 ) );
						}
						break;
					}
				}
			}
		}
	}

	return $result;
}

/**
 * Find or create an artist by name.
 *
 * @param string $name Artist name.
 * @return int Artist post ID, or 0 if not found/created.
 */
function vatan_music_admin_find_or_create_artist( string $name ): int {
	$name = trim( $name );
	if ( '' === $name ) {
		return 0;
	}

	// Search existing artists.
	$existing = get_posts( array(
		'post_type'      => 'artist',
		'post_status'    => 'publish',
		'title'          => $name,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );

	if ( $existing ) {
		return (int) $existing[0];
	}

	// Create new artist.
	$post_id = wp_insert_post( array(
		'post_type'   => 'artist',
		'post_status' => 'publish',
		'post_title'  => $name,
	) );

	return is_wp_error( $post_id ) ? 0 : (int) $post_id;
}

/**
 * Find or create an album by title.
 *
 * @param string $title Album title.
 * @param int    $artist_id Optional artist ID to link.
 * @return int Album post ID, or 0 if not found/created.
 */
function vatan_music_admin_find_or_create_album( string $title, int $artist_id = 0 ): int {
	$title = trim( $title );
	if ( '' === $title ) {
		return 0;
	}

	// Search existing albums.
	$existing = get_posts( array(
		'post_type'      => 'album',
		'post_status'    => 'publish',
		'title'          => $title,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );

	if ( $existing ) {
		return (int) $existing[0];
	}

	// Create new album.
	$post_id = wp_insert_post( array(
		'post_type'   => 'album',
		'post_status' => 'publish',
		'post_title'  => $title,
	) );

	if ( ! is_wp_error( $post_id ) && $post_id && $artist_id ) {
		update_post_meta( $post_id, 'album_artist', $artist_id );
		update_post_meta( $post_id, '_album_artist', 'field_vatan_album_artist' );
	}

	return is_wp_error( $post_id ) ? 0 : (int) $post_id;
}

/**
 * Set cover art from a file path.
 *
 * @param int    $post_id  Post ID.
 * @param string $file_path Path to image file.
 */
function vatan_music_admin_set_cover_from_file( int $post_id, string $file_path ): void {
	if ( ! $post_id ) {
		error_log( 'VATAN COVER: no post_id' );
		return;
	}
	if ( ! file_exists( $file_path ) ) {
		error_log( 'VATAN COVER: file not found: ' . $file_path );
		return;
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$mime = mime_content_type( $file_path );
	$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
	if ( ! in_array( $mime, $allowed, true ) ) {
		unlink( $file_path );
		return;
	}

	$ext = 'jpg';
	if ( str_contains( $mime, 'png' ) ) {
		$ext = 'png';
	} elseif ( str_contains( $mime, 'webp' ) ) {
		$ext = 'webp';
	} elseif ( str_contains( $mime, 'gif' ) ) {
		$ext = 'gif';
	}

	$upload_dir = wp_upload_dir();
	$filename = wp_unique_filename( $upload_dir['path'], 'albumart.' . $ext );
	$new_file = $upload_dir['path'] . '/' . $filename;
	// Use copy instead of move_uploaded_file — the source is an ID3-extracted
	// temp file, not a PHP $_FILES upload.
	if ( ! copy( $file_path, $new_file ) ) {
		error_log( 'VATAN COVER: copy failed from ' . $file_path . ' to ' . $new_file );
		return;
	}
	error_log( 'VATAN COVER: copied to ' . $new_file );

	$attachment = array(
		'post_mime_type' => $mime,
		'post_title'     => sanitize_file_name( pathinfo( $new_file, PATHINFO_FILENAME ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	$attach_id = wp_insert_attachment( $attachment, $new_file );
	if ( ! is_wp_error( $attach_id ) && $attach_id ) {
		$attach_data = wp_generate_attachment_metadata( $attach_id, $new_file );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		set_post_thumbnail( $post_id, $attach_id );
		error_log( 'VATAN COVER: set cover on post #' . $post_id . ' with attachment #' . $attach_id );
	} else {
		error_log( 'VATAN COVER: wp_insert_attachment failed for post #' . $post_id . ' — ' . ( is_wp_error( $attach_id ) ? $attach_id->get_error_message() : 'false' ) );
	}
}

/**
 * Wrap wp_handle_upload + wp_insert_attachment in a single helper that
 * returns the new attachment ID (or 0 on failure). Validates against a
 * mime allow-list so a misnamed file can't pretend to be audio/image.
 *
 * @param array    $file_array  $_FILES entry.
 * @param string[] $allowed     Allowed MIME types.
 */
function vatan_music_admin_handle_upload( array $file_array, array $allowed ): int {
	if ( ! function_exists( 'media_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	// Validate mime type before handing off to WordPress.
	$detected = wp_check_filetype_and_ext( $file_array['tmp_name'], $file_array['name'] );
	$mime     = (string) ( $detected['type'] ?? '' );
	if ( ! $mime ) {
		$fallback = wp_check_filetype( $file_array['name'] );
		$mime     = (string) ( $fallback['type'] ?? '' );
	}
	if ( ! $mime || ! in_array( $mime, $allowed, true ) ) {
		error_log( 'VATAN UPLOAD: rejected ' . ( $file_array['name'] ?? '' ) . ' mime=' . $mime );
		return 0;
	}

	// Use WordPress's built-in media upload — handles attachment creation,
	// metadata generation, and thumbnail support correctly.
	$_FILES['vatan_upload'] = $file_array;
	$attach_id = media_handle_upload( 'vatan_upload', 0 );
	unset( $_FILES['vatan_upload'] );

	if ( is_wp_error( $attach_id ) ) {
		error_log( 'VATAN UPLOAD: media_handle_upload failed for ' . ( $file_array['name'] ?? '' ) . ' — ' . $attach_id->get_error_message() );
		return 0;
	}

	error_log( 'VATAN UPLOAD: attachment #' . $attach_id . ' status=' . get_post_status( $attach_id ) );
	return (int) $attach_id;
}

/**
 * Render a hidden form that submits a music admin POST action.
 *
 * Used by the list-view templates so each row's button gets its own
 * nonce-protected form without page-wide POST handling getting confused.
 *
 * @param string $action      One of: delete | toggle-featured | untrash.
 * @param int    $id          Post ID.
 * @param string $button_html The button markup (already-escaped HTML).
 * @param array  $button_attrs Optional extra attributes for the form's button.
 */
function vatan_music_admin_action_form( string $action, int $id, string $button_html, array $button_attrs = array() ): void {
	$nonce = wp_create_nonce( 'vatan_music_admin_' . $action . '_' . $id );
	$attrs = '';
	foreach ( $button_attrs as $k => $v ) {
		$attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	?>
	<form method="post" action="" class="vatan-music-admin__action-form" style="display:inline">
		<input type="hidden" name="vatan_music_action" value="<?php echo esc_attr( $action ); ?>" />
		<input type="hidden" name="id" value="<?php echo (int) $id; ?>" />
		<input type="hidden" name="_vatan_music_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
		<button type="submit"<?php echo $attrs; ?>><?php echo $button_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — caller escapes ?></button>
	</form>
	<?php
}

/**
 * Resolve a cover URL for a music post — falls back through the
 * track → album → artist chain so an editor table never shows a blank
 * cell when the album has art but the track itself doesn't.
 */
if ( ! function_exists( 'vatan_admin_music_cover' ) ) {
	function vatan_admin_music_cover( int $post_id, string $size = 'thumbnail' ): string {
		if ( has_post_thumbnail( $post_id ) ) {
			return (string) get_the_post_thumbnail_url( $post_id, $size );
		}
		if ( function_exists( 'get_field' ) ) {
			$album_id = (int) get_field( 'track_album', $post_id );
			if ( $album_id && has_post_thumbnail( $album_id ) ) {
				return (string) get_the_post_thumbnail_url( $album_id, $size );
			}
			$artist_id = (int) get_field( 'track_artist', $post_id );
			if ( $artist_id && has_post_thumbnail( $artist_id ) ) {
				return (string) get_the_post_thumbnail_url( $artist_id, $size );
			}
		}
		return '';
	}
}

/* =============================================================================
 *  AJAX endpoint for detecting ID3 metadata from uploaded audio files.
 * ===========================================================================*/

add_action( 'wp_ajax_vatan_music_detect_meta', 'vatan_music_admin_ajax_detect_meta' );

function vatan_music_admin_ajax_detect_meta(): void {
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}

	$nonce = isset( $_POST['nonce'] ) ? (string) wp_unslash( $_POST['nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'vatan_music_batch_upload' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
	}

	if ( empty( $_FILES['audio_file'] ) ) {
		wp_send_json_error( array( 'message' => 'No file uploaded' ) );
	}

	$file = $_FILES['audio_file'];
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		wp_send_json_error( array( 'message' => 'Upload error' ) );
	}

	// Check file type.
	$detected = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
	$mime     = (string) ( $detected['type'] ?? '' );
	$allowed  = array( 'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/aac', 'audio/x-m4a', 'audio/ogg', 'audio/wav' );
	if ( ! in_array( $mime, $allowed, true ) ) {
		wp_send_json_error( array( 'message' => 'Invalid file type' ) );
	}

	// Read ID3 metadata.
	$meta = vatan_music_admin_read_id3( $file['tmp_name'] );

	// Clean up temp album art if present.
	if ( ! empty( $meta['album_art'] ) && file_exists( $meta['album_art'] ) ) {
		unlink( $meta['album_art'] );
		$meta['album_art'] = ''; // Don't return file path to client.
	}

	wp_send_json_success( $meta );
}

/* =============================================================================
 *  Batch upload handler — processes multiple audio files at once.
 * ===========================================================================*/

add_action( 'template_redirect', 'vatan_music_admin_handle_batch_upload', 5 );

function vatan_music_admin_handle_batch_upload(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
	if ( empty( $_POST['vatan_music_batch_upload'] ) ) return;
	if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) return;

	$nonce = isset( $_POST['_vatan_music_batch_nonce'] ) ? (string) wp_unslash( $_POST['_vatan_music_batch_nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'vatan_music_batch_upload' ) ) {
		$debug = 'VATAN BATCH DEBUG: nonce failed. POST=' . wp_json_encode( array_keys( $_POST ) ) . ' FILES=' . wp_json_encode( isset( $_FILES['batch_audio_files'] ) ? 'present' : 'missing' );
		error_log( $debug );
		wp_die( $debug );
	}

	if ( ! function_exists( 'wp_handle_upload' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) require_once ABSPATH . 'wp-admin/includes/image.php';
	if ( ! function_exists( 'wp_read_audio_metadata' ) ) require_once ABSPATH . 'wp-admin/includes/media.php';

	$artist_id      = isset( $_POST['batch_artist'] ) ? (int) $_POST['batch_artist'] : 0;
	$album_id       = isset( $_POST['batch_album'] ) ? (int) $_POST['batch_album'] : 0;
	$status         = isset( $_POST['batch_status'] ) && 'draft' === $_POST['batch_status'] ? 'draft' : 'publish';
	$auto_title     = ! empty( $_POST['batch_auto_title'] );
	$auto_number    = ! empty( $_POST['batch_auto_number'] );
	$genre_ids      = isset( $_POST['batch_genres'] ) && is_array( $_POST['batch_genres'] ) ? array_map( 'absint', $_POST['batch_genres'] ) : array();
	$use_detected   = ! empty( $_POST['batch_use_detected'] );
	$create_artist  = ! empty( $_POST['batch_create_artist'] );
	$create_album   = ! empty( $_POST['batch_create_album'] );

	$results = array(
		'created' => 0,
		'failed'  => 0,
		'errors'  => array(),
		'tracks'  => array(),
	);

	// Debug: log what we received
	$files_received = ! empty( $_FILES['batch_audio_files'] ) ? count( $_FILES['batch_audio_files']['name'] ) : 0;
	error_log( 'VATAN BATCH: POST keys = ' . implode( ', ', array_keys( $_POST ) ) );
	error_log( 'VATAN BATCH: FILES received = ' . $files_received );
	if ( ! empty( $_FILES['batch_audio_files'] ) ) {
		for ( $d = 0; $d < min( 3, count( $_FILES['batch_audio_files']['name'] ) ); $d++ ) {
			error_log( 'VATAN BATCH: file[' . $d . '] name=' . ( $_FILES['batch_audio_files']['name'][ $d ] ?? '' ) . ' error=' . ( $_FILES['batch_audio_files']['error'][ $d ] ?? '' ) . ' size=' . ( $_FILES['batch_audio_files']['size'][ $d ] ?? '' ) );
		}
	}

	if ( empty( $_FILES['batch_audio_files']['name'] ) || ! is_array( $_FILES['batch_audio_files']['name'] ) ) {
		error_log( 'VATAN BATCH: no files in $_FILES, redirecting with error' );
		wp_safe_redirect( vatan_music_admin_url( 'batch', array( 'err' => 'no_files' ) ) );
		exit;
	}

	$file_count = count( $_FILES['batch_audio_files']['name'] );
	$track_counter = 0;
	error_log( 'VATAN BATCH: processing ' . $file_count . ' files' );

	// Get existing track count for auto-numbering if album is set.
	if ( $auto_number && $album_id ) {
		$existing = (int) ( new WP_Query( array(
			'post_type'      => 'track',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => 'track_album', 'value' => $album_id, 'compare' => '=' ),
			),
		) ) )->found_posts;
		$track_counter = $existing;
	}

	for ( $i = 0; $i < $file_count; $i++ ) {
		$file_name = $_FILES['batch_audio_files']['name'][ $i ] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
		$tmp_name  = $_FILES['batch_audio_files']['tmp_name'][ $i ] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
		$error     = $_FILES['batch_audio_files']['error'][ $i ] ?? UPLOAD_ERR_NO_FILE; // phpcs:ignore WordPress.Security.NonceVerification

		if ( UPLOAD_ERR_OK !== (int) $error || empty( $file_name ) ) {
			if ( UPLOAD_ERR_OK !== (int) $error ) {
				$results['failed']++;
				$results['errors'][] = sprintf( __( 'Failed to upload "%s" — upload error.', 'vatan-event' ), $file_name );
			}
			continue;
		}

		$file_array = array(
			'name'     => $file_name,
			'type'     => $_FILES['batch_audio_files']['type'][ $i ] ?? '', // phpcs:ignore WordPress.Security.NonceVerification
			'tmp_name' => $tmp_name,
			'error'    => (int) $error,
			'size'     => $_FILES['batch_audio_files']['size'][ $i ] ?? 0, // phpcs:ignore WordPress.Security.NonceVerification
		);

		// Read ID3 BEFORE uploading — wp_handle_upload moves the tmp file.
		$id3 = vatan_music_admin_read_id3( $tmp_name );
		error_log( 'VATAN BATCH: id3 for ' . $file_name . ' = ' . wp_json_encode( $id3 ) );

		$audio_attachment_id = vatan_music_admin_handle_upload( $file_array, array( 'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/aac', 'audio/x-m4a', 'audio/ogg', 'audio/wav' ) );

		if ( ! $audio_attachment_id ) {
			error_log( 'VATAN BATCH: upload failed for ' . $file_name );
			$results['failed']++;
			$results['errors'][] = sprintf( __( 'Failed to upload "%s" — invalid file type.', 'vatan-event' ), $file_name );
			continue;
		}
		error_log( 'VATAN BATCH: uploaded ' . $file_name . ' as attachment #' . $audio_attachment_id );

		// Determine title: ID3 > filename.
		$title = '';
		if ( $use_detected && ! empty( $id3['title'] ) ) {
			$title = $id3['title'];
		} elseif ( $auto_title ) {
			$title = pathinfo( $file_name, PATHINFO_FILENAME );
			$title = preg_replace( '/[-_]/', ' ', $title );
			$title = ucwords( trim( $title ) );
		}
		if ( empty( $title ) ) {
			$title = pathinfo( $file_name, PATHINFO_FILENAME );
		}

		// Determine artist: ID3 > manual selection > auto-create.
		$track_artist_id = $artist_id;
		error_log( 'VATAN BATCH: artist_id=' . $track_artist_id . ' use_detected=' . ( $use_detected ? 'yes' : 'no' ) . ' id3_artist=' . ( $id3['artist'] ?? '' ) . ' create_artist=' . ( $create_artist ? 'yes' : 'no' ) );
		if ( $use_detected && ! empty( $id3['artist'] ) && ! $track_artist_id ) {
			// Try to find existing artist by name.
			$existing_artists = get_posts( array(
				'post_type'      => 'artist',
				'post_status'    => 'publish',
				'title'          => $id3['artist'],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );
			if ( $existing_artists ) {
				$track_artist_id = (int) $existing_artists[0];
			} elseif ( $create_artist ) {
				$track_artist_id = vatan_music_admin_find_or_create_artist( $id3['artist'] );
			}
		}

		// Determine album: ID3 > manual selection > auto-create.
		$track_album_id = $album_id;
		error_log( 'VATAN BATCH: album_id=' . $track_album_id . ' id3_album=' . ( $id3['album'] ?? '' ) . ' create_album=' . ( $create_album ? 'yes' : 'no' ) );
		if ( $use_detected && ! empty( $id3['album'] ) && ! $track_album_id ) {
			// Try to find existing album by title.
			$existing_albums = get_posts( array(
				'post_type'      => 'album',
				'post_status'    => 'publish',
				'title'          => $id3['album'],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );
			if ( $existing_albums ) {
				$track_album_id = (int) $existing_albums[0];
			} elseif ( $create_album ) {
				$track_album_id = vatan_music_admin_find_or_create_album( $id3['album'], $track_artist_id );
			}
		}

		// Duration from ID3 or attachment metadata.
		$duration = null;
		if ( ! empty( $id3['duration'] ) ) {
			$duration = (int) $id3['duration'];
		} else {
			$meta = wp_get_attachment_metadata( $audio_attachment_id );
			if ( is_array( $meta ) && isset( $meta['length'] ) ) {
				$duration = (int) $meta['length'];
			}
		}

		// Track number: ID3 > auto-number.
		$track_number = null;
		if ( $use_detected && ! empty( $id3['track_number'] ) ) {
			$track_number = (int) $id3['track_number'];
		} elseif ( $auto_number ) {
			$track_counter++;
			$track_number = $track_counter;
		}

		// Create the track post.
		$post_data = array(
			'post_type'   => 'track',
			'post_status' => $status,
			'post_title'  => $title,
		);
		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$results['failed']++;
			$results['errors'][] = sprintf( __( 'Failed to create track for "%s".', 'vatan-event' ), $file_name );
			continue;
		}

		// Set meta fields.
		if ( $track_artist_id ) {
			update_post_meta( $post_id, 'track_artist', $track_artist_id );
			update_post_meta( $post_id, '_track_artist', 'field_vatan_track_artist' );
			error_log( 'VATAN BATCH: set artist_id=' . $track_artist_id . ' for track #' . $post_id );
		}
		if ( $track_album_id ) {
			update_post_meta( $post_id, 'track_album', $track_album_id );
			update_post_meta( $post_id, '_track_album', 'field_vatan_track_album' );
			error_log( 'VATAN BATCH: set album_id=' . $track_album_id . ' for track #' . $post_id );
		}
		if ( null !== $track_number ) {
			update_post_meta( $post_id, 'track_track_number', max( 1, $track_number ) );
			update_post_meta( $post_id, '_track_track_number', 'field_vatan_track_track_number' );
		}
		if ( null !== $duration ) {
			update_post_meta( $post_id, 'track_duration_seconds', max( 0, $duration ) );
			update_post_meta( $post_id, '_track_duration_seconds', 'field_vatan_track_duration_seconds' );
		}

		// Set audio file.
		update_post_meta( $post_id, 'track_audio_file', $audio_attachment_id );
		update_post_meta( $post_id, '_track_audio_file', 'field_vatan_track_audio_file' );

		// Set genres.
		if ( $genre_ids ) {
			wp_set_object_terms( $post_id, $genre_ids, 'music_genre', false );
		}

		// Set cover art from ID3 if available.
		if ( ! empty( $id3['album_art'] ) && file_exists( $id3['album_art'] ) ) {
			// Set cover on the track itself.
			if ( ! has_post_thumbnail( $post_id ) ) {
				vatan_music_admin_set_cover_from_file( $post_id, $id3['album_art'] );
			}
			// Also set cover on the album if it doesn't have one yet.
			if ( $track_album_id && ! has_post_thumbnail( $track_album_id ) && file_exists( $id3['album_art'] ) ) {
				vatan_music_admin_set_cover_from_file( $track_album_id, $id3['album_art'] );
			}
		}

		$results['created']++;
		$results['tracks'][] = array(
			'id'    => $post_id,
			'title' => $title,
		);

		// Clean up the ID3-extracted album art temp file after use.
		if ( ! empty( $id3['album_art'] ) && file_exists( $id3['album_art'] ) ) {
			unlink( $id3['album_art'] );
		}
	}

	// Store results in session for display after redirect.
	error_log( 'VATAN BATCH: completed — created=' . $results['created'] . ' failed=' . $results['failed'] );
	if ( session_id() ) {
		$_SESSION['vatan_batch_results'] = $results;
	} else {
		error_log( 'VATAN BATCH: no session, cannot store results' );
	}

	wp_safe_redirect( vatan_music_admin_url( 'batch', array( 'msg' => 'batch_done', 'n' => $results['created'] ) ) );
	exit;
}

/**
 * One-off flash banner shown above the list when ?msg=… is present.
 * Map of known message keys to translated strings.
 */
function vatan_music_admin_flash_message(): string {
	$msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$n   = isset( $_GET['n'] ) ? (int) $_GET['n'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$num = vatan_to_persian_digits( max( 1, $n ) );
	$map = array(
		'deleted'        => __( 'Item moved to trash.', 'vatan-event' ),
		'restored'       => __( 'Item restored from trash.', 'vatan-event' ),
		'featured'       => __( 'Marked as featured.', 'vatan-event' ),
		'unfeatured'     => __( 'Removed from featured.', 'vatan-event' ),
		'saved'          => __( 'Saved.', 'vatan-event' ),
		/* translators: %s: number of items affected */
		'bulk_trash'     => sprintf( __( '%s items moved to trash.', 'vatan-event' ), $num ),
		'bulk_untrash'   => sprintf( __( '%s items restored.', 'vatan-event' ), $num ),
		'bulk_feature'   => sprintf( __( '%s items marked as featured.', 'vatan-event' ), $num ),
		'bulk_unfeature' => sprintf( __( '%s items removed from featured.', 'vatan-event' ), $num ),
		'batch_done'     => sprintf( __( '%s tracks uploaded successfully.', 'vatan-event' ), $num ),
	);
	return $map[ $msg ] ?? '';
}

/**
 * Error banner — analogue of the success flash above, but read from
 * `?err=` and rendered in red.
 */
function vatan_music_admin_error_message(): string {
	$err = isset( $_GET['err'] ) ? sanitize_key( wp_unslash( $_GET['err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$map = array(
		'title'    => __( 'Title is required.', 'vatan-event' ),
		'save'     => __( 'Could not save — please try again.', 'vatan-event' ),
		'upload'   => __( 'File upload failed. Check the file type and size.', 'vatan-event' ),
		'no_files' => __( 'No files selected for upload.', 'vatan-event' ),
		'nonce'    => __( 'Security check failed. Please try again.', 'vatan-event' ),
	);
	return $map[ $err ] ?? '';
}
