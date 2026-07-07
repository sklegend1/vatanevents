<?php
/**
 * Single blog post template.
 *
 * Used for standard WordPress posts (post-type `post`). Custom CPTs have
 * their own single-{type}.php templates (single-event.php for events).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$post_id    = get_the_ID();
	$thumb_url  = get_the_post_thumbnail_url( $post_id, 'full' );
	$categories = get_the_category( $post_id );
	$category   = ! empty( $categories ) ? $categories[0] : null;
	$author_id  = (int) get_the_author_meta( 'ID' );

	$word_count      = str_word_count( wp_strip_all_tags( get_the_content() ) );
	$reading_minutes = $word_count > 0 ? max( 1, (int) ceil( $word_count / 200 ) ) : 0;
	?>

	<main class="site-main site-main--single-post">

		<!-- Breadcrumb -->
		<nav class="container breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'vatan-event' ); ?>">
			<ol class="breadcrumb__list">
				<li class="breadcrumb__item">
					<a class="breadcrumb__link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php esc_html_e( 'Home', 'vatan-event' ); ?>
					</a>
				</li>
				<li class="breadcrumb__sep" aria-hidden="true">›</li>
				<li class="breadcrumb__item">
					<a class="breadcrumb__link" href="<?php echo esc_url( get_post_type_archive_link( 'post' ) ?: home_url( '/blog/' ) ); ?>">
						<?php esc_html_e( 'Guides', 'vatan-event' ); ?>
					</a>
				</li>
				<?php if ( $category ) : ?>
					<li class="breadcrumb__sep" aria-hidden="true">›</li>
					<li class="breadcrumb__item">
						<a class="breadcrumb__link" href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>">
							<?php echo esc_html( $category->name ); ?>
						</a>
					</li>
				<?php endif; ?>
				<li class="breadcrumb__sep" aria-hidden="true">›</li>
				<li class="breadcrumb__item breadcrumb__item--current" aria-current="page">
					<?php the_title(); ?>
				</li>
			</ol>
		</nav>

		<!-- Hero -->
		<header class="container post-hero">
			<?php if ( $category ) : ?>
				<a class="post-hero__category" href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>">
					<?php echo esc_html( $category->name ); ?>
				</a>
			<?php endif; ?>

			<h1 class="post-hero__title"><?php the_title(); ?></h1>

			<div class="post-hero__meta">
				<span class="post-hero__author">
					<?php
					echo get_avatar( $author_id, 28, '', '', array( 'class' => 'post-hero__avatar' ) );
					?>
					<span><?php the_author(); ?></span>
				</span>
				<span class="post-hero__sep" aria-hidden="true">·</span>
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
					<?php echo esc_html( vatan_to_persian_digits( get_the_date() ) ); ?>
				</time>
				<?php if ( $reading_minutes > 0 ) : ?>
					<span class="post-hero__sep" aria-hidden="true">·</span>
					<span>
						<?php
						printf(
							/* translators: %s: reading time in minutes */
							esc_html( _n( '%s min read', '%s min read', $reading_minutes, 'vatan-event' ) ),
							esc_html( vatan_to_persian_digits( $reading_minutes ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( $thumb_url ) : ?>
				<figure class="post-hero__cover">
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
				</figure>
			<?php endif; ?>
		</header>

		<!-- Body -->
		<div class="container post-layout">
			<article class="post-content">
				<?php the_content(); ?>

				<?php wp_link_pages( array(
					'before'         => '<nav class="post-content__pagination" aria-label="' . esc_attr__( 'Page navigation', 'vatan-event' ) . '"><span>' . esc_html__( 'Pages:', 'vatan-event' ) . '</span> ',
					'after'          => '</nav>',
					'next_or_number' => 'number',
				) ); ?>
			</article>

			<!-- Share row -->
			<?php get_template_part( 'template-parts/share-row' ); ?>

			<!-- Tags -->
			<?php
			$tags = get_the_tags( $post_id );
			if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) :
				?>
				<footer class="post-tags">
					<span class="post-tags__label"><?php esc_html_e( 'Tags:', 'vatan-event' ); ?></span>
					<?php foreach ( $tags as $tag ) : ?>
						<a class="post-tag" href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>">
							#<?php echo esc_html( $tag->name ); ?>
						</a>
					<?php endforeach; ?>
				</footer>
			<?php endif; ?>
		</div>

		<!-- Related guides -->
		<?php
		$rel_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => 1,
			'no_found_rows'       => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		);
		if ( $category ) {
			$rel_args['category__in'] = array( $category->term_id );
		}
		$related = new WP_Query( $rel_args );
		if ( $related->have_posts() ) : ?>
			<section class="container related-posts">
				<header class="related-posts__head">
					<h2 class="section-title"><?php esc_html_e( 'More guides', 'vatan-event' ); ?></h2>
				</header>
				<div class="post-grid">
					<?php while ( $related->have_posts() ) :
						$related->the_post();
						get_template_part( 'template-parts/post-card' );
					endwhile; ?>
				</div>
			</section>
			<?php wp_reset_postdata(); ?>
		<?php endif; ?>

	</main>

	<?php
endwhile;

get_footer();
