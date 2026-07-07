<?php
/**
 * Share row — social-sharing buttons for a post.
 *
 * Renders Telegram / WhatsApp / Twitter / Facebook / Email / Copy-link buttons
 * pointing to the current post's URL. Falls back to the home URL outside
 * the loop.
 *
 * Usage:
 *   get_template_part( 'template-parts/share-row' );
 *   // or with overrides:
 *   get_template_part( 'template-parts/share-row', null, array(
 *       'url'   => 'https://...',
 *       'title' => 'Some title',
 *   ) );
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$defaults = array(
	'url'   => get_permalink() ? get_permalink() : home_url( '/' ),
	'title' => get_the_title() ? get_the_title() : (string) get_bloginfo( 'name' ),
);

$args  = isset( $args ) && is_array( $args ) ? array_merge( $defaults, $args ) : $defaults;
$url   = (string) $args['url'];
$title = (string) $args['title'];

$enc_url   = rawurlencode( $url );
$enc_title = rawurlencode( $title );

// One row entry per provider. `href` is the share URL; `external` toggles
// target=_blank, `class` is appended for theming per-provider.
$providers = array(
	'telegram' => array(
		'label' => __( 'Share on Telegram', 'vatan-event' ),
		'href'  => 'https://t.me/share/url?url=' . $enc_url . '&text=' . $enc_title,
	),
	'whatsapp' => array(
		'label' => __( 'Share on WhatsApp', 'vatan-event' ),
		'href'  => 'https://api.whatsapp.com/send?text=' . $enc_title . '%20' . $enc_url,
	),
	'twitter'  => array(
		'label' => __( 'Share on Twitter', 'vatan-event' ),
		'href'  => 'https://twitter.com/intent/tweet?url=' . $enc_url . '&text=' . $enc_title,
	),
	'facebook' => array(
		'label' => __( 'Share on Facebook', 'vatan-event' ),
		'href'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $enc_url,
	),
	'email'    => array(
		'label' => __( 'Share by Email', 'vatan-event' ),
		'href'  => 'mailto:?subject=' . $enc_title . '&body=' . $enc_url,
	),
);

$platforms = function_exists( 'vatan_social_platforms' ) ? vatan_social_platforms() : array();
?>

<div class="share-row" data-vatan-share>
	<span class="share-row__label"><?php esc_html_e( 'Share:', 'vatan-event' ); ?></span>

	<ul class="share-row__list">
		<?php foreach ( $providers as $key => $p ) :
			$svg = isset( $platforms[ $key ]['svg'] ) ? $platforms[ $key ]['svg'] : '';
			$is_external = ! in_array( $key, array( 'email' ), true );
			?>
			<li class="share-row__item">
				<a
					class="share-row__btn share-row__btn--<?php echo esc_attr( $key ); ?>"
					href="<?php echo esc_url( $p['href'] ); ?>"
					aria-label="<?php echo esc_attr( $p['label'] ); ?>"
					title="<?php echo esc_attr( $p['label'] ); ?>"
					<?php if ( $is_external ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
				>
					<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — hardcoded inline SVG from helpers. ?>
					<span class="screen-reader-text"><?php echo esc_html( $p['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>

		<li class="share-row__item">
			<button
				type="button"
				class="share-row__btn share-row__btn--copy"
				data-vatan-share-copy
				data-share-url="<?php echo esc_attr( $url ); ?>"
				aria-label="<?php esc_attr_e( 'Copy link', 'vatan-event' ); ?>"
				title="<?php esc_attr_e( 'Copy link', 'vatan-event' ); ?>"
			>
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect x="9" y="9" width="11" height="11" rx="2"/>
					<path d="M5 15V6a2 2 0 0 1 2-2h9"/>
				</svg>
				<span class="share-row__copy-feedback" data-vatan-share-feedback hidden>
					<?php esc_html_e( 'Copied', 'vatan-event' ); ?>
				</span>
				<span class="screen-reader-text"><?php esc_html_e( 'Copy link', 'vatan-event' ); ?></span>
			</button>
		</li>
	</ul>
</div>
