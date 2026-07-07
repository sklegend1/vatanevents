<?php
/**
 * Template Name: Checkout
 *
 * Custom checkout shell. The WooCommerce flow is wired in a later prompt.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main site-main--checkout">
	<div class="container">
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e( 'Checkout', 'vatan-event' ); ?></h1>
		</header>

		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<div class="page-content">
				<?php the_content(); ?>
			</div>
			<?php
		endwhile;
		?>

		<div class="checkout-shell" data-vatan-checkout>
			<!-- WooCommerce checkout markup will be rendered here. -->
		</div>
	</div>
</main>

<?php
get_footer();
