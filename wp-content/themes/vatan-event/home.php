<?php
/**
 * Blog ("Guides") archive — listing of standard `post` content. Used when
 * the user assigns a "Posts page" under Settings → Reading, and as the
 * fallback archive for `post`.
 *
 * Layout mirrors the About / Contact info pages: gradient hero with a
 * thematic backdrop, category chip row for quick filtering, then the
 * normal post grid + pagination.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

// Title — from the assigned "Posts page" if any, else a generic label.
$posts_page_id = (int) get_option( 'page_for_posts' );
$page_title    = $posts_page_id ? get_the_title( $posts_page_id ) : __( 'Guides', 'vatan-event' );

// Hero backdrop — reuse one of the seeded event covers.
$hero_image = '';
$state      = (array) get_option( 'vatan_media_seeded', array() );
foreach ( $state['attachments'] ?? array() as $key => $id ) {
	if ( str_ends_with( $key, ':hero-festival-night' ) || str_ends_with( $key, ':event-microphone' ) ) {
		$hero_image = wp_get_attachment_image_url( (int) $id, 'large' );
		if ( $hero_image ) {
			break;
		}
	}
}

// Active filter (when this template renders a category archive via fall-through).
$active_cat = is_category() ? get_queried_object() : null;

// Categories that have posts.
$post_cats = get_terms( array(
	'taxonomy'   => 'category',
	'hide_empty' => true,
	'orderby'    => 'name',
) );

get_header();
?>

<main class="site-main site-main--blog">

	<section class="info-hero info-hero--blog">
		<?php if ( $hero_image ) : ?>
			<img class="info-hero__bg" src="<?php echo esc_url( $hero_image ); ?>" alt="" loading="eager" />
		<?php endif; ?>
		<div class="info-hero__overlay" aria-hidden="true"></div>
		<div class="container info-hero__inner">
			<p class="info-hero__eyebrow">
				<?php esc_html_e( 'Vatan Event blog', 'vatan-event' ); ?>
			</p>
			<h1 class="info-hero__title">
				<?php echo esc_html( $page_title ); ?>
			</h1>
			<p class="info-hero__lead">
				<?php esc_html_e( 'Tips, how-tos, and stories about events, ticketing, and the people behind them.', 'vatan-event' ); ?>
			</p>
		</div>
	</section>

	<div class="container">

		<?php if ( ! is_wp_error( $post_cats ) && ! empty( $post_cats ) ) : ?>
			<nav class="blog-cat-chips" aria-label="<?php esc_attr_e( 'Categories', 'vatan-event' ); ?>" data-vatan-anim-children>
				<a class="blog-cat-chip<?php echo ( ! $active_cat ) ? ' is-active' : ''; ?>"
				   href="<?php echo esc_url( get_post_type_archive_link( 'post' ) ?: home_url( '/blog/' ) ); ?>">
					<?php esc_html_e( 'All', 'vatan-event' ); ?>
				</a>
				<?php foreach ( $post_cats as $cat ) :
					$active = $active_cat && (int) $active_cat->term_id === (int) $cat->term_id;
					?>
					<a class="blog-cat-chip<?php echo $active ? ' is-active' : ''; ?>"
					   href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
						<?php echo esc_html( $cat->name ); ?>
						<span class="blog-cat-chip__count"><?php echo esc_html( vatan_to_persian_digits( $cat->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="post-grid" data-vatan-anim-children>
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/post-card' );
				endwhile;
				?>
			</div>

			<?php the_posts_pagination( array(
				'mid_size'  => 2,
				'prev_text' => esc_html__( 'Previous', 'vatan-event' ),
				'next_text' => esc_html__( 'Next', 'vatan-event' ),
			) ); ?>
		<?php else : ?>
			<p class="no-results"><?php esc_html_e( 'No posts yet. Check back soon.', 'vatan-event' ); ?></p>
		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
