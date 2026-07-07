<?php
/**
 * Ticket check-in / door scanner.
 *
 * Lets venue staff verify customer QR codes at the door. Flow:
 *   1. Staff opens Vatan Event → Door Scanner in wp-admin.
 *   2. Camera reads the QR, frontend POSTs the payload to
 *      /wp-json/vatan/v1/checkin.
 *   3. Endpoint validates the signature, finds the matching order item,
 *      checks payment status, and records the check-in timestamp on the
 *      item meta (`_vatan_checked_in_at`). Already-used tickets are
 *      rejected — same physical ticket can't enter twice.
 *
 * QR payload format (built in vatan_get_order_tickets()):
 *   VATAN:<order_id>:<item_id>:<wp_hash(order_id|item_id)>
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* =============================================================================
 *  REST endpoint
 * ===========================================================================*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'vatan/v1', '/checkin', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'vatan_checkin_can_user',
		'callback'            => 'vatan_rest_checkin',
		'args'                => array(
			'payload' => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'event_id' => array(
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
			),
		),
	) );
} );

/**
 * Anyone with `manage_woocommerce` (shop manager / admin) can check tickets.
 */
function vatan_checkin_can_user(): bool {
	return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' );
}

/**
 * POST /vatan/v1/checkin
 *
 * Returns JSON of shape:
 *   { status: 'valid'|'used'|'wrong_event'|'unpaid'|'invalid',
 *     ticket: { id, event_title, ticket_type, seats, customer, order_number, checked_in_at } }
 */
function vatan_rest_checkin( WP_REST_Request $request ): WP_REST_Response {
	$payload  = (string) $request->get_param( 'payload' );
	$event_id = (int) $request->get_param( 'event_id' );

	$result = vatan_checkin_verify_payload( $payload, $event_id );
	return rest_ensure_response( $result );
}

/**
 * Parse + validate a QR payload. Returns the same dict shape REST does so
 * the verification can be reused (e.g. for the admin manual-lookup form).
 */
function vatan_checkin_verify_payload( string $payload, int $event_id_filter = 0 ): array {
	$payload = trim( $payload );
	$parts   = explode( ':', $payload, 4 );

	if ( count( $parts ) !== 4 || 'VATAN' !== $parts[0] ) {
		return array( 'status' => 'invalid', 'message' => 'پیلود QR قابل شناسایی نیست.' );
	}

	$order_id = (int) $parts[1];
	$item_id  = (int) $parts[2];
	$sig      = (string) $parts[3];

	// Recompute the signature to guard against forged QRs.
	$expected = wp_hash( $order_id . '|' . $item_id );
	if ( ! hash_equals( $expected, $sig ) ) {
		return array( 'status' => 'invalid', 'message' => 'امضای بلیت نامعتبر است — احتمالاً جعلی.' );
	}

	if ( ! function_exists( 'wc_get_order' ) ) {
		return array( 'status' => 'invalid', 'message' => 'WooCommerce فعال نیست.' );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return array( 'status' => 'invalid', 'message' => 'سفارش یافت نشد.' );
	}

	$item = $order->get_item( $item_id );
	if ( ! $item || $item->get_order_id() !== $order_id ) {
		return array( 'status' => 'invalid', 'message' => 'این آیتم متعلق به سفارش نیست.' );
	}

	// Tickets are valid only on completed / processing orders.
	if ( ! in_array( $order->get_status(), array( 'completed', 'processing' ), true ) ) {
		return array(
			'status'  => 'unpaid',
			'message' => 'سفارش پرداخت نشده — وضعیت: ' . wc_get_order_status_name( $order->get_status() ),
			'ticket'  => vatan_checkin_ticket_summary( $order, $item, null ),
		);
	}

	$ticket_event = (int) $item->get_meta( '_vatan_event_id' );
	if ( $event_id_filter && $ticket_event && $ticket_event !== $event_id_filter ) {
		return array(
			'status'  => 'wrong_event',
			'message' => 'این بلیت برای رویداد دیگری است.',
			'ticket'  => vatan_checkin_ticket_summary( $order, $item, null ),
		);
	}

	// Has the ticket already been used?
	$existing = (string) $item->get_meta( '_vatan_checked_in_at' );
	if ( $existing ) {
		return array(
			'status'  => 'used',
			'message' => 'این بلیت قبلاً در ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $existing ) ) . ' استفاده شده.',
			'ticket'  => vatan_checkin_ticket_summary( $order, $item, $existing ),
		);
	}

	// Mark it used. Stamp time + the staff member who scanned.
	$now = current_time( 'mysql' );
	$item->add_meta_data( '_vatan_checked_in_at', $now, true );
	$item->add_meta_data( '_vatan_checked_in_by', get_current_user_id(), true );
	$item->save();

	return array(
		'status'  => 'valid',
		'message' => 'بلیت معتبر است — ورود تأیید شد.',
		'ticket'  => vatan_checkin_ticket_summary( $order, $item, $now ),
	);
}

/**
 * Compact ticket record for the response.
 */
function vatan_checkin_ticket_summary( $order, $item, $checked_in_at ): array {
	$event_id    = (int) $item->get_meta( '_vatan_event_id' );
	$event_title = $event_id ? get_the_title( $event_id ) : '';
	$seats       = (array) $item->get_meta( '_vatan_seats' );

	return array(
		'id'             => $item->get_id(),
		'order_id'       => $order->get_id(),
		'order_number'   => $order->get_order_number(),
		'customer'       => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ?: $order->get_billing_email(),
		'event_title'    => $event_title,
		'event_id'       => $event_id,
		'ticket_type'    => (string) $item->get_meta( '_vatan_ticket_type' ),
		'seats'          => array_values( array_filter( array_map( 'strval', $seats ) ) ),
		'checked_in_at'  => $checked_in_at,
	);
}

/* =============================================================================
 *  Admin "Door Scanner" page
 * ===========================================================================*/

add_action( 'admin_menu', function () {
	add_submenu_page(
		'vatan-dashboard', // parent slug — set in /inc/admin-panel.php
		__( 'Door Scanner', 'vatan-event' ),
		__( 'Door Scanner', 'vatan-event' ),
		'manage_woocommerce',
		'vatan-door-scanner',
		'vatan_render_door_scanner_page'
	);
}, 60 );

function vatan_render_door_scanner_page(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Permission denied.' );
	}

	// Event filter — optional, lets staff scope check-in to a single event.
	$events = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'meta_value',
		'meta_key'       => 'event_date',
		'order'          => 'ASC',
		'fields'         => array( 'ID', 'post_title' ),
	) );
	?>
	<div class="wrap vatan-door">
		<h1><?php esc_html_e( 'Door Scanner', 'vatan-event' ); ?></h1>
		<p class="vatan-door__lead">
			<?php esc_html_e( 'Scan a customer\'s QR code to verify their ticket. Already-used tickets and tickets for other events are rejected.', 'vatan-event' ); ?>
		</p>

		<div class="vatan-door__layout">
			<section class="vatan-door__cam-card">
				<header class="vatan-door__head">
					<label for="vatan-door-event">
						<?php esc_html_e( 'Event filter', 'vatan-event' ); ?>
					</label>
					<select id="vatan-door-event">
						<option value=""><?php esc_html_e( 'Any event', 'vatan-event' ); ?></option>
						<?php foreach ( $events as $ev ) : ?>
							<option value="<?php echo esc_attr( $ev->ID ); ?>"><?php echo esc_html( $ev->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button button-primary" id="vatan-door-start">
						<?php esc_html_e( 'Start camera', 'vatan-event' ); ?>
					</button>
				</header>

				<div class="vatan-door__viewport">
					<video id="vatan-door-video" playsinline muted></video>
					<canvas id="vatan-door-canvas" hidden></canvas>
					<div class="vatan-door__crosshair" aria-hidden="true"></div>
				</div>

				<details class="vatan-door__manual">
					<summary><?php esc_html_e( 'Manual lookup (no camera)', 'vatan-event' ); ?></summary>
					<div class="vatan-door__manual-body">
						<input type="text" id="vatan-door-manual-input" class="regular-text"
						       placeholder="VATAN:123:456:..." />
						<button type="button" class="button" id="vatan-door-manual-submit">
							<?php esc_html_e( 'Check', 'vatan-event' ); ?>
						</button>
					</div>
				</details>
			</section>

			<section class="vatan-door__result-card">
				<h2><?php esc_html_e( 'Last scan', 'vatan-event' ); ?></h2>
				<div class="vatan-door__result" id="vatan-door-result" data-empty="true">
					<p class="vatan-door__placeholder">
						<?php esc_html_e( 'Awaiting scan…', 'vatan-event' ); ?>
					</p>
				</div>

				<h2 style="margin-top:24px"><?php esc_html_e( 'Recent', 'vatan-event' ); ?></h2>
				<ul class="vatan-door__history" id="vatan-door-history">
					<li class="vatan-door__history-empty">
						<?php esc_html_e( 'No scans yet.', 'vatan-event' ); ?>
					</li>
				</ul>
			</section>
		</div>
	</div>
	<?php
}

/* =============================================================================
 *  Asset enqueue
 * ===========================================================================*/

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	// WordPress builds the hook as "<parent-slug>_page_<child-slug>" — for
	// our menu (parent = vatan-dashboard) that's
	// "vatan-ticket_page_vatan-door-scanner" or similar. Match by child slug
	// to stay tolerant of parent renames.
	if ( false === strpos( (string) $hook, 'vatan-door-scanner' ) ) {
		return;
	}

	wp_enqueue_script(
		'vatan-jsqr',
		VATAN_EVENT_URI . '/assets/js/vendor/jsqr.min.js',
		array(),
		'1.4.0',
		true
	);
	wp_enqueue_script(
		'vatan-door-scanner',
		VATAN_EVENT_URI . '/assets/admin/js/door-scanner.js',
		array( 'vatan-jsqr', 'wp-api-fetch' ),
		VATAN_EVENT_VERSION,
		true
	);
	wp_localize_script( 'vatan-door-scanner', 'vatanDoor', array(
		'restUrl'   => esc_url_raw( rest_url( 'vatan/v1/checkin' ) ),
		'restNonce' => wp_create_nonce( 'wp_rest' ),
		'i18n'      => array(
			'cameraDenied'  => __( 'Could not access camera — check browser permissions.', 'vatan-event' ),
			'starting'      => __( 'Starting camera…', 'vatan-event' ),
			'stop'          => __( 'Stop camera', 'vatan-event' ),
			'start'         => __( 'Start camera', 'vatan-event' ),
			'networkError'  => __( 'Network error — could not contact the server.', 'vatan-event' ),
		),
	) );
} );

