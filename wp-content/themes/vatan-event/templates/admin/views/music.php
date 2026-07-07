<?php
/**
 * Admin dashboard — music management dispatcher.
 *
 * Routes `?type=` to one of the sub-templates under views/music/:
 *   overview · tracks · albums · artists · genres
 *
 * Each sub-template owns its own filters/search/pagination and inherits
 * $view + $action from page-admin.php. Tab navigation is rendered here
 * once so all sub-views share a consistent header.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$type = function_exists( 'vatan_music_admin_current_type' ) ? vatan_music_admin_current_type() : 'overview';

$tabs = array(
	'overview' => array( 'label' => __( 'Overview', 'vatan-event' ), 'icon' => '✨' ),
	'tracks'   => array( 'label' => __( 'Tracks', 'vatan-event' ),   'icon' => '🎵' ),
	'albums'   => array( 'label' => __( 'Albums', 'vatan-event' ),   'icon' => '💿' ),
	'artists'  => array( 'label' => __( 'Artists', 'vatan-event' ),  'icon' => '🎤' ),
	'genres'   => array( 'label' => __( 'Genres', 'vatan-event' ),   'icon' => '🎨' ),
	'batch'    => array( 'label' => __( 'Batch Upload', 'vatan-event' ), 'icon' => '📦' ),
);

$flash = function_exists( 'vatan_music_admin_flash_message' ) ? vatan_music_admin_flash_message() : '';
$error = function_exists( 'vatan_music_admin_error_message' ) ? vatan_music_admin_error_message() : '';
?>

<div class="vatan-music-admin">

	<nav class="vatan-music-admin__tabs" aria-label="<?php esc_attr_e( 'Music sections', 'vatan-event' ); ?>">
		<?php foreach ( $tabs as $slug => $meta ) :
			$is_active = ( $slug === $type );
			?>
			<a class="vatan-music-admin__tab<?php echo $is_active ? ' is-active' : ''; ?>"
			   href="<?php echo esc_url( vatan_music_admin_url( $slug ) ); ?>"
			   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
				<span class="vatan-music-admin__tab-icon" aria-hidden="true"><?php echo esc_html( $meta['icon'] ); ?></span>
				<span class="vatan-music-admin__tab-label"><?php echo esc_html( $meta['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $flash ) : ?>
		<div class="vatan-music-admin__flash" role="status">
			<?php echo esc_html( $flash ); ?>
		</div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="vatan-music-admin__flash vatan-music-admin__flash--error" role="alert">
			<?php echo esc_html( $error ); ?>
		</div>
	<?php endif; ?>

	<?php
	$sub_file = locate_template( 'templates/admin/views/music/' . $type . '.php', false, false );
	if ( $sub_file ) {
		include $sub_file;
	} else {
		echo '<div class="vatan-admin__placeholder"><h2>' . esc_html__( 'This section is being built.', 'vatan-event' ) . '</h2></div>';
	}
	?>

</div>
