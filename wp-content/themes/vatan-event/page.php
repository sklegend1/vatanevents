<?php
/**
 * Page template — fallback for every standard WP page.
 *
 * Critically uses the_content() (not the_excerpt) so shortcodes inside the
 * page body — `[woocommerce_cart]`, `[woocommerce_checkout]`, `[woocommerce_my_account]`,
 * any plugin shortcodes — actually expand. The previous fallback to index.php
 * stripped them via the_excerpt and rendered the cart page blank.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main site-main--page">
	<div class="container">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'page' ); ?>>
				<header class="page-header">
					<h1 class="page-title"><?php the_title(); ?></h1>
				</header>

				<div class="page-content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;
		?>
	</div>
</main>

<?php
get_footer();
