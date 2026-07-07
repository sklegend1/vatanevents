<?php
/**
 * Generic fallback template.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="post-list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class(); ?>>
						<h2 class="post-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>
						<div class="post-excerpt"><?php the_excerpt(); ?></div>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No content found.', 'vatan-event' ); ?></p>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
