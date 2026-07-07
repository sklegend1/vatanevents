<?php
/**
 * WooCommerce integration.
 *
 * Single entry-point for everything WC-related:
 *   1. Theme support + cart-fragment for the header badge.
 *   2. `event_ticket` custom product type.
 *   3. Auto-sync of WC products from the event's ACF ticket_types repeater.
 *   4. Cart item meta (seats + event date) — display and persistence.
 *   5. Add-to-cart helper called by the REST endpoint with capacity / collision checks.
 *   6. Merge reserved seats from orders + admin-blocked into the seat-map payload.
 *   7. Checkout: Iranian national-ID field + validation + save.
 *   8. Order: copy meta to line items, enrich emails, hook for SMS.
 *   9. My Account: register `my-tickets` endpoint + data fetch + asset enqueue.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/* ---------- 1. Theme support + cart fragment ---------- */

function vatan_woocommerce_setup() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'vatan_woocommerce_setup' );

/**
 * Header cart badge fragment — keeps the count in sync after AJAX adds.
 * Markup must match what header.php renders.
 */
function vatan_cart_count_fragment( $fragments ) {
	$count = ( WC()->cart instanceof WC_Cart ) ? WC()->cart->get_cart_contents_count() : 0;
	$empty = $count > 0 ? 'false' : 'true';

	ob_start();
	?>
	<span class="cart-count" data-empty="<?php echo esc_attr( $empty ); ?>" data-vatan-cart-count><?php echo esc_html( $count ); ?></span>
	<?php
	$fragments['span[data-vatan-cart-count]'] = ob_get_clean();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'vatan_cart_count_fragment' );

/* ---------- 2. Custom product type: event_ticket ---------- */

function vatan_register_event_ticket_class() {
	if ( ! class_exists( 'WC_Product_Simple' ) || class_exists( 'WC_Product_Event_Ticket' ) ) {
		return;
	}
	// phpcs:ignore Squiz.Commenting.ClassComment.Missing
	class WC_Product_Event_Ticket extends WC_Product_Simple {
		public function get_type() {
			return 'event_ticket';
		}
	}
}
add_action( 'init', 'vatan_register_event_ticket_class', 20 );

function vatan_add_event_ticket_to_selector( $types ) {
	$types['event_ticket'] = __( 'Event Ticket', 'vatan-event' );
	return $types;
}
add_filter( 'product_type_selector', 'vatan_add_event_ticket_to_selector' );

function vatan_event_ticket_class_resolver( $classname, $product_type ) {
	if ( 'event_ticket' === $product_type ) {
		return 'WC_Product_Event_Ticket';
	}
	return $classname;
}
add_filter( 'woocommerce_product_class', 'vatan_event_ticket_class_resolver', 10, 2 );

/* ---------- 3. Auto-sync products with the event's ticket_types repeater ---------- */

/**
 * After an event saves, ensure one `event_ticket` product exists per
 * tier in the ACF repeater. Trash products for tiers that were removed.
 *
 * Fires on `acf/save_post` (priority 30) so it runs after ACF has
 * persisted the repeater data, and also on plain `save_post_event`
 * for environments without ACF.
 *
 * @param int|string $post_id
 */
function vatan_sync_event_ticket_products( $post_id ) {
	if ( ! is_numeric( $post_id ) ) {
		return; // acf/save_post can pass strings (option pages); skip those.
	}
	$post_id = (int) $post_id;
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'event' ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product_Event_Ticket' ) ) {
		return;
	}

	$tickets = function_exists( 'get_field' ) ? get_field( 'ticket_types', $post_id ) : null;
	if ( ! is_array( $tickets ) ) {
		return;
	}

	$existing = (array) get_post_meta( $post_id, '_vatan_ticket_products', true );
	$next     = array();

	foreach ( $tickets as $tier ) {
		$name = isset( $tier['ticket_name'] ) ? sanitize_text_field( (string) $tier['ticket_name'] ) : '';
		if ( '' === $name ) {
			continue;
		}
		$key   = sanitize_title( $name );
		$price = isset( $tier['ticket_price'] ) ? (float) $tier['ticket_price'] : 0.0;

		$product_id = isset( $existing[ $key ] ) ? (int) $existing[ $key ] : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product || 'event_ticket' !== $product->get_type() ) {
			$product = new WC_Product_Event_Ticket();
		}

		$product->set_name( get_the_title( $post_id ) . ' — ' . $name );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' ); // hidden from shop catalog & search
		$product->set_regular_price( (string) $price );
		$product->set_price( (string) $price );
		$product->set_virtual( true );
		$product->set_sold_individually( false );
		$product->set_reviews_allowed( false );

		$product_id = $product->save();

		update_post_meta( $product_id, '_vatan_event_id', $post_id );
		update_post_meta( $product_id, '_vatan_ticket_type', $name );
		update_post_meta( $product_id, '_vatan_ticket_color', isset( $tier['ticket_color'] ) ? (string) $tier['ticket_color'] : '' );

		$next[ $key ] = $product_id;
	}

	// Trash products whose tier was removed.
	foreach ( $existing as $key => $pid ) {
		if ( ! isset( $next[ $key ] ) && $pid ) {
			wp_trash_post( (int) $pid );
		}
	}

	update_post_meta( $post_id, '_vatan_ticket_products', $next );
}
add_action( 'acf/save_post', 'vatan_sync_event_ticket_products', 30 );
add_action( 'save_post_event', 'vatan_sync_event_ticket_products', 30 );

/* ---------- 4. Cart item meta: display + persistence ---------- */

/**
 * Display selected seats + event date inside the cart / checkout summary.
 */
function vatan_show_cart_item_meta( $item_data, $cart_item ) {
	if ( isset( $cart_item['vatan_seats'] ) && is_array( $cart_item['vatan_seats'] ) ) {
		$seat_strs = array();
		foreach ( $cart_item['vatan_seats'] as $seat ) {
			if ( isset( $seat['row'], $seat['col'] ) ) {
				$seat_strs[] = vatan_to_persian_digits( $seat['row'] . '.' . $seat['col'] );
			} elseif ( isset( $seat['table'], $seat['seat'] ) ) {
				$seat_strs[] = $seat['table'] . '.' . vatan_to_persian_digits( $seat['seat'] );
			}
		}
		if ( $seat_strs ) {
			$item_data[] = array(
				'key'   => __( 'Seats', 'vatan-event' ),
				'value' => implode( '، ', $seat_strs ),
			);
		}
	}

	if ( ! empty( $cart_item['vatan_event_date'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Date', 'vatan-event' ),
			'value' => vatan_event_date_display( $cart_item['vatan_event_date'] ),
		);
	}

	return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'vatan_show_cart_item_meta', 10, 2 );

/**
 * Copy cart item meta into the order line item on checkout.
 * Stores both internal (`_vatan_*`) and display-friendly keys, so admins
 * see seats + dates inside the order detail screen too.
 */
function vatan_save_cart_meta_to_order( $item, $cart_item_key, $values, $order ) {
	if ( isset( $values['vatan_event_id'] ) ) {
		$item->add_meta_data( '_vatan_event_id', (int) $values['vatan_event_id'], true );
		$item->add_meta_data( '_event_id', (int) $values['vatan_event_id'], true ); // alias per spec
	}
	if ( isset( $values['vatan_seats'] ) && is_array( $values['vatan_seats'] ) ) {
		$item->add_meta_data( '_vatan_seats', $values['vatan_seats'], true );
		$item->add_meta_data( '_seat_numbers', $values['vatan_seats'], true ); // alias per spec
		$seat_strs = array();
		foreach ( $values['vatan_seats'] as $seat ) {
			if ( isset( $seat['row'], $seat['col'] ) ) {
				$seat_strs[] = $seat['row'] . '.' . $seat['col'];
			} elseif ( isset( $seat['table'], $seat['seat'] ) ) {
				$seat_strs[] = $seat['table'] . '.' . $seat['seat'];
			}
		}
		if ( $seat_strs ) {
			$item->add_meta_data( __( 'Seats', 'vatan-event' ), implode( ', ', $seat_strs ), true );
		}
	}
	if ( ! empty( $values['vatan_event_date'] ) ) {
		$item->add_meta_data( '_event_date', $values['vatan_event_date'], true );
	}

	$product = $item->get_product();
	if ( $product ) {
		$ticket_type = (string) get_post_meta( $product->get_id(), '_vatan_ticket_type', true );
		if ( $ticket_type ) {
			$item->add_meta_data( '_vatan_ticket_type', $ticket_type, true );
			$item->add_meta_data( '_ticket_type', $ticket_type, true ); // alias per spec
			$item->add_meta_data( 'ticket_type', $ticket_type, true );  // visible
		}
	}
}
add_action( 'woocommerce_checkout_create_order_line_item', 'vatan_save_cart_meta_to_order', 10, 4 );

/* ---------- 5. Add-to-cart helper (called by REST) ---------- */

/**
 * Validate a seat selection against capacity + already-reserved seats +
 * the current cart, then add to WC cart. Groups seats by ticket type and
 * adds one cart line per group (qty = number of seats in that tier).
 *
 * @param int   $event_id
 * @param array $seats Each: [row,col,type,price].
 * @return array|WP_Error  ['cart_keys' => [...], 'cart_url' => '...'] or WP_Error.
 */
function vatan_add_seats_to_cart( $event_id, $seats ) {
	if ( ! function_exists( 'wc_load_cart' ) ) {
		return new WP_Error( 'wc_inactive', __( 'WooCommerce is not active.', 'vatan-event' ), array( 'status' => 503 ) );
	}

	if ( ! WC()->cart ) {
		wc_load_cart(); // bootstraps cart + session for REST contexts.
	}

	$product_map = (array) get_post_meta( $event_id, '_vatan_ticket_products', true );
	if ( empty( $product_map ) ) {
		return new WP_Error(
			'no_products',
			__( 'No ticket products are configured for this event yet — re-save the event so products are generated.', 'vatan-event' ),
			array( 'status' => 500 )
		);
	}

	$event_date = function_exists( 'get_field' ) ? (string) get_field( 'event_date', $event_id ) : '';

	// Reserved set: orders + admin-blocked + ACF reserved.
	$reserved = vatan_get_reserved_seat_keys( $event_id );

	// Same-cart collision check (so a user can't add the same seat twice).
	// Builds a flat key set covering both grid and table seats.
	$in_cart = array();
	foreach ( WC()->cart->get_cart() as $existing ) {
		if ( ! isset( $existing['vatan_event_id'] ) || (int) $existing['vatan_event_id'] !== $event_id ) {
			continue;
		}
		if ( isset( $existing['vatan_seats'] ) && is_array( $existing['vatan_seats'] ) ) {
			foreach ( $existing['vatan_seats'] as $s ) {
				if ( isset( $s['row'], $s['col'] ) ) {
					$in_cart[] = $s['row'] . '-' . $s['col'];
				} elseif ( isset( $s['table'], $s['seat'] ) ) {
					$in_cart[] = $s['table'] . '-' . $s['seat'];
				}
			}
		}
	}

	// Group incoming seats by type. Each entry preserves its grid/table shape.
	$by_type = array();
	foreach ( $seats as $seat ) {
		$key    = '';
		$stored = null;

		if ( isset( $seat['row'], $seat['col'] ) && (int) $seat['row'] > 0 && (int) $seat['col'] > 0 ) {
			$row    = (int) $seat['row'];
			$col    = (int) $seat['col'];
			$key    = $row . '-' . $col;
			$stored = array( 'row' => $row, 'col' => $col );
		} elseif ( isset( $seat['table'], $seat['seat'] ) && (int) $seat['seat'] > 0 ) {
			$table  = sanitize_text_field( (string) $seat['table'] );
			$seat_n = (int) $seat['seat'];
			if ( ! preg_match( '/^T[A-Za-z0-9_-]*$/', $table ) ) {
				continue;
			}
			$key    = $table . '-' . $seat_n;
			$stored = array( 'table' => $table, 'seat' => $seat_n );
		} else {
			continue;
		}

		$type = isset( $seat['type'] ) ? sanitize_text_field( $seat['type'] ) : '';

		if ( in_array( $key, $reserved, true ) ) {
			return new WP_Error(
				'seat_taken',
				/* translators: %s: seat key, e.g. "1-5" */
				sprintf( __( 'Seat %s is no longer available.', 'vatan-event' ), $key ),
				array( 'status' => 409 )
			);
		}
		if ( in_array( $key, $in_cart, true ) ) {
			return new WP_Error(
				'in_cart',
				/* translators: %s: seat key */
				sprintf( __( 'Seat %s is already in your cart.', 'vatan-event' ), $key ),
				array( 'status' => 409 )
			);
		}

		if ( ! isset( $by_type[ $type ] ) ) {
			$by_type[ $type ] = array();
		}
		$by_type[ $type ][] = $stored;
	}

	if ( empty( $by_type ) ) {
		return new WP_Error( 'invalid_seats', __( 'No valid seats in the request.', 'vatan-event' ), array( 'status' => 400 ) );
	}

	// Race-safe hold acquisition — before we touch WC's per-session cart,
	// claim every seat in a UNIQUE-constrained table so a second concurrent
	// shopper can't grab the same seat. On any failure, the helper rolls
	// back its own partial holds; we just propagate the WP_Error.
	if ( function_exists( 'vatan_acquire_seat_holds' ) ) {
		$hold_keys = array();
		foreach ( $by_type as $type_seats ) {
			foreach ( $type_seats as $s ) {
				if ( isset( $s['row'], $s['col'] ) )       $hold_keys[] = $s['row'] . '-' . $s['col'];
				elseif ( isset( $s['table'], $s['seat'] ) ) $hold_keys[] = $s['table'] . '-' . $s['seat'];
			}
		}
		$held = vatan_acquire_seat_holds( $event_id, $hold_keys );
		if ( is_wp_error( $held ) ) {
			return $held;
		}
	}

	// Add one cart line per type.
	$cart_keys = array();
	foreach ( $by_type as $type => $type_seats ) {
		$slug       = sanitize_title( $type );
		$product_id = isset( $product_map[ $slug ] ) ? (int) $product_map[ $slug ] : 0;
		if ( ! $product_id ) {
			return new WP_Error(
				'no_product',
				/* translators: %s: ticket type name */
				sprintf( __( 'No product configured for ticket type "%s".', 'vatan-event' ), $type ),
				array( 'status' => 500 )
			);
		}

		$cart_data = array(
			'vatan_event_id'   => $event_id,
			'vatan_seats'      => $type_seats,
			'vatan_event_date' => $event_date,
			// Forces WC to treat each add as a new line, not merge.
			'unique_key'       => md5( microtime() . wp_json_encode( $type_seats ) ),
		);

		$key = WC()->cart->add_to_cart( $product_id, count( $type_seats ), 0, array(), $cart_data );
		if ( $key ) {
			$cart_keys[] = $key;
		}
	}

	if ( empty( $cart_keys ) ) {
		return new WP_Error( 'add_to_cart_failed', __( 'Could not add tickets to cart.', 'vatan-event' ), array( 'status' => 500 ) );
	}

	WC()->cart->calculate_totals();

	// Persist the WC session so the cart survives the redirect to /cart/.
	//
	// In REST contexts, `wp_loaded`/`wp` action chains that normally fire
	// `WC_Cart::maybe_set_cart_cookies()` don't run the way they do for
	// frontend / wc-ajax requests. The result: for a guest with no prior
	// `wp_woocommerce_session_*` cookie, `WC_Session_Handler::has_session()`
	// returns false during shutdown, which short-circuits `save_data()` —
	// so the cart is added to an in-memory session that never reaches the
	// DB, and the browser navigates to /cart/ without a session cookie and
	// sees an empty cart.
	//
	// Calling `set_customer_session_cookie(true)` explicitly: (a) writes the
	// session cookie into the REST response so the browser carries it on
	// the redirect, and (b) flips `_has_cookie = true`, which lets the
	// shutdown save proceed and persist the cart to the session table.
	if ( WC()->session ) {
		WC()->session->set_customer_session_cookie( true );
	}
	if ( method_exists( WC()->cart, 'maybe_set_cart_cookies' ) ) {
		WC()->cart->maybe_set_cart_cookies();
	}

	return array(
		'cart_keys' => $cart_keys,
		'cart_url'  => wc_get_cart_url(),
	);
}

/**
 * Compute the full reserved-set for an event:
 *   - admin-blocked (vatan_blocked_seats post meta, set in the Seat Manager)
 *   - seats inside completed/processing/on-hold orders
 *   - reserved seats listed in the seat_map_config JSON
 *   - active CART HOLDS (vatan_seat_holds table) belonging to OTHER shoppers
 *     — this is the "temporarily reserved" window between Add-to-cart and
 *     payment. Held seats belonging to the current shopper are excluded so
 *     their own picker doesn't grey them out.
 *
 * @param int $event_id
 * @return string[] List of "row-col" keys.
 */
function vatan_get_reserved_seat_keys( $event_id ) {
	$out = array();

	// Cart holds from other shoppers — excludes our own session's holds.
	if ( function_exists( 'vatan_get_held_seat_keys' ) ) {
		$out = array_merge( $out, vatan_get_held_seat_keys( (int) $event_id ) );
	}

	// Admin-blocked.
	$blocked = (array) get_post_meta( $event_id, 'vatan_blocked_seats', true );
	$out     = array_merge( $out, array_filter( $blocked, 'is_string' ) );

	// ACF JSON reserved — accept both grid ("1-5") and table ("T1-3") keys.
	if ( function_exists( 'get_field' ) ) {
		$config_json = (string) get_field( 'seat_map_config', $event_id );
		if ( $config_json ) {
			$decoded = json_decode( $config_json, true );
			if ( is_array( $decoded ) && isset( $decoded['reserved'] ) && is_array( $decoded['reserved'] ) ) {
				foreach ( $decoded['reserved'] as $k ) {
					if ( ! is_string( $k ) ) {
						continue;
					}
					if ( preg_match( '/^\d+-\d+$/', $k ) || preg_match( '/^T[A-Za-z0-9_-]*-\d+$/', $k ) ) {
						$out[] = $k;
					}
				}
			}
		}
	}

	// Orders — seats stored on each line item, including table seats.
	if ( function_exists( 'wc_get_orders' ) ) {
		$orders = wc_get_orders( array(
			'limit'      => -1,
			'status'     => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ),
			'meta_key'   => '_vatan_event_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $event_id,         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		) );
		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					if ( (int) $item->get_meta( '_vatan_event_id' ) !== $event_id ) {
						continue;
					}
					$item_seats = $item->get_meta( '_vatan_seats' );
					if ( is_array( $item_seats ) ) {
						foreach ( $item_seats as $s ) {
							if ( isset( $s['row'], $s['col'] ) ) {
								$out[] = $s['row'] . '-' . $s['col'];
							} elseif ( isset( $s['table'], $s['seat'] ) ) {
								$out[] = $s['table'] . '-' . $s['seat'];
							}
						}
					}
				}
			}
		}
	}

	return array_values( array_unique( array_filter( $out ) ) );
}

/* ---------- 6. Merge reserved into seat-map REST payload ---------- */

function vatan_merge_reserved_into_seats_payload( $payload, $event_id ) {
	if ( ! is_array( $payload ) ) {
		return $payload;
	}
	$existing = isset( $payload['reserved'] ) && is_array( $payload['reserved'] ) ? $payload['reserved'] : array();
	$merged   = array_merge( $existing, vatan_get_reserved_seat_keys( $event_id ) );
	$payload['reserved'] = array_values( array_unique( $merged ) );
	return $payload;
}
add_filter( 'vatan_rest_seats_payload', 'vatan_merge_reserved_into_seats_payload', 10, 2 );

/* ---------- 7. Checkout: National ID field ---------- */

function vatan_add_national_id_field( $fields ) {
	$fields['billing']['billing_national_id'] = array(
		'label'       => __( 'National ID', 'vatan-event' ),
		'placeholder' => __( '10-digit national ID', 'vatan-event' ),
		'required'    => true,
		'class'       => array( 'form-row-wide' ),
		'priority'    => 25,
		'autocomplete'=> 'off',
		'maxlength'   => 10,
	);
	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'vatan_add_national_id_field' );

function vatan_validate_checkout_national_id() {
	$id = isset( $_POST['billing_national_id'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_national_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === $id ) {
		return; // required-check already covered by WC.
	}
	if ( ! preg_match( '/^\d{10}$/', $id ) ) {
		wc_add_notice( __( 'National ID must be exactly 10 digits.', 'vatan-event' ), 'error' );
		return;
	}
	if ( ! vatan_validate_iranian_national_id( $id ) ) {
		wc_add_notice( __( 'Invalid Iranian national ID — please double-check the digits.', 'vatan-event' ), 'error' );
	}
}
add_action( 'woocommerce_after_checkout_validation', 'vatan_validate_checkout_national_id' );

function vatan_save_checkout_national_id( $order_id ) {
	if ( empty( $_POST['billing_national_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$id = sanitize_text_field( wp_unslash( $_POST['billing_national_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$order = wc_get_order( $order_id );
	if ( $order ) {
		$order->update_meta_data( '_billing_national_id', $id );
		$order->save();
	}
}
add_action( 'woocommerce_checkout_update_order_meta', 'vatan_save_checkout_national_id' );

/**
 * Standard Iranian کد ملی checksum.
 *
 * @param string $id 10-digit numeric string.
 * @return bool
 */
function vatan_validate_iranian_national_id( $id ) {
	$id = (string) $id;
	if ( strlen( $id ) !== 10 || ! ctype_digit( $id ) ) {
		return false;
	}
	// Reject all-same-digit IDs (0000000000, 1111111111, …) — invalid by spec.
	if ( preg_match( '/^(\d)\1{9}$/', $id ) ) {
		return false;
	}
	$check = (int) $id[9];
	$sum   = 0;
	for ( $i = 0; $i < 9; $i++ ) {
		$sum += ( (int) $id[ $i ] ) * ( 10 - $i );
	}
	$remainder = $sum % 11;
	if ( $remainder < 2 ) {
		return $check === $remainder;
	}
	return $check === ( 11 - $remainder );
}

/* ---------- 8. Order: emails + SMS hook ---------- */

/**
 * Append a "Your Tickets" block to customer order emails (processing + completed).
 */
function vatan_email_after_order_table( $order, $sent_to_admin, $plain_text, $email ) {
	if ( $sent_to_admin ) {
		return;
	}
	if ( ! isset( $email->id ) || ! in_array( $email->id, array( 'customer_processing_order', 'customer_completed_order' ), true ) ) {
		return;
	}

	$tickets = vatan_collect_order_tickets( $order );
	if ( empty( $tickets ) ) {
		return;
	}

	if ( $plain_text ) {
		echo "\n\n=== " . esc_html__( 'Your Tickets', 'vatan-event' ) . " ===\n";
		foreach ( $tickets as $t ) {
			echo esc_html( $t['event_title'] ) . "\n";
			$line = $t['date_display'];
			if ( $t['ticket_type'] ) {
				$line .= ' — ' . $t['ticket_type'];
			}
			echo esc_html( $line ) . "\n";
			if ( ! empty( $t['seat_keys'] ) ) {
				echo esc_html__( 'Seats:', 'vatan-event' ) . ' ' . esc_html( implode( ', ', $t['seat_keys'] ) ) . "\n";
			}
			echo "\n";
		}
	} else {
		?>
		<h2 style="margin:24px 0 12px;"><?php esc_html_e( 'Your Tickets', 'vatan-event' ); ?></h2>
		<?php foreach ( $tickets as $t ) : ?>
			<div style="border:1px solid #e0e0e0; border-radius:8px; padding:14px 16px; margin-bottom:10px;">
				<div style="font-weight:700; font-size:16px;"><?php echo esc_html( $t['event_title'] ); ?></div>
				<div style="color:#666; margin-top:4px;">
					<?php echo esc_html( $t['date_display'] ); ?>
					<?php if ( $t['ticket_type'] ) : ?>
						· <?php echo esc_html( $t['ticket_type'] ); ?>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $t['seat_keys'] ) ) : ?>
					<div style="margin-top:6px;"><strong><?php esc_html_e( 'Seats:', 'vatan-event' ); ?></strong> <?php echo esc_html( implode( ', ', $t['seat_keys'] ) ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<p style="font-size:13px; color:#666;">
			<?php esc_html_e( 'View QR codes and download your tickets from your account.', 'vatan-event' ); ?>
		</p>
		<?php
	}
}
add_action( 'woocommerce_email_after_order_table', 'vatan_email_after_order_table', 10, 4 );

/**
 * Fire `vatan_send_sms` on order completion. Default behaviour: no-op.
 * Plug an SMS provider in by hooking this action.
 */
function vatan_dispatch_sms_on_completion( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$tickets = vatan_collect_order_tickets( $order );
	if ( empty( $tickets ) ) {
		return;
	}
	$phone = $order->get_billing_phone();
	if ( ! $phone ) {
		return;
	}
	$message = sprintf(
		/* translators: 1: site name, 2: number of tickets */
		__( '%1$s — %2$d ticket(s) confirmed. Check My Account for QR codes.', 'vatan-event' ),
		get_bloginfo( 'name' ),
		count( $tickets )
	);
	/**
	 * Hook for SMS gateway integrations. Default: no-op.
	 *
	 * @param string   $phone
	 * @param string   $message
	 * @param WC_Order $order
	 * @param array    $tickets
	 */
	do_action( 'vatan_send_sms', $phone, $message, $order, $tickets );
}
add_action( 'woocommerce_order_status_completed', 'vatan_dispatch_sms_on_completion' );

/* ---------- 9. My Account: My Tickets endpoint ---------- */

function vatan_register_my_tickets_endpoint() {
	add_rewrite_endpoint( 'my-tickets', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'vatan_register_my_tickets_endpoint' );

/**
 * Flush rewrites once on theme activation so the new endpoint resolves
 * without an admin manually re-saving permalinks.
 */
function vatan_flush_rewrite_after_theme_switch() {
	vatan_register_my_tickets_endpoint();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'vatan_flush_rewrite_after_theme_switch' );

function vatan_my_tickets_query_vars( $vars ) {
	$vars[] = 'my-tickets';
	return $vars;
}
add_filter( 'query_vars', 'vatan_my_tickets_query_vars' );

function vatan_my_tickets_menu_items( $items ) {
	$out = array();
	foreach ( $items as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'orders' === $key ) {
			$out['my-tickets'] = __( 'My Tickets', 'vatan-event' );
		}
	}
	if ( ! isset( $out['my-tickets'] ) ) {
		// Fallback: append before customer-logout.
		$logout = isset( $out['customer-logout'] ) ? $out['customer-logout'] : null;
		if ( $logout ) {
			unset( $out['customer-logout'] );
		}
		$out['my-tickets'] = __( 'My Tickets', 'vatan-event' );
		if ( $logout ) {
			$out['customer-logout'] = $logout;
		}
	}
	return $out;
}
add_filter( 'woocommerce_account_menu_items', 'vatan_my_tickets_menu_items' );

function vatan_my_tickets_endpoint_title( $title ) {
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'my-tickets' ) && in_the_loop() ) {
		return __( 'My Tickets', 'vatan-event' );
	}
	return $title;
}
add_filter( 'the_title', 'vatan_my_tickets_endpoint_title' );

/**
 * Render the My Tickets endpoint via theme template.
 */
function vatan_my_tickets_endpoint_content() {
	$tickets = vatan_get_user_tickets( get_current_user_id() );
	wc_get_template(
		'myaccount/my-tickets.php',
		array( 'tickets' => $tickets ),
		'',
		trailingslashit( VATAN_EVENT_DIR ) . 'woocommerce/'
	);
}
add_action( 'woocommerce_account_my-tickets_endpoint', 'vatan_my_tickets_endpoint_content' );

/**
 * Collect ticket lines for an order — flat list, one entry per cart line.
 *
 * @param WC_Order $order
 * @return array<int,array>
 */
function vatan_collect_order_tickets( $order ) {
	$tickets = array();
	foreach ( $order->get_items() as $item_id => $item ) {
		$event_id = (int) $item->get_meta( '_vatan_event_id' );
		if ( ! $event_id ) {
			continue;
		}
		$seats = $item->get_meta( '_vatan_seats' );
		if ( ! is_array( $seats ) ) {
			$seats = array();
		}
		$seat_keys = array();
		foreach ( $seats as $s ) {
			if ( isset( $s['row'], $s['col'] ) ) {
				$seat_keys[] = $s['row'] . '.' . $s['col'];
			} elseif ( isset( $s['table'], $s['seat'] ) ) {
				$seat_keys[] = $s['table'] . '.' . $s['seat'];
			}
		}
		$event_date = (string) $item->get_meta( '_event_date' );

		$tickets[] = array(
			'order_id'      => $order->get_id(),
			'order_number'  => $order->get_order_number(),
			'item_id'       => $item_id,
			'event_id'      => $event_id,
			'event_title'   => get_the_title( $event_id ),
			'event_date'    => $event_date,
			'date_display'  => vatan_event_date_display( $event_date ),
			'ticket_type'   => (string) $item->get_meta( '_vatan_ticket_type' ),
			'seats'         => $seats,
			'seat_keys'     => $seat_keys,
			'price'         => (float) $item->get_total(),
			'status'        => $order->get_status(),
			// QR payload: prefix + order/item + a salted hash so payloads can't be forged.
			'qr_data'       => sprintf(
				'VATAN:%d:%d:%s',
				$order->get_id(),
				$item_id,
				wp_hash( $order->get_id() . '|' . $item_id )
			),
		);
	}
	return $tickets;
}

/**
 * All tickets owned by a user, across all orders. Used by the My Tickets page.
 */
function vatan_get_user_tickets( $user_id ) {
	if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}
	$orders = wc_get_orders( array(
		'customer_id' => $user_id,
		'status'      => array( 'wc-completed', 'wc-processing' ),
		'limit'       => -1,
		'orderby'     => 'date',
		'order'       => 'DESC',
	) );
	$out = array();
	foreach ( $orders as $order ) {
		$out = array_merge( $out, vatan_collect_order_tickets( $order ) );
	}
	return $out;
}

/* ---------- 10. Asset enqueuing for My Tickets ---------- */

function vatan_enqueue_my_tickets_assets() {
	// Match the my-tickets page even when Polylang or another plugin has
	// rewritten the URL with a language prefix (e.g. /fa/my-account/...).
	// We accept any of:
	//   • is_wc_endpoint_url('my-tickets') — the canonical WC check.
	//   • Request URI contains "/my-tickets/" — covers Polylang-prefixed URLs.
	//   • Query var 'my-tickets' is set — covers EP_ROOT/EP_PAGES rewrites.
	$is_my_tickets = ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'my-tickets' ) );

	if ( ! $is_my_tickets && isset( $_SERVER['REQUEST_URI'] ) ) {
		$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
		if ( false !== strpos( $uri, '/my-tickets/' ) || false !== strpos( $uri, '/my-tickets' ) ) {
			$is_my_tickets = true;
		}
	}

	if ( ! $is_my_tickets && get_query_var( 'my-tickets', null ) !== null ) {
		$is_my_tickets = true;
	}

	if ( ! $is_my_tickets ) {
		return;
	}

	// Both libraries are self-hosted under /assets/js/vendor/ so the My
	// Tickets page works regardless of which external CDNs are reachable.
	//   - qrcode-generator: ~55KB pure-JS QR encoder (renders to canvas).
	//   - html2pdf:         ~880KB bundle of jsPDF + html2canvas.
	wp_enqueue_script(
		'vatan-qrcode',
		VATAN_EVENT_URI . '/assets/js/vendor/qrcode.min.js',
		array(),
		'1.4.4',
		true
	);
	wp_enqueue_script(
		'html2pdf',
		VATAN_EVENT_URI . '/assets/js/vendor/html2pdf.bundle.min.js',
		array(),
		'0.10.2',
		true
	);
	wp_enqueue_script(
		'vatan-my-tickets',
		VATAN_EVENT_URI . '/assets/js/my-tickets.js',
		array( 'vatan-qrcode', 'html2pdf', 'vatan-main' ),
		VATAN_EVENT_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'vatan_enqueue_my_tickets_assets', 20 );
