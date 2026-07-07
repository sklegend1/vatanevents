<?php
/**
 * Homepage template.
 *
 * Prefers the layout composed in Vatan Event → Page Builder. When no layout
 * is saved, falls back to the hardcoded default below — so a brand-new install
 * still renders a coherent homepage out of the box.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main site-main--home">
<?php
$rendered = function_exists( 'vatan_render_page_layout' )
	? vatan_render_page_layout( 'homepage' )
	: false;

if ( ! $rendered ) :
	?>
	<?php get_template_part( 'template-parts/hero' ); ?>

	<?php get_template_part( 'template-parts/search-bar', null, array( 'floating' => true ) ); ?>

	<?php
	// Categories strip — uses the page-builder section render so the markup
	// and styles match what the admin gets when adding the categories_row
	// block. Safe to call directly even when the page builder isn't in use.
	if ( function_exists( 'vatan_render_section_categories_row' ) ) {
		vatan_render_section_categories_row( array( 'title' => __( 'Browse by category', 'vatan-event' ) ) );
	}
	?>

	<section class="hot-events">
		<div class="container">
			<header class="section-header">
				<h2 class="section-title"><?php esc_html_e( 'Hottest Events', 'vatan-event' ); ?></h2>
				<a class="section-more" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ); ?>">
					<?php esc_html_e( 'View all', 'vatan-event' ); ?>
				</a>
			</header>

			<div class="event-grid">
				<?php
				$hot = new WP_Query( array(
					'post_type'      => 'event',
					'posts_per_page' => 4,
					'post_status'    => 'publish',
				) );

				if ( $hot->have_posts() ) :
					while ( $hot->have_posts() ) :
						$hot->the_post();
						get_template_part( 'template-parts/event-card' );
					endwhile;
					wp_reset_postdata();
				else :
					echo '<p>' . esc_html__( 'No events yet.', 'vatan-event' ) . '</p>';
				endif;
				?>
			</div>
		</div>
	</section>

	<?php get_template_part( 'template-parts/newsletter' ); ?>
	<?php
endif;
?>
</main>

<?php
get_footer();
