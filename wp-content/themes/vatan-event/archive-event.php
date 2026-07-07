<?php
/**
 * Event archive template.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main site-main--archive-event">
	<div class="container">
		<header class="archive-header">
			<h1 class="archive-title"><?php post_type_archive_title(); ?></h1>
			<?php the_archive_description( '<p class="archive-description">', '</p>' ); ?>
		</header>

		<?php get_template_part( 'template-parts/search-bar' ); ?>

		<?php if ( have_posts() ) : ?>
			<div class="event-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/event-card' );
				endwhile;
				?>
			</div>

			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p class="no-results"><?php esc_html_e( 'No events found.', 'vatan-event' ); ?></p>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
