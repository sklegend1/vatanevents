<?php
/**
 * Newsletter signup.
 *
 * Wires to POST /wp-json/vatan/v1/newsletter via assets/js/main.js
 * (initNewsletterForm). The form ships a `vatan_newsletter` nonce in the
 * body and a honeypot input that bots fill but humans don't see.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$title    = (string) ( function_exists( 'vatan_get_setting' ) ? vatan_get_setting( 'newsletter_title' ) : '' );
$subtitle = (string) ( function_exists( 'vatan_get_setting' ) ? vatan_get_setting( 'newsletter_subtitle' ) : '' );

// Run through Polylang's string translator when available so admins can
// translate these per-language under Languages → Strings translations.
if ( function_exists( 'vatan_pll_translate' ) ) {
	$title    = vatan_pll_translate( $title );
	$subtitle = vatan_pll_translate( $subtitle );
}
?>

<section class="newsletter">
	<div class="container newsletter__inner">
		<div class="newsletter__icon" aria-hidden="true">&#9993;</div>
		<h2 class="newsletter__title">
			<?php echo esc_html( $title ?: __( 'Vatan Event Newsletter', 'vatan-event' ) ); ?>
		</h2>
		<p class="newsletter__lead">
			<?php echo esc_html( $subtitle ?: __( 'Subscribe to hear about new events and exclusive offers before anyone else.', 'vatan-event' ) ); ?>
		</p>
		<form class="newsletter__form" data-vatan-newsletter novalidate>
			<label for="vatan-newsletter-email" class="screen-reader-text">
				<?php esc_html_e( 'Your email address', 'vatan-event' ); ?>
			</label>
			<input
				type="email"
				id="vatan-newsletter-email"
				name="email"
				class="newsletter__input"
				placeholder="<?php esc_attr_e( 'Your email address', 'vatan-event' ); ?>"
				autocomplete="email"
				required
			/>

			<?php // Honeypot — hidden from humans (visually + a11y), but bots fill it. ?>
			<label class="newsletter__honeypot" aria-hidden="true">
				<span><?php esc_html_e( 'Leave this field empty', 'vatan-event' ); ?></span>
				<input type="text" name="website" tabindex="-1" autocomplete="off" />
			</label>

			<input type="hidden" name="source" value="<?php echo esc_attr( is_singular() ? 'single-' . get_post_type() : 'site' ); ?>" />
			<?php wp_nonce_field( 'vatan_newsletter', 'vatan_newsletter_nonce' ); ?>

			<button type="submit" class="btn btn--dark newsletter__submit">
				<span class="newsletter__submit-label"><?php esc_html_e( 'Subscribe', 'vatan-event' ); ?></span>
			</button>
		</form>
		<p class="newsletter__status" data-vatan-newsletter-status role="status" aria-live="polite" hidden></p>
	</div>
</section>
