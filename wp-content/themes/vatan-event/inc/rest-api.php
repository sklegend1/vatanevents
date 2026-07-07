<?php
/**
 * REST API endpoints (namespace `vatan/v1`).
 *
 * Public endpoints — no auth required for browsing events.
 * Endpoints that mutate state (cart, checkout) will be added later
 * with explicit nonce / capability checks.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all `vatan/v1/*` routes.
 */
function vatan_register_rest_routes() {
	register_rest_route( 'vatan/v1', '/events', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_get_events',
		'args'                => array(
			'q'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'required'          => false,
			),
			'city'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'required'          => false,
			),
			'country'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'required'          => false,
			),
			'lang'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'required'          => false,
				'description'       => 'Language slug to filter by. Defaults to the request\'s current language (Polylang).',
			),
			'category' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'required'          => false,
			),
			'date'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'required'          => false,
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 50,
				'sanitize_callback' => 'absint',
			),
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
		),
	) );

	register_rest_route( 'vatan/v1', '/seats/(?P<event_id>\d+)', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_get_seats',
		'args'                => array(
			'event_id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		),
	) );

	register_rest_route( 'vatan/v1', '/add-ticket', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_add_ticket',
		'args'                => array(
			'event_id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'seats'    => array(
				'type'     => 'array',
				'required' => true,
			),
		),
	) );
}
add_action( 'rest_api_init', 'vatan_register_rest_routes' );

/**
 * GET /vatan/v1/events
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function vatan_rest_get_events( WP_REST_Request $request ) {
	$args = array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => $request->get_param( 'per_page' ),
		'paged'          => $request->get_param( 'page' ),
		'no_found_rows'  => false,
	);

	$q = $request->get_param( 'q' );
	if ( $q ) {
		$args['s'] = $q;
	}

	$tax_query = array();

	// City is more specific than country — when both arrive, city wins and
	// country is ignored. When only country is set, include descendants so
	// "Germany" matches events tagged with Berlin / Hamburg / etc.
	$city    = $request->get_param( 'city' );
	$country = $request->get_param( 'country' );
	if ( $city && '0' !== $city ) {
		$tax_query[] = array(
			'taxonomy' => 'event_city',
			'field'    => is_numeric( $city ) ? 'term_id' : 'slug',
			'terms'    => $city,
		);
	} elseif ( $country && '0' !== $country ) {
		$tax_query[] = array(
			'taxonomy'         => 'event_city',
			'field'            => is_numeric( $country ) ? 'term_id' : 'slug',
			'terms'            => $country,
			'include_children' => true,
		);
	}

	$category = $request->get_param( 'category' );
	if ( $category && '0' !== $category ) {
		$tax_query[] = array(
			'taxonomy' => 'event_category',
			'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
			'terms'    => $category,
		);
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}
	if ( ! empty( $tax_query ) ) {
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	}

	$date = $request->get_param( 'date' );
	if ( $date ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'event_date',
				'value'   => $date,
				'compare' => '=',
				'type'    => 'DATE',
			),
		);
	}

	// Language filter — Polylang reads WP_Query's `lang` arg. Caller must
	// pass it explicitly (search.js does this from `vatanData.lang`). In
	// REST context Polylang's own current-language detection is unreliable
	// because there's no language URL prefix, so we don't auto-fallback —
	// if the caller doesn't pass `lang`, we return events in all languages.
	$lang = (string) $request->get_param( 'lang' );
	if ( '' !== $lang ) {
		$args['lang'] = $lang;
	}

	$query = new WP_Query( $args );
	$items = array();

	foreach ( $query->posts as $post ) {
		$items[] = vatan_format_event_for_rest( $post );
	}

	$response = rest_ensure_response( $items );
	$response->header( 'X-WP-Total', (string) $query->found_posts );
	$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

	return $response;
}

/**
 * Shape an event post into the JSON payload returned by the REST endpoint.
 *
 * @param WP_Post $post
 * @return array
 */
function vatan_format_event_for_rest( $post ) {
	$id        = (int) $post->ID;
	$thumb_id  = get_post_thumbnail_id( $id );
	$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';

	$event_date = '';
	$venue      = '';
	if ( function_exists( 'get_field' ) ) {
		$event_date = (string) get_field( 'event_date', $id );
		$venue      = (string) get_field( 'event_venue', $id );
	}
	if ( '' === $event_date ) {
		$event_date = (string) get_post_meta( $id, 'event_date', true );
	}
	if ( '' === $venue ) {
		$venue = (string) get_post_meta( $id, 'event_venue', true );
	}

	$cities = wp_get_post_terms( $id, 'event_city', array( 'fields' => 'names' ) );
	$cats   = wp_get_post_terms( $id, 'event_category', array( 'fields' => 'names' ) );

	return array(
		'id'        => $id,
		'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
		'permalink' => get_permalink( $post ),
		'thumbnail' => $thumb_url ? $thumb_url : '',
		'date'      => $event_date,
		'venue'     => $venue,
		'city'      => is_array( $cities ) && isset( $cities[0] ) ? $cities[0] : '',
		'category'  => is_array( $cats ) && isset( $cats[0] ) ? $cats[0] : '',
	);
}

/**
 * GET /vatan/v1/seats/{event_id}
 *
 * Returns the parsed seat-map config for an event:
 *   { rows, cols, sections: [...], reserved: ["1-5", ...] }
 *
 * Reads `seat_map_rows` / `seat_map_cols` ACF fields as the canonical grid
 * dimensions and the `seat_map_config` JSON textarea for sections + reserved
 * seats. Future enhancement: merge in seats locked by pending orders.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function vatan_rest_get_seats( WP_REST_Request $request ) {
	$event_id = (int) $request->get_param( 'event_id' );
	$post     = get_post( $event_id );

	if ( ! $post || 'event' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'not_found', __( 'Event not found.', 'vatan-event' ), array( 'status' => 404 ) );
	}

	$enabled = function_exists( 'get_field' ) ? (bool) get_field( 'seat_map_enabled', $event_id ) : false;
	if ( ! $enabled ) {
		return new WP_Error( 'no_seat_map', __( 'Seat map not enabled for this event.', 'vatan-event' ), array( 'status' => 404 ) );
	}

	$rows        = function_exists( 'get_field' ) ? (int) get_field( 'seat_map_rows', $event_id ) : 0;
	$cols        = function_exists( 'get_field' ) ? (int) get_field( 'seat_map_cols', $event_id ) : 0;
	$config_json = function_exists( 'get_field' ) ? (string) get_field( 'seat_map_config', $event_id ) : '';

	$result = array(
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
			if ( isset( $decoded['rows'] ) ) {
				$result['rows'] = (int) $decoded['rows'];
			}
			if ( isset( $decoded['cols'] ) ) {
				$result['cols'] = (int) $decoded['cols'];
			}
			if ( isset( $decoded['sections'] ) && is_array( $decoded['sections'] ) ) {
				$result['sections'] = array_values( array_map( 'vatan_normalize_seat_section', $decoded['sections'] ) );
			}
			if ( isset( $decoded['reserved'] ) && is_array( $decoded['reserved'] ) ) {
				$result['reserved'] = array_values( array_filter( array_map( 'vatan_normalize_seat_key', $decoded['reserved'] ) ) );
			}
			if ( isset( $decoded['hallways'] ) && is_array( $decoded['hallways'] ) ) {
				// Hallways are grid cells only — restrict to numeric "row-col".
				$result['hallways'] = array_values( array_filter(
					array_map( 'vatan_normalize_seat_key', $decoded['hallways'] ),
					function ( $k ) { return preg_match( '/^\d+-\d+$/', $k ); }
				) );
			}
			if ( isset( $decoded['tables'] ) && is_array( $decoded['tables'] ) ) {
				$result['tables'] = array_values( array_filter( array_map( 'vatan_normalize_seat_table', $decoded['tables'] ) ) );
			}
		}
	}

	/**
	 * Filter the seat-map payload before it leaves the REST endpoint.
	 * Use to merge seats held by pending orders, etc.
	 *
	 * @param array $result   Parsed config payload.
	 * @param int   $event_id
	 */
	$result = apply_filters( 'vatan_rest_seats_payload', $result, $event_id );

	return rest_ensure_response( $result );
}

/**
 * Sanitize one section entry from the seat_map_config JSON.
 *
 * @param mixed $section
 * @return array
 */
function vatan_normalize_seat_section( $section ) {
	if ( ! is_array( $section ) ) {
		return array();
	}
	$rows = isset( $section['rows'] ) && is_array( $section['rows'] )
		? array_map( 'intval', $section['rows'] )
		: array();

	// Per-seat assignments (the GUI editor's output schema). Accepts both
	// grid keys ("row-col") and table seat keys ("Tid-seat").
	$seats = array();
	if ( isset( $section['seats'] ) && is_array( $section['seats'] ) ) {
		foreach ( $section['seats'] as $key ) {
			$normalized = vatan_normalize_seat_key( $key );
			if ( '' !== $normalized ) {
				$seats[] = $normalized;
			}
		}
	}

	return array(
		'rows'  => $rows,
		'seats' => $seats,
		'type'  => isset( $section['type'] ) ? sanitize_text_field( $section['type'] ) : '',
		'price' => isset( $section['price'] ) ? (float) $section['price'] : 0.0,
		'color' => isset( $section['color'] ) && sanitize_hex_color( $section['color'] ) ? $section['color'] : '',
	);
}

/**
 * Normalize a "row-col" reserved-seat key. Drops anything that isn't
 * a pair of positive integers.
 *
 * @param mixed $key
 * @return string Empty string if the input was malformed.
 */
function vatan_normalize_seat_key( $key ) {
	if ( ! is_string( $key ) ) {
		return '';
	}
	// Grid seat: "row-col" with positive integers (e.g. "5-3").
	if ( preg_match( '/^\d+-\d+$/', $key ) ) {
		return $key;
	}
	// Table seat: "Txxx-N" where Txxx is a table id starting with `T` and
	// N is the seat number inside that table (e.g. "T1-5", "Tvip-8").
	if ( preg_match( '/^T[A-Za-z0-9_-]*-\d+$/', $key ) ) {
		return $key;
	}
	return '';
}

/**
 * Sanitize one round-table definition from the seat_map_config JSON.
 *
 * Shape:
 *   { "id": "T1", "seats": 8, "label": "Table 1",
 *     "type": "vip", "price": 3500000, "color": "#FF2D78",
 *     "row": 1 }
 *
 * `row` is the table's lane below (or above) the seat grid: row 1 is the
 * lane closest to the grid; row 2 is the next one out, etc. All tables in
 * the same row flex horizontally side-by-side. Using lanes (instead of
 * free x/y) makes overlap with the seat grid impossible by construction.
 *
 * Returns null when the entry is invalid so callers can filter it out.
 *
 * @param mixed $table
 * @return array|null
 */
function vatan_normalize_seat_table( $table ) {
	if ( ! is_array( $table ) ) {
		return null;
	}
	$id = isset( $table['id'] ) ? sanitize_text_field( (string) $table['id'] ) : '';
	if ( '' === $id || ! preg_match( '/^T[A-Za-z0-9_-]*$/', $id ) ) {
		return null;
	}
	$seats = isset( $table['seats'] ) ? (int) $table['seats'] : 0;
	$seats = max( 2, min( 20, $seats ) );
	if ( $seats < 2 ) {
		return null;
	}

	$row = isset( $table['row'] ) ? (int) $table['row'] : 1;
	$row = max( 1, min( 10, $row ) );

	return array(
		'id'    => $id,
		'seats' => $seats,
		'label' => isset( $table['label'] ) ? sanitize_text_field( (string) $table['label'] ) : '',
		'type'  => isset( $table['type'] )  ? sanitize_text_field( (string) $table['type'] )  : '',
		'price' => isset( $table['price'] ) ? (float) $table['price'] : 0.0,
		'color' => isset( $table['color'] ) && sanitize_hex_color( $table['color'] ) ? $table['color'] : '',
		'row'   => $row,
	);
}

/**
 * POST /vatan/v1/add-ticket
 *
 * Validates the seat selection then delegates to vatan_add_seats_to_cart()
 * (defined in inc/woocommerce.php) for actual cart insertion. WC handles
 * capacity / collision checks; this endpoint just sanitizes input.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function vatan_rest_add_ticket( WP_REST_Request $request ) {
	$event_id = (int) $request->get_param( 'event_id' );
	$seats    = $request->get_param( 'seats' );

	if ( ! $event_id || ! is_array( $seats ) || empty( $seats ) ) {
		return new WP_Error( 'invalid_input', __( 'Please select at least one seat.', 'vatan-event' ), array( 'status' => 400 ) );
	}

	$post = get_post( $event_id );
	if ( ! $post || 'event' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'not_found', __( 'Event not found.', 'vatan-event' ), array( 'status' => 404 ) );
	}

	$max = (int) apply_filters( 'vatan_max_selectable_seats', 10 );
	if ( count( $seats ) > $max ) {
		return new WP_Error(
			'too_many_seats',
			sprintf(
				/* translators: %d: maximum allowed seats */
				__( 'You can submit at most %d seats per request.', 'vatan-event' ),
				$max
			),
			array( 'status' => 400 )
		);
	}

	// Normalize input. Each entry is either a grid seat ({row,col,...}) or
	// a table seat ({table,seat,...}); anything else is discarded.
	$clean = array();
	foreach ( $seats as $seat ) {
		if ( ! is_array( $seat ) ) {
			continue;
		}
		$base = array(
			'type'  => isset( $seat['type'] ) ? sanitize_text_field( $seat['type'] ) : '',
			'price' => isset( $seat['price'] ) ? (float) $seat['price'] : 0.0,
		);

		if ( isset( $seat['row'], $seat['col'] ) ) {
			$row = (int) $seat['row'];
			$col = (int) $seat['col'];
			if ( $row > 0 && $col > 0 ) {
				$clean[] = array_merge( array( 'row' => $row, 'col' => $col ), $base );
			}
			continue;
		}

		if ( isset( $seat['table'], $seat['seat'] ) ) {
			$table  = sanitize_text_field( (string) $seat['table'] );
			$seat_n = (int) $seat['seat'];
			if ( $seat_n > 0 && preg_match( '/^T[A-Za-z0-9_-]*$/', $table ) ) {
				$clean[] = array_merge( array( 'table' => $table, 'seat' => $seat_n ), $base );
			}
			continue;
		}
	}

	if ( empty( $clean ) ) {
		return new WP_Error( 'invalid_seats', __( 'No valid seat in the request.', 'vatan-event' ), array( 'status' => 400 ) );
	}

	/**
	 * Fires when a seat selection is submitted via the REST API. Listeners
	 * can use this for analytics / logging. The actual cart insertion is
	 * handled by vatan_add_seats_to_cart() below.
	 *
	 * @param int   $event_id
	 * @param array $clean   Normalized seat list.
	 * @param int   $user_id 0 for guest.
	 */
	do_action( 'vatan_seats_submitted', $event_id, $clean, get_current_user_id() );

	// Delegate to WC integration.
	if ( ! function_exists( 'vatan_add_seats_to_cart' ) ) {
		// WC isn't active — return success but no cart side-effect.
		return rest_ensure_response( array(
			'success'  => true,
			'message'  => __( 'Tickets recorded (WooCommerce inactive — no cart created).', 'vatan-event' ),
			'cart_url' => home_url( '/' ),
			'seats'    => $clean,
		) );
	}

	$result = vatan_add_seats_to_cart( $event_id, $clean );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return rest_ensure_response( array(
		'success'   => true,
		'message'   => __( 'Tickets added to cart.', 'vatan-event' ),
		'cart_url'  => $result['cart_url'],
		'cart_keys' => $result['cart_keys'],
		'seats'     => $clean,
	) );
}
