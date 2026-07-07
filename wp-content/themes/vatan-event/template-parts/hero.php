<?php
/**
 * Hero block — carousel + legacy single-slide fallback.
 *
 * Reads `hero_slides` from theme settings (repeater of {image,eyebrow,title,
 * title_highlight,subtitle,primary_label/url,secondary_label/url}). If empty,
 * synthesizes a single slide from the legacy `hero_*` settings + theme_mod
 * so existing installs keep rendering.
 *
 * Hydration is by /assets/js/main.js — HeroCarousel class.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$slides = function_exists( 'vatan_get_setting' ) ? (array) vatan_get_setting( 'hero_slides' ) : array();

// Build a legacy fallback slide from the older hero_* settings + theme_mod.
if ( empty( $slides ) ) {
	$legacy_image = function_exists( 'vatan_get_setting' ) ? (int) vatan_get_setting( 'hero_image_id' ) : 0;
	if ( ! $legacy_image ) {
		$mod = get_theme_mod( 'vatan_hero_image' );
		if ( $mod && is_numeric( $mod ) ) {
			$legacy_image = (int) $mod;
		}
	}

	$slides = array(
		array(
			'image_id'        => $legacy_image,
			'eyebrow'         => function_exists( 'vatan_get_setting' ) ? (string) vatan_get_setting( 'hero_title' ) : '',
			'title'           => '',
			'title_highlight' => '',
			'subtitle'        => function_exists( 'vatan_get_setting' ) ? (string) vatan_get_setting( 'hero_subtitle' ) : '',
			'primary_label'   => function_exists( 'vatan_get_setting' ) ? (string) vatan_get_setting( 'hero_btn_label' ) : '',
			'primary_url'     => function_exists( 'vatan_get_setting' ) ? (string) vatan_get_setting( 'hero_btn_link' ) : '',
			'secondary_label' => '',
			'secondary_url'   => '',
		),
	);

	// Default copy when nothing is configured anywhere yet.
	if ( '' === $slides[0]['eyebrow'] && '' === $slides[0]['subtitle'] ) {
		$slides[0]['eyebrow']         = __( 'Special Sale', 'vatan-event' );
		$slides[0]['title']           = __( 'Echo of Iranian music around the world', 'vatan-event' );
		$slides[0]['title_highlight'] = __( 'Iranian music', 'vatan-event' );
		$slides[0]['subtitle']        = __( 'Experience the most beloved Iranian artists live, anywhere in the world. Book your ticket from the most trusted ticketing platform.', 'vatan-event' );
		$slides[0]['primary_label']   = __( 'Quick book ticket', 'vatan-event' );
		$archive_url                  = get_post_type_archive_link( 'event' );
		$slides[0]['primary_url']     = is_string( $archive_url ) ? $archive_url : home_url( '/' );
		$slides[0]['secondary_label'] = __( 'Watch trailers', 'vatan-event' );
		$slides[0]['secondary_url']   = '#trailers';
	}
}

// Pipe every translatable text field on each slide through Polylang's
// string translator when available. Admins manage translations under
// Languages → Strings translations (group: Vatan Event). Without Polylang
// this is a no-op pass-through.
if ( function_exists( 'vatan_pll_translate' ) ) {
	foreach ( $slides as &$_slide ) {
		foreach ( array( 'eyebrow', 'title', 'title_highlight', 'subtitle', 'primary_label', 'secondary_label' ) as $_key ) {
			if ( isset( $_slide[ $_key ] ) && '' !== $_slide[ $_key ] ) {
				$_slide[ $_key ] = vatan_pll_translate( (string) $_slide[ $_key ] );
			}
		}
	}
	unset( $_slide );
}

$slide_count = count( $slides );
$has_carousel = $slide_count > 1;
?>

<section
	class="hero<?php echo $has_carousel ? ' hero--carousel' : ''; ?>"
	data-vatan-hero-carousel
	data-autoplay="<?php echo esc_attr( (string) apply_filters( 'vatan_hero_autoplay_ms', 6000 ) ); ?>"
	aria-roledescription="carousel"
>
	<div class="hero__slides" data-vatan-hero-track>
		<?php foreach ( $slides as $index => $slide ) :
			$image_id = isset( $slide['image_id'] ) ? (int) $slide['image_id'] : 0;
			$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

			$title     = isset( $slide['title'] ) ? (string) $slide['title'] : '';
			$highlight = isset( $slide['title_highlight'] ) ? trim( (string) $slide['title_highlight'] ) : '';
			$title_html = esc_html( $title );
			if ( $title && $highlight && false !== mb_stripos( $title, $highlight ) ) {
				// Case-insensitive replace of FIRST occurrence with an accent span.
				$pos = mb_stripos( $title, $highlight );
				$len = mb_strlen( $highlight );
				$pre   = mb_substr( $title, 0, $pos );
				$match = mb_substr( $title, $pos, $len );
				$post  = mb_substr( $title, $pos + $len );
				$title_html = esc_html( $pre )
					. '<span class="hero__title-accent">' . esc_html( $match ) . '</span>'
					. esc_html( $post );
			}
			?>
			<article
				class="hero__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
				data-slide-index="<?php echo esc_attr( $index ); ?>"
				role="group"
				aria-roledescription="slide"
				aria-label="<?php echo esc_attr( sprintf( __( 'Slide %1$d of %2$d', 'vatan-event' ), $index + 1, $slide_count ) ); ?>"
				<?php echo 0 !== $index ? 'aria-hidden="true"' : ''; ?>
			>
				<?php if ( $image ) : ?>
					<img class="hero__slide-bg" src="<?php echo esc_url( $image ); ?>" alt="" loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>" />
				<?php else : ?>
					<div class="hero__slide-bg hero__slide-bg--gradient" aria-hidden="true"></div>
				<?php endif; ?>

				<div class="hero__slide-overlay" aria-hidden="true"></div>

				<div class="container hero__slide-inner">
					<?php if ( ! empty( $slide['eyebrow'] ) ) : ?>
						<span class="hero__badge">
							<?php echo esc_html( $slide['eyebrow'] ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $title ) : ?>
						<h1 class="hero__title">
							<?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — already escaped above. ?>
						</h1>
					<?php endif; ?>

					<?php if ( ! empty( $slide['subtitle'] ) ) : ?>
						<p class="hero__lead">
							<?php echo esc_html( $slide['subtitle'] ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $slide['primary_label'] ) || ! empty( $slide['secondary_label'] ) ) : ?>
						<div class="hero__actions">
							<?php if ( ! empty( $slide['primary_label'] ) ) : ?>
								<a class="btn btn--primary btn--lg" href="<?php echo esc_url( $slide['primary_url'] ?: '#' ); ?>">
									<?php echo esc_html( $slide['primary_label'] ); ?>
								</a>
							<?php endif; ?>
							<?php if ( ! empty( $slide['secondary_label'] ) ) : ?>
								<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( $slide['secondary_url'] ?: '#' ); ?>">
									<svg class="btn__icon" aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
										<path d="M8 5v14l11-7z"/>
									</svg>
									<?php echo esc_html( $slide['secondary_label'] ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<?php if ( $has_carousel ) : ?>
		<button type="button" class="hero__nav hero__nav--prev" data-vatan-hero-prev aria-label="<?php esc_attr_e( 'Previous slide', 'vatan-event' ); ?>">
			<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
				<polyline points="15 18 9 12 15 6"></polyline>
			</svg>
		</button>
		<button type="button" class="hero__nav hero__nav--next" data-vatan-hero-next aria-label="<?php esc_attr_e( 'Next slide', 'vatan-event' ); ?>">
			<svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
				<polyline points="9 18 15 12 9 6"></polyline>
			</svg>
		</button>

		<div class="hero__dots" data-vatan-hero-dots role="tablist" aria-label="<?php esc_attr_e( 'Slide selection', 'vatan-event' ); ?>">
			<?php for ( $i = 0; $i < $slide_count; $i++ ) : ?>
				<button
					type="button"
					class="hero__dot<?php echo 0 === $i ? ' is-active' : ''; ?>"
					data-slide-index="<?php echo esc_attr( $i ); ?>"
					role="tab"
					aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
					aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide %d', 'vatan-event' ), $i + 1 ) ); ?>"
				></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</section>
