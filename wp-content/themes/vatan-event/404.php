<?php
/**
 * 404 template.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main site-main--404">
	<div class="container error-404">
		<h1 class="error-404__title">404</h1>
		<p class="error-404__lead"><?php esc_html_e( 'The page you are looking for was not found.', 'vatan-event' ); ?></p>
		<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Back to homepage', 'vatan-event' ); ?>
		</a>
	</div>
</main>

<?php
get_footer();
