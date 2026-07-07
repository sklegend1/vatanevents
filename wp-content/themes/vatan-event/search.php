<?php
/**
 * Search results template.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main site-main--search">
	<div class="container">
		<header class="search-header">
			<h1 class="search-title">
				<?php
				printf(
					/* translators: %s: search query */
					esc_html__( 'Search results for: %s', 'vatan-event' ),
					'<span>' . esc_html( get_search_query() ) . '</span>'
				);
				?>
			</h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="search-results">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'search-result' ); ?>>
						<h2 class="search-result__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>
						<div class="search-result__excerpt"><?php the_excerpt(); ?></div>
					</article>
					<?php
				endwhile;
				?>
			</div>

			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No results found. Try a different keyword.', 'vatan-event' ); ?></p>
			<?php get_search_form(); ?>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
