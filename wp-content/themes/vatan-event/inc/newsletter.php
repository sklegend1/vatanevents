<?php
/**
 * Newsletter — custom DB table + REST submit endpoint.
 *
 * Storage: a single `wp_vatan_subscribers` table holds one row per email.
 * The schema (id / email / status / source / locale / ip / ua /
 * subscribed_at) keeps room for a future double-opt-in flow and per-source
 * reporting without further migrations.
 *
 * Endpoint: POST /wp-json/vatan/v1/newsletter
 *   - Verifies the page-rendered nonce (`vatan_newsletter`).
 *   - Rejects bots via a honeypot field (`website`) — bots fill every input.
 *   - Sanitises + validates the email; rejects banned addresses.
 *   - UPSERTs by email so re-subscribing existing emails just bumps the row.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_NEWSLETTER_TABLE_VERSION = '1.0.0';

/**
 * The full table name including the WP prefix.
 *
 * @return string
 */
function vatan_newsletter_table() {
	global $wpdb;
	return $wpdb->prefix . 'vatan_subscribers';
}

/**
 * Create (or migrate) the subscribers table. Runs on theme activation and
 * on admin_init when the stored schema version doesn't match the constant
 * above — that's how we pick up schema bumps without manual SQL.
 */
function vatan_newsletter_maybe_install_table() {
	$installed = get_option( 'vatan_newsletter_schema_version' );
	if ( $installed === VATAN_NEWSLETTER_TABLE_VERSION ) {
		return;
	}

	global $wpdb;
	$table   = vatan_newsletter_table();
	$charset = $wpdb->get_charset_collate();

	// dbDelta needs PRIMARY KEY on its own line and two spaces after PRIMARY KEY.
	$sql = "CREATE TABLE $table (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		email VARCHAR(190) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'subscribed',
		source VARCHAR(60) NOT NULL DEFAULT '',
		locale VARCHAR(10) NOT NULL DEFAULT '',
		ip_address VARCHAR(45) NOT NULL DEFAULT '',
		user_agent VARCHAR(255) NOT NULL DEFAULT '',
		subscribed_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY uniq_email (email),
		KEY status_idx (status)
	) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'vatan_newsletter_schema_version', VATAN_NEWSLETTER_TABLE_VERSION );
}
add_action( 'after_switch_theme', 'vatan_newsletter_maybe_install_table' );
add_action( 'admin_init', 'vatan_newsletter_maybe_install_table', 5 );

/**
 * Register the POST endpoint.
 */
function vatan_register_newsletter_route() {
	register_rest_route( 'vatan/v1', '/newsletter', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'vatan_rest_newsletter_subscribe',
		'args'                => array(
			'email'   => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
			),
			'website' => array( // honeypot — must arrive empty
				'type'     => 'string',
				'required' => false,
			),
			'source'  => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_wpnonce' => array(
				'type'     => 'string',
				'required' => false,
			),
		),
	) );
}
add_action( 'rest_api_init', 'vatan_register_newsletter_route' );

/**
 * Handle a newsletter subscription submission.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function vatan_rest_newsletter_subscribe( WP_REST_Request $request ) {
	// Honeypot — bots cheerfully fill every input.
	$honeypot = (string) $request->get_param( 'website' );
	if ( '' !== trim( $honeypot ) ) {
		// Pretend success so the bot moves on; no row written.
		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Subscribed.', 'vatan-event' ),
		) );
	}

	// Nonce. Accept from header or body; both work with WP's standard fetch helper.
	$nonce = $request->get_header( 'X-WP-Nonce' );
	if ( ! $nonce ) {
		$nonce = (string) $request->get_param( '_wpnonce' );
	}
	// Page-rendered nonce: action `vatan_newsletter`. wp_rest covers WC AJAX,
	// either is acceptable.
	if (
		! ( $nonce && ( wp_verify_nonce( $nonce, 'vatan_newsletter' ) || wp_verify_nonce( $nonce, 'wp_rest' ) ) )
	) {
		return new WP_Error( 'vatan_newsletter_bad_nonce', __( 'Security check failed. Refresh and try again.', 'vatan-event' ), array( 'status' => 403 ) );
	}

	$email = sanitize_email( (string) $request->get_param( 'email' ) );
	if ( ! $email || ! is_email( $email ) ) {
		return new WP_Error( 'vatan_newsletter_bad_email', __( 'Please enter a valid email address.', 'vatan-event' ), array( 'status' => 422 ) );
	}

	$existing = vatan_newsletter_find_subscriber( $email );
	if ( $existing && 'subscribed' === $existing->status ) {
		return rest_ensure_response( array(
			'success' => true,
			'already' => true,
			'message' => __( 'You are already subscribed — thank you!', 'vatan-event' ),
		) );
	}

	$result = vatan_newsletter_upsert( array(
		'email'         => $email,
		'source'        => substr( (string) $request->get_param( 'source' ), 0, 60 ),
		'locale'        => substr( (string) get_locale(), 0, 10 ),
		'ip_address'    => vatan_newsletter_client_ip(),
		'user_agent'    => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
		'subscribed_at' => current_time( 'mysql' ),
	) );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	/**
	 * Fires after a successful subscription. Hook in to push to an external
	 * provider (Mailchimp / FluentCRM) when one is added later.
	 *
	 * @param string $email
	 * @param array  $row
	 */
	do_action( 'vatan_newsletter_subscribed', $email, $result );

	return rest_ensure_response( array(
		'success' => true,
		'message' => __( 'Subscribed — check your inbox for updates.', 'vatan-event' ),
	) );
}

/**
 * Best-effort client IP for audit logging. Honors a couple of common
 * reverse-proxy headers, falls back to REMOTE_ADDR. Never trust this for
 * security decisions — only for observability.
 *
 * @return string
 */
function vatan_newsletter_client_ip() {
	$candidates = array(
		'HTTP_CF_CONNECTING_IP', // Cloudflare
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'REMOTE_ADDR',
	);
	foreach ( $candidates as $h ) {
		if ( empty( $_SERVER[ $h ] ) ) {
			continue;
		}
		$raw = wp_unslash( (string) $_SERVER[ $h ] );
		// XFF can be a comma-separated chain — take the first.
		$ip  = trim( explode( ',', $raw )[0] );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return substr( $ip, 0, 45 );
		}
	}
	return '';
}

/**
 * Look up one subscriber by email. Returns the row object or null.
 *
 * @param string $email
 * @return object|null
 */
function vatan_newsletter_find_subscriber( $email ) {
	global $wpdb;
	$table = vatan_newsletter_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	return $row ?: null;
}

/**
 * Insert or update a subscriber row.
 *
 * @param array $data
 * @return array|WP_Error
 */
function vatan_newsletter_upsert( $data ) {
	global $wpdb;
	$table = vatan_newsletter_table();

	$row = array(
		'email'         => $data['email'],
		'status'        => 'subscribed',
		'source'        => isset( $data['source'] ) ? $data['source'] : '',
		'locale'        => isset( $data['locale'] ) ? $data['locale'] : '',
		'ip_address'    => isset( $data['ip_address'] ) ? $data['ip_address'] : '',
		'user_agent'    => isset( $data['user_agent'] ) ? $data['user_agent'] : '',
		'subscribed_at' => isset( $data['subscribed_at'] ) ? $data['subscribed_at'] : current_time( 'mysql' ),
	);

	$existing = vatan_newsletter_find_subscriber( $data['email'] );
	if ( $existing ) {
		$updated = $wpdb->update(
			$table,
			array(
				'status'        => 'subscribed',
				'source'        => $row['source'] ?: $existing->source,
				'locale'        => $row['locale'] ?: $existing->locale,
				'subscribed_at' => $row['subscribed_at'],
			),
			array( 'id' => (int) $existing->id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			return new WP_Error( 'vatan_newsletter_db', __( 'Could not save your subscription. Please try again.', 'vatan-event' ), array( 'status' => 500 ) );
		}
		$row['id'] = (int) $existing->id;
		return $row;
	}

	$inserted = $wpdb->insert(
		$table,
		$row,
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	if ( false === $inserted ) {
		return new WP_Error( 'vatan_newsletter_db', __( 'Could not save your subscription. Please try again.', 'vatan-event' ), array( 'status' => 500 ) );
	}
	$row['id'] = (int) $wpdb->insert_id;
	return $row;
}

/**
 * Total subscriber count (subscribed status only). Used by the dashboard.
 *
 * @return int
 */
function vatan_newsletter_subscriber_count() {
	global $wpdb;
	$table = vatan_newsletter_table();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'subscribed'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
}
