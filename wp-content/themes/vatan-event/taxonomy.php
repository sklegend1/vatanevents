<?php
/**
 * Event taxonomy archive (event_category, event_city).
 *
 * Without this file, term archives fall back to index.php and render plain
 * <article> + excerpt instead of event cards. Mirrors archive-event.php but
 * uses the term name/description for the header.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();

$term = get_queried_object();
?>

<main class="site-main site-main--archive-event">
	<div class="container">
		<header class="archive-header">
			<h1 class="archive-title"><?php single_term_title(); ?></h1>
			<?php
			if ( $term && ! empty( $term->description ) ) {
				echo '<p class="archive-description">' . esc_html( $term->description ) . '</p>';
			}
			?>
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
			<p class="no-results"><?php esc_html_e( 'No events found in this category.', 'vatan-event' ); ?></p>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();
