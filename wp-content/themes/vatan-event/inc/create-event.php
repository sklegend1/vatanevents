<?php
/**
 * Frontend "Create Event" flow.
 *
 * Lets logged-in users submit an event from `/create-event/` without ever
 * touching wp-admin. New submissions land as `pending` events for admin
 * moderation. ACF fields, taxonomies, ticket-types repeater, and featured
 * image are all populated server-side from the form payload.
 *
 * Architecture:
 *   - `page-create-event.php` renders the form (slug = `create-event`).
 *   - On submit the form posts to itself with a hidden action token. We
 *     intercept at `template_redirect`, validate + create the post + handle
 *     uploads, then redirect back with a success/error code in the query
 *     string so the template can show a feedback message without leaking
 *     POST data to the page render.
 *   - Existing helpers (taxonomy registration, ACF field group, WC product
 *     sync via `save_post_event`) handle everything downstream of the
 *     `wp_insert_post()` call — including auto-generating the
 *     `event_ticket` products once the admin publishes the submission.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_CREATE_EVENT_ACTION = 'vatan_submit_event';
const VATAN_CREATE_EVENT_NONCE  = 'vatan_submit_event_nonce';

/**
 * Can the current user edit this event from the frontend? Owners (matched
 * by `_vatan_submitted_by` meta) can edit; admins always can; everyone
 * else is blocked. Returns false for non-event posts and missing IDs.
 *
 * @param int $event_id
 * @param int $user_id  Defaults to current user.
 * @return bool
 */
function vatan_create_event_user_can_edit( $event_id, $user_id = 0 ) {
	$event_id = (int) $event_id;
	$user_id  = (int) ( $user_id ?: get_current_user_id() );
	if ( ! $event_id || ! $user_id ) {
		return false;
	}
	if ( 'event' !== get_post_type( $event_id ) ) {
		return false;
	}
	if ( user_can( $user_id, 'edit_others_posts' ) ) {
		return true; // admins / editors can edit anything
	}
	$submitter = (int) get_post_meta( $event_id, '_vatan_submitted_by', true );
	if ( $submitter && $submitter === $user_id ) {
		return true;
	}
	// Legacy fallback: events without the meta should still be editable
	// by their post_author. The my-events backfill normally fills this in
	// on admin_init, but a never-logged-in admin would leave it blank.
	return ( (int) get_post_field( 'post_author', $event_id ) === $user_id );
}

/**
 * Load an existing event into the field shape the create-event form
 * expects. Used to pre-fill the form when `?edit=ID` is in the URL.
 *
 * @param int $event_id
 * @return array
 */
function vatan_create_event_load_existing( $event_id ) {
	$event_id = (int) $event_id;
	if ( ! $event_id ) {
		return array();
	}
	$post = get_post( $event_id );
	if ( ! $post || 'event' !== $post->post_type ) {
		return array();
	}

	$get = function ( $key ) use ( $event_id ) {
		return function_exists( 'get_field' )
			? get_field( $key, $event_id )
			: get_post_meta( $event_id, $key, true );
	};

	$cat_terms  = wp_get_post_terms( $event_id, 'event_category', array( 'fields' => 'ids' ) );
	$city_terms = wp_get_post_terms( $event_id, 'event_city',     array( 'fields' => 'ids' ) );

	return array(
		'title'      => $post->post_title,
		'excerpt'    => $post->post_excerpt,
		'content'    => $post->post_content,
		'date'       => (string) $get( 'event_date' ),
		'time_start' => (string) $get( 'event_time_start' ),
		'time_end'   => (string) $get( 'event_time_end' ),
		'venue'      => (string) $get( 'event_venue' ),
		'venue_map'  => (string) $get( 'event_venue_map_link' ),
		'duration'   => (int)    $get( 'event_duration' ),
		'age_limit'  => (int)    $get( 'event_age_limit' ),
		'category'   => ! is_wp_error( $cat_terms )  && ! empty( $cat_terms )  ? (int) $cat_terms[0]  : 0,
		'city'       => ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ? (int) $city_terms[0] : 0,
		'tickets'    => (array) ( $get( 'ticket_types' ) ?: array() ),
		'thumbnail'  => (int) get_post_thumbnail_id( $event_id ),
		'seat_map_enabled' => (bool) $get( 'seat_map_enabled' ),
		'seat_map_rows'    => (int)  $get( 'seat_map_rows' ),
		'seat_map_cols'    => (int)  $get( 'seat_map_cols' ),
		'seat_map_config'  => (string) $get( 'seat_map_config' ),
	);
}

/**
 * Capability gate. Lets us tighten access later (custom role / membership
 * plugin / etc.) without touching the template.
 *
 * @return bool
 */
function vatan_can_submit_event() {
	/**
	 * Filter who can submit events from the front-end.
	 *
	 * @param bool $allowed Default: true for any logged-in user.
	 */
	return (bool) apply_filters( 'vatan_can_submit_event', is_user_logged_in() );
}

/**
 * Intercept the submission. Runs at template_redirect so we can wp_safe_redirect
 * after writing.
 */
function vatan_handle_create_event_submit() {
	if ( empty( $_POST['vatan_create_event_action'] ) ) {
		return;
	}
	if ( VATAN_CREATE_EVENT_ACTION !== $_POST['vatan_create_event_action'] ) {
		return;
	}

	$nonce = isset( $_POST[ VATAN_CREATE_EVENT_NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ VATAN_CREATE_EVENT_NONCE ] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, VATAN_CREATE_EVENT_ACTION ) ) {
		vatan_create_event_redirect( 'bad_nonce' );
	}
	if ( ! vatan_can_submit_event() ) {
		vatan_create_event_redirect( 'forbidden' );
	}

	$is_edit_request = ! empty( $_POST['event_id'] );

	$result = vatan_create_event_process_submission( $_POST, $_FILES );
	if ( is_wp_error( $result ) ) {
		// Persist the error code + any individual field errors in a transient
		// so the page render can replay them next request.
		set_transient(
			'vatan_create_event_errors_' . get_current_user_id(),
			array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
				'fields'  => (array) $result->get_error_data(),
			),
			MINUTE_IN_SECONDS * 5
		);
		// Bounce back to the edit URL so the form re-loads pre-filled.
		$args = $is_edit_request ? array( 'edit' => (int) $_POST['event_id'] ) : array();
		vatan_create_event_redirect( 'error', $args );
	}

	$status = $is_edit_request ? 'updated' : 'success';
	$args   = array( 'event_id' => (int) $result );
	if ( $is_edit_request ) {
		// Stay on the edit URL so the organizer can keep tweaking.
		$args['edit'] = (int) $result;
	}
	vatan_create_event_redirect( $status, $args );
}
add_action( 'template_redirect', 'vatan_handle_create_event_submit', 1 );

/**
 * Build the redirect URL after handling a submission and exit.
 *
 * @param string $status
 * @param array  $extra
 */
function vatan_create_event_redirect( $status, $extra = array() ) {
	$base = vatan_static_page_url( 'create-event' );
	if ( ! $base ) {
		$base = home_url( '/create-event/' );
	}
	$args = array_merge( array( 'vatan_status' => $status ), $extra );
	wp_safe_redirect( add_query_arg( $args, $base ) );
	exit;
}

/**
 * Validate + create the event post from submitted data.
 *
 * @param array $post   POST payload (raw).
 * @param array $files  $_FILES payload (raw).
 * @return int|WP_Error Post ID on success, WP_Error on failure.
 */
function vatan_create_event_process_submission( $post, $files ) {
	$title = isset( $post['event_title'] ) ? sanitize_text_field( wp_unslash( $post['event_title'] ) ) : '';
	$title = trim( $title );
	if ( '' === $title || mb_strlen( $title ) < 4 ) {
		return new WP_Error( 'title', __( 'Please give your event a title (at least 4 characters).', 'vatan-event' ), array( 'event_title' => 1 ) );
	}

	$excerpt = isset( $post['event_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $post['event_excerpt'] ) ) : '';
	$content = isset( $post['event_content'] ) ? wp_kses_post( wp_unslash( $post['event_content'] ) ) : '';

	$event_date = isset( $post['event_date'] ) ? sanitize_text_field( wp_unslash( $post['event_date'] ) ) : '';
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) ) {
		return new WP_Error( 'date', __( 'Please pick a valid event date.', 'vatan-event' ), array( 'event_date' => 1 ) );
	}
	if ( strtotime( $event_date ) < strtotime( current_time( 'Y-m-d' ) ) ) {
		return new WP_Error( 'date_past', __( 'The event date must be today or later.', 'vatan-event' ), array( 'event_date' => 1 ) );
	}

	$time_start = isset( $post['event_time_start'] ) ? sanitize_text_field( wp_unslash( $post['event_time_start'] ) ) : '';
	$time_end   = isset( $post['event_time_end'] )   ? sanitize_text_field( wp_unslash( $post['event_time_end'] ) )   : '';
	if ( $time_start && ! preg_match( '/^\d{2}:\d{2}$/', $time_start ) ) $time_start = '';
	if ( $time_end   && ! preg_match( '/^\d{2}:\d{2}$/', $time_end ) )   $time_end   = '';

	$venue     = isset( $post['event_venue'] )            ? sanitize_text_field( wp_unslash( $post['event_venue'] ) )         : '';
	$venue_map = isset( $post['event_venue_map_link'] )   ? esc_url_raw( wp_unslash( $post['event_venue_map_link'] ) )         : '';
	$duration  = isset( $post['event_duration'] )         ? max( 0, (int) $post['event_duration'] )                            : 0;
	$age_limit = isset( $post['event_age_limit'] )        ? max( 0, (int) $post['event_age_limit'] )                           : 0;

	$category_id = isset( $post['event_category'] ) ? (int) $post['event_category'] : 0;
	$city_id     = isset( $post['event_city'] )     ? (int) $post['event_city'] : 0;
	if ( $category_id < 1 ) {
		return new WP_Error( 'category', __( 'Please pick a category.', 'vatan-event' ), array( 'event_category' => 1 ) );
	}
	if ( $city_id < 1 ) {
		return new WP_Error( 'city', __( 'Please pick a city.', 'vatan-event' ), array( 'event_city' => 1 ) );
	}

	// Ticket types — at least one with a name + price.
	$tickets = vatan_create_event_parse_tickets( $post );
	if ( empty( $tickets ) ) {
		return new WP_Error( 'tickets', __( 'Add at least one ticket type (with a name and price).', 'vatan-event' ), array( 'tickets' => 1 ) );
	}

	// All validation done — create or update the post.
	$submitter_id = get_current_user_id();
	$edit_id      = isset( $post['event_id'] ) ? (int) $post['event_id'] : 0;
	$is_edit      = false;

	if ( $edit_id > 0 ) {
		if ( ! vatan_create_event_user_can_edit( $edit_id, $submitter_id ) ) {
			return new WP_Error( 'forbidden', __( 'You can\'t edit this event.', 'vatan-event' ) );
		}
		$is_edit = true;
	}

	if ( $is_edit ) {
		$update = wp_update_post( array(
			'ID'           => $edit_id,
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'post_content' => $content,
			// post_status intentionally NOT changed — a published event
			// stays published after an organizer edit. Admin can still
			// re-moderate from wp-admin if they want stricter control.
		), true );
		if ( is_wp_error( $update ) ) {
			return new WP_Error( 'update_failed', __( 'Could not save your changes. Please try again.', 'vatan-event' ) );
		}
		$post_id = $edit_id;
	} else {
		$post_id = wp_insert_post( array(
			'post_type'    => 'event',
			'post_status'  => 'pending',
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'post_content' => $content,
			'post_author'  => $submitter_id,
		), true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new WP_Error( 'insert_failed', __( 'Could not save your event. Please try again.', 'vatan-event' ) );
		}

		// Persist the original submitter on a dedicated meta key. `post_author`
		// can be reassigned by admins (and the block editor can quietly change
		// it on publish), so we record ownership-of-submission separately. The
		// My Events dashboard queries by this meta — never by `post_author` —
		// so the organizer keeps seeing their submissions even if admin owns
		// the post later.
		update_post_meta( $post_id, '_vatan_submitted_by', $submitter_id );
	}

	// Taxonomies.
	wp_set_object_terms( $post_id, array( $category_id ), 'event_category' );
	wp_set_object_terms( $post_id, array( $city_id ),     'event_city' );

	// Seat map — optional. When the toggle is on, parse & sanitize the JSON
	// the seat planner serialised into the hidden `seat_map_config` field.
	$seat_enabled = ! empty( $post['seat_map_enabled'] );
	$seat_rows    = isset( $post['seat_map_rows'] ) ? max( 1, min( 50, (int) $post['seat_map_rows'] ) ) : 0;
	$seat_cols    = isset( $post['seat_map_cols'] ) ? max( 1, min( 50, (int) $post['seat_map_cols'] ) ) : 0;
	$seat_config_json = '';
	if ( $seat_enabled ) {
		$raw = isset( $post['seat_map_config'] ) ? wp_unslash( (string) $post['seat_map_config'] ) : '';
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			// Clamp the dimensions so the editor's value wins if it's saner
			// than the bare rows/cols inputs (e.g. user typed huge numbers).
			if ( isset( $decoded['rows'] ) ) $seat_rows = max( 1, min( 50, (int) $decoded['rows'] ) );
			if ( isset( $decoded['cols'] ) ) $seat_cols = max( 1, min( 50, (int) $decoded['cols'] ) );
			$decoded['rows'] = $seat_rows;
			$decoded['cols'] = $seat_cols;
			$seat_config_json = wp_json_encode( $decoded );
		} else {
			// Bad JSON — fail soft, keep enabled flag but blank the config.
			$seat_config_json = '';
		}
	}

	// ACF / meta fields (use update_field when ACF is active).
	$meta = array(
		'event_date'           => $event_date,
		'event_time_start'     => $time_start,
		'event_time_end'       => $time_end,
		'event_venue'          => $venue,
		'event_venue_map_link' => $venue_map,
		'event_duration'       => $duration,
		'event_age_limit'      => $age_limit,
		'event_status'         => 'upcoming',
		'event_is_featured'    => 0,
		'ticket_types'         => $tickets,
		'seat_map_enabled'     => $seat_enabled ? 1 : 0,
		'seat_map_rows'        => $seat_enabled ? $seat_rows : 0,
		'seat_map_cols'        => $seat_enabled ? $seat_cols : 0,
		'seat_map_config'      => $seat_enabled ? $seat_config_json : '',
	);
	$has_acf = function_exists( 'update_field' );
	foreach ( $meta as $key => $value ) {
		if ( $has_acf ) {
			update_field( $key, $value, $post_id );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}

	// Featured image upload (optional).
	if ( ! empty( $files['event_image']['name'] ) ) {
		$attachment_id = vatan_create_event_handle_image_upload( $files['event_image'], $post_id );
		if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	// Notify admins only on the first submission. Edits don't pull admins
	// back into the loop — they can still see the change in the post list
	// via the modified date, and we don't want to spam their inbox each
	// time an organizer tweaks a typo.
	if ( ! $is_edit ) {
		vatan_create_event_notify_admins( $post_id );
	}

	/**
	 * Fires after a successful frontend create/update of an event.
	 *
	 * @param int  $post_id
	 * @param bool $is_edit  True for updates, false for new submissions.
	 */
	do_action( 'vatan_event_form_saved', (int) $post_id, $is_edit );

	return (int) $post_id;
}

/**
 * Pull ticket-type rows out of the submitted form into the ACF repeater
 * shape: [ ['ticket_name'=>…, 'ticket_price'=>…, 'ticket_capacity'=>…, 'ticket_color'=>…], … ].
 *
 * @param array $post
 * @return array
 */
function vatan_create_event_parse_tickets( $post ) {
	if ( empty( $post['ticket_types'] ) || ! is_array( $post['ticket_types'] ) ) {
		return array();
	}
	$out = array();
	foreach ( $post['ticket_types'] as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$name  = isset( $row['name'] ) ? sanitize_text_field( wp_unslash( $row['name'] ) ) : '';
		$price = isset( $row['price'] ) ? (float) $row['price'] : 0.0;
		$cap   = isset( $row['capacity'] ) ? max( 0, (int) $row['capacity'] ) : 0;
		$color = isset( $row['color'] ) ? sanitize_hex_color( wp_unslash( $row['color'] ) ) : '';
		if ( '' === $name || $price <= 0 ) {
			continue; // skip empty / unpriced rows
		}
		$out[] = array(
			'ticket_name'     => $name,
			'ticket_price'    => $price,
			'ticket_color'    => $color ?: '#7C3AED',
			'ticket_capacity' => $cap,
			'ticket_sold'     => 0,
		);
	}
	return $out;
}

/**
 * Handle the featured-image upload. Wraps WP's media_handle_upload so we
 * can pull in the required dependencies lazily (this code runs on the
 * front-end where they're not auto-loaded).
 *
 * @param array $file
 * @param int   $post_id
 * @return int|WP_Error Attachment ID or WP_Error.
 */
function vatan_create_event_handle_image_upload( $file, $post_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// media_handle_upload reads the file from $_FILES by key, so reset
	// $_FILES['event_image'] to the single file we want.
	$_FILES['vatan_event_image'] = $file;

	// Restrict accepted types to common image MIME types.
	$overrides = array(
		'test_form' => false,
		'mimes'     => array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		),
	);
	return media_handle_upload( 'vatan_event_image', $post_id, array(), $overrides );
}

/**
 * Notify the organizer when their pending event flips to publish.
 *
 * Fires on `transition_post_status`. We only act on `pending → publish`
 * for `event` posts that have a `_vatan_submitted_by` recorded (i.e. came
 * through the frontend create-event flow, or were backfilled by the
 * my-events migration). Admin-only events that never had a submitter
 * stay silent. Idempotent: we set `_vatan_approved_notified_at` so a
 * second `pending → publish` round-trip (e.g. after unpublish + republish)
 * doesn't double-send.
 *
 * @param string  $new_status
 * @param string  $old_status
 * @param WP_Post $post
 */
function vatan_notify_organizer_on_publish( $new_status, $old_status, $post ) {
	if ( ! $post instanceof WP_Post || 'event' !== $post->post_type ) {
		return;
	}
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}

	$organizer_id = (int) get_post_meta( $post->ID, '_vatan_submitted_by', true );
	if ( $organizer_id < 1 ) {
		// Fall back to post_author for legacy events; still skip if no user.
		$organizer_id = (int) $post->post_author;
	}
	$user = $organizer_id ? get_userdata( $organizer_id ) : null;
	if ( ! $user || ! $user->user_email ) {
		return;
	}

	// Already notified? Don't spam on republish.
	if ( get_post_meta( $post->ID, '_vatan_approved_notified_at', true ) ) {
		return;
	}

	$site_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$event_url   = (string) get_permalink( $post );
	$dashboard   = ( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'my-events' ) : home_url( '/my-account/my-events/' ) );

	/* translators: %s: event title */
	$subject = sprintf( __( '[%s] Your event is now live', 'vatan-event' ), $site_name );

	$lines = array(
		sprintf(
			/* translators: 1: first name fallback display name 2: event title */
			__( 'Hi %1$s,', 'vatan-event' ),
			$user->first_name ? $user->first_name : $user->display_name
		),
		'',
		sprintf(
			/* translators: %s: event title */
			__( 'Good news — your event "%s" has been approved and is now live on the site.', 'vatan-event' ),
			$post->post_title
		),
		'',
		__( 'View it here:', 'vatan-event' ),
		$event_url,
		'',
		__( 'Track ticket sales and earnings on your dashboard:', 'vatan-event' ),
		$dashboard,
		'',
		__( 'Thanks for organising with us.', 'vatan-event' ),
	);
	$body = implode( "\n", $lines );

	$sent = wp_mail( $user->user_email, $subject, $body );
	if ( $sent ) {
		update_post_meta( $post->ID, '_vatan_approved_notified_at', current_time( 'mysql' ) );
	}

	/**
	 * Fires after we attempt to email an organizer that their event was published.
	 *
	 * @param int     $post_id
	 * @param WP_User $user
	 * @param bool    $sent
	 */
	do_action( 'vatan_event_approved_notified', $post->ID, $user, (bool) $sent );
}
add_action( 'transition_post_status', 'vatan_notify_organizer_on_publish', 20, 3 );

/**
 * Notify admins about a new pending event.
 *
 * @param int $post_id
 */
function vatan_create_event_notify_admins( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	$admin_email = (string) get_option( 'admin_email' );
	if ( ! $admin_email ) {
		return;
	}

	$edit_url = admin_url( 'post.php?action=edit&post=' . $post_id );
	$author   = get_userdata( (int) $post->post_author );

	/* translators: %s: event title */
	$subject = sprintf( __( '[Vatan] New event pending review: %s', 'vatan-event' ), wp_strip_all_tags( $post->post_title ) );

	$body  = sprintf(
		/* translators: 1: event title 2: author display name 3: edit URL */
		__( "A new event has been submitted and is waiting for review.\n\nTitle: %1\$s\nSubmitted by: %2\$s\nReview & publish: %3\$s\n", 'vatan-event' ),
		$post->post_title,
		$author ? $author->display_name . ' <' . $author->user_email . '>' : __( 'Unknown', 'vatan-event' ),
		$edit_url
	);

	wp_mail( $admin_email, $subject, $body );
}

/**
 * Inline notice payload — read by the page template when the form just
 * round-tripped. Returns [ 'status'=>..., 'message'=>..., 'fields'=>[] ]
 * or null if there's nothing to show.
 *
 * @return array|null
 */
function vatan_create_event_get_notice() {
	if ( empty( $_GET['vatan_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return null;
	}
	$status = sanitize_key( wp_unslash( $_GET['vatan_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( 'success' === $status ) {
		return array(
			'status'  => 'success',
			'message' => __( 'Thanks! Your event has been submitted for review. We\'ll email you when it goes live.', 'vatan-event' ),
		);
	}

	if ( 'updated' === $status ) {
		return array(
			'status'  => 'success',
			'message' => __( 'Your changes have been saved.', 'vatan-event' ),
		);
	}

	if ( 'error' === $status ) {
		$errors = get_transient( 'vatan_create_event_errors_' . get_current_user_id() );
		if ( is_array( $errors ) ) {
			delete_transient( 'vatan_create_event_errors_' . get_current_user_id() );
			return array(
				'status'  => 'error',
				'message' => isset( $errors['message'] ) ? (string) $errors['message'] : __( 'Please fix the errors and try again.', 'vatan-event' ),
				'fields'  => isset( $errors['fields'] ) ? (array) $errors['fields'] : array(),
			);
		}
		return array(
			'status'  => 'error',
			'message' => __( 'Something went wrong. Please try again.', 'vatan-event' ),
		);
	}

	if ( 'bad_nonce' === $status ) {
		return array( 'status' => 'error', 'message' => __( 'Security check failed. Please try again.', 'vatan-event' ) );
	}

	if ( 'forbidden' === $status ) {
		return array( 'status' => 'error', 'message' => __( 'You don\'t have permission to submit events.', 'vatan-event' ) );
	}

	return null;
}

/**
 * Render the seat-map planner inline inside the create-event form.
 *
 * Mirrors `vatan_render_seat_editor()` from inc/admin/seat-manager.php but
 * with two important differences:
 *
 *  1. NO inner <form> tag — the planner lives inside the create-event form,
 *     so its hidden inputs (`seat_map_*`) post with the rest of the event.
 *  2. Tiers are seeded from the in-form `ticket_types` array (which is what
 *     the user is currently editing) instead of from saved ACF data — this
 *     way it works the moment a new event has ticket rows entered, before
 *     the first save.
 *
 * The seat-editor.js module reads its initial state from the
 * `[data-vatan-editor-payload]` <script> below; that's what we re-render via
 * `seat-planner-create.js` whenever the user clicks "Refresh ticket tiers".
 *
 * @param array $defaults  The `$default` array built by page-create-event.php.
 */
function vatan_render_seat_planner_field( array $defaults ): void {
	$enabled     = ! empty( $defaults['seat_map_enabled'] );
	$rows        = isset( $defaults['seat_map_rows'] ) ? max( 1, (int) $defaults['seat_map_rows'] ) : 5;
	$cols        = isset( $defaults['seat_map_cols'] ) ? max( 1, (int) $defaults['seat_map_cols'] ) : 8;
	$config_json = isset( $defaults['seat_map_config'] ) ? (string) $defaults['seat_map_config'] : '';

	// Decode any saved config so the editor can hydrate from it.
	$config = array(
		'rows'     => $rows,
		'cols'     => $cols,
		'sections' => array(),
		'reserved' => array(),
		'hallways' => array(),
		'tables'   => array(),
	);
	if ( $config_json ) {
		$decoded = json_decode( $config_json, true );
		if ( is_array( $decoded ) ) {
			$config['sections'] = isset( $decoded['sections'] ) && is_array( $decoded['sections'] ) ? $decoded['sections'] : array();
			$config['reserved'] = isset( $decoded['reserved'] ) && is_array( $decoded['reserved'] ) ? $decoded['reserved'] : array();
			$config['hallways'] = isset( $decoded['hallways'] ) && is_array( $decoded['hallways'] ) ? $decoded['hallways'] : array();
			$config['tables']   = isset( $decoded['tables'] )   && is_array( $decoded['tables'] )   ? $decoded['tables']   : array();
		}
	}

	// Build the tier list from the tickets currently in $defaults (saved or
	// just typed into the form). seat-planner-create.js will re-derive this
	// on demand from live DOM values, but we still seed once for SSR-friendly
	// hydration on edit-mode page load.
	$tiers            = array();
	$fallback_palette = array( '#06B6D4', '#8B5CF6', '#F59E0B', '#10B981', '#FF2D78', '#EF4444' );
	$i                = 0;
	foreach ( (array) $defaults['tickets'] as $tier ) {
		$name = isset( $tier['ticket_name'] ) ? (string) $tier['ticket_name'] : '';
		if ( '' === $name ) {
			continue;
		}
		$price = isset( $tier['ticket_price'] ) ? (float) $tier['ticket_price'] : 0.0;
		$color = isset( $tier['ticket_color'] ) ? (string) $tier['ticket_color'] : '';
		if ( ! sanitize_hex_color( $color ) ) {
			$color = $fallback_palette[ $i % count( $fallback_palette ) ];
		}
		$tiers[] = array( 'name' => $name, 'price' => $price, 'color' => $color );
		$i++;
	}

	$payload = array(
		'rows'   => $rows,
		'cols'   => $cols,
		'tiers'  => $tiers,
		'config' => $config,
		'i18n'   => array(
			'reserved'       => __( 'Reserved', 'vatan-event' ),
			'hallway'        => __( 'Hallway', 'vatan-event' ),
			'erase'          => __( 'Erase', 'vatan-event' ),
			'unassigned'     => __( 'Unassigned', 'vatan-event' ),
			'paintLabel'     => __( 'Paint:', 'vatan-event' ),
			'rowsLabel'      => __( 'Rows:', 'vatan-event' ),
			'colsLabel'      => __( 'Columns:', 'vatan-event' ),
			'resetConfirm'   => __( 'Clear every painted, reserved, and hallway seat (tables stay)?', 'vatan-event' ),
			'unsavedChanges' => __( 'You have unsaved changes.', 'vatan-event' ),
			'pickPaintFirst' => __( 'Add at least one ticket type above to start painting seats.', 'vatan-event' ),
			'tableLabel'     => __( 'Label', 'vatan-event' ),
			'tableSeats'     => __( 'Seats', 'vatan-event' ),
			'tableTier'      => __( 'Tier', 'vatan-event' ),
			'tableRow'       => __( 'Lane', 'vatan-event' ),
			'tableRowHint'   => __( 'Lane 1 is closest to the grid.', 'vatan-event' ),
			'tableRemove'    => __( 'Remove table', 'vatan-event' ),
			'tableAdd'       => __( 'Add round table', 'vatan-event' ),
			'tableNoTiers'   => __( 'Add a ticket type to the event first to assign a tier.', 'vatan-event' ),
			'tableNone'      => __( 'No tables yet. Add one to start a banquet / round-table layout.', 'vatan-event' ),
		),
	);
	?>
	<section class="create-event__section create-event__section--seats">
		<header class="create-event__section-head">
			<h2 class="create-event__section-title"><?php esc_html_e( 'Assigned seating (optional)', 'vatan-event' ); ?></h2>
		</header>
		<p class="create-event__section-hint">
			<?php esc_html_e( 'Turn this on to give buyers a visual seat picker. Sections are painted with the ticket types you defined above — change a ticket\'s name, price or colour and click "Refresh tiers" to push the change into the planner.', 'vatan-event' ); ?>
		</p>

		<label class="create-event__field create-event__field--toggle">
			<input
				type="checkbox"
				name="seat_map_enabled"
				value="1"
				data-vatan-seat-toggle
				<?php checked( $enabled ); ?>
			/>
			<span class="create-event__label"><?php esc_html_e( 'Enable seat map for this event', 'vatan-event' ); ?></span>
		</label>

		<div class="create-event__seat-planner" data-vatan-seat-planner <?php echo $enabled ? '' : 'hidden'; ?>>

			<div class="vatan-seat-editor" data-vatan-seat-editor>
				<div class="vatan-seat-editor__toolbar">
					<div class="vatan-seat-editor__group">
						<span class="vatan-seat-editor__label"><?php esc_html_e( 'Paint:', 'vatan-event' ); ?></span>
						<div class="vatan-seat-editor__tiers" data-vatan-tier-buttons></div>
						<button type="button" class="vatan-tool" data-tool="reserved">
							<span class="vatan-tool__chip vatan-tool__chip--reserved" aria-hidden="true">×</span>
							<?php esc_html_e( 'Reserved', 'vatan-event' ); ?>
						</button>
						<button type="button" class="vatan-tool" data-tool="hallway">
							<span class="vatan-tool__chip vatan-tool__chip--hallway" aria-hidden="true">⇕</span>
							<?php esc_html_e( 'Hallway', 'vatan-event' ); ?>
						</button>
						<button type="button" class="vatan-tool" data-tool="erase">
							<?php esc_html_e( 'Erase', 'vatan-event' ); ?>
						</button>
					</div>
					<div class="vatan-seat-editor__group">
						<label class="vatan-seat-editor__num">
							<span><?php esc_html_e( 'Rows', 'vatan-event' ); ?></span>
							<input type="number" min="1" max="50" value="<?php echo esc_attr( $rows ); ?>" data-vatan-rows-control />
						</label>
						<label class="vatan-seat-editor__num">
							<span><?php esc_html_e( 'Columns', 'vatan-event' ); ?></span>
							<input type="number" min="1" max="50" value="<?php echo esc_attr( $cols ); ?>" data-vatan-cols-control />
						</label>
						<button type="button" class="vatan-tool" data-vatan-refresh-tiers>
							<?php esc_html_e( 'Refresh tiers', 'vatan-event' ); ?>
						</button>
						<button type="button" class="vatan-tool" data-vatan-editor-reset>
							<?php esc_html_e( 'Reset seats', 'vatan-event' ); ?>
						</button>
					</div>
				</div>

				<div class="vatan-seat-editor__stage" aria-hidden="true">
					<span><?php esc_html_e( 'Stage', 'vatan-event' ); ?></span>
				</div>

				<div class="vatan-seat-editor__grid" data-vatan-editor-grid></div>

				<div class="vatan-seat-editor__counts" data-vatan-counts></div>

				<section class="vatan-seat-editor__tables">
					<header class="vatan-seat-editor__tables-head">
						<h4><?php esc_html_e( 'Round tables', 'vatan-event' ); ?></h4>
						<button type="button" class="btn btn--ghost btn--sm" data-vatan-table-add>
							<?php esc_html_e( '+ Add round table', 'vatan-event' ); ?>
						</button>
					</header>
					<p class="description"><?php esc_html_e( 'Tables flow into lanes below the seat grid. Lane 1 is closest to the grid.', 'vatan-event' ); ?></p>

					<div class="vatan-seat-editor__tables-preview" data-vatan-tables-preview>
						<div class="vatan-seat-editor__preview-grid" data-vatan-tables-preview-grid aria-hidden="true"></div>
						<div class="vatan-seat-editor__preview-lanes" data-vatan-tables-preview-lanes></div>
					</div>

					<div class="vatan-seat-editor__tables-list" data-vatan-tables-list></div>
				</section>

				<!-- Stub element so the editor's bindEvents() doesn't crash on
				     `this.saveForm.addEventListener('submit', …)`. The editor's
				     original DOM has a real <form> here for its standalone save
				     button; we don't need that — seat-planner-create.js
				     serialises the editor state into the hidden inputs below on
				     the create-event form's submit instead. -->
				<div class="vatan-seat-editor__save" hidden></div>

				<!-- Hidden inputs the parent create-event form will POST.
				     seat-planner-create.js writes the latest editor state into
				     them on form submit. -->
				<input type="hidden" name="seat_map_rows"   data-vatan-rows-input   value="<?php echo esc_attr( $rows ); ?>" />
				<input type="hidden" name="seat_map_cols"   data-vatan-cols-input   value="<?php echo esc_attr( $cols ); ?>" />
				<input type="hidden" name="seat_map_config" data-vatan-config-input value="" />

				<script type="application/json" data-vatan-editor-payload>
					<?php echo wp_json_encode( $payload ); ?>
				</script>
			</div>

		</div>
	</section>
	<?php
}
