<?php
/**
 * "My Events" — organizer dashboard under WooCommerce My Account.
 *
 * Adds a `/my-account/my-events/` endpoint that lists events the current
 * user has submitted (via the frontend create-event form OR directly in
 * wp-admin). Each row shows status (Pending / Published / Draft) plus
 * date, venue, view count, and ticket-type count.
 *
 * Read-only in v1 — admins still moderate / publish from wp-admin. Future
 * iterations can layer in inline edit / delete.
 *
 * Architecture mirrors inc/woocommerce.php's `my-tickets` endpoint so the
 * two surfaces feel consistent for users.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Endpoint registration ---------- */

function vatan_register_my_events_endpoint() {
	add_rewrite_endpoint( 'my-events', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'vatan_register_my_events_endpoint' );

/**
 * One-time backfill — for every event missing `_vatan_submitted_by`, copy
 * the current `post_author` into the meta key. Guard with an option so it
 * runs once per install (or once per migration if we bump the version).
 *
 * This catches events created before the meta-tracking existed, as well
 * as seeded demo events. After the backfill the meta is the authoritative
 * source for the dashboard.
 */
function vatan_my_events_maybe_backfill_submitter_meta() {
	if ( '1' === get_option( 'vatan_submitted_by_backfilled' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$events = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_vatan_submitted_by',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	foreach ( $events as $event_id ) {
		$author_id = (int) get_post_field( 'post_author', $event_id );
		if ( $author_id > 0 ) {
			update_post_meta( $event_id, '_vatan_submitted_by', $author_id );
		}
	}

	update_option( 'vatan_submitted_by_backfilled', '1' );
}
add_action( 'admin_init', 'vatan_my_events_maybe_backfill_submitter_meta', 40 );

function vatan_my_events_query_vars( $vars ) {
	$vars[] = 'my-events';
	return $vars;
}
add_filter( 'query_vars', 'vatan_my_events_query_vars' );

/**
 * Slot "My Events" into the WC account menu — only for users who can
 * submit events (mirrors the create-event capability gate).
 *
 * @param array $items
 * @return array
 */
function vatan_my_events_menu_items( $items ) {
	if ( function_exists( 'vatan_can_submit_event' ) && ! vatan_can_submit_event() ) {
		return $items;
	}

	$out = array();
	foreach ( $items as $key => $label ) {
		$out[ $key ] = $label;
		// Insert right after "my-tickets" if it exists, else after orders,
		// else just append before customer-logout.
		if ( 'my-tickets' === $key ) {
			$out['my-events'] = __( 'My Events', 'vatan-event' );
		}
	}
	if ( ! isset( $out['my-events'] ) ) {
		// Fallback: insert just before customer-logout.
		$logout = isset( $out['customer-logout'] ) ? $out['customer-logout'] : null;
		if ( $logout ) {
			unset( $out['customer-logout'] );
		}
		$out['my-events'] = __( 'My Events', 'vatan-event' );
		if ( $logout ) {
			$out['customer-logout'] = $logout;
		}
	}
	return $out;
}
add_filter( 'woocommerce_account_menu_items', 'vatan_my_events_menu_items' );

function vatan_my_events_endpoint_title( $title ) {
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'my-events' ) && in_the_loop() ) {
		return __( 'My Events', 'vatan-event' );
	}
	return $title;
}
add_filter( 'the_title', 'vatan_my_events_endpoint_title', 10, 1 );

/* ---------- Content handler ---------- */

function vatan_my_events_endpoint_content() {
	$events = vatan_get_user_events( get_current_user_id() );
	wc_get_template(
		'myaccount/my-events.php',
		array( 'events' => $events ),
		'',
		trailingslashit( VATAN_EVENT_DIR ) . 'woocommerce/'
	);
}
add_action( 'woocommerce_account_my-events_endpoint', 'vatan_my_events_endpoint_content' );

/* ---------- Data collection ---------- */

/**
 * Collect every event authored by the given user, grouped by status,
 * with the metadata the dashboard wants to show.
 *
 * @param int $user_id
 * @return array<int,array>
 */
function vatan_get_user_events( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return array();
	}

	// Query by `_vatan_submitted_by` (the immutable "submitter of record")
	// rather than post_author. post_author can be reassigned by admins or
	// quietly mutated by the block editor on publish, which would otherwise
	// orphan the event from its organizer's dashboard.
	$posts = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => array( 'pending', 'publish', 'draft', 'future', 'private' ),
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_vatan_submitted_by',
				'value'   => $user_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
		),
	) );

	$out = array();
	foreach ( $posts as $post ) {
		$event_id = (int) $post->ID;

		$tickets = function_exists( 'get_field' ) ? get_field( 'ticket_types', $event_id ) : array();
		if ( ! is_array( $tickets ) ) {
			$tickets = array();
		}
		$ticket_count = count( $tickets );

		$event_date   = function_exists( 'get_field' ) ? (string) get_field( 'event_date',   $event_id ) : (string) get_post_meta( $event_id, 'event_date',   true );
		$event_venue  = function_exists( 'get_field' ) ? (string) get_field( 'event_venue',  $event_id ) : (string) get_post_meta( $event_id, 'event_venue',  true );

		$gross    = function_exists( 'vatan_event_gross_revenue' ) ? vatan_event_gross_revenue( $event_id ) : 0.0;
		$paid_out = function_exists( 'vatan_event_paid_out' )     ? vatan_event_paid_out( $event_id )     : 0.0;

		$out[] = array(
			'id'             => $event_id,
			'title'          => get_the_title( $post ),
			'status'         => $post->post_status,
			'submitted'      => $post->post_date,
			'event_date'     => $event_date,
			'date_display'   => function_exists( 'vatan_event_date_display' ) ? vatan_event_date_display( $event_date ) : $event_date,
			'venue'          => $event_venue,
			'permalink'      => 'publish' === $post->post_status ? get_permalink( $post ) : '',
			'ticket_count'   => $ticket_count,
			'view_count'     => function_exists( 'vatan_event_view_count' ) ? vatan_event_view_count( $event_id ) : 0,
			'tickets_sold'   => function_exists( 'vatan_event_tickets_sold' )  ? vatan_event_tickets_sold( $event_id )  : 0,
			'gross_revenue'  => $gross,
			'paid_out'       => $paid_out,
			'balance'        => max( 0.0, $gross - $paid_out ),
		);
	}
	return $out;
}

/**
 * Translate a post status to a friendly label + state class for badging.
 *
 * @param string $status
 * @return array{label:string, state:string}
 */
function vatan_event_post_status_meta( $status ) {
	switch ( $status ) {
		case 'publish':
			return array( 'label' => __( 'Published', 'vatan-event' ), 'state' => 'live' );
		case 'pending':
			return array( 'label' => __( 'Pending review', 'vatan-event' ), 'state' => 'pending' );
		case 'draft':
			return array( 'label' => __( 'Draft', 'vatan-event' ), 'state' => 'draft' );
		case 'future':
			return array( 'label' => __( 'Scheduled', 'vatan-event' ), 'state' => 'scheduled' );
		case 'private':
			return array( 'label' => __( 'Private', 'vatan-event' ), 'state' => 'private' );
		default:
			return array( 'label' => ucfirst( $status ), 'state' => 'other' );
	}
}
