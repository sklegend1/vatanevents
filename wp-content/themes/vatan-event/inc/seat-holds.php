<?php
/**
 * Vatan Event — temporary seat holds.
 *
 * Closes the window between "Add to cart" and "Order created" where a seat
 * was visible only in the buyer's WC session, so two shoppers could pick
 * the same seat in parallel. We persist every cart-added seat in a custom
 * table with a UNIQUE constraint on (event_id, seat_key), so a duplicate
 * insert simply fails — that makes the race trivially safe at the storage
 * layer without locking or transactions.
 *
 * Lifecycle:
 *
 *   1. POST /vatan/v1/add-ticket → vatan_add_seats_to_cart() →
 *      vatan_acquire_seat_holds(). Insert with TTL ~15 minutes (filterable
 *      via `vatan_seat_hold_ttl`). On conflict → WP_Error('seat_taken'),
 *      caller rolls back any partial holds it already grabbed.
 *
 *   2. While the hold is active, vatan_get_reserved_seat_keys() (in
 *      inc/woocommerce.php) merges the held seats — minus the caller's own
 *      token — into the reserved set returned to every other shopper, so
 *      their seat picker greys them out.
 *
 *   3. WC events:
 *        - `woocommerce_cart_item_removed`  → release holds for those seats
 *        - `woocommerce_cart_emptied`       → release everything we hold
 *                                             for the current session
 *        - `woocommerce_new_order`          → attach the holds to the order
 *                                             id so we can find them later
 *        - `woocommerce_payment_complete` and
 *          `woocommerce_order_status_processing` / `_completed`
 *                                           → order is now the permanent
 *                                             reservation (already in
 *                                             vatan_get_reserved_seat_keys
 *                                             via order_status); the hold
 *                                             becomes redundant and is
 *                                             deleted.
 *        - `woocommerce_cancelled_order`    → release.
 *
 *   4. Holds older than TTL are deleted on every read by
 *      vatan_get_held_seat_keys() and once-daily via wp-cron, so even an
 *      abandoned tab won't sit on seats forever.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_SEAT_HOLDS_TABLE_VERSION = '1.0.0';

/* =============================================================================
 *  Schema
 * ===========================================================================*/

function vatan_seat_holds_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'vatan_seat_holds';
}

function vatan_seat_holds_install_table(): void {
	if ( get_option( 'vatan_seat_holds_schema_version' ) === VATAN_SEAT_HOLDS_TABLE_VERSION ) {
		return;
	}

	global $wpdb;
	$table   = vatan_seat_holds_table();
	$charset = $wpdb->get_charset_collate();

	// UNIQUE KEY uniq_event_seat is the race-safety guarantee — duplicate
	// inserts for the same (event_id, seat_key) fail at the storage layer,
	// regardless of how many concurrent requests are in flight.
	$sql = "CREATE TABLE $table (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		event_id BIGINT UNSIGNED NOT NULL,
		seat_key VARCHAR(40) NOT NULL,
		holder_token VARCHAR(64) NOT NULL,
		order_id BIGINT UNSIGNED NULL DEFAULT NULL,
		expires_at DATETIME NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY uniq_event_seat (event_id, seat_key),
		KEY holder_idx (holder_token),
		KEY order_idx (order_id),
		KEY expires_idx (expires_at)
	) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'vatan_seat_holds_schema_version', VATAN_SEAT_HOLDS_TABLE_VERSION );
}
add_action( 'after_switch_theme', 'vatan_seat_holds_install_table' );
add_action( 'admin_init', 'vatan_seat_holds_install_table', 5 );

/* =============================================================================
 *  Token + TTL
 * ===========================================================================*/

/**
 * Stable identifier for the current shopper — used to tell "my holds"
 * apart from "someone else's holds" when rendering the seat picker.
 *
 * Resolution order:
 *   1. Logged-in user → `user:<ID>` (stable across sessions).
 *   2. Guest → `cookie:<random>` from a dedicated `vatan_hold_token`
 *      cookie. We set it lazily on first call so a brand-new visitor
 *      gets a token immediately.
 *
 * We deliberately do NOT bind to WC's session id: in REST contexts
 * without a pre-existing session cookie, WC's `get_customer_id()` can
 * return a freshly-generated id, which would make a follow-up request
 * see its own holds as someone else's. The dedicated cookie persists
 * across REST + frontend requests and is independent of WC's session
 * cookie lifecycle.
 */
function vatan_seat_holds_current_token(): string {
	$user_id = get_current_user_id();
	if ( $user_id > 0 ) {
		return 'user:' . $user_id;
	}
	if ( ! empty( $_COOKIE['vatan_hold_token'] ) ) {
		$existing = sanitize_text_field( wp_unslash( $_COOKIE['vatan_hold_token'] ) );
		// Defensive: only honor cookie values that look like our own.
		if ( preg_match( '/^[A-Za-z0-9]{24,64}$/', $existing ) ) {
			return 'cookie:' . $existing;
		}
	}
	$tok = wp_generate_password( 32, false );
	if ( ! headers_sent() ) {
		setcookie(
			'vatan_hold_token',
			$tok,
			time() + DAY_IN_SECONDS,
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
		$_COOKIE['vatan_hold_token'] = $tok;
	}
	return 'cookie:' . $tok;
}

/**
 * Seconds a hold survives without renewal. Default 15 minutes — enough for
 * a paying user to complete checkout, short enough that an abandoned cart
 * frees seats reasonably fast.
 */
function vatan_seat_holds_ttl(): int {
	return (int) apply_filters( 'vatan_seat_hold_ttl', 15 * MINUTE_IN_SECONDS );
}

/* =============================================================================
 *  Acquire / release
 * ===========================================================================*/

/**
 * Atomically claim a batch of seats for the current shopper. Race-safe:
 * the table's UNIQUE(event_id, seat_key) constraint guarantees that two
 * concurrent INSERTs for the same seat can never both succeed.
 *
 * Semantics: all-or-nothing. If any seat is unavailable (held by someone
 * else and not expired), we release the holds we just acquired and return
 * a WP_Error. The caller doesn't need to clean up.
 *
 * @param int      $event_id
 * @param string[] $seat_keys  e.g. ['1-3', 'T1-5']
 * @param string   $token      Caller's hold token (defaults to current).
 * @return true|WP_Error
 */
function vatan_acquire_seat_holds( int $event_id, array $seat_keys, string $token = '' ) {
	global $wpdb;
	$event_id  = (int) $event_id;
	$seat_keys = array_values( array_unique( array_filter( $seat_keys, 'is_string' ) ) );
	if ( ! $event_id || empty( $seat_keys ) ) {
		return new WP_Error( 'invalid_input', __( 'Nothing to hold.', 'vatan-event' ) );
	}
	if ( '' === $token ) {
		$token = vatan_seat_holds_current_token();
	}

	$table   = vatan_seat_holds_table();
	$now     = current_time( 'mysql' );
	$expires = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + vatan_seat_holds_ttl() );

	// Step 1: clear any EXPIRED rows for the seats we want, so the UNIQUE
	// index doesn't reject our fresh insert just because someone's stale
	// hold is still sitting there.
	$ph     = implode( ',', array_fill( 0, count( $seat_keys ), '%s' ) );
	$params = array_merge( array( $event_id, $now ), $seat_keys );
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $table WHERE event_id = %d AND expires_at < %s AND order_id IS NULL AND seat_key IN ($ph)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params
	) );

	// Step 2: INSERT IGNORE one row per seat. If our row inserts (returns 1),
	// we own that seat. If the UNIQUE constraint blocks us (returns 0), the
	// seat is taken — but it could also be a stale row we held ourselves a
	// moment ago; check the row's token before giving up.
	$acquired = array();
	foreach ( $seat_keys as $seat_key ) {
		$ok = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO $table (event_id, seat_key, holder_token, expires_at, created_at) VALUES (%d, %s, %s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$event_id, $seat_key, $token, $expires, $now
		) );

		if ( 1 === (int) $ok ) {
			$acquired[] = $seat_key;
			continue;
		}

		// Insert failed — someone holds it. Refresh OUR own active hold so
		// repeated clicks don't burn through TTL.
		$existing_token = $wpdb->get_var( $wpdb->prepare(
			"SELECT holder_token FROM $table WHERE event_id = %d AND seat_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$event_id, $seat_key
		) );
		if ( $existing_token === $token ) {
			// Already ours — extend the lease and consider it acquired.
			$wpdb->update(
				$table,
				array( 'expires_at' => $expires ),
				array( 'event_id' => $event_id, 'seat_key' => $seat_key, 'holder_token' => $token ),
				array( '%s' ),
				array( '%d', '%s', '%s' )
			);
			$acquired[] = $seat_key;
			continue;
		}

		// Someone else's active hold — abort and roll back what we got.
		foreach ( $acquired as $rb ) {
			$wpdb->delete(
				$table,
				array( 'event_id' => $event_id, 'seat_key' => $rb, 'holder_token' => $token ),
				array( '%d', '%s', '%s' )
			);
		}
		return new WP_Error(
			'seat_taken',
			/* translators: %s: seat label, e.g. "1-5" */
			sprintf( __( 'Seat %s was just taken by another shopper.', 'vatan-event' ), $seat_key ),
			array( 'status' => 409, 'seat' => $seat_key )
		);
	}

	return true;
}

/**
 * Drop specific seats from the hold table. No-op for seats that aren't
 * held by this token (so it's safe to call indiscriminately).
 */
function vatan_release_seat_holds_for_seats( int $event_id, array $seat_keys, string $token = '' ): void {
	global $wpdb;
	$event_id  = (int) $event_id;
	$seat_keys = array_values( array_unique( array_filter( $seat_keys, 'is_string' ) ) );
	if ( ! $event_id || empty( $seat_keys ) ) {
		return;
	}
	if ( '' === $token ) {
		$token = vatan_seat_holds_current_token();
	}

	$table  = vatan_seat_holds_table();
	$ph     = implode( ',', array_fill( 0, count( $seat_keys ), '%s' ) );
	$params = array_merge( array( $event_id, $token ), $seat_keys );
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $table WHERE event_id = %d AND holder_token = %s AND seat_key IN ($ph) AND order_id IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params
	) );
}

/**
 * Drop every hold owned by this token (across all events). Used on
 * cart-emptied and on logout.
 */
function vatan_release_all_seat_holds_for_token( string $token = '' ): void {
	global $wpdb;
	if ( '' === $token ) {
		$token = vatan_seat_holds_current_token();
	}
	$wpdb->delete(
		vatan_seat_holds_table(),
		array( 'holder_token' => $token, 'order_id' => null ), // only un-attached holds
		array( '%s', '%d' )
	);
	// `array('order_id' => null)` doesn't generate `IS NULL` in $wpdb->delete
	// (it produces `= NULL` which never matches). Run the same statement again
	// explicitly to cover that case.
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM " . vatan_seat_holds_table() . " WHERE holder_token = %s AND order_id IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$token
	) );
}

/**
 * Attach a set of holds to an order. Called at checkout so we know which
 * holds belong to which order — once the order is finalised we can drop
 * the redundant hold rows.
 */
function vatan_attach_holds_to_order( int $event_id, array $seat_keys, int $order_id, string $token = '' ): void {
	global $wpdb;
	$event_id  = (int) $event_id;
	$seat_keys = array_values( array_unique( array_filter( $seat_keys, 'is_string' ) ) );
	if ( ! $event_id || ! $order_id || empty( $seat_keys ) ) {
		return;
	}
	if ( '' === $token ) {
		$token = vatan_seat_holds_current_token();
	}

	$table = vatan_seat_holds_table();
	$ph    = implode( ',', array_fill( 0, count( $seat_keys ), '%s' ) );
	$params = array_merge( array( $order_id, $event_id, $token ), $seat_keys );
	$wpdb->query( $wpdb->prepare(
		"UPDATE $table SET order_id = %d WHERE event_id = %d AND holder_token = %s AND seat_key IN ($ph)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params
	) );
}

/**
 * Delete every hold tied to an order. Called once the order moves to a
 * status that itself counts as a reservation (processing / completed) — at
 * that point the holds are redundant.
 */
function vatan_release_holds_for_order( int $order_id ): void {
	global $wpdb;
	$order_id = (int) $order_id;
	if ( ! $order_id ) return;
	$wpdb->delete( vatan_seat_holds_table(), array( 'order_id' => $order_id ), array( '%d' ) );
}

/* =============================================================================
 *  Read paths
 * ===========================================================================*/

/**
 * Returns every actively-held seat key for an event, excluding holds owned
 * by `$exclude_token` (so the caller's own holds don't appear as "taken"
 * in their own seat picker). Expired rows are deleted as a side-effect so
 * the table self-prunes during normal traffic.
 *
 * @return string[]
 */
function vatan_get_held_seat_keys( int $event_id, string $exclude_token = '' ): array {
	global $wpdb;
	$event_id = (int) $event_id;
	if ( ! $event_id ) {
		return array();
	}

	$table = vatan_seat_holds_table();
	$now   = current_time( 'mysql' );

	// Opportunistic cleanup — drop expired un-attached rows for this event.
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $table WHERE event_id = %d AND expires_at < %s AND order_id IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event_id, $now
	) );

	if ( '' === $exclude_token ) {
		$exclude_token = vatan_seat_holds_current_token();
	}

	$rows = $wpdb->get_col( $wpdb->prepare(
		"SELECT seat_key FROM $table WHERE event_id = %d AND holder_token <> %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event_id, $exclude_token
	) );
	return is_array( $rows ) ? array_values( array_unique( array_filter( $rows ) ) ) : array();
}

/* =============================================================================
 *  WooCommerce wiring
 * ===========================================================================*/

/**
 * When a cart line is removed, drop the holds it represented. Without
 * this, removing items from cart would leave seats stuck reserved until
 * TTL expiry.
 */
add_action( 'woocommerce_cart_item_removed', function ( $cart_item_key, $cart ) {
	if ( ! $cart || ! method_exists( $cart, 'removed_cart_contents' ) ) {
		return;
	}
	$removed = $cart->removed_cart_contents;
	if ( empty( $removed[ $cart_item_key ] ) ) {
		return;
	}
	$entry = $removed[ $cart_item_key ];
	$event_id = isset( $entry['vatan_event_id'] ) ? (int) $entry['vatan_event_id'] : 0;
	$seats    = isset( $entry['vatan_seats'] )    ? (array) $entry['vatan_seats']  : array();
	if ( ! $event_id || empty( $seats ) ) {
		return;
	}
	$keys = array();
	foreach ( $seats as $s ) {
		if ( isset( $s['row'], $s['col'] ) )       $keys[] = $s['row'] . '-' . $s['col'];
		elseif ( isset( $s['table'], $s['seat'] ) ) $keys[] = $s['table'] . '-' . $s['seat'];
	}
	if ( $keys ) {
		vatan_release_seat_holds_for_seats( $event_id, $keys );
	}
}, 10, 2 );

/**
 * When the cart is emptied, release every hold tied to this session.
 */
add_action( 'woocommerce_cart_emptied', function () {
	vatan_release_all_seat_holds_for_token();
} );

/**
 * Re-validate every seat in the cart against the holds table whenever the
 * cart is loaded — covers the corner case where a hold was released
 * (payment failed, hold TTL expired, admin reset) and another shopper
 * grabbed the seat before the original user noticed. Without this they
 * could still proceed to checkout with a stale cart line and double-book.
 *
 * Runs on the `woocommerce_check_cart_items` action which fires on the
 * cart page, the checkout page, and during checkout submission — so any
 * mismatch is caught before the order is created.
 *
 * For each cart line:
 *   - try to (re)acquire the hold for the current shopper
 *   - on success the hold lease is extended; checkout proceeds
 *   - on failure we add a checkout notice + remove the offending line so
 *     the user can pick a different seat without manually clearing cart
 */
add_action( 'woocommerce_check_cart_items', function () {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}
	$token   = vatan_seat_holds_current_token();
	$removed = array();

	foreach ( WC()->cart->get_cart() as $cart_key => $entry ) {
		$event_id = isset( $entry['vatan_event_id'] ) ? (int) $entry['vatan_event_id'] : 0;
		$seats    = isset( $entry['vatan_seats'] ) && is_array( $entry['vatan_seats'] ) ? $entry['vatan_seats'] : array();
		if ( ! $event_id || empty( $seats ) ) {
			continue;
		}

		$keys = array();
		foreach ( $seats as $s ) {
			if ( isset( $s['row'], $s['col'] ) )       $keys[] = $s['row'] . '-' . $s['col'];
			elseif ( isset( $s['table'], $s['seat'] ) ) $keys[] = $s['table'] . '-' . $s['seat'];
		}
		if ( empty( $keys ) ) {
			continue;
		}

		$ok = vatan_acquire_seat_holds( $event_id, $keys, $token );
		if ( is_wp_error( $ok ) ) {
			$bad_seat = $ok->get_error_data();
			$bad_seat = is_array( $bad_seat ) && isset( $bad_seat['seat'] ) ? (string) $bad_seat['seat'] : '';
			$removed[] = array(
				'event_title' => get_the_title( $event_id ),
				'seat'        => $bad_seat,
			);
			WC()->cart->remove_cart_item( $cart_key );
		}
	}

	if ( ! empty( $removed ) ) {
		foreach ( $removed as $row ) {
			wc_add_notice(
				sprintf(
					/* translators: 1: event title, 2: seat label */
					__( 'Seat %2$s for "%1$s" is no longer available — please pick another seat.', 'vatan-event' ),
					$row['event_title'] ?: __( 'this event', 'vatan-event' ),
					$row['seat'] ?: '?'
				),
				'error'
			);
		}
	}
}, 20 );

/**
 * When an order is created (checkout submitted), attach every hold the
 * session owns to the order so we can find them on payment events.
 */
add_action( 'woocommerce_checkout_order_created', function ( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	$token = vatan_seat_holds_current_token();
	$order->update_meta_data( '_vatan_hold_token', $token );
	$order->save_meta_data();

	foreach ( $order->get_items() as $item ) {
		$event_id = (int) $item->get_meta( '_vatan_event_id' );
		$seats    = (array) $item->get_meta( '_vatan_seats' );
		if ( ! $event_id || empty( $seats ) ) continue;
		$keys = array();
		foreach ( $seats as $s ) {
			if ( isset( $s['row'], $s['col'] ) )       $keys[] = $s['row'] . '-' . $s['col'];
			elseif ( isset( $s['table'], $s['seat'] ) ) $keys[] = $s['table'] . '-' . $s['seat'];
		}
		if ( $keys ) {
			vatan_attach_holds_to_order( $event_id, $keys, $order->get_id(), $token );
		}
	}
} );

/**
 * On payment success / order processing / completion, the order itself
 * becomes the seat reservation (vatan_get_reserved_seat_keys already
 * counts processing+completed orders). Drop the now-redundant hold rows.
 */
$release_on_status_change = function ( $order_id ) {
	vatan_release_holds_for_order( (int) $order_id );
};
add_action( 'woocommerce_payment_complete',              $release_on_status_change );
add_action( 'woocommerce_order_status_processing',       $release_on_status_change );
add_action( 'woocommerce_order_status_completed',        $release_on_status_change );

/**
 * On cancel / fail / refund, drop the holds so the seats free immediately.
 * Without this the seats would stay blocked by the order status itself
 * until an admin manually cleared them — but in those statuses the order
 * is NOT in vatan_get_reserved_seat_keys() (that helper only includes
 * pending/on-hold/processing/completed), so dropping the holds is enough.
 */
$release_on_cancel = function ( $order_id ) {
	vatan_release_holds_for_order( (int) $order_id );
};
add_action( 'woocommerce_order_status_cancelled', $release_on_cancel );
add_action( 'woocommerce_order_status_failed',    $release_on_cancel );
add_action( 'woocommerce_order_status_refunded',  $release_on_cancel );

/* =============================================================================
 *  REST payload hook — expose the caller's own held seats so the picker can
 *  paint them as "in your cart" instead of as just-available. Without this,
 *  the user who just added seats sees them as still free in their own view
 *  (because vatan_get_held_seat_keys excludes their own token from the
 *  reserved set), which makes it look like nothing happened.
 * ===========================================================================*/

add_filter( 'vatan_rest_seats_payload', function ( $payload, $event_id ) {
	if ( ! is_array( $payload ) ) {
		return $payload;
	}
	global $wpdb;
	$event_id = (int) $event_id;
	$token    = vatan_seat_holds_current_token();
	$rows     = $wpdb->get_col( $wpdb->prepare(
		"SELECT seat_key FROM " . vatan_seat_holds_table() . " WHERE event_id = %d AND holder_token = %s AND expires_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event_id, $token, current_time( 'mysql' )
	) );
	$payload['mine'] = is_array( $rows ) ? array_values( array_unique( array_filter( $rows ) ) ) : array();
	return $payload;
}, 20, 2 );

/* =============================================================================
 *  Scheduled cleanup — sweeps abandoned holds even when the read paths above
 *  aren't being hit for an event.
 * ===========================================================================*/

add_action( 'wp', function () {
	if ( ! wp_next_scheduled( 'vatan_seat_holds_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'vatan_seat_holds_cleanup' );
	}
} );

add_action( 'vatan_seat_holds_cleanup', function () {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM " . vatan_seat_holds_table() . " WHERE expires_at < %s AND order_id IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		current_time( 'mysql' )
	) );
} );
