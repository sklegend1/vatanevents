<?php
/**
 * Template Name: Vatan — Admin Dashboard
 *
 * Top-level template for the frontend admin UI at /admin/. Auto-applied
 * to the page with slug `admin` (seeded by vatan_static_page_definitions).
 * Builds its own minimal HTML shell — no site header/footer — and routes
 * to a view file under templates/admin/views/<view>.php based on the
 * `view` query var.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* -- Capability gate --------------------------------------------------- */

if ( ! is_user_logged_in() ) {
	$return_to = function_exists( 'vatan_admin_url' ) ? vatan_admin_url() : home_url( '/admin/' );
	wp_safe_redirect( function_exists( 'vatan_auth_login_url' ) ? vatan_auth_login_url( $return_to ) : wp_login_url( $return_to ) );
	exit;
}

if ( ! function_exists( 'vatan_admin_can_access' ) || ! vatan_admin_can_access() ) {
	wp_die(
		esc_html__( 'You do not have permission to access this page.', 'vatan-event' ),
		'',
		array( 'response' => 403 )
	);
}

/* -- Resolve the view + per-view title --------------------------------- */

$view   = function_exists( 'vatan_admin_current_view' ) ? vatan_admin_current_view() : 'dashboard';
$action = sanitize_key( (string) get_query_var( 'vatan_action' ) );

$view_titles = array(
	'dashboard' => __( 'Dashboard', 'vatan-event' ),
	'events'    => __( 'Events', 'vatan-event' ),
	'sales'     => __( 'Sales analytics', 'vatan-event' ),
	'payouts'   => __( 'Payouts', 'vatan-event' ),
	'scanner'   => __( 'Door scanner', 'vatan-event' ),
	'music'     => __( 'Music', 'vatan-event' ),
);
$page_title = $view_titles[ $view ] ?? __( 'Admin', 'vatan-event' );

/* -- HTML shell ------------------------------------------------------- */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<title><?php echo esc_html( $page_title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'vatan-admin-body' ); ?>>
<?php wp_body_open(); ?>

<div class="vatan-admin">
	<?php get_template_part( 'templates/admin/sidebar', null, array( 'current' => $view ) ); ?>

	<div class="vatan-admin__main">
		<?php get_template_part( 'templates/admin/topbar', null, array( 'title' => $page_title, 'view' => $view ) ); ?>

		<div class="vatan-admin__view vatan-admin__view--<?php echo esc_attr( $view ); ?>">
			<?php
			// Each view is its own file under templates/admin/views/. The
			// view template has access to $view, $action, and standard WP
			// globals. Falls back to a friendly placeholder if missing
			// (useful during phased rollout — Batch C adds the views).
			$view_file = locate_template( 'templates/admin/views/' . $view . '.php', false, false );
			if ( $view_file ) {
				include $view_file;
			} else {
				echo '<div class="vatan-admin__placeholder">';
				echo '<h2>' . esc_html__( 'This view is being built.', 'vatan-event' ) . '</h2>';
				echo '<p>' . esc_html__( 'Come back after the next deploy — the rest of the dashboard ships soon.', 'vatan-event' ) . '</p>';
				echo '</div>';
			}
			?>
		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
