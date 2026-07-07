<?php
/**
 * Admin dashboard — left sidebar (logo + nav).
 *
 * Receives via $args:
 *   - current (string)  active view slug, used to highlight the nav item.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$current = isset( $args['current'] ) ? sanitize_key( $args['current'] ) : 'dashboard';
$items   = function_exists( 'vatan_admin_nav_items' ) ? vatan_admin_nav_items() : array();
?>
<aside class="vatan-admin__sidebar" aria-label="<?php esc_attr_e( 'Admin navigation', 'vatan-event' ); ?>">

	<a class="vatan-admin__brand" href="<?php echo esc_url( vatan_admin_url() ); ?>">
		<?php
		// Reuse the theme's site logo when an image is uploaded; fall back
		// to a compact "V" mark + label that matches the front-end chrome.
		$logo_id = function_exists( 'vatan_get_setting' ) ? (int) vatan_get_setting( 'logo_id' ) : 0;
		if ( ! $logo_id ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( $logo_id ) {
			echo wp_get_attachment_image(
				$logo_id,
				'medium',
				false,
				array(
					'class' => 'vatan-admin__brand-img',
					'alt'   => get_bloginfo( 'name' ),
				)
			);
		} else { ?>
			<span class="vatan-admin__brand-icon" aria-hidden="true">V</span>
			<span class="vatan-admin__brand-text"><?php esc_html_e( 'Vatan Admin', 'vatan-event' ); ?></span>
		<?php } ?>
	</a>

	<nav class="vatan-admin__nav">
		<?php foreach ( $items as $slug => $item ) :
			if ( ! empty( $item['cap'] ) && ! current_user_can( $item['cap'] ) ) {
				continue;
			}
			$is_active = ( $slug === $current );
			?>
			<a class="vatan-admin__nav-item<?php echo $is_active ? ' is-active' : ''; ?>"
			   href="<?php echo esc_url( $item['url'] ); ?>"
			   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
				<span class="vatan-admin__nav-icon" aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
				<span class="vatan-admin__nav-label"><?php echo esc_html( $item['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<footer class="vatan-admin__sidebar-foot">
		<a class="vatan-admin__sidebar-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			← <?php esc_html_e( 'View site', 'vatan-event' ); ?>
		</a>
	</footer>

</aside>
