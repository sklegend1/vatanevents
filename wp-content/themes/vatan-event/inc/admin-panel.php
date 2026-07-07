<?php
/**
 * Vatan Event admin panel — entry point.
 *
 * Registers the top-level "Vatan Event" admin menu, loads each subpage
 * controller, queues admin-scoped assets, and renders the dashboard.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

require_once VATAN_EVENT_DIR . '/inc/admin/theme-settings.php';
require_once VATAN_EVENT_DIR . '/inc/admin/seat-manager.php';
require_once VATAN_EVENT_DIR . '/inc/admin/sales-analytics.php';
require_once VATAN_EVENT_DIR . '/inc/admin/page-builder.php';
require_once VATAN_EVENT_DIR . '/inc/payouts.php';

// Page builder save handler — runs on admin_init so it can redirect-after-POST
// before any output is sent. The handler self-gates on $_POST['vatan_pb_submit'].
add_action( 'admin_init', 'vatan_page_builder_handle_submit' );

/**
 * Register the top-level "Vatan Event" menu and its submenus.
 */
function vatan_register_admin_menu() {
	add_menu_page(
		__( 'Vatan Event', 'vatan-event' ),
		__( 'Vatan Event', 'vatan-event' ),
		'manage_options',
		'vatan-dashboard',
		'vatan_dashboard_page',
		'dashicons-tickets-alt',
		2
	);

	add_submenu_page(
		'vatan-dashboard',
		__( 'Dashboard', 'vatan-event' ),
		__( 'Dashboard', 'vatan-event' ),
		'manage_options',
		'vatan-dashboard',
		'vatan_dashboard_page'
	);

	add_submenu_page(
		'vatan-dashboard',
		__( 'Events', 'vatan-event' ),
		__( 'Events', 'vatan-event' ),
		'edit_posts',
		'edit.php?post_type=event'
	);

	add_submenu_page(
		'vatan-dashboard',
		__( 'Page Builder', 'vatan-event' ),
		__( 'Page Builder', 'vatan-event' ),
		'manage_options',
		'vatan-page-builder',
		'vatan_page_builder_page'
	);

	add_submenu_page(
		'vatan-dashboard',
		__( 'Theme Settings', 'vatan-event' ),
		__( 'Theme Settings', 'vatan-event' ),
		'manage_options',
		'vatan-theme-settings',
		'vatan_theme_settings_page'
	);

	add_submenu_page(
		'vatan-dashboard',
		__( 'Seat Manager', 'vatan-event' ),
		__( 'Seats', 'vatan-event' ),
		'manage_options',
		'vatan-seat-manager',
		'vatan_seat_manager_page'
	);

	add_submenu_page(
		'vatan-dashboard',
		__( 'Sales Analytics', 'vatan-event' ),
		__( 'Sales Analytics', 'vatan-event' ),
		'manage_options',
		'vatan-sales-analytics',
		'vatan_sales_analytics_page'
	);

	add_submenu_page(
		'vatan-dashboard',
		__( 'Payouts', 'vatan-event' ),
		__( 'Payouts', 'vatan-event' ),
		'manage_options',
		'vatan-payouts',
		'vatan_payouts_page'
	);
}
add_action( 'admin_menu', 'vatan_register_admin_menu' );

/**
 * Enqueue admin-only assets, scoped to our pages by `?page=vatan-…` slug.
 *
 * @param string $hook_suffix
 */
function vatan_admin_enqueue( $hook_suffix ) {
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 0 !== strpos( $page, 'vatan-' ) ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );

	wp_enqueue_style(
		'vatan-admin',
		VATAN_EVENT_URI . '/assets/admin/css/admin.css',
		array( 'wp-color-picker' ),
		VATAN_EVENT_VERSION
	);

	wp_enqueue_script(
		'vatan-admin',
		VATAN_EVENT_URI . '/assets/admin/js/admin.js',
		array( 'jquery', 'wp-color-picker' ),
		VATAN_EVENT_VERSION,
		true
	);

	wp_localize_script( 'vatan-admin', 'vatanAdmin', array(
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'restUrl'   => esc_url_raw( rest_url( 'vatan/v1/' ) ),
		'nonce'     => wp_create_nonce( 'vatan_admin_nonce' ),
		'restNonce' => wp_create_nonce( 'wp_rest' ),
		'i18n'      => array(
			'mediaTitle'  => __( 'Choose media', 'vatan-event' ),
			'mediaButton' => __( 'Use this', 'vatan-event' ),
			'addRow'      => __( 'Add row', 'vatan-event' ),
			'removeRow'   => __( 'Remove', 'vatan-event' ),
		),
	) );

	// Chart.js only on the analytics screen — avoid pulling 200KB on every page.
	if ( 'vatan-sales-analytics' === $page ) {
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);
	}

	// Visual seat-map editor only on the seat-manager screen.
	if ( 'vatan-seat-manager' === $page ) {
		wp_enqueue_script(
			'vatan-seat-editor',
			VATAN_EVENT_URI . '/assets/admin/js/seat-editor.js',
			array(),
			VATAN_EVENT_VERSION,
			true
		);
	}

	// Page builder: load Sortable.js from CDN, plus the editor script.
	if ( 'vatan-page-builder' === $page ) {
		wp_enqueue_script(
			'sortablejs',
			'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js',
			array(),
			'1.15.2',
			true
		);
		wp_enqueue_script(
			'vatan-page-builder',
			VATAN_EVENT_URI . '/assets/admin/js/page-builder.js',
			array( 'sortablejs' ),
			VATAN_EVENT_VERSION,
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'vatan_admin_enqueue' );

/**
 * Dashboard landing page.
 */
function vatan_dashboard_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'vatan-event' ) );
	}

	$counts        = wp_count_posts( 'event' );
	$published     = isset( $counts->publish ) ? (int) $counts->publish : 0;
	$drafts        = isset( $counts->draft ) ? (int) $counts->draft : 0;
	$recent_events = get_posts( array(
		'post_type'      => 'event',
		'posts_per_page' => 5,
		'post_status'    => array( 'publish', 'draft' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	$orders_count = 0;
	$orders_total = 0.0;
	if ( function_exists( 'wc_get_orders' ) ) {
		$orders = wc_get_orders( array(
			'limit'        => -1,
			'status'       => array( 'wc-completed', 'wc-processing' ),
			'date_created' => '>' . gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
		) );
		if ( is_array( $orders ) ) {
			$orders_count = count( $orders );
			foreach ( $orders as $order ) {
				$orders_total += (float) $order->get_total();
			}
		}
	}
	?>
	<div class="wrap vatan-admin">
		<header class="vatan-admin__header">
			<h1><?php esc_html_e( 'Vatan Event Dashboard', 'vatan-event' ); ?></h1>
			<p class="vatan-admin__subtitle"><?php esc_html_e( 'Quick overview of your ticketing platform.', 'vatan-event' ); ?></p>
		</header>

		<div class="vatan-admin__cards">
			<div class="vatan-card">
				<span class="vatan-card__label"><?php esc_html_e( 'Published events', 'vatan-event' ); ?></span>
				<span class="vatan-card__value"><?php echo esc_html( vatan_to_persian_digits( $published ) ); ?></span>
				<?php if ( $drafts ) : ?>
					<span class="vatan-card__delta">
						<?php
						printf(
							/* translators: %s: number of drafts */
							esc_html__( '+ %s drafts', 'vatan-event' ),
							esc_html( vatan_to_persian_digits( $drafts ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>
			<div class="vatan-card">
				<span class="vatan-card__label"><?php esc_html_e( 'Orders (30 days)', 'vatan-event' ); ?></span>
				<span class="vatan-card__value"><?php echo esc_html( vatan_to_persian_digits( $orders_count ) ); ?></span>
			</div>
			<div class="vatan-card">
				<span class="vatan-card__label"><?php esc_html_e( 'Revenue (30 days)', 'vatan-event' ); ?></span>
				<span class="vatan-card__value"><?php echo esc_html( vatan_format_price( $orders_total ) ); ?></span>
			</div>
		</div>

		<div class="vatan-admin__columns">
			<section class="vatan-section">
				<h2><?php esc_html_e( 'Recent Events', 'vatan-event' ); ?></h2>
				<?php if ( $recent_events ) : ?>
					<ul class="vatan-recent-list">
						<?php foreach ( $recent_events as $event ) : ?>
							<li>
								<a href="<?php echo esc_url( get_edit_post_link( $event->ID ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a>
								<span class="vatan-recent-list__date"><?php echo esc_html( get_the_date( '', $event ) ); ?></span>
								<span class="vatan-recent-list__status">
									<?php echo esc_html( ucfirst( $event->post_status ) ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p><?php esc_html_e( 'No events yet — create your first one.', 'vatan-event' ); ?></p>
				<?php endif; ?>
			</section>

			<section class="vatan-section">
				<h2><?php esc_html_e( 'Quick Actions', 'vatan-event' ); ?></h2>
				<div class="vatan-action-row">
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=event' ) ); ?>"><?php esc_html_e( 'Create event', 'vatan-event' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vatan-theme-settings' ) ); ?>"><?php esc_html_e( 'Theme settings', 'vatan-event' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vatan-seat-manager' ) ); ?>"><?php esc_html_e( 'Manage seats', 'vatan-event' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vatan-sales-analytics' ) ); ?>"><?php esc_html_e( 'View analytics', 'vatan-event' ); ?></a>
				</div>
			</section>
		</div>
	</div>
	<?php
}

/**
 * Output a <style> block in wp_head with admin-supplied color overrides.
 *
 * Brand and surface tokens both go here. Surface overrides target the
 * `body.vatan-theme--{scheme}` selector so they win against the scheme's
 * defaults set in main.css. Empty-string overrides are skipped, so each
 * untouched setting keeps the scheme default.
 */
function vatan_print_dynamic_tokens() {
	if ( ! function_exists( 'vatan_get_setting' ) ) {
		return;
	}

	$scheme = (string) vatan_get_setting( 'color_scheme', 'dark' );
	if ( ! in_array( $scheme, array( 'dark', 'light' ), true ) ) {
		$scheme = 'dark';
	}

	// Brand — always available in :root.
	$primary   = sanitize_hex_color( (string) vatan_get_setting( 'primary_color' ) );
	$secondary = sanitize_hex_color( (string) vatan_get_setting( 'secondary_color' ) );

	$brand_rules = array();
	if ( $primary && '#FF2D78' !== strtoupper( $primary ) ) {
		$brand_rules[] = '--color-primary: ' . $primary . ';';
	}
	if ( $secondary && '#7C3AED' !== strtoupper( $secondary ) ) {
		$brand_rules[] = '--color-secondary: ' . $secondary . ';';
	}

	// Surface — read overrides for the ACTIVE scheme only. Each setting
	// writes to both the canonical token and its backwards-compat alias,
	// since components.css still references the alias names and CSS
	// custom-property var() chains don't propagate descendant overrides
	// (see the comment in :root in main.css).
	$surface_map = array(
		'bg'         => array( '--color-bg', '--color-dark' ),
		'surface'    => array( '--color-surface', '--color-dark-card' ),
		'border'     => array( '--color-border', '--color-dark-border' ),
		'text'       => array( '--color-text', '--color-text-primary' ),
		'text_muted' => array( '--color-text-muted', '--color-text-secondary' ),
	);
	$surface_rules = array();
	foreach ( $surface_map as $base => $tokens ) {
		$hex = sanitize_hex_color( (string) vatan_get_setting( $base . '_color_' . $scheme ) );
		if ( $hex ) {
			foreach ( $tokens as $token ) {
				$surface_rules[] = $token . ': ' . $hex . ';';
			}
		}
	}

	if ( empty( $brand_rules ) && empty( $surface_rules ) ) {
		return;
	}

	$css = '';
	if ( $brand_rules ) {
		$css .= ':root{' . implode( '', $brand_rules ) . '}';
	}
	if ( $surface_rules ) {
		$css .= 'body.vatan-theme--' . $scheme . '{' . implode( '', $surface_rules ) . '}';
	}

	echo '<style id="vatan-dynamic-tokens">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'vatan_print_dynamic_tokens', 50 );
