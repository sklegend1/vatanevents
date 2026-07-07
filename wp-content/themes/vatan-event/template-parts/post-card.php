<?php
/**
 * Post (blog) card.
 *
 * Designed to run inside The Loop. Shape mirrors `event-card.php` so the
 * same `.event-grid`-style wrappers can hold either flavour, but with
 * post-specific classes for independent styling.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$post_id   = get_the_ID();
$permalink = get_permalink( $post_id );
$thumb     = get_the_post_thumbnail( $post_id, 'medium_large', array( 'class' => 'post-card__image' ) );

$categories = get_the_category( $post_id );
$category   = ! empty( $categories ) ? $categories[0] : null;

$reading_minutes = 0;
$word_count = str_word_count( wp_strip_all_tags( get_the_content() ) );
if ( $word_count > 0 ) {
	$reading_minutes = max( 1, (int) ceil( $word_count / 200 ) );
}
?>

<article class="post-card" id="post-card-<?php the_ID(); ?>">
	<a class="post-card__media-link" href="<?php echo esc_url( $permalink ); ?>" aria-hidden="true" tabindex="-1">
		<div class="post-card__media">
			<?php
			if ( $thumb ) {
				echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — markup from get_the_post_thumbnail.
			}
			?>
			<?php if ( $category ) : ?>
				<span class="post-card__category"><?php echo esc_html( $category->name ); ?></span>
			<?php endif; ?>
		</div>
	</a>

	<div class="post-card__body">
		<h3 class="post-card__title">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php the_title(); ?></a>
		</h3>

		<?php if ( has_excerpt() || get_the_excerpt() ) : ?>
			<p class="post-card__excerpt">
				<?php echo esc_html( wp_trim_words( get_the_excerpt(), 24, '…' ) ); ?>
			</p>
		<?php endif; ?>

		<footer class="post-card__meta">
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" class="post-card__date">
				<?php echo esc_html( vatan_to_persian_digits( get_the_date() ) ); ?>
			</time>
			<?php if ( $reading_minutes > 0 ) : ?>
				<span class="post-card__sep" aria-hidden="true">·</span>
				<span class="post-card__reading">
					<?php
					printf(
						/* translators: %s: reading time in minutes */
						esc_html( _n( '%s min read', '%s min read', $reading_minutes, 'vatan-event' ) ),
						esc_html( vatan_to_persian_digits( $reading_minutes ) )
					);
					?>
				</span>
			<?php endif; ?>
		</footer>
	</div>
</article>
