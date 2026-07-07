<?php
/**
 * Template for the page with slug `blog` — the "Guides" landing.
 *
 * WordPress picks `page-blog.php` automatically for any page whose slug
 * is `blog`. We run our own WP_Query for posts here so the listing
 * works regardless of how the admin has configured Settings → Reading
 * (no need to designate a "Posts page" or commit to a static homepage).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$page_title = get_the_title();
if ( '' === $page_title ) {
	$page_title = __( 'Guides', 'vatan-event' );
}

// Hero backdrop — reuse a seeded event cover.
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

// Category chip row.
$post_cats = get_terms( array(
	'taxonomy'   => 'category',
	'hide_empty' => true,
	'orderby'    => 'name',
) );

// Paginated post query — uses ?paged= from the URL.
$paged = max( 1, (int) get_query_var( 'paged' ) );
$q     = new WP_Query( array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => 9,
	'paged'          => $paged,
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
			<p class="info-hero__eyebrow"><?php esc_html_e( 'Vatan Event blog', 'vatan-event' ); ?></p>
			<h1 class="info-hero__title"><?php echo esc_html( $page_title ); ?></h1>
			<p class="info-hero__lead">
				<?php esc_html_e( 'Tips, how-tos, and stories about events, ticketing, and the people behind them.', 'vatan-event' ); ?>
			</p>
		</div>
	</section>

	<div class="container">

		<?php if ( ! is_wp_error( $post_cats ) && ! empty( $post_cats ) ) : ?>
			<nav class="blog-cat-chips" aria-label="<?php esc_attr_e( 'Categories', 'vatan-event' ); ?>" data-vatan-anim-children>
				<a class="blog-cat-chip is-active" href="<?php the_permalink(); ?>">
					<?php esc_html_e( 'All', 'vatan-event' ); ?>
				</a>
				<?php foreach ( $post_cats as $cat ) : ?>
					<a class="blog-cat-chip" href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
						<?php echo esc_html( $cat->name ); ?>
						<span class="blog-cat-chip__count"><?php echo esc_html( vatan_to_persian_digits( $cat->count ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( $q->have_posts() ) : ?>
			<div class="post-grid" data-vatan-anim-children>
				<?php
				while ( $q->have_posts() ) :
					$q->the_post();
					get_template_part( 'template-parts/post-card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>

			<?php
			// Paginated nav — paginate_links works for any WP_Query when given big/current.
			$pagination = paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '?paged=%#%',
				'current'   => $paged,
				'total'     => (int) $q->max_num_pages,
				'mid_size'  => 2,
				'prev_text' => esc_html__( 'Previous', 'vatan-event' ),
				'next_text' => esc_html__( 'Next', 'vatan-event' ),
				'type'      => 'list',
			) );
			if ( $pagination ) {
				echo '<nav class="pagination" aria-label="' . esc_attr__( 'Page navigation', 'vatan-event' ) . '">' . $pagination . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		<?php else : ?>
			<p class="no-results"><?php esc_html_e( 'No posts yet. Check back soon.', 'vatan-event' ); ?></p>
		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
