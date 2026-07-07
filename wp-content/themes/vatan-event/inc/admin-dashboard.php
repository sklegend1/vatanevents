<?php
/**
 * Vatan Event — frontend admin dashboard.
 *
 * Serves a curated event-management UI at /admin/ so the client's staff
 * can manage events, sales, payouts and door check-in WITHOUT entering
 * wp-admin. Sub-views are routed via the `view` query var with pretty
 * rewrites:
 *
 *   /admin/                  → dashboard (overview)
 *   /admin/events/           → events list (filterable)
 *   /admin/events/edit/?id=  → event editor
 *   /admin/sales/            → sales analytics
 *   /admin/payouts/          → payouts list + record-payout form
 *   /admin/scanner/          → door scanner
 *
 * Lockout: any user with `manage_options` who isn't listed in
 * VATAN_WP_ADMIN_USER_IDS (a comma-separated user-ID constant set in
 * wp-config.php) is redirected from wp-admin → /admin/. If the constant
 * is undefined, user ID 1 (the site owner) keeps wp-admin access. Super
 * admins (multisite) always bypass the redirect.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* =============================================================================
 *  Capability + routing helpers
 * ===========================================================================*/

const VATAN_ADMIN_VIEWS = array( 'dashboard', 'events', 'sales', 'payouts', 'scanner', 'music' );

/**
 * Can the current user reach the frontend admin dashboard?
 * Admin role (`manage_options`) or shop manager (`manage_woocommerce`) — same
 * gate WP uses for the same surfaces in wp-admin.
 */
function vatan_admin_can_access(): bool {
	return is_user_logged_in() && (
		current_user_can( 'manage_options' ) ||
		current_user_can( 'manage_woocommerce' )
	);
}

/**
 * Returns the canonical /admin/ URL, optionally for a specific view.
 */
function vatan_admin_url( string $view = '', array $query = array() ): string {
	$base = function_exists( 'vatan_static_page_url' ) ? (string) vatan_static_page_url( 'admin' ) : '';
	if ( ! $base ) {
		$base = home_url( '/admin/' );
	}
	if ( $view && in_array( $view, VATAN_ADMIN_VIEWS, true ) && 'dashboard' !== $view ) {
		$base = trailingslashit( $base ) . $view . '/';
	}
	return $query ? add_query_arg( $query, $base ) : $base;
}

/**
 * Resolve the requested view from `?view=` (and the rewrite-injected
 * version). Falls back to the dashboard.
 */
function vatan_admin_current_view(): string {
	$view = sanitize_key( (string) get_query_var( 'view' ) );
	if ( ! $view || ! in_array( $view, VATAN_ADMIN_VIEWS, true ) ) {
		return 'dashboard';
	}
	return $view;
}

/* =============================================================================
 *  Rewrite rules — pretty URLs for the sub-views
 * ===========================================================================*/

add_action( 'init', function () {
	add_rewrite_tag( '%view%', '([^/]+)' );

	// /admin/<view>/edit/  → ?pagename=admin&view=<view>&vatan_action=edit
	add_rewrite_rule(
		'^admin/([a-z-]+)/edit/?$',
		'index.php?pagename=admin&view=$matches[1]&vatan_action=edit',
		'top'
	);

	// /admin/<view>/  → ?pagename=admin&view=<view>
	add_rewrite_rule(
		'^admin/([a-z-]+)/?$',
		'index.php?pagename=admin&view=$matches[1]',
		'top'
	);
}, 11 );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'view';
	$vars[] = 'vatan_action';
	return $vars;
} );

/**
 * Force the page-admin.php template for any /admin/* request so the custom
 * HTML shell (no site header/footer) is always used, regardless of whether
 * the "admin" page has the template explicitly selected in the database.
 */
add_filter( 'template_include', function ( $template ) {
	if ( ! vatan_is_admin_request() ) {
		return $template;
	}
	$admin_template = locate_template( 'page-admin.php', false, false );
	if ( $admin_template ) {
		return $admin_template;
	}
	return $template;
}, 99 );

/**
 * Suppress the WordPress admin bar on the frontend admin panel.
 * The bar's markup and the 32px margin it injects on <html>/<body>
 * both need to be gone — the dashboard has its own topbar chrome.
 */
add_action( 'after_setup_theme', function () {
	// Use REQUEST_URI directly: get_query_var() is not yet available here,
	// but a simple prefix match is sufficient and safe.
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	if ( preg_match( '#^/admin(/|\?|$)#', strtok( $uri, '?' ) . ( strpos( $uri, '?' ) !== false ? '?' : '' ) ) ) {
		show_admin_bar( false );
	}
} );

/* =============================================================================
 *  wp-admin lockout — redirect non-allow-listed admins to /admin/
 * ===========================================================================*/

/**
 * Whether the current user should be bounced out of wp-admin.
 * False for:
 *   - non-admins (no `manage_options` — they don't need wp-admin anyway)
 *   - super-admins (multisite network admins always keep wp-admin)
 *   - users in VATAN_WP_ADMIN_USER_IDS (developer / site owner)
 *   - AJAX / cron / REST requests
 */
function vatan_should_redirect_from_wp_admin(): bool {
	if ( wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( ! is_user_logged_in() ) {
		return false;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	if ( is_multisite() && current_user_can( 'manage_network' ) ) {
		return false;
	}

	$allowed = defined( 'VATAN_WP_ADMIN_USER_IDS' )
		? array_map( 'intval', array_filter( explode( ',', (string) VATAN_WP_ADMIN_USER_IDS ) ) )
		: array( 1 );

	return ! in_array( (int) get_current_user_id(), $allowed, true );
}

add_action( 'admin_init', function () {
	if ( vatan_should_redirect_from_wp_admin() ) {
		wp_safe_redirect( vatan_admin_url() );
		exit;
	}
}, 1 );

/* =============================================================================
 *  Login flow — admins land on /admin/, customers on /my-account/
 * ===========================================================================*/

/**
 * Override the default post-login redirect. Hooked from inc/auth.php (the
 * custom /login/ form calls `apply_filters( 'vatan_login_redirect', … )`
 * after a successful signon). We just centralise the role decision here.
 */
function vatan_decide_post_login_redirect( string $current_default = '' ): string {
	if ( $current_default ) {
		return $current_default;
	}
	if ( vatan_admin_can_access() ) {
		return vatan_admin_url();
	}
	if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
		return wc_get_account_endpoint_url( 'dashboard' );
	}
	return home_url( '/' );
}
add_filter( 'vatan_login_redirect', 'vatan_decide_post_login_redirect', 10, 1 );

/* =============================================================================
 *  Sidebar nav definition — used by templates/admin/shell.php
 * ===========================================================================*/

function vatan_admin_nav_items(): array {
	return array(
		'dashboard' => array(
			'label' => __( 'Dashboard', 'vatan-event' ),
			'icon'  => '🏠',
			'url'   => vatan_admin_url( 'dashboard' ),
			'cap'   => 'manage_woocommerce',
		),
		'events'    => array(
			'label' => __( 'Events', 'vatan-event' ),
			'icon'  => '🎪',
			'url'   => vatan_admin_url( 'events' ),
			'cap'   => 'edit_posts',
		),
		'sales'     => array(
			'label' => __( 'Sales', 'vatan-event' ),
			'icon'  => '📈',
			'url'   => vatan_admin_url( 'sales' ),
			'cap'   => 'manage_woocommerce',
		),
		'payouts'   => array(
			'label' => __( 'Payouts', 'vatan-event' ),
			'icon'  => '💳',
			'url'   => vatan_admin_url( 'payouts' ),
			'cap'   => 'manage_options',
		),
		'scanner'   => array(
			'label' => __( 'Door Scanner', 'vatan-event' ),
			'icon'  => '🎫',
			'url'   => vatan_admin_url( 'scanner' ),
			'cap'   => 'manage_woocommerce',
		),
		'music'     => array(
			'label' => __( 'Music', 'vatan-event' ),
			'icon'  => '🎵',
			'url'   => vatan_admin_url( 'music' ),
			'cap'   => 'manage_woocommerce',
		),
	);
}

/* =============================================================================
 *  Frontend admin: POST handlers (fire before the page renders)
 *
 *  These hook on `template_redirect` priority 5, before page-admin.php starts
 *  printing HTML — so we can wp_safe_redirect after writes.
 * ===========================================================================*/

/**
 * Whether the current request is the frontend admin (any view) — short
 * helper used by the POST handlers below to gate themselves.
 */
function vatan_is_admin_request(): bool {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	$slug = (string) get_query_var( 'pagename' );
	if ( 'admin' === $slug ) {
		return true;
	}
	// Fallback: pagename may not be set when the page is the front page.
	$req = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
	return (bool) preg_match( '#^/admin(/|\?|$)#', $req );
}

/**
 * Handle the "record payout" form posted from /admin/payouts/.
 */
function vatan_admin_handle_payout_post(): void {
	if ( ! vatan_is_admin_request() || empty( $_POST['vatan_admin_payout'] ) ) {
		return;
	}
	if ( ! vatan_admin_can_access() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'vatan_admin_record_payout' );

	if ( ! function_exists( 'vatan_record_payout' ) ) {
		return;
	}

	$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
	$amount   = isset( $_POST['amount'] )   ? (float) $_POST['amount'] : 0;
	$paid_at  = isset( $_POST['paid_at'] )  ? sanitize_text_field( wp_unslash( $_POST['paid_at'] ) ) : '';
	$notes    = isset( $_POST['notes'] )    ? wp_unslash( $_POST['notes'] ) : '';

	$result = vatan_record_payout( array(
		'event_id' => $event_id,
		'amount'   => $amount,
		'paid_at'  => $paid_at,
		'notes'    => $notes,
	) );

	if ( is_wp_error( $result ) ) {
		set_transient(
			'vatan_admin_payout_error_' . get_current_user_id(),
			$result->get_error_message(),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( vatan_admin_url( 'payouts', array( 'vatan_action' => 'new', 'status' => 'error' ) ) );
		exit;
	}

	if ( function_exists( 'vatan_notify_organizer_of_payout' ) ) {
		vatan_notify_organizer_of_payout( $event_id, (float) $amount, $paid_at, (string) $notes );
	}

	wp_safe_redirect( vatan_admin_url( 'payouts', array( 'status' => 'recorded' ) ) );
	exit;
}
add_action( 'template_redirect', 'vatan_admin_handle_payout_post', 5 );

/**
 * Handle the "delete payout" link from /admin/payouts/.
 */
function vatan_admin_handle_payout_delete(): void {
	if ( ! vatan_is_admin_request() || empty( $_GET['vatan_payout_delete'] ) ) {
		return;
	}
	if ( ! vatan_admin_can_access() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$id = (int) $_GET['vatan_payout_delete'];
	check_admin_referer( 'vatan_admin_delete_payout_' . $id );

	if ( function_exists( 'vatan_delete_payout' ) ) {
		vatan_delete_payout( $id );
	}
	wp_safe_redirect( vatan_admin_url( 'payouts', array( 'status' => 'deleted' ) ) );
	exit;
}
add_action( 'template_redirect', 'vatan_admin_handle_payout_delete', 5 );

/**
 * Handle the inline event-status toggle posted from /admin/events/edit/.
 */
function vatan_admin_handle_event_status_post(): void {
	if ( ! vatan_is_admin_request() || empty( $_POST['vatan_admin_event_status'] ) ) {
		return;
	}
	if ( ! vatan_admin_can_access() ) {
		return;
	}
	$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
	if ( ! $event_id || 'event' !== get_post_type( $event_id ) ) {
		return;
	}
	check_admin_referer( 'vatan_admin_event_status_' . $event_id );

	if ( ! current_user_can( 'edit_post', $event_id ) ) {
		return;
	}

	$new_status = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
	$allowed    = array( 'publish', 'pending', 'draft', 'trash' );
	if ( ! in_array( $new_status, $allowed, true ) ) {
		return;
	}

	if ( 'trash' === $new_status ) {
		wp_trash_post( $event_id );
		wp_safe_redirect( vatan_admin_url( 'events', array( 'status' => 'all', 'trashed' => 1 ) ) );
		exit;
	}

	wp_update_post( array( 'ID' => $event_id, 'post_status' => $new_status ) );
	wp_safe_redirect( vatan_admin_url( 'events', array( 'vatan_action' => 'edit', 'id' => $event_id, 'status_updated' => 1 ) ) );
	exit;
}
add_action( 'template_redirect', 'vatan_admin_handle_event_status_post', 5 );

/* =============================================================================
 *  Frontend admin: scanner asset enqueue
 *
 *  The door scanner reuses `assets/js/vendor/jsqr.min.js` and the existing
 *  `assets/admin/js/door-scanner.js` module already shipped for wp-admin.
 *  The markup we render in templates/admin/views/scanner.php matches the DOM
 *  IDs the script expects.
 * ===========================================================================*/

/**
 * Admin dashboard stylesheet — loaded on every /admin/* view. Pulled in
 * after the public theme's main.css so it can reference the brand vars.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! vatan_is_admin_request() ) {
		return;
	}
	if ( ! defined( 'VATAN_EVENT_URI' ) || ! defined( 'VATAN_EVENT_VERSION' ) ) {
		return;
	}
	wp_enqueue_style(
		'vatan-admin-dashboard',
		VATAN_EVENT_URI . '/assets/css/admin-dashboard.css',
		array( 'vatan-main' ),
		VATAN_EVENT_VERSION
	);
}, 30 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! vatan_is_admin_request() ) {
		return;
	}
	if ( 'scanner' !== vatan_admin_current_view() ) {
		return;
	}
	if ( ! defined( 'VATAN_EVENT_URI' ) || ! defined( 'VATAN_EVENT_VERSION' ) ) {
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
			'cameraDenied' => __( 'Could not access camera — check browser permissions.', 'vatan-event' ),
			'starting'     => __( 'Starting camera…', 'vatan-event' ),
			'stop'         => __( 'Stop camera', 'vatan-event' ),
			'start'        => __( 'Start camera', 'vatan-event' ),
			'networkError' => __( 'Network error — could not contact the server.', 'vatan-event' ),
		),
	) );
}, 20 );
