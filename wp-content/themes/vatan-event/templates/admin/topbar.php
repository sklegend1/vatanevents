<?php
/**
 * Admin dashboard — top bar (view title + user menu).
 *
 * Receives via $args:
 *   - title (string) the current view's heading.
 *   - view  (string) the current view slug — used to render view-specific
 *                    quick actions (e.g. "+ Create event" on the events view).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$title = isset( $args['title'] ) ? (string) $args['title'] : '';
$view  = isset( $args['view'] )  ? sanitize_key( $args['view'] ) : '';
$user  = wp_get_current_user();
?>
<header class="vatan-admin__topbar">

	<div class="vatan-admin__topbar-title">
		<h1><?php echo esc_html( $title ); ?></h1>
	</div>

	<div class="vatan-admin__topbar-actions">

		<?php // View-specific quick actions ?>
		<?php if ( 'events' === $view ) : ?>
			<a class="vatan-admin__btn vatan-admin__btn--primary"
			   href="<?php echo esc_url( function_exists( 'vatan_static_page_url' ) ? vatan_static_page_url( 'create-event' ) : home_url( '/create-event/' ) ); ?>">
				+ <?php esc_html_e( 'Create event', 'vatan-event' ); ?>
			</a>
		<?php elseif ( 'payouts' === $view ) : ?>
			<a class="vatan-admin__btn vatan-admin__btn--primary"
			   href="<?php echo esc_url( vatan_admin_url( 'payouts', array( 'vatan_action' => 'new' ) ) ); ?>">
				+ <?php esc_html_e( 'Record payout', 'vatan-event' ); ?>
			</a>
		<?php endif; ?>

		<details class="vatan-admin__user-menu">
			<summary>
				<?php
				echo get_avatar(
					$user->ID,
					32,
					'',
					$user->display_name,
					array( 'class' => 'vatan-admin__user-avatar' )
				);
				?>
				<span class="vatan-admin__user-name"><?php echo esc_html( $user->display_name ); ?></span>
			</summary>
			<ul class="vatan-admin__user-dropdown">
				<li><a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'Profile', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'View site', 'vatan-event' ); ?></a></li>
				<li class="vatan-admin__user-dropdown-sep" role="presentation"></li>
				<li><a href="<?php echo esc_url( wp_logout_url( function_exists( 'vatan_auth_login_url' ) ? vatan_auth_login_url() : home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Logout', 'vatan-event' ); ?></a></li>
			</ul>
		</details>
	</div>

</header>
