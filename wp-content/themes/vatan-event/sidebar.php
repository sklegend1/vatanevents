<?php
/**
 * Default sidebar.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_active_sidebar( 'sidebar-primary' ) ) {
	return;
}
?>

<aside class="sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'vatan-event' ); ?>">
	<?php dynamic_sidebar( 'sidebar-primary' ); ?>
</aside>
